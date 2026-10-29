<?php

namespace App\Support;

use App\Models\Conference;
use App\Models\ModeradorConstancia;
use App\Models\ParticipationType;
use App\Models\User;
use App\Models\Workshop;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

class ManualCertificateOptions
{
    /**
     * @return array{consolidated: bool, eligible: bool, options: array<int, array{id: int, label: string, subtitle: string, event_type: string}>}
     */
    public static function for(User $user, ParticipationType $type): array
    {
        return match ($type->event_kind) {
            'workshop' => self::workshopResult($user, $type),
            'conference' => self::conferenceResult($user, $type),
            default => ['consolidated' => false, 'eligible' => false, 'options' => []],
        };
    }

    public static function moderatorEligible(User $user): bool
    {
        if (! $user->moderatedAnyActivity()) {
            return false;
        }

        return (bool) ModeradorConstancia::query()
            ->where('user_id', $user->id)
            ->value('activated');
    }

    /**
     * @return array{consolidated: bool, eligible: bool, options: array<int, array{id: int, label: string, subtitle: string, event_type: string}>}
     */
    private static function workshopResult(User $user, ParticipationType $type): array
    {
        $options = self::workshopOptions($user, $type);

        return [
            'consolidated' => false,
            'eligible' => $options !== [],
            'options' => $options,
        ];
    }

    /**
     * @return array{consolidated: bool, eligible: bool, options: array<int, array{id: int, label: string, subtitle: string, event_type: string}>}
     */
    private static function conferenceResult(User $user, ParticipationType $type): array
    {
        if ($type->role === 'moderator') {
            return [
                'consolidated' => true,
                'eligible' => self::moderatorEligible($user),
                'options' => [],
            ];
        }

        $options = self::conferenceOptions($user, $type);

        return [
            'consolidated' => false,
            'eligible' => $options !== [],
            'options' => $options,
        ];
    }

    /**
     * @return array<int, array{id: int, label: string, subtitle: string, event_type: string}>
     */
    private static function workshopOptions(User $user, ParticipationType $type): array
    {
        $workshops = self::eligibleWorkshops($user, $type);

        if ($workshops->isEmpty()) {
            return [];
        }

        $options = [];
        $seenGroups = [];

        foreach ($workshops as $workshop) {
            $group = WorkshopGroups::for($workshop);

            if ($group !== null && $group->isDivided() && self::qualifiesForGroup($group, $user, $type)) {
                $groupKey = $group->groupId();

                if (isset($seenGroups[$groupKey])) {
                    continue;
                }

                $seenGroups[$groupKey] = true;
                $representative = $group->representative();

                $options[] = [
                    'id' => $representative->id,
                    'label' => $group->baseTitle(),
                    'subtitle' => self::joinParts([$group->dateRange(), $group->totalHours().' h']),
                    'event_type' => WorkshopGroups::GROUPED_EVENT_TYPE,
                ];

                continue;
            }

            $options[] = [
                'id' => $workshop->id,
                'label' => (string) $workshop->name,
                'subtitle' => self::workshopSubtitle($workshop),
                'event_type' => 'workshop',
            ];
        }

        return self::uniqueOptions($options);
    }

    /**
     * @return Collection<int, Workshop>
     */
    private static function eligibleWorkshops(User $user, ParticipationType $type)
    {
        if ($type->role === 'instructor') {
            return $user->instructedWorkshops()
                ->wherePivot('activated', true)
                ->orderBy('workshops.day')
                ->orderBy('workshops.start_time')
                ->get();
        }

        if ($type->role === 'enrolled_attendance') {
            return $user->enrolledWorkshops()
                ->wherePivot('status', 'enrolled')
                ->whereHas('attendances', fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('workshops.day')
                ->orderBy('workshops.start_time')
                ->get();
        }

        return $user->enrolledWorkshops()->whereRaw('1 = 0')->get();
    }

    private static function qualifiesForGroup(WorkshopGroups $group, User $user, ParticipationType $type): bool
    {
        return $type->role === 'instructor'
            ? $group->hasFullInstructor($user)
            : $group->hasFullAttendance($user);
    }

    /**
     * @return array<int, array{id: int, label: string, subtitle: string, event_type: string}>
     */
    private static function conferenceOptions(User $user, ParticipationType $type): array
    {
        if ($type->role !== 'speaker') {
            return [];
        }

        $query = Conference::query()
            ->whereHas('members', function ($query) use ($user) {
                $query->where('users.id', $user->id)
                    ->where('conference_members.role', 'speaker')
                    ->where('conference_members.activated', true);
            })
            ->orderBy('day')
            ->orderBy('start_time');

        if ($type->kind !== null) {
            $query->where('kind', $type->kind);
        }

        return $query->get()
            ->map(fn (Conference $conference) => [
                'id' => $conference->id,
                'label' => (string) $conference->title,
                'subtitle' => self::conferenceSubtitle($conference),
                'event_type' => 'conference',
            ])
            ->all();
    }

    private static function workshopSubtitle(Workshop $workshop): string
    {
        $hours = WorkshopGroups::formatHours(WorkshopGroups::duration($workshop));

        return self::joinParts([
            self::dateString($workshop->day),
            $hours !== '0' ? $hours.' h' : '',
            (string) ($workshop->location ?? ''),
        ]);
    }

    private static function conferenceSubtitle(Conference $conference): string
    {
        return self::joinParts([
            self::dateString($conference->day),
            (string) ($conference->location ?? ''),
        ]);
    }

    private static function dateString(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $value = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : (string) $date;

        return WorkshopGroups::formatSpanishDate($value);
    }

    /**
     * @param  array<int, string>  $parts
     */
    private static function joinParts(array $parts): string
    {
        return implode(' · ', array_values(array_filter($parts, fn (string $part) => trim($part) !== '')));
    }

    /**
     * @param  array<int, array{id: int, label: string, subtitle: string, event_type: string}>  $options
     * @return array<int, array{id: int, label: string, subtitle: string, event_type: string}>
     */
    private static function uniqueOptions(array $options): array
    {
        $unique = [];

        foreach ($options as $option) {
            $unique[$option['id']] = $option;
        }

        return array_values($unique);
    }
}
