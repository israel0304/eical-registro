<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['orcid', 'bio', 'personal_url', 'phone', 'extension'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('orcid')->nullable()->after('country');
            $table->text('bio')->nullable()->after('orcid');
            $table->string('personal_url')->nullable()->after('bio');
            $table->string('phone')->nullable()->after('remember_token');
            $table->string('extension')->nullable()->after('phone');
        });
    }
};
