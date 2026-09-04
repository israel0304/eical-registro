<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Programa - {{ $eventName }}</title>
    <style>
        @page { size: 8.5in 11in; margin: 14mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: {{ $forPdf ?? false ? '14mm' : '32px' }};
            padding-top: {{ $forPdf ?? false ? '0' : '72px' }};
            font-family: system-ui, -apple-system, sans-serif;
            color: #111827;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 4px 0 0; color: #6b7280; font-size: 14px; }
        .day {
            margin-bottom: 28px;
        }
        .day h2 {
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #b91c1c;
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 6px;
            margin: 0 0 10px;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        th { font-size: 11px; text-transform: uppercase; color: #6b7280; }
        .time { white-space: nowrap; font-variant-numeric: tabular-nums; width: 120px; color: #374151; }
        .badge {
            display: inline-block;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 2px 8px;
            border-radius: 999px;
            margin-bottom: 4px;
        }
        .badge.block { background: #fef3c7; color: #92400e; }
        .badge.activity { background: #e0f2fe; color: #075985; }
        .title { font-weight: 600; }
        .meta { color: #6b7280; font-size: 12px; margin-top: 2px; }
        .empty { color: #9ca3af; font-style: italic; }
        .footer { text-align: center; color: #9ca3af; font-size: 11px; margin-top: 24px; }
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
        @media screen and (max-width: 480px) {
            .print-actions {
                top: 8px;
                right: 8px;
                left: 8px;
                max-width: calc(100vw - 16px);
                flex-wrap: wrap;
                justify-content: center;
                box-sizing: border-box;
                gap: 8px;
                padding: 8px 12px;
            }
            .print-action {
                flex: 1 1 auto;
                padding: 7px 10px;
                font-size: 13px;
                gap: 6px;
            }
        }
        @media print {
            .print-actions { display: none; }
            body { padding: 0; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    @if (! ($forPdf ?? false))
        <div class="print-actions">
            <a class="print-action print-action-pdf" href="{{ $pdfUrl ?? '/programa/imprimir/pdf' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Descargar PDF
            </a>
            <button class="print-action print" onclick="window.print()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir
            </button>
        </div>
    @endif
    <div class="header">
        <h1>Programa del evento</h1>
        <p>{{ $eventName }}</p>
    </div>

    @forelse ($groups as $group)
        <section class="day">
            <h2>{{ $group['label'] }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Actividad</th>
                        <th>Ubicación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['items'] as $item)
                        <tr>
                            <td class="time">{{ $item['time_label'] ?: '—' }}</td>
                            <td>
                                <span class="badge {{ $item['kind'] === 'activity' ? 'activity' : 'block' }}">
                                    {{ $item['kind'] === 'activity' ? ($item['activity_label'] ?? 'Actividad') : ($item['block_label'] ?? 'Actividad') }}
                                </span>
                                <div class="title">{{ $item['title'] }}</div>
                                @if ($item['location'] || $item['activity_name'])
                                    <div class="meta">
                                        {{ $item['location'] }}
                                        @if ($item['location'] && $item['activity_name'])
                                            ·
                                        @endif
                                        {{ $item['activity_name'] }}
                                    </div>
                                @endif
                            </td>
                            <td>{{ $item['location'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p class="empty">Aún no hay actividades en el programa.</p>
    @endforelse

    <div class="footer">Generado el {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
