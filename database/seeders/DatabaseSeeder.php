<?php

namespace Database\Seeders;

use App\Models\ParticipationType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Role::updateOrCreate(['id' => 1], ['name' => 'Administrator']);
        Role::updateOrCreate(['id' => 2], ['name' => 'Ponente']);
        Role::updateOrCreate(['id' => 3], ['name' => 'Asistente']);
        Role::updateOrCreate(['id' => 4], ['name' => 'Instructor']);

        $types = [
            ['key' => 'taller', 'label' => 'Asistente a taller', 'event_kind' => 'workshop', 'role' => 'enrolled_attendance'],
            ['key' => 'taller_instructor', 'label' => 'Instructor de taller', 'event_kind' => 'workshop', 'role' => 'instructor'],
            ['key' => 'ponencia', 'label' => 'Ponente', 'event_kind' => 'presentation', 'role' => 'presented_author'],
        ];

        foreach ($types as $type) {
            ParticipationType::updateOrCreate(['key' => $type['key']], $type);
        }

        $admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'Sistema',
            'email' => 'admin@cinvestav.edu.mx',
            'dni' => 'ADMIN001',
            'is_active' => true,
            'password' => 'password',
        ]);

        $admin->roles()->sync([1]);
    }
}
