<?php

use App\Models\Conference;
use App\Models\Presentation;
use App\Models\Workshop;

return [
    /**
     * Actividades que pueden enlazarse a items del programa.
     *
     * La llave es el nombre corto del tipo (se almacena en program_items.activity_type)
     * y el valor es la clase Eloquent del modelo.
     */
    'activity_types' => [
        'workshop' => Workshop::class,
        'presentation' => Presentation::class,
        'conference' => Conference::class,
    ],

    /**
     * Tipos de bloque del programa (activities no enlazadas).
     */
    'block_types' => [
        'registro' => 'Registro',
        'inauguracion' => 'Inauguración',
        'receso' => 'Receso',
        'clausura' => 'Clausura',
        'convivencia' => 'Convivencia',
        'otro' => 'Otro',
    ],
];
