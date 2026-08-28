<?php

namespace App\Http\Controllers;

use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use App\Services\EventAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PresentationImportController extends Controller
{
    public function index()
    {
        return Inertia::render('Presentations/Import', []);
    }

    public function store(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return back()->withErrors(['csv_file' => 'No se pudo leer el archivo.']);
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return back()->withErrors(['csv_file' => 'El archivo está vacío.']);
        }

        $commas = substr_count($firstLine, ',');
        $tabs = substr_count($firstLine, "\t");
        $delimiter = $commas >= $tabs ? ',' : "\t";

        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);

        if ($headers === false) {
            fclose($handle);

            return back()->withErrors(['csv_file' => 'El archivo está vacío o tiene un formato incorrecto.']);
        }

        $headerMap = array_map('trim', $headers);
        $headerMap[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headerMap[0]);

        $ponenteRole = Role::where('name', 'Ponente')->first();
        if (! $ponenteRole) {
            $ponenteRole = Role::create(['name' => 'Ponente']);
        }

        $imported = 0;
        $skipped = 0;
        $malformed = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($headerMap) !== count($row)) {
                $malformed++;

                continue;
            }

            $data = array_combine($headerMap, $row);

            $estado = trim($data['Estado'] ?? '');
            if (! in_array(strtolower($estado), ['aceptada', 'aceptado', 'accepted'])) {
                $skipped++;

                continue;
            }

            $submissionId = trim($data['Id. del envío'] ?? '');
            $title = trim($data['Título'] ?? '');

            if (empty($submissionId) || empty($title)) {
                $skipped++;

                continue;
            }

            $existingPresentation = Presentation::where('submission_id', $submissionId)->first();
            if ($existingPresentation) {
                $skipped++;

                continue;
            }

            $presentation = Presentation::create([
                'submission_id' => $submissionId,
                'title' => $title,
                'abstract' => trim($data['Resumen'] ?? ''),
                'discipline' => trim($data['Disciplina(s)'] ?? ''),
                'keywords' => trim($data['Palabras clave'] ?? ''),
                'status' => Presentation::STATUS_ACEPTADA,
            ]);

            for ($i = 1; $i <= 10; $i++) {
                $firstName = trim($data["Nombre (Autor/a $i)"] ?? '');
                $lastName = trim($data["Apellidos (Autor/a $i)"] ?? '');
                $email = trim($data["Correo electrónico (Autor/a $i)"] ?? '');

                if (empty($firstName) || empty($email)) {
                    continue;
                }

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'dni' => 'CNV-'.strtoupper(Str::random(7)),
                        'affiliation' => trim($data["Afiliación (Autor/a $i)"] ?? ''),
                        'country' => trim($data["País (Autor/a $i)"] ?? ''),
                        'password' => Hash::make(Str::random(16)),
                        'is_active' => true,
                        'activation_token' => Str::random(60),
                    ]
                );

                $user->roles()->syncWithoutDetaching([Role::where('name', 'Ponente')->value('id')]); // Ponente

                // Enviar email de activación de cuenta
                try {
                    EventAudit::emit('presentation.accepted', $user, $request->user(), [
                        'destinatario' => $user->email,
                        'nombre_completo' => $user->name,
                        'nombre' => $user->first_name,
                        'submission_id' => $submissionId,
                        'url_activacion' => url('/ponente/activar'),
                    ]);
                } catch (\Throwable $e) {
                    Log::error("Error enviando invitación a {$user->email}: ".$e->getMessage());
                }

                $presentation->authors()->attach($user->id, ['author_order' => $i]);
            }

            $imported++;
        }

        fclose($handle);

        $message = "Importación completada: $imported ponencias importadas";
        if ($skipped > 0) {
            $message .= ", $skipped omitidas";
        }
        if ($malformed > 0) {
            $message .= ". Se encontraron $malformed filas con formato incorrecto y no se procesaron";
        }

        return back()->with('success', $message.'.');
    }
}
