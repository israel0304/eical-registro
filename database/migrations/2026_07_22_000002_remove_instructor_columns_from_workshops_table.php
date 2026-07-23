<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing data to instructors table
        $workshops = DB::table('workshops')
            ->whereNotNull('instructor_name')
            ->get();

        foreach ($workshops as $workshop) {
            DB::table('instructors')->insert([
                'workshop_id' => $workshop->id,
                'name' => $workshop->instructor_name,
                'institution' => $workshop->instructor_institution,
                'email' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('workshops', function (Blueprint $table) {
            $table->dropColumn(['instructor_name', 'instructor_institution']);
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->string('instructor_name');
            $table->string('instructor_institution')->nullable();
        });

        // Migrate data back
        $instructors = DB::table('instructors')->get();
        foreach ($instructors as $instructor) {
            DB::table('workshops')
                ->where('id', $instructor->workshop_id)
                ->update([
                    'instructor_name' => $instructor->name,
                    'instructor_institution' => $instructor->institution,
                ]);
        }
    }
};
