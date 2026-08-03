<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificar Constancia - {{ config('app.name', 'EICAL 2026') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Georgia, 'Times New Roman', serif;
            background: #f3f4f6;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 560px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 40px;
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 12px;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-family: system-ui, sans-serif;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .title { font-size: 22px; font-weight: bold; color: #111827; margin: 20px 0 4px; }
        .subtitle { font-size: 14px; color: #6b7280; margin: 0 0 24px; font-family: system-ui, sans-serif; }
        .row { padding: 14px 0; border-top: 1px solid #f3f4f6; text-align: left; }
        .row-label {
            font-family: system-ui, sans-serif;
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 4px;
        }
        .row-value { font-size: 15px; color: #1f2937; }
        .footer { margin-top: 24px; font-family: system-ui, sans-serif; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">Constancia verificada</span>
        <h1 class="title">{{ $metadata['nombre'] ?? $user->name }}</h1>
        <p class="subtitle">Registro EICAL 2026</p>

        <div class="row">
            <div class="row-label">Tipo de participación</div>
            <div class="row-value">{{ $participationType->label ?? $metadata['tipo_participacion'] ?? '—' }}</div>
        </div>
        <div class="row">
            <div class="row-label">Actividad</div>
            <div class="row-value">{{ $eventName }}</div>
        </div>
        <div class="row">
            <div class="row-label">Fecha del evento</div>
            <div class="row-value">{{ $eventDate }}</div>
        </div>
        @if ($eventLocation)
            <div class="row">
                <div class="row-label">Lugar</div>
                <div class="row-value">{{ $eventLocation }}</div>
            </div>
        @endif
        <div class="row">
            <div class="row-label">Folio único</div>
            <div class="row-value" style="font-family: system-ui, sans-serif;">{{ $certificate->folio }}</div>
        </div>

        <p class="footer">Este documento es válido y ha sido emitido por el sistema de registro oficial.</p>
    </div>
</body>
</html>
