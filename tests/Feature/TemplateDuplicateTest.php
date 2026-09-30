<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\CertificateTemplateElement;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions = ['constancias.templates.manage', 'gafete.templates.manage', 'programa.templates.manage']): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        foreach ($permissions as $key) {
            Permission::firstOrCreate(['key' => $key], [
                'module' => 'constancias',
                'label' => $key,
            ]);
        }

        return $user;
    }

    private function template(array $overrides = []): CertificateTemplate
    {
        return CertificateTemplate::create(array_merge([
            'name' => 'Constancia de Taller',
            'kind' => 'certificate',
            'description' => 'Plantilla base',
            'width' => 1800,
            'height' => 1200,
            'is_default' => true,
        ], $overrides));
    }

    private function element(CertificateTemplate $template, array $overrides = []): CertificateTemplateElement
    {
        return CertificateTemplateElement::create(array_merge([
            'template_id' => $template->id,
            'type' => 'text',
            'content' => '{nombre}',
            'x' => 100,
            'y' => 200,
            'width' => 500,
            'height' => 60,
            'font_size' => 24,
            'font_weight' => 'bold',
            'color' => '#111827',
            'text_align' => 'center',
            'auto_fit' => true,
            'word_wrap' => true,
            'z_index' => 1,
        ], $overrides));
    }

    public function test_duplicate_copies_metadata_and_elements(): void
    {
        $source = $this->template(['participation_type_id' => null, 'print_width_mm' => 210, 'print_height_mm' => 297]);
        $this->element($source);
        $this->element($source, ['type' => 'qr', 'content' => null, 'variable' => 'qr', 'z_index' => 2]);

        $this->actingAs($this->admin())
            ->from('/admin/constancias/plantillas')
            ->post('/admin/constancias/plantillas/'.$source->id.'/duplicar')
            ->assertRedirect('/admin/constancias/plantillas');

        $copy = CertificateTemplate::where('name', 'Constancia de Taller - copia')->firstOrFail();

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame('Constancia de Taller - copia', $copy->name);
        $this->assertSame('Constancia de Taller', $source->fresh()->name);
        $this->assertSame('certificate', $copy->kind);
        $this->assertSame('Plantilla base', $copy->description);
        $this->assertSame(1800, (int) $copy->width);
        $this->assertSame(1200, (int) $copy->height);
        $this->assertSame(210.0, (float) $copy->print_width_mm);
        $this->assertFalse((bool) $copy->is_default);

        $this->assertSame(2, $copy->elements()->count());

        $original = $source->elements()->orderBy('z_index')->get();
        $cloned = $copy->elements()->orderBy('z_index')->get();

        foreach ($original as $i => $element) {
            $this->assertSame($element->type, $cloned[$i]->type);
            $this->assertSame($element->content, $cloned[$i]->content);
            $this->assertSame($element->variable, $cloned[$i]->variable);
            $this->assertSame((float) $element->x, (float) $cloned[$i]->x);
            $this->assertSame((float) $element->y, (float) $cloned[$i]->y);
            $this->assertSame((int) $element->font_size, (int) $cloned[$i]->font_size);
            $this->assertSame($element->font_weight, $cloned[$i]->font_weight);
            $this->assertSame($element->color, $cloned[$i]->color);
            $this->assertSame($element->text_align, $cloned[$i]->text_align);
            $this->assertSame((bool) $element->auto_fit, (bool) $cloned[$i]->auto_fit);
            $this->assertSame((bool) $element->word_wrap, (bool) $cloned[$i]->word_wrap);
        }
    }

    public function test_duplicate_name_is_incremental(): void
    {
        $source = $this->template();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/constancias/plantillas/'.$source->id.'/duplicar');
        $this->actingAs($admin)->post('/admin/constancias/plantillas/'.$source->id.'/duplicar');
        $this->actingAs($admin)->post('/admin/constancias/plantillas/'.$source->id.'/duplicar');

        $this->assertDatabaseHas('certificate_templates', ['name' => 'Constancia de Taller - copia']);
        $this->assertDatabaseHas('certificate_templates', ['name' => 'Constancia de Taller - copia 2']);
        $this->assertDatabaseHas('certificate_templates', ['name' => 'Constancia de Taller - copia 3']);
    }

    public function test_duplicate_copies_background_to_a_new_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificate_backgrounds/original.png', 'binary');

        $source = $this->template(['background_path' => 'certificate_backgrounds/original.png']);

        $this->actingAs($this->admin())
            ->post('/admin/constancias/plantillas/'.$source->id.'/duplicar');

        $copy = CertificateTemplate::where('name', 'Constancia de Taller - copia')->firstOrFail();

        $this->assertNotNull($copy->background_path);
        $this->assertNotSame($source->background_path, $copy->background_path);
        $this->assertStringStartsWith('certificate_backgrounds/', $copy->background_path);
        Storage::disk('public')->assertExists($copy->background_path);
        Storage::disk('public')->assertExists($source->fresh()->background_path);
    }

    public function test_duplicate_keeps_the_original_as_default(): void
    {
        $source = $this->template();

        $this->actingAs($this->admin())
            ->post('/admin/constancias/plantillas/'.$source->id.'/duplicar');

        $this->assertTrue((bool) $source->fresh()->is_default);
        $this->assertFalse((bool) CertificateTemplate::where('name', 'Constancia de Taller - copia')->value('is_default'));
    }

    public function test_badge_templates_can_be_duplicated(): void
    {
        $source = $this->template(['name' => 'Gafete Estándar', 'kind' => 'badge']);

        $this->actingAs($this->admin())
            ->post('/admin/gafetes/plantillas/'.$source->id.'/duplicar')
            ->assertRedirect();

        $this->assertDatabaseHas('certificate_templates', [
            'name' => 'Gafete Estándar - copia',
            'kind' => 'badge',
        ]);
    }

    public function test_invitation_templates_can_be_duplicated(): void
    {
        $role = Role::firstOrCreate(['name' => 'Speaker']);
        $source = $this->template([
            'name' => 'Carta de Invitación EICAL',
            'kind' => 'invitation',
            'role_id' => $role->id,
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/constancias/invitaciones/plantillas/'.$source->id.'/duplicar')
            ->assertRedirect();

        $copy = CertificateTemplate::where('name', 'Carta de Invitación EICAL - copia')->firstOrFail();

        $this->assertSame('invitation', $copy->kind);
        $this->assertSame($role->id, (int) $copy->role_id);
    }

    public function test_program_templates_are_duplicated_inactive(): void
    {
        $source = $this->template([
            'name' => 'Programa',
            'kind' => 'program',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->actingAs($this->admin())
            ->post('/programa/plantillas/'.$source->id.'/duplicar')
            ->assertRedirect();

        $copy = CertificateTemplate::where('name', 'Programa - copia')->firstOrFail();

        $this->assertSame('program', $copy->kind);
        $this->assertFalse((bool) $copy->is_active);
        $this->assertFalse((bool) $copy->is_default);
        $this->assertTrue((bool) $source->fresh()->is_active);
    }

    public function test_other_template_kinds_cannot_be_duplicated_from_the_wrong_module(): void
    {
        $source = $this->template(['kind' => 'program', 'name' => 'Programa']);

        $this->actingAs($this->admin())
            ->post('/admin/constancias/plantillas/'.$source->id.'/duplicar')
            ->assertNotFound();
    }

    public function test_duplicate_requires_permission(): void
    {
        $source = $this->template();
        $before = CertificateTemplate::count();

        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->post('/admin/constancias/plantillas/'.$source->id.'/duplicar')
            ->assertForbidden();

        $this->assertDatabaseMissing('certificate_templates', ['name' => 'Constancia de Taller - copia']);
        $this->assertSame($before, CertificateTemplate::count());
    }
}
