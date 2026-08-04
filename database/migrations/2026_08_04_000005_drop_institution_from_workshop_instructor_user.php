<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_instructor_user', function (Blueprint $table) {
            $table->dropColumn('institution');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_instructor_user', function (Blueprint $table) {
            $table->string('institution')->nullable();
        });
    }
};
