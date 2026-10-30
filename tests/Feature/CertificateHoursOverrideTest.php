<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ParticipationType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use App\Services\CertificateRenderer;
use App\Support\WorkshopGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CertificateHoursOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function permissions(): void
    {
        foreach (['constancias.view', 'constancias.download'] as $key) {
            Permission::firstOrCreate(['key' => $key], ['module' => 'constancias', 'label' => $key]);
        }
    }

    private function admin(): User
    {
        $this->permissions();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function assistant(): User
    {
        $this->permissions();
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $role->permissions()->sync(Permission::whereIn('key', ['constancias.view', 'constancias.download'])->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function tallerType(): ParticipationType
    {
        return ParticipationType::create([
            'key' => 'taller',
            'label' => 'Asistente a taller',
            'event_kind' => 'workshop',
            'role' => 'enrolled_attendance',
            'is_active' => true,
        ]);
    }

    private function templateFor(ParticipationType $type): CertificateTemplate
    {
        $template = CertificateTemplate::create([
            'name' => 'Plantilla taller',
            'kind' => 'certificate',
            'participation_type_id' => $type->id,
            'is_default' => true,
            'width' => 1800,
            'height' => 1200,
        ]);

        $template->elements()->create([
            'type' => 'text',
            'content' => '{nombre} · {evento} · {horas_totales} h',
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

    private function workshop(string $name, string $day, string $start, string $end, ?int $parent = null): Workshop
    {
        return Workshop::create([
            'name' => $name,
            'description' => 'test',
            'capacity' => 20,
            'location' => 'Aula 1',
            'day' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'created_by' => User::factory()->create()->id,
            'parent_workshop_id' => $parent,
        ]);
    }

    private function enrollAndAttend(Workshop $workshop, User $user): void
    {
        WorkshopEnrollment::create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);

        Attendance::create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'event_day' => $workshop->day,
            'registered_by' => $user->id,
        ]);
    }

    public function test_admin_sets_hours_override_and_metadata_reflects_it(): void
    {
        $admin = $this->admin();
        $type = $this->tallerType();
        $this->templateFor($type);
        $user = $this->assistant();

        $workshop = $this->workshop('Taller de prueba', '2026-10-05', '09:00', '13:00');
        $this->enrollAndAttend($workshop, $user);

        $this->actingAs($admin)
            ->patch("/admin/constancias/{$workshop->id}/{$user->id}/horas", ['hours' => 8])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $certificate = Certificate::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('workshop', $certificate->event_type);
        $this->assertEquals(8, $certificate->hours_override);
        $this->assertSame('8', $certificate->metadata['horas_totales']);
    }

    public function test_override_survives_reissue_download_and_metadata_refresh(): void
    {
        $admin = $this->admin();
        $type = $this->tallerType();
        $this->templateFor($type);
        $user = $this->assistant();

        $workshop = $this->workshop('Taller de prueba', '2026-10-05', '09:00', '13:00');
        $this->enrollAndAttend($workshop, $user);

        $this->actingAs($admin)
            ->patch("/admin/constancias/{$workshop->id}/{$user->id}/horas", ['hours' => 8])
            ->assertRedirect();

        app(CertificateRenderer::class)->issue($user, 'workshop', $workshop);
        app(CertificateRenderer::class)->issue($user, 'workshop', $workshop);

        $certificate = Certificate::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('8', $certificate->fresh()->metadata['horas_totales']);

        $this->actingAs($admin)
            ->get("/admin/constancias/{$workshop->id}/{$user->id}/download")
            ->assertOk();

        $this->assertSame('8', $certificate->fresh()->metadata['horas_totales']);

        $this->artisan('constancias:refrescar-metadata')->assertSuccessful();

        $certificate = $certificate->fresh();
        $this->assertEquals(8, $certificate->hours_override);
        $this->assertSame('8', $certificate->metadata['horas_totales']);
    }

    public function test_clearing_override_restores_real_hours(): void
    {
        $admin = $this->admin();
        $type = $this->tallerType();
        $this->templateFor($type);
        $user = $this->assistant();

        $workshop = $this->workshop('Taller de prueba', '2026-10-05', '09:00', '13:00');
        $this->enrollAndAttend($workshop, $user);

        $this->actingAs($admin)
            ->patch("/admin/constancias/{$workshop->id}/{$user->id}/horas", ['hours' => 8])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch("/admin/constancias/{$workshop->id}/{$user->id}/horas", ['hours' => null])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $certificate = Certificate::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertNull($certificate->hours_override);
        $this->assertSame('4', $certificate->metadata['horas_totales']);
    }

    public function test_grouped_course_override_applies_to_consolidated_certificate(): void
    {
        $admin = $this->admin();
        $type = $this->tallerType();
        $this->templateFor($type);
        $user = $this->assistant();

        $session1 = $this->workshop('Sesión 1: Taller de IA', '2026-09-23', '12:00', '14:00');
        $session2 = $this->workshop('Sesión 2: Taller de IA', '2026-09-24', '11:00', '13:00', $session1->id);

        $this->enrollAndAttend($session1, $user);
        $this->enrollAndAttend($session2, $user);

        $this->actingAs($admin)
            ->patch("/admin/constancias/{$session1->id}/{$user->id}/horas", ['hours' => 6.5])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $certificate = Certificate::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(WorkshopGroups::GROUPED_EVENT_TYPE, $certificate->event_type);
        $this->assertSame($session1->id, $certificate->event_id);
        $this->assertEquals(6.5, $certificate->hours_override);
        $this->assertSame('6,5', $certificate->metadata['horas_totales']);

        $this->actingAs($user)
            ->get('/constancias')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Constancias/Index')
                ->where('completedWorkshops.0.horas_totales', '6,5'));
    }

    public function test_workshop_payload_exposes_effective_and_real_hours(): void
    {
        $admin = $this->admin();
        $type = $this->tallerType();
        $this->templateFor($type);
        $withOverride = $this->assistant();
        $withoutOverride = $this->assistant();

        $workshop = $this->workshop('Taller de prueba', '2026-10-05', '09:00', '13:00');
        $this->enrollAndAttend($workshop, $withOverride);
        $this->enrollAndAttend($workshop, $withoutOverride);

        $this->actingAs($admin)
            ->patch("/admin/constancias/{$workshop->id}/{$withOverride->id}/horas", ['hours' => 8])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get("/workshops/{$workshop->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workshops/Show')
                ->where('workshop.enrollments', function ($enrollments) use ($withOverride, $withoutOverride) {
                    $byUser = $enrollments->keyBy('user_id');

                    $adjusted = $byUser[$withOverride->id];
                    $this->assertSame('8', $adjusted['hours']);
                    $this->assertSame('4', $adjusted['real_hours']);
                    $this->assertEquals(8, $adjusted['hours_override']);

                    $regular = $byUser[$withoutOverride->id];
                    $this->assertSame('4', $regular['hours']);
                    $this->assertSame('4', $regular['real_hours']);
                    $this->assertNull($regular['hours_override']);

                    return true;
                }));
    }

    public function test_requires_verified_attendance(): void
    {
        $admin = $this->admin();
        $this->tallerType();
        $user = $this->assistant();

        $workshop = $this->workshop('Taller de prueba', '2026-10-05', '09:00', '13:00');
        WorkshopEnrollment::create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);

        $this->actingAs($admin)
            ->patch("/admin/constancias/{$workshop->id}/{$user->id}/horas", ['hours' => 8])
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_non_admin_cannot_change_hours(): void
    {
        $this->tallerType();
        $this->admin();
        $user = $this->assistant();
        $other = User::factory()->create();
        $other->roles()->sync([Role::firstOrCreate(['name' => 'Sin permisos'])->id]);

        $workshop = $this->workshop('Taller de prueba', '2026-10-05', '09:00', '13:00');
        $this->enrollAndAttend($workshop, $user);

        $this->actingAs($other)
            ->patch("/admin/constancias/{$workshop->id}/{$user->id}/horas", ['hours' => 8])
            ->assertForbidden();

        $this->assertDatabaseCount('certificates', 0);
    }
}
