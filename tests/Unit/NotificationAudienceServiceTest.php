<?php

namespace Tests\Unit;

use App\Models\Conference;
use App\Models\NotificationSend;
use App\Models\ParticipationType;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Services\NotificationAudienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAudienceServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationAudienceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new NotificationAudienceService;
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name]);
    }

    private function conference(User $creator, string $kind): Conference
    {
        return Conference::create([
            'title' => 'Conferencia '.$kind,
            'kind' => $kind,
            'day' => '2026-08-05',
            'location' => 'Auditorio',
            'start_time' => '09:00',
            'end_time' => '10:30',
            'created_by' => $creator->id,
        ]);
    }

    public function test_all_users_only_returns_active_users(): void
    {
        $active = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);

        $users = $this->service->allUsers();

        $this->assertContains($active->id, $users->pluck('id'));
        $this->assertNotContains($inactive->id, $users->pluck('id'));
    }

    public function test_by_role_returns_only_users_with_that_role(): void
    {
        $role = $this->role('Comité');
        $member = User::factory()->create();
        $member->roles()->sync([$role->id]);
        $outsider = User::factory()->create();
        $roleless = User::factory()->create(['is_active' => false]);
        $roleless->roles()->attach($role->id);

        $users = $this->service->byRole($role->id);

        $this->assertContains($member->id, $users->pluck('id'));
        $this->assertNotContains($outsider->id, $users->pluck('id'));
        $this->assertNotContains($roleless->id, $users->pluck('id'));
    }

    public function test_speakers_by_kind_includes_unactivated_speakers_only_of_that_kind(): void
    {
        ParticipationType::updateOrCreate(['key' => 'conferencia_magistral'], [
            'label' => 'Conferencista magistral',
            'event_kind' => 'conference',
            'kind' => 'magistral',
            'role' => 'speaker',
            'is_active' => true,
        ]);

        $speaker = User::factory()->create();
        $unactivated = User::factory()->create();
        $moderator = User::factory()->create();

        $magistral = $this->conference($speaker, 'magistral');
        $magistral->members()->attach($speaker->id, ['role' => 'speaker']);
        $magistral->members()->attach($unactivated->id, ['role' => 'speaker', 'activated' => false]);
        $magistral->members()->attach($moderator->id, ['role' => 'moderator']);

        $simposio = $this->conference($speaker, 'simposio');
        $simposio->members()->attach($speaker->id, ['role' => 'speaker']);

        $users = $this->service->speakersByKind('magistral');

        $this->assertSame(2, $users->count());
        $this->assertContains($speaker->id, $users->pluck('id'));
        $this->assertContains($unactivated->id, $users->pluck('id'));
        $this->assertNotContains($moderator->id, $users->pluck('id'));
    }

    public function test_individual_filters_active_users_and_ignores_missing(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create(['is_active' => false]);

        $users = $this->service->individual([$first->id, $second->id, 99999]);

        $this->assertSame([$first->id], $users->pluck('id')->all());
    }

    public function test_resolve_maps_each_audience_type(): void
    {
        $user = User::factory()->create();
        $role = $this->role('Ponente');
        $user->roles()->sync([$role->id]);

        $this->assertSame(
            $user->id,
            $this->service->resolve(NotificationSend::AUDIENCE_TYPES[0])->firstOrFail()->id
        );

        $this->assertSame(
            $user->id,
            $this->service->resolve('role', $role->id)->firstOrFail()->id
        );

        ParticipationType::updateOrCreate(['key' => 'conferencia_especial'], [
            'label' => 'Conferencista especial',
            'event_kind' => 'conference',
            'kind' => 'especial',
            'role' => 'speaker',
            'is_active' => true,
        ]);
        $conference = $this->conference($user, 'especial');
        $conference->members()->attach($user->id, ['role' => 'speaker']);

        $this->assertSame(
            $user->id,
            $this->service->resolve('speakers_by_kind', null, 'especial')->firstOrFail()->id
        );

        $this->assertSame(
            $user->id,
            $this->service->resolve('individual', null, null, [$user->id])->firstOrFail()->id
        );

        $this->assertTrue($this->service->resolve('desconocido')->isEmpty());
    }

    public function test_build_payload_resolves_profile_and_rol(): void
    {
        $role = $this->role('Asistente');
        $user = User::factory()->create([
            'first_name' => 'Luis',
            'last_name' => 'Pérez',
            'dni' => '123456789',
        ]);
        $user->roles()->sync([$role->id]);

        $payload = $this->service->buildPayload($user, 'Conferencista magistral', 'Taller de prueba');

        $this->assertSame($user->name, $payload['nombre_completo']);
        $this->assertSame('Luis', $payload['nombre']);
        $this->assertSame('Pérez', $payload['apellidos']);
        $this->assertSame($user->email, $payload['correo']);
        $this->assertSame('123456789', $payload['dni']);
        $this->assertSame('Asistente', $payload['rol']);
        $this->assertSame('Conferencista magistral', $payload['tipo_conferencia']);
        $this->assertSame('Taller de prueba', $payload['nombre_taller']);
    }

    public function test_workshop_enrollment_returns_only_enrolled_active_users(): void
    {
        $workshop = Workshop::create([
            'name' => 'Taller de prueba',
            'description' => 'Descripción',
            'capacity' => 10,
            'location' => 'Aula 1',
            'day' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'qr_time_restricted' => false,
            'created_by' => User::factory()->create()->id,
        ]);

        $enrolled = User::factory()->create();
        $cancelled = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $outside = User::factory()->create();

        $workshop->enrollments()->create(['user_id' => $enrolled->id, 'enrolled_at' => now()]);
        $workshop->enrollments()->create(['user_id' => $cancelled->id, 'enrolled_at' => now(), 'status' => 'cancelled']);
        $workshop->enrollments()->create(['user_id' => $inactive->id, 'enrolled_at' => now()]);

        $users = $this->service->workshopEnrollment($workshop->id);

        $this->assertSame([$enrolled->id], $users->pluck('id')->all());
        $this->assertNotContains($outside->id, $users->pluck('id'));
        $this->assertSame('Taller de prueba', $this->service->workshopName($workshop->id));
    }

    public function test_resolve_maps_workshop_enrollment_audience(): void
    {
        $workshop = Workshop::create([
            'name' => 'Taller para resolve',
            'description' => 'Descripción',
            'capacity' => 10,
            'location' => 'Aula 2',
            'day' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'qr_time_restricted' => false,
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $workshop->enrollments()->create(['user_id' => $user->id, 'enrolled_at' => now()]);

        $this->assertSame(
            $user->id,
            $this->service->resolve('workshop_enrollment', null, null, [], $workshop->id)->firstOrFail()->id
        );
    }

    public function test_label_for_describes_each_audience(): void
    {
        $role = $this->role('Comité');
        $sender = User::factory()->create();
        $sender->roles()->sync([$role->id]);
        $this->role('Asistente');

        ParticipationType::updateOrCreate(['key' => 'conferencia_magistral'], [
            'label' => 'Conferencista magistral',
            'event_kind' => 'conference',
            'kind' => 'magistral',
            'role' => 'speaker',
            'is_active' => true,
        ]);

        $sendAll = NotificationSend::create([
            'subject' => 'Todos',
            'body_html' => '<p>x</p>',
            'audience_type' => 'all_users',
            'sent_by' => $sender->id,
            'recipient_count' => 3,
            'status' => NotificationSend::STATUS_SENT,
        ]);
        $sendRole = NotificationSend::create([
            'subject' => 'Rol',
            'body_html' => '<p>x</p>',
            'audience_type' => 'role',
            'audience_value' => ['role_id' => $role->id],
            'sent_by' => $sender->id,
            'recipient_count' => 2,
            'status' => NotificationSend::STATUS_SENT,
        ]);
        $sendKind = NotificationSend::create([
            'subject' => 'Tipo',
            'body_html' => '<p>x</p>',
            'audience_type' => 'speakers_by_kind',
            'audience_value' => ['kind' => 'magistral'],
            'sent_by' => $sender->id,
            'recipient_count' => 4,
            'status' => NotificationSend::STATUS_SENT,
        ]);
        $sendIndividual = NotificationSend::create([
            'subject' => 'Individual',
            'body_html' => '<p>x</p>',
            'audience_type' => 'individual',
            'audience_value' => ['user_ids' => [1]],
            'sent_by' => $sender->id,
            'recipient_count' => 1,
            'status' => NotificationSend::STATUS_SENT,
        ]);
        $workshop = Workshop::create([
            'name' => 'Taller etiqueta',
            'description' => 'Descripción',
            'capacity' => 10,
            'location' => 'Aula 3',
            'day' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'qr_time_restricted' => false,
            'created_by' => $sender->id,
        ]);
        $sendWorkshop = NotificationSend::create([
            'subject' => 'Taller',
            'body_html' => '<p>x</p>',
            'audience_type' => 'workshop_enrollment',
            'audience_value' => ['workshop_id' => $workshop->id],
            'sent_by' => $sender->id,
            'recipient_count' => 1,
            'status' => NotificationSend::STATUS_SENT,
        ]);

        $this->assertSame('Todos los usuarios', $this->service->labelFor($sendAll));
        $this->assertSame('Rol: Comité', $this->service->labelFor($sendRole));
        $this->assertSame('Speakers por tipo: Conferencista magistral', $this->service->labelFor($sendKind));
        $this->assertSame('Individual (1)', $this->service->labelFor($sendIndividual));
        $this->assertSame('Inscritos en taller: Taller etiqueta', $this->service->labelFor($sendWorkshop));
    }

    public function test_conference_kinds_returns_labels_from_participation_types(): void
    {
        ParticipationType::updateOrCreate(['key' => 'conferencia_magistral'], [
            'label' => 'Conferencista magistral',
            'event_kind' => 'conference',
            'kind' => 'magistral',
            'role' => 'speaker',
            'is_active' => true,
        ]);

        $kinds = $this->service->conferenceKinds();

        $this->assertSame('magistral', array_key_first($kinds));
        $this->assertSame('Conferencista magistral', $kinds['magistral']);
    }
}
