<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ParticipationType;
use App\Models\Permission;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartaInvitacionTest extends TestCase
{
    use RefreshDatabase;

    private function cartaType(): ParticipationType
    {
        return ParticipationType::create([
            'key' => 'carta_invitacion',
            'label' => 'Carta de Invitación',
            'event_kind' => 'event',
            'kind' => 'carta',
            'role' => null,
            'is_active' => true,
            'manual_generable' => false,
        ]);
    }

    private function userWithPermission(string $roleName = 'Ponente'): User
    {
        $permission = Permission::updateOrCreate(
            ['key' => 'constancias.invitaciones.download'],
            ['module' => 'constancias', 'label' => 'Descargar carta de invitación'],
        );
        $download = Permission::updateOrCreate(
            ['key' => 'constancias.download'],
            ['module' => 'constancias', 'label' => 'Descargar constancias'],
        );
        $view = Permission::updateOrCreate(
            ['key' => 'constancias.view'],
            ['module' => 'constancias', 'label' => 'Ver constancias'],
        );

        $role = Role::firstOrCreate(['name' => $roleName]);
        $role->permissions()->sync([$permission->id, $download->id, $view->id]);

        $user = User::factory()->create([
            'first_name' => 'María',
            'last_name' => 'López',
            'affiliation' => 'Cinvestav',
            'country' => 'México',
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_user_with_permission_can_download_invitation_letter(): void
    {
        $type = $this->cartaType();
        $user = $this->userWithPermission();

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('María', false);

        $this->assertDatabaseHas('certificates', [
            'user_id' => $user->id,
            'participation_type_id' => $type->id,
            'event_type' => 'event',
            'event_id' => 0,
        ]);

        $certificate = Certificate::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($certificate->downloaded_at);
        $this->assertSame('Ponente', $certificate->metadata['rol'] ?? null);
    }

    public function test_downloading_twice_returns_same_folio(): void
    {
        $this->cartaType();
        $user = $this->userWithPermission();

        $this->actingAs($user)->get('/constancias/invitacion/descargar')->assertOk();
        $first = Certificate::query()->where('user_id', $user->id)->first();

        $this->actingAs($user)->get('/constancias/invitacion/descargar')->assertOk();

        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame($first->folio, Certificate::query()->where('user_id', $user->id)->first()->folio);
    }

    public function test_user_without_permission_cannot_download_invitation_letter(): void
    {
        $this->cartaType();
        $download = Permission::updateOrCreate(
            ['key' => 'constancias.download'],
            ['module' => 'constancias', 'label' => 'Descargar constancias'],
        );
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $role->permissions()->sync([$download->id]);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar')
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_carta_with_presentation_includes_ponencia_title(): void
    {
        $type = $this->cartaType();
        $user = $this->userWithPermission();

        $template = CertificateTemplate::create([
            'name' => 'Carta',
            'kind' => 'invitation',
            'participation_type_id' => $type->id,
            'is_default' => true,
            'width' => 816,
            'height' => 1056,
        ]);
        $template->elements()->create([
            'type' => 'text',
            'content' => '{ponencia}',
            'x' => 100,
            'y' => 300,
            'width' => 600,
            'height' => 40,
            'font_size' => 18,
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        $presentation = Presentation::create([
            'title' => 'Inteligencia Artificial en la Educación',
            'day' => '2026-08-05',
            'location' => 'Sala 3',
        ]);
        $presentation->authors()->attach($user->id, ['author_order' => 1, 'presented' => true, 'presented_at' => now()]);

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?type='.$type->id.'&event_type=presentation&event_id='.$presentation->id)
            ->assertOk()
            ->assertSee('Inteligencia Artificial en la Educación', false);

        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'presentation')
            ->where('event_id', $presentation->id)
            ->first();

        $this->assertNotNull($certificate);
        $this->assertSame('Inteligencia Artificial en la Educación', $certificate->metadata['ponencia'] ?? null);
        $this->assertSame('Ponente', $certificate->metadata['rol'] ?? null);
    }

    public function test_activity_carta_creates_separate_certificate(): void
    {
        $type = $this->cartaType();
        $user = $this->userWithPermission();

        $presentation = Presentation::create(['title' => 'Ponencia de prueba', 'day' => '2026-08-05']);
        $presentation->authors()->attach($user->id, ['author_order' => 1, 'presented' => true, 'presented_at' => now()]);

        $this->actingAs($user)->get('/constancias/invitacion/descargar?type='.$type->id)->assertOk();
        $this->actingAs($user)->get('/constancias/invitacion/descargar?type='.$type->id.'&event_type=presentation&event_id='.$presentation->id)->assertOk();

        $this->assertDatabaseCount('certificates', 2);

        $generic = Certificate::query()->where('event_type', 'event')->where('event_id', 0)->first();
        $activity = Certificate::query()->where('event_type', 'presentation')->where('event_id', $presentation->id)->first();

        $this->assertNotNull($generic);
        $this->assertNotNull($activity);
        $this->assertNotSame($generic->folio, $activity->folio);
    }

    public function test_multiple_carta_types_are_listed_on_my_certificates(): void
    {
        $this->cartaType();
        ParticipationType::create([
            'key' => 'carta_invitacion_speaker',
            'label' => 'Carta de Invitación - Speaker',
            'event_kind' => 'event',
            'kind' => 'carta',
            'role' => null,
            'is_active' => true,
            'manual_generable' => false,
        ]);
        $user = $this->userWithPermission();

        $this->actingAs($user)
            ->get('/constancias')
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->has('invitationLetters', 2)
                ->where('invitationLetters.0.label', 'Carta de Invitación')
                ->where('invitationLetters.1.label', 'Carta de Invitación - Speaker'));
    }

    public function test_invalid_activity_is_rejected(): void
    {
        $type = $this->cartaType();
        $user = $this->userWithPermission();

        $presentation = Presentation::create(['title' => 'Ponencia ajena', 'day' => '2026-08-05']);

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?type='.$type->id.'&event_type=presentation&event_id='.$presentation->id)
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }
}
