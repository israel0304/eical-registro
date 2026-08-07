<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\ParticipationType;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use App\Services\CertificateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
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

    private function makeTemplate(ParticipationType $type): CertificateTemplate
    {
        $template = CertificateTemplate::create([
            'name' => 'Plantilla taller',
            'description' => 'Prueba',
            'participation_type_id' => $type->id,
            'is_default' => true,
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

        $template->elements()->create([
            'type' => 'qr',
            'content' => null,
            'x' => 1400,
            'y' => 900,
            'width' => 200,
            'height' => 200,
            'text_align' => 'center',
            'z_index' => 2,
        ]);

        return $template;
    }

    private function attendedWorkshop(User $user): Workshop
    {
        $workshop = Workshop::create([
            'name' => 'Taller de prueba',
            'description' => 'test',
            'capacity' => 10,
            'location' => 'Cinvestav',
            'day' => '2026-08-03',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'created_by' => $user->id,
        ]);

        WorkshopEnrollment::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);

        $workshop->attendances()->create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'event_day' => '2026-08-03',
            'registered_by' => $user->id,
        ]);

        return $workshop;
    }

    public function test_admin_can_access_template_index()
    {
        $this->actingAs($this->admin());

        $this->get('/admin/constancias/plantillas')->assertOk();
    }

    public function test_admin_can_access_unified_plantillas_page()
    {
        $this->actingAs($this->admin());

        $this->get('/admin/plantillas')->assertOk();
    }

    public function test_non_admin_is_forbidden_from_unified_plantillas_page()
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::firstOrCreate(['name' => 'Asistente'])->id]);
        $this->actingAs($user);

        $this->get('/admin/plantillas')->assertForbidden();
    }

    public function test_non_admin_is_forbidden_from_template_index()
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::firstOrCreate(['name' => 'Asistente'])->id]);
        $this->actingAs($user);

        $this->get('/admin/constancias/plantillas')->assertForbidden();
    }

    public function test_admin_can_create_template()
    {
        $this->actingAs($this->admin());
        $type = $this->tallerType();

        $response = $this->post('/admin/constancias/plantillas', [
            'name' => 'Nueva plantilla',
            'description' => 'Descripción',
            'participation_type_id' => $type->id,
            'is_default' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('certificate_templates', [
            'name' => 'Nueva plantilla',
            'is_default' => true,
        ]);
    }

    public function test_issue_creates_certificate_with_folio_and_renders_html()
    {
        $user = $this->admin();
        $type = $this->tallerType();
        $template = $this->makeTemplate($type);
        $workshop = $this->attendedWorkshop($user);

        $renderer = app(CertificateRenderer::class);
        $certificate = $renderer->issue($user, 'workshop', $workshop);

        $this->assertNotNull($certificate);
        $this->assertNotNull($certificate->folio);
        $this->assertStringStartsWith('EICAL-', $certificate->folio);

        $html = $renderer->render($certificate);

        $this->assertStringContainsString(htmlspecialchars($user->name), $html);
        $this->assertStringContainsString('data:image/svg+xml;base64', $html);
        $this->assertStringContainsString($certificate->folio, $html);

        $this->assertDatabaseHas('certificates', [
            'folio' => $certificate->folio,
            'user_id' => $user->id,
            'event_type' => 'workshop',
            'event_id' => $workshop->id,
        ]);
    }

    public function test_issue_returns_null_for_user_without_attendance()
    {
        $user = $this->admin();
        $this->tallerType();
        $this->makeTemplate(ParticipationType::where('key', 'taller')->first());
        $workshop = Workshop::create([
            'name' => 'Taller sin asistencia',
            'description' => 'test',
            'capacity' => 10,
            'location' => 'Cinvestav',
            'day' => '2026-08-03',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'created_by' => $user->id,
        ]);

        $certificate = app(CertificateRenderer::class)->issue($user, 'workshop', $workshop);

        $this->assertNull($certificate);
    }

    public function test_public_verification_page_renders_for_valid_folio()
    {
        $user = $this->admin();
        $type = $this->tallerType();
        $this->makeTemplate($type);
        $workshop = $this->attendedWorkshop($user);
        $certificate = app(CertificateRenderer::class)->issue($user, 'workshop', $workshop);

        $this->get('/constancias/verificar/'.$certificate->folio)
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($certificate->folio);
    }

    public function test_public_verification_page_returns_404_for_unknown_folio()
    {
        $this->get('/constancias/verificar/NO-EXISTE')->assertNotFound();
    }

    public function test_owner_can_download_certificate_pdf()
    {
        $user = $this->admin();
        $type = $this->tallerType();
        $this->makeTemplate($type);
        $workshop = $this->attendedWorkshop($user);
        $certificate = app(CertificateRenderer::class)->issue($user, 'workshop', $workshop);

        $this->actingAs($user);

        $response = $this->get('/constancias/'.$certificate->id.'/pdf');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename=constancia_'.$certificate->folio.'.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_non_owner_cannot_download_certificate_pdf()
    {
        $owner = $this->admin();
        $type = $this->tallerType();
        $this->makeTemplate($type);
        $workshop = $this->attendedWorkshop($owner);
        $certificate = app(CertificateRenderer::class)->issue($owner, 'workshop', $workshop);

        $other = User::factory()->create();
        $other->roles()->sync([Role::firstOrCreate(['name' => 'Asistente'])->id]);
        $this->actingAs($other);

        $this->get('/constancias/'.$certificate->id.'/pdf')->assertForbidden();
    }
}
