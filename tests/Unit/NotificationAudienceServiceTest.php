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

    private function workshop(string $name): Workshop
    {
        return Workshop::create([
            'name' => $name,
            'description' => 'Descripción',
            'capacity' => 10,
            'location' => 'Aula 1',
            'day' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'qr_time_restricted' => false,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    /**
     * Curso dividido: el representante es "Sesión 1" y la hijo "Sesión 2",
     * igual que en producción.
     */
    private function dividedCourse(): array
    {
        $parent = $this->workshop('Sesión 1: Geometría con eloquentía');
        $parent->update(['day' => '2026-10-05']);

        $child = $this->workshop('Sesión 2: Geometría con eloquentía');
        $child->update([
            'day' => '2026-10-06',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'parent_workshop_id' => $parent->id,
        ]);

        return [$parent, $child];
    }

    public function test_normalize_segments_casts_and_drops_empty_ones(): void
    {
        $segments = $this->service->normalizeSegments([
            ['type' => 'role', 'role_ids' => ['3', 3, 0, -1]],
            ['type' => 'workshop_enrollment', 'workshop_ids' => ['5'], 'all_workshops' => 1],
            ['type' => 'individual', 'user_ids' => []],
            ['type' => 'inventado', 'role_ids' => [1]],
            'no es un array',
        ]);

        $this->assertSame([
            ['type' => 'role', 'role_ids' => [3]],
            ['type' => 'workshop_enrollment', 'workshop_ids' => [5], 'all_workshops' => true],
        ], $segments);
    }

    public function test_normalize_segments_accepts_nothing_useful(): void
    {
        $this->assertSame([], $this->service->normalizeSegments(null));
        $this->assertSame([], $this->service->normalizeSegments([]));
    }

    public function test_resolve_segments_unions_groups_without_duplicating_people(): void
    {
        $role = $this->role('Comité');
        $speaker = User::factory()->create();
        $speaker->roles()->sync([$role->id]);
        $committee = User::factory()->create();
        $committee->roles()->sync([$role->id]);

        ParticipationType::updateOrCreate(['key' => 'conferencia_magistral'], [
            'label' => 'Conferencista magistral',
            'event_kind' => 'conference',
            'kind' => 'magistral',
            'role' => 'speaker',
            'is_active' => true,
        ]);
        $conference = $this->conference($speaker, 'magistral');
        $conference->members()->attach($speaker->id, ['role' => 'speaker']);

        $users = $this->service->resolveSegments([
            ['type' => 'role', 'role_ids' => [$role->id]],
            ['type' => 'speakers_by_kind', 'kinds' => ['magistral']],
        ]);

        $this->assertSame(
            [$speaker->id, $committee->id],
            $users->pluck('id')->sort()->values()->all()
        );
        $this->assertCount(2, $users);
    }

    public function test_resolve_segments_detailed_records_every_matching_group(): void
    {
        $role = $this->role('Comité');
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $other = User::factory()->create();

        $resolved = $this->service->resolveSegmentsDetailed([
            ['type' => 'role', 'role_ids' => [$role->id]],
            ['type' => 'individual', 'user_ids' => [$user->id, $other->id]],
        ]);

        $this->assertCount(2, $resolved['users']);
        $this->assertCount(2, $resolved['matches'][$user->id]);
        $this->assertCount(1, $resolved['matches'][$other->id]);
        $this->assertSame('role', $resolved['matches'][$user->id][0]['type']);
        $this->assertSame('individual', $resolved['matches'][$user->id][1]['type']);
    }

    public function test_resolve_segments_ignores_segments_without_criteria(): void
    {
        $this->assertTrue($this->service->resolveSegments([
            ['type' => 'role', 'role_ids' => []],
            ['type' => 'individual', 'user_ids' => []],
        ])->isEmpty());
    }

    public function test_workshop_instructors_returns_only_teachers_of_the_workshops(): void
    {
        $workshop = $this->workshop('Taller con instructor');
        $other = $this->workshop('Otro taller');

        $instructor = User::factory()->create();
        $workshop->instructors()->attach($instructor->id);
        $moderator = User::factory()->create();
        $workshop->moderators()->attach($moderator->id);
        $outsider = User::factory()->create();
        $other->instructors()->attach($outsider->id);
        $inactive = User::factory()->create(['is_active' => false]);
        $workshop->instructors()->attach($inactive->id);
        $enrolled = User::factory()->create();
        $workshop->enrollments()->create(['user_id' => $enrolled->id, 'enrolled_at' => now()]);

        $this->assertSame(
            [$instructor->id],
            $this->service->workshopInstructors([$workshop->id])->pluck('id')->all()
        );

        $all = $this->service->workshopInstructors([], true);
        $this->assertSame(
            [$instructor->id, $outsider->id],
            $all->pluck('id')->sort()->values()->all()
        );
    }

    public function test_workshop_enrollments_respects_the_workshop_list(): void
    {
        $first = $this->workshop('Taller A');
        $second = $this->workshop('Taller B');

        $both = User::factory()->create();
        $onlyFirst = User::factory()->create();
        $first->enrollments()->create(['user_id' => $both->id, 'enrolled_at' => now()]);
        $first->enrollments()->create(['user_id' => $onlyFirst->id, 'enrolled_at' => now()]);
        $second->enrollments()->create(['user_id' => $both->id, 'enrolled_at' => now()]);

        $this->assertSame(
            [$both->id, $onlyFirst->id],
            $this->service->workshopEnrollments([$first->id])->pluck('id')->sort()->values()->all()
        );

        $this->assertSame(
            [$both->id, $onlyFirst->id],
            $this->service->workshopEnrollments([], true)->pluck('id')->sort()->values()->all()
        );
    }

    public function test_workshop_enrollments_skip_soft_deleted_workshops(): void
    {
        $workshop = $this->workshop('Taller eliminado');
        $user = User::factory()->create();
        $workshop->enrollments()->create(['user_id' => $user->id, 'enrolled_at' => now()]);

        $this->assertCount(1, $this->service->workshopEnrollments([$workshop->id]));

        $workshop->delete();

        $this->assertTrue($this->service->workshopEnrollments([$workshop->id])->isEmpty());
        $this->assertTrue($this->service->workshopEnrollments([], true)->isEmpty());
    }

    public function test_payload_for_keeps_legacy_fields_and_adds_plurals(): void
    {
        ParticipationType::updateOrCreate(['key' => 'conferencia_especial'], [
            'label' => 'Conferencista especial',
            'event_kind' => 'conference',
            'kind' => 'especial',
            'role' => 'speaker',
            'is_active' => true,
        ]);

        $workshop = $this->workshop('Taller de algebra');
        $user = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Díaz']);

        $payload = $this->service->payloadFor($user, [
            ['type' => 'speakers_by_kind', 'kinds' => ['especial']],
            ['type' => 'workshop_enrollment', 'workshop_ids' => [$workshop->id]],
        ]);

        $this->assertSame('Ana', $payload['nombre']);
        $this->assertSame('Conferencista especial', $payload['tipo_conferencia']);
        $this->assertSame('Taller de algebra', $payload['nombre_taller']);
        $this->assertSame('Conferencista especial', $payload['tipos_conferencia']);
        $this->assertSame('Taller de algebra', $payload['nombres_talleres']);
        $this->assertSame(
            'Speakers: Conferencista especial + Inscritos: Taller de algebra',
            $payload['grupos']
        );
    }

    public function test_payload_for_without_workshop_or_kind_leaves_fields_empty(): void
    {
        $user = User::factory()->create();

        $payload = $this->service->payloadFor($user, [['type' => 'all_users']]);

        $this->assertSame('', $payload['tipo_conferencia']);
        $this->assertSame('', $payload['nombre_taller']);
        $this->assertSame('Todos los usuarios', $payload['grupos']);
    }

    public function test_label_for_summarizes_combined_audiences(): void
    {
        $role = $this->role('Comité');
        $sender = User::factory()->create();
        $workshop = $this->workshop('Taller resumible');

        $single = NotificationSend::create([
            'subject' => 'Uno',
            'body_html' => '<p>x</p>',
            'audience_type' => NotificationSend::AUDIENCE_SEGMENTS,
            'audience_value' => [
                'segments' => [
                    ['type' => 'workshop_instructors', 'all_workshops' => true],
                ],
            ],
            'sent_by' => $sender->id,
            'recipient_count' => 4,
            'status' => NotificationSend::STATUS_SENT,
        ]);

        $combined = NotificationSend::create([
            'subject' => 'Varios',
            'body_html' => '<p>x</p>',
            'audience_type' => NotificationSend::AUDIENCE_SEGMENTS,
            'audience_value' => [
                'segments' => [
                    ['type' => 'role', 'role_ids' => [$role->id]],
                    ['type' => 'workshop_instructors', 'workshop_ids' => [$workshop->id]],
                    ['type' => 'individual', 'user_ids' => [1, 2, 3]],
                ],
            ],
            'sent_by' => $sender->id,
            'recipient_count' => 6,
            'status' => NotificationSend::STATUS_SENT,
        ]);

        $empty = NotificationSend::create([
            'subject' => 'Vacío',
            'body_html' => '<p>x</p>',
            'audience_type' => NotificationSend::AUDIENCE_SEGMENTS,
            'audience_value' => ['segments' => []],
            'sent_by' => $sender->id,
            'recipient_count' => 0,
            'status' => NotificationSend::STATUS_SENT,
        ]);

        $this->assertSame('Instructores: todos los talleres', $this->service->labelFor($single));
        $this->assertSame(
            '3 grupos: Roles: Comité + Instructores: Taller resumible + Usuarios sueltos: 3',
            $this->service->labelFor($combined)
        );
        $this->assertSame('Sin audiencia', $this->service->labelFor($empty));
    }

    public function test_workshop_courses_returns_one_item_per_course(): void
    {
        [$parent, $child] = $this->dividedCourse();
        $standalone = $this->workshop('Taller suelto');

        $courses = $this->service->workshopCourses();

        $this->assertCount(2, $courses, 'Un curso dividido debe ser un solo item.');

        $course = collect($courses)->firstWhere('id', $parent->id);
        $this->assertNotNull($course);
        $this->assertSame('Geometría con eloquentía', $course['name']);
        $this->assertTrue($course['is_divided']);
        $this->assertSame(2, $course['total_sessions']);
        $this->assertSame(
            [$parent->id, $child->id],
            collect($course['workshop_ids'])->sort()->values()->all()
        );
        $this->assertSame(['2026-10-05', '2026-10-06'], $course['days']);

        $plain = collect($courses)->firstWhere('id', $standalone->id);
        $this->assertSame('Taller suelto', $plain['name']);
        $this->assertFalse($plain['is_divided']);
        $this->assertSame(1, $plain['total_sessions']);
    }

    public function test_workshop_courses_filters_by_instructor_through_any_session(): void
    {
        [$parent, $child] = $this->dividedCourse();
        $other = $this->workshop('Curso ajeno');

        $onlySecondSession = User::factory()->create();
        $child->instructors()->attach($onlySecondSession->id);

        $stranger = User::factory()->create();
        $other->instructors()->attach($stranger->id);

        $courses = $this->service->workshopCourses($onlySecondSession);

        $this->assertCount(1, $courses);
        $this->assertSame($parent->id, $courses[0]['id']);
    }

    public function test_expand_to_sessions_accepts_the_course_or_any_session(): void
    {
        [$parent, $child] = $this->dividedCourse();
        $expected = collect([$child->id, $parent->id])->sort()->values()->all();

        $this->assertSame(
            $expected,
            collect($this->service->expandToSessions([$parent->id]))->sort()->values()->all()
        );

        $this->assertSame(
            $expected,
            collect($this->service->expandToSessions([$child->id]))->sort()->values()->all()
        );

        $this->assertSame(
            $expected,
            collect($this->service->expandToSessions([$parent->id, $child->id, $parent->id]))->sort()->values()->all()
        );

        // Un id que ya no existe se conserva para que el filtro de deleted_at
        // lo descarte al resolver, en lugar de ampliar la audiencia.
        $this->assertSame([99999], $this->service->expandToSessions([99999]));
        $this->assertTrue(
            $this->service->workshopInstructors([99999])->isEmpty()
        );
    }

    public function test_workshop_instructors_of_a_course_include_a_session_only_teacher(): void
    {
        [$parent, $child] = $this->dividedCourse();

        $onlySecond = User::factory()->create();
        $child->instructors()->attach($onlySecond->id);

        $onlyFirst = User::factory()->create();
        $parent->instructors()->attach($onlyFirst->id);

        $outsider = User::factory()->create();
        $this->workshop('Curso sin relación')->instructors()->attach($outsider->id);

        $byCourse = $this->service->workshopInstructors([$parent->id]);
        $this->assertSame(
            [$onlySecond->id, $onlyFirst->id],
            $byCourse->pluck('id')->sort()->values()->all()
        );

        // También alcanzable partiendo de la sesión hija.
        $this->assertSame(
            $byCourse->pluck('id')->sort()->values()->all(),
            $this->service->workshopInstructors([$child->id])->pluck('id')->sort()->values()->all()
        );
    }

    public function test_workshop_enrollments_of_a_course_reach_every_session_without_duplicating(): void
    {
        [$parent, $child] = $this->dividedCourse();

        $both = User::factory()->create();
        $parent->enrollments()->create(['user_id' => $both->id, 'enrolled_at' => now()]);
        $child->enrollments()->create(['user_id' => $both->id, 'enrolled_at' => now()]);

        $onlySecond = User::factory()->create();
        $child->enrollments()->create(['user_id' => $onlySecond->id, 'enrolled_at' => now()]);

        $users = $this->service->workshopEnrollments([$parent->id]);

        $this->assertSame(
            [$both->id, $onlySecond->id],
            $users->pluck('id')->sort()->values()->all()
        );
        $this->assertCount(2, $users, 'Inscrito en dos sesiones sigue siendo una persona.');
    }

    public function test_instructs_course_is_true_from_any_session(): void
    {
        [$parent, $child] = $this->dividedCourse();
        $other = $this->workshop('Curso ajeno');

        $teacher = User::factory()->create();
        $child->instructors()->attach($teacher->id);

        $stranger = User::factory()->create();
        $other->instructors()->attach($stranger->id);

        $this->assertTrue($this->service->instructsCourse($teacher, $parent->id));
        $this->assertTrue($this->service->instructsCourse($teacher, $child->id));
        $this->assertFalse($this->service->instructsCourse($stranger, $parent->id));
        $this->assertFalse($this->service->instructsCourse($teacher, $other->id));
    }

    public function test_course_labels_and_payload_drop_the_session_prefix(): void
    {
        [$parent, $child] = $this->dividedCourse();
        $user = User::factory()->create();
        $child->enrollments()->create(['user_id' => $user->id, 'enrolled_at' => now()]);
        $parent->instructors()->attach($user->id);

        $this->assertSame(['Geometría con eloquentía'], $this->service->workshopNames([$parent->id]));

        $segment = ['type' => 'workshop_enrollment', 'workshop_ids' => [$parent->id], 'all_workshops' => false];

        $this->assertSame('Inscritos: Geometría con eloquentía', $this->service->segmentLabel($segment));

        $payload = $this->service->payloadFor($user, [$segment]);
        $this->assertSame('Geometría con eloquentía', $payload['nombre_taller']);
        $this->assertSame('Geometría con eloquentía', $payload['nombres_talleres']);
        $this->assertStringNotContainsString('Sesión', $payload['grupos']);

        // Ambos segmentos del mismo curso no repiten el nombre.
        $both = $this->service->segmentLabel([
            'type' => 'workshop_instructors',
            'workshop_ids' => [$child->id],
            'all_workshops' => false,
        ]);
        $this->assertSame('Instructores: Geometría con eloquentía', $both);
    }

    public function test_course_labels_keep_plain_workshop_names(): void
    {
        $workshop = $this->workshop('Taller de retrato digital');

        $this->assertSame(
            ['Taller de retrato digital'],
            $this->service->workshopNames([$workshop->id])
        );
        $this->assertSame(
            ['Taller de retrato digital'],
            $this->service->workshopNames([], true)
        );
    }
}
