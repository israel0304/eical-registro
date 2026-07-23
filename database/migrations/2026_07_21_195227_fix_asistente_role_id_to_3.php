<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::table('roles')->insert([
            'id' => 3,
            'name' => 'Asistente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('users')->where('role_id', 4)->update(['role_id' => 3]);
        \Illuminate\Support\Facades\DB::table('roles')->where('id', 4)->delete();
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('roles')->insert([
            'id' => 4,
            'name' => 'Asistente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('users')->where('role_id', 3)->update(['role_id' => 4]);
        \Illuminate\Support\Facades\DB::table('roles')->where('id', 3)->delete();
    }
};
