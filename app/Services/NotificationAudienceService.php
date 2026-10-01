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
    public const SEGMENT_ALL_USERS = 'all_users';

    public const SEGMENT_ROLE = 'role';

    public const SEGMENT_SPEAKERS_BY_KIND = 'speakers_by_kind';

    public const SEGMENT_WORKSHOP_ENROLLMENT = 'workshop_enrollment';

    public const SEGMENT_WORKSHOP_INSTRUCTORS = 'workshop_instructors';

    public const SEGMENT_INDIVIDUAL = 'individual';

    /**
     * Tipos de segmento combinables dentro de una misma campaña. Los cinco
     * primeros coinciden con los tipos de audiencia históricos; el sexto
     * (instructores de taller) es nuevo.
     */
    public const SEGMENT_TYPES = [
        self::SEGMENT_ALL_USERS,
        self::SEGMENT_ROLE,
        self::SEGMENT_SPEAKERS_BY_KIND,
        self::SEGMENT_WORKSHOP_ENROLLMENT,
        self::SEGMENT_WORKSHOP_INSTRUCTORS,
        self::SEGMENT_INDIVIDUAL,
    ];

    /**
     * @return Collection<int, User>
     */
    public function resolve(string $audienceType, ?int $roleId = null, ?string $kind = null, array $userIds = [], ?int $workshopId = null): Collection
    {
        return $this->resolveSegmentsDetailed([
            $this->legacySegment($audienceType, $roleId, $kind, $userIds, $workshopId),
        ])['users'];
    }

    /**
     * Traduce la audiencia de un solo tipo (formato histórico) a un segmento.
     *
     * @return array<string, mixed>
     */
    public function legacySegment(string $audienceType, ?int $roleId = null, ?string $kind = null, array $userIds = [], ?int $workshopId = null): array
    {
        return match ($audienceType) {
            self::SEGMENT_ALL_USERS => ['type' => self::SEGMENT_ALL_USERS],
            self::SEGMENT_ROLE => ['type' => self::SEGMENT_ROLE, 'role_ids' => array_filter([(int) $roleId])],
            self::SEGMENT_SPEAKERS_BY_KIND => ['type' => self::SEGMENT_SPEAKERS_BY_KIND, 'kinds' => array_filter([(string) $kind])],
            self::SEGMENT_WORKSHOP_ENROLLMENT => ['type' => self::SEGMENT_WORKSHOP_ENROLLMENT, 'workshop_ids' => array_filter([(int) $workshopId])],
            self::SEGMENT_INDIVIDUAL => ['type' => self::SEGMENT_INDIVIDUAL, 'user_ids' => array_map('intval', $userIds)],
            // Un tipo desconocido no debe convertirse en "todos los usuarios".
            default => ['type' => $audienceType],
        };
    }

    /**
     * Normaliza segmentos recibidos: castea a enteros, elimina los que se
     * quedan sin criterio y descarta los tipos desconocidos.
     *
     * @param  array<int, array<string, mixed>|mixed>  $segments
     * @return array<int, array<string, mixed>>
     */
    public function normalizeSegments(mixed $segments): array
    {
        if (! is_array($segments)) {
            return [];
        }

        $normalized = [];

        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $type = (string) ($segment['type'] ?? '');

            if (! in_array($type, self::SEGMENT_TYPES, true)) {
                continue;
            }

            $normalized[] = match ($type) {
                self::SEGMENT_ROLE => [
                    'type' => $type,
                    'role_ids' => $this->intList($segment['role_ids'] ?? []),
                ],
                self::SEGMENT_SPEAKERS_BY_KIND => [
                    'type' => $type,
                    'kinds' => $this->stringList($segment['kinds'] ?? []),
                ],
                self::SEGMENT_WORKSHOP_ENROLLMENT,
                self::SEGMENT_WORKSHOP_INSTRUCTORS => [
                    'type' => $type,
                    'workshop_ids' => $this->intList($segment['workshop_ids'] ?? []),
                    'all_workshops' => (bool) ($segment['all_workshops'] ?? false),
                ],
                self::SEGMENT_INDIVIDUAL => [
                    'type' => $type,
                    'user_ids' => $this->intList($segment['user_ids'] ?? []),
                ],
                default => ['type' => $type],
            };
        }

        return array_values(array_filter($normalized, fn (array $segment) => $this->hasCriteria($segment)));
    }

    /**
     * Un segmento sin criterio (por ejemplo roles vacíos) no debe enviar a
     * nadie, así que se descarta antes de resolver.
     *
     * @param  array<string, mixed>  $segment
     */
    public function hasCriteria(array $segment): bool
    {
        return match ($segment['type'] ?? '') {
            self::SEGMENT_ALL_USERS => true,
            self::SEGMENT_ROLE => ($segment['role_ids'] ?? []) !== [],
            self::SEGMENT_SPEAKERS_BY_KIND => ($segment['kinds'] ?? []) !== [],
            self::SEGMENT_WORKSHOP_ENROLLMENT,
            self::SEGMENT_WORKSHOP_INSTRUCTORS => ($segment['workshop_ids'] ?? []) !== [] || (bool) ($segment['all_workshops'] ?? false),
            self::SEGMENT_INDIVIDUAL => ($segment['user_ids'] ?? []) !== [],
            default => false,
        };
    }

    /**
     * Une varios segmentos en una sola audiencia. Cada persona recibe un único
     * envío aunque pertenezca a varios grupos.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @return Collection<int, User>
     */
    public function resolveSegments(array $segments): Collection
    {
        return $this->resolveSegmentsDetailed($segments)['users'];
    }

    /**
     * Igual que resolveSegments pero además devuelve, para cada usuario, los
     * segmentos en los que coincidió. Se resuelve cada segmento una sola vez
     * (una consulta por segmento) en lugar de una por usuario y segmento.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @return array{users: Collection<int, User>, matches: array<int, array<int, array<string, mixed>>>}
     */
    public function resolveSegmentsDetailed(array $segments): array
    {
        $users = collect();
        $matches = [];

        foreach ($segments as $segment) {
            if (! is_array($segment) || ! $this->hasCriteria($segment)) {
                continue;
            }

            foreach ($this->usersForSegment($segment) as $user) {
                $matches[$user->id][] = $segment;
                $users->put($user->id, $user);
            }
        }

        return [
            'users' => $users->values(),
            'matches' => $matches,
        ];
    }

    /**
     * @param  array<string, mixed>  $segment
     * @return Collection<int, User>
     */
    public function usersForSegment(array $segment): Collection
    {
        return match ($segment['type'] ?? '') {
            self::SEGMENT_ALL_USERS => $this->allUsers(),
            self::SEGMENT_ROLE => $this->byRoles($segment['role_ids'] ?? []),
            self::SEGMENT_SPEAKERS_BY_KIND => $this->speakersByKinds($segment['kinds'] ?? []),
            self::SEGMENT_WORKSHOP_ENROLLMENT => $this->workshopEnrollments(
                $segment['workshop_ids'] ?? [],
                (bool) ($segment['all_workshops'] ?? false)
            ),
            self::SEGMENT_WORKSHOP_INSTRUCTORS => $this->workshopInstructors(
                $segment['workshop_ids'] ?? [],
                (bool) ($segment['all_workshops'] ?? false)
            ),
            self::SEGMENT_INDIVIDUAL => $this->individual($segment['user_ids'] ?? []),
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
        return $this->byRoles([$roleId]);
    }

    /**
     * @param  array<int, int>  $roleIds
     * @return Collection<int, User>
     */
    public function byRoles(array $roleIds): Collection
    {
        $roleIds = $this->intList($roleIds);

        if ($roleIds === []) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
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
        return $this->speakersByKinds([$kind]);
    }

    /**
     * @param  array<int, string>  $kinds
     * @return Collection<int, User>
     */
    public function speakersByKinds(array $kinds): Collection
    {
        $kinds = $this->stringList($kinds);

        if ($kinds === []) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('conferences', fn ($q) => $q
                ->whereIn('conferences.kind', $kinds)
                ->where('conference_members.role', 'speaker'))
            ->get();
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, User>
     */
    public function individual(array $userIds): Collection
    {
        $userIds = $this->intList($userIds);

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
        return $this->workshopEnrollments([$workshopId], false);
    }

    /**
     * @param  array<int, int>  $workshopIds
     * @return Collection<int, User>
     */
    public function workshopEnrollments(array $workshopIds, bool $allWorkshops = false): Collection
    {
        $workshopIds = $this->intList($workshopIds);

        if (! $allWorkshops && $workshopIds === []) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('enrolledWorkshops', fn ($q) => $q
                ->where('workshop_enrollments.status', 'enrolled')
                ->whereNull('workshops.deleted_at')
                ->when(! $allWorkshops, fn ($q) => $q->whereIn('workshops.id', $workshopIds)))
            ->get();
    }

    /**
     * @param  array<int, int>  $workshopIds
     * @return Collection<int, User>
     */
    public function workshopInstructors(array $workshopIds, bool $allWorkshops = false): Collection
    {
        $workshopIds = $this->intList($workshopIds);

        if (! $allWorkshops && $workshopIds === []) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('instructedWorkshops', fn ($q) => $q
                ->whereNull('workshops.deleted_at')
                ->when(! $allWorkshops, fn ($q) => $q->whereIn('workshops.id', $workshopIds)))
            ->get();
    }

    public function workshopName(int $workshopId): ?string
    {
        return Workshop::withTrashed()->whereKey($workshopId)->value('name');
    }

    /**
     * Nombres de los talleres indicados, para etiquetar la campaña.
     *
     * @param  array<int, int>  $workshopIds
     * @return array<int, string>
     */
    public function workshopNames(array $workshopIds, bool $allWorkshops = false): array
    {
        if ($allWorkshops) {
            return Workshop::query()->orderBy('day')->orderBy('start_time')->pluck('name')
                ->map(fn ($n) => (string) $n)->all();
        }

        $workshopIds = $this->intList($workshopIds);

        if ($workshopIds === []) {
            return [];
        }

        return Workshop::withTrashed()->whereIn('id', $workshopIds)
            ->orderBy('day')->orderBy('start_time')
            ->pluck('name')->map(fn ($n) => (string) $n)->all();
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

    /**
     * Payload de un destinatario dentro de una campaña combinada. Mantiene
     * {{ tipo_conferencia }} y {{ nombre_taller }} con la primera coincidencia
     * para no romper las plantillas existentes, y agrega las variantes en
     * plural y la lista de grupos.
     *
     * @param  array<int, array<string, mixed>>  $matchedSegments
     * @return array<string, mixed>
     */
    public function payloadFor(User $user, array $matchedSegments): array
    {
        $kinds = [];
        $workshopIds = [];
        $allWorkshops = false;
        $groupLabels = [];

        foreach ($matchedSegments as $segment) {
            $groupLabels[] = $this->segmentLabel($segment);

            foreach ((array) ($segment['kinds'] ?? []) as $kind) {
                $kinds[] = (string) $this->kindLabel((string) $kind, (string) $kind);
            }

            foreach ((array) ($segment['workshop_ids'] ?? []) as $workshopId) {
                $workshopIds[] = (int) $workshopId;
            }

            $allWorkshops = $allWorkshops || ! empty($segment['all_workshops']);
        }

        $kinds = array_values(array_unique($kinds));
        $workshops = $allWorkshops
            ? $this->workshopNames([], true)
            : $this->workshopNames($workshopIds);

        return array_merge($this->buildPayload(
            $user,
            $kinds[0] ?? null,
            $workshops[0] ?? null,
        ), [
            'tipos_conferencia' => implode(', ', $kinds),
            'nombres_talleres' => implode(', ', $workshops),
            'grupos' => implode(' + ', array_unique($groupLabels)),
        ]);
    }

    public function labelFor(NotificationSend $send): string
    {
        $value = $send->audience_value ?? [];

        if ($send->audience_type === NotificationSend::AUDIENCE_SEGMENTS) {
            $labels = array_map(
                fn ($segment) => $this->segmentLabel(is_array($segment) ? $segment : []),
                (array) ($value['segments'] ?? [])
            );

            if ($labels === []) {
                return 'Sin audiencia';
            }

            if (count($labels) === 1) {
                return $labels[0];
            }

            return count($labels).' grupos: '.implode(' + ', $labels);
        }

        return match ($send->audience_type) {
            'all_users' => 'Todos los usuarios',
            'role' => 'Rol: '.($this->roleName((int) ($value['role_id'] ?? 0)) ?? '?'),
            'speakers_by_kind' => 'Speakers por tipo: '.$this->kindLabel($value['kind'] ?? null, $value['kind'] ?? '?'),
            'individual' => 'Individual ('.(int) $send->recipient_count.')',
            'workshop_enrollment' => 'Inscritos en taller: '.($this->workshopName((int) ($value['workshop_id'] ?? 0)) ?? '?'),
            default => $send->audience_type,
        };
    }

    /**
     * Etiqueta legible de un segmento, reutilizada en el historial y en la
     * variable {{ grupos }} del correo.
     *
     * @param  array<string, mixed>  $segment
     */
    public function segmentLabel(array $segment): string
    {
        return match ($segment['type'] ?? '') {
            self::SEGMENT_ALL_USERS => 'Todos los usuarios',
            self::SEGMENT_ROLE => 'Roles: '.$this->roleNames($segment['role_ids'] ?? []),
            self::SEGMENT_SPEAKERS_BY_KIND => 'Speakers: '.$this->kindLabels($segment['kinds'] ?? []),
            self::SEGMENT_WORKSHOP_ENROLLMENT => 'Inscritos: '.$this->workshopSummary($segment),
            self::SEGMENT_WORKSHOP_INSTRUCTORS => 'Instructores: '.$this->workshopSummary($segment),
            self::SEGMENT_INDIVIDUAL => 'Usuarios sueltos: '.count((array) ($segment['user_ids'] ?? [])),
            default => 'Grupo',
        };
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function roleNames(array $roleIds): string
    {
        $roleIds = $this->intList($roleIds);

        if ($roleIds === []) {
            return '?';
        }

        $names = Role::query()->whereIn('id', $roleIds)->pluck('name')
            ->map(fn ($n) => (string) $n)->all();

        return $names === [] ? '?' : implode(', ', $names);
    }

    /**
     * @param  array<int, string>  $kinds
     */
    private function kindLabels(array $kinds): string
    {
        $kinds = $this->stringList($kinds);

        if ($kinds === []) {
            return '?';
        }

        $labels = array_map(fn (string $kind) => (string) $this->kindLabel($kind, $kind), $kinds);

        return implode(', ', array_unique($labels));
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function workshopSummary(array $segment): string
    {
        if (! empty($segment['all_workshops'])) {
            return 'todos los talleres';
        }

        $names = $this->workshopNames((array) ($segment['workshop_ids'] ?? []));

        return $names === [] ? '?' : implode(', ', $names);
    }

    private function roleName(int $roleId): ?string
    {
        return Role::query()->whereKey($roleId)->value('name');
    }

    /**
     * @return array<int, int>
     */
    private function intList(mixed $values): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', is_array($values) ? $values : []),
            fn (int $value) => $value > 0
        )));
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                fn ($value) => trim((string) $value),
                is_array($values) ? $values : []
            ),
            fn (string $value) => $value !== ''
        )));
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
