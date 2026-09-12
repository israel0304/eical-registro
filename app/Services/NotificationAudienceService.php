<?php

namespace App\Services;

use App\Models\Conference;
use App\Models\NotificationSend;
use App\Models\ParticipationType;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Support\Collection;

class NotificationAudienceService
{
    /**
     * @return Collection<int, User>
     */
    public function resolve(string $audienceType, ?int $roleId = null, ?string $kind = null, array $userIds = [], ?int $workshopId = null): Collection
    {
        return match ($audienceType) {
            NotificationSend::AUDIENCE_TYPES[0] => $this->allUsers(),
            NotificationSend::AUDIENCE_TYPES[1] => $this->byRole((int) $roleId),
            NotificationSend::AUDIENCE_TYPES[2] => $this->speakersByKind((string) $kind),
            NotificationSend::AUDIENCE_TYPES[3] => $this->individual($userIds),
            NotificationSend::AUDIENCE_TYPES[4] => $this->workshopEnrollment((int) $workshopId),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, User>
     */
    public function allUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function byRole(int $roleId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereKey($roleId))
            ->get();
    }

    /**
     * Speakers asignados a conferencias del tipo dado, siempre todos (sin
     * discriminar por activated).
     *
     * @return Collection<int, User>
     */
    public function speakersByKind(string $kind): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('conferences', fn ($q) => $q->where('conferences.kind', $kind)->where('conference_members.role', 'speaker'))
            ->get();
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, User>
     */
    public function individual(array $userIds): Collection
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));

        if ($userIds === []) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->whereIn('id', $userIds)
            ->get();
    }

    public function kindLabel(?string $kind, ?string $fallback = null): ?string
    {
        if ($kind === null || $kind === '') {
            return $fallback;
        }

        return ParticipationType::query()
            ->where('event_kind', 'conference')
            ->where('role', 'speaker')
            ->where('kind', $kind)
            ->where('is_active', true)
            ->value('label') ?? $fallback ?? $kind;
    }

    /**
     * Usuarios inscritos (status 'enrolled') en un taller no eliminado.
     *
     * @return Collection<int, User>
     */
    public function workshopEnrollment(int $workshopId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('enrolledWorkshops', fn ($q) => $q
                ->whereKey($workshopId)
                ->where('workshop_enrollments.status', 'enrolled'))
            ->get();
    }

    public function workshopName(int $workshopId): ?string
    {
        return Workshop::withTrashed()->whereKey($workshopId)->value('name');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(User $user, ?string $tipoConferencia = null, ?string $nombreTaller = null): array
    {
        return [
            'nombre_completo' => $user->name,
            'nombre' => $user->first_name,
            'apellidos' => trim($user->last_name ?? ''),
            'correo' => $user->email,
            'dni' => $user->dni ?? '',
            'rol' => $user->roles->first()?->name ?? '',
            'tipo_conferencia' => $tipoConferencia ?? '',
            'nombre_taller' => $nombreTaller ?? '',
        ];
    }

    public function labelFor(NotificationSend $send): string
    {
        $value = $send->audience_value;

        return match ($send->audience_type) {
            'all_users' => 'Todos los usuarios',
            'role' => 'Rol: '.($this->roleName((int) ($value['role_id'] ?? 0)) ?? '?'),
            'speakers_by_kind' => 'Speakers por tipo: '.$this->kindLabel($value['kind'] ?? null, $value['kind'] ?? '?'),
            'individual' => 'Individual ('.(int) $send->recipient_count.')',
            'workshop_enrollment' => 'Inscritos en taller: '.($this->workshopName((int) ($value['workshop_id'] ?? 0)) ?? '?'),
            default => $send->audience_type,
        };
    }

    private function roleName(int $roleId): ?string
    {
        return Role::query()->whereKey($roleId)->value('name');
    }

    /**
     * Kinds de conferencia con su label de ParticipationType.
     *
     * @return array<string, string|null>
     */
    public function conferenceKinds(): array
    {
        return collect(Conference::KINDS)
            ->mapWithKeys(fn (string $kind) => [$kind => $this->kindLabel($kind)])
            ->all();
    }
}
