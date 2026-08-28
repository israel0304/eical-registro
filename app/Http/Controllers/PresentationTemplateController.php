<?php

namespace App\Http\Controllers;

use App\Models\Presentation;
use App\Models\Setting;
use App\Support\EventSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PresentationTemplateController extends Controller
{
    public function update(Request $request)
    {
        abort_unless($request->user()->can('presentations.template'), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'extensions:pptx,potx', 'max:20480'],
        ]);

        $file = $validated['file'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return back()->withErrors(['file' => 'No se pudo subir el archivo.']);
        }

        $this->deleteCurrentFile();

        $filename = 'plantilla-presentacion-'.now()->format('Ymd-His').'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('presentation-template', $filename, 'public');

        if ($path === false) {
            return back()->withErrors(['file' => 'No se pudo guardar el archivo.']);
        }

        Setting::updateOrCreate(['key' => 'plantilla_presentacion_path'], ['value' => $path]);
        Setting::updateOrCreate(['key' => 'plantilla_presentacion_nombre'], ['value' => $file->getClientOriginalName()]);

        return back()->with('success', 'Plantilla de presentación subida correctamente.');
    }

    public function destroy(Request $request)
    {
        abort_unless($request->user()->can('presentations.template'), 403);

        $this->deleteCurrentFile();
        Setting::where('key', 'plantilla_presentacion_path')->delete();
        Setting::where('key', 'plantilla_presentacion_nombre')->delete();

        return back()->with('success', 'Plantilla de presentación eliminada.');
    }

    public function download(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('presentations.my'), 403);

        $hasAccepted = Presentation::query()
            ->where('status', Presentation::STATUS_ACEPTADA)
            ->whereHas('authors', fn ($q) => $q->where('users.id', $request->user()->id))
            ->exists();

        abort_unless($hasAccepted, 403, 'Tu ponencia debe estar aceptada para descargar este recurso.');

        $path = EventSettings::presentationTemplatePath();
        $name = EventSettings::presentationTemplateName();

        abort_unless($path !== null && Storage::disk('public')->exists($path), 404, 'La plantilla de presentación aún no está disponible.');

        return Storage::disk('public')->download($path, $name ?? basename($path));
    }

    private function deleteCurrentFile(): void
    {
        $path = EventSettings::presentationTemplatePath();

        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
