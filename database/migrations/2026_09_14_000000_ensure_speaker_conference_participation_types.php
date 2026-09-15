<?php

use App\Models\ParticipationType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const SPEAKER_TYPES = [
        ['key' => 'conferencia_magistral', 'label' => 'Conferencista magistral', 'kind' => 'magistral'],
        ['key' => 'conferencia_especial', 'label' => 'Conferencista especial', 'kind' => 'especial'],
        ['key' => 'simposiasta', 'label' => 'Simposiasta', 'kind' => 'simposio'],
        ['key' => 'conferencia_grupo_tematico', 'label' => 'Conferencista de grupo temático', 'kind' => 'grupo_tematico'],
    ];

    public function up(): void
    {
        foreach (self::SPEAKER_TYPES as $type) {
            ParticipationType::updateOrCreate(
                ['key' => $type['key']],
                [
                    'label' => $type['label'],
                    'event_kind' => 'conference',
                    'kind' => $type['kind'],
                    'role' => 'speaker',
                    'is_active' => true,
                    'manual_generable' => false,
                ]
            );
        }
    }

    public function down(): void
    {
        foreach (self::SPEAKER_TYPES as $type) {
            ParticipationType::query()
                ->where('key', $type['key'])
                ->whereDoesntHave('templates')
                ->whereDoesntHave('certificates')
                ->delete();
        }
    }
};
