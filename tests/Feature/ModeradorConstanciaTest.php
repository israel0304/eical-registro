<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Conference;
use App\Models\ModeradorConstancia;
use App\Models\ParticipationType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeradorConstanciaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function moderator(): User
    {
        $user = User::factory()->create([
            'first_name' => 'Moderador',
            'last_name' => 'Unico',
        ]);

        $role = Role::firstOrCreate(['name' => 'Moderator']);

        foreach (['constancias.view', 'constancias.download'] as $key) {
            Permission::firstOrCreate(['key' => $key], [
                'module' => 'constancias',
                'label' => $key,
            ]);
        }

        $role->permissions()->sync(
            Permission::whereIn('key', ['constancias.view', 'constancias.download'])->pluck('id')
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function conferenceFor(User $creator, array $overrides = []): Conference
    {
        return Conference::create(array_merge([
            'title' => 'Conferencia de prueba',
            'kind' => 'magistral',
            'location' => 'Aula Magna',
            'day' => '2026-10-07',
            'start_time' => '11:00',
            'end_time' => '12:00',
            'created_by' => $creator->id,
        ], $overrides));
    }

    private function moderadorType(): ParticipationType
    {
        return ParticipationType::updateOrCreate(
            ['key' => 'moderador'],
            [
                'label' => 'Moderador de conferencias',
                'event_kind' => 'conference',
                'kind' => null,
                'role' => 'moderator',
                'is_active' => true,
            ]
        );
    }

    private function moderadorTemplate(ParticipationType $type): CertificateTemplate
    {
        return CertificateTemplate::create([
            'name' => 'Constancia de Moderador',
            'kind' => 'certificate',
            'participation_type_id' => $type->id,
            'is_default' => true,
            'width' => 1800,
            'height' => 1200,
        ]);
    }

    public function test_my_certificates_shows_single_moderator_card_for_each_moderator(): void
    {
        $this->moderadorType();
        $moderator = $this->moderator();
        $creator = $this->admin();

        $c1 = $this->conferenceFor($creator, ['title' => 'Modera Una']);
        $c1->members()->attach($moderator->id, ['role' => 'moderator']);

        $c2 = $this->conferenceFor($creator, ['title' => 'Modera Dos']);
        $c2->members()->attach($moderator->id, ['role' => 'moderator']);

        $this->actingAs($moderator)
            ->get('/constancias')
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->has('moderatorConstancia')
                ->where('moderatorConstancia.conference_count', 2)
                ->where('moderatorConstancia.activated', false)
                ->where('moderatorConstancia.conference_titles', ['Modera Una', 'Modera Dos'])
                ->has('conferenceCertificates', 0));
    }

    public function test_my_certificates_keeps_speaker_cards_per_conference(): void
    {
        $this->moderadorType();
        $speaker = $this->moderator();
        $creator = $this->admin();

        $c1 = $this->conferenceFor($creator, ['title' => 'Expone Una']);
        $c1->members()->attach($speaker->id, ['role' => 'speaker', 'activated' => true]);

        $this->actingAs($speaker)
            ->get('/constancias')
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->has('conferenceCertificates', 1)
                ->where('conferenceCertificates.0.title', 'Expone Una')
                ->where('conferenceCertificates.0.activated', true)
                ->where('moderatorConstancia', null));
    }

    public function test_moderator_download_requires_activation(): void
    {
        $this->moderadorType();
        $moderador = $this->moderator();
        $creator = $this->admin();
        $c = $this->conferenceFor($creator);
        $c->members()->attach($moderador->id, ['role' => 'moderator']);

        $this->actingAs($moderador)
            ->get('/constancias/moderador/constancia')
            ->assertRedirect();

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_moderator_gets_a_single_certificate_not_one_per_conference(): void
    {
        $type = $this->moderadorType();
        $this->moderadorTemplate($type);
        $moderador = $this->moderator();
        $creator = $this->admin();

        $c1 = $this->conferenceFor($creator, ['title' => 'Modera Una']);
        $c1->members()->attach($moderador->id, ['role' => 'moderator']);

        $c2 = $this->conferenceFor($creator, ['title' => 'Modera Dos']);
        $c2->members()->attach($moderador->id, ['role' => 'moderator']);

        ModeradorConstancia::create(['user_id' => $moderador->id, 'activated' => true]);

        $this->actingAs($moderador)
            ->get('/constancias/moderador/constancia')
            ->assertOk();

        $this->actingAs($moderador)
            ->get('/constancias/moderador/constancia')
            ->assertOk();

        $this->assertDatabaseHas('certificates', [
            'user_id' => $moderador->id,
            'participation_type_id' => $type->id,
            'event_type' => 'conference',
            'event_id' => 0,
        ]);

        $this->assertSame(1, Certificate::query()->where('user_id', $moderador->id)->count());
    }

    public function test_admin_moderadores_index_lists_moderators_with_state(): void
    {
        $type = $this->moderadorType();
        $this->moderadorTemplate($type);
        $admin = $this->admin();
        $moderador = $this->moderator();
        $c = $this->conferenceFor($admin);
        $c->members()->attach($moderador->id, ['role' => 'moderator']);
        ModeradorConstancia::create(['user_id' => $moderador->id, 'activated' => true]);

        $this->actingAs($admin)
            ->get('/admin/constancias/moderadores')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Moderadores/Index')
                ->has('moderators', 1)
                ->where('moderators.0.full_name', 'Moderador Unico')
                ->where('moderators.0.conference_count', 1)
                ->where('moderators.0.activated', true)
                ->where('moderators.0.conference_titles', ['Conferencia de prueba']));
    }

    public function test_admin_toggle_activates_and_deactivates_moderator_constancia(): void
    {
        $admin = $this->admin();
        $moderador = $this->moderator();
        $c = $this->conferenceFor($admin);
        $c->members()->attach($moderador->id, ['role' => 'moderator']);

        $this->actingAs($admin)
            ->post('/admin/constancias/moderadores/'.$moderador->id.'/activar')
            ->assertRedirect();

        $this->assertDatabaseHas('moderador_constancias', [
            'user_id' => $moderador->id,
            'activated' => true,
        ]);

        $this->actingAs($admin)
            ->post('/admin/constancias/moderadores/'.$moderador->id.'/activar')
            ->assertRedirect();

        $this->assertDatabaseHas('moderador_constancias', [
            'user_id' => $moderador->id,
            'activated' => false,
        ]);
    }

    public function test_admin_download_generates_moderator_constancia_without_activation(): void
    {
        $type = $this->moderadorType();
        $this->moderadorTemplate($type);
        $admin = $this->admin();
        $moderador = $this->moderator();
        $c = $this->conferenceFor($admin);
        $c->members()->attach($moderador->id, ['role' => 'moderator']);

        $this->actingAs($admin)
            ->get('/admin/constancias/moderadores/'.$moderador->id.'/constancia')
            ->assertOk();

        $this->assertDatabaseHas('certificates', [
            'user_id' => $moderador->id,
            'participation_type_id' => $type->id,
            'event_type' => 'conference',
            'event_id' => 0,
        ]);
    }

    public function test_admin_download_conference_for_moderator_uses_single_constancia(): void
    {
        $type = $this->moderadorType();
        $this->moderadorTemplate($type);
        $admin = $this->admin();
        $moderador = $this->moderator();
        $c = $this->conferenceFor($admin);
        $c->members()->attach($moderador->id, ['role' => 'moderator']);

        $this->actingAs($admin)
            ->get('/admin/constancias/conferencia/'.$c->id.'/'.$moderador->id.'/download')
            ->assertOk();

        $this->assertDatabaseHas('certificates', [
            'user_id' => $moderador->id,
            'participation_type_id' => $type->id,
            'event_type' => 'conference',
            'event_id' => 0,
        ]);
    }
}
