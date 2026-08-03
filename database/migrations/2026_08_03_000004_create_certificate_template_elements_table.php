<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_template_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('certificate_templates')->cascadeOnDelete();
            $table->string('type')->default('text');
            $table->text('content')->nullable();
            $table->string('variable')->nullable();
            $table->float('x')->default(0);
            $table->float('y')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('font_size')->nullable();
            $table->string('font_weight')->nullable();
            $table->string('font_family')->nullable();
            $table->string('color')->nullable();
            $table->string('text_align')->default('center');
            $table->integer('z_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_template_elements');
    }
};
