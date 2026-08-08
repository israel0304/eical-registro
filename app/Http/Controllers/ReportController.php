<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Presentation;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index()
    {
        $stats = [
            'total_asistentes' => User::whereHas('roles', fn ($q) => $q->where('name', 'Asistente'))->count(),
            'total_ponentes' => User::whereHas('roles', fn ($q) => $q->where('name', 'Ponente'))->count(),
            'total_talleres' => Workshop::count(),
            'total_ponencias' => Presentation::count(),
            'total_inscritos' => WorkshopEnrollment::where('status', 'enrolled')->count(),
            'total_asistencias' => Attendance::count(),
        ];

        return Inertia::render('Reports/Index', [
            'stats' => $stats,
        ]);
    }

    public function asistenciaTaller(Request $request)
    {
        $query = Workshop::withCount(['enrollments as total_inscritos' => function ($q) {
            $q->where('status', 'enrolled');
        }])->withCount(['attendances as total_asistieron']);

        if ($request->filled('workshop_id')) {
            $query->where('id', $request->input('workshop_id'));
        }

        $workshops = $query->get();

        return Inertia::render('Reports/AsistenciaTaller', [
            'workshops' => $workshops,
        ]);
    }

    public function asistenciaGeneral(Request $request)
    {
        $query = User::whereHas('roles', fn ($q) => $q->where('name', 'Asistente'))
            ->with('attendances');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('dni', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(25)->withQueryString();

        return Inertia::render('Reports/AsistenciaGeneral', [
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    public function asistenciaPonencias(Request $request)
    {
        $query = Presentation::withCount('attendances as total_asistieron');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        $presentations = $query->get();

        return Inertia::render('Reports/AsistenciaPonencias', [
            'presentations' => $presentations,
        ]);
    }

    public function resumen()
    {
        $stats = [
            'total_users' => User::count(),
            'asistentes' => User::whereHas('roles', fn ($q) => $q->where('name', 'Asistente'))->count(),
            'asistentes_activos' => User::whereHas('roles', fn ($q) => $q->where('name', 'Asistente'))->where('is_active', true)->count(),
            'ponentes' => User::whereHas('roles', fn ($q) => $q->where('name', 'Ponente'))->count(),
            'talleres' => Workshop::count(),
            'inscripciones' => WorkshopEnrollment::where('status', 'enrolled')->count(),
            'ponencias' => Presentation::count(),
            'asistencias_total' => Attendance::count(),
        ];

        return Inertia::render('Reports/Resumen', [
            'stats' => $stats,
        ]);
    }

    public function ocupacion()
    {
        $workshops = Workshop::withCount(['enrollments as enrolled_count' => function ($q) {
            $q->where('status', 'enrolled');
        }])->get()->map(function ($workshop) {
            return [
                'id' => $workshop->id,
                'name' => $workshop->name,
                'capacity' => $workshop->capacity,
                'enrolled_count' => $workshop->enrolled_count,
                'occupancy_rate' => $workshop->capacity > 0
                    ? round(($workshop->enrolled_count / $workshop->capacity) * 100, 1)
                    : 0,
            ];
        });

        return Inertia::render('Reports/Ocupacion', [
            'workshops' => $workshops,
        ]);
    }

    public function estadisticas()
    {
        $asistenciaPorDia = Attendance::select('event_day', DB::raw('count(*) as total'))
            ->groupBy('event_day')
            ->orderBy('event_day')
            ->get();

        $ocupacionTalleres = Workshop::withCount(['enrollments as enrolled' => function ($q) {
            $q->where('status', 'enrolled');
        }])->get(['id', 'name', 'capacity']);

        $totalInscritos = WorkshopEnrollment::where('status', 'enrolled')->count();
        $totalAsistentes = User::whereHas('roles', fn ($q) => $q->where('name', 'Asistente'))->count();
        $tasaCompletado = $totalAsistentes > 0 ? round(($totalInscritos / $totalAsistentes) * 100, 1) : 0;

        return Inertia::render('Reports/Estadisticas', [
            'asistenciaPorDia' => $asistenciaPorDia,
            'ocupacionTalleres' => $ocupacionTalleres,
            'tasaCompletado' => $tasaCompletado,
        ]);
    }

    public function exportCsv(Request $request, string $type)
    {
        $filename = "reporte_{$type}_".now()->format('Y-m-d').'.csv';
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($type) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            switch ($type) {
                case 'asistencia-taller':
                    fputcsv($file, ['Taller', 'Capacidad', 'Inscritos', 'Asistieron']);
                    $workshops = Workshop::withCount(['enrollments as inscritos' => function ($q) {
                        $q->where('status', 'enrolled');
                    }])->withCount('attendances as asistieron')->get();
                    foreach ($workshops as $w) {
                        fputcsv($file, [$w->name, $w->capacity, $w->inscritos, $w->asistieron]);
                    }
                    break;

                case 'asistencia-general':
                    fputcsv($file, ['DNI', 'Nombre', 'Email', 'Días asistidos']);
                    $users = User::whereHas('roles', fn ($q) => $q->where('name', 'Asistente'))
                        ->withCount('attendances as dias_asistidos')->get();
                    foreach ($users as $u) {
                        fputcsv($file, [$u->dni, $u->name, $u->email, $u->dias_asistidos]);
                    }
                    break;

                case 'resumen':
                    fputcsv($file, ['Métrica', 'Valor']);
                    fputcsv($file, ['Total usuarios', User::count()]);
                    fputcsv($file, ['Asistentes', User::whereHas('roles', fn ($q) => $q->where('name', 'Asistente'))->count()]);
                    fputcsv($file, ['Ponentes', User::whereHas('roles', fn ($q) => $q->where('name', 'Ponente'))->count()]);
                    fputcsv($file, ['Talleres', Workshop::count()]);
                    fputcsv($file, ['Ponencias', Presentation::count()]);
                    fputcsv($file, ['Inscripciones', WorkshopEnrollment::where('status', 'enrolled')->count()]);
                    break;

                case 'ocupacion':
                    fputcsv($file, ['Taller', 'Capacidad', 'Inscritos', 'Ocupación %']);
                    $workshops = Workshop::withCount(['enrollments as inscritos' => function ($q) {
                        $q->where('status', 'enrolled');
                    }])->get();
                    foreach ($workshops as $w) {
                        $rate = $w->capacity > 0 ? round(($w->inscritos / $w->capacity) * 100, 1) : 0;
                        fputcsv($file, [$w->name, $w->capacity, $w->inscritos, $rate.'%']);
                    }
                    break;
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportWorkshopCsv(Request $request, Workshop $workshop)
    {
        abort_unless($request->user()->can('reportes.view'), 403);

        $workshop->load([
            'enrollments' => function ($q) {
                $q->where('status', 'enrolled');
            },
            'enrollments.user',
        ]);

        $filename = 'inscritos_taller_'.$workshop->id.'_'.now()->format('Y-m-d').'.csv';
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($workshop) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Nombre', 'Apellido', 'Email', 'DNI', 'Fecha Inscripcion']);

            foreach ($workshop->enrollments as $enrollment) {
                $user = $enrollment->user;
                if ($user) {
                    fputcsv($file, [
                        $user->first_name,
                        $user->last_name,
                        $user->email,
                        $user->dni,
                        $enrollment->enrolled_at,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
