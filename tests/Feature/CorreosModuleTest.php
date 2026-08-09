<?php

namespace Tests\Feature;

use App\Mail\EmailTemplateMailable;
use App\Models\EmailTemplate;
use App\Models\EmailTrigger;
use App\Models\EventLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CorreosModuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function userWith(string $permissionKey): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Role-'.$permissionKey]);
        $role->permissions()->sync(
            Permission::firstOrCreate(['key' => $permissionKey], [
                'module' => 'Correos',
                'label' => $permissionKey,
            ])
        );
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function workshop(): Workshop
    {
        return Workshop::create([
            'name' => 'Taller de prueba',
            'description' => 'Descripción',
            'capacity' => 10,
            'location' => 'Aula 1',
            'day' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'qr_time_restricted' => false,
            'created_by' => auth()->id(),
        ]);
    }

    private function templateFor(string $eventKey): EmailTemplate
    {
        return EmailTemplate::create([
            'event_key' => $eventKey,
            'name' => 'Plantilla de prueba',
            'subject' => 'Hola {{ nombre_completo }}',
            'body_html' => '<p>Hola {{ nombre_completo }}</p>',
        ]);
    }

    public function test_only_users_with_permission_can_manage_email_templates(): void
    {
        $this->actingAs($this->userWith('users.view'));

        $this->post(route('correos.templates.store'), [
            'event_key' => 'workshop.enrollment',
            'name' => 'X',
            'subject' => 'Asunto',
            'body_html' => '<p>Cuerpo</p>',
        ])->assertForbidden();

        $this->actingAs($this->userWith('correos.templates.manage'))
            ->post(route('correos.templates.store'), [
                'event_key' => 'workshop.enrollment',
                'name' => 'Confirmación',
                'subject' => 'Confirmado {{ taller }}',
                'body_html' => '<p>Confirmado {{ taller }}</p>',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('email_templates', ['event_key' => 'workshop.enrollment']);
    }

    public function test_plantillas_page_requires_template_permission(): void
    {
        $this->actingAs($this->userWith('users.view'))
            ->get(route('plantillas.index'))
            ->assertForbidden();

        $this->actingAs($this->userWith('correos.templates.manage'))
            ->get(route('plantillas.index'))
            ->assertOk();
    }

    public function test_cannot_create_template_for_audit_only_event(): void
    {
        $this->actingAs($this->userWith('correos.templates.manage'));

        $this->post(route('correos.templates.store'), [
            'event_key' => 'workshop.updated',
            'name' => 'X',
            'subject' => 'Asunto',
            'body_html' => '<p>Cuerpo</p>',
        ])->assertSessionHasErrors('event_key');

        $this->assertDatabaseMissing('email_templates', ['event_key' => 'workshop.updated']);
    }

    public function test_cannot_create_duplicate_template_for_same_event(): void
    {
        $this->templateFor('workshop.enrollment');
        $this->actingAs($this->userWith('correos.templates.manage'));

        $this->post(route('correos.templates.store'), [
            'event_key' => 'workshop.enrollment',
            'name' => 'Duplicado',
            'subject' => 'Asunto',
            'body_html' => '<p>Cuerpo</p>',
        ])->assertSessionHasErrors('event_key');
    }

    public function test_template_can_be_updated_and_deleted(): void
    {
        $template = $this->templateFor('workshop.enrollment');
        $this->actingAs($this->userWith('correos.templates.manage'));

        $this->get(route('correos.templates.edit', $template))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Correos/Edit')
                ->where('template.id', $template->id));

        $this->put(route('correos.templates.update', $template), [
            'name' => 'Nuevo nombre',
            'subject' => 'Nuevo asunto',
            'body_html' => '<p>Nuevo cuerpo</p>',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
            'name' => 'Nuevo nombre',
        ]);

        $this->delete(route('correos.templates.destroy', $template))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
    }

    public function test_trigger_can_be_created_updated_and_deleted(): void
    {
        $template = $this->templateFor('workshop.enrollment');
        $this->actingAs($this->userWith('correos.templates.manage'));

        $this->post(route('correos.triggers.store'), [
            'event_key' => 'workshop.enrollment',
            'email_template_id' => $template->id,
            'to' => 'destinatario',
            'is_active' => true,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('email_triggers', [
            'event_key' => 'workshop.enrollment',
            'email_template_id' => $template->id,
        ]);

        $trigger = EmailTrigger::where('event_key', 'workshop.enrollment')->first();

        $this->put(route('correos.triggers.update', $trigger), [
            'event_key' => 'workshop.enrollment',
            'email_template_id' => $template->id,
            'to' => 'destinatario',
            'is_active' => false,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('email_triggers', [
            'id' => $trigger->id,
            'is_active' => false,
        ]);

        $this->delete(route('correos.triggers.destroy', $trigger))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('email_triggers', ['id' => $trigger->id]);
    }

    public function test_active_trigger_sends_email_on_event(): void
    {
        Mail::fake();

        $this->templateFor('workshop.enrollment');
        EmailTrigger::create([
            'event_key' => 'workshop.enrollment',
            'email_template_id' => EmailTemplate::where('event_key', 'workshop.enrollment')->first()->id,
            'to' => 'destinatario',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin());

        $workshop = $this->workshop();
        $user = auth()->user();

        $this->post(route('workshops.enroll', $workshop), [])->assertSessionHas('success');

        Mail::assertSent(EmailTemplateMailable::class, function (EmailTemplateMailable $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->envelope()->subject === 'Hola '.$user->name;
        });

        $this->assertDatabaseHas('event_logs', [
            'event_key' => 'workshop.enrollment',
            'status' => 'sent',
        ]);
    }

    public function test_inactive_trigger_does_not_send_email_but_logs_event(): void
    {
        Mail::fake();

        $this->templateFor('workshop.enrollment');
        EmailTrigger::create([
            'event_key' => 'workshop.enrollment',
            'email_template_id' => EmailTemplate::where('event_key', 'workshop.enrollment')->first()->id,
            'to' => 'destinatario',
            'is_active' => false,
        ]);

        $this->actingAs($this->admin());

        $workshop = $this->workshop();

        $this->post(route('workshops.enroll', $workshop))->assertSessionHas('success');

        Mail::assertNothingSent();
        $this->assertDatabaseHas('event_logs', ['event_key' => 'workshop.enrollment']);
    }

    public function test_missing_trigger_does_not_send_email(): void
    {
        Mail::fake();

        $this->actingAs($this->admin());

        $workshop = $this->workshop();

        $this->post(route('workshops.enroll', $workshop))->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_preview_renders_variables_with_sample_data(): void
    {
        $this->actingAs($this->userWith('correos.templates.manage'));

        $response = $this->postJson(route('correos.templates.preview'), [
            'event_key' => 'workshop.enrollment',
            'subject' => 'Taller {{ taller }}',
            'body_html' => '<p>Hola {{ nombre_completo }}</p>',
        ])->assertOk();

        $response->assertJson([
            'subject' => 'Taller Taller de ejemplo EICAL',
        ]);

        $this->assertStringContainsString('Hola María González', $response->json('body_html'));
    }

    public function test_enrollment_event_audits_via_lifecycle_trait(): void
    {
        $this->actingAs($this->admin());

        $workshop = $this->workshop();
        $user = auth()->user();

        $this->post(route('workshops.enroll', $workshop))->assertSessionHas('success');

        $this->assertDatabaseHas('event_logs', [
            'event_key' => 'workshop_enrollment.created',
            'subject_type' => WorkshopEnrollment::class,
            'subject_id' => WorkshopEnrollment::where('user_id', $user->id)
                ->where('workshop_id', $workshop->id)
                ->first()
                ?->id,
        ]);
    }

    public function test_multiple_triggers_for_same_event_each_send_email(): void
    {
        Mail::fake();

        $this->templateFor('workshop.enrollment');
        $templateId = EmailTemplate::where('event_key', 'workshop.enrollment')->first()->id;

        EmailTrigger::create([
            'event_key' => 'workshop.enrollment',
            'email_template_id' => $templateId,
            'to' => 'destinatario',
            'is_active' => true,
        ]);
        EmailTrigger::create([
            'event_key' => 'workshop.enrollment',
            'email_template_id' => $templateId,
            'to' => 'destinatario',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin());

        $workshop = $this->workshop();
        $user = auth()->user();

        $this->post(route('workshops.enroll', $workshop))->assertSessionHas('success');

        Mail::assertSent(EmailTemplateMailable::class, 2);
        $this->assertSame(2, EventLog::where('event_key', 'workshop.enrollment')->where('status', 'sent')->count());
    }

    public function test_trigger_by_role_sends_to_all_users_with_that_role(): void
    {
        Mail::fake();

        $this->templateFor('workshop.created');

        $role = Role::firstOrCreate(['name' => 'Comité']);
        $recipientA = User::factory()->create();
        $recipientB = User::factory()->create();
        $recipientA->roles()->attach($role->id);
        $recipientB->roles()->attach($role->id);

        EmailTrigger::create([
            'event_key' => 'workshop.created',
            'email_template_id' => EmailTemplate::where('event_key', 'workshop.created')->first()->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin());

        $this->workshop();

        Mail::assertSent(EmailTemplateMailable::class, 1);
        Mail::assertSent(EmailTemplateMailable::class, fn ($mail) => $mail->hasTo($recipientA->email));
        Mail::assertSent(EmailTemplateMailable::class, fn ($mail) => $mail->hasTo($recipientB->email));
        $this->assertDatabaseHas('event_logs', [
            'event_key' => 'workshop.created',
            'status' => 'sent',
        ]);
    }

    public function test_enrollment_cancelled_triggers_email_to_participant(): void
    {
        Mail::fake();

        $this->templateFor('workshop.enrollment_cancelled');
        EmailTrigger::create([
            'event_key' => 'workshop.enrollment_cancelled',
            'email_template_id' => EmailTemplate::where('event_key', 'workshop.enrollment_cancelled')->first()->id,
            'to' => 'destinatario',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin());

        $user = auth()->user();
        $workshop = Workshop::create([
            'name' => 'Taller futuro',
            'description' => 'Descripción',
            'capacity' => 10,
            'location' => 'Aula 1',
            'day' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'created_by' => $user->id,
        ]);

        $this->post(route('workshops.enroll', $workshop))->assertSessionHas('success');
        $this->delete(route('workshops.unenroll', $workshop))->assertSessionHas('success');

        Mail::assertSent(EmailTemplateMailable::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertDatabaseHas('event_logs', [
            'event_key' => 'workshop.enrollment_cancelled',
            'status' => 'sent',
        ]);
    }

    public function test_attendance_confirmed_triggers_email_to_participant(): void
    {
        Mail::fake();

        $this->templateFor('attendance.confirmed');
        EmailTrigger::create([
            'event_key' => 'attendance.confirmed',
            'email_template_id' => EmailTemplate::where('event_key', 'attendance.confirmed')->first()->id,
            'to' => 'destinatario',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin());

        $user = auth()->user();
        $workshop = $this->workshop();

        $this->post(route('workshops.enroll', $workshop))->assertSessionHas('success');
        $this->get(route('workshops.scan', $workshop))->assertOk();

        Mail::assertSent(EmailTemplateMailable::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertDatabaseHas('event_logs', [
            'event_key' => 'attendance.confirmed',
            'status' => 'sent',
        ]);
    }
}
