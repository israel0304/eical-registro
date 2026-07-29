<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentation_authors', function (Blueprint $table) {
            $table->boolean('presented')->default(false);
            $table->timestamp('presented_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('presentation_authors', function (Blueprint $table) {
            $table->dropColumn(['presented', 'presented_at']);
        });
    }
};
