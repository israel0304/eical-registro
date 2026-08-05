<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Setting;
use App\Models\User;
use App\Services\CertificateRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckinController extends Controller
{
    public function __construct(private readonly CertificateRenderer $renderer) {}

    public function index(Request $request)
    {
        abort_if(! $request->user()->isAdmin() && ! $request->user()->hasPermission('checkin.scan'), 403);

        $today = Attendance::query()
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->with(['user:id,first_name,last_name,dni,affiliation,profile_photo_path,email'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Attendance $attendance) {
                $attendance->setAttribute('certificate_issued', $this->hasEventCertificate($attendance->user_id));

                return $attendance;
            });

        return Inertia::render('Checkin/Index', [
            'attendances' => $today,
            'checkinEnabled' => (bool) Setting::query()->where('key', 'evento_checkin_enabled')->value('value'),
        ]);
    }

    public function register(Request $request)
    {
        abort_if(! $request->user()->isAdmin() && ! $request->user()->hasPermission('checkin.scan'), 403);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:200'],
        ]);

        $token = trim($validated['token']);

        if (! Setting::query()->where('key', 'evento_checkin_enabled')->value('value')) {
            return response()->json([
                'success' => false,
                'message' => 'El check-in del evento está deshabilitado.',
            ], 422);
        }

        $user = User::query()->where('checkin_token', $token)->first();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Código de gafete no válido.',
            ], 404);
        }

        $existing = Attendance::query()
            ->where('user_id', $user->id)
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'already' => true,
                'message' => 'El participante ya tenía asistencia registrada.',
                'user' => $this->userPayload($user),
            ], 200);
        }

        Attendance::create([
            'user_id' => $user->id,
            'event_day' => now()->format('Y-m-d'),
            'registered_by' => $request->user()->id,
        ]);

        $certificate = $this->renderer->issueEvent($user);

        return response()->json([
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            'user' => $this->userPayload($user),
            'certificate_issued' => $certificate !== null,
        ], 200);
    }

    public function lookup(Request $request)
    {
        abort_if(! $request->user()->isAdmin() && ! $request->user()->hasPermission('checkin.scan'), 403);

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
        return [
            'id' => $user->id,
            'name' => trim($user->first_name.' '.$user->last_name),
            'dni' => $user->dni,
            'affiliation' => $user->affiliation,
            'email' => $user->email,
            'photo' => $user->profile_photo_path
                ? asset('storage/'.$user->profile_photo_path)
                : null,
            'checked_in' => Attendance::query()
                ->where('user_id', $user->id)
                ->whereNull('workshop_id')
                ->whereNull('presentation_id')
                ->exists(),
        ];
    }

    private function hasEventCertificate(int $userId): bool
    {
        return Certificate::query()
            ->where('user_id', $userId)
            ->where('event_type', 'event')
            ->exists();
    }
}
