<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Presentation;
use App\Models\ProgramItem;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgramaTest extends TestCase
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

    private function workshopFor(User $user, array $overrides = []): Workshop
    {
        return Workshop::create(array_merge([
            'name' => 'Taller de prueba',
            'description' => 'test',
            'capacity' => 10,
            'location' => 'Auditorio A',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'created_by' => $user->id,
        ], $overrides));
    }

    private function blockPayload(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'block',
            'day' => '2026-10-05',
            'block_type' => 'registro',
            'title' => 'Registro de asistentes',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'location' => 'Lobby',
        ], $overrides);
    }

    public function test_admin_can_view_program(): void
    {
        $this->actingAs($this->admin());

        $this->get('/programa')->assertOk();
    }

    public function test_user_without_permission_cannot_view_program(): void
    {
        $this->actingAs($this->regularUser());

        $this->get('/programa')->assertForbidden();
    }

    public function test_admin_can_create_block(): void
    {
        $this->actingAs($this->admin());

        $this->post('/programa', $this->blockPayload())->assertRedirect();

        $this->assertDatabaseHas('program_items', [
            'title' => 'Registro de asistentes',
            'block_type' => 'registro',
            'activity_type' => null,
            'activity_id' => null,
        ]);
    }

    public function test_block_requires_title_and_type(): void
    {
        $this->actingAs($this->admin());

        $this->post('/programa', $this->blockPayload(['title' => '']))
            ->assertSessionHasErrors('title');
        $this->post('/programa', $this->blockPayload(['block_type' => '']))
            ->assertSessionHasErrors('block_type');
        $this->post('/programa', $this->blockPayload(['block_type' => 'no-valido']))
            ->assertSessionHasErrors('block_type');

        $this->assertDatabaseCount('program_items', 0);
    }

    public function test_creating_workshop_auto_adds_it_to_program(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);

        $this->assertDatabaseHas('program_items', [
            'activity_type' => 'workshop',
            'activity_id' => $workshop->id,
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'location' => 'Auditorio A',
            'title' => null,
        ]);
    }

    public function test_program_shows_activities_outside_event_date_range(): void
    {
        Setting::updateOrCreate(['key' => 'evento_fecha_inicio'], ['value' => '2026-09-23']);
        Setting::updateOrCreate(['key' => 'evento_fecha_fin'], ['value' => '2026-09-25']);

        $admin = $this->admin();
        $this->workshopFor($admin, ['day' => '2026-08-13']);

        $this->actingAs($admin)
            ->get('/programa')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Programa/Index')
                ->has('groups', 1)
                ->where('groups.0.label', 'Jueves 13')
                ->has('groups.0.items', 1)
                ->where('groups.0.items.0.title', 'Taller de prueba'));
    }

    public function test_program_shows_items_without_event_date_range_configured(): void
    {
        $admin = $this->admin();
        $this->workshopFor($admin);

        $this->actingAs($admin)
            ->get('/programa')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Programa/Index')
                ->has('groups', 1)
                ->where('groups.0.label', 'Lunes 5')
                ->where('groups.0.items.0.title', 'Taller de prueba'));
    }

    public function test_updating_workshop_schedule_syncs_program_item(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);

        $workshop->update([
            'day' => '2026-10-05',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'location' => 'Sala B',
        ]);

        $this->assertDatabaseHas('program_items', [
            'activity_type' => 'workshop',
            'activity_id' => $workshop->id,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'location' => 'Sala B',
        ]);

        $item = ProgramItem::where('activity_type', 'workshop')
            ->where('activity_id', $workshop->id)
            ->first();

        $this->assertSame('08:00', $item->start_time->format('H:i'));
    }

    public function test_scheduling_presentation_updates_program_item(): void
    {
        $presentation = Presentation::create([
            'title' => 'Ponencia sin horario',
            'abstract' => null,
        ]);

        $this->assertDatabaseHas('program_items', [
            'activity_type' => 'presentation',
            'activity_id' => $presentation->id,
            'day' => null,
        ]);

        $presentation->update([
            'day' => '2026-10-06',
            'start_time' => '10:00',
            'end_time' => '10:30',
        ]);

        $this->assertDatabaseHas('program_items', [
            'activity_type' => 'presentation',
            'activity_id' => $presentation->id,
            'day' => '2026-10-06',
            'start_time' => '10:00',
        ]);
    }

    public function test_soft_deleting_activity_removes_item_and_restore_recreates_it(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);

        $workshop->delete();

        $this->assertDatabaseMissing('program_items', [
            'activity_type' => 'workshop',
            'activity_id' => $workshop->id,
        ]);

        Workshop::withTrashed()->find($workshop->id)->restore();

        $this->assertDatabaseHas('program_items', [
            'activity_type' => 'workshop',
            'activity_id' => $workshop->id,
        ]);
    }

    public function test_editing_linked_item_from_program_updates_activity(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);
        $item = ProgramItem::where('activity_type', 'workshop')
            ->where('activity_id', $workshop->id)
            ->first();

        $this->actingAs($admin);

        $this->put("/programa/{$item->id}", [
            'day' => '2026-10-05',
            'start_time' => '15:00',
            'end_time' => '18:00',
            'location' => 'Auditorio Central',
        ])->assertRedirect();

        $workshop->refresh();

        $this->assertSame('15:00', $workshop->start_time);
        $this->assertSame('18:00', $workshop->end_time);
        $this->assertSame('Auditorio Central', $workshop->location);

        $item->refresh();
        $this->assertSame('15:00', $item->start_time->format('H:i'));
    }

    public function test_editing_linked_item_requires_workshop_times(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);
        $item = ProgramItem::where('activity_type', 'workshop')
            ->where('activity_id', $workshop->id)
            ->first();

        $this->actingAs($admin);

        $this->put("/programa/{$item->id}", [
            'day' => '2026-10-05',
            'start_time' => null,
            'end_time' => null,
        ])->assertSessionHasErrors('start_time');

        $workshop->refresh();
        $this->assertSame('09:00', $workshop->start_time);
    }

    public function test_linked_item_cannot_be_deleted_from_program(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);
        $item = ProgramItem::where('activity_type', 'workshop')
            ->where('activity_id', $workshop->id)
            ->first();

        $this->actingAs($admin);

        $this->delete("/programa/{$item->id}")->assertStatus(422);

        $this->assertDatabaseHas('program_items', ['id' => $item->id]);
    }

    public function test_admin_can_delete_block(): void
    {
        $admin = $this->admin();
        $item = ProgramItem::create($this->blockPayload() + ['created_by' => $admin->id]);
        $this->actingAs($admin);

        $this->delete("/programa/{$item->id}")->assertRedirect();

        $this->assertDatabaseMissing('program_items', ['id' => $item->id]);
    }

    public function test_programa_sync_backfills_missing_items(): void
    {
        $admin = $this->admin();
        $this->workshopFor($admin);
        $this->actingAs($admin);

        ProgramItem::query()->delete();
        $this->assertDatabaseCount('program_items', 0);

        $this->get('/programa')->assertOk();

        $this->assertDatabaseHas('program_items', [
            'activity_type' => 'workshop',
        ]);
    }

    public function test_admin_can_print_program(): void
    {
        $this->actingAs($this->admin());

        $this->get('/programa/imprimir')->assertOk();
    }

    public function test_user_without_permission_cannot_print_program(): void
    {
        $this->actingAs($this->regularUser());

        $this->get('/programa/imprimir')->assertForbidden();
    }

    public function test_presentations_order_by_location_within_same_start_time(): void
    {
        $admin = $this->admin();

        $this->workshopFor($admin, ['day' => '2026-10-05', 'start_time' => '09:00', 'location' => 'Auditorio A']);

        $late = Presentation::create([
            'title' => 'Ponencia Auditorio B',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'location' => 'Auditorio B',
        ]);
        $early = Presentation::create([
            'title' => 'Ponencia Lobby',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'location' => 'Lobby',
        ]);
        $nosala = Presentation::create([
            'title' => 'Ponencia sin sala',
            'day' => '2026-10-05',
            'start_time' => '09:00',
        ]);

        $this->actingAs($admin)
            ->get('/programa')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Programa/Index')
                ->has('groups', 1)
                ->where('groups.0.label', 'Lunes 5')
                ->has('groups.0.items', 4)
                ->where('groups.0.items.0.title', 'Ponencia sin sala')
                ->where('groups.0.items.1.title', 'Ponencia Auditorio B')
                ->where('groups.0.items.2.title', 'Ponencia Lobby')
                ->where('groups.0.items.3.title', 'Taller de prueba'));
    }

    public function test_presentations_index_orders_by_location_within_same_start_time(): void
    {
        $admin = $this->admin();

        $latest = Presentation::create([
            'title' => 'Ponencia Auditorio B',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'location' => 'Auditorio B',
        ]);
        $earliest = Presentation::create([
            'title' => 'Ponencia Lobby',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'location' => 'Lobby',
        ]);
        $nosala = Presentation::create([
            'title' => 'Ponencia sin sala',
            'day' => '2026-10-05',
            'start_time' => '09:00',
        ]);

        $this->actingAs($admin)
            ->get('/presentations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Presentations/Index')
                ->has('presentations.data', 3)
                ->where('presentations.data.0.title', 'Ponencia sin sala')
                ->where('presentations.data.1.title', 'Ponencia Auditorio B')
                ->where('presentations.data.2.title', 'Ponencia Lobby'));
    }

    public function test_admin_can_create_block_with_moderators(): void
    {
        $admin = $this->admin();
        $moderator = User::factory()->create();

        $this->actingAs($admin)
            ->post('/programa', $this->blockPayload([
                'moderator_ids' => [$moderator->id],
            ]))
            ->assertRedirect();

        $item = ProgramItem::where('title', 'Registro de asistentes')->firstOrFail();

        $this->assertDatabaseHas('program_item_moderators', [
            'program_item_id' => $item->id,
            'user_id' => $moderator->id,
        ]);
        $this->assertTrue($moderator->hasRole('Moderator'));
    }

    public function test_admin_can_update_block_moderators(): void
    {
        $admin = $this->admin();
        $moderatorA = User::factory()->create();
        $moderatorB = User::factory()->create();

        $this->actingAs($admin)->post('/programa', $this->blockPayload([
            'moderator_ids' => [$moderatorA->id],
        ]));

        $item = ProgramItem::where('title', 'Registro de asistentes')->firstOrFail();

        $this->actingAs($admin)
            ->put('/programa/'.$item->id, $this->blockPayload([
                'title' => 'Registro actualizado',
                'moderator_ids' => [$moderatorB->id],
            ]))
            ->assertRedirect();

        $item->refresh();

        $this->assertDatabaseHas('program_item_moderators', [
            'program_item_id' => $item->id,
            'user_id' => $moderatorB->id,
        ]);
        $this->assertDatabaseMissing('program_item_moderators', [
            'program_item_id' => $item->id,
            'user_id' => $moderatorA->id,
        ]);
    }

    public function test_updating_linked_workshop_assigns_moderators(): void
    {
        $admin = $this->admin();
        $moderator = User::factory()->create();
        $workshop = $this->workshopFor($admin, [
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $item = ProgramItem::where('activity_type', 'workshop')->firstOrFail();

        $this->actingAs($admin)
            ->put('/programa/'.$item->id, [
                'day' => '2026-10-05',
                'start_time' => '09:00',
                'end_time' => '12:00',
                'location' => 'Auditorio A',
                'moderator_ids' => [$moderator->id],
            ])
            ->assertRedirect();

        $workshop->refresh();
        $this->assertTrue($workshop->moderators()->where('users.id', $moderator->id)->exists());
        $this->assertTrue($moderator->refresh()->hasRole('Moderator'));
    }

    public function test_updating_linked_conference_preserves_speakers_and_sets_moderators(): void
    {
        $admin = $this->admin();
        $speaker = User::factory()->create();
        $moderator = User::factory()->create();
        $conference = Conference::create([
            'title' => 'Conferencia magistral',
            'kind' => 'magistral',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'created_by' => $admin->id,
        ]);
        $conference->members()->attach($speaker->id, ['role' => 'speaker']);

        $item = ProgramItem::where('activity_type', 'conference')->firstOrFail();

        $this->actingAs($admin)
            ->put('/programa/'.$item->id, [
                'day' => '2026-10-05',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'location' => null,
                'moderator_ids' => [$moderator->id],
            ])
            ->assertRedirect();

        $conference->refresh();
        $this->assertTrue($conference->speakers()->where('users.id', $speaker->id)->exists());
        $this->assertTrue($conference->moderators()->where('users.id', $moderator->id)->exists());
    }

    public function test_program_serializes_moderator_ids_in_details(): void
    {
        $admin = $this->admin();
        $moderator = User::factory()->create();
        $this->workshopFor($admin, [
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $item = ProgramItem::where('activity_type', 'workshop')->firstOrFail();

        $this->actingAs($admin)
            ->put('/programa/'.$item->id, [
                'day' => '2026-10-05',
                'start_time' => '09:00',
                'end_time' => '12:00',
                'location' => 'Auditorio A',
                'moderator_ids' => [$moderator->id],
            ]);

        $this->actingAs($admin)
            ->get('/programa')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Programa/Index')
                ->has('groups.0.items.0.details.moderators', 1)
                ->where('groups.0.items.0.details.moderators.0.id', $moderator->id));
    }
}
