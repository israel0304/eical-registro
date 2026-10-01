<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ParticipationType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComiteConstanciaTest extends TestCase
{
    use RefreshDatabase;

    private function comiteUser(array $permissions = ['constancias.view', 'constancias.download'], string $roleName = 'Comité'): User
    {
        $user = User::factory()->create([
            'first_name' => 'Miembro',
            'last_name' => 'Comité',
        ]);

        $role = Role::firstOrCreate(['name' => $roleName]);

        foreach ($permissions as $key) {
            Permission::firstOrCreate(['key' => $key], [
                'module' => 'constancias',
                'label' => $key,
            ]);
        }

        $role->permissions()->sync(
            Permission::whereIn('key', $permissions)->pluck('id')
        );

        // sync y no syncWithoutDetaching: el factory asigna un rol aleatorio
        // (Administrator, Ponente, Asistente) y contaminaría a los usuarios de
        // esta prueba con permisos de administrador.
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function comiteType(): ParticipationType
    {
        return ParticipationType::query()->where('key', 'comite')->firstOrFail();
    }

    public function test_migration_creates_active_manual_comite_type_and_template(): void
    {
        $type = $this->comiteType();

        $this->assertSame('Miembro del comité', $type->label);
        $this->assertSame('comite', $type->event_kind);
        $this->assertNull($type->kind);
        $this->assertNull($type->role);
        $this->assertTrue((bool) $type->is_active);
        $this->assertTrue((bool) $type->manual_generable);

        $template = CertificateTemplate::where('participation_type_id', $type->id)
            ->where('kind', 'certificate')
            ->firstOrFail();

        $this->assertTrue((bool) $template->is_default);
        $this->assertSame('Constancia de Miembro del Comité', $template->name);
        $this->assertTrue($template->elements()->where('variable', 'nombre')->exists());
        $this->assertTrue($template->elements()->where('type', 'qr')->exists());
    }

    public function test_event_kinds_config_includes_comite(): void
    {
        $kinds = config('participation.event_kinds');

        $this->assertArrayHasKey('comite', $kinds);
        $this->assertSame('Comité', $kinds['comite']);
        $this->assertArrayHasKey('comite', config('participation.role_rules'));
    }

    public function test_comite_member_downloads_own_constancia_without_activation(): void
    {
        $type = $this->comiteType();
        $user = $this->comiteUser();

        $this->actingAs($user)
            ->get('/constancias/comite/constancia')
            ->assertOk();

        $this->assertDatabaseHas('certificates', [
            'user_id' => $user->id,
            'participation_type_id' => $type->id,
            'event_type' => 'comite',
            'event_id' => 0,
        ]);

        $this->actingAs($user)
            ->get('/constancias/comite/constancia')
            ->assertOk();

        $this->assertSame(1, Certificate::query()->where('user_id', $user->id)->count());
    }

    public function test_generated_constancia_uses_the_default_template_and_metadata(): void
    {
        $this->comiteType();
        $user = $this->comiteUser();

        $this->actingAs($user)->get('/constancias/comite/constancia')->assertOk();

        $certificate = Certificate::query()->where('user_id', $user->id)->sole();

        $this->assertNotNull($certificate->template_id);
        $this->assertSame('Miembro Comité', $certificate->metadata['nombre'] ?? null);
        $this->assertSame('Miembro del comité', $certificate->metadata['rol'] ?? null);
        $this->assertSame('Miembro del comité', $certificate->metadata['tipo_participacion'] ?? null);
        $this->assertNotEmpty($certificate->folio);
    }

    public function test_card_is_only_shown_to_comite_members(): void
    {
        $this->comiteType();
        $user = $this->comiteUser();

        $this->actingAs($user)
            ->get('/constancias')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->where('isComite', true));

        $outsider = $this->comiteUser(['constancias.view', 'constancias.download'], 'Asistente');

        $this->actingAs($outsider)
            ->get('/constancias')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Index')
                ->where('isComite', false));
    }

    public function test_non_comite_member_cannot_download(): void
    {
        $this->comiteType();
        $outsider = $this->comiteUser(['constancias.view', 'constancias.download'], 'Asistente');

        $this->actingAs($outsider)
            ->get('/constancias/comite/constancia')
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_download_requires_constancias_download_permission(): void
    {
        $this->comiteType();
        $user = $this->comiteUser(['constancias.view']);

        $this->actingAs($user)
            ->get('/constancias/comite/constancia')
            ->assertForbidden();

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_role_detection_is_accent_and_case_insensitive(): void
    {
        $this->comiteType();

        $this->assertTrue($this->comiteUser(['constancias.view'], 'comite')->isComite());
        $this->assertTrue($this->comiteUser(['constancias.view'], 'COMITÉ')->isComite());
        $this->assertFalse($this->comiteUser(['constancias.view'], 'Ponente')->isComite());
    }

    public function test_inactive_comite_type_blocks_generation(): void
    {
        $type = $this->comiteType();
        $user = $this->comiteUser();

        $type->update(['is_active' => false]);

        $this->actingAs($user)
            ->get('/constancias/comite/constancia')
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_admin_can_generate_comite_constancia_manually(): void
    {
        $type = $this->comiteType();
        $user = $this->comiteUser();

        $this->actingAs($this->admin())
            ->get('/admin/constancias/tipos/'.$type->id.'/usuario/'.$user->id.'/generar')
            ->assertOk();

        $this->assertDatabaseHas('certificates', [
            'user_id' => $user->id,
            'participation_type_id' => $type->id,
        ]);
    }
}
