<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ParticipationType;
use App\Models\Presentation;
use App\Models\User;
use App\Models\Workshop;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;

class CertificateRenderer
{
    private const MONTHS = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    /**
     * Resolve the participation type for a given event and user.
     */
    public function resolveParticipationType(string $eventKind, User $user, Workshop|Presentation $event): ?ParticipationType
    {
        $role = $this->resolveRole($eventKind, $user, $event);

        if ($role === null) {
            return null;
        }

        return ParticipationType::query()
            ->where('event_kind', $eventKind)
            ->where('role', $role)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find or create a certificate for the user/event and return it.
     */
    public function issue(User $user, string $eventKind, Workshop|Presentation $event): ?Certificate
    {
        $type = $this->resolveParticipationType($eventKind, $user, $event);

        if ($type === null) {
            return null;
        }

        $template = $type->templates()->where('is_default', true)->first();

        $certificate = Certificate::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'participation_type_id' => $type->id,
                'event_type' => $eventKind,
                'event_id' => $event->id,
            ],
            [
                'template_id' => $template?->id,
                'metadata' => $this->buildMetadata($user, $type, $event),
            ],
        );

        if ($certificate->template_id === null && $template !== null) {
            $certificate->update(['template_id' => $template->id]);
        }

        if ($certificate->metadata === null) {
            $certificate->update(['metadata' => $this->buildMetadata($user, $type, $event)]);
        }

        if ($certificate->folio === null) {
            $folio = $this->generateFolio($certificate);
            $certificate->update([
                'folio' => $folio,
                'metadata' => array_merge($certificate->metadata ?? [], ['folio' => $folio]),
            ]);
        } elseif (($certificate->metadata['folio'] ?? null) !== $certificate->folio) {
            $certificate->update([
                'metadata' => array_merge($certificate->metadata ?? [], ['folio' => $certificate->folio]),
            ]);
        }

