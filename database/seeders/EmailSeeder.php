<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\EmailTrigger;
use Illuminate\Database\Seeder;

class EmailSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'workshop.enrollment' => [
                'name' => 'Confirmación de inscripción a taller',
                'subject' => 'Inscripción confirmada: {{ taller }}',
                'default_trigger' => true,
                'body_html' => <<<'HTML'
<h2>Inscripción confirmada</h2>

<p>Hola <strong>{{ nombre_completo }}</strong>,</p>

<p>Tu inscripción al taller ha sido confirmada.</p>

<table>
<tr><th>Taller</th><td>{{ taller }}</td></tr>
<tr><th>Fecha</th><td>{{ dia }}</td></tr>
<tr><th>Horario</th><td>{{ hora_inicio }} - {{ hora_fin }}</td></tr>
<tr><th>Lugar</th><td>{{ lugar }}</td></tr>
</table>

<p>Te esperamos.</p>

<p>Saludos,<br>El equipo</p>
HTML,
            ],
            'workshop.qr_sent' => [
                'name' => 'Código QR de asistencia a instructor',
                'subject' => 'Código QR de asistencia: {{ taller }}',
                'default_trigger' => true,
                'body_html' => <<<'HTML'
<h2>Código QR de asistencia</h2>

<p>Hola <strong>{{ nombre_completo }}</strong>,</p>

<p>A continuación encontrarás el código QR de asistencia para el taller <strong>{{ taller }}</strong>.</p>

<table>
<tr><th>Fecha</th><td>{{ dia }}</td></tr>
<tr><th>Horario</th><td>{{ hora_inicio }} - {{ hora_fin }}</td></tr>
<tr><th>Lugar</th><td>{{ lugar }}</td></tr>
</table>

<h3>Enlace de asistencia</h3>

<p>Puedes compartir este enlace con los asistentes:</p>

<p><a href="{{ url_escaneo }}">{{ url_escaneo }}</a></p>

<h3>Código QR</h3>

<p><img src="{{ qrImage }}" alt="QR Asistencia" width="250" height="250" /></p>

<p>Saludos,<br>El equipo</p>
HTML,
            ],
            'user.welcome' => [
                'name' => 'Bienvenida y activación de cuenta',
                'subject' => 'Activa tu cuenta',
                'default_trigger' => true,
                'body_html' => <<<'HTML'
<h2>¡Bienvenido!</h2>

<p>Hola <strong>{{ nombre_completo }}</strong>,</p>

<p>Has sido registrado en el sistema de registro del Encuentro de Innovación, Ciencia, Tecnología, Academia y Saberes (EICAL).</p>

<p>Para activar tu cuenta y establecer tu contraseña, haz clic en el siguiente enlace:</p>

<p><a href="{{ url_activacion }}">Activar mi cuenta</a></p>

<p>Este enlace expirará en 60 minutos.</p>

<p>Si no esperabas este correo, puedes ignorarlo.</p>

<p>Saludos,<br>El equipo</p>
HTML,
            ],
            'workshop.enrollment_cancelled' => [
                'name' => 'Inscripción a taller cancelada',
                'subject' => 'Inscripción cancelada: {{ taller }}',
                'body_html' => <<<'HTML'
<h2>Inscripción cancelada</h2>

<p>Hola <strong>{{ nombre_completo }}</strong>,</p>

<p>Tu inscripción al taller <strong>{{ taller }}</strong> ha sido cancelada.</p>

<p>Si fue un error, puedes volver a inscribirte mientras haya cupos disponibles.</p>

<p>Saludos,<br>El equipo</p>
HTML,
            ],
            'attendance.confirmed' => [
                'name' => 'Asistencia confirmada (check-in)',
                'subject' => 'Asistencia registrada: {{ taller }}',
                'body_html' => <<<'HTML'
<h2>Asistencia confirmada</h2>

<p>Hola <strong>{{ nombre_completo }}</strong>,</p>

<p>Tu asistencia al taller <strong>{{ taller }}</strong> ha sido registrada.</p>

<table>
<tr><th>Fecha</th><td>{{ dia }}</td></tr>
<tr><th>Horario</th><td>{{ hora_inicio }} - {{ hora_fin }}</td></tr>
<tr><th>Lugar</th><td>{{ lugar }}</td></tr>
</table>

<p>Recuerda que tu constancia estará disponible en el módulo de constancias al cumplir los requisitos.</p>

<p>Saludos,<br>El equipo</p>
HTML,
            ],
            'workshop.created' => [
                'name' => 'Nuevo taller publicado',
                'subject' => 'Nuevo taller: {{ name }}',
                'body_html' => <<<'HTML'
<h2>Nuevo taller disponible</h2>

<p>Hola,</p>

<p>Se ha publicado un nuevo taller en el programa del EICAL:</p>

<p><strong>{{ name }}</strong></p>

<table>
<tr><th>Fecha</th><td>{{ day }}</td></tr>
<tr><th>Horario</th><td>{{ start_time }} - {{ end_time }}</td></tr>
<tr><th>Lugar</th><td>{{ location }}</td></tr>
<tr><th>Cupo</th><td>{{ capacity }}</td></tr>
</table>

<p>Inscríbete antes de que se agoten los cupos.</p>

<p>Saludos,<br>El equipo</p>
HTML,
            ],
            'presentation.created' => [
                'name' => 'Nueva ponencia registrada',
                'subject' => 'Nueva ponencia: {{ title }}',
                'body_html' => <<<'HTML'
<h2>Nueva ponencia registrada</h2>

<p>Se ha registrado una nueva ponencia:</p>

<p><strong>{{ title }}</strong></p>

<table>
<tr><th>Fecha</th><td>{{ day }}</td></tr>
<tr><th>Horario</th><td>{{ start_time }} - {{ end_time }}</td></tr>
<tr><th>Lugar</th><td>{{ location }}</td></tr>
</table>

<p><strong>Resumen:</strong></p>

<p>{{ abstract }}</p>

<p>Saludos,<br>El equipo</p>
HTML,
            ],
            'user.created' => [
                'name' => 'Cuenta de usuario creada',
                'subject' => 'Nueva cuenta registrada',
                'body_html' => <<<'HTML'
<h2>Nueva cuenta registrada</h2>

<p>Se ha creado una cuenta nueva en el sistema de registro del EICAL:</p>

<table>
<tr><th>Nombre</th><td>{{ first_name }} {{ last_name }}</td></tr>
<tr><th>Correo</th><td>{{ email }}</td></tr>
<tr><th>DNI</th><td>{{ dni }}</td></tr>
</table>

<p>Saludos,<br>El equipo</p>
HTML,
            ],
        ];

        foreach ($templates as $eventKey => $data) {
            EmailTemplate::updateOrCreate(
                ['event_key' => $eventKey],
                collect($data)->except('default_trigger')->all(),
            );

            if (! ($data['default_trigger'] ?? false)) {
                continue;
            }

            EmailTrigger::updateOrCreate(
                ['event_key' => $eventKey],
                [
                    'email_template_id' => EmailTemplate::where('event_key', $eventKey)->first()->id,
                    'to' => 'destinatario',
                    'role_id' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
