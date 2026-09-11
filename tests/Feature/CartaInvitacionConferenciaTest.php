<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Conference;
use App\Models\ParticipationType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartaInvitacionConferenciaTest extends TestCase
{
    use RefreshDatabase;

    private function speakerRole(): Role
    {
        $role = $this->role('Speaker', 5);
        $this->roleWithPermissions($role);

        return $role;
    }

    private function invitationTemplate(Role $role, ?ParticipationType $type = null, bool $active = true): CertificateTemplate
    {
        $template = CertificateTemplate::create([
            'name' => 'Carta Invitación '.($type?->label ?? $role->name),
            'description' => null,
            'kind' => 'invitation',
            'role_id' => $role->id,
            'participation_type_id' => $type?->id,
            'is_default' => true,
            'is_active' => $active,
            'width' => 816,
            'height' => 1056,
        ]);

        $template->elements()->create([
            'type' => 'text',
            'content' => '{nombre_completo}',
            'x' => 100,
            'y' => 300,
            'width' => 600,
            'height' => 40,
            'font_size' => 18,
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        return $template;
    }

    private function genericInvitationTemplate(bool $active = true): CertificateTemplate
    {
        $template = CertificateTemplate::create([
            'name' => 'Carta Invitación Genérica',
            'description' => null,
            'kind' => 'invitation',
            'role_id' => null,
            'is_default' => true,
            'is_active' => $active,
            'width' => 816,
            'height' => 1056,
        ]);

        $template->elements()->create([
            'type' => 'text',
            'content' => '{nombre_completo}',
            'x' => 100,
            'y' => 300,
            'width' => 600,
            'height' => 40,
            'font_size' => 18,
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        return $template;
    }

    private function conferenceType(string $key = 'conferencia_magistral', string $kind = 'magistral', string $label = 'Conferencista magistral'): ParticipationType
    {
        return ParticipationType::updateOrCreate(
            ['key' => $key],
            [
                'label' => $label,
                'event_kind' => 'conference',
                'kind' => $kind,
                'role' => 'speaker',
                'is_active' => true,
            ],
        );
    }

    private function roleWithPermissions(Role $role): Role
    {
        $download = Permission::updateOrCreate(
            ['key' => 'constancias.download'],
            ['module' => 'constancias', 'label' => 'Descargar constancias'],
        );
        $view = Permission::updateOrCreate(
            ['key' => 'constancias.view'],
            ['module' => 'constancias', 'label' => 'Ver constancias'],
        );

        $role->permissions()->sync([$download->id, $view->id]);

        return $role;
    }

    private function role(string $name, int $id): Role
    {
        $role = Role::find($id);

        if ($role !== null) {
            return $role;
        }

        return Role::query()->forceCreate(['id' => $id, 'name' => $name]);
    }

    private function speakerUser(): User
    {
        $user = User::factory()->create([
            'first_name' => 'María',
            'last_name' => 'López',
            'affiliation' => 'Cinvestav',
            'country' => 'México',
        ]);
        $user->roles()->sync([$this->speakerRole()->id]);

        return $user;
    }

    private function conference(User $user, string $kind = 'magistral', string $title = 'La conferencia magistral inaugural'): Conference
    {
        $conference = Conference::create([
            'title' => $title,
            'kind' => $kind,
            'day' => '2026-08-05',
            'location' => 'Auditorio',
            'start_time' => '09:00',
            'end_time' => '10:30',
            'created_by' => $user->id,
        ]);
        $conference->members()->attach($user->id, ['role' => 'speaker']);

        return $conference;
    }

    private function downloadUrl(Conference $conference): string
    {
        return '/constancias/invitacion/conferencia/'.$conference->id.'/download';
    }

    public function test_speaker_can_download_conference_invitation_letter_without_activation(): void
    {
        $role = $this->speakerRole();
        $type = $this->conferenceType();
        $template = $this->invitationTemplate($role);
        $template->elements()->delete();
        $template->elements()->create([
            'type' => 'text',
            'content' => '{evento}',
            'x' => 100,
            'y' => 300,
            'width' => 600,
            'height' => 40,
            'font_size' => 18,
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        $user = $this->speakerUser();

        $conference = $this->conference($user);

        $coSpeaker = User::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
        ]);
        $conference->members()->attach($coSpeaker->id, ['role' => 'speaker']);

        $this->actingAs($user)
            ->get($this->downloadUrl($conference))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('La conferencia magistral inaugural', false);

        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'carta-conference')
            ->where('event_id', $conference->id)
            ->first();

        $this->assertNotNull($certificate);
        $this->assertSame($type->id, $certificate->participation_type_id);
        $this->assertSame($role->id, $certificate->role_id);
        $this->assertNotNull($certificate->folio);
        $this->assertNotNull($certificate->downloaded_at);
        $this->assertSame('La conferencia magistral inaugural', $certificate->metadata['evento'] ?? null);
        $this->assertSame('Conferencista magistral', $certificate->metadata['tipo_participacion'] ?? null);
        $this->assertSame('09:00 - 10:30', $certificate->metadata['horario'] ?? null);

        $speakers = $certificate->metadata['speakers'] ?? '';
        $this->assertStringContainsString('María López', $speakers);
        $this->assertStringContainsString('Juan Pérez', $speakers);
        $this->assertSame($speakers, $certificate->metadata['autores'] ?? '');
    }

    public function test_conference_kind_binds_type_template_over_role_template(): void
    {
        $role = $this->speakerRole();
        $this->genericInvitationTemplate();
        $this->invitationTemplate($role);
        $type = $this->conferenceType();
        $byType = $this->invitationTemplate($role, $type);

        $user = $this->speakerUser();

        $conference = $this->conference($user);

        $this->actingAs($user)
            ->get($this->downloadUrl($conference))
            ->assertOk();

        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'carta-conference')
            ->first();

        $this->assertSame($byType->id, $certificate->template_id);
    }

    public function test_non_member_cannot_download_conference_letter(): void
    {
        $this->speakerRole();
        $this->conferenceType();
        $role = $this->role('Speaker', 5);
        $this->invitationTemplate($role);
        $user = $this->speakerUser();

        $conference = $this->conference($user);
        $conference->members()->detach($user->id);

        $this->actingAs($user)
            ->get($this->downloadUrl($conference))
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_moderator_member_cannot_download_speaker_letter(): void
    {
        $this->speakerRole();
        $this->conferenceType();
        $role = $this->role('Speaker', 5);
        $this->invitationTemplate($role);
        $user = $this->speakerUser();

        $conference = Conference::create([
            'title' => 'Mesa moderada',
            'kind' => 'magistral',
            'day' => '2026-08-05',
            'location' => 'Auditorio',
            'created_by' => $user->id,
        ]);
        $conference->members()->attach($user->id, ['role' => 'moderator']);

        $this->actingAs($user)
            ->get($this->downloadUrl($conference))
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_no_template_blocks_conference_letter(): void
    {
        $this->speakerRole();
        $this->conferenceType();
        $user = $this->speakerUser();

        $conference = $this->conference($user);

        $this->actingAs($user)
            ->get($this->downloadUrl($conference))
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_shared_speaker_role_template_is_used_without_type_template(): void
    {
        $this->genericInvitationTemplate();
        $this->conferenceType();
        $role = $this->speakerRole();
        $template = $this->invitationTemplate($role);

        $user = $this->speakerUser();

        $conference = $this->conference($user);

        $this->actingAs($user)
            ->get($this->downloadUrl($conference))
            ->assertOk();

        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'carta-conference')
            ->first();

        $this->assertSame($template->id, $certificate->template_id);
    }

    public function test_generic_template_is_used_without_role_or_type_template(): void
    {
        $generic = $this->genericInvitationTemplate();
        $this->conferenceType();
        $user = $this->speakerUser();

        $conference = $this->conference($user);

        $this->actingAs($user)
            ->get($this->downloadUrl($conference))
            ->assertOk()
            ->assertSee('María', false);

        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'carta-conference')
            ->first();

        $this->assertSame($generic->id, $certificate->template_id);
    }

    public function test_speakers_and_tipo_participacion_variables_render(): void
    {
        $role = $this->speakerRole();
        $this->conferenceType();
        $template = $this->invitationTemplate($role);
        $template->elements()->delete();
        $template->elements()->create([
            'type' => 'text',
            'content' => '{speakers} | {tipo_participacion}',
            'x' => 100,
            'y' => 300,
            'width' => 600,
            'height' => 60,
            'font_size' => 18,
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        $user = $this->speakerUser();

        $conference = $this->conference($user);
        $conference->speakers()->attach(User::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
        ])->id);

        $this->actingAs($user)
            ->get($this->downloadUrl($conference))
            ->assertOk()
            ->assertSee('María López, Juan Pérez', false)
            ->assertSee('Conferencista magistral', false);
    }

    public function test_downloading_twice_returns_same_folio(): void
    {
        $this->speakerRole();
        $this->conferenceType();
        $role = $this->role('Speaker', 5);
        $this->invitationTemplate($role);
        $user = $this->speakerUser();

        $conference = $this->conference($user);

        $url = $this->downloadUrl($conference);
        $this->actingAs($user)->get($url)->assertOk();
        $first = Certificate::query()->first();

        $this->actingAs($user)->get($url)->assertOk();

        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame($first->folio, Certificate::query()->first()->folio);
    }

    public function test_my_certificates_exposes_carta_conferences(): void
    {
        $this->speakerRole();
        $this->conferenceType();
        $role = $this->role('Speaker', 5);
        $this->invitationTemplate($role);
        $user = $this->speakerUser();

        $conference = $this->conference($user);

        $this->actingAs($user)
            ->get('/constancias')
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->has('cartaConferences', 1)
                ->where('cartaConferences.0.title', 'La conferencia magistral inaugural')
                ->where('cartaConferences.0.tipo_participacion', 'Conferencista magistral'));

        $this->actingAs($user)->get($this->downloadUrl($conference))->assertOk();

        $this->actingAs($user)
            ->get('/constancias')
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->where('cartaConferences.0.cartaFolio', Certificate::query()->first()->folio));
    }

    public function test_seeder_creates_four_speaker_conference_types(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('participation_types', ['key' => 'conferencia_magistral', 'kind' => 'magistral', 'role' => 'speaker']);
        $this->assertDatabaseHas('participation_types', ['key' => 'conferencia_especial', 'kind' => 'especial', 'role' => 'speaker']);
        $this->assertDatabaseHas('participation_types', ['key' => 'simposiasta', 'kind' => 'simposio', 'role' => 'speaker']);
        $this->assertDatabaseHas('participation_types', ['key' => 'conferencia_grupo_tematico', 'kind' => 'grupo_tematico', 'role' => 'speaker']);
    }

    public function test_no_speaker_role_blocks_conference_letter(): void
    {
        $writeRole = Permission::updateOrCreate(
            ['key' => 'constancias.download'],
            ['module' => 'constancias', 'label' => 'Descargar constancias'],
        );
        $viewRole = Permission::updateOrCreate(
            ['key' => 'constancias.view'],
            ['module' => 'constancias', 'label' => 'Ver constancias'],
        );
        $asistente = $this->role('Asistente', 3);
        $asistente->permissions()->sync([$writeRole->id, $viewRole->id]);

        $this->conferenceType();
        $user = User::factory()->create();
        $user->roles()->sync([$asistente->id]);

        $conference = $this->conference($user);

        $this->actingAs($user)
            ->get($this->downloadUrl($conference))
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }
}
