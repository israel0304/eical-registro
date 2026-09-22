<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeradorAsignacionesAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => config('roles.super_admin')]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function moderator(): User
    {
        return User::factory()->create([
            'first_name' => 'Moderador',
            'last_name' => 'Principal',
        ]);
    }

    private function participant(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'first_name' => 'Participante',
            'last_name' => 'De Prueba',
            'affiliation' => 'Institución de prueba',
            'semblanza' => 'Semblanza de prueba del participante.',
        ], $overrides));
    }

    private function workshopFor(User $creator): Workshop
    {
        $workshop = Workshop::create([
            'name' => 'Taller de asignaciones',
            'description' => 'Descripción del taller.',
            'capacity' => 15,
            'location' => 'Auditorio A',
            'day' => '2026-10-05',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'created_by' => $creator->id,
        ]);

        return $workshop;
    }

    private function presentationFor(): Presentation
    {
        return Presentation::create([
            'title' => 'Ponencia de asignaciones',
            'abstract' => 'Resumen de la ponencia.',
            'discipline' => 'Ciencias de la información',
            'keywords' => 'bibliotecas, lectura',
            'location' => 'Sala B',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
    }

    private function conferenceFor(User $creator): Conference
    {
        return Conference::create([
            'title' => 'Conferencia de asignaciones',
            'kind' => 'magistral',
            'description' => 'Descripción de la conferencia.',
            'location' => 'Aula Magna',
            'day' => '2026-10-07',
            'start_time' => '11:00',
            'end_time' => '12:00',
            'created_by' => $creator->id,
        ]);
    }

    private function moderatorWithAllAssignments(): User
    {
        $moderator = $this->moderator();

        $instructor = $this->participant(['first_name' => 'Ana', 'last_name' => 'Instructora']);
        $workshop = $this->workshopFor($moderator);
        $workshop->instructors()->attach($instructor->id);
        $workshop->moderators()->attach($moderator->id);

        $author = $this->participant(['first_name' => 'Beto', 'last_name' => 'Autor']);
        $presentation = $this->presentationFor();
        $presentation->authors()->attach($author->id, ['author_order' => 1]);
        $presentation->moderators()->attach($moderator->id);

        $speaker = $this->participant(['first_name' => 'Carla', 'last_name' => 'Conferencista']);
        $conference = $this->conferenceFor($moderator);
        $conference->members()->attach($speaker->id, ['role' => 'speaker']);
        $conference->members()->attach($moderator->id, ['role' => 'moderator']);

        return $moderator;
    }

    public function test_admin_can_view_moderator_assignments_with_detail(): void
    {
        $moderator = $this->moderatorWithAllAssignments();

        $this->actingAs($this->admin())
            ->get('/admin/constancias/moderadores/'.$moderator->id.'/asignaciones')
            ->assertOk()
            ->assertSee('Taller de asignaciones')
            ->assertSee('Ponencia de asignaciones')
            ->assertSee('Conferencia de asignaciones')
            ->assertSee('Ana Instructora')
            ->assertSee('Beto Autor')
            ->assertSee('Carla Conferencista')
            ->assertSee('Semblanza de prueba del participante.')
            ->assertSee('Conferencia · Magistral')
            ->assertSee('Ciencias de la información')
            ->assertSee('Moderador Principal');
    }

    public function test_admin_view_hides_own_semblanza_but_keeps_others(): void
    {
        $moderator = $this->moderator();
        $moderator->update(['semblanza' => 'Semblanza propia del moderador.']);

        $instructor = $this->participant(['first_name' => 'Ana', 'last_name' => 'Instructora']);
        $workshop = $this->workshopFor($moderator);
        $workshop->instructors()->attach($instructor->id);
        $workshop->moderators()->attach($moderator->id);

        $this->actingAs($this->admin())
            ->get('/admin/constancias/moderadores/'.$moderator->id.'/asignaciones')
            ->assertOk()
            ->assertDontSee('Semblanza propia del moderador.')
            ->assertSee('Semblanza de prueba del participante.');
    }

    public function test_admin_can_download_moderator_assignments_pdf(): void
    {
        $moderator = $this->moderatorWithAllAssignments();

        $response = $this->actingAs($this->admin())
            ->get('/admin/constancias/moderadores/'.$moderator->id.'/asignaciones/pdf');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename=asignaciones_moderador_moderador-principal_'.date('Y-m-d').'.pdf');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_admin_index_lists_total_assignment_count(): void
    {
        $moderator = $this->moderatorWithAllAssignments();

        $this->actingAs($this->admin())
            ->get('/admin/constancias/moderadores')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Constancias/Moderadores/Index')
                ->where('moderators.0.full_name', 'Moderador Principal')
                ->where('moderators.0.assignment_count', 3)
                ->where('moderators.0.assignment_titles', ['Ponencia de asignaciones', 'Taller de asignaciones', 'Conferencia de asignaciones']));
    }

    public function test_without_admin_permission_is_forbidden(): void
    {
        $moderator = $this->moderator();
        $plainUser = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $plainUser->roles()->sync([$role->id]);

        $this->actingAs($plainUser)
            ->get('/admin/constancias/moderadores/'.$moderator->id.'/asignaciones')
            ->assertForbidden();

        $this->actingAs($plainUser)
            ->get('/admin/constancias/moderadores/'.$moderator->id.'/asignaciones/pdf')
            ->assertForbidden();
    }
}
