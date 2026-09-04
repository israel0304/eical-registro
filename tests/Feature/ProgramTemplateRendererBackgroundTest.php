<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Services\ProgramTemplateRenderer;
use Dompdf\Dompdf;
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

        // PNG de tamaño razonable con algo de ruido para que Dompdf no descarte
        // el recurso por pequeño (los PNG diminutos se omiten del PDF).
        $img = imagecreatetruecolor(300, 200);
        for ($y = 0; $y < 200; $y++) {
            for ($x = 0; $x < 300; $x++) {
                imagesetpixel($img, $x, $y, imagecolorallocate($img, $x % 255, $y % 255, ($x + $y) % 255));
            }
        }
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put('program_backgrounds/bg.png', $png);

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

    public function test_every_page_includes_background_image_on_screen(): void
    {
        $template = $this->makeTemplate();
        $renderer = new ProgramTemplateRenderer;

        $html = $renderer->render(
            $template,
            $this->bigGroups(),
            ['eventName' => 'EICAL', 'fechas' => '2026-10-05', 'lugar' => 'Ciudad'],
            false,
        );

        // En pantalla cada página conserva su <img class="bg"> dentro de .page.
        $pageCount = substr_count($html, 'class="page"');
        $this->assertGreaterThan(1, $pageCount, 'Se esperaba más de una página de programa.');

        $this->assertSame(
            $pageCount,
            substr_count($html, 'class="bg"'),
            'Cada página en pantalla debe llevar un <img class="bg"> de fondo.',
        );
    }

    public function test_pdf_uses_single_fixed_background_div(): void
    {
        $template = $this->makeTemplate();
        $renderer = new ProgramTemplateRenderer;

        $html = $renderer->render(
            $template,
            $this->bigGroups(),
            ['eventName' => 'EICAL', 'fechas' => '2026-10-05', 'lugar' => 'Ciudad'],
            true,
        );

        // En PDF el fondo se emite una sola vez como <img> position:fixed a nivel
        // de body, sin <img class="bg"> por página (que Dompdf no repite).
        $this->assertSame(1, substr_count($html, 'class="bg-fixed"'));
        $this->assertSame(0, substr_count($html, 'class="bg"'));
        $this->assertStringContainsString('position: fixed', $html);
        $this->assertStringContainsString('class="bg-fixed" style="top:0px;left:0px;', $html);
    }

    public function test_rendered_pdf_embeds_background_on_every_page(): void
    {
        $template = $this->makeTemplate();
        $renderer = new ProgramTemplateRenderer;

        $html = $renderer->render(
            $template,
            $this->bigGroups(),
            ['eventName' => 'EICAL', 'fechas' => '2026-10-05', 'lugar' => 'Ciudad'],
            true,
        );

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, $template->width * 0.75, $template->height * 0.75]);
        $dompdf->render();
        $pdf = $dompdf->output();

        // El div position:fixed se incrusta como imagen en el PDF (referenciado
        // en todas las hojas). El fallo previo dejaba el fondo sin incrustar.
        $pages = preg_match_all('/\/Type\s*\/Page\b/', $pdf);
        $images = substr_count($pdf, '/Image');

        $this->assertGreaterThan(1, $pages, 'Se esperaba más de una página en el PDF.');
        $this->assertGreaterThanOrEqual(1, $images, 'El fondo fixeado debe incrustarse en el PDF.');
    }
}
