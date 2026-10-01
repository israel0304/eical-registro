<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Conference;
use App\Models\ModeradorConstancia;
use App\Models\ParticipationType;
use App\Models\Permission;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use App\Services\CertificateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateMetadataRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function participant(): User
    {
        foreach (['constancias.view', 'constancias.download'] as $key) {
            Permission::firstOrCreate(['key' => $key], ['module' => 'constancias', 'label' => $key]);
        }

        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $role->permissions()->sync(
            Permission::whereIn('key', ['constancias.view', 'constancias.download'])->pluck('id')
        );

        $user = User::factory()->create([
            'first_name' => 'Nombre',
            'last_name' => 'Antiguo',
            'affiliation' => 'Institución Vieja',
            'country' => 'México',
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * Usa el tipo que ya traen las migraciones y, si no existe, lo crea con la
     * misma forma que le da el seeder de producción.
     */
    private function type(string $key): ParticipationType
    {
        $type = ParticipationType::query()->where('key', $key)->first();

        if ($type !== null) {
            return $type;
        }

        $specs = [
            'taller' => ['label' => 'Asistente a taller', 'event_kind' => 'workshop', 'role' => 'enrolled_attendance'],
            'ponencia' => ['label' => 'Ponente', 'event_kind' => 'presentation', 'role' => 'presented_author'],
            'evento_asistencia' => ['label' => 'Asistente al evento', 'event_kind' => 'event', 'role' => null],
        ];

        $this->assertArrayHasKey($key, $specs, "El tipo \"{$key}\" no existe y no hay definición de respaldo.");

        return ParticipationType::create(array_merge($specs[$key], [
            'key' => $key,
            'is_active' => true,
        ]));
    }

    private function templateFor(ParticipationType $type): CertificateTemplate
    {
        return CertificateTemplate::firstOrCreate(
            [
                'kind' => 'certificate',
                'participation_type_id' => $type->id,
                'name' => 'Plantilla de prueba',
            ],
            ['is_default' => true, 'width' => 1800, 'height' => 1200]
        );
    }

    private function workshop(string $name = 'Taller de prueba'): Workshop
    {
        return Workshop::create([
            'name' => $name,
            'description' => 'test',
            'capacity' => 20,
            'location' => 'Aula 1',
            'day' => '2026-09-23',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'created_by' => User::factory()->create()->id,
        ]);
    }

    private function presentation(string $title = 'Modelización matemática'): Presentation
    {
        return Presentation::create([
            'title' => $title,
            'abstract' => 'test',
            'location' => 'Auditorio',
            'day' => '2026-09-24',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'accepted',
        ]);
    }

    private function conference(string $title = 'Conferencia magistral'): Conference
    {
        return Conference::create([
            'title' => $title,
            'kind' => 'magistral',
            'description' => 'test',
            'location' => 'Auditorio',
            'day' => '2026-09-25',
            'start_time' => '09:00',
            'end_time' => '10:30',
            'created_by' => User::factory()->create()->id,
        ]);
    }

    private function enrollAndAttend(Workshop $workshop, User $user): void
    {
        WorkshopEnrollment::create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);

        Attendance::create([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'event_day' => $workshop->day,
            'registered_by' => $user->id,
        ]);
    }

    private function rename(User $user, string $first, string $last, string $affiliation = 'Institución Nueva'): void
    {
        $user->forceFill([
            'first_name' => $first,
            'last_name' => $last,
            'affiliation' => $affiliation,
        ])->save();
    }

    public function test_workshop_certificate_picks_up_a_name_change_on_redownload(): void
    {
        $user = $this->participant();
        $this->templateFor($this->type('taller'));
        $workshop = $this->workshop();
        $this->enrollAndAttend($workshop, $user);

        $this->actingAs($user)
            ->get(route('constancias.download', $workshop->id))
            ->assertOk();

        $certificate = Certificate::query()->sole();
        $this->assertSame('Nombre Antiguo', $certificate->metadata['nombre']);
        $folio = $certificate->folio;
        $this->assertNotNull($folio);

        $this->rename($user, 'Nombre', 'Nuevo');

        $this->actingAs($user)
            ->get(route('constancias.download', $workshop->id))
            ->assertOk();

        $certificate->refresh();
        $this->assertSame('Nombre Nuevo', $certificate->metadata['nombre']);
        $this->assertSame($folio, $certificate->folio, 'El folio no debe cambiar al refrescar.');
    }

    public function test_attendance_certificate_picks_up_a_name_change(): void
    {
        $user = $this->participant();
        $this->templateFor($this->type('evento_asistencia'));
        $renderer = app(CertificateRenderer::class);

        $this->assertNotNull($renderer->issueEvent($user));
        $this->assertSame('Nombre Antiguo', Certificate::query()->sole()->metadata['nombre']);

        $this->rename($user, 'Nombre', 'Nuevo', 'Universidad Nueva');

        $this->assertNotNull($renderer->issueEvent($user));

        $certificate = Certificate::query()->sole();
        $this->assertSame('Nombre Nuevo', $certificate->metadata['nombre']);
    }

    public function test_presentation_and_conference_certificates_pick_up_a_name_change(): void
    {
        $user = $this->participant();

        $this->templateFor($this->type('ponencia'));
        $presentation = $this->presentation();
        $presentation->authors()->attach($user->id, ['author_order' => 1, 'presented' => true]);

        $this->templateFor($this->type('conferencia_magistral'));
        $conference = $this->conference();
        $conference->members()->attach($user->id, [
            'role' => 'speaker',
            'activated' => true,
        ]);

        $this->actingAs($user)->get(route('constancias.ponencia.download', $presentation->id))->assertOk();
        $this->actingAs($user)->get(route('constancias.conferencia.download', $conference->id))->assertOk();

        $this->assertSame(2, Certificate::query()->count());
        $this->assertSame(
            ['Nombre Antiguo', 'Nombre Antiguo'],
            Certificate::query()->orderBy('id')->pluck('metadata')->map(fn ($m) => $m['nombre'])->all()
        );

        $this->rename($user, 'Nombre', 'Nuevo');

        $this->actingAs($user)->get(route('constancias.ponencia.download', $presentation->id))->assertOk();
        $this->actingAs($user)->get(route('constancias.conferencia.download', $conference->id))->assertOk();

        $this->assertSame(
            ['Nombre Nuevo', 'Nombre Nuevo'],
            Certificate::query()->orderBy('id')->pluck('metadata')->map(fn ($m) => $m['nombre'])->all()
        );
    }

    public function test_comite_and_moderator_certificates_pick_up_a_name_change(): void
    {
        $user = $this->participant();
        $comiteRole = Role::firstOrCreate(['name' => 'Comité']);
        $comiteRole->permissions()->sync(
            Permission::whereIn('key', ['constancias.view', 'constancias.download'])->pluck('id')
        );
        $user->roles()->sync([$comiteRole->id]);

        $this->templateFor($this->type('comite'));
        $this->templateFor($this->type('moderador'));

        $conference = $this->conference();
        $conference->members()->attach($user->id, [
            'role' => 'moderator',
            'activated' => true,
        ]);

        ModeradorConstancia::create([
            'user_id' => $user->id,
            'activated' => true,
            'activated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('constancias.comite.download'))->assertOk();
        $this->actingAs($user)->get(route('constancias.moderador.download'))->assertOk();

        $this->assertSame(2, Certificate::query()->count());

        $this->rename($user, 'Nombre', 'Nuevo', 'Universidad Nueva');

        $this->actingAs($user)->get(route('constancias.comite.download'))->assertOk();
        $this->actingAs($user)->get(route('constancias.moderador.download'))->assertOk();

        $comite = Certificate::query()->where('event_type', 'comite')->sole();
        $moderador = Certificate::query()
            ->where('event_type', 'conference')
            ->where('event_id', 0)
            ->sole();

        $this->assertSame('Nombre Nuevo', $comite->metadata['nombre']);
        $this->assertSame('Universidad Nueva', $comite->metadata['institucion']);
        $this->assertSame('Nombre Nuevo', $moderador->metadata['nombre']);
        $this->assertSame('Universidad Nueva', $moderador->metadata['institucion']);
    }

    public function test_coauthor_names_are_refreshed(): void
    {
        $coauthor = User::factory()->create(['first_name' => 'Coautor', 'last_name' => 'Viejo']);
        $user = $this->participant();

        foreach (['constancias.view', 'constancias.download'] as $key) {
            Permission::firstOrCreate(['key' => $key], ['module' => 'constancias', 'label' => $key]);
        }

        $role = Role::firstOrCreate(['name' => 'Ponente']);
        $role->permissions()->sync(
            Permission::whereIn('key', ['constancias.view', 'constancias.download'])->pluck('id')
        );
        $user->roles()->sync([$role->id]);

        $type = $this->type('ponencia');
        CertificateTemplate::firstOrCreate(
            [
                'kind' => 'invitation',
                'role_id' => $role->id,
                'name' => 'Plantilla carta',
            ],
            ['participation_type_id' => $type->id, 'is_default' => true, 'width' => 1800, 'height' => 1200]
        );

        $presentation = $this->presentation();
        $presentation->authors()->attach($user->id, ['author_order' => 1, 'presented' => true]);
        $presentation->authors()->attach($coauthor->id, ['author_order' => 2, 'presented' => true]);

        $this->actingAs($user)
            ->get(route('constancias.invitacion.ponencia.download', $presentation->id))
            ->assertOk();

        $carta = Certificate::query()->where('event_type', 'carta-presentation')->sole();
        $this->assertStringContainsString('Coautor Viejo', $carta->metadata['autores']);

        $coauthor->forceFill(['first_name' => 'Coautor', 'last_name' => 'Nuevo'])->save();

        $this->actingAs($user)
            ->get(route('constancias.invitacion.ponencia.download', $presentation->id))
            ->assertOk();

        $carta->refresh();
        $this->assertStringContainsString('Coautor Nuevo', $carta->metadata['autores']);
        $this->assertStringNotContainsString('Viejo', $carta->metadata['autores']);
    }

    public function test_activity_title_is_refreshed(): void
    {
        $user = $this->participant();
        $this->templateFor($this->type('taller'));
        $workshop = $this->workshop('Nombre Viejo del Taller');
        $this->enrollAndAttend($workshop, $user);

        $this->actingAs($user)->get(route('constancias.download', $workshop->id))->assertOk();
        $this->assertSame('Nombre Viejo del Taller', Certificate::query()->sole()->metadata['evento']);

        $workshop->update(['name' => 'Nombre Nuevo del Taller']);

        $this->actingAs($user)->get(route('constancias.download', $workshop->id))->assertOk();
        $this->assertSame('Nombre Nuevo del Taller', Certificate::query()->sole()->metadata['evento']);
    }

    public function test_command_refreshes_stale_metadata_without_creating_certificates(): void
    {
        $user = $this->participant();
        $this->templateFor($this->type('taller'));
        $workshop = $this->workshop();
        $this->enrollAndAttend($workshop, $user);

        $this->actingAs($user)->get(route('constancias.download', $workshop->id))->assertOk();

        $certificate = Certificate::query()->sole();
        $folio = $certificate->folio;

        // Se reproduce la metadata congelada de una constancia emitida antes
        // del cambio de nombre.
        $certificate->update([
            'metadata' => array_merge($certificate->metadata, ['nombre' => 'Nombre Antiguo']),
        ]);

        $this->rename($user, 'Nombre', 'Nuevo');

        $this->artisan('constancias:refrescar-metadata --user='.$user->id)
            ->assertSuccessful();

        $certificate->refresh();
        $this->assertSame('Nombre Nuevo', $certificate->metadata['nombre']);
        $this->assertSame($folio, $certificate->folio);
        $this->assertSame(1, Certificate::query()->count(), 'El comando no debe crear constancias.');
    }

    public function test_command_dry_run_does_not_write(): void
    {
        $user = $this->participant();
        $this->templateFor($this->type('taller'));
        $workshop = $this->workshop();
        $this->enrollAndAttend($workshop, $user);

        $this->actingAs($user)->get(route('constancias.download', $workshop->id))->assertOk();

        $certificate = Certificate::query()->sole();
        $certificate->update([
            'metadata' => array_merge($certificate->metadata, ['nombre' => 'Nombre Antiguo']),
        ]);

        $this->rename($user, 'Nombre', 'Nuevo');

        $this->artisan('constancias:refrescar-metadata --dry-run --user='.$user->id)
            ->expectsOutputToContain('Simulación')
            ->expectsOutputToContain('nombre')
            ->assertSuccessful();

        $this->assertSame('Nombre Antiguo', $certificate->fresh()->metadata['nombre']);
    }

    public function test_command_is_idempotent_and_reports_no_changes(): void
    {
        $user = $this->participant();
        $this->templateFor($this->type('taller'));
        $workshop = $this->workshop();
        $this->enrollAndAttend($workshop, $user);

        $this->actingAs($user)->get(route('constancias.download', $workshop->id))->assertOk();

        $this->artisan('constancias:refrescar-metadata --user='.$user->id)
            ->expectsOutputToContain('Sin cambios:           1')
            ->assertSuccessful();
    }

    public function test_command_skips_certificates_whose_activity_was_deleted(): void
    {
        $user = $this->participant();
        $this->templateFor($this->type('taller'));
        $workshop = $this->workshop();
        $this->enrollAndAttend($workshop, $user);

        $this->actingAs($user)->get(route('constancias.download', $workshop->id))->assertOk();

        $certificate = Certificate::query()->sole();
        $before = $certificate->metadata;
        $workshop->delete();

        $this->artisan('constancias:refrescar-metadata --user='.$user->id)
            ->expectsOutputToContain('Omitidas')
            ->assertSuccessful();

        $this->assertSame($before, $certificate->fresh()->metadata);
    }

    public function test_resolve_metadata_returns_null_for_a_broken_certificate(): void
    {
        $renderer = app(CertificateRenderer::class);

        $certificate = Certificate::create([
            'user_id' => $this->participant()->id,
            'participation_type_id' => $this->type('taller')->id,
            'event_type' => 'workshop',
            'event_id' => 999999,
            'metadata' => ['nombre' => 'Alguien'],
        ]);

        $this->assertNull($renderer->resolveMetadata($certificate));
    }
}
