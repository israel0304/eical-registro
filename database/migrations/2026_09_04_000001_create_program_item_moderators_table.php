<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_item_moderators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_item_id')->constrained('program_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['program_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_item_moderators');
    }
};
