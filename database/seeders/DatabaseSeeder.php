<?php

namespace Database\Seeders;

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
