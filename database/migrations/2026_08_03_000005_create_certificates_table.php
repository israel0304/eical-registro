<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique()->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->string('event_type');
            $table->json('metadata')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'participation_type_id', 'event_type', 'event_id'], 'certificates_unique_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
