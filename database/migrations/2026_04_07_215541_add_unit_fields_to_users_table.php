<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
            $table->string('custom_unit')->nullable();
            $table->string('custom_department')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn(['unit_id', 'department_id', 'custom_unit', 'custom_department']);
        });
    }
};
