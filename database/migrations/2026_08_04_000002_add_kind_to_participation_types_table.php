<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participation_types', function (Blueprint $table) {
            $table->string('kind')->nullable()->after('event_kind');
        });
    }

    public function down(): void
    {
        Schema::table('participation_types', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
