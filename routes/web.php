<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\ConferenceController;
use App\Http\Controllers\ConstanciaController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\GafeteController;
use App\Http\Controllers\ParticipationTypeController;
use App\Http\Controllers\PonenteActivationController;
use App\Http\Controllers\PresentationController;
use App\Http\Controllers\PresentationImportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkshopController;
use App\Http\Controllers\WorkshopEnrollmentController;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use App\Notifications\BienvenidaNuevoUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('auth/Login', [
        'canResetPassword' => true,
        'canRegister' => true,
    ]);
})->name('home');

Route::get('/ponente/activar', [PonenteActivationController::class, 'showForm'])->name('ponente.activate');

Route::get('/email/verify/{id}/{hash}', EmailVerificationController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
Route::post('/ponente/activar', [PonenteActivationController::class, 'verify'])->name('ponente.verify');
Route::get('/ponente/activar/{token}', [PonenteActivationController::class, 'showSetPasswordForm'])->name('ponente.activate-token');
Route::post('/ponente/establecer-contrasena', [PonenteActivationController::class, 'setPassword'])->name('ponente.set-password');

Route::get('constancias/verificar/{folio}', [CertificateVerificationController::class, 'show'])->name('constancias.verificar');

Route::get('gafete/escaneo', [GafeteController::class, 'scanInfo'])->name('gafete.scan-info');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();
        $stats = [];

        if ($user->isAdmin()) {
            $stats = [
                'total_users' => User::count(),
                'asistentes' => User::whereHas('roles', fn ($q) => $q->where('name', 'Asistente'))->count(),
                'ponentes' => User::whereHas('roles', fn ($q) => $q->where('name', 'Ponente'))->count(),
                'talleres' => Workshop::count(),
                'ponencias' => Presentation::count(),
                'inscripciones' => WorkshopEnrollment::where('status', 'enrolled')->count(),
            ];
        }

        if ($user->isPonente()) {
            $stats['mis_ponencias'] = $user->presentations()->count();
        }

        if ($user->isPonente() || $user->isAsistente() || $user->isInstructor()) {
            $stats['talleres_inscritos'] = $user->enrolledWorkshops()->wherePivot('status', 'enrolled')->count();
        }

        return Inertia::render('Dashboard', ['stats' => $stats]);
    })->middleware('can:dashboard.view')->name('dashboard');

    Route::get('users/export/csv', [UserController::class, 'exportCsv'])->middleware('can:users.export')->name('users.export');
    Route::post('users/import/csv', [UserController::class, 'importCsv'])->middleware('can:users.import')->name('users.import');

    Route::middleware('can:users.manage')->group(function () {
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::delete('users/{user}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');
        Route::resource('users', UserController::class);
    });

    // Workshops
    Route::get('workshops', [WorkshopController::class, 'index'])->middleware('can:workshops.view')->name('workshops.index');
    Route::get('workshops/{workshop}', [WorkshopController::class, 'show'])->middleware('can:workshops.view')->name('workshops.show');

    Route::middleware('can:workshops.manage')->group(function () {
        Route::get('api/instructores', function (Request $request) {
            $search = $request->input('search');

            return User::where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
                ->limit(20)
                ->get(['id', 'first_name', 'last_name', 'email', 'affiliation']);
        })->name('api.instructores.index');

        Route::post('workshops', [WorkshopController::class, 'store'])->name('workshops.store');
        Route::put('workshops/{workshop}', [WorkshopController::class, 'update'])->name('workshops.update');
        Route::delete('workshops/{workshop}', [WorkshopController::class, 'destroy'])->name('workshops.destroy');
    });

    // Workshop enrollment
    Route::post('workshops/{workshop}/enroll', [WorkshopEnrollmentController::class, 'store'])->name('workshops.enroll');
    Route::delete('workshops/{workshop}/unenroll', [WorkshopEnrollmentController::class, 'destroy'])->name('workshops.unenroll');
    Route::get('my-workshops', [WorkshopEnrollmentController::class, 'myWorkshops'])->middleware('can:workshops.my')->name('workshops.my');

    // Workshop attendance (QR scan)
    Route::get('workshops/{workshop}/scan', [AttendanceController::class, 'scan'])->name('workshops.scan');

    // Admin: workshop enrollment management
    Route::delete('admin/workshops/{workshop}/enrollments/{enrollment}', [WorkshopEnrollmentController::class, 'adminDestroy'])->middleware('can:workshops.enrollments')->name('workshops.admin-enrollment-destroy');
    Route::get('admin/workshops/{workshop}/enrollments', [AttendanceController::class, 'showEnrollments'])->middleware('can:workshops.enrollments')->name('workshops.admin-enrollments');

    // Admin: attendance
    Route::post('admin/workshops/{workshop}/attendance/{userId}', [AttendanceController::class, 'toggleAttendance'])->middleware('can:workshops.attendance')->name('workshops.admin-attendance-toggle');
    Route::post('admin/workshops/{workshop}/send-qr', [AttendanceController::class, 'sendQRToInstructor'])->middleware('can:workshops.attendance')->name('workshops.admin-send-qr');
    Route::post('admin/workshops/{workshop}/send-qr-all', [AttendanceController::class, 'sendQRToAll'])->middleware('can:workshops.attendance')->name('workshops.admin-send-qr-all');

    // Presentations
    Route::get('presentations', [PresentationController::class, 'index'])->middleware('can:presentations.view')->name('presentations.index');
    Route::get('presentations/{presentation}', [PresentationController::class, 'show'])->middleware('can:presentations.view')->name('presentations.show');

    Route::middleware('can:presentations.manage')->group(function () {
        Route::post('presentations', [PresentationController::class, 'store'])->name('presentations.store');
        Route::put('presentations/{presentation}', [PresentationController::class, 'update'])->name('presentations.update');
        Route::delete('presentations/{presentation}', [PresentationController::class, 'destroy'])->name('presentations.destroy');
    });

    // Conferences
    Route::get('conferences', [ConferenceController::class, 'index'])->middleware('can:conferences.view')->name('conferences.index');
    Route::get('conferences/{conference}', [ConferenceController::class, 'show'])->middleware('can:conferences.view')->name('conferences.show');

    Route::middleware('can:conferences.manage')->group(function () {
        Route::get('conferences/create', [ConferenceController::class, 'create'])->name('conferences.create');
        Route::get('conferences/{conference}/edit', [ConferenceController::class, 'edit'])->name('conferences.edit');
        Route::post('conferences', [ConferenceController::class, 'store'])->name('conferences.store');
        Route::put('conferences/{conference}', [ConferenceController::class, 'update'])->name('conferences.update');
        Route::delete('conferences/{conference}', [ConferenceController::class, 'destroy'])->name('conferences.destroy');
        Route::post('conferences/{conference}/members/{user}/activation', [ConferenceController::class, 'toggleActivation'])->name('conferences.activation');
    });

    // API-like routes for internal use (ponente search/create)
    Route::get('api/ponentes', function (Request $request) {
        $search = $request->input('search');

        return User::where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        })
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email']);
    })->name('api.ponentes.index');

    Route::post('api/ponentes', function (Request $request) {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'semblanza' => 'nullable|string|max:5000',
        ]);

        $ponenteRoleId = Role::where('name', 'Ponente')->value('id') ?? 2;

        $user = User::where('email', $validated['email'])->first();

        if ($user === null) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'dni' => 'CNV-'.Str::random(7),
                'password' => bcrypt(Str::random(16)),
                'affiliation' => $request->input('affiliation', ''),
                'country' => $request->input('country', ''),
                'state' => $request->input('state', ''),
                'semblanza' => $request->input('semblanza', ''),
            ]);

            $activationToken = Str::random(60);
            $user->update(['activation_token' => $activationToken]);
            $url = url('/ponente/activar/'.$activationToken);
            $user->notify(new BienvenidaNuevoUsuario($url, $user->first_name));
        }

        $user->roles()->syncWithoutDetaching([$ponenteRoleId]);

        return $user->only(['id', 'first_name', 'last_name', 'email']);
    })->name('api.ponentes.store');

    Route::middleware('can:conferences.manage')->group(function () {
        Route::get('api/usuarios', function (Request $request) {
            $search = $request->input('search');

            return User::where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
                ->limit(20)
                ->get(['id', 'first_name', 'last_name', 'email']);
        })->name('api.usuarios.index');

        Route::post('api/usuarios', function (Request $request) {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email',
                'semblanza' => 'nullable|string|max:5000',
            ]);

            $speakerRoleId = Role::where('name', 'Speaker')->value('id') ?? 5;

            $user = User::where('email', $validated['email'])->first();

            if ($user === null) {
                $user = User::create([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'dni' => 'CNV-'.Str::random(7),
                    'password' => bcrypt(Str::random(16)),
                    'affiliation' => $request->input('affiliation', ''),
                    'country' => $request->input('country', ''),
                    'state' => $request->input('state', ''),
                    'semblanza' => $request->input('semblanza', ''),
                ]);

                $activationToken = Str::random(60);
                $user->update(['activation_token' => $activationToken]);
                $url = url('/ponente/activar/'.$activationToken);
                $user->notify(new BienvenidaNuevoUsuario($url, $user->first_name));
            }

            $user->roles()->syncWithoutDetaching([$speakerRoleId]);

            return $user->only(['id', 'first_name', 'last_name', 'email']);
        })->name('api.usuarios.store');
    });

    Route::redirect('admin/presentations/import', '/presentations?tab=import')->middleware('can:presentations.import')->name('presentations.import');
    Route::post('admin/presentations/import', [PresentationImportController::class, 'store'])->middleware('can:presentations.import')->name('presentations.import.store');

    Route::middleware('can:reportes.view')->prefix('admin/reportes')->name('reportes.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('asistencia-taller', [ReportController::class, 'asistenciaTaller'])->name('asistencia-taller');
        Route::get('asistencia-general', [ReportController::class, 'asistenciaGeneral'])->name('asistencia-general');
        Route::get('asistencia-ponencias', [ReportController::class, 'asistenciaPonencias'])->name('asistencia-ponencias');
        Route::get('resumen', [ReportController::class, 'resumen'])->name('resumen');
        Route::get('ocupacion', [ReportController::class, 'ocupacion'])->name('ocupacion');
        Route::get('estadisticas', [ReportController::class, 'estadisticas'])->name('estadisticas');
        Route::get('export/{type}', [ReportController::class, 'exportCsv'])->name('export');
        Route::get('workshops/{workshop}/csv', [ReportController::class, 'exportWorkshopCsv'])->name('workshop-csv');
    });

    // Constancias
    Route::get('constancias', [ConstanciaController::class, 'myCertificates'])->middleware('can:constancias.view')->name('constancias.my');

    // Constancia de asistencia al evento (antes de rutas genéricas)
    Route::get('constancias/evento/download', [ConstanciaController::class, 'downloadEvento'])->middleware('can:constancias.download')->name('constancias.evento.download');
    Route::get('admin/constancias/evento/{user}/download', [ConstanciaController::class, 'adminDownloadEvento'])->middleware('can:constancias.download')->name('constancias.evento.admin-download');

    Route::get('constancias/{id}/download', [ConstanciaController::class, 'download'])->middleware('can:constancias.download')->name('constancias.download');
    Route::get('constancias/{certificate}/pdf', [ConstanciaController::class, 'downloadPdf'])->middleware('can:constancias.download')->name('constancias.pdf');
    Route::get('admin/constancias/{workshopId}/{userId}/download', [ConstanciaController::class, 'adminDownload'])->middleware('can:constancias.download')->name('constancias.admin.download');

    // Constancias de ponencia
    Route::get('constancias/ponencia/{presentation}/download', [ConstanciaController::class, 'downloadPonencia'])->middleware('can:constancias.download')->name('constancias.ponencia.download');
    Route::get('admin/constancias/ponencia/{presentation}/{user}/download', [ConstanciaController::class, 'adminDownloadPonencia'])->middleware('can:constancias.download')->name('constancias.ponencia.admin-download');

    // Constancias de conferencia
    Route::get('constancias/conferencia/{conference}/download', [ConstanciaController::class, 'downloadConferencia'])->middleware('can:constancias.download')->name('constancias.conferencia.download');
    Route::get('admin/constancias/conferencia/{conference}/{user}/download', [ConstanciaController::class, 'adminDownloadConferencia'])->middleware('can:constancias.download')->name('constancias.conferencia.admin-download');

    // Generación manual de constancias de tipos marcados como "manuales" (ej. staff)
    Route::get('admin/constancias/tipos/{type}/usuario/{user}/generar', [ConstanciaController::class, 'adminGenerate'])->middleware('can:constancias.download')->name('constancias.tipos.admin-generate');

    // Gafete
    Route::middleware('can:gafete.view')->group(function () {
        Route::get('gafete', [GafeteController::class, 'show'])->name('gafete.show');
        Route::get('gafete/imprimir', [GafeteController::class, 'print'])->name('gafete.print');
        Route::get('gafete/imprimir/pdf', [GafeteController::class, 'printPdf'])->name('gafete.print-pdf');
        Route::post('gafete/foto', [GafeteController::class, 'uploadPhoto'])->name('gafete.photo');
        Route::delete('gafete/foto', [GafeteController::class, 'destroyPhoto'])->name('gafete.photo.destroy');
    });

    // Check-in (escáner de gafetes)
    Route::middleware('can:checkin.scan')->group(function () {
        Route::get('checkin', [CheckinController::class, 'index'])->name('checkin.index');
        Route::post('checkin/register', [CheckinController::class, 'register'])->name('checkin.register');
        Route::get('checkin/lookup', [CheckinController::class, 'lookup'])->name('checkin.lookup');
    });

    // Admin: badge templates
    Route::middleware('can:gafete.templates.manage')->group(function () {
        Route::get('admin/gafetes/plantillas', [CertificateTemplateController::class, 'badgeIndex'])->name('gafete.templates.index');
        Route::post('admin/gafetes/plantillas', [CertificateTemplateController::class, 'badgeStore'])->name('gafete.templates.store');
        Route::get('admin/gafetes/plantillas/{template}/edit', [CertificateTemplateController::class, 'badgeEdit'])->name('gafete.templates.edit');
        Route::put('admin/gafetes/plantillas/{template}', [CertificateTemplateController::class, 'badgeUpdate'])->name('gafete.templates.update');
        Route::delete('admin/gafetes/plantillas/{template}', [CertificateTemplateController::class, 'badgeDestroy'])->name('gafete.templates.destroy');
    });

    // Admin: evento (check-in + constancias)
    Route::middleware('can:constancias.evento.manage')->group(function () {
        Route::get('admin/evento', [EventoController::class, 'index'])->name('evento.index');
        Route::put('admin/evento', [EventoController::class, 'update'])->name('evento.update');
        Route::post('admin/evento/generar-constancias', [EventoController::class, 'generateConstancias'])->name('evento.generate-constancias');
        Route::delete('admin/evento/attendance/{attendance}', [EventoController::class, 'destroyAttendance'])->name('evento.attendance-destroy');
    });

    // Admin: certificate templates
    Route::middleware('can:constancias.templates.manage')->group(function () {
        Route::get('admin/constancias/plantillas', [CertificateTemplateController::class, 'index'])->name('constancias.templates.index');
        Route::post('admin/constancias/plantillas', [CertificateTemplateController::class, 'store'])->name('constancias.templates.store');
        Route::get('admin/constancias/plantillas/{template}/edit', [CertificateTemplateController::class, 'edit'])->name('constancias.templates.edit');
        Route::put('admin/constancias/plantillas/{template}', [CertificateTemplateController::class, 'update'])->name('constancias.templates.update');
        Route::delete('admin/constancias/plantillas/{template}', [CertificateTemplateController::class, 'destroy'])->name('constancias.templates.destroy');
    });

    // Admin: participation types
    Route::middleware('can:constancias.types.manage')->group(function () {
        Route::get('admin/constancias/tipos', [ParticipationTypeController::class, 'index'])->name('constancias.types.index');
        Route::post('admin/constancias/tipos', [ParticipationTypeController::class, 'store'])->name('constancias.types.store');
        Route::put('admin/constancias/tipos/{type}', [ParticipationTypeController::class, 'update'])->name('constancias.types.update');
        Route::delete('admin/constancias/tipos/{type}', [ParticipationTypeController::class, 'destroy'])->name('constancias.types.destroy');
    });

    // Admin: roles & permissions
    Route::middleware('can:roles.manage')->group(function () {
        Route::get('admin/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('admin/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('admin/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::get('admin/roles/{role}/users', [RoleController::class, 'users'])->name('roles.users');
        Route::delete('admin/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
});

require __DIR__.'/settings.php';
