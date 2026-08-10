<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\CertificateTemplateElement;
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
            ['key' => 'carta_invitacion', 'label' => 'Carta de Invitación', 'event_kind' => 'event', 'kind' => 'carta', 'role' => null, 'manual_generable' => false],
        ];

        foreach ($types as $type) {
            ParticipationType::updateOrCreate(['key' => $type['key']], $type);
        }

        // Carta de Invitación - plantilla default portrait (tamaño carta 8.5x11")
        $cartaType = ParticipationType::where('key', 'carta_invitacion')->first();
        if ($cartaType && ! CertificateTemplate::where('participation_type_id', $cartaType->id)->where('kind', 'invitation')->exists()) {
            $template = CertificateTemplate::create([
                'name' => 'Carta de Invitación EICAL',
                'description' => 'Plantilla por defecto para cartas de invitación (tamaño carta 816×1056)',
                'kind' => 'invitation',
                'participation_type_id' => $cartaType->id,
                'is_default' => true,
                'width' => 816,
                'height' => 1056,
            ]);

            $elements = [
                ['type' => 'text', 'content' => 'CARTA DE INVITACIÓN', 'variable' => null, 'x' => 100, 'y' => 60, 'width' => 616, 'height' => 50, 'font_size' => 30, 'font_weight' => 'bold', 'font_family' => 'Georgia, serif', 'color' => '#0f3460', 'text_align' => 'center', 'z_index' => 1],
                ['type' => 'text', 'content' => '{nombre_evento}', 'variable' => 'nombre_evento', 'x' => 100, 'y' => 130, 'width' => 616, 'height' => 40, 'font_size' => 22, 'font_weight' => 'bold', 'font_family' => 'Arial', 'color' => '#1a1a2e', 'text_align' => 'center', 'z_index' => 2],
                ['type' => 'text', 'content' => '{nombre_completo}', 'variable' => 'nombre_completo', 'x' => 100, 'y' => 400, 'width' => 616, 'height' => 50, 'font_size' => 28, 'font_weight' => 'bold', 'font_family' => 'Georgia, serif', 'color' => '#0f3460', 'text_align' => 'center', 'z_index' => 3],
                ['type' => 'text', 'content' => '{institucion}', 'variable' => 'institucion', 'x' => 100, 'y' => 470, 'width' => 616, 'height' => 35, 'font_size' => 18, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#333333', 'text_align' => 'center', 'z_index' => 4],
                ['type' => 'text', 'content' => '{pais}', 'variable' => 'pais', 'x' => 100, 'y' => 515, 'width' => 616, 'height' => 30, 'font_size' => 16, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#555555', 'text_align' => 'center', 'z_index' => 5],
                ['type' => 'text', 'content' => 'Tiene el honor de invitar a participar como', 'variable' => null, 'x' => 100, 'y' => 585, 'width' => 616, 'height' => 35, 'font_size' => 17, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#333333', 'text_align' => 'center', 'z_index' => 6],
                ['type' => 'text', 'content' => '{rol}', 'variable' => 'rol', 'x' => 100, 'y' => 635, 'width' => 616, 'height' => 40, 'font_size' => 24, 'font_weight' => 'bold', 'font_family' => 'Arial', 'color' => '#16213e', 'text_align' => 'center', 'z_index' => 7],
                ['type' => 'text', 'content' => '{fecha_evento}', 'variable' => 'fecha_evento', 'x' => 100, 'y' => 695, 'width' => 616, 'height' => 30, 'font_size' => 16, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#555555', 'text_align' => 'center', 'z_index' => 8],
                ['type' => 'qr', 'content' => null, 'variable' => 'qr', 'x' => 283, 'y' => 760, 'width' => 250, 'height' => 250, 'font_size' => null, 'font_weight' => null, 'font_family' => null, 'color' => null, 'text_align' => 'center', 'z_index' => 9],
                ['type' => 'text', 'content' => 'Escanea para verificar tu carta', 'variable' => null, 'x' => 100, 'y' => 1020, 'width' => 616, 'height' => 25, 'font_size' => 12, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#888888', 'text_align' => 'center', 'z_index' => 10],
            ];

            foreach ($elements as $el) {
                CertificateTemplateElement::create(array_merge(['template_id' => $template->id], $el));
            }
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

        $this->call(EmailSeeder::class);

        foreach (PermissionSync::rolePermissionMap() as $roleId => $keys) {
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

        $adminRole = Role::where('name', config('roles.super_admin'))->first();

        if ($adminRole !== null) {
            $admin->roles()->sync([$adminRole->id]);
            $adminRole->permissions()->sync(Permission::pluck('id'));
        }

        User::query()
            ->whereNull('checkin_token')
            ->each(function (User $user) {
                $user->update(['checkin_token' => 'GFT-'.strtoupper(Str::random(16))]);
            });
    }
}
