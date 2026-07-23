<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = \App\Models\Role::create(['name' => 'Administrator']);
        \App\Models\Role::create(['name' => 'Ponente']);
        \App\Models\Role::create(['name' => 'Asistente']);

        \App\Models\User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'Sistema',
            'email' => 'admin@cinvestav.edu.mx',
            'dni' => 'ADMIN001',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
    }
}
