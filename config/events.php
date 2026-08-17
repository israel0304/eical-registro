<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zona horaria del evento
    |--------------------------------------------------------------------------
    |
    | Zona en la que se interpretan los horarios (día/hora) de talleres,
    | ponencias y conferencias. El resto de la aplicación corre en UTC.
    |
    */

    'timezone' => env('EVENT_TIMEZONE', 'America/Mexico_City'),

    /*
    |--------------------------------------------------------------------------
    | Catálogo de eventos
    |--------------------------------------------------------------------------
    |
    | Cada entrada define un evento que el sistema puede registrar en la
    | auditoría (event_log) y, si tiene `has_trigger`, disparar correos a
    | través de un EmailTrigger. `variables` lista las variables disponibles
    | para la plantilla de correo asociada al evento.
    |
    */

    'events' => [

        'workshop.enrollment' => [
            'label' => 'Confirmación de inscripción a taller',
            'group' => 'Talleres',
            'has_trigger' => true,
            'to_options' => [
                'destinatario' => 'Participante inscrito',
            ],
            'variables' => [
                'nombre_completo' => 'Nombre completo del participante',
                'taller' => 'Nombre del taller',
                'dia' => 'Día del taller',
                'hora_inicio' => 'Hora de inicio',
                'hora_fin' => 'Hora de fin',
                'lugar' => 'Lugar / salón',
            ],
        ],

        'workshop.enrollment_cancelled' => [
            'label' => 'Inscripción a taller cancelada',
            'group' => 'Talleres',
            'has_trigger' => true,
            'to_options' => [
                'destinatario' => 'Participante',
            ],
            'variables' => [
                'nombre_completo' => 'Nombre completo del participante',
                'taller' => 'Nombre del taller',
                'dia' => 'Día del taller',
                'hora_inicio' => 'Hora de inicio',
                'hora_fin' => 'Hora de fin',
                'lugar' => 'Lugar / salón',
            ],
        ],

        'workshop.qr_sent' => [
            'label' => 'Código QR de asistencia a instructor',
            'group' => 'Talleres',
            'has_trigger' => true,
            'to_options' => [
                'destinatario' => 'Instructor',
            ],
            'variables' => [
                'nombre_completo' => 'Nombre completo del instructor',
                'taller' => 'Nombre del taller',
                'dia' => 'Día del taller',
                'hora_inicio' => 'Hora de inicio',
                'hora_fin' => 'Hora de fin',
                'lugar' => 'Lugar / salón',
                'url_escaneo' => 'Enlace de asistencia (escaneo)',
                'qrImage' => 'Código QR (imagen embebida)',
            ],
        ],

        'attendance.confirmed' => [
            'label' => 'Asistencia confirmada (check-in)',
            'group' => 'Talleres',
            'has_trigger' => true,
            'to_options' => [
                'destinatario' => 'Participante',
            ],
            'variables' => [
                'nombre_completo' => 'Nombre completo del participante',
                'taller' => 'Nombre del taller',
                'dia' => 'Día del taller',
                'hora_inicio' => 'Hora de inicio',
                'hora_fin' => 'Hora de fin',
                'lugar' => 'Lugar / salón',
            ],
        ],

        'user.welcome' => [
            'label' => 'Bienvenida y activación de cuenta',
            'group' => 'Usuarios',
            'has_trigger' => true,
            'to_options' => [
                'destinatario' => 'Nuevo usuario',
            ],
            'variables' => [
                'nombre_completo' => 'Nombre completo del usuario',
                'nombre' => 'Nombre del usuario',
                'url_activacion' => 'Enlace de activación de cuenta',
            ],
        ],

        'user.registered' => [
            'label' => 'Registro de usuario (verificación de correo)',
            'group' => 'Usuarios',
            'has_trigger' => true,
            'to_options' => [
                'destinatario' => 'Usuario que se registra',
            ],
            'variables' => [
                'nombre_completo' => 'Nombre completo del usuario',
                'nombre' => 'Nombre del usuario',
                'url_verificacion' => 'Enlace de verificación de correo',
            ],
        ],

        'user.password_reset' => [
            'label' => 'Restablecer contraseña',
            'group' => 'Usuarios',
            'has_trigger' => true,
            'to_options' => [
                'destinatario' => 'Usuario que solicita el restablecimiento',
            ],
            'variables' => [
                'nombre_completo' => 'Nombre completo del usuario',
                'nombre' => 'Nombre del usuario',
                'url_restablecer' => 'Enlace para restablecer la contraseña',
            ],
        ],

        // Eventos de ciclo de vida: se auditan automáticamente vía trait
        // (solo registro en event_log; sin disparador de correo).

        'user.created' => [
            'label' => 'Cuenta de usuario creada',
            'group' => 'Usuarios',
            'has_trigger' => true,
            'to_options' => [],
            'variables' => [
                'first_name' => 'Nombre',
                'last_name' => 'Apellido',
                'email' => 'Correo electrónico',
                'dni' => 'DNI',
            ],
        ],

        'user.updated' => [
            'label' => 'Usuario actualizado',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'user.deleted' => [
            'label' => 'Usuario eliminado',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'workshop.created' => [
            'label' => 'Taller creado',
            'group' => 'Talleres',
            'has_trigger' => true,
            'to_options' => [],
            'variables' => [
                'name' => 'Nombre del taller',
                'description' => 'Descripción',
                'location' => 'Lugar / salón',
                'day' => 'Día',
                'start_time' => 'Hora de inicio',
                'end_time' => 'Hora de fin',
                'capacity' => 'Cupo',
            ],
        ],

        'workshop.updated' => [
            'label' => 'Taller actualizado',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'workshop.deleted' => [
            'label' => 'Taller eliminado',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'presentation.accepted' => [
            'label' => 'Ponencia aceptada (activación de cuenta)',
            'group' => 'Ponencias',
            'has_trigger' => true,
            'to_options' => [
                'destinatario' => 'Ponente importado',
            ],
            'variables' => [
                'nombre_completo' => 'Nombre completo del ponente',
                'nombre' => 'Nombre del ponente',
                'submission_id' => 'ID de la ponencia',
                'url_activacion' => 'Enlace de activación de cuenta',
            ],
        ],

        'presentation.created' => [
            'label' => 'Ponencia creada',
            'group' => 'Ponencias',
            'has_trigger' => true,
            'to_options' => [],
            'variables' => [
                'title' => 'Título',
                'abstract' => 'Resumen',
                'discipline' => 'Disciplina',
                'location' => 'Lugar / salón',
                'day' => 'Día',
                'start_time' => 'Hora de inicio',
                'end_time' => 'Hora de fin',
            ],
        ],

        'presentation.updated' => [
            'label' => 'Ponencia actualizada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'presentation.deleted' => [
            'label' => 'Ponencia eliminada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'conference.created' => [
            'label' => 'Conferencia creada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'conference.updated' => [
            'label' => 'Conferencia actualizada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'conference.deleted' => [
            'label' => 'Conferencia eliminada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'workshop_enrollment.created' => [
            'label' => 'Inscripción a taller registrada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'workshop_enrollment.updated' => [
            'label' => 'Inscripción a taller actualizada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'workshop_enrollment.deleted' => [
            'label' => 'Inscripción a taller eliminada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'attendance.created' => [
            'label' => 'Asistencia registrada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'attendance.updated' => [
            'label' => 'Asistencia actualizada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'attendance.deleted' => [
            'label' => 'Asistencia eliminada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

        'certificate.generated' => [
            'label' => 'Constancia generada',
            'group' => 'Auditoría',
            'has_trigger' => false,
            'variables' => [],
        ],

    ],

];
