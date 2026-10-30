<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\ParticipationType;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function certificate(array $metadata, int $eventId = 1, string $folio = 'EICAL-TEST-HORAS'): Certificate
    {
        $type = ParticipationType::create([
            'key' => 'taller',
            'label' => 'Asistente a taller',
            'event_kind' => 'workshop',
            'role' => 'enrolled_attendance',
            'is_active' => true,
        ]);

        return Certificate::create([
            'folio' => $folio,
            'user_id' => User::factory()->create()->id,
            'participation_type_id' => $type->id,
            'event_id' => $eventId,
            'event_type' => 'workshop',
            'metadata' => $metadata,
        ]);
    }

    private function workshop(string $location = 'Aula 1'): Workshop
    {
        return Workshop::create([
            'name' => 'Taller de prueba',
            'description' => 'test',
            'capacity' => 20,
            'location' => $location,
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_verification_shows_the_certificate_duration(): void
    {
        $certificate = $this->certificate([
            'nombre' => 'Ana Pérez',
            'evento' => 'Taller de prueba',
            'fecha_evento' => '5 de octubre de 2026',
            'horas_totales' => '8',
        ]);

        $this->get(route('constancias.verificar', $certificate->folio))
            ->assertOk()
            ->assertSee('Duración')
            ->assertSee('8 h');
    }

    public function test_verification_hides_the_duration_when_not_available(): void
    {
        $certificate = $this->certificate([
            'nombre' => 'Ana Pérez',
            'evento' => 'Conferencia de prueba',
            'fecha_evento' => '5 de octubre de 2026',
        ]);

        $this->get(route('constancias.verificar', $certificate->folio))
            ->assertOk()
            ->assertDontSee('Duración');
    }

    public function test_verification_reflects_workshop_changes_after_issuance(): void
    {
        $workshop = $this->workshop('Aula 1');

        $certificate = $this->certificate([
            'nombre' => 'Ana Pérez',
            'evento' => $workshop->name,
            'fecha_evento' => '5 de octubre de 2026',
            'location' => 'Aula 1',
            'horas_totales' => '4',
        ], $workshop->id);

        $workshop->update(['location' => 'Aula 2']);

        $response = $this->get(route('constancias.verificar', $certificate->folio));

        $response->assertOk()
            ->assertSee('Aula 2')
            ->assertDontSee('Aula 1');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_verification_falls_back_to_stored_metadata_when_activity_is_missing(): void
    {
        $certificate = $this->certificate([
            'nombre' => 'Ana Pérez',
            'evento' => 'Taller eliminado',
            'fecha_evento' => '5 de octubre de 2026',
            'location' => 'Aula 1',
        ], 999999);

        $this->get(route('constancias.verificar', $certificate->folio))
            ->assertOk()
            ->assertSee('Taller eliminado')
            ->assertSee('Aula 1');
    }
}
