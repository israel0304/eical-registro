<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('author_order')->default(1);
            $table->timestamps();
            $table->unique(['presentation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_authors');
    }
};
