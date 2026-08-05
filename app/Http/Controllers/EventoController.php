<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Setting;
use App\Models\User;
use App\Services\CertificateRenderer;
use App\Support\EventSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventoController extends Controller
{
    public function __construct(private readonly CertificateRenderer $renderer) {}

    public function index(Request $request)
    {
        abort_if(! $request->user()->isAdmin() && ! $request->user()->hasPermission('constancias.evento.manage'), 403);

        $attendances = Attendance::query()
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->when($request->filled('day'), function ($query) use ($request) {
                $query->where('event_day', $request->input('day'));
            })
            ->with(['user:id,first_name,last_name,dni,affiliation,email', 'registeredBy:id,first_name,last_name'])
            ->orderByDesc('event_day')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Attendance $attendance) {
                $attendance->setAttribute('certificate_issued', $this->hasEventCertificate($attendance->user_id));
                $attendance->setAttribute('days_attended', EventSettings::attendedDays($attendance->user_id));
                $attendance->setAttribute('qualifies', EventSettings::qualifies($attendance->user_id));
                $attendance->setAttribute('day_label', EventSettings::dayLabel($attendance->event_day));

                return $attendance;
            });

        $dayCounts = Attendance::query()
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->selectRaw('event_day, COUNT(*) as total')
            ->groupBy('event_day')
            ->get()
            ->keyBy('event_day');

        $days = [];
        foreach (EventSettings::eventDays() as $date) {
            $days[] = [
                'date' => $date,
                'label' => EventSettings::dayLabel($date),
                'count' => (int) ($dayCounts->get($date)?->total ?? 0),
            ];
        }

        foreach ($dayCounts as $date => $row) {
            if (! in_array($date, EventSettings::eventDays(), true)) {
                $days[] = [
                    'date' => $date,
                    'label' => EventSettings::dayLabel($date) ?: $date,
                    'count' => (int) $row->total,
                ];
            }
        }

        usort($days, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return Inertia::render('Evento/Index', [
            'settings' => [
                'evento_nombre' => EventSettings::nombre(),
                'evento_checkin_enabled' => EventSettings::checkinEnabled(),
                'evento_checkin_time_restricted' => EventSettings::checkinTimeRestricted(),
                'evento_min_dias' => EventSettings::minDays(),
                'evento_fecha_inicio' => EventSettings::startDate(),
                'evento_fecha_fin' => EventSettings::endDate(),
                'total_days' => EventSettings::totalDays(),
            ],
            'attendances' => $attendances,
            'days' => $days,
            'selected_day' => $request->input('day'),
            'total_checked_in' => Attendance::query()
                ->whereNull('workshop_id')
                ->whereNull('presentation_id')
                ->distinct()
                ->count('user_id'),
            'constancias_issued' => Certificate::query()
                ->where('event_type', 'event')
                ->distinct()
                ->count('user_id'),
            'total_users' => User::count(),
        ]);
    }

    public function update(Request $request)
    {
        abort_if(! $request->user()->isAdmin() && ! $request->user()->hasPermission('constancias.evento.manage'), 403);

        $validated = $request->validate([
            'evento_nombre' => ['required', 'string', 'max:255'],
            'evento_checkin_enabled' => ['nullable', 'boolean'],
            'evento_checkin_time_restricted' => ['nullable', 'boolean'],
            'evento_min_dias' => ['required', 'integer', 'min:1', 'max:31', function ($attribute, $value, $fail) use ($request) {
                $start = $request->input('evento_fecha_inicio');
                $end = $request->input('evento_fecha_fin');

                if ($start === null || $start === '' || $end === null || $end === '') {
                    return;
                }

                try {
                    $totalDays = CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;
                } catch (\Throwable) {
                    return;
                }

                if ($value > $totalDays) {
                    $fail("El número de días mínimos no puede ser mayor al total de días del evento ({$totalDays}).");
                }
            }],
            'evento_fecha_inicio' => ['nullable', 'date'],
            'evento_fecha_fin' => ['nullable', 'date', 'after_or_equal:evento_fecha_inicio'],
        ]);

        Setting::updateOrCreate(['key' => 'evento_nombre'], ['value' => $validated['evento_nombre']]);
        Setting::updateOrCreate(['key' => 'evento_checkin_enabled'], ['value' => (bool) ($validated['evento_checkin_enabled'] ?? false) ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'evento_checkin_time_restricted'], ['value' => (bool) ($validated['evento_checkin_time_restricted'] ?? true) ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'evento_min_dias'], ['value' => (string) $validated['evento_min_dias']]);
        Setting::updateOrCreate(['key' => 'evento_fecha_inicio'], ['value' => $validated['evento_fecha_inicio'] ?? '']);
        Setting::updateOrCreate(['key' => 'evento_fecha_fin'], ['value' => $validated['evento_fecha_fin'] ?? '']);

        return back()->with('success', 'Configuración del evento actualizada.');
    }

    public function generateConstancias(Request $request)
    {
        abort_if(! $request->user()->isAdmin() && ! $request->user()->hasPermission('constancias.evento.manage'), 403);

        $userIds = Attendance::query()
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->distinct()
            ->pluck('user_id');

        $generated = 0;
        $skipped = 0;

        foreach ($userIds as $userId) {
            $user = User::find($userId);

            if ($user === null) {
                continue;
            }

            if (! EventSettings::qualifies($user->id)) {
                $skipped++;

                continue;
            }

            if ($this->renderer->issueEvent($user) !== null) {
                $generated++;
            }
        }

        return back()->with('success', "Constancias de evento generadas/verificadas para {$generated} asistentes".($skipped > 0 ? " (se omitieron {$skipped} por no cumplir los días mínimos)." : '.'));
    }

    public function destroyAttendance(Request $request, Attendance $attendance)
    {
        abort_if(! $request->user()->isAdmin() && ! $request->user()->hasPermission('constancias.evento.manage'), 403);

        if ($attendance->workshop_id !== null || $attendance->presentation_id !== null) {
            abort(404);
        }

        $attendance->delete();

        return back()->with('success', 'Registro de asistencia eliminado.');
    }

    private function hasEventCertificate(int $userId): bool
    {
        return Certificate::query()
            ->where('user_id', $userId)
            ->where('event_type', 'event')
            ->exists();
    }
}
