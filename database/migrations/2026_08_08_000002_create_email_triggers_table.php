<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 100)->unique();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 100)->default('destinatario');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_triggers');
    }
};
