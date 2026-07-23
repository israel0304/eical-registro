<?php

namespace App\Http\Controllers;

use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        $headers = fgetcsv($handle, 0, "\t");

        if ($headers === false) {
            fclose($handle);

            return back()->withErrors(['csv_file' => 'El archivo está vacío o tiene un formato incorrecto.']);
        }

        $headerMap = array_map('trim', $headers);

        $ponenteRole = Role::where('name', 'Ponente')->first();
        if (! $ponenteRole) {
            $ponenteRole = Role::create(['name' => 'Ponente']);
        }

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            $data = array_combine($headerMap, $row);

            $estado = trim($data['Estado'] ?? '');
            if (strtolower($estado) !== 'aceptada' && strtolower($estado) !== 'accepted') {
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
            ]);

            for ($i = 1; $i <= 5; $i++) {
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
                        'role_id' => $ponenteRole->id,
                        'password' => Hash::make(Str::random(16)),
                        'is_active' => true,
                        'activation_token' => Str::random(60),
                    ]
                );

                $presentation->authors()->attach($user->id, ['author_order' => $i]);
            }

            $imported++;
        }

        fclose($handle);

        $message = "Importación completada: $imported ponencias importadas";
        if ($skipped > 0) {
            $message .= ", $skipped omitidas";
        }

        return back()->with('success', $message.'.');
    }
}
