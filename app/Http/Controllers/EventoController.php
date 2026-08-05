<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Setting;
use App\Models\User;
use App\Services\CertificateRenderer;
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
            ->with(['user:id,first_name,last_name,dni,affiliation,email', 'registeredBy:id,first_name,last_name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Attendance $attendance) {
                $attendance->setAttribute('certificate_issued', $this->hasEventCertificate($attendance->user_id));

                return $attendance;
            });

        return Inertia::render('Evento/Index', [
            'settings' => [
                'evento_nombre' => Setting::query()->where('key', 'evento_nombre')->value('value') ?? '',
                'evento_checkin_enabled' => (bool) Setting::query()->where('key', 'evento_checkin_enabled')->value('value'),
                'evento_min_dias' => (int) (Setting::query()->where('key', 'evento_min_dias')->value('value') ?? 2),
            ],
            'attendances' => $attendances,
            'total_checked_in' => $attendances->count(),
            'constancias_issued' => $attendances->where('certificate_issued', true)->count(),
            'total_users' => User::count(),
        ]);
    }

    public function update(Request $request)
    {
        abort_if(! $request->user()->isAdmin() && ! $request->user()->hasPermission('constancias.evento.manage'), 403);

        $validated = $request->validate([
            'evento_nombre' => ['required', 'string', 'max:255'],
            'evento_checkin_enabled' => ['nullable', 'boolean'],
            'evento_min_dias' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        Setting::updateOrCreate(['key' => 'evento_nombre'], ['value' => $validated['evento_nombre']]);
        Setting::updateOrCreate(['key' => 'evento_checkin_enabled'], ['value' => (bool) ($validated['evento_checkin_enabled'] ?? false) ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'evento_min_dias'], ['value' => (string) $validated['evento_min_dias']]);

        return back()->with('success', 'Configuración del evento actualizada.');
    }

    public function generateConstancias(Request $request)
    {
        abort_if(! $request->user()->isAdmin() && ! $request->user()->hasPermission('constancias.evento.manage'), 403);

        $userIds = Attendance::query()
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->pluck('user_id')
            ->unique();

        $generated = 0;
        foreach ($userIds as $userId) {
            $user = User::find($userId);

            if ($user === null) {
                continue;
            }

            if ($this->renderer->issueEvent($user) !== null) {
                $generated++;
            }
        }

        return back()->with('success', "Constancias de evento generadas/verificadas para {$generated} asistentes.");
    }

    private function hasEventCertificate(int $userId): bool
    {
        return Certificate::query()
            ->where('user_id', $userId)
            ->where('event_type', 'event')
            ->exists();
    }
}
