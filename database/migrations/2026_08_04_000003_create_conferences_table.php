<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conferences', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('kind')->default('especial');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('day')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('conference_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('speaker');
            $table->boolean('activated')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->unique(['conference_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conference_members');
        Schema::dropIfExists('conferences');
    }
};
