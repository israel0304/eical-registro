<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('presentation_id')->nullable();
            $table->unsignedBigInteger('workshop_id')->nullable();
            $table->integer('event_day');
            $table->foreignId('registered_by')->constrained('users');
            $table->boolean('certificate_generated')->default(false);
            $table->timestamp('certificate_generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
