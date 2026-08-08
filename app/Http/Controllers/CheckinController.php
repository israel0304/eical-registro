<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\User;
use App\Services\CertificateRenderer;
use App\Support\EventSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckinController extends Controller
{
    public function __construct(private readonly CertificateRenderer $renderer) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('checkin.scan'), 403);

        $today = now()->format('Y-m-d');

        $attendances = Attendance::query()
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->where('event_day', $today)
            ->with(['user:id,first_name,last_name,dni,affiliation,profile_photo_path,email'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Attendance $attendance) {
                $attendance->setAttribute('certificate_issued', $this->hasEventCertificate($attendance->user_id));

                return $attendance;
            });

        return Inertia::render('Checkin/Index', [
            'attendances' => $attendances,
            'checkinEnabled' => EventSettings::checkinEnabled(),
            'dayLabel' => EventSettings::dayLabel($today) ?: $today,
            'requiredDays' => EventSettings::minDays(),
        ]);
    }

    public function register(Request $request)
    {
        abort_unless($request->user()->can('checkin.scan'), 403);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:200'],
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
                'message' => 'No se encontró ningún participante con ese código. Busca por nombre, DNI o escanea el gafete.',
            ], 200);
        }

        $today = now()->format('Y-m-d');

        if (EventSettings::checkinTimeRestricted() && ! $this->isWithinEventDates($today)) {
            return response()->json([
                'success' => false,
                'message' => 'El check-in solo está disponible durante las fechas del evento ('.EventSettings::startDate().' a '.EventSettings::endDate().').',
            ], 422);
        }

        $existing = Attendance::query()
            ->where('user_id', $user->id)
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->where('event_day', $today)
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'already' => true,
                'message' => 'El participante ya tenía asistencia registrada hoy.',
                'user' => $this->userPayload($user),
            ], 200);
        }

        Attendance::create([
            'user_id' => $user->id,
            'event_day' => $today,
            'registered_by' => $request->user()->id,
        ]);

        $qualifies = EventSettings::qualifies($user->id);
        $certificate = $qualifies ? $this->renderer->issueEvent($user) : null;

        return response()->json([
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            'user' => $this->userPayload($user),
            'day_label' => EventSettings::dayLabel($today),
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
        ]);

        $search = trim($validated['search']);

        $users = User::query()
            ->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('dni', 'like', "%{$search}%")
                    ->orWhere('checkin_token', $search);
            })
            ->limit(10)
            ->get()
            ->map(fn (User $user) => $this->userPayload($user));

        return response()->json($users);
    }

    private function userPayload(User $user): array
    {
        $today = now()->format('Y-m-d');

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
                ->where('event_day', $today)
                ->exists(),
            'days_attended' => EventSettings::attendedDays($user->id),
        ];
    }

    private function hasEventCertificate(int $userId): bool
    {
        return Certificate::query()
            ->where('user_id', $userId)
            ->where('event_type', 'event')
            ->exists();
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
