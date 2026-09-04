<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProgramTemplateRenderer
{
    private int $pageWidth = 816;

    private int $pageHeight = 1056;

    private const DEFAULTS = [
        'show_day' => true,
        'show_time' => true,
        'show_location' => true,
        'show_persons' => true,
        'accent_color' => '#9d174d',
        'text_color' => '#111827',
        'badge_text_color' => '#ffffff',
        'row_font_size' => 13,
        'day_font_size' => 14,
        'time_column' => 96,
        'bottom_padding' => 32,
        'row_padding_y' => 8,
        'type_colors' => [
            'workshop' => '#b45309',
            'presentation' => '#0369a1',
            'conference' => '#9d174d',
            'block' => '#475569',
        ],
    ];

    private const TYPE_COLORS_KEYS = ['workshop', 'presentation', 'conference', 'block'];

    /**
     * @param  Collection<int, array>  $groups  grupos serializados del programa
     * @param  array<string, string>  $meta  {eventName, fechas, lugar}
     */
    public function render(CertificateTemplate $template, Collection $groups, array $meta, bool $forPdf = false, ?string $pdfUrl = null): string
    {
        $template->loadMissing('elements');

        $pageW = (int) ($template->width ?: 816);
        $pageH = (int) ($template->height ?: 1056);

        $this->pageWidth = $pageW;
        $this->pageHeight = $pageH;

        $listEl = $template->elements->first(fn ($e) => $e->type === 'program');
        $config = array_merge(self::DEFAULTS, $listEl ? $this->decodeConfig($listEl->content) : []);

        $listX = $listEl && $listEl->x !== null ? (int) $listEl->x : 48;
        $listY = $listEl && $listEl->y !== null ? (int) $listEl->y : (int) round($pageH * 0.16);
        $listW = $listEl && $listEl->width ? (int) $listEl->width : max(200, $pageW - 96);

        $letterhead = $template->elements
            ->filter(fn ($e) => $e->type !== 'program')
            ->values();

        $qr = $this->qrDataUri(url('/programa/publico'));
        $background = $this->backgroundDataUri($template);

        $budget = max(120, $pageH - $listY - (int) $config['bottom_padding']);
        $chunks = $this->paginate($this->buildRows($groups), $config, $budget, $listW);

        $total = count($chunks);
        if ($total === 0) {
            $chunks = [[]];
            $total = 1;
        }

        $pages = '';
        foreach ($chunks as $i => $chunk) {
            $pageNo = $i + 1;
            $letterheadHtml = $this->renderLetterhead($letterhead, $meta, $store = compact('qr'), $pageNo, $total);
            $listHtml = $this->renderList($chunk, $config, $listW);
            $bgImg = ($background && ! $forPdf)
                ? '<img class="bg" style="top:0px;left:0px;width:'.$pageW.'px;height:'.$pageH.'px;" src="'.$background.'#'.$pageNo.'" alt="" />'
                : '';

            $pages .= <<<HTML
<div class="page-holder">
    <div class="page" id="page-{$pageNo}">
        {$bgImg}
        {$letterheadHtml}
        <div class="program-list" style="left:{$listX}px;top:{$listY}px;width:{$listW}px;">
            {$listHtml}
        </div>
    </div>
</div>
HTML;
        }

        // En PDF Dompdf no repite los elementos absolute por página: se usa un
        // único <img> position:fixed a nivel de body, que sí se dibuja en cada hoja.
        $bgFixed = ($background && $forPdf)
            ? '<img class="bg-fixed" style="top:0px;left:0px;width:'.$pageW.'px;height:'.$pageH.'px;" src="'.$background.'" alt="" />'
            : '';

        $style = $this->styles($config, $listW, $forPdf);
        $stage = $forPdf ? '' : $this->screenOverlay($pageW, $pageH);
        $toolbar = $forPdf ? '' : $this->printToolbar($pdfUrl);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Programa - {$meta['eventName']}</title>
    <style>
        html, body { margin: 0; padding: 0; }
        {$style}
        {$stage}
    </style>
</head>
<body>
    {$bgFixed}
    {$pages}
    {$toolbar}
</body>
</html>
HTML;
    }

    /**
     * Genera el PDF del programa a partir del mismo HTML de la plantilla
     * personalizada (modo forPdf): sin overlay ni botón flotante, con el
     * fondo en todas las hojas y un layout de filas compatible con Dompdf.
     */
    public function renderPdf(CertificateTemplate $template, Collection $groups, array $meta): string
    {
        $html = $this->render($template, $groups, $meta, forPdf: true);

        $pageW = (int) ($template->width ?: 816);
        $pageH = (int) ($template->height ?: 1056);

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, $pageW * 72 / 96, $pageH * 72 / 96]);
        $dompdf->render();

        return $dompdf->output();
    }

    public function renderBladePdf(string $html): string
    {
        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter');
        $dompdf->render();

        return $dompdf->output();
    }

    /** Plantilla activa kind=program, o null. */
    public static function activeTemplate(): ?CertificateTemplate
    {
        return CertificateTemplate::query()->kind('program')->where('is_active', true)->first();
    }

    private function decodeConfig(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Colores por tipo de actividad, completados con los valores por defecto. */
    private function typeColors(array $config): array
    {
        $given = $config['type_colors'] ?? [];

        return array_merge(self::DEFAULTS['type_colors'], array_intersect_key($given, array_flip(self::TYPE_COLORS_KEYS)));
    }

    /**
     * @param  Collection<int, array>  $groups
     */
    private function buildRows(Collection $groups): array
    {
        $rows = [];

        foreach ($groups as $group) {
            $rows[] = ['type' => 'day', 'label' => $group['label'] ?? ''];

            foreach ($group['items'] ?? [] as $item) {
                $rows[] = ['type' => 'item', 'item' => $item];
            }
        }

        return $rows;
    }

    private function paginate(array $rows, array $config, int $budget, int $listW): array
    {
        $pages = [];
        $current = [];
        $used = 0;
        $dayH = $this->dayHeight($config);
        $activeDay = null;

        foreach ($rows as $row) {
            $isDay = $row['type'] === 'day';
            if ($isDay) {
                $activeDay = $row['label'];
            }
            $h = $isDay ? $dayH : $this->itemHeight($row['item'], $config, $listW);

            // Un día nuevo siempre arranca en una hoja nueva.
            if ($isDay && ! empty($current)) {
                $pages[] = $current;
                $current = [];
                $used = 0;
                // Un item que no cabe se desborda y repite el rótulo del día activo.
            } elseif (! empty($current) && $used + $h > $budget) {
                $pages[] = $current;
                $current = [];
                $used = 0;
                $current[] = ['type' => 'day', 'label' => $activeDay];
                $used += $dayH;
            }

            $current[] = $row;
            $used += $h;
        }

        if (! empty($current)) {
            $pages[] = $current;
        }

        return $pages;
    }

    private function dayHeight(array $config): int
    {
        return (int) round(((int) $config['day_font_size']) * 1.4 + 10);
    }

    private function itemHeight(array $item, array $config, int $listW): int
    {
        $colW = $this->bodyWidth($config, $listW);
        $font = (int) $config['row_font_size'];

        $lines = 1; // badge
        $lines += $this->wrapLines((string) ($item['title'] ?? ''), $colW, $font, true);

        if (($config['show_location'] ?? true) && ! empty($item['location'])) {
            $lines += $this->wrapLines((string) $item['location'], $colW, $font - 1, false);
        }

        if ($config['show_persons'] ?? true) {
            foreach (($item['people'] ?? []) as $group) {
                $text = ($group['label'] ?? '').': '.implode(', ', $group['names'] ?? []);
                $lines += $this->wrapLines($text, $colW, $font - 2, false);
            }
        }

        $height = (int) $config['row_padding_y'] * 2 + $lines * (int) round($font * 1.3) + 1;

        return max(40, $height);
    }

    private function bodyWidth(array $config, int $listW): int
    {
        $time = ($config['show_time'] ?? true) ? (int) $config['time_column'] : 0;

        return max(120, $listW - $time - 16);
    }

    private function wrapLines(string $text, int $width, int $fontSize, bool $bold = false): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $avgChar = $fontSize * ($bold ? 0.58 : 0.51);
        $charsPerLine = max(1, (int) ($width / max(1, $avgChar)));

        return max(1, (int) ceil(mb_strlen($text) / $charsPerLine));
    }

    private function renderList(array $rows, array $config, int $listW): string
    {
        $showTime = $config['show_time'] ?? true;
        $showLocation = $config['show_location'] ?? true;
        $showPersons = $config['show_persons'] ?? true;

        $html = '';

        foreach ($rows as $row) {
            if ($row['type'] === 'day') {
                $html .= '<div class="pd">'.e($row['label']).'</div>';

                continue;
            }

            $item = $row['item'];
            $badge = ($item['kind'] === 'activity' ? ($item['activity_label'] ?? 'Actividad') : ($item['block_label'] ?? 'Actividad'));
            $badgeKind = in_array($item['activity_type'] ?? null, self::TYPE_COLORS_KEYS, true)
                ? $item['activity_type']
                : 'block';

            $html .= '<div class="pi">';

            if ($showTime) {
                $html .= '<div class="pi-time">'.e($item['time_label'] ?: '—').'</div>';
            }

            $html .= '<div class="pi-body">';
            $html .= '<span class="pi-badge pi-badge-'.e($badgeKind).'">'.e($badge).'</span>';
            $html .= '<div class="pi-title">'.e((string) ($item['title'] ?? '')).'</div>';

            if ($showLocation && ! empty($item['location'])) {
                $html .= '<div class="pi-meta">'.e((string) $item['location']).'</div>';
            }

            if ($showPersons) {
                foreach (($item['people'] ?? []) as $group) {
                    $names = implode(', ', $group['names'] ?? []);
                    if ($names === '') {
                        continue;
                    }
                    $html .= '<div class="pi-people"><b>'.e((string) $group['label']).':</b> '.e($names).'</div>';
                }
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    private function renderLetterhead(Collection $elements, array $meta, array $store, int $page, int $total): string
    {
        $html = '';

        foreach ($elements as $el) {
            $html .= $this->renderElement($el->toArray(), $meta, $store['qr'], $page, $total);
        }

        return $html;
    }

    private function renderElement(array $el, array $meta, string $qr, int $page, int $total): string
    {
        $left = (float) $el['x'];
        $top = (float) $el['y'];
        $z = (int) $el['z_index'];

        if ($el['type'] === 'qr') {
            $size = ($el['width'] ?: 160);
            $style = "position:absolute;left:{$left}px;top:{$top}px;width:{$size}px;height:{$size}px;z-index:{$z};";

            return '<img src="'.$qr.'" alt="QR" style="'.$style.'" />';
        }

        if ($el['type'] === 'image') {
            $width = ($el['width'] ?: 200);
            $height = ($el['height'] ?: $width);
            $src = ! empty($el['content']) ? e($this->imageDataUri($el['content'])) : '';
            $style = "position:absolute;left:{$left}px;top:{$top}px;width:{$width}px;height:{$height}px;z-index:{$z};object-fit:contain;border-radius:8px;";

            return $src === '' ? '' : '<img src="'.$src.'" alt="Imagen" style="'.$style.'" />';
        }

        $content = (string) ($el['content'] ?? '');
        $content = $this->replaceVariables($content, $meta, $qr, $page, $total);

        $width = $el['width'] ? (int) round($el['width']).'px' : 'auto';
        $height = $el['height'] ? (int) round($el['height']).'px' : 'auto';

        $style = "position:absolute;left:{$left}px;top:{$top}px;width:{$width};height:{$height};z-index:{$z};";
        if (! empty($el['font_size'])) {
            $style .= 'font-size:'.(int) $el['font_size'].'px;';
        }
        if (! empty($el['font_weight'])) {
            $style .= "font-weight:{$el['font_weight']};";
        }
        if (! empty($el['font_family'])) {
            $style .= "font-family:{$el['font_family']};";
        }
        if (! empty($el['color'])) {
            $style .= "color:{$el['color']};";
        }
        $style .= 'text-align:'.($el['text_align'] ?? 'center').';';
        if (! empty($el['word_wrap']) || array_key_exists('word_wrap', $el)) {
            if (! ($el['word_wrap'] ?? true)) {
                $style .= 'white-space:nowrap;overflow:hidden;';
            }
        }

        return '<div style="'.$style.'">'.$content.'</div>';
    }

    private function replaceVariables(string $content, array $meta, string $qr, int $page, int $total): string
    {
        return strtr($content, [
            '{evento}' => $meta['eventName'],
            '{nombre_evento}' => $meta['eventName'],
            '{fecha_evento}' => $meta['fechas'],
            '{lugar_evento}' => $meta['lugar'],
            '{pagina}' => (string) $page,
            '{total_paginas}' => (string) $total,
            '{qr}' => $qr,
        ]);
    }

    private function backgroundDataUri(CertificateTemplate $template): string
    {
        if (! $template->background_path || ! Storage::disk('public')->exists($template->background_path)) {
            return '';
        }

        $data = Storage::disk('public')->get($template->background_path);

        return 'data:image/png;base64,'.base64_encode($data);
    }

    private function imageDataUri(string $path): string
    {
        $path = ltrim($path, '/');

        if (preg_match('#^https?://#', $path)) {
            return $path;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return '';
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }

    private function qrDataUri(string $data): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200, 4),
            new SvgImageBackEnd,
        );

        return 'data:image/svg+xml;base64,'.base64_encode((new Writer($renderer))->writeString($data));
    }

    private function styles(array $config, int $listW, bool $forPdf): string
    {
        $accent = $config['accent_color'];
        $text = $config['text_color'];
        $badgeText = $config['badge_text_color'];
        $font = (int) $config['row_font_size'];
        $dayFont = (int) $config['day_font_size'];
        $timeCol = (int) $config['time_column'];
        $rowPad = (int) $config['row_padding_y'];
        $metaFont = $font - 1;
        $peopleFont = $font - 2;
        $typeColors = $this->typeColors($config);
        $bodyW = $this->bodyWidth($config, $listW);

        // Dompdf no soporta flexbox: en modo PDF se usa una fila de celdas.
        $rowLayout = $forPdf
            ? <<<CSS
.pi { display: table; width: 100%; padding: {$rowPad}px 0; border-bottom: 1px solid #e5e7eb; }
.pi-time { display: table-cell; width: {$timeCol}px; font-size: {$font}px; font-weight: 700; color: {$accent}; line-height: 1.3; }
.pi-body { display: table-cell; width: {$bodyW}px; }
CSS
            : <<<CSS
.pi { display: flex; gap: 16px; padding: {$rowPad}px 0; border-bottom: 1px solid #e5e7eb; }
.pi-time { width: {$timeCol}px; font-size: {$font}px; font-weight: 700; color: {$accent}; line-height: 1.3; flex-shrink: 0; }
.pi-body { flex: 1; min-width: 0; }
CSS;

        $pageBreak = $forPdf
            ? '.page { page-break-after: always; }
               .page:last-child { page-break-after: auto; }'
            : '';

        return <<<CSS
@page { size: {$this->pageW()}px {$this->pageH()}px; margin: 0; }
@media print {
    .page-holder { width: {$this->pageW()}px !important; height: {$this->pageH()}px !important; margin: 0 !important; }
    .page { transform: none !important; width: {$this->pageW()}px !important; height: {$this->pageH()}px !important; page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    .bg { position: absolute; }
}
.page { position: relative; width: {$this->pageW()}px; height: {$this->pageH()}px; overflow: hidden; margin: 0 auto; font-family: Helvetica, Arial, sans-serif; }
{$pageBreak}
.bg { position: absolute; }
.bg-fixed { position: fixed; top: 0; left: 0; width: {$this->pageW()}px; height: {$this->pageH()}px; z-index: -1; }
.program-list { position: absolute; box-sizing: border-box; overflow: hidden; }
.pd { font-size: {$dayFont}px; font-weight: 800; color: {$accent}; text-transform: uppercase; letter-spacing: 0.03em; border-bottom: 2px solid {$accent}; padding-bottom: 4px; margin-bottom: 2px; }
{$rowLayout}
.pi-badge { display: inline-block; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; color: {$badgeText}; border-radius: 999px; padding: 2px 8px; margin-bottom: 3px; }
.pi-badge-workshop { background: {$typeColors['workshop']}; }
.pi-badge-presentation { background: {$typeColors['presentation']}; }
.pi-badge-conference { background: {$typeColors['conference']}; }
.pi-badge-block { background: {$typeColors['block']}; }
.pi-title { font-size: {$font}px; font-weight: 700; color: {$text}; line-height: 1.3; }
.pi-meta { font-size: {$metaFont}px; color: #6b7280; line-height: 1.3; margin-top: 1px; }
.pi-people { font-size: {$peopleFont}px; color: #374151; line-height: 1.3; }
CSS;
    }

    private function pageW(): int
    {
        return (int) ($this->pageWidth ?? 816);
    }

    private function pageH(): int
    {
        return (int) ($this->pageHeight ?? 1056);
    }

    private function screenOverlay(int $pageW, int $pageH): string
    {
        return <<<CSS
@media screen {
    body { background: #e5e7eb; padding: 24px 0; }
    .page-holder { width: {$pageW}px; height: {$pageH}px; margin: 0 auto 24px; }
    .page { transform-origin: top left; box-shadow: 0 10px 32px rgba(0, 0, 0, 0.18); }
}
CSS;
    }

    private function printToolbar(?string $pdfUrl): string
    {
        $pdfBtn = $pdfUrl === null
            ? ''
            : <<<HTML
<a class="print-action print-action-pdf" href="{$pdfUrl}">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Descargar PDF
</a>
HTML;

        return <<<HTML
<style>
@media screen {
    .print-actions {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 1000;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 18px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    .print-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border: none;
        border-radius: 8px;
        font-family: system-ui, sans-serif;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
    }
    .print-action.print { background: #dc2626; color: #ffffff; }
    .print-action.print:hover { background: #b91c1c; }
    .print-action-pdf {
        background: #ffffff;
        color: #111827;
        border: 1px solid #d1d5db;
    }
    .print-action-pdf:hover { background: #f3f4f6; }
}
@media print {
    .print-actions { display: none !important; }
}
</style>
<div class="print-actions">
    {$pdfBtn}
    <button class="print-action print" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimir
    </button>
</div>
HTML;
    }
}
