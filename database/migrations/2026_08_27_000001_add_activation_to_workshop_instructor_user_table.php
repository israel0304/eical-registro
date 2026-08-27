<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_instructor_user', function (Blueprint $table) {
            $table->boolean('activated')->default(false);
            $table->timestamp('activated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_instructor_user', function (Blueprint $table) {
            $table->dropColumn(['activated', 'activated_at']);
        });
    }
};
