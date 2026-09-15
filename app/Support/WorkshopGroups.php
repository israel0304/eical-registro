<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class WorkshopGroups
{
    private const MONTHS = [
        '',
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre',
    ];

    private ?Collection $sessions = null;

    /**
     * Resolve the divided-workshop group to which a workshop belongs, or null
     * when the workshop is not part of a split course.
     */
    public static function for(Workshop $workshop): ?self
    {
        if ($workshop->parent_workshop_id !== null) {
            $parent = $workshop->parentWorkshop;

            return $parent !== null ? new self($parent) : null;
        }

        if ($workshop->childWorkshops()->exists()) {
            return new self($workshop);
        }

        return null;
    }

    public function __construct(private readonly Workshop $representative) {}

    /** The workshop that acts as the group header (the parent session). */
    public function representative(): Workshop
    {
        return $this->representative;
    }

    public function groupId(): int
    {
        return $this->representative->id;
    }

    /** Negative event_id that guarantees the consolidated certificate key never collides with per-session keys. */
    public function signedGroupId(): int
    {
        return -$this->representative->id;
    }

    public function isDivided(): bool
    {
        return $this->sessions()->count() > 1;
    }

    /** @return Collection<int, Workshop> Sessions of the course, ordered by day/time. */
    public function sessions(): Collection
    {
        return $this->sessions ??= $this->representative->childWorkshops()
            ->get()
            ->push($this->representative)
            ->sortBy([
                ['day', 'asc'],
                ['start_time', 'asc'],
            ])
            ->values();
    }

    /** Total duration of the course in hours, formatted for display ("4" or "3,5"). */
    public function totalHours(): string
    {
        $total = $this->sessions()->reduce(
            fn (float $carry, Workshop $w) => $carry + self::duration($w),
            0.0,
        );

        return self::formatHours($total);
    }

    /** Course title without the leading "Sesión N:" prefix. */
    public function baseTitle(): string
    {
        $name = trim((string) $this->representative->name);

        $stripped = preg_replace('/^\s*sesi[oó]n\s+[a-z0-9]+\s*[:.\-]?\s*/iu', '', $name);

        if ($stripped === null || trim($stripped) === '') {
            return $name;
        }

        return trim($stripped);
    }

    /** Human-readable date span of the sessions, e.g. "23 y 24 de septiembre de 2026". */
    public function dateRange(): string
    {
        $days = $this->sessions()
            ->pluck('day')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($days === []) {
            return '';
        }

        if (count($days) === 1) {
            return self::formatSpanishDate((string) $days[0]);
        }

        $first = self::parseDay((string) $days[0]);

        $sameMonth = $first->format('m-Y') === self::parseDay((string) $days[array_key_last($days)])->format('m-Y');

        if ($sameMonth) {
            $dayNumbers = array_map(
                fn (string $d) => self::parseDay($d)->format('j'),
                $days,
            );

            return implode(' y ', $dayNumbers).' de '.self::MONTHS[(int) $first->format('n')].' de '.$first->format('Y');
        }

        $formatted = array_map(
            fn (string $d) => self::formatSpanishDate($d),
            $days,
        );

        return implode(' y ', array_values(array_unique($formatted)));
    }

    /** True when the user meets the requirements of every session of the course. */
    public function qualifiedFor(User $user): bool
    {
        return $this->sessions()->isNotEmpty()
            && ($this->hasFullInstructor($user) || $this->hasFullAttendance($user));
    }

    public function hasFullInstructor(User $user): bool
    {
        return $this->sessions()->every(function (Workshop $w) use ($user) {
            return $w->instructors()
                ->where('users.id', $user->id)
                ->wherePivot('activated', true)
                ->exists();
        });
    }

    public function hasFullAttendance(User $user): bool
    {
        return $this->sessions()->every(function (Workshop $w) use ($user) {
            $enrolled = $w->enrollments()
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->exists();

            return $enrolled && $w->attendances()->where('user_id', $user->id)->exists();
        });
    }

    public static function duration(Workshop $workshop): float
    {
        $start = strtotime($workshop->start_time);
        $end = strtotime($workshop->end_time);

        if ($start === false || $end === false || $end <= $start) {
            return 0.0;
        }

        return ($end - $start) / 3600;
    }

    public static function formatHours(float $hours): string
    {
        $rounded = round($hours, 2);

        if (floor($rounded) == $rounded) {
            return (string) (int) $rounded;
        }

        return number_format(round($rounded, 1), 1, ',', '');
    }

    public static function formatSpanishDate(string $date): string
    {
        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return $date;
        }

        return date('j', $timestamp).' de '.self::MONTHS[(int) date('n', $timestamp)].' de '.date('Y', $timestamp);
    }

    private static function parseDay(string $day): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $day) ?? CarbonImmutable::parse($day);
    }
}
