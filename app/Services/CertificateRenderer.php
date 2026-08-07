<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateTemplateElement;
use App\Models\Conference;
use App\Models\ParticipationType;
use App\Models\Presentation;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workshop;
use App\Support\EventSettings;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode as ChillerlanQRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
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

    private const ROLE_LABELS = [
        'Administrator' => 'Administración',
        'Ponente' => 'Ponente',
        'Asistente' => 'Asistente',
        'Instructor' => 'Instructor',
        'Speaker' => 'Speaker',
        'Moderator' => 'Moderador',
    ];

    private const BADGE_PRINT_WIDTH_PT = 212.59842519685;

    private const BADGE_PRINT_HEIGHT_PT = 354.33070866142;

    /**
     * Resolve the participation type for a given event and user.
     */
    public function resolveParticipationType(string $eventKind, User $user, Workshop|Presentation|Conference $event): ?ParticipationType
    {
        $role = $this->resolveRole($user, $event);

        if ($role === null) {
            return null;
        }

        $kind = $this->resolveKind($event);

        return ParticipationType::query()
            ->where('event_kind', $eventKind)
            ->where('role', $role)
            ->where('is_active', true)
            ->where(function ($query) use ($kind) {
                $query->whereNull('kind')->orWhere('kind', $kind);
            })
            ->orderByRaw('CASE WHEN kind IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    /**
     * Find or create a certificate for the user/event and return it.
     */
    public function issue(User $user, string $eventKind, Workshop|Presentation|Conference $event): ?Certificate
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

        return $this->finalize($certificate, $template, $this->buildMetadata($user, $type, $event));
    }

    /**
     * Find or create the event attendance certificate for the user.
     */
    public function issueEvent(User $user): ?Certificate
    {
        $type = ParticipationType::query()
            ->where('event_kind', 'event')
            ->where('is_active', true)
            ->first();

        if ($type === null) {
            return null;
        }

        return $this->issueType($user, $type, 'event', 0);
    }

    /**
     * Find or create a certificate for a generic (non-activity) participation
     * type, used by event attendance and manually generated certificates.
     */
    public function issueType(User $user, ParticipationType $type, string $eventType, int $eventId = 0): ?Certificate
    {
        if (! $type->is_active) {
            return null;
        }

        $template = $type->templates()->where('is_default', true)->first();
        $metadata = $this->buildEventMetadata($user, $type);

        $certificate = Certificate::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'participation_type_id' => $type->id,
                'event_type' => $eventType,
                'event_id' => $eventId,
            ],
            [
                'template_id' => $template?->id,
                'metadata' => $metadata,
            ],
        );

        return $this->finalize($certificate, $template, $metadata);
    }

    /**
     * Find or create a generic invitation letter certificate for the user.
     */
    public function issueCarta(User $user, ParticipationType $type, string $rolLabel): ?Certificate
    {
        if (! $type->is_active) {
            return null;
        }

        $template = $type->templates()
            ->where('kind', 'invitation')
            ->where('is_default', true)
            ->first();

        $metadata = $this->buildCartaMetadata($user, $type, $rolLabel);

        $certificate = Certificate::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'participation_type_id' => $type->id,
                'event_type' => 'event',
                'event_id' => 0,
            ],
            [
                'template_id' => $template?->id,
                'metadata' => $metadata,
            ],
        );

        return $this->finalize($certificate, $template, $metadata);
    }

    /**
     * Role label shown in the invitation letter, resolved from the first
     * role of the user that holds the invitation-letter permission.
     */
    public function cartaRolLabel(User $user): string
    {
        foreach ($user->roles as $role) {
            if ($role->permissions->contains('key', 'constancias.invitaciones.download')) {
                return self::ROLE_LABELS[$role->name] ?? $role->name;
            }
        }

        return 'Participante';
    }

    private function finalize(Certificate $certificate, ?CertificateTemplate $template, array $metadata): Certificate
    {
        if ($certificate->template_id === null && $template !== null) {
            $certificate->update(['template_id' => $template->id]);
        }

        if ($certificate->metadata === null) {
            $certificate->update(['metadata' => $metadata]);
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
        return $this->buildCertificateHtml($certificate, forPdf: false);
    }

    /**
     * Render the certificate as a PDF document.
     */
    public function renderPdf(Certificate $certificate): string
    {
        $template = $this->resolveTemplate($certificate);

        $dompdf = new Dompdf;
        $dompdf->loadHtml($this->buildCertificateHtml($certificate, forPdf: true));
        $dompdf->setPaper([0, 0, ($template->width ?: 1800) * 0.75, ($template->height ?: 1200) * 0.75]);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Render the user badge as a standalone HTML document.
     */
    public function renderBadge(User $user): string
    {
        $template = $this->resolveBadgeTemplate();
        $metadata = $this->buildBadgeMetadata($user);
        $qr = $this->qrDataUri($this->badgeUrl($user));
        $photo = $this->photoDataUri($user);

        $layout = $this->badgePrintLayout($template);
        $printPage = '7.5cm 12.5cm';
        $previewScale = 1 / $layout['scale'];

        $printButton = <<<'HTML'
<button class="print-btn" onclick="window.print()">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Imprimir gafete
</button>
HTML;

        if ($template === null) {
            return $this->fallbackBadgeHtml(
                $user,
                $metadata,
                $qr,
                $photo,
                forPdf: false,
                scale: $layout['scale'],
                offsetX: $layout['offsetX'],
                offsetY: $layout['offsetY'],
                pageWidth: $layout['targetWidth'],
                pageHeight: $layout['targetHeight'],
                printPage: $printPage,
                previewScale: $previewScale,
            );
        }

        return $this->buildTemplateHtml(
            $template,
            $metadata,
            $qr,
            $photo,
            forPdf: false,
            options: [
                'title' => 'Gafete de '.$user->name,
                'pdfButton' => $printButton,
                'styles' => $this->badgeStyles(),
                'scale' => $layout['scale'],
                'offsetX' => $layout['offsetX'],
                'offsetY' => $layout['offsetY'],
                'printPage' => $printPage,
                'previewScale' => $previewScale,
            ],
        );
    }

    /**
     * Render the user badge as a PDF document.
     */
    public function renderBadgePdf(User $user): string
    {
        $template = $this->resolveBadgeTemplate();
        $metadata = $this->buildBadgeMetadata($user);
        $qr = $this->qrDataUri($this->badgeUrl($user), png: true);
        $photo = $this->photoDataUri($user);

        $layout = $this->badgePrintLayout($template);

        $dompdf = new Dompdf;
        $dompdf->loadHtml($template === null
            ? $this->fallbackBadgeHtml($user, $metadata, $qr, $photo, forPdf: true, scale: $layout['scale'], offsetX: $layout['offsetX'], offsetY: $layout['offsetY'], pageWidth: $layout['targetWidth'], pageHeight: $layout['targetHeight'])
            : $this->buildTemplateHtml($template, $metadata, $qr, $photo, forPdf: true, options: [
                'title' => 'Gafete de '.$user->name,
                'scale' => $layout['scale'],
                'offsetX' => $layout['offsetX'],
                'offsetY' => $layout['offsetY'],
                'pageWidth' => $layout['targetWidth'],
                'pageHeight' => $layout['targetHeight'],
            ]));
        $dompdf->setPaper([0, 0, self::BADGE_PRINT_WIDTH_PT, self::BADGE_PRINT_HEIGHT_PT]);
        $dompdf->render();

        return $dompdf->output();
    }

    private function badgePrintLayout(?CertificateTemplate $template): array
    {
        $designWidth = $template->width ?? 384;
        $designHeight = $template->height ?? 816;

        $targetWidth = self::BADGE_PRINT_WIDTH_PT * 96 / 72;
        $targetHeight = self::BADGE_PRINT_HEIGHT_PT * 96 / 72;

        $scale = min($targetWidth / $designWidth, $targetHeight / $designHeight);
        $offsetX = ($targetWidth - $designWidth * $scale) / 2;
        $offsetY = ($targetHeight - $designHeight * $scale) / 2;

        return [
            'scale' => $scale,
            'offsetX' => $offsetX,
            'offsetY' => $offsetY,
            'targetWidth' => $targetWidth,
            'targetHeight' => $targetHeight,
        ];
    }

    private function buildCertificateHtml(Certificate $certificate, bool $forPdf): string
    {
        $template = $this->resolveTemplate($certificate, fallback: true);

        $metadata = $certificate->metadata ?? [];
        $qr = $forPdf
            ? $this->qrDataUri($this->verificationUrl($certificate), png: true)
            : $this->qrDataUri($this->verificationUrl($certificate));

        $pdfButton = $forPdf ? '' : $this->pdfButton($certificate);

        return $this->buildTemplateHtml(
            $template,
            $metadata,
            $qr,
            null,
            $forPdf,
            [
                'title' => 'Constancia '.$certificate->folio,
                'pdfButton' => $pdfButton,
            ],
        );
    }

    private function buildTemplateHtml(
        CertificateTemplate $template,
        array $metadata,
        string $qr,
        ?string $photo,
        bool $forPdf = false,
        array $options = [],
    ): string {
        $template->loadMissing('elements');

        $scale = $options['scale'] ?? 1;
        $offsetX = $options['offsetX'] ?? 0;
        $offsetY = $options['offsetY'] ?? 0;

        $width = ($template->width ?: 1800) * $scale;
        $height = ($template->height ?: 1200) * $scale;

        $background = '';
        if ($template->background_path && Storage::disk('public')->exists($template->background_path)) {
            $background = '<img class="bg" src="data:image/png;base64,'
                .base64_encode(Storage::disk('public')->get($template->background_path))
                .'" alt="" />';
        }

        $elementHtml = '';
        foreach ($template->elements as $element) {
            $elementHtml .= $this->renderElement($element->toArray(), $metadata, $qr, $photo, $scale);
        }

        $title = $options['title'] ?? 'Documento';
        $pdfButton = $options['pdfButton'] ?? '';
        $extraStyles = $options['styles'] ?? '';
        $pageWidth = $options['pageWidth'] ?? $width;
        $pageHeight = $options['pageHeight'] ?? $height;
        $printPage = $options['printPage'] ?? null;
        $previewScale = $options['previewScale'] ?? 0;
        $pageRule = $forPdf
            ? "@page { size: {$pageWidth}px {$pageHeight}px; margin: 0; }"
            : ($printPage ? "@page { size: {$printPage}; margin: 0; }" : '');
        $previewW = round($width * $previewScale);
        $previewH = round($height * $previewScale);
        $previewRule = $previewScale > 0
            ? "@media screen { .preview { transform: scale({$previewScale}); transform-origin: top left; width: {$previewW}px; height: {$previewH}px; } }"
            : '';
        $previewOpen = $previewScale > 0 ? '<div class="preview">' : '';
        $previewClose = $previewScale > 0 ? '</div>' : '';
        $bgStyle = $forPdf
            ? '.certificate .bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }'
            : '.certificate .bg { position: absolute; inset: 0; width: 100%; height: 100%; }';

        $styles = $this->elementStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        html, body { margin: 0; padding: 0; }
        .certificate { position: relative; width: {$width}px; height: {$height}px; margin-left: {$offsetX}px; margin-top: {$offsetY}px; overflow: hidden; }
        {$bgStyle}
        {$pageRule}
        {$previewRule}
        {$styles}
        {$extraStyles}
    </style>
</head>
<body>
    {$pdfButton}
    {$previewOpen}
    <div class="certificate">
        {$background}
        {$elementHtml}
    </div>
    {$previewClose}
</body>
</html>
HTML;
    }

    private function resolveTemplate(Certificate $certificate, bool $fallback = false): CertificateTemplate
    {
        $template = $certificate->template ?? $certificate->participationType?->templates()->where('is_default', true)->first();

        if ($template === null && $fallback) {
            return $this->fallbackTemplate();
        }

        if ($template === null) {
            throw new \RuntimeException('No hay una plantilla configurada para este tipo de participación.');
        }

        return $template;
    }

    private function fallbackTemplate(): CertificateTemplate
    {
        $template = new CertificateTemplate([
            'name' => 'Constancia',
            'width' => 1800,
            'height' => 1200,
        ]);

        $template->setRelation('elements', collect([
            new CertificateTemplateElement([
                'type' => 'text',
                'content' => '{nombre}',
                'x' => 150,
                'y' => 380,
                'width' => 1500,
                'height' => 90,
                'font_size' => 56,
                'font_weight' => 'bold',
                'text_align' => 'center',
                'z_index' => 1,
            ]),
            new CertificateTemplateElement([
                'type' => 'text',
                'content' => 'Por su asistencia a: {tipo_participacion}',
                'x' => 200,
                'y' => 520,
                'width' => 1400,
                'height' => 60,
                'font_size' => 32,
                'text_align' => 'center',
                'z_index' => 2,
            ]),
            new CertificateTemplateElement([
                'type' => 'qr',
                'x' => 1500,
                'y' => 960,
                'width' => 200,
                'height' => 200,
                'text_align' => 'center',
                'z_index' => 3,
            ]),
        ]));

        return $template;
    }

    private function resolveBadgeTemplate(): ?CertificateTemplate
    {
        $template = CertificateTemplate::query()
            ->kind('badge')
            ->where('is_default', true)
            ->first();

        if ($template === null) {
            $template = CertificateTemplate::query()
                ->kind('badge')
                ->first();
        }

        return $template;
    }

    private function verificationUrl(Certificate $certificate): string
    {
        return url('/constancias/verificar/'.$certificate->folio);
    }

    private function badgeUrl(User $user): string
    {
        return url('/gafete/escaneo?token='.$user->ensureCheckinToken());
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

    private function renderElement(array $element, array $metadata, string $qr, ?string $photo, float $scale = 1.0): string
    {
        $left = (float) $element['x'] * $scale;
        $top = (float) $element['y'] * $scale;
        $z = (int) $element['z_index'];

        if ($element['type'] === 'qr') {
            $size = ($element['width'] ?: 160) * $scale;
            $style = "position:absolute;left:{$left}px;top:{$top}px;width:{$size}px;height:{$size}px;z-index:{$z};";

            return '<img src="'.$qr.'" alt="QR de check-in" style="'.$style.'" />';
        }

        if ($element['type'] === 'image') {
            $originalWidth = $element['width'] ?: 200;
            $width = $originalWidth * $scale;
            $height = ($element['height'] ?: $originalWidth) * $scale;
            $style = "position:absolute;left:{$left}px;top:{$top}px;width:{$width}px;height:{$height}px;z-index:{$z};object-fit:cover;border-radius:8px;";

            return '<img src="'.($photo ?? '').'" alt="Foto del participante" style="'.$style.'" />';
        }

        $width = $element['width'] ? (int) round($element['width'] * $scale).'px' : 'auto';
        $height = $element['height'] ? (int) round($element['height'] * $scale).'px' : 'auto';

        $content = $element['content'] ?? '';
        $content = $this->replaceVariables($content, $metadata);
        $content = str_replace(['{qr}', '{qr_activacion}'], $qr, $content);

        $fontSize = ! empty($element['font_size']) ? (float) $element['font_size'] * $scale : 0.0;

        if ($fontSize > 0 && ! empty($element['auto_fit']) && ! empty($element['width']) && $content !== '') {
            $boxWidth = (float) $element['width'] * $scale;
            $measured = $this->measuredTextWidth($content, $element['font_family'] ?? null, $element['font_weight'] ?? null, $fontSize);

            if ($measured > 0) {
                $fitted = $fontSize * (($boxWidth * 0.75 * 0.96) / $measured);
                $minFont = 8 * $scale;
                $fontSize = max($fitted, $minFont);
            }
        }

        $style = "position:absolute;left:{$left}px;top:{$top}px;width:{$width};height:{$height};z-index:{$z};";
        if ($fontSize > 0) {
            $style .= 'font-size:'.$fontSize.'px;';
        }
        if (! empty($element['font_weight'])) {
            $style .= "font-weight:{$element['font_weight']};";
        }
        if (! empty($element['font_family'])) {
            $style .= "font-family:{$element['font_family']};";
        }
        if (! empty($element['color'])) {
            $style .= "color:{$element['color']};";
        }
        $textAlign = $element['text_align'] ?? 'center';
        $style .= "text-align:{$textAlign};";

        return '<div style="'.$style.'">'.$content.'</div>';
    }

    private function measuredTextWidth(string $text, ?string $fontFamily, ?string $fontWeight, float $sizePx): float
    {
        $metrics = $this->fontMetrics();
        $font = $metrics->getFont($fontFamily ?: 'sans-serif', $this->fontStyle($fontWeight));

        return $metrics->getTextWidth($text, $font, $sizePx * 0.75);
    }

    private function fontStyle(?string $fontWeight): string
    {
        $weight = strtolower(trim((string) $fontWeight));

        if ($weight === 'italic') {
            return 'italic';
        }

        return in_array($weight, ['bold', 'bolder', '600', '700', '800', '900'], true)
            ? 'bold'
            : 'normal';
    }

    private ?FontMetrics $fontMetricsInstance = null;

    private function fontMetrics(): FontMetrics
    {
        if ($this->fontMetricsInstance === null) {
            $this->fontMetricsInstance = (new Dompdf)->getFontMetrics();
        }

        return $this->fontMetricsInstance;
    }

    private function replaceVariables(string $content, array $metadata): string
    {
        $replacements = [
            '{nombre}' => $metadata['nombre'] ?? '',
            '{nombre_completo}' => $metadata['nombre_completo'] ?? $metadata['nombre'] ?? '',
            '{tipo_participacion}' => $metadata['tipo_participacion'] ?? '',
            '{evento}' => $metadata['evento'] ?? '',
            '{nombre_evento}' => $metadata['nombre_evento'] ?? $metadata['evento'] ?? '',
            '{fecha_evento}' => $metadata['fecha_evento'] ?? '',
            '{folio}' => $metadata['folio'] ?? '',
            '{dni}' => $metadata['dni'] ?? '',
            '{afiliacion}' => $metadata['afiliacion'] ?? '',
            '{institucion}' => $metadata['institucion'] ?? $metadata['afiliacion'] ?? '',
            '{pais}' => $metadata['pais'] ?? '',
            '{rol}' => $metadata['rol'] ?? '',
            '{iniciales}' => $metadata['iniciales'] ?? '',
        ];

        foreach ($replacements as $key => $value) {
            $content = str_replace($key, htmlspecialchars((string) $value, ENT_QUOTES), $content);
        }

        return $content;
    }

    private function buildMetadata(User $user, ParticipationType $type, Workshop|Presentation|Conference $event): array
    {
        $eventName = match (true) {
            $event instanceof Presentation => $event->title,
            $event instanceof Conference => $event->title,
            default => $event->name,
        };

        return [
            'nombre' => trim($user->first_name.' '.$user->last_name),
            'tipo_participacion' => $type->label,
            'evento' => (string) $eventName,
            'fecha_evento' => $event->day ? $this->formatSpanishDate($event->day) : '',
            'folio' => '',
        ];
    }

    private function buildEventMetadata(User $user, ParticipationType $type): array
    {
        return [
            'nombre' => trim($user->first_name.' '.$user->last_name),
            'tipo_participacion' => $type->label,
            'evento' => $this->eventName(),
            'fecha_evento' => '',
            'folio' => '',
        ];
    }

    private function buildCartaMetadata(User $user, ParticipationType $type, string $rolLabel): array
    {
        $nombre = trim($user->first_name.' '.$user->last_name);

        return [
            'nombre' => $nombre,
            'nombre_completo' => $nombre,
            'rol' => $rolLabel,
            'tipo_participacion' => $type->label,
            'evento' => $this->eventName(),
            'nombre_evento' => $this->eventName(),
            'fecha_evento' => $this->eventDateRange(),
            'institucion' => (string) ($user->affiliation ?? ''),
            'pais' => (string) ($user->country ?? ''),
            'folio' => '',
        ];
    }

    private function eventDateRange(): string
    {
        $start = EventSettings::startDate();
        $end = EventSettings::endDate();

        if ($start === null) {
            return '';
        }

        $startText = $this->formatSpanishDate($start);

        if ($end === null || $end === $start) {
            return $startText;
        }

        return $startText.' – '.$this->formatSpanishDate($end);
    }

    private function buildBadgeMetadata(User $user): array
    {
        return [
            'nombre' => trim($user->first_name.' '.$user->last_name),
            'dni' => (string) $user->dni,
            'afiliacion' => (string) ($user->affiliation ?? ''),
            'evento' => $this->eventName(),
            'rol' => $this->roleLabel($user),
            'iniciales' => $this->initials($user),
        ];
    }

    private function eventName(): string
    {
        return (string) (Setting::query()->where('key', 'evento_nombre')->value('value') ?? 'EICAL 2026');
    }

    private function roleLabel(User $user): string
    {
        $role = $user->roles->first();

        if ($role === null) {
            return 'Participante';
        }

        return self::ROLE_LABELS[$role->name] ?? $role->name;
    }

    private function initials(User $user): string
    {
        $first = mb_substr(trim((string) $user->first_name), 0, 1);
        $last = mb_substr(trim((string) $user->last_name), 0, 1);

        return strtoupper(($first ?: '').($last ?: ''));
    }

    private function photoDataUri(User $user): string
    {
        if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
            $mime = Storage::disk('public')->mimeType($user->profile_photo_path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($user->profile_photo_path));
        }

        return $this->initialsPlaceholder($user);
    }

    private function initialsPlaceholder(User $user): string
    {
        $initials = $this->initials($user);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><rect width="100%" height="100%" fill="#e2e8f0"/><text x="50%" y="50%" font-family="Arial, sans-serif" font-size="160" fill="#94a3b8" text-anchor="middle" dominant-baseline="central">'.htmlspecialchars($initials, ENT_QUOTES).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function fallbackBadgeHtml(User $user, array $metadata, string $qr, string $photo, bool $forPdf = false, float $scale = 1.0, float $offsetX = 0.0, float $offsetY = 0.0, float $pageWidth = 0.0, float $pageHeight = 0.0, string $printPage = '', float $previewScale = 0.0): string
    {
        $s = $scale;
        $width = 384 * $s;
        $height = 816 * $s;
        $pw = $pageWidth ?: $width;
        $ph = $pageHeight ?: $height;
        $pageRule = $forPdf
            ? "@page { size: {$pw}px {$ph}px; margin: 0; }"
            : ($printPage ? "@page { size: {$printPage}; margin: 0; }" : '');
        $previewW = round($width * $previewScale);
        $previewH = round($height * $previewScale);
        $previewRule = $previewScale > 0
            ? "@media screen { .preview { transform: scale({$previewScale}); transform-origin: top left; width: {$previewW}px; height: {$previewH}px; } }"
            : '';
        $previewOpen = $previewScale > 0 ? '<div class="preview">' : '';
        $previewClose = $previewScale > 0 ? '</div>' : '';
        $printButton = $forPdf ? '' : '<button class="print-btn" onclick="window.print()">Imprimir gafete</button>';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gafete de {$user->name}</title>
    <style>
        html, body { margin: 0; padding: 0; }
        .badge { position: relative; width: {$width}px; height: {$height}px; margin-left: {$offsetX}px; margin-top: {$offsetY}px; background: #ffffff; font-family: 'Helvetica Neue', Arial, sans-serif; overflow: hidden; }
        .badge-header { position: absolute; top: 0; left: 0; right: 0; height: {64 * $s}px; background: #0f172a; color: #ffffff; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0 {16 * $s}px; }
        .badge-header .title { font-size: {22 * $s}px; font-weight: 700; letter-spacing: 1px; }
        .badge-header .subtitle { font-size: {11 * $s}px; color: #94a3b8; }
        .badge-photo { position: absolute; top: {96 * $s}px; left: {117 * $s}px; width: {150 * $s}px; height: {150 * $s}px; border-radius: {12 * $s}px; object-fit: cover; border: {3 * $s}px solid #e2e8f0; }
        .badge-name { position: absolute; top: {268 * $s}px; left: 0; right: 0; text-align: center; padding: 0 {12 * $s}px; font-size: {24 * $s}px; font-weight: 700; color: #0f172a; }
        .badge-dni { position: absolute; top: {314 * $s}px; left: 0; right: 0; text-align: center; font-size: {13 * $s}px; color: #475569; }
        .badge-role { position: absolute; top: {352 * $s}px; left: 0; right: 0; text-align: center; }
        .badge-role span { display: inline-block; padding: {5 * $s}px {14 * $s}px; border-radius: 999px; background: #6366f1; color: #ffffff; font-size: {14 * $s}px; font-weight: 600; }
        .badge-aff { position: absolute; top: {408 * $s}px; left: 0; right: 0; text-align: center; padding: 0 {12 * $s}px; font-size: {13 * $s}px; color: #475569; }
        .badge-event { position: absolute; top: {470 * $s}px; left: 0; right: 0; text-align: center; font-size: {12 * $s}px; color: #94a3b8; }
        .badge-qr { position: absolute; left: {102 * $s}px; bottom: {120 * $s}px; width: {180 * $s}px; height: {180 * $s}px; }
        .badge-qr-caption { position: absolute; left: 0; right: 0; bottom: {40 * $s}px; text-align: center; font-size: {12 * $s}px; color: #64748b; }
        {$pageRule}
        {$previewRule}
        {$this->badgeStyles()}
    </style>
</head>
<body>
    {$printButton}
    {$previewOpen}
    <div class="badge">
        <div class="badge-header">
            <span class="title">{$metadata['evento']}</span>
            <span class="subtitle">Acceso</span>
        </div>
        <img class="badge-photo" src="{$photo}" alt="Foto" />
        <div class="badge-name">{$metadata['nombre']}</div>
        <div class="badge-dni">{$metadata['dni']}</div>
        <div class="badge-role"><span>{$metadata['rol']}</span></div>
        <div class="badge-aff">{$metadata['afiliacion']}</div>
        <div class="badge-event">Asistente al evento</div>
        <img class="badge-qr" src="{$qr}" alt="QR" />
        <div class="badge-qr-caption">Escanear en acceso</div>
    </div>
    {$previewClose}
</body>
</html>
HTML;
    }

    private function badgeStyles(): string
    {
        return '
        html, body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .print-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1000;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            background: #0f172a;
            color: #ffffff;
            font-family: system-ui, sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .print-btn:hover { background: #1e293b; }
        @media print { .print-btn { display: none; } }
        ';
    }

    private function generateFolio(Certificate $certificate): string
    {
        return 'EICAL-'.date('Y').'-'.str_pad((string) $certificate->id, 4, '0', STR_PAD_LEFT);
    }

    private function qrDataUri(string $data, bool $png = false): string
    {
        if ($png) {
            return $this->pngDataUri($data);
        }

        $renderer = new ImageRenderer(
            new RendererStyle(200, 4),
            new SvgImageBackEnd,
        );
        $writer = new Writer($renderer);
        $image = $writer->writeString($data);

        return 'data:image/svg+xml;base64,'.base64_encode($image);
    }

    private function pngDataUri(string $data): string
    {
        if (extension_loaded('imagick')) {
            $renderer = new ImageRenderer(
                new RendererStyle(200, 4),
                new ImagickImageBackEnd('png'),
            );
            $image = (new Writer($renderer))->writeString($data);

            return 'data:image/png;base64,'.base64_encode($image);
        }

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 4,
            'quietzoneSize' => 4,
        ]);

        return (new ChillerlanQRCode($options))->render($data);
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

    private function resolveRole(User $user, Workshop|Presentation|Conference $event): ?string
    {
        if ($event instanceof Workshop) {
            if ($event->instructors()->where('users.id', $user->id)->exists()) {
                return 'instructor';
            }

            if ($event->attendances()->where('user_id', $user->id)->exists()) {
                return 'enrolled_attendance';
            }

            return null;
        }

        if ($event instanceof Presentation) {
            return $event->authors()->where('users.id', $user->id)->exists() ? 'presented_author' : null;
        }

        if ($event instanceof Conference) {
            return $event->members()->where('users.id', $user->id)->first()?->pivot->role;
        }

        return null;
    }

    private function resolveKind(Workshop|Presentation|Conference $event): ?string
    {
        if ($event instanceof Conference) {
            return $event->kind;
        }

        if ($event instanceof Presentation) {
            return $event->type;
        }

        return null;
    }
}
