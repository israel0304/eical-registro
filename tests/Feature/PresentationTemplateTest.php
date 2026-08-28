<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PresentationTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::firstOrCreate(['name' => 'Administrator'])->id]);

        return $user;
    }

    private function ponente(): User
    {
        $role = Role::create(['name' => 'Ponente', 'is_active' => true]);
        $my = Permission::updateOrCreate(
            ['key' => 'presentations.my'],
            ['module' => 'Ponencias', 'label' => 'Ver mis ponencias'],
        );
        $role->permissions()->sync([$my->id]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function ponenteConPonencia(string $status = 'aceptada'): User
    {
        $user = $this->ponente();

        $presentation = Presentation::create([
            'title' => 'Ponencia de prueba',
            'abstract' => 'Resumen',
            'status' => $status,
        ]);
        $presentation->authors()->attach($user->id, ['author_order' => 1]);

        return $user;
    }

    private function configurarPlantilla(): void
    {
        Storage::fake('public');
        Setting::updateOrCreate(['key' => 'plantilla_presentacion_path'], ['value' => 'presentation-template/plantilla.pptx']);
        Setting::updateOrCreate(['key' => 'plantilla_presentacion_nombre'], ['value' => 'plantilla-eical.pptx']);
        Storage::disk('public')->put('presentation-template/plantilla.pptx', 'binario');
    }

    public function test_admin_can_upload_presentation_template(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post('/admin/presentations/plantilla', [
                'file' => UploadedFile::fake()->create('plantilla.pptx', 100),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'plantilla_presentacion_path']);
        $files = Storage::disk('public')->files('presentation-template');
        $this->assertCount(1, $files);
    }

    public function test_non_admin_cannot_upload_or_delete_template(): void
    {
        Storage::fake('public');

        $this->actingAs($this->ponente())
            ->post('/admin/presentations/plantilla', [
                'file' => UploadedFile::fake()->create('plantilla.pptx', 100),
            ])
            ->assertForbidden();

        $this->actingAs($this->ponente())
            ->delete('/admin/presentations/plantilla')
            ->assertForbidden();
    }

    public function test_admin_can_delete_presentation_template(): void
    {
        $this->configurarPlantilla();

        $this->actingAs($this->admin())
            ->delete('/admin/presentations/plantilla')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('settings', ['key' => 'plantilla_presentacion_path']);
        $this->assertDatabaseMissing('settings', ['key' => 'plantilla_presentacion_nombre']);
        Storage::disk('public')->assertMissing('presentation-template/plantilla.pptx');
    }

    public function test_ponente_with_accepted_presentation_can_download_template(): void
    {
        $this->configurarPlantilla();

        $user = $this->ponenteConPonencia('aceptada');

        $this->actingAs($user)
            ->get('/my-presentations/plantilla-presentacion')
            ->assertOk()
            ->assertDownload('plantilla-eical.pptx');
    }

    public function test_ponente_without_accepted_presentation_cannot_download_template(): void
    {
        $this->configurarPlantilla();

        $user = $this->ponenteConPonencia('rechazada');

        $this->actingAs($user)
            ->get('/my-presentations/plantilla-presentacion')
            ->assertForbidden();
    }

    public function test_ponente_without_any_presentation_cannot_download_template(): void
    {
        $this->configurarPlantilla();

        $this->actingAs($this->ponente())
            ->get('/my-presentations/plantilla-presentacion')
            ->assertForbidden();
    }

    public function test_download_returns_404_when_template_not_configured(): void
    {
        Storage::fake('public');

        $this->actingAs($this->ponenteConPonencia('aceptada'))
            ->get('/my-presentations/plantilla-presentacion')
            ->assertNotFound();
    }

    public function test_my_presentations_exposes_slide_template_available(): void
    {
        $this->configurarPlantilla();

        $user = $this->ponenteConPonencia('aceptada');

        $response = $this->actingAs($user)->get('/my-presentations');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('slideTemplateAvailable', true));
    }

    public function test_my_presentations_hides_slide_template_when_no_accepted_presentation(): void
    {
        $this->configurarPlantilla();

        $user = $this->ponenteConPonencia('rechazada');

        $this->actingAs($user)
            ->get('/my-presentations')
            ->assertInertia(fn ($page) => $page
                ->where('slideTemplateAvailable', false));
    }
}
