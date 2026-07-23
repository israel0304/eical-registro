<x-mail::message>
# Inscripción confirmada

Hola **{{ $userName }}**,

Tu inscripción al taller ha sido confirmada.

<x-mail::table>
| Taller | **{{ $workshopName }}** |
|:---|:---|
| **Fecha** | {{ $day }} |
| **Horario** | {{ $startTime }} - {{ $endTime }} |
| **Lugar** | {{ $location }} |
</x-mail::table>

Te esperamos.

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
