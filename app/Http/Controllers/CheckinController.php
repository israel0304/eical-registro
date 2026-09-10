<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\User;
use App\Services\CertificateRenderer;
use App\Support\EventSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckinController extends Controller
{
    public function __construct(private readonly CertificateRenderer $renderer) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('checkin.scan'), 403);

        $day = $this->normalizedDay($request->query('day')) ?? now()->format('Y-m-d');

        $attendances = Attendance::query()
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->where('event_day', $day)
            ->with(['user:id,first_name,last_name,dni,affiliation,profile_photo_path,email'])
            ->orderByDesc('created_at')
            ->get();

        $certIssuedByIds = Certificate::query()
            ->whereIn('user_id', $attendances->pluck('user_id'))
            ->where('event_type', 'event')
            ->pluck('user_id')
            ->flip()
            ->all();

        $attendances = $attendances->map(function (Attendance $attendance) use ($certIssuedByIds) {
            $attendance->setAttribute('certificate_issued', isset($certIssuedByIds[$attendance->user_id]));

            return $attendance;
        });

        return Inertia::render('Checkin/Index', [
            'attendances' => $attendances,
            'checkinEnabled' => EventSettings::checkinEnabled(),
            'day' => $day,
            'dayLabel' => EventSettings::dayLabel($day) ?: $day,
            'eventDays' => $this->eventDaysOptions(),
            'requiredDays' => EventSettings::minDays(),
        ]);
    }

    public function register(Request $request)
    {
        abort_unless($request->user()->can('checkin.scan'), 403);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:200'],
            'day' => ['nullable', 'date'],
        ]);

        $token = trim($validated['token']);

        if (! EventSettings::checkinEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'El check-in del evento está deshabilitado.',
            ], 422);
        }

        $user = User::query()->where('checkin_token', $token)->first();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'not_found' => true,
                'message' => 'No se encontró ningún participante con ese código. Busca por nombre, DNI o escanea el gafete.',
            ], 200);
        }

        $requestedDay = trim((string) ($validated['day'] ?? ''));
        $today = now()->format('Y-m-d');

        if ($requestedDay !== '') {
            try {
                $requestedDay = CarbonImmutable::parse($requestedDay)->format('Y-m-d');
            } catch (\Throwable) {
                return response()->json([
                    'success' => false,
                    'message' => 'La fecha seleccionada no es válida.',
                ], 422);
            }
        }

        $day = $requestedDay === '' ? $today : $requestedDay;

        if ($day !== $today) {
            if ($day > $today || ! in_array($day, EventSettings::eventDays(), true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La fecha seleccionada no está dentro de las fechas del evento.',
                ], 422);
            }
        } elseif (EventSettings::checkinTimeRestricted() && ! $this->isWithinEventDates($today)) {
            return response()->json([
                'success' => false,
                'message' => 'El check-in solo está disponible durante las fechas del evento ('.EventSettings::startDate().' a '.EventSettings::endDate().').',
            ], 422);
        }

        $existing = Attendance::query()
            ->where('user_id', $user->id)
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->where('event_day', $day)
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'already' => true,
                'message' => 'El participante ya tenía asistencia registrada en ese día.',
                'day' => $day,
                'day_label' => EventSettings::dayLabel($day),
                'user' => $this->userPayload($user, $day),
            ], 200);
        }

        Attendance::create([
            'user_id' => $user->id,
            'event_day' => $day,
            'registered_by' => $request->user()->id,
        ]);

        $qualifies = EventSettings::qualifies($user->id);
        $certificate = $qualifies ? $this->renderer->issueEvent($user) : null;

        return response()->json([
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            'day' => $day,
            'user' => $this->userPayload($user, $day),
            'day_label' => EventSettings::dayLabel($day),
            'days_attended' => EventSettings::attendedDays($user->id),
            'required_days' => EventSettings::minDays(),
            'qualifies' => $qualifies,
            'certificate_issued' => $certificate !== null,
        ], 200);
    }

    public function lookup(Request $request)
    {
        abort_unless($request->user()->can('checkin.scan'), 403);

        $validated = $request->validate([
            'search' => ['required', 'string', 'max:255'],
            'day' => ['nullable', 'date'],
        ]);

        $search = trim($validated['search']);
        $day = $this->normalizedDay($validated['day'] ?? null) ?? now()->format('Y-m-d');

        $users = User::query()
            ->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('dni', 'like', "%{$search}%")
                    ->orWhere('checkin_token', $search);
            })
            ->limit(10)
            ->get()
            ->map(fn (User $user) => $this->userPayload($user, $day));

        return response()->json($users);
    }

    private function userPayload(User $user, ?string $day = null): array
    {
        $day ??= now()->format('Y-m-d');

        return [
            'id' => $user->id,
            'name' => trim($user->first_name.' '.$user->last_name),
            'dni' => $user->dni,
            'affiliation' => $user->affiliation,
            'email' => $user->email,
            'photo' => $user->profile_photo_path
                ? asset('storage/'.$user->profile_photo_path)
                : null,
            'checkin_token' => $user->checkin_token,
            'checked_in' => Attendance::query()
                ->where('user_id', $user->id)
                ->whereNull('workshop_id')
                ->whereNull('presentation_id')
                ->where('event_day', $day)
                ->exists(),
            'days_attended' => EventSettings::attendedDays($user->id),
        ];
    }

    private function eventDaysOptions(): array
    {
        $today = now()->format('Y-m-d');

        return collect(EventSettings::eventDays())
            ->filter(fn (string $date) => $date <= $today)
            ->map(fn (string $date) => [
                'date' => $date,
                'label' => EventSettings::dayLabel($date) ?: $date,
                'is_today' => $date === $today,
            ])
            ->values()
            ->all();
    }

    private function normalizedDay(?string $day): ?string
    {
        if ($day === null || $day === '') {
            return now()->format('Y-m-d');
        }

        try {
            $day = CarbonImmutable::parse($day)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }

        return in_array($day, EventSettings::eventDays(), true) && $day <= now()->format('Y-m-d')
            ? $day
            : null;
    }

    private function isWithinEventDates(string $date): bool
    {
        $start = EventSettings::startDate();
        $end = EventSettings::endDate();

        if ($start === null || $end === null) {
            return true;
        }

        return $date >= $start && $date <= $end;
    }
}
