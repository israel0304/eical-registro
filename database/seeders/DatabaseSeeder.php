<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminRole = \App\Models\Role::create(['name' => 'Administrator']);
        $staffRole = \App\Models\Role::create(['name' => 'Staff']);
        $talleristaRole = \App\Models\Role::create(['name' => 'Tallerista']);
        $participanteRole = \App\Models\Role::create(['name' => 'Participante']);

        \App\Models\User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'dni' => 'ADMIN001',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
    }
}
