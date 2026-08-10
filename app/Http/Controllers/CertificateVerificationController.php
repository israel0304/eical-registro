<?php

namespace App\Http\Controllers;

use App\Models\Certificate;

class CertificateVerificationController extends Controller
{
    public function show(string $folio)
    {
        $certificate = Certificate::query()
            ->with(['user', 'participationType'])
            ->where('folio', $folio)
            ->firstOrFail();

        $metadata = $certificate->metadata ?? [];
        $eventName = $metadata['evento'] ?? '—';
        $eventDate = $metadata['fecha_evento'] ?? '—';
        $eventLocation = $metadata['location'] ?? null;

        return view('certificates.verificar', [
            'certificate' => $certificate,
            'user' => $certificate->user,
            'participationType' => $certificate->participationType,
            'eventName' => $eventName,
            'eventDate' => $eventDate,
            'eventLocation' => $eventLocation,
            'metadata' => $metadata,
        ]);
    }
}
