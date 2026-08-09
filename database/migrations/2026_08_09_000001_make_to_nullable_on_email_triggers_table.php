<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_triggers', function (Blueprint $table) {
            $table->string('to', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('email_triggers', function (Blueprint $table) {
            $table->string('to', 100)->default('destinatario')->nullable(false)->change();
        });
    }
};
