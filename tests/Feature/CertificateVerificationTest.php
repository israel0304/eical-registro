<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\ParticipationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function certificate(array $metadata): Certificate
    {
        $type = ParticipationType::create([
            'key' => 'taller',
            'label' => 'Asistente a taller',
            'event_kind' => 'workshop',
            'role' => 'enrolled_attendance',
            'is_active' => true,
        ]);

        return Certificate::create([
            'folio' => 'EICAL-TEST-HORAS',
            'user_id' => User::factory()->create()->id,
            'participation_type_id' => $type->id,
            'event_id' => 1,
            'event_type' => 'workshop',
            'metadata' => $metadata,
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
}
