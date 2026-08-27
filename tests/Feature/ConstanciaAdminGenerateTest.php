<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ParticipationType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConstanciaAdminGenerateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function participant(): User
    {
        return User::factory()->create();
    }

    private function manualType(): ParticipationType
    {
        return ParticipationType::create([
            'key' => 'staff',
            'label' => 'Personal de apoyo',
            'event_kind' => 'staff',
            'kind' => null,
            'role' => null,
            'is_active' => true,
            'manual_generable' => true,
        ]);
    }

    private function instructorType(bool $active = true): ParticipationType
    {
        return ParticipationType::create([
            'key' => 'taller_instructor',
            'label' => 'Instructor de taller',
            'event_kind' => 'workshop',
            'kind' => null,
            'role' => 'instructor',
            'is_active' => $active,
            'manual_generable' => false,
        ]);
    }

    private function certificateTemplate(ParticipationType $type): CertificateTemplate
    {
        $template = CertificateTemplate::create([
            'name' => 'Constancia instructor',
            'description' => null,
            'kind' => 'certificate',
            'participation_type_id' => $type->id,
            'is_default' => true,
            'is_active' => true,
            'width' => 1800,
            'height' => 1200,
        ]);

        $template->elements()->create([
            'type' => 'text',
            'content' => '{nombre} · {evento}',
            'x' => 100,
            'y' => 100,
            'width' => 800,
            'height' => 60,
            'font_size' => 30,
            'font_weight' => 'bold',
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        return $template;
    }

    private function instructor(): User
    {
        $role = Role::firstOrCreate(['name' => 'Instructor']);
        $role->permissions()->syncWithoutDetaching(
            Permission::firstOrCreate(['key' => 'constancias.download'], [
                'module' => 'constancias',
                'label' => 'Descargar constancias',
            ]),
            Permission::firstOrCreate(['key' => 'constancias.view'], [
                'module' => 'constancias',
                'label' => 'Ver constancias',
            ]),
        );

        $user = User::factory()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Méndez',
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function workshop(): Workshop
    {
        return Workshop::create([
            'name' => 'Taller de instructor',
            'description' => 'test',
            'capacity' => 10,
            'location' => 'Aula 1',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'created_by' => $this->admin()->id,
        ]);
    }

    public function test_instructor_can_download_own_workshop_certificate(): void
    {
        $instructor = $this->instructor();
        $type = $this->instructorType();
        $this->certificateTemplate($type);
        $workshop = $this->workshop();
        $workshop->instructors()->attach($instructor->id);

        $this->actingAs($instructor)
            ->get('/constancias/'.$workshop->id.'/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('Carlos', false);

        $this->assertDatabaseHas('certificates', [
            'user_id' => $instructor->id,
            'participation_type_id' => $type->id,
            'event_type' => 'workshop',
            'event_id' => $workshop->id,
        ]);
    }

    public function test_instructor_download_blocked_when_type_is_inactive(): void
    {
        $instructor = $this->instructor();
        $type = $this->instructorType(false);
        $this->certificateTemplate($type);
        $workshop = $this->workshop();
        $workshop->instructors()->attach($instructor->id);

        $this->actingAs($instructor)
            ->get('/constancias/'.$workshop->id.'/download')
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_non_instructor_cannot_download_workshop_certificate_without_enrollment(): void
    {
        $type = $this->instructorType();
        $this->certificateTemplate($type);
        $workshop = $this->workshop();

        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $role->permissions()->syncWithoutDetaching(
            Permission::firstOrCreate(['key' => 'constancias.download'], [
                'module' => 'constancias',
                'label' => 'Descargar constancias',
            ]),
        );
        $user = $this->participant();
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get('/constancias/'.$workshop->id.'/download')
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_admin_can_generate_manual_certificate(): void
    {
        $admin = $this->admin();
        $user = $this->participant();
        $type = $this->manualType();

        $this->actingAs($admin)
            ->get("/admin/constancias/tipos/{$type->id}/usuario/{$user->id}/generar")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('Personal de apoyo', false);

        $this->assertDatabaseHas('certificates', [
            'user_id' => $user->id,
            'participation_type_id' => $type->id,
            'event_type' => 'staff',
            'event_id' => 0,
        ]);
    }

    public function test_generating_twice_returns_same_folio(): void
    {
        $admin = $this->admin();
        $user = $this->participant();
        $type = $this->manualType();

        $this->actingAs($admin)->get("/admin/constancias/tipos/{$type->id}/usuario/{$user->id}/generar")->assertOk();
        $first = Certificate::query()->where('user_id', $user->id)->first();

        $this->actingAs($admin)->get("/admin/constancias/tipos/{$type->id}/usuario/{$user->id}/generar")->assertOk();

        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame($first->folio, Certificate::query()->where('user_id', $user->id)->first()->folio);
    }

    public function test_cannot_generate_for_non_manual_type(): void
    {
        $admin = $this->admin();
        $user = $this->participant();
        $type = ParticipationType::create([
            'key' => 'taller',
            'label' => 'Asistente a taller',
            'event_kind' => 'workshop',
            'kind' => null,
            'role' => 'enrolled_attendance',
            'is_active' => true,
            'manual_generable' => false,
        ]);

        $this->actingAs($admin)
            ->get("/admin/constancias/tipos/{$type->id}/usuario/{$user->id}/generar")
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = $this->participant();
        $type = $this->manualType();
        $user->roles()->sync([Role::firstOrCreate(['name' => 'Asistente'])->id]);

        $this->actingAs($user)
            ->get("/admin/constancias/tipos/{$type->id}/usuario/{$user->id}/generar")
            ->assertForbidden();

        $this->assertDatabaseCount('certificates', 0);
    }
}
