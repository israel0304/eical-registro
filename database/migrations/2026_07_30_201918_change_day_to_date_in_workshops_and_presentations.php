<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->date('day')->change();
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->date('day')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->string('day')->change();
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->string('day')->nullable()->change();
        });
    }
};
