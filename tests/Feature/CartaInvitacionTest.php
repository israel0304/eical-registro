<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Permission;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartaInvitacionTest extends TestCase
{
    use RefreshDatabase;

    private function invitationTemplate(Role $role, bool $active = true): CertificateTemplate
    {
        $template = CertificateTemplate::create([
            'name' => 'Carta Invitación '.$role->name,
            'description' => null,
            'kind' => 'invitation',
            'role_id' => $role->id,
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

    private function userWithRole(): User
    {
        $role = $this->role('Ponente', 2);
        $this->roleWithPermissions($role);

        $user = User::factory()->create([
            'first_name' => 'María',
            'last_name' => 'López',
            'affiliation' => 'Cinvestav',
            'country' => 'México',
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_user_with_active_role_template_can_download_invitation_letter(): void
    {
        $role = $this->role('Ponente', 2);
        $this->invitationTemplate($role);
        $user = $this->userWithRole();

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('María', false);

        $this->assertDatabaseHas('certificates', [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'event_type' => 'event',
            'event_id' => 0,
        ]);

        $certificate = Certificate::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($certificate->downloaded_at);
        $this->assertSame('Ponente', $certificate->metadata['rol'] ?? null);
    }

    public function test_downloading_twice_returns_same_folio(): void
    {
        $role = $this->role('Ponente', 2);
        $this->invitationTemplate($role);
        $user = $this->userWithRole();

        $url = '/constancias/invitacion/descargar?role='.$role->id;
        $this->actingAs($user)->get($url)->assertOk();
        $first = Certificate::query()->where('user_id', $user->id)->first();

        $this->actingAs($user)->get($url)->assertOk();

        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame($first->folio, Certificate::query()->where('user_id', $user->id)->first()->folio);
    }

    public function test_role_without_active_template_cannot_download_invitation_letter(): void
    {
        $role = $this->role('Ponente', 2);
        $user = $this->userWithRole();

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_inactive_template_blocks_download(): void
    {
        $role = $this->role('Ponente', 2);
        $this->invitationTemplate($role, active: false);
        $user = $this->userWithRole();

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_role_without_role_template_uses_generic_template(): void
    {
        $generic = $this->genericInvitationTemplate();
        $role = $this->role('Ponente', 2);
        $user = $this->userWithRole();

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('María', false);

        $this->assertDatabaseHas('certificates', [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'template_id' => $generic->id,
        ]);

        $this->assertSame($generic->id, Certificate::query()->where('user_id', $user->id)->first()->template_id);
    }

    public function test_role_template_takes_precedence_over_generic(): void
    {
        $this->genericInvitationTemplate();
        $role = $this->role('Ponente', 2);
        $specific = $this->invitationTemplate($role);
        $user = $this->userWithRole();

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertOk();

        $this->assertSame($specific->id, Certificate::query()->where('user_id', $user->id)->first()->template_id);
    }

    public function test_my_certificates_lists_generic_letter_when_no_role_template(): void
    {
        $this->genericInvitationTemplate();
        $role = $this->role('Ponente', 2);
        $user = $this->userWithRole();

        $this->actingAs($user)
            ->get('/constancias')
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->has('invitationLetters', 1)
                ->where('invitationLetters.0.label', 'Carta de Invitación - Ponente'));
    }

    public function test_carta_with_presentation_includes_work_title(): void
    {
        $role = $this->role('Ponente', 2);
        $template = $this->invitationTemplate($role);
        $template->elements()->delete();
        $template->elements()->create([
            'type' => 'text',
            'content' => '{titulo_actividad}',
            'x' => 100,
            'y' => 300,
            'width' => 600,
            'height' => 60,
            'font_size' => 18,
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        $user = $this->userWithRole();

        $presentation = Presentation::create([
            'title' => 'Inteligencia Artificial en la Educación',
            'day' => '2026-08-05',
            'location' => 'Sala 3',
        ]);
        $presentation->authors()->attach($user->id, ['author_order' => 1, 'presented' => true, 'presented_at' => now()]);

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertOk()
            ->assertSee('Inteligencia Artificial en la Educación', false);

        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'event')
            ->first();

        $this->assertNotNull($certificate);
        $this->assertSame('Inteligencia Artificial en la Educación', $certificate->metadata['trabajos'][0] ?? null);
        $this->assertSame('Ponente', $certificate->metadata['rol'] ?? null);
    }

    public function test_carta_evento_shows_assigned_activity_title(): void
    {
        $role = $this->role('Ponente', 2);
        $template = $this->invitationTemplate($role);
        $template->elements()->delete();
        $template->elements()->create([
            'type' => 'text',
            'content' => '{evento}',
            'x' => 100,
            'y' => 300,
            'width' => 600,
            'height' => 60,
            'font_size' => 18,
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        $user = $this->userWithRole();

        $presentation = Presentation::create([
            'title' => 'Modelización matemática de caída libre',
            'day' => '2026-08-05',
            'location' => 'Sala 3',
        ]);
        $presentation->authors()->attach($user->id, ['author_order' => 1, 'presented' => false]);

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertOk()
            ->assertSee('Modelización matemática de caída libre', false);

        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'event')
            ->first();

        $this->assertSame('Modelización matemática de caída libre', $certificate->metadata['evento'] ?? null);
        $this->assertSame('Modelización matemática de caída libre', $certificate->metadata['trabajos'][0] ?? null);
    }

    public function test_carta_fecha_is_user_registration_date(): void
    {
        $role = $this->role('Ponente', 2);
        $template = $this->invitationTemplate($role);
        $template->elements()->delete();
        $template->elements()->create([
            'type' => 'text',
            'content' => '{fecha}',
            'x' => 100,
            'y' => 300,
            'width' => 600,
            'height' => 60,
            'font_size' => 18,
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        $user = $this->userWithRole();
        $user->forceFill(['created_at' => '2026-07-02 10:00:00'])->save();

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertOk()
            ->assertSee('2 de julio de 2026', false);

        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'event')
            ->first();

        $this->assertSame('2 de julio de 2026', $certificate->metadata['fecha'] ?? null);
    }

    public function test_carta_autores_includes_authors_when_not_presented(): void
    {
        $role = $this->role('Ponente', 2);
        $template = $this->invitationTemplate($role);
        $template->elements()->delete();
        $template->elements()->create([
            'type' => 'text',
            'content' => '{autores}',
            'x' => 100,
            'y' => 300,
            'width' => 600,
            'height' => 60,
            'font_size' => 18,
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        $user = $this->userWithRole();

        $coauthor = User::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
        ]);
        $coauthor->roles()->sync([$role->id]);

        $presentation = Presentation::create([
            'title' => 'Modelización matemática de caída libre',
            'day' => '2026-08-05',
            'location' => 'Sala 3',
        ]);
        $presentation->authors()->attach($user->id, ['author_order' => 1, 'presented' => false]);
        $presentation->authors()->attach($coauthor->id, ['author_order' => 2, 'presented' => false]);

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertOk()
            ->assertSee('María López', false)
            ->assertSee('Juan Pérez', false);

        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'event')
            ->first();

        $autores = $certificate->metadata['autores'] ?? '';
        $this->assertStringContainsString('María López', $autores);
        $this->assertStringContainsString('Juan Pérez', $autores);
    }

    public function test_carta_metadata_refreshes_on_redownload(): void
    {
        $role = $this->role('Ponente', 2);
        $template = $this->invitationTemplate($role);
        $template->elements()->delete();
        $template->elements()->create([
            'type' => 'text',
            'content' => '{evento}',
            'x' => 100,
            'y' => 300,
            'width' => 600,
            'height' => 60,
            'font_size' => 18,
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        $user = $this->userWithRole();

        $url = '/constancias/invitacion/descargar?role='.$role->id;
        $this->actingAs($user)->get($url)->assertOk();

        $certificate = Certificate::query()->where('user_id', $user->id)->where('event_type', 'event')->first();
        $this->assertSame('', $certificate->metadata['evento'] ?? '');

        $presentation = Presentation::create([
            'title' => 'Modelización matemática de caída libre',
            'day' => '2026-08-05',
            'location' => 'Sala 3',
        ]);
        $presentation->authors()->attach($user->id, ['author_order' => 1, 'presented' => false]);

        $this->actingAs($user)->get($url)->assertOk();

        $certificate->refresh();
        $this->assertSame('Modelización matemática de caída libre', $certificate->metadata['evento'] ?? null);
        $this->assertNotNull($certificate->folio);
    }

    public function test_carta_image_elements_render_with_contain_fit(): void
    {
        $role = $this->role('Ponente', 2);
        $template = $this->invitationTemplate($role);
        $template->elements()->delete();
        $template->elements()->create([
            'type' => 'image',
            'content' => '/storage/invitation-images/logo.png',
            'x' => 100,
            'y' => 100,
            'width' => 200,
            'height' => 200,
            'z_index' => 1,
        ]);

        $user = $this->userWithRole();

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertOk()
            ->assertSee('object-fit:contain', false);
    }

    public function test_carta_pdf_embeds_images_as_data_uri(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('invitation-images/logo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));

        $role = $this->role('Ponente', 2);
        $template = $this->invitationTemplate($role);
        $template->elements()->create([
            'type' => 'image',
            'content' => '/storage/invitation-images/logo.png',
            'x' => 100,
            'y' => 100,
            'width' => 200,
            'height' => 200,
            'z_index' => 2,
        ]);

        $user = $this->userWithRole();

        $this->actingAs($user)
            ->get('/constancias/invitacion/descargar?role='.$role->id)
            ->assertOk()
            ->assertSee('data:image/png;base64,', false);

        $certificate = Certificate::where('user_id', $user->id)->firstOrFail();

        $response = $this->actingAs($user)
            ->get('/constancias/'.$certificate->id.'/pdf');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename=constancia_'.$certificate->folio.'.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_multiple_roles_are_listed_on_my_certificates(): void
    {
        $ponente = $this->role('Ponente', 2);
        $instructor = $this->role('Instructor', 4);
        $this->invitationTemplate($ponente);
        $this->invitationTemplate($instructor);

        $user = $this->userWithRole();
        $user->roles()->sync([$ponente->id, $instructor->id]);

        $this->actingAs($user)
            ->get('/constancias')
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->has('invitationLetters', 2)
                ->where('invitationLetters.0.label', 'Carta de Invitación - Ponente')
                ->where('invitationLetters.1.label', 'Carta de Invitación - Instructor'));
    }
}
