<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\ParticipationType;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipationTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'key' => 'evento_asistencia',
            'label' => 'Asistente al evento',
            'event_kind' => 'event',
            'kind' => null,
            'role' => null,
            'is_active' => true,
        ], $overrides);
    }

    private function workshopFor(User $user): Workshop
    {
        return Workshop::create([
            'name' => 'Taller de prueba',
            'description' => 'test',
            'capacity' => 10,
            'location' => 'Cinvestav',
            'day' => '2026-08-03',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'created_by' => $user->id,
        ]);
    }

    public function test_admin_can_create_event_type(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/constancias/tipos', $this->storePayload())
            ->assertRedirect();

        $this->assertDatabaseHas('participation_types', [
            'key' => 'evento_asistencia',
            'event_kind' => 'event',
        ]);
    }

    public function test_cannot_create_duplicate_combination(): void
    {
        $this->actingAs($this->admin());
        ParticipationType::create($this->storePayload());

        $this->post('/admin/constancias/tipos', $this->storePayload(['key' => 'otra_clave']))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseCount('participation_types', 1);
    }

    public function test_cannot_use_role_not_allowed_for_event_kind(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/constancias/tipos', $this->storePayload([
            'key' => 'workshop_bad',
            'event_kind' => 'workshop',
            'role' => 'moderator',
        ]))->assertSessionHasErrors('role');

        $this->assertDatabaseCount('participation_types', 0);
    }

    public function test_event_type_cannot_have_role(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/constancias/tipos', $this->storePayload(['role' => 'speaker']))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseCount('participation_types', 0);
    }

    public function test_admin_can_edit_type(): void
    {
        $this->actingAs($this->admin());
        $type = ParticipationType::create($this->storePayload());

        $this->put('/admin/constancias/tipos/'.$type->id, [
            'key' => 'evento_asistencia',
            'label' => 'Asistente al evento (editado)',
            'event_kind' => 'event',
            'kind' => null,
            'role' => null,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('participation_types', [
            'id' => $type->id,
            'label' => 'Asistente al evento (editado)',
        ]);
    }

    public function test_admin_can_delete_type_without_references(): void
    {
        $this->actingAs($this->admin());
        $type = ParticipationType::create($this->storePayload());

        $this->delete('/admin/constancias/tipos/'.$type->id)->assertRedirect();

        $this->assertDatabaseMissing('participation_types', ['id' => $type->id]);
    }

    public function test_cannot_delete_type_with_certificates(): void
    {
        $user = $this->admin();
        $type = ParticipationType::create($this->storePayload());

        Certificate::create([
            'folio' => 'EICAL-TEST-1',
            'user_id' => $user->id,
            'participation_type_id' => $type->id,
            'event_id' => $this->workshopFor($user)->id,
            'event_type' => Workshop::class,
        ]);

        $this->delete('/admin/constancias/tipos/'.$type->id)->assertRedirect();

        $this->assertDatabaseHas('participation_types', ['id' => $type->id]);
    }

    public function test_cannot_delete_type_with_templates(): void
    {
        $this->actingAs($this->admin());
        $type = ParticipationType::create($this->storePayload());
        $type->templates()->create([
            'name' => 'Plantilla de prueba',
            'participation_type_id' => $type->id,
            'is_default' => true,
        ]);

        $this->delete('/admin/constancias/tipos/'.$type->id)->assertRedirect();

        $this->assertDatabaseHas('participation_types', ['id' => $type->id]);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::firstOrCreate(['name' => 'Asistente'])->id]);
        $this->actingAs($user);

        $this->post('/admin/constancias/tipos', $this->storePayload())->assertForbidden();
    }

    public function test_index_passes_catalog_with_event_option(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/constancias/tipos')
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Tipos/Index')
                ->has('catalog.event_kinds', 5)
                ->where('catalog.event_kinds.event', 'Evento'));
    }

    public function test_store_persists_manual_generable(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/constancias/tipos', $this->storePayload([
            'key' => 'staff_test',
            'event_kind' => 'staff',
            'manual_generable' => true,
        ]))->assertRedirect();

        $this->assertDatabaseHas('participation_types', [
            'key' => 'staff_test',
            'event_kind' => 'staff',
            'manual_generable' => true,
        ]);
    }

    public function test_admin_can_create_carta_kind(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/constancias/tipos', $this->storePayload([
            'key' => 'carta_invitacion_speaker',
            'label' => 'Carta de Invitación - Speaker',
            'kind' => 'carta',
        ]))->assertRedirect();

        $this->assertDatabaseHas('participation_types', [
            'key' => 'carta_invitacion_speaker',
            'event_kind' => 'event',
            'kind' => 'carta',
        ]);
    }
}
