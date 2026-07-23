<x-mail::message>
# Código QR de asistencia

Hola **{{ $instructorName }}**,

A continuación encontrarás el código QR de asistencia para el taller **{{ $workshopName }}**.

<x-mail::table>
| **Fecha** | {{ $day }} |
|:---|:---|
| **Horario** | {{ $startTime }} - {{ $endTime }} |
| **Lugar** | {{ $location }} |
</x-mail::table>

## Enlace de asistencia

Puede compartir este enlace con los asistentes:

**{{ $scanUrl }}**

## Código QR

<img src="data:image/png;base64,{{ $qrImageBase64 }}" alt="QR Asistencia" width="250" height="250" />

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
