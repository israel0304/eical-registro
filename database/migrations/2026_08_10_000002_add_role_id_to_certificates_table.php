<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            if (! Schema::hasColumn('certificates', 'role_id')) {
                $table->foreignId('role_id')->nullable()->after('participation_type_id')->constrained('roles')->nullOnDelete();
            }
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->index('user_id', 'certificates_user_id_index');
            $table->dropUnique('certificates_unique_scope');
            $table->unique(
                ['user_id', 'participation_type_id', 'role_id', 'event_type', 'event_id'],
                'certificates_unique_scope',
            );
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique('certificates_unique_scope');
            $table->unique(['user_id', 'participation_type_id', 'event_type', 'event_id'], 'certificates_unique_scope');
            $table->dropIndex('certificates_user_id_index');

            if (Schema::hasColumn('certificates', 'role_id')) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            }
        });
    }
};
