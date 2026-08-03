<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateRenderer;

class CertificateVerificationController extends Controller
{
    public function show(string $folio, CertificateRenderer $renderer)
    {
        $certificate = Certificate::query()
            ->with(['user', 'participationType', 'event'])
            ->where('folio', $folio)
            ->firstOrFail();

        $event = $certificate->event;
        $metadata = $certificate->metadata ?? [];
        $eventName = $metadata['evento'] ?? ($event?->name ?? $event?->title ?? '—');
        $eventDate = $metadata['fecha_evento'] ?? ($event?->day ? $renderer->formatSpanishDate($event->day) : '—');
        $eventLocation = $event?->location ?? null;

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
