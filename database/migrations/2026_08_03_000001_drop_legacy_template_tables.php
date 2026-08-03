<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('template_fields');
        Schema::dropIfExists('templates');
    }

    public function down(): void
    {
        // Not restoring legacy unused tables.
    }
};
