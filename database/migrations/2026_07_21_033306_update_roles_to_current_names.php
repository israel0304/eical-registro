<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Role::where('name', 'Staff')->update(['name' => 'Ponente']);
        Role::where('name', 'Participante')->update(['name' => 'Asistente']);
        Role::where('name', 'Tallerista')->delete();
    }

    public function down(): void
    {
        Role::where('name', 'Ponente')->update(['name' => 'Staff']);
        Role::where('name', 'Asistente')->update(['name' => 'Participante']);
        Role::firstOrCreate(['name' => 'Tallerista']);
    }
};
