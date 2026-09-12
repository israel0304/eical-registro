<?php

namespace Tests\Feature;

use App\Mail\NotificationMailable;
use App\Models\Conference;
use App\Models\EmailTemplate;
use App\Models\NotificationRecipient;
use App\Models\NotificationSend;
use App\Models\ParticipationType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificacionesTest extends TestCase
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

    private function conferenceType(string $kind = 'magistral', string $label = 'Conferencista magistral'): ParticipationType
    {
        return ParticipationType::updateOrCreate(
            ['key' => 'conferencia_'.$kind],
            [
                'label' => $label,
                'event_kind' => 'conference',
                'kind' => $kind,
                'role' => 'speaker',
                'is_active' => true,
            ],
        );
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
            'created_by' => auth()->id() ?? User::factory()->create()->id,
        ]);
    }

    public function test_only_users_with_permission_can_access_notifications(): void
    {
        $this->actingAs($this->userWith('users.view'))
            ->get(route('correos.notificaciones.index'))
            ->assertForbidden();

        $this->actingAs($this->userWith('users.view'))
            ->get(route('correos.notificaciones.create'))
            ->assertForbidden();

        $this->actingAs($this->userWith('correos.notifications.manage'))
            ->get(route('correos.notificaciones.index'))
            ->assertOk();

        $this->actingAs($this->userWith('correos.notifications.manage'))
            ->get(route('correos.notificaciones.create'))
            ->assertOk();
    }

    public function test_create_page_exposes_roles_kinds_and_templates(): void
    {
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $this->conferenceType('magistral', 'Conferencista magistral');
        EmailTemplate::create([
            'event_key' => 'workshop.enrollment',
            'name' => 'Plantilla de prueba',
            'subject' => 'Asunto {{ nombre_completo }}',
            'body_html' => '<p>Hola {{ nombre_completo }}</p>',
        ]);

        $this->actingAs($this->admin())
            ->get(route('correos.notificaciones.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Notificaciones/Create')
                ->has('roles')
                ->where('roles', fn ($roles) => collect($roles)->contains('name', 'Asistente'))
                ->where('conferenceKinds.magistral', 'Conferencista magistral')
                ->has('templates', 1));
    }

    public function test_preview_counts_users_of_selected_role(): void
    {
        $role = Role::firstOrCreate(['name' => 'Comité']);
        $recipient = User::factory()->create();
        $recipient->roles()->sync([$role->id]);

        $this->actingAs($this->admin())
            ->postJson(route('correos.notificaciones.preview'), [
                'audience_type' => 'role',
                'role_id' => $role->id,
            ])
            ->assertOk()
            ->assertJson([
                'count' => 1,
                'sample' => [
                    ['email' => $recipient->email],
                ],
            ]);
    }

    public function test_preview_counts_all_speakers_by_kind_even_unactivated(): void
    {
        $this->conferenceType('magistral', 'Conferencista magistral');

        $speaker = User::factory()->create();
        $conference = Conference::create([
            'title' => 'Magistral inaugural',
            'kind' => 'magistral',
            'day' => '2026-08-05',
            'location' => 'Auditorio',
            'start_time' => '09:00',
            'end_time' => '10:30',
            'created_by' => $speaker->id,
        ]);
        $conference->members()->attach($speaker->id, ['role' => 'speaker', 'activated' => false]);

        $inactiveSpeaker = User::factory()->create(['is_active' => false]);
        $conference->members()->attach($inactiveSpeaker->id, ['role' => 'speaker']);

        $moderator = User::factory()->create();
        $conference->members()->attach($moderator->id, ['role' => 'moderator']);

        $otherConference = Conference::create([
            'title' => 'Simposio aparte',
            'kind' => 'simposio',
            'day' => '2026-08-06',
            'location' => 'Sala 2',
            'start_time' => '09:00',
            'end_time' => '10:30',
            'created_by' => $speaker->id,
        ]);
        $otherConference->members()->attach($speaker->id, ['role' => 'speaker']);

        $this->actingAs($this->admin())
            ->postJson(route('correos.notificaciones.preview'), [
                'audience_type' => 'speakers_by_kind',
                'kind' => 'magistral',
            ])
            ->assertOk()
            ->assertJson(['count' => 1]);
    }

    public function test_preview_counts_individual_users(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())
            ->postJson(route('correos.notificaciones.preview'), [
                'audience_type' => 'individual',
                'user_ids' => [$first->id, $second->id, $inactive->id],
            ])
            ->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_store_creates_send_recipients_and_dispatches_emails(): void
    {
        Mail::fake();

        $sender = $this->admin();
        $recipients = User::factory()->count(3)->create();

        $this->actingAs($sender)
            ->post(route('correos.notificaciones.store'), [
                'audience_type' => 'all_users',
                'subject' => 'Bienvenida {{ nombre_completo }}',
                'body_html' => '<p>Hola {{ nombre_completo }}</p>',
            ])
            ->assertRedirect(route('correos.notificaciones.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notification_sends', [
            'subject' => 'Bienvenida {{ nombre_completo }}',
            'audience_type' => 'all_users',
            'sent_by' => $sender->id,
            'recipient_count' => count($recipients) + 1,
            'status' => NotificationSend::STATUS_SENT,
        ]);

        $send = NotificationSend::firstOrFail();
        $this->assertSame(count($recipients) + 1, $send->recipients()->count());

        Mail::assertSent(NotificationMailable::class, count($recipients) + 1);
        Mail::assertSent(NotificationMailable::class, function (NotificationMailable $mail) use ($recipients) {
            return $mail->hasBcc($recipients->first()->email)
                && $mail->envelope()->subject === 'Bienvenida '.$recipients->first()->name;
        });
    }

    public function test_store_rejects_empty_audience_without_creating_send(): void
    {
        $role = Role::firstOrCreate(['name' => 'Vacío']);

        $this->actingAs($this->admin())
            ->post(route('correos.notificaciones.store'), [
                'audience_type' => 'role',
                'role_id' => $role->id,
                'subject' => 'Asunto',
                'body_html' => '<p>Cuerpo</p>',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('notification_sends', 0);
        $this->assertDatabaseCount('notification_recipients', 0);
    }

    public function test_store_requires_subject_and_body(): void
    {
        $this->actingAs($this->admin())
            ->post(route('correos.notificaciones.store'), [
                'audience_type' => 'all_users',
                'subject' => '',
                'body_html' => '',
            ])
            ->assertSessionHasErrors(['subject', 'body_html']);

        $this->assertDatabaseCount('notification_sends', 0);
    }

    public function test_index_lists_sends_with_audience_label(): void
    {
        $sender = $this->admin();
        $role = Role::firstOrCreate(['name' => 'Comité']);

        NotificationSend::create([
            'subject' => 'Aviso rol',
            'body_html' => '<p>Hola</p>',
            'audience_type' => 'role',
            'audience_value' => ['role_id' => $role->id],
            'sent_by' => $sender->id,
            'recipient_count' => 0,
            'status' => NotificationSend::STATUS_SENT,
        ]);

        $this->actingAs($sender)
            ->get(route('correos.notificaciones.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Notificaciones/Index')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.audience_label', 'Rol: Comité'));
    }

    public function test_users_search_returns_active_matches_only(): void
    {
        $admin = $this->admin();

        $match = User::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Martínez',
        ]);
        $inactive = User::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Velasco',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->getJson(route('correos.notificaciones.users', ['search' => 'Ana']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $match->id])
            ->assertJsonMissing(['id' => $inactive->id]);
    }

    public function test_individual_store_persists_chosen_user_ids_in_audience_value(): void
    {
        Mail::fake();

        $sender = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($sender)
            ->post(route('correos.notificaciones.store'), [
                'audience_type' => 'individual',
                'user_ids' => [$target->id],
                'subject' => 'Caso individual',
                'body_html' => '<p>Hola</p>',
            ])
            ->assertRedirect(route('correos.notificaciones.index'));

        $send = NotificationSend::firstOrFail();
        $this->assertSame(['user_ids' => [$target->id]], $send->audience_value);
        $this->assertSame(1, $send->recipients()->count());
        $this->assertSame($target->email, $send->recipients()->first()->email);

        Mail::assertSent(NotificationMailable::class, 1);
    }

    public function test_retry_failed_resends_only_failed_recipients(): void
    {
        Mail::fake();

        $sender = $this->admin();
        $recipient = User::factory()->create();

        $send = NotificationSend::create([
            'subject' => 'Reintento',
            'body_html' => '<p>Hola</p>',
            'audience_type' => 'all_users',
            'sent_by' => $sender->id,
            'recipient_count' => 1,
            'sent_count' => 1,
            'status' => NotificationSend::STATUS_FAILED,
        ]);
        $send->recipients()->create([
            'email' => $recipient->email,
            'name' => $recipient->name,
            'status' => NotificationRecipient::STATUS_FAILED,
            'error' => 'SMTP timeout',
        ]);

        $this->actingAs($sender)
            ->post(route('correos.notificaciones.retry-failed', $send))
            ->assertSessionHas('success');

        Mail::assertSent(NotificationMailable::class, 1);
        $this->assertDatabaseHas('notification_recipients', [
            'notification_send_id' => $send->id,
            'status' => NotificationRecipient::STATUS_SENT,
            'error' => null,
        ]);
        $this->assertDatabaseHas('notification_sends', [
            'id' => $send->id,
            'status' => NotificationSend::STATUS_SENT,
            'sent_count' => 1,
            'failed_count' => 0,
        ]);
    }

    public function test_retry_failed_without_failures_returns_error(): void
    {
        $sender = $this->admin();

        $send = NotificationSend::create([
            'subject' => 'Sin fallidos',
            'body_html' => '<p>Hola</p>',
            'audience_type' => 'all_users',
            'sent_by' => $sender->id,
            'recipient_count' => 0,
            'sent_count' => 0,
            'status' => NotificationSend::STATUS_SENT,
        ]);

        $this->actingAs($sender)
            ->post(route('correos.notificaciones.retry-failed', $send))
            ->assertSessionHas('error');
    }

    public function test_show_returns_recipients_json(): void
    {
        $sender = $this->admin();
        $recipient = User::factory()->create();

        $send = NotificationSend::create([
            'subject' => 'Con destinatarios',
            'body_html' => '<p>Hola</p>',
            'audience_type' => 'all_users',
            'sent_by' => $sender->id,
            'recipient_count' => 1,
            'status' => NotificationSend::STATUS_SENT,
        ]);
        $send->recipients()->create([
            'email' => $recipient->email,
            'name' => $recipient->name,
            'status' => NotificationRecipient::STATUS_SENT,
        ]);

        $this->actingAs($sender)
            ->getJson(route('correos.notificaciones.show', $send))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['email' => $recipient->email]);
    }

    public function test_instructor_with_workshop_permission_can_open_composer_prefilled(): void
    {
        $instructor = $this->userWith('workshops.enrollments.email');
        $workshop = $this->workshop();
        $workshop->instructors()->attach($instructor->id);

        $this->actingAs($instructor)
            ->get(route('correos.notificaciones.create', ['workshop_id' => $workshop->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notificaciones/Create')
                ->where('workshopId', $workshop->id)
                ->where('workshopName', $workshop->name));
    }

    public function test_workshop_preview_counts_only_enrolled_active_users(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop();

        $enrolled = User::factory()->create();
        $cancelled = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);

        $workshop->enrollments()->create(['user_id' => $enrolled->id, 'enrolled_at' => now()]);
        $workshop->enrollments()->create(['user_id' => $cancelled->id, 'enrolled_at' => now(), 'status' => 'cancelled']);
        $workshop->enrollments()->create(['user_id' => $inactive->id, 'enrolled_at' => now()]);

        $this->actingAs($admin)
            ->postJson(route('correos.notificaciones.preview'), [
                'audience_type' => 'workshop_enrollment',
                'workshop_id' => $workshop->id,
            ])
            ->assertOk()
            ->assertJson([
                'count' => 1,
                'sample' => [
                    ['email' => $enrolled->email],
                ],
            ]);
    }

    public function test_workshop_store_includes_taller_name_in_payload(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $workshop = $this->workshop();
        $enrolled = User::factory()->create();
        $workshop->enrollments()->create(['user_id' => $enrolled->id, 'enrolled_at' => now()]);

        $this->actingAs($admin)
            ->post(route('correos.notificaciones.store'), [
                'audience_type' => 'workshop_enrollment',
                'workshop_id' => $workshop->id,
                'subject' => 'Inscrito en {{ nombre_taller }}',
                'body_html' => '<p>Hola {{ nombre_completo }}</p>',
            ])
            ->assertRedirect(route('correos.notificaciones.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notification_sends', [
            'audience_type' => 'workshop_enrollment',
            'recipient_count' => 1,
        ]);

        $recipient = NotificationSend::firstOrFail()->recipients()->first();
        $this->assertSame($workshop->name, $recipient->payload['nombre_taller']);
        $this->assertSame($enrolled->email, $recipient->email);
    }

    public function test_instructor_can_send_with_permission_to_own_workshop_enrolled(): void
    {
        Mail::fake();

        $instructor = $this->userWith('workshops.enrollments.email');
        $workshop = $this->workshop();
        $workshop->instructors()->attach($instructor->id);

        $enrolled = User::factory()->create();
        $workshop->enrollments()->create(['user_id' => $enrolled->id, 'enrolled_at' => now()]);

        $this->actingAs($instructor)
            ->post(route('correos.notificaciones.store'), [
                'audience_type' => 'workshop_enrollment',
                'workshop_id' => $workshop->id,
                'subject' => 'Asunto del taller',
                'body_html' => '<p>Cuerpo</p>',
            ])
            ->assertRedirect(route('correos.notificaciones.index'))
            ->assertSessionHas('success');

        Mail::assertSent(NotificationMailable::class, 1);
        Mail::assertSent(NotificationMailable::class, fn (NotificationMailable $mail) => $mail->hasBcc($enrolled->email));
    }

    public function test_instructor_with_permission_cannot_email_workshop_it_does_not_teach(): void
    {
        $instructor = $this->userWith('workshops.enrollments.email');
        $other = $this->workshop();

        $this->actingAs($instructor)
            ->postJson(route('correos.notificaciones.preview'), [
                'audience_type' => 'workshop_enrollment',
                'workshop_id' => $other->id,
            ])
            ->assertForbidden();

        $this->actingAs($instructor)
            ->post(route('correos.notificaciones.store'), [
                'audience_type' => 'workshop_enrollment',
                'workshop_id' => $other->id,
                'subject' => 'Asunto',
                'body_html' => '<p>Cuerpo</p>',
            ])
            ->assertForbidden();
    }

    public function test_instructor_with_workshop_permission_cannot_send_to_other_audiences(): void
    {
        $instructor = $this->userWith('workshops.enrollments.email');
        $workshop = $this->workshop();
        $workshop->instructors()->attach($instructor->id);

        $this->actingAs($instructor)
            ->postJson(route('correos.notificaciones.preview'), [
                'audience_type' => 'all_users',
            ])
            ->assertForbidden();

        $this->actingAs($instructor)
            ->post(route('correos.notificaciones.store'), [
                'audience_type' => 'role',
                'role_id' => Role::firstOrCreate(['name' => 'Comité'])->id,
                'subject' => 'Asunto',
                'body_html' => '<p>Cuerpo</p>',
            ])
            ->assertForbidden();
    }
}
