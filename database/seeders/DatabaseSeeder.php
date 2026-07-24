<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\Role::updateOrCreate(['id' => 1], ['name' => 'Administrator']);
        \App\Models\Role::updateOrCreate(['id' => 2], ['name' => 'Ponente']);
        \App\Models\Role::updateOrCreate(['id' => 3], ['name' => 'Asistente']);

        \App\Models\User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'Sistema',
            'email' => 'admin@cinvestav.edu.mx',
            'dni' => 'ADMIN001',
            'role_id' => 1,
            'is_active' => true,
            'password' => 'password',
        ]);
    }
}
