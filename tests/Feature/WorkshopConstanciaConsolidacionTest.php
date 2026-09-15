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
use App\Support\WorkshopGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopConstanciaConsolidacionTest extends TestCase
{
    use RefreshDatabase;

    private function permissions(): void
    {
        foreach (['constancias.view', 'constancias.download'] as $key) {
            Permission::firstOrCreate(['key' => $key], ['module' => 'constancias', 'label' => $key]);
        }
    }

    private function participant(): User
    {
        $this->permissions();
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $role->permissions()->sync(Permission::whereIn('key', ['constancias.view', 'constancias.download'])->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function admin(): User
    {
        $this->permissions();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
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

    private function instructorType(): ParticipationType
    {
        return ParticipationType::create([
            'key' => 'taller_instructor',
            'label' => 'Instructor de taller',
            'event_kind' => 'workshop',
            'role' => 'instructor',
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

    private function enrollAndAttend(Workshop $workshop, User $user, string $day): void
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
            'event_day' => $day,
            'registered_by' => $user->id,
        ]);
    }

    private function dividedWorkshop(): array
    {
        $session1 = $this->workshop('Sesión 1: Taller de IA', '2026-09-23', '12:00', '14:00');
        $session2 = $this->workshop('Sesión 2: Taller de IA', '2026-09-24', '11:00', '13:00', $session1->id);

        return [$session1, $session2];
    }

    public function test_full_attendance_downloads_single_consolidated_certificate(): void
    {
        $this->templateFor($this->tallerType());
        $user = $this->participant();

        [$session1, $session2] = $this->dividedWorkshop();
        $this->enrollAndAttend($session1, $user, '2026-09-23');
        $this->enrollAndAttend($session2, $user, '2026-09-24');

        $this->actingAs($user)
            ->get('/constancias/'.$session1->id.'/download')
            ->assertOk();

        $this->assertDatabaseCount('certificates', 1);

        $certificate = Certificate::where('user_id', $user->id)->first();

        $this->assertSame(WorkshopGroups::GROUPED_EVENT_TYPE, $certificate->event_type);
        $this->assertSame($session1->id, $certificate->event_id);
        $this->assertSame('Taller de IA', $certificate->metadata['evento']);
        $this->assertSame('4', $certificate->metadata['horas_totales']);
        $this->assertSame('23 y 24 de septiembre de 2026', $certificate->metadata['fecha_evento']);

        $folio = $certificate->folio;

        $this->actingAs($user)
            ->get('/constancias/'.$session2->id.'/download')
            ->assertOk();

        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame($folio, Certificate::where('user_id', $user->id)->first()->folio);
    }

    public function test_partial_attendance_keeps_per_session_certificate(): void
    {
        $this->templateFor($this->tallerType());
        $user = $this->participant();

        [$session1] = $this->dividedWorkshop();
        $this->enrollAndAttend($session1, $user, '2026-09-23');

        $this->actingAs($user)
            ->get('/constancias/'.$session1->id.'/download')
            ->assertOk();

        $this->assertDatabaseCount('certificates', 1);

        $certificate = Certificate::where('user_id', $user->id)->first();

        $this->assertSame($session1->id, $certificate->event_id);
        $this->assertSame('Sesión 1: Taller de IA', $certificate->metadata['evento']);
        $this->assertSame('2', $certificate->metadata['horas_totales']);
    }

    public function test_instructor_fully_activated_issues_consolidated_certificate(): void
    {
        $type = $this->instructorType();
        $this->templateFor($type);
        $user = $this->participant();

        [$session1, $session2] = $this->dividedWorkshop();

        foreach ([$session1, $session2] as $session) {
            $session->instructors()->attach($user->id, ['activated' => true, 'activated_at' => now()]);
        }

        $this->actingAs($user)
            ->get('/constancias/'.$session2->id.'/download')
            ->assertOk();

        $this->assertDatabaseCount('certificates', 1);

        $certificate = Certificate::where('user_id', $user->id)->first();

        $this->assertSame(WorkshopGroups::GROUPED_EVENT_TYPE, $certificate->event_type);
        $this->assertSame($session1->id, $certificate->event_id);
        $this->assertSame($type->id, $certificate->participation_type_id);
        $this->assertSame('Taller de IA', $certificate->metadata['evento']);
        $this->assertSame('4', $certificate->metadata['horas_totales']);
    }

    public function test_instructor_partially_activated_stays_per_session(): void
    {
        $this->templateFor($this->instructorType());
        $user = $this->participant();

        [$session1, $session2] = $this->dividedWorkshop();

        $session1->instructors()->attach($user->id, ['activated' => true, 'activated_at' => now()]);
        $session2->instructors()->attach($user->id);

        $this->actingAs($user)
            ->get('/constancias/'.$session1->id.'/download')
            ->assertOk();

        $this->assertDatabaseCount('certificates', 1);

        $certificate = Certificate::where('user_id', $user->id)->first();

        $this->assertSame($session1->id, $certificate->event_id);
        $this->assertSame('Sesión 1: Taller de IA', $certificate->metadata['evento']);
    }

    public function test_admin_download_consolidates_when_full_attendance(): void
    {
        $this->templateFor($this->tallerType());
        $admin = $this->admin();
        $user = $this->participant();

        [$session1, $session2] = $this->dividedWorkshop();
        $this->enrollAndAttend($session1, $user, '2026-09-23');
        $this->enrollAndAttend($session2, $user, '2026-09-24');

        $this->actingAs($admin)
            ->get('/admin/constancias/'.$session1->id.'/'.$user->id.'/download')
            ->assertOk();

        $this->assertDatabaseCount('certificates', 1);

        $certificate = Certificate::where('user_id', $user->id)->first();

        $this->assertSame(WorkshopGroups::GROUPED_EVENT_TYPE, $certificate->event_type);
        $this->assertSame($session1->id, $certificate->event_id);
        $this->assertSame('Taller de IA', $certificate->metadata['evento']);
        $this->assertSame('4', $certificate->metadata['horas_totales']);
    }

    public function test_parent_session_download_never_reuses_consolidated_certificate(): void
    {
        $this->templateFor($this->tallerType());
        $user = $this->participant();

        [$session1, $session2] = $this->dividedWorkshop();
        $this->enrollAndAttend($session1, $user, '2026-09-23');
        $this->enrollAndAttend($session2, $user, '2026-09-24');

        $this->actingAs($user)
            ->get('/constancias/'.$session2->id.'/download')
            ->assertOk();

        $consolidated = Certificate::where('user_id', $user->id)->first();

        $this->assertSame(WorkshopGroups::GROUPED_EVENT_TYPE, $consolidated->event_type);
        $this->assertSame($session1->id, $consolidated->event_id);
        $this->assertSame('4', $consolidated->metadata['horas_totales']);

        WorkshopEnrollment::where('workshop_id', $session2->id)->delete();
        Attendance::where('workshop_id', $session2->id)->delete();

        $this->actingAs($user)
            ->get('/constancias/'.$session1->id.'/download')
            ->assertOk();

        $this->assertDatabaseCount('certificates', 2);

        $perSession = Certificate::where('user_id', $user->id)
            ->where('event_type', 'workshop')
            ->where('event_id', $session1->id)
            ->first();

        $this->assertNotNull($perSession);
        $this->assertNotSame($consolidated, $perSession);
        $this->assertSame('Sesión 1: Taller de IA', $perSession->metadata['evento']);
        $this->assertSame('2', $perSession->metadata['horas_totales']);
    }

    public function test_my_certificates_groups_divided_workshop_into_one_entry(): void
    {
        $this->templateFor($this->tallerType());
        $user = $this->participant();

        [$session1, $session2] = $this->dividedWorkshop();
        $this->enrollAndAttend($session1, $user, '2026-09-23');
        $this->enrollAndAttend($session2, $user, '2026-09-24');

        $this->actingAs($user)
            ->get('/constancias')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->has('completedWorkshops', 1)
                ->where('completedWorkshops.0.id', $session1->id)
                ->where('completedWorkshops.0.name', 'Taller de IA')
                ->where('completedWorkshops.0.session_count', 2)
                ->where('completedWorkshops.0.horas_totales', '4'));
    }

    public function test_my_certificates_keeps_individual_sessions_when_partial(): void
    {
        $this->templateFor($this->tallerType());
        $user = $this->participant();

        [$session1] = $this->dividedWorkshop();
        $this->enrollAndAttend($session1, $user, '2026-09-23');

        $this->actingAs($user)
            ->get('/constancias')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->has('completedWorkshops', 1)
                ->where('completedWorkshops.0.id', $session1->id)
                ->where('completedWorkshops.0.name', 'Sesión 1: Taller de IA')
                ->missing('completedWorkshops.0.session_count'));
    }
}
