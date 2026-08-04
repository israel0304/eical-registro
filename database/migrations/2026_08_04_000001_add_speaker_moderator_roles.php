<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Role::updateOrCreate(['id' => 5], ['name' => 'Speaker']);
        Role::updateOrCreate(['id' => 6], ['name' => 'Moderator']);
    }

    public function down(): void
    {
        Role::whereIn('id', [5, 6])->delete();
    }
};
