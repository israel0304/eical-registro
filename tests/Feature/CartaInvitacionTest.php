<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Permission;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
