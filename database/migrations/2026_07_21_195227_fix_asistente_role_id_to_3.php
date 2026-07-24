<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('roles')->insert([
            'id' => 3,
            'name' => 'Asistente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->where('role_id', 4)->update(['role_id' => 3]);
        DB::table('roles')->where('id', 4)->delete();
    }

    public function down(): void
    {
        DB::table('roles')->insert([
            'id' => 4,
            'name' => 'Asistente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->where('role_id', 3)->update(['role_id' => 4]);
        DB::table('roles')->where('id', 3)->delete();
    }
};
