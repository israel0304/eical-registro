<?php

namespace Database\Seeders;

use App\Models\ParticipationType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\PermissionSync;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Role::updateOrCreate(['id' => 1], ['name' => 'Administrator']);
        Role::updateOrCreate(['id' => 2], ['name' => 'Ponente']);
        Role::updateOrCreate(['id' => 3], ['name' => 'Asistente']);
        Role::updateOrCreate(['id' => 4], ['name' => 'Instructor']);
        Role::updateOrCreate(['id' => 5], ['name' => 'Speaker']);
        Role::updateOrCreate(['id' => 6], ['name' => 'Moderator']);

        $types = [
            ['key' => 'taller', 'label' => 'Asistente a taller', 'event_kind' => 'workshop', 'kind' => null, 'role' => 'enrolled_attendance'],
            ['key' => 'taller_instructor', 'label' => 'Instructor de taller', 'event_kind' => 'workshop', 'kind' => null, 'role' => 'instructor'],
            ['key' => 'ponencia', 'label' => 'Ponente', 'event_kind' => 'presentation', 'kind' => null, 'role' => 'presented_author'],
            ['key' => 'conferencia_magistral', 'label' => 'Conferencista magistral', 'event_kind' => 'conference', 'kind' => 'magistral', 'role' => 'speaker'],
            ['key' => 'conferencia_especial', 'label' => 'Conferencista especial', 'event_kind' => 'conference', 'kind' => 'especial', 'role' => 'speaker'],
            ['key' => 'simposiasta', 'label' => 'Simposiasta', 'event_kind' => 'conference', 'kind' => 'simposio', 'role' => 'speaker'],
            ['key' => 'moderador_mesa', 'label' => 'Moderador de mesa', 'event_kind' => 'conference', 'kind' => 'mesa_dialogo', 'role' => 'moderator'],
            ['key' => 'moderador_simposio', 'label' => 'Moderador de simposio', 'event_kind' => 'conference', 'kind' => 'simposio', 'role' => 'moderator'],
            ['key' => 'evento_asistencia', 'label' => 'Asistente al evento', 'event_kind' => 'event', 'kind' => null, 'role' => null, 'manual_generable' => false],
            ['key' => 'staff', 'label' => 'Personal de apoyo', 'event_kind' => 'staff', 'kind' => null, 'role' => null, 'manual_generable' => true],
        ];

        foreach ($types as $type) {
            ParticipationType::updateOrCreate(['key' => $type['key']], $type);
        }

        $settings = [
            'evento_nombre' => 'EICAL 2026',
            'evento_checkin_enabled' => '1',
            'evento_checkin_time_restricted' => '1',
            'evento_min_dias' => '2',
            'evento_fecha_inicio' => '',
            'evento_fecha_fin' => '',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        app(PermissionSync::class)->sync();

        $rolePermissions = [
            2 => ['dashboard.view', 'workshops.view', 'workshops.my', 'presentations.view', 'presentations.my', 'constancias.view', 'constancias.download', 'gafete.view'],
            3 => ['dashboard.view', 'workshops.view', 'workshops.my', 'constancias.view', 'constancias.download', 'gafete.view'],
            4 => ['dashboard.view', 'workshops.view', 'workshops.my', 'constancias.view', 'constancias.download', 'gafete.view'],
            5 => ['dashboard.view', 'constancias.view', 'constancias.download', 'gafete.view'],
            6 => ['dashboard.view', 'constancias.view', 'constancias.download', 'gafete.view'],
        ];

        foreach ($rolePermissions as $roleId => $keys) {
            Role::find($roleId)?->permissions()->sync(
                Permission::whereIn('key', $keys)->pluck('id')
            );
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@cinvestav.edu.mx'],
            [
                'first_name' => 'Admin',
                'last_name' => 'Sistema',
                'dni' => 'ADMIN001',
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        $admin->roles()->sync([1]);
        Role::where('id', 1)->first()?->permissions()->sync(Permission::pluck('id'));

        User::query()
            ->whereNull('checkin_token')
            ->each(function (User $user) {
                $user->update(['checkin_token' => 'GFT-'.strtoupper(Str::random(16))]);
            });
    }
}
