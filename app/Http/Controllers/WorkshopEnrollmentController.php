<?php

namespace App\Http\Controllers;

use App\Mail\WorkshopEnrollmentConfirmation;
use App\Models\Attendance;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class WorkshopEnrollmentController extends Controller
{
    public function store(Request $request, Workshop $workshop)
    {
        $user = $request->user();

        $existingEnrollment = WorkshopEnrollment::where('user_id', $user->id)
            ->where('workshop_id', $workshop->id)
            ->first();

        if ($existingEnrollment) {
            if ($existingEnrollment->status === 'enrolled') {
                return back()->withErrors(['error' => 'Ya estás inscrito en este taller.']);
            }

            if ($existingEnrollment->status === 'cancelled') {
                if (! $workshop->hasAvailableSpots()) {
                    return back()->withErrors(['error' => 'No hay cupos disponibles.']);
                }

                $existingEnrollment->update([
                    'status' => 'enrolled',
                    'enrolled_at' => now(),
                ]);

                Mail::to($user->email)->send(new WorkshopEnrollmentConfirmation($workshop, $user));

                return back()->with('success', 'Inscripción reactivada correctamente.');
            }
        }

        if (! $workshop->hasAvailableSpots()) {
            return back()->withErrors(['error' => 'No hay cupos disponibles en este taller.']);
        }

        WorkshopEnrollment::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);

        Mail::to($user->email)->send(new WorkshopEnrollmentConfirmation($workshop, $user));

        return back()->with('success', 'Inscrito correctamente en el taller.');
    }

    public function destroy(Request $request, Workshop $workshop)
    {
        $enrollment = WorkshopEnrollment::where('user_id', $request->user()->id)
            ->where('workshop_id', $workshop->id)
            ->where('status', 'enrolled')
            ->first();

        if (! $enrollment) {
            return back()->withErrors(['error' => 'No estás inscrito en este taller.']);
        }

        $hasAttendance = Attendance::where('user_id', $request->user()->id)
            ->where('workshop_id', $workshop->id)
            ->exists();

        if ($hasAttendance) {
            return back()->withErrors(['error' => 'No puedes cancelar: ya tienes asistencia confirmada en este taller.']);
        }

        $workshopStart = Carbon::parse("{$workshop->day} {$workshop->start_time}");

        if (now()->gte($workshopStart->subMinutes(10))) {
            return back()->withErrors(['error' => 'No puedes cancelar: faltan 10 minutos o menos para el inicio del taller.']);
        }

        $enrollment->update(['status' => 'cancelled']);

        return back()->with('success', 'Inscripción cancelada correctamente.');
    }

    public function adminDestroy(Request $request, Workshop $workshop, WorkshopEnrollment $enrollment)
    {
        abort_unless($request->user()->can('workshops.enrollments'), 403);

        if ($enrollment->workshop_id !== $workshop->id) {
            abort(404);
        }

        $enrollment->update(['status' => 'cancelled']);

        return back()->with('success', 'Inscripción cancelada correctamente.');
    }

    public function myWorkshops(Request $request)
    {
        $user = $request->user();

        $workshops = Workshop::with('instructors')->whereHas('enrollments', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('status', 'enrolled');
        })->get()->map(function ($workshop) use ($user) {
            $workshop->has_attendance = Attendance::where('user_id', $user->id)
                ->where('workshop_id', $workshop->id)
                ->exists();

            return $workshop;
        });

        return Inertia::render('Workshops/MyWorkshops', [
            'workshops' => $workshops,
        ]);
    }
}
