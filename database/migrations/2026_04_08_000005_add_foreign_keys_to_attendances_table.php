<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('presentation_id')->references('id')->on('presentations')->nullOnDelete();
            $table->foreign('workshop_id')->references('id')->on('workshops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['presentation_id']);
            $table->dropForeign(['workshop_id']);
        });
    }
};
