<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure Instructor role exists
        Role::updateOrCreate(['id' => 4], ['name' => 'Instructor']);

        // 2. Populate role_user from existing users.role_id
        DB::table('users')->whereNotNull('role_id')->orderBy('id')->each(function ($user) {
            DB::table('role_user')->insert([
                'user_id' => $user->id,
                'role_id' => $user->role_id,
            ]);
        });

        // 3. Create workshop_instructor_user table
        Schema::create('workshop_instructor_user', function (Blueprint $table) {
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('institution')->nullable();
            $table->timestamps();
            $table->primary(['workshop_id', 'user_id']);
        });

        // 4. Migrate instructors table to users
        $instructorRoleId = 4;

        DB::table('instructors')->orderBy('id')->each(function ($instructor) use ($instructorRoleId) {
            $user = User::where('email', $instructor->email)->first();

            if (! $user) {
                $nameParts = explode(' ', $instructor->name, 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';

                $userId = DB::table('users')->insertGetId([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $instructor->email,
                    'password' => bcrypt(Str::random(32)),
                    'affiliation' => $instructor->institution ?? '',
                    'dni' => 'CNV-'.Str::random(7),
                    'country' => '',
                    'state' => '',
                    'role_id' => 4,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $user = User::find($userId);
            }

            // Ensure instructor role is assigned
            DB::table('role_user')->updateOrInsert(
                ['user_id' => $user->id, 'role_id' => $instructorRoleId],
                ['user_id' => $user->id, 'role_id' => $instructorRoleId],
            );

            // Link to workshop
            DB::table('workshop_instructor_user')->updateOrInsert(
                ['workshop_id' => $instructor->workshop_id, 'user_id' => $user->id],
                ['workshop_id' => $instructor->workshop_id, 'user_id' => $user->id, 'institution' => $instructor->institution ?? null],
            );
        });

        // 5. Drop old columns and tables
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });

        Schema::dropIfExists('instructors');
    }

    public function down(): void
    {
        // Restore users.role_id (first role only)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('roles')->after('id');
        });

        DB::table('role_user')->orderBy('user_id')->each(function ($pivot) {
            DB::table('users')->where('id', $pivot->user_id)->update(['role_id' => $pivot->role_id]);
        });

        // Recreate instructors table
        if (! Schema::hasTable('instructors')) {
            Schema::create('instructors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('institution')->nullable();
                $table->string('email');
                $table->timestamps();
            });
        }

        // Restore instructor data from workshop_instructor_user
        DB::table('workshop_instructor_user')->orderBy('workshop_id')->each(function ($pivot) {
            $user = DB::table('users')->where('id', $pivot->user_id)->first();
            if ($user) {
                $name = trim($user->first_name.' '.$user->last_name);
                DB::table('instructors')->updateOrInsert(
                    ['workshop_id' => $pivot->workshop_id, 'email' => $user->email],
                    [
                        'workshop_id' => $pivot->workshop_id,
                        'name' => $name ?: $user->email,
                        'email' => $user->email,
                        'institution' => $pivot->institution,
                    ],
                );
            }
        });

        Schema::dropIfExists('workshop_instructor_user');
        Schema::dropIfExists('role_user');
    }
};