        return $certificate;
    }

    /**
     * Render the certificate as a standalone HTML document.
     */
    public function render(Certificate $certificate): string
    {
        return $this->buildHtml($certificate);
    }

    /**
     * Render the certificate as a PDF document.
     */
    public function renderPdf(Certificate $certificate): string
    {
        $template = $this->resolveTemplate($certificate);

        $dompdf = new Dompdf;
        $dompdf->loadHtml($this->buildHtml($certificate, forPdf: true));
        $dompdf->setPaper([0, 0, ($template->width ?: 1800) * 0.75, ($template->height ?: 1200) * 0.75]);
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildHtml(Certificate $certificate, bool $forPdf = false): string
    {
        $template = $this->resolveTemplate($certificate);

        $template->loadMissing('elements');

        $metadata = $certificate->metadata ?? [];
        $width = $template->width ?: 1800;
        $height = $template->height ?: 1200;

        $background = '';
        if ($template->background_path && Storage::disk('public')->exists($template->background_path)) {
            $background = '<img class="bg" src="data:image/png;base64,'
                .base64_encode(Storage::disk('public')->get($template->background_path))
                .'" alt="" />';
        }

        $qr = $forPdf
            ? $this->qrDataUri($this->verificationUrl($certificate), png: true)
            : $this->qrDataUri($this->verificationUrl($certificate));

        $elementHtml = '';
        foreach ($template->elements as $element) {
            $elementHtml .= $this->renderElement($element->toArray(), $metadata, $qr);
        }

        $pdfButton = $forPdf ? '' : $this->pdfButton($certificate);
        $pageRule = $forPdf ? "@page { size: {$width}px {$height}px; margin: 0; }" : '';
        $bgStyle = $forPdf
            ? '.certificate .bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }'
            : '.certificate .bg { position: absolute; inset: 0; width: 100%; height: 100%; }';

        $styles = $this->elementStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia {$certificate->folio}</title>
    <style>
        html, body { margin: 0; padding: 0; }
        .certificate { position: relative; width: {$width}px; height: {$height}px; overflow: hidden; }
        {$bgStyle}
        {$pageRule}
        {$styles}
    </style>
</head>
<body>
    {$pdfButton}
    <div class="certificate">
        {$background}
        {$elementHtml}
    </div>
</body>
</html>
HTML;
    }

    private function resolveTemplate(Certificate $certificate): CertificateTemplate
    {
        $template = $certificate->template ?? $certificate->participationType?->templates()->where('is_default', true)->first();

        if ($template === null) {
            throw new \RuntimeException('No hay una plantilla configurada para este tipo de participación.');
        }

        return $template;
    }

    private function verificationUrl(Certificate $certificate): string
    {
        return url('/constancias/verificar/'.$certificate->folio);
    }

    private function pdfButton(Certificate $certificate): string
    {
        $href = route('constancias.pdf', $certificate);

        return <<<HTML
<a class="pdf-btn" href="{$href}" target="_blank" rel="noopener">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Descargar PDF
</a>
HTML;
    }

    private function renderElement(array $element, array $metadata, string $qr): string
    {
        $left = (float) $element['x'];
        $top = (float) $element['y'];
        $z = (int) $element['z_index'];
        $width = $element['width'] ? (int) $element['width'].'px' : 'auto';
        $height = $element['height'] ? (int) $element['height'].'px' : 'auto';

        $style = "position:absolute;left:{$left}px;top:{$top}px;width:{$width};height:{$height};z-index:{$z};";

        if ($element['type'] === 'qr') {
            $size = $element['width'] ?: 160;
            $style = "position:absolute;left:{$left}px;top:{$top}px;width:{$size}px;height:{$size}px;z-index:{$z};";

            return '<img src="'.$qr.'" alt="QR de verificacion" style="'.$style.'" />';
        }

        $style .= "font-size:{$element['font_size']}px;";
        if ($element['font_weight']) {
            $style .= "font-weight:{$element['font_weight']};";
        }
        if ($element['font_family']) {
            $style .= "font-family:{$element['font_family']};";
        }
        if ($element['color']) {
            $style .= "color:{$element['color']};";
        }
        $style .= "text-align:{$element['text_align']};";

        $content = $element['content'] ?? '';
        $content = $this->replaceVariables($content, $metadata);
        $content = str_replace('{qr}', $qr, $content);

        return '<div style="'.$style.'">'.$content.'</div>';
    }

    private function replaceVariables(string $content, array $metadata): string
    {
        $replacements = [
            '{nombre}' => $metadata['nombre'] ?? '',
            '{tipo_participacion}' => $metadata['tipo_participacion'] ?? '',
            '{evento}' => $metadata['evento'] ?? '',
            '{fecha_evento}' => $metadata['fecha_evento'] ?? '',
            '{folio}' => $metadata['folio'] ?? '',
        ];

        foreach ($replacements as $key => $value) {
            $content = str_replace($key, htmlspecialchars((string) $value, ENT_QUOTES), $content);
        }

        return $content;
    }

    private function buildMetadata(User $user, ParticipationType $type, Workshop|Presentation $event): array
    {
        if ($event instanceof Presentation) {
            $eventName = $event->title;
            $eventDate = $event->day;
        } else {
            $eventName = $event->name;
            $eventDate = $event->day;
        }

        return [
            'nombre' => trim($user->first_name.' '.$user->last_name),
            'tipo_participacion' => $type->label,
            'evento' => (string) $eventName,
            'fecha_evento' => $eventDate ? $this->formatSpanishDate($eventDate) : '',
            'folio' => '',
        ];
    }

    private function generateFolio(Certificate $certificate): string
    {
        return 'EICAL-'.date('Y').'-'.str_pad((string) $certificate->id, 4, '0', STR_PAD_LEFT);
    }

    private function qrDataUri(string $data, bool $png = false): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200, 4),
            $png ? new ImagickImageBackEnd('png') : new SvgImageBackEnd,
        );
        $writer = new Writer($renderer);
        $image = $writer->writeString($data);

        return ($png ? 'data:image/png;base64,' : 'data:image/svg+xml;base64,').base64_encode($image);
    }

    private function elementStyles(): string
    {
        return '
        .certificate > div, .certificate > img { box-sizing: border-box; }
        .pdf-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1000;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            background: #dc2626;
            color: #ffffff;
            font-family: system-ui, sans-serif;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .pdf-btn:hover { background: #b91c1c; }
        @media print { .pdf-btn { display: none; } }
        ';
    }

    public function formatSpanishDate(string $date): string
    {
        $timestamp = strtotime($date);
        $day = date('j', $timestamp);
        $month = self::MONTHS[(int) date('n', $timestamp)];
        $year = date('Y', $timestamp);

        return $day.' de '.$month.' de '.$year;
    }

    private function resolveRole(string $eventKind, User $user, Workshop|Presentation $event): ?string
    {
        if ($eventKind === 'workshop') {
            if ($event->instructors()->where('users.id', $user->id)->exists()) {
                return 'instructor';
            }

            if ($event->attendances()->where('user_id', $user->id)->exists()) {
                return 'enrolled_attendance';
            }

            return null;
        }

        if ($eventKind === 'presentation') {
            $presented = $event->authors()
                ->where('users.id', $user->id)
                ->wherePivot('presented', true)
                ->exists();

            return $presented ? 'presented_author' : null;
        }

        return null;
    }
}
