<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Services\ProgramTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramTemplateRendererBackgroundTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(): CertificateTemplate
    {
        Storage::fake('public');
        $bg = 'program_backgrounds/bg.png';
        Storage::disk('public')->put('program_backgrounds/bg.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        $template = CertificateTemplate::create([
            'name' => 'Programa con fondo',
            'kind' => 'program',
            'is_active' => true,
            'width' => 816,
            'height' => 1056,
            'background_path' => $bg,
        ]);

        $template->elements()->create([
            'type' => 'program',
            'content' => json_encode(['show_time' => true]),
            'x' => 48,
            'y' => 170,
            'width' => 720,
            'z_index' => 1,
        ]);

        return $template;
    }

    private function bigGroups(): Collection
    {
        $items = [];
        for ($i = 0; $i < 25; $i++) {
            $items[] = [
                'id' => $i + 1,
                'kind' => 'activity',
                'activity_type' => 'workshop',
                'activity_label' => 'Taller',
                'title' => 'Actividad de prueba número '.$i.' con un título lo suficientemente largo',
                'time_label' => '09:00',
                'location' => 'Auditorio A',
                'people' => [['label' => 'Ponente', 'names' => ['Persona Uno', 'Persona Dos']]],
            ];
        }

        return collect([
            ['label' => 'Día 1', 'items' => $items],
        ]);
    }

    public function test_every_page_includes_background_image_css(): void
    {
        $template = $this->makeTemplate();
        $renderer = new ProgramTemplateRenderer;

        $html = $renderer->render(
            $template,
            $this->bigGroups(),
            ['eventName' => 'EICAL', 'fechas' => '2026-10-05', 'lugar' => 'Ciudad'],
            true,
        );

        // El fondo no debe inyectarse como una imagen con la clase "bg".
        $this->assertStringNotContainsString('class="bg"', $html);
        $this->assertStringNotContainsString('.bg {', $html);

        // Debe haber más de una página para validar que el fondo se repite.
        $pageCount = substr_count($html, 'class="page"');
        $this->assertGreaterThan(1, $pageCount, 'Se esperaba más de una página de programa.');

        // Cada página debe incluir el fondo como background-image CSS.
        $this->assertSame(
            $pageCount,
            substr_count($html, "background-image:url('data:image/png;base64,"),
            'Cada página debe llevar el fondo como background-image.',
        );
    }
}
