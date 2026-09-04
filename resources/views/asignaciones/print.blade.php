<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis Asignaciones - {{ $eventName }}</title>
    <style>
        @page { size: 8.5in 11in; margin: 14mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: Helvetica, Arial, sans-serif;
            color: #111827;
            font-size: 12px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 4px 0 0; color: #6b7280; font-size: 13px; }
        .day { margin-bottom: 24px; }
        .day-title {
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #b91c1c;
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 6px;
            margin: 0 0 8px;
        }
        .activity {
            border: 1px solid #f3f4f6;
            border-left: 3px solid #e5e7eb;
            padding: 10px 12px;
            margin-bottom: 12px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .badge {
            display: inline-block;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 2px 8px;
            border-radius: 999px;
            color: #ffffff;
            margin-bottom: 4px;
        }
        .badge.taller { background: #4f46e5; }
        .badge.ponencia { background: #059669; }
        .badge.conferencia { background: #d97706; }
        .activity-title { font-size: 14px; font-weight: 700; margin: 2px 0 6px; }
        .meta { color: #4b5563; font-size: 12px; }
        .meta strong { color: #111827; }
        .description { color: #374151; margin-top: 8px; line-height: 1.5; }
        .participants { margin-top: 10px; }
        .participant {
            padding: 8px 0 0;
            border-top: 1px dotted #e5e7eb;
            margin-top: 8px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .participant-head { font-weight: 700; font-size: 12px; }
        .badge-role {
            display: inline-block;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 1px 6px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            margin-right: 6px;
            font-weight: 700;
        }
        .affiliation { color: #6b7280; font-weight: 400; }
        .semblanza { color: #374151; font-size: 11px; line-height: 1.5; margin-top: 4px; }
        .empty { color: #9ca3af; font-style: italic; }
        .footer { text-align: center; color: #9ca3af; font-size: 10px; margin-top: 20px; }
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
        }
    </style>
</head>
<body>
    @if (! $forPdf)
        <div class="print-actions">
            <a class="print-action print-action-pdf" href="{{ $pdfUrl ?? '/mis-asignaciones/imprimir/pdf' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Descargar PDF
            </a>
            <button class="print-action print" onclick="window.print()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir
            </button>
        </div>
    @endif

    @php
        $groups = $assignments
            ->groupBy(fn (array $item) => $item['day'] ? $item['day']->format('Y-m-d') : 'none')
            ->sortKeys();
    @endphp

    <div class="header">
        <h1>Mis Asignaciones como Moderador</h1>
        <p>{{ $eventName }} · {{ $moderator->name }}</p>
    </div>

    @forelse ($groups as $dayKey => $items)
        <section class="day">
            @if ($dayKey === 'none')
                <h2 class="day-title">Fecha por definir</h2>
            @else
                @php
                    $day = $items->first()['day'];
                    $dayNumber = \App\Support\EventSettings::dayNumber($day->format('Y-m-d'));
                    $dayTotal = \App\Support\EventSettings::totalDays();
                @endphp
                <h2 class="day-title">
                    @if ($dayNumber)
                        Día {{ $dayNumber }} de {{ $dayTotal }} ·
                    @endif
                    {{ $day->locale('es')->translatedFormat('l j \d\e F \d\e Y') }}
                </h2>
            @endif

            @foreach ($items as $item)
                <div class="activity">
                    <span class="badge {{ strtolower($item['type']) }}">
                        {{ $item['type'] }}@if ($item['kind']) · {{ $item['kind'] }}@endif
                    </span>
                    <div class="activity-title">{{ $item['title'] }}</div>
                    <div class="meta">
                        <div><strong>ID:</strong> {{ $item['id'] }}</div>
                        <div>
                            <strong>Horario:</strong>
                            {{ $item['start_time'] ?: '—' }}@if ($item['end_time']) – {{ $item['end_time'] }}@endif
                        </div>
                        <div><strong>Ubicación:</strong> {{ $item['location'] ?: '—' }}</div>
                        @if ($item['discipline'])
                            <div><strong>Disciplina:</strong> {{ $item['discipline'] }}</div>
                        @endif
                        @if ($item['keywords'])
                            <div><strong>Palabras clave:</strong> {{ $item['keywords'] }}</div>
                        @endif
                    </div>
                    @if ($item['description'])
                        <div class="description">{{ $item['description'] }}</div>
                    @endif
                    @if ($item['participants']->isNotEmpty())
                        <div class="participants">
                            @foreach ($item['participants'] as $p)
                                <div class="participant">
                                    <div class="participant-head">
                                        <span class="badge-role">{{ $p['role'] }}</span>
                                        {{ $p['name'] }}@if ($p['affiliation']) <span class="affiliation">– {{ $p['affiliation'] }}</span>@endif
                                    </div>
                                    <div class="semblanza">
                                        {{ $p['semblanza'] !== '' ? $p['semblanza'] : 'Sin semblanza registrada.' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
    @empty
        <p class="empty">No tienes actividades asignadas como moderador actualmente.</p>
    @endforelse

    <div class="footer">Generado el {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>