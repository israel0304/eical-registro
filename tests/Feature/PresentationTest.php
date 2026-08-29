<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresentationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::create(['name' => 'Administrator', 'is_active' => true])->id]);

        return $user;
    }

    private function ponente(): User
    {
        $role = Role::create(['name' => 'Ponente', 'is_active' => true]);
        $role->permissions()->sync([
            Permission::updateOrCreate(
                ['key' => 'presentations.my'],
                ['module' => 'Ponencias', 'label' => 'Ver mis ponencias'],
            )->id,
        ]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        $presentation = Presentation::create([
            'title' => 'Ponencia de prueba',
            'abstract' => 'Resumen',
        ]);
        $presentation->authors()->attach($user->id, ['author_order' => 1]);

        return $user;
    }

    public function test_store_allows_empty_discipline(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Administrator', 'is_active' => true]);
        $user->roles()->sync([$role->id]);
        Role::create(['name' => 'Ponente', 'is_active' => true]);

        $this->actingAs($user)
            ->post('/presentations', [
                'title' => 'Ponencia sin disciplina',
                'abstract' => 'Resumen',
                'discipline' => null,
                'author_ids' => [$user->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('presentations.index'));

        $this->assertDatabaseHas('presentations', [
            'title' => 'Ponencia sin disciplina',
            'discipline' => null,
        ]);
    }

    public function test_admin_update_allows_empty_discipline(): void
    {
        $admin = $this->admin();
        $presentation = Presentation::create([
            'title' => 'Ponencia',
            'abstract' => 'Resumen',
            'discipline' => 'STEM',
        ]);

        $this->actingAs($admin)
            ->put('/presentations/'.$presentation->id, [
                'title' => 'Ponencia editada',
                'abstract' => 'Resumen',
                'discipline' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('presentations.index'));

        $this->assertDatabaseHas('presentations', [
            'id' => $presentation->id,
            'title' => 'Ponencia editada',
            'discipline' => null,
        ]);
    }

    public function test_author_update_allows_empty_discipline(): void
    {
        $user = $this->ponente();
        $presentation = $user->presentations()->first();

        $this->actingAs($user)
            ->put('/presentations/'.$presentation->id, [
                'title' => 'Ponencia del autor',
                'abstract' => 'Resumen',
                'discipline' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('presentations.index'));

        $this->assertDatabaseHas('presentations', [
            'id' => $presentation->id,
            'title' => 'Ponencia del autor',
            'discipline' => null,
        ]);
    }
}
