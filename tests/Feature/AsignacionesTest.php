<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Permission;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsignacionesTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        $user = User::factory()->create([
            'first_name' => 'Moderador',
            'last_name' => 'Principal',
        ]);

        $role = Role::firstOrCreate(['name' => 'Moderador']);
        $permission = Permission::firstOrCreate(['key' => 'asignaciones.view'], [
            'module' => 'asignaciones',
            'label' => 'Ver mis asignaciones',
        ]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
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

    private function workshopFor(User $creator, array $overrides = []): Workshop
    {
        return Workshop::create(array_merge([
            'name' => 'Taller de prueba',
            'description' => 'Descripción del taller.',
            'capacity' => 15,
            'location' => 'Auditorio A',
            'day' => '2026-10-05',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'created_by' => $creator->id,
        ], $overrides));
    }

    private function presentationFor(array $overrides = []): Presentation
    {
        return Presentation::create(array_merge([
            'title' => 'Ponencia de prueba',
            'abstract' => 'Resumen de la ponencia.',
            'discipline' => 'Ciencias de la información',
            'keywords' => 'bibliotecas, lectura',
            'location' => 'Sala B',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ], $overrides));
    }

    private function conferenceFor(User $creator, array $overrides = []): Conference
    {
        return Conference::create(array_merge([
            'title' => 'Conferencia de prueba',
            'kind' => 'magistral',
            'description' => 'Descripción de la conferencia.',
            'location' => 'Aula Magna',
            'day' => '2026-10-07',
            'start_time' => '11:00',
            'end_time' => '12:00',
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_requires_authentication_and_permission(): void
    {
        $this->get('/mis-asignaciones/imprimir')->assertRedirect(route('login'));
        $this->get('/mis-asignaciones/imprimir/pdf')->assertRedirect(route('login'));

        $user = $this->moderator();
        $user->roles()->sync([]);

        $this->actingAs($user)
            ->get('/mis-asignaciones/imprimir')
            ->assertForbidden();
    }

    public function test_print_shows_assignments_with_participants_and_semblanzas(): void
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

        $this->actingAs($moderator)
            ->get('/mis-asignaciones/imprimir')
            ->assertOk()
            ->assertSee($workshop->name)
            ->assertSee($presentation->title)
            ->assertSee($conference->title)
            ->assertSee('Ana Instructora')
            ->assertSee('Beto Autor')
            ->assertSee('Carla Conferencista')
            ->assertSee('Semblanza de prueba del participante.')
            ->assertSee('Conferencia · Magistral')
            ->assertSee('Ciencias de la información')
            ->assertSee('Moderador Principal');
    }

    public function test_print_orders_assignments_chronologically(): void
    {
        $moderator = $this->moderator();
        $user = $this->participant();

        $later = $this->workshopFor($moderator, [
            'name' => 'Taller del Día Dos',
            'day' => '2026-10-06',
            'start_time' => '08:00',
        ]);
        $later->moderators()->attach($moderator->id);
        $later->instructors()->attach($user->id);

        $earlier = $this->workshopFor($moderator, [
            'name' => 'Taller del Día Uno',
            'day' => '2026-10-05',
            'start_time' => '10:00',
        ]);
        $earlier->moderators()->attach($moderator->id);
        $earlier->instructors()->attach($user->id);

        $this->actingAs($moderator)
            ->get('/mis-asignaciones/imprimir')
            ->assertOk()
            ->assertSeeInOrder(['Taller del Día Uno', 'Taller del Día Dos']);
    }

    public function test_print_pdf_downloads_document(): void
    {
        $moderator = $this->moderator();
        $instructor = $this->participant();
        $workshop = $this->workshopFor($moderator);
        $workshop->instructors()->attach($instructor->id);
        $workshop->moderators()->attach($moderator->id);

        $response = $this->actingAs($moderator)->get('/mis-asignaciones/imprimir/pdf');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename=mis_asignaciones_'.date('Y-m-d').'.pdf');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_print_shows_empty_state_without_assignments(): void
    {
        $moderator = $this->moderator();

        $this->actingAs($moderator)
            ->get('/mis-asignaciones/imprimir')
            ->assertOk()
            ->assertSee('No tienes actividades asignadas como moderador actualmente.');
    }
}
