<?php

use App\Models\Certificate;
use App\Models\Workshop;
use App\Support\WorkshopGroups;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->rekey(fn (int $parentId) => -$parentId);
    }

    public function down(): void
    {
        $this->rekey(fn (int $parentId) => $parentId);
    }

    private function rekey(callable $target): void
    {
        $parents = Workshop::query()
            ->whereHas('childWorkshops')
            ->get();

        foreach ($parents as $parent) {
            $group = new WorkshopGroups($parent);

            Certificate::query()
                ->where('event_type', 'workshop')
                ->where('event_id', $parent->id)
                ->cursor()
                ->each(function (Certificate $certificate) use ($group, $target) {
                    $metadata = $certificate->metadata;

                    $isConsolidated = ($metadata['horas_totales'] ?? null) === $group->totalHours()
                        && ($metadata['evento'] ?? null) === $group->baseTitle()
                        && ($metadata['fecha_evento'] ?? null) === $group->dateRange();

                    if ($isConsolidated) {
                        $certificate->forceFill(['event_id' => $target($group->groupId())])->save();
                    }
                });
        }
    }
};
