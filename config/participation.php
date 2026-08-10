<?php

return [
    'event_kinds' => [
        'workshop' => 'Taller',
        'presentation' => 'Ponencia',
        'conference' => 'Conferencia',
        'event' => 'Evento',
        'staff' => 'Staff',
    ],

    'roles' => [
        'enrolled_attendance' => 'Asistente',
        'instructor' => 'Instructor',
        'presented_author' => 'Ponente presentado',
        'speaker' => 'Speaker',
        'moderator' => 'Moderador',
    ],

    'kinds' => [
        'conference' => [
            'magistral' => 'Magistral',
            'especial' => 'Especial',
            'simposio' => 'Simposio',
            'mesa_dialogo' => 'Mesa de diálogo',
        ],
        'event' => [
            'carta' => 'Carta de invitación',
        ],
    ],

    'role_rules' => [
        'workshop' => ['enrolled_attendance', 'instructor'],
        'presentation' => ['presented_author'],
        'conference' => ['speaker', 'moderator'],
        'event' => [],
        'staff' => [],
    ],
];
