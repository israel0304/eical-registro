<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\ParticipationType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConstanciaAdminGenerateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function participant(): User
    {
        return User::factory()->create();
    }

    private function manualType(): ParticipationType
    {
        return ParticipationType::create([
            'key' => 'staff',
            'label' => 'Personal de apoyo',
            'event_kind' => 'staff',
            'kind' => null,
            'role' => null,
            'is_active' => true,
            'manual_generable' => true,
        ]);
    }

    public function test_admin_can_generate_manual_certificate(): void
    {
        $admin = $this->admin();
        $user = $this->participant();
        $type = $this->manualType();

        $this->actingAs($admin)
            ->get("/admin/constancias/tipos/{$type->id}/usuario/{$user->id}/generar")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('Personal de apoyo', false);

        $this->assertDatabaseHas('certificates', [
            'user_id' => $user->id,
            'participation_type_id' => $type->id,
            'event_type' => 'staff',
            'event_id' => 0,
        ]);
    }

    public function test_generating_twice_returns_same_folio(): void
    {
        $admin = $this->admin();
        $user = $this->participant();
        $type = $this->manualType();

        $this->actingAs($admin)->get("/admin/constancias/tipos/{$type->id}/usuario/{$user->id}/generar")->assertOk();
        $first = Certificate::query()->where('user_id', $user->id)->first();

        $this->actingAs($admin)->get("/admin/constancias/tipos/{$type->id}/usuario/{$user->id}/generar")->assertOk();

        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame($first->folio, Certificate::query()->where('user_id', $user->id)->first()->folio);
    }

    public function test_cannot_generate_for_non_manual_type(): void
    {
        $admin = $this->admin();
        $user = $this->participant();
        $type = ParticipationType::create([
            'key' => 'taller',
            'label' => 'Asistente a taller',
            'event_kind' => 'workshop',
            'kind' => null,
            'role' => 'enrolled_attendance',
            'is_active' => true,
            'manual_generable' => false,
        ]);

        $this->actingAs($admin)
            ->get("/admin/constancias/tipos/{$type->id}/usuario/{$user->id}/generar")
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = $this->participant();
        $type = $this->manualType();
        $user->roles()->sync([Role::firstOrCreate(['name' => 'Asistente'])->id]);

        $this->actingAs($user)
            ->get("/admin/constancias/tipos/{$type->id}/usuario/{$user->id}/generar")
            ->assertForbidden();

        $this->assertDatabaseCount('certificates', 0);
    }
}
