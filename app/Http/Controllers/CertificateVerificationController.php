<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateRenderer;

class CertificateVerificationController extends Controller
{
    public function show(string $folio, CertificateRenderer $renderer)
    {
        $certificate = Certificate::query()
            ->with(['user', 'participationType', 'role'])
            ->where('folio', $folio)
            ->firstOrFail();

        $resolved = $renderer->resolveMetadata($certificate);
        $metadata = $resolved !== null
            ? array_merge($certificate->metadata ?? [], $resolved)
            : ($certificate->metadata ?? []);

        $eventName = $metadata['evento'] ?? '—';
        $eventDate = $metadata['fecha_evento'] ?? '—';
        $eventLocation = $metadata['location'] ?? null;

        return response()
            ->view('certificates.verificar', [
                'certificate' => $certificate,
                'user' => $certificate->user,
                'participationType' => $certificate->participationType,
                'eventName' => $eventName,
                'eventDate' => $eventDate,
                'eventLocation' => $eventLocation,
                'metadata' => $metadata,
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
