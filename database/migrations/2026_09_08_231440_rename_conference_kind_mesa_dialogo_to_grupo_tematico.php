<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('conferences')
            ->where('kind', 'mesa_dialogo')
            ->update(['kind' => 'grupo_tematico']);

        DB::table('participation_types')
            ->where('kind', 'mesa_dialogo')
            ->update(['kind' => 'grupo_tematico']);
    }

    public function down(): void
    {
        DB::table('conferences')
            ->where('kind', 'grupo_tematico')
            ->update(['kind' => 'mesa_dialogo']);

        DB::table('participation_types')
            ->where('kind', 'grupo_tematico')
            ->update(['kind' => 'mesa_dialogo']);
    }
};
