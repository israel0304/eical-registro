<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresentationPresentedTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_presented_permission_can_mark_author_as_presented(): void
    {
        $user = $this->userWithPermission('presentations.presented');
        [$presentation, $author] = $this->presentationWithAuthor();

        $this->actingAs($user)
            ->putJson("/presentations/{$presentation->id}", [
                'authors_presented' => [
                    ['user_id' => $author->id, 'presented' => true],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('presentation_authors', [
            'presentation_id' => $presentation->id,
            'user_id' => $author->id,
            'presented' => 1,
        ]);
    }

    public function test_user_with_presented_permission_can_unmark_author(): void
    {
        $user = $this->userWithPermission('presentations.presented');
        [$presentation, $author] = $this->presentationWithAuthor(true);

        $this->actingAs($user)
            ->putJson("/presentations/{$presentation->id}", [
                'authors_presented' => [
                    ['user_id' => $author->id, 'presented' => false],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('presentation_authors', [
            'presentation_id' => $presentation->id,
            'user_id' => $author->id,
            'presented' => 0,
        ]);
    }

    public function test_user_with_edit_permission_but_without_presented_gets_forbidden(): void
    {
        $user = $this->userWithPermission('presentations.edit');
        [$presentation, $author] = $this->presentationWithAuthor();

        $this->actingAs($user)
            ->putJson("/presentations/{$presentation->id}", [
                'authors_presented' => [
                    ['user_id' => $author->id, 'presented' => true],
                ],
            ])
            ->assertForbidden();
    }

    public function test_assigned_moderator_with_presented_permission_can_mark_author(): void
    {
        $user = $this->userWithPermission('presentations.presented');
        [$presentation, $author] = $this->presentationWithAuthor();
        $presentation->moderators()->attach($user->id);

        $this->actingAs($user)
            ->putJson("/presentations/{$presentation->id}", [
                'authors_presented' => [
                    ['user_id' => $author->id, 'presented' => true],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('presentation_authors', [
            'presentation_id' => $presentation->id,
            'user_id' => $author->id,
            'presented' => 1,
        ]);
    }

    public function test_assigned_moderator_without_presented_permission_gets_forbidden(): void
    {
        $user = $this->userWithoutPermissions();
        [$presentation, $author] = $this->presentationWithAuthor();
        $presentation->moderators()->attach($user->id);

        $this->actingAs($user)
            ->putJson("/presentations/{$presentation->id}", [
                'authors_presented' => [
                    ['user_id' => $author->id, 'presented' => true],
                ],
            ])
            ->assertForbidden();
    }

    public function test_regular_user_without_permission_gets_forbidden(): void
    {
        $user = $this->userWithoutPermissions();
        [$presentation, $author] = $this->presentationWithAuthor();

        $this->actingAs($user)
            ->putJson("/presentations/{$presentation->id}", [
                'authors_presented' => [
                    ['user_id' => $author->id, 'presented' => true],
                ],
            ])
            ->assertForbidden();
    }

    private function userWithPermission(string $key): User
    {
        $permission = Permission::updateOrCreate(
            ['key' => $key],
            ['module' => 'Ponencias', 'label' => $key],
        );

        $role = Role::create(['name' => 'Rol '.$key]);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function userWithoutPermissions(): User
    {
        $user = User::factory()->create();
        $user->roles()->detach();

        return $user;
    }

    private function presentationWithAuthor(bool $presented = false): array
    {
        $presentation = Presentation::create([
            'title' => 'Ponencia de prueba',
            'abstract' => 'Resumen de prueba',
        ]);

        $author = User::factory()->create();
        $presentation->authors()->attach($author->id, [
            'author_order' => 1,
            'presented' => $presented,
        ]);

        return [$presentation, $author];
    }
}
