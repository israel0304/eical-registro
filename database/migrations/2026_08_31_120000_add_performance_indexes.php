<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('is_active', 'users_is_active_index');
        });

        Schema::table('workshops', function (Blueprint $table) {
            $table->index(['day', 'start_time'], 'workshops_day_start_time_index');
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->index(['day', 'start_time'], 'presentations_day_start_time_index');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['user_id', 'event_day'], 'attendances_user_id_event_day_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_is_active_index');
        });

        Schema::table('workshops', function (Blueprint $table) {
            $table->dropIndex('workshops_day_start_time_index');
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->dropIndex('presentations_day_start_time_index');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_user_id_event_day_index');
        });
    }
};
