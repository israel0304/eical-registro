<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\User;
use App\Services\CertificateRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GafeteController extends Controller
{
    public function __construct(private readonly CertificateRenderer $renderer) {}

    public function show(Request $request)
    {
        $user = $request->user();
        $user->ensureCheckinToken();

        return Inertia::render('Gafete/Index', [
            'user' => [
                'id' => $user->id,
                'name' => trim($user->first_name.' '.$user->last_name),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'dni' => $user->dni,
                'affiliation' => $user->affiliation,
                'role' => $this->roleLabel($user),
                'profile_photo_path' => $user->profile_photo_path,
                'checkin_token' => $user->checkin_token,
                'has_photo' => (bool) $user->profile_photo_path,
            ],
            'eventoNombre' => Setting::query()->where('key', 'evento_nombre')->value('value') ?? 'EICAL 2026',
            'checkinEnabled' => (bool) Setting::query()->where('key', 'evento_checkin_enabled')->value('value'),
            'checkedIn' => Attendance::query()
                ->where('user_id', $user->id)
                ->whereNull('workshop_id')
                ->whereNull('presentation_id')
                ->where('event_day', now()->format('Y-m-d'))
                ->exists(),
            'printUrl' => route('gafete.print'),
        ]);
    }

    public function print(Request $request)
    {
        abort_unless($request->user()->can('gafete.view'), 403);

        return response($this->renderer->renderBadge($request->user()), 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    public function printPdf(Request $request)
    {
        abort_unless($request->user()->can('gafete.view'), 403);

        $user = $request->user();

        return response($this->renderer->renderBadgePdf($user), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename=gafete_'.$user->dni.'.pdf',
        ]);
    }

    public function staffPrint(Request $request, User $user)
    {
        abort_unless($request->user()->can('checkin.scan'), 403);

        return response($this->renderer->renderBadge($user), 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    public function staffPrintPdf(Request $request, User $user)
    {
        abort_unless($request->user()->can('checkin.scan'), 403);

        return response($this->renderer->renderBadgePdf($user), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename=gafete_'.$user->dni.'.pdf',
        ]);
    }

    public function userPrint(Request $request, User $user)
    {
        abort_unless($request->user()->can('users.view'), 403);

        return response($this->renderer->renderBadge($user), 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    public function asistentesBadgesPrint(Request $request)
    {
        abort_unless($request->user()->can('users.view'), 403);
        ini_set('memory_limit', '512M');

        return response($this->renderer->renderBadges($this->activeUsers()), 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    public function asistentesBadgesPrintPdf(Request $request)
    {
        abort_unless($request->user()->can('users.view'), 403);
        ini_set('memory_limit', '512M');

        return response($this->renderer->renderBadgesPdf($this->activeUsers()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename=credenciales_asistentes_'.date('Y-m-d').'.pdf',
        ]);
    }

    private function activeUsers(): Collection
    {
        return User::query()
            ->with('roles:id,name')
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function uploadPhoto(Request $request)
    {
        abort_unless($request->user()->can('gafete.view'), 403);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $request->file('photo')->store('profile-photos', 'public');

        if (! is_string($path) || $path === '') {
            return back()->withErrors(['photo' => 'No se pudo guardar la imagen. Verifica permisos de almacenamiento.']);
        }

        $user->update(['profile_photo_path' => $path]);

        return back()->with('success', 'Foto de perfil actualizada.');
    }

    public function destroyPhoto(Request $request)
    {
        abort_unless($request->user()->can('gafete.view'), 403);

        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->update(['profile_photo_path' => null]);

        return back()->with('success', 'Foto de perfil eliminada.');
    }

    public function scanInfo(Request $request)
    {
        $token = $request->query('token');

        return response(<<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Código de acceso EICAL</title>
    <style>
        body { margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f1f5f9; color: #0f172a; }
        .card { text-align: center; max-width: 420px; padding: 40px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .title { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
        .text { color: #475569; font-size: 15px; line-height: 1.5; }
        .token { margin-top: 16px; font-family: ui-monospace, monospace; font-size: 14px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 10px; color: #0f172a; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">Credencial de acceso</div>
        <p class="text">Este código corresponde a la credencial de un participante. Acércate a la mesa de registro para escanear tu gafete.</p>
        <div class="token">{$token}</div>
    </div>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']);
    }

    private function roleLabel(User $user): string
    {
        $labels = [
            'Administrator' => 'Administración',
            'Ponente' => 'Ponente',
            'Asistente' => 'Asistente',
            'Instructor' => 'Instructor',
            'Speaker' => 'Speaker',
            'Moderator' => 'Moderador',
        ];

        $roles = $user->roles->map(
            fn ($role) => $labels[$role->name] ?? $role->name,
        );

        return $roles->isNotEmpty() ? $roles->join(' | ') : 'Participante';
    }
}
