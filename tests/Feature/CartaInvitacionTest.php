<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\ParticipationType;
use App\Models\Permission;
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

        $role = Role::firstOrCreate(['name' => $roleName]);
        $role->permissions()->sync([$permission->id, $download->id]);

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
}
