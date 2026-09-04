<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\ProgramItem;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgramaTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function blocks(User $user, int $count, string $day = '2026-10-05'): void
    {
        for ($i = 1; $i <= $count; $i++) {
            ProgramItem::create([
                'title' => "Bloque {$i}",
                'day' => $day,
                'start_time' => sprintf('%02d:00', 8 + ($i % 10)),
                'end_time' => sprintf('%02d:00', 9 + ($i % 10)),
                'location' => 'Sala '.$i,
                'block_type' => 'registro',
                'created_by' => $user->id,
            ]);
        }
    }

    private function programTemplate(): CertificateTemplate
    {
        $template = CertificateTemplate::create([
            'name' => 'Programa activo',
            'kind' => 'program',
            'is_active' => true,
            'width' => 816,
            'height' => 1056,
        ]);

        $template->elements()->create([
            'type' => 'text',
            'content' => '{evento} · {lugar_evento}',
            'x' => 48,
            'y' => 56,
            'width' => 720,
            'height' => 60,
            'font_size' => 28,
            'font_weight' => 'bold',
            'text_align' => 'center',
            'z_index' => 1,
        ]);

        return $template;
    }

    public function test_admin_can_index_program_templates(): void
    {
        CertificateTemplate::create([
            'name' => 'Programa carta',
            'kind' => 'program',
            'is_active' => true,
            'width' => 816,
            'height' => 1056,
        ]);

        $this->actingAs($this->admin())
            ->get('/programa/plantillas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Programa/Plantillas/Index')
                ->has('templates', 1)
                ->where('templates.0.name', 'Programa carta'));
    }

    public function test_user_without_permission_cannot_access_program_templates(): void
    {
        $this->actingAs($this->regularUser());

        $this->get('/programa/plantillas')->assertForbidden();
        $this->post('/programa/plantillas', ['name' => 'X'])->assertForbidden();
    }

    public function test_creating_template_seeds_default_elements(): void
    {
        $this->actingAs($this->admin());

        $this->post('/programa/plantillas', ['name' => 'Programa ECIAL'])
            ->assertRedirect();

        $template = CertificateTemplate::where('name', 'Programa ECIAL')->first();

        $this->assertNotNull($template);
        $this->assertSame('program', $template->kind);
        $this->assertNull($template->role_id);
        $this->assertSame(816, $template->width);
        $this->assertSame(1056, $template->height);
        $this->assertTrue($template->is_active);
        $this->assertSame(3, $template->elements()->count());
        $this->assertSame(1, $template->elements()->where('type', 'program')->count());
    }

    public function test_creating_active_template_deactivates_previous_one(): void
    {
        $this->actingAs($this->admin());

        $this->post('/programa/plantillas', ['name' => 'Primera', 'is_active' => true]);
        $this->post('/programa/plantillas', ['name' => 'Segunda', 'is_active' => true]);

        $first = CertificateTemplate::where('name', 'Primera')->first();
        $second = CertificateTemplate::where('name', 'Segunda')->first();

        $this->assertFalse($first->is_active);
        $this->assertTrue($second->is_active);
    }

    public function test_admin_can_update_template_elements_and_toggle(): void
    {
        $admin = $this->admin();
        $template = CertificateTemplate::create([
            'name' => 'Original',
            'kind' => 'program',
            'is_active' => true,
            'width' => 816,
            'height' => 1056,
        ]);

        $this->actingAs($admin);

        $this->put("/programa/plantillas/{$template->id}", [
            'name' => 'Actualizado',
            'width' => 816,
            'height' => 1056,
            'elements' => [
                ['type' => 'text', 'content' => '{evento}', 'x' => 48, 'y' => 56, 'width' => 720, 'height' => 60, 'font_size' => 32, 'text_align' => 'center', 'z_index' => 1],
                ['type' => 'program', 'content' => '{}', 'x' => 48, 'y' => 190, 'width' => 720, 'text_align' => 'left', 'z_index' => 2],
            ],
        ])->assertRedirect();

        $template->refresh()->load('elements');

        $this->assertSame('Actualizado', $template->name);
        $this->assertSame(2, $template->elements()->count());

        $this->patch("/programa/plantillas/{$template->id}/activar", ['is_active' => false])
            ->assertRedirect();

        $this->assertFalse($template->refresh()->is_active);
    }

    public function test_print_uses_active_program_template_and_paginates(): void
    {
        Setting::updateOrCreate(['key' => 'evento_nombre'], ['value' => 'EICAL Prueba']);
        Setting::updateOrCreate(['key' => 'evento_lugar'], ['value' => 'Casa de la Ciencia']);

        $admin = $this->admin();
        $this->blocks($admin, 45);
        $this->programTemplate();

        $this->actingAs($admin);

        $response = $this->get('/programa/imprimir');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $this->assertStringContainsString('EICAL Prueba · Casa de la Ciencia', $html);
        $this->assertStringContainsString('<div class="page-holder">', $html);
        $this->assertGreaterThan(1, substr_count($html, '<div class="page"'));
        $firstPage = strstr($html, '<div class="page" id="page-2"', true) ?: $html;
        $this->assertStringNotContainsString('Bloque 45', $firstPage);
        $this->assertStringContainsString('Bloque 1', $html);
        $secondPageOn = strstr($html, '<div class="page" id="page-2"') ?: '';
        $this->assertStringContainsString('Lunes 5', $secondPageOn);
        $this->assertStringContainsString('<div class="pd">Lunes 5</div>', $html);
        $this->assertStringContainsString('print-btn', $html);
        $this->assertStringContainsString('onclick="window.print()"', $html);
        $this->assertStringContainsString('@page { size: 816px 1056px;', $html);
    }

    public function test_print_starts_each_new_day_on_a_fresh_page(): void
    {
        Setting::updateOrCreate(['key' => 'evento_nombre'], ['value' => 'EICAL Prueba']);
        Setting::updateOrCreate(['key' => 'evento_lugar'], ['value' => 'Casa de la Ciencia']);

        $admin = $this->admin();
        $this->blocks($admin, 40, '2026-10-05');
        $this->blocks($admin, 3, '2026-10-06');
        $this->programTemplate();

        $this->actingAs($admin);

        $response = $this->get('/programa/imprimir');
        $html = $response->getContent();

        $response->assertOk();
        $this->assertGreaterThan(2, substr_count($html, '<div class="page"'));
        $this->assertStringContainsString('<div class="pd">Lunes 5</div>', $html);
        $this->assertStringContainsString('<div class="pd">Martes 6</div>', $html);

        // "Lunes 5" se repite en las hojas que continúan el día 1.
        $this->assertGreaterThan(1, substr_count($html, '<div class="pd">Lunes 5</div>'));

        // Cada hoja se extrae y se comprueba que nunca mezcla ambos días.
        $pages = $this->extractPages($html);
        $this->assertNotEmpty($pages);
        foreach ($pages as $page) {
            $hasMonday = str_contains($page, 'Lunes 5');
            $hasTuesday = str_contains($page, 'Martes 6');
            $this->assertFalse($hasMonday && $hasTuesday, 'Una hoja no debe mezclar dos días distintos.');
        }

        // "Martes 6" aparece una sola vez, en una hoja propia cuyo listado
        // arranca con el rótulo del día (Regla B: día nuevo en hoja nueva).
        $this->assertEquals(1, count(array_filter($pages, fn (string $p) => str_contains($p, 'Martes 6'))));
        $tuesdayPage = collect($pages)->first(fn (string $p) => str_contains($p, 'Martes 6'));
        $firstDayHead = mb_substr($tuesdayPage, mb_strpos($tuesdayPage, '<div class="pd">'), mb_strlen('<div class="pd">Martes 6</div>'));
        $this->assertSame('<div class="pd">Martes 6</div>', $firstDayHead);
    }

    /**
     * @return array<int, string>
     */
    private function extractPages(string $html): array
    {
        $pages = [];
        foreach (explode('<div class="page" id="page-', $html) as $i => $fragment) {
            if ($i === 0) {
                continue;
            }
            $pages[] = substr($fragment, strpos($fragment, '">') + 2);
        }

        return $pages;
    }

    public function test_print_shows_people_for_activities(): void
    {
        $admin = $this->admin();
        $instructor = User::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Torres',
        ]);
        $moderator = User::factory()->create([
            'first_name' => 'Luis',
            'last_name' => 'Gómez',
        ]);

        $workshop = Workshop::create([
            'name' => 'Taller con personas',
            'description' => 'test',
            'capacity' => 10,
            'location' => 'Auditorio A',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'created_by' => $admin->id,
        ]);
        $workshop->instructors()->attach($instructor->id);
        $workshop->moderators()->attach($moderator->id);

        CertificateTemplate::create([
            'name' => 'Programa personas',
            'kind' => 'program',
            'is_active' => true,
            'width' => 816,
            'height' => 1056,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/programa/imprimir');
        $html = $response->getContent();

        $response->assertOk();
        $this->assertStringContainsString('Instructores:', $html);
        $this->assertStringContainsString('Ana Torres', $html);
        $this->assertStringContainsString('Moderadores:', $html);
        $this->assertStringContainsString('Luis Gómez', $html);
    }

    public function test_print_falls_back_to_blade_without_active_template(): void
    {
        $admin = $this->admin();
        $this->blocks($admin, 2);

        $this->actingAs($admin);

        $html = $this->get('/programa/imprimir')->getContent();

        $this->assertStringContainsString('Programa del evento', $html);
        $this->assertStringContainsString('table-header-group', $html);
        $this->assertStringContainsString('print-btn', $html);
        $this->assertStringContainsString('onclick="window.print()"', $html);
        $this->assertStringNotContainsString('<div class="page-holder">', $html);
    }

    public function test_edit_page_exposes_program_groups_and_meta(): void
    {
        Setting::updateOrCreate(['key' => 'evento_nombre'], ['value' => 'EICAL Grupos']);
        Setting::updateOrCreate(['key' => 'evento_lugar'], ['value' => 'Auditorio Central']);
        Setting::updateOrCreate(['key' => 'evento_fecha_inicio'], ['value' => '2026-10-05']);
        Setting::updateOrCreate(['key' => 'evento_fecha_fin'], ['value' => '2026-10-07']);

        $admin = $this->admin();
        $this->blocks($admin, 3);

        $template = CertificateTemplate::create([
            'name' => 'Editor props',
            'kind' => 'program',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get("/programa/plantillas/{$template->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Programa/Plantillas/Edit')
                ->has('groups', 1)
                ->where('groups.0.label', 'Lunes 5')
                ->has('groups.0.items', 3)
                ->where('groups.0.items.0.title', 'Bloque 1')
                ->where('groups.0.items.0.kind', 'block')
                ->where('groups.0.items.0.people', [])
                ->where('meta.eventName', 'EICAL Grupos')
                ->where('meta.fechas', '5 de octubre de 2026 al 7 de octubre de 2026')
                ->where('meta.lugar', 'Auditorio Central'));
    }

    public function test_print_uses_badge_color_per_activity_type(): void
    {
        $admin = $this->admin();
        $this->blocks($admin, 1);

        Workshop::create([
            'name' => 'Taller colorido',
            'description' => 'test',
            'capacity' => 10,
            'location' => 'Laboratorio 1',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'created_by' => $admin->id,
        ]);

        CertificateTemplate::create([
            'name' => 'Programa badges',
            'kind' => 'program',
            'is_active' => true,
            'width' => 816,
            'height' => 1056,
        ]);

        $this->actingAs($admin);

        $html = $this->get('/programa/imprimir')->getContent();

        $this->assertStringContainsString('pi-badge pi-badge-workshop', $html);
        $this->assertStringContainsString('pi-badge pi-badge-block', $html);
        $this->assertStringContainsString('background: #b45309', $html);
        $this->assertStringContainsString('background: #475569', $html);
    }

    public function test_public_print_renders_active_template_without_auth(): void
    {
        CertificateTemplate::create([
            'name' => 'Programa público',
            'kind' => 'program',
            'is_active' => true,
            'width' => 816,
            'height' => 1056,
        ]);

        $this->get('/programa/publico/imprimir')
            ->assertOk()
            ->assertSee('pi-badge-block');
    }

    public function test_public_print_falls_back_when_no_active_template(): void
    {
        $this->get('/programa/publico/imprimir')
            ->assertOk()
            ->assertSee('Programa del evento');
    }
}
