<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \App\Models\Role::where('name', 'Staff')->update(['name' => 'Ponente']);
        \App\Models\Role::where('name', 'Participante')->update(['name' => 'Asistente']);
        \App\Models\Role::where('name', 'Tallerista')->delete();
    }

    public function down(): void
    {
        \App\Models\Role::where('name', 'Ponente')->update(['name' => 'Staff']);
        \App\Models\Role::where('name', 'Asistente')->update(['name' => 'Participante']);
        \App\Models\Role::firstOrCreate(['name' => 'Tallerista']);
    }
};
