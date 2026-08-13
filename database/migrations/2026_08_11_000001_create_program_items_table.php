<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('day')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('block_type')->nullable();

            $table->string('activity_type')->nullable();
            $table->unsignedBigInteger('activity_id')->nullable();

            $table->timestamps();

            $table->unique(['activity_type', 'activity_id']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_items');
    }
};
