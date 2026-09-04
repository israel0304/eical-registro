<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\ConferenceController;
use App\Http\Controllers\ConstanciaController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\EmailTriggerController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\GafeteController;
use App\Http\Controllers\InvitationTemplateController;
use App\Http\Controllers\ParticipationTypeController;
use App\Http\Controllers\PonenteActivationController;
use App\Http\Controllers\PresentationController;
use App\Http\Controllers\PresentationImportController;
use App\Http\Controllers\PresentationTemplateController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgramTemplateController;
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
use App\Services\EventAudit;
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

// Programa público (sin auth)
Route::get('programa/publico', [ProgramController::class, 'publicIndex'])->name('programa.public');
Route::get('programa/publico/imprimir', [ProgramController::class, 'printPublic'])->name('programa.public.print');
Route::get('programa/publico/imprimir/pdf', [ProgramController::class, 'printPublicPdf'])->name('programa.public.print-pdf');

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

    Route::get('users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
    Route::post('users', [UserController::class, 'store'])->middleware('can:users.create')->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:users.edit')->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete')->name('users.destroy');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('can:users.edit')->name('users.reset-password');
    Route::delete('users/{user}/force-delete', [UserController::class, 'forceDelete'])->middleware('can:users.delete')->name('users.force-delete');

    Route::get('users/{user}/gafete/imprimir', [GafeteController::class, 'userPrint'])->middleware('can:users.view')->name('users.gafete.print');
    Route::get('users/gafetes/imprimir', [GafeteController::class, 'asistentesBadgesPrint'])->middleware('can:users.view')->name('users.gafete.print-asistentes');
    Route::get('users/gafetes/imprimir/pdf', [GafeteController::class, 'asistentesBadgesPrintPdf'])->middleware('can:users.view')->name('users.gafete.print-asistentes-pdf');

    // Workshops
    Route::get('workshops', [WorkshopController::class, 'index'])->middleware('can:workshops.view')->name('workshops.index');
    Route::get('workshops/{workshop}', [WorkshopController::class, 'show'])->name('workshops.show');

    Route::get('api/instructores', function (Request $request) {
        abort_unless(
            $request->user()->can('workshops.create') || $request->user()->can('workshops.edit'),
            403
        );

        $search = $request->input('search');

        return User::where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        })
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email', 'affiliation']);
    })->name('api.instructores.index');

    Route::post('workshops', [WorkshopController::class, 'store'])->middleware('can:workshops.create')->name('workshops.store');
    Route::put('workshops/{workshop}', [WorkshopController::class, 'update'])->middleware('can:workshops.edit')->name('workshops.update');
    Route::post('workshops/{workshop}/instructors/{user}/activation', [WorkshopController::class, 'toggleInstructorActivation'])->middleware('can:workshops.activate')->name('workshops.instructors.activation');
    Route::delete('workshops/{workshop}', [WorkshopController::class, 'destroy'])->middleware('can:workshops.delete')->name('workshops.destroy');
    Route::post('workshops/{workshop}/force-delete', [WorkshopController::class, 'forceDelete'])->middleware('can:workshops.delete')->name('workshops.force-delete');
    Route::post('workshops/{workshop}/restore', [WorkshopController::class, 'restore'])->middleware('can:workshops.delete')->name('workshops.restore');

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
    Route::post('admin/workshops/{workshop}/attendance/{userId}', [AttendanceController::class, 'toggleAttendance'])->name('workshops.admin-attendance-toggle');
    Route::post('admin/workshops/{workshop}/send-qr', [AttendanceController::class, 'sendQRToInstructor'])->middleware('can:workshops.attendance')->name('workshops.admin-send-qr');
    Route::post('admin/workshops/{workshop}/send-qr-all', [AttendanceController::class, 'sendQRToAll'])->middleware('can:workshops.attendance')->name('workshops.admin-send-qr-all');

    // Presentations
    Route::get('presentations', [PresentationController::class, 'index'])->middleware('can:presentations.view')->name('presentations.index');
    Route::get('presentations/export/csv', [PresentationController::class, 'exportCsv'])->middleware('can:presentations.view')->name('presentations.export');
    Route::get('presentations/{presentation}', [PresentationController::class, 'show'])->name('presentations.show');

    Route::post('presentations', [PresentationController::class, 'store'])->middleware('can:presentations.create')->name('presentations.store');
    Route::put('presentations/{presentation}', [PresentationController::class, 'update'])->name('presentations.update');
    Route::delete('presentations/{presentation}', [PresentationController::class, 'destroy'])->middleware('can:presentations.delete')->name('presentations.destroy');

    // Mis ponencias (ponente)
    Route::get('my-presentations', [PresentationController::class, 'myPresentations'])->middleware('can:presentations.my')->name('presentations.my');
    Route::get('my-presentations/plantilla-presentacion', [PresentationTemplateController::class, 'download'])->middleware('can:presentations.my')->name('presentations.template.download');

    // Plantilla de presentación (admin)
    Route::post('admin/presentations/plantilla', [PresentationTemplateController::class, 'update'])->middleware('can:presentations.template')->name('presentations.template.update');
    Route::delete('admin/presentations/plantilla', [PresentationTemplateController::class, 'destroy'])->middleware('can:presentations.template')->name('presentations.template.destroy');

    // Mis asignaciones (moderador)
    Route::get('mis-asignaciones', [AssignmentController::class, 'index'])->middleware('can:asignaciones.view')->name('asignaciones.index');
    Route::get('mis-asignaciones/{type}/{id}', [AssignmentController::class, 'show'])->middleware('can:asignaciones.view')->name('asignaciones.show');

    // Conferences
    Route::get('conferences', [ConferenceController::class, 'index'])->middleware('can:conferences.view')->name('conferences.index');
    Route::get('conferences/{conference}', [ConferenceController::class, 'show'])->name('conferences.show');

    Route::get('conferences/create', [ConferenceController::class, 'create'])->middleware('can:conferences.create')->name('conferences.create');
    Route::get('conferences/{conference}/edit', [ConferenceController::class, 'edit'])->middleware('can:conferences.edit')->name('conferences.edit');
    Route::post('conferences', [ConferenceController::class, 'store'])->middleware('can:conferences.create')->name('conferences.store');
    Route::put('conferences/{conference}', [ConferenceController::class, 'update'])->middleware('can:conferences.edit')->name('conferences.update');
    Route::delete('conferences/{conference}', [ConferenceController::class, 'destroy'])->middleware('can:conferences.delete')->name('conferences.destroy');
    Route::post('conferences/{conference}/members/{user}/activation', [ConferenceController::class, 'toggleActivation'])->name('conferences.activation');

    // API-like routes for internal use (ponente search/create)
    Route::get('api/ponentes', function (Request $request) {
        abort_unless(
            $request->user()->can('presentations.create') || $request->user()->can('presentations.edit'),
            403
        );

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
        abort_unless(
            $request->user()->can('presentations.create') || $request->user()->can('presentations.edit'),
            403
        );

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'semblanza' => 'nullable|string|max:5000',
        ]);

        $ponenteRoleId = Role::where('name', 'Ponente')->value('id');

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
            EventAudit::emit('user.welcome', $user, $request->user(), [
                'destinatario' => $user->email,
                'nombre_completo' => $user->name,
                'nombre' => $user->first_name,
                'url_activacion' => $url,
            ]);
        }

        $user->roles()->syncWithoutDetaching([$ponenteRoleId]);

        return $user->only(['id', 'first_name', 'last_name', 'email']);
    })->name('api.ponentes.store');

    Route::get('api/moderadores', function (Request $request) {
        abort_unless(
            $request->user()->can('presentations.create') || $request->user()->can('presentations.edit') ||
            $request->user()->can('workshops.create') || $request->user()->can('workshops.edit') ||
            $request->user()->can('conferences.create') || $request->user()->can('conferences.edit'),
            403
        );

        $search = $request->input('search');

        return User::where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        })
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email']);
    })->name('api.moderadores.index');

    Route::post('api/moderadores', function (Request $request) {
        abort_unless(
            $request->user()->can('presentations.create') || $request->user()->can('presentations.edit') ||
            $request->user()->can('workshops.create') || $request->user()->can('workshops.edit') ||
            $request->user()->can('conferences.create') || $request->user()->can('conferences.edit'),
            403
        );

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'semblanza' => 'nullable|string|max:5000',
        ]);

        $moderatorRoleId = Role::where('name', 'Moderator')->value('id');

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
            EventAudit::emit('user.welcome', $user, $request->user(), [
                'destinatario' => $user->email,
                'nombre_completo' => $user->name,
                'nombre' => $user->first_name,
                'url_activacion' => $url,
            ]);
        }

        $user->roles()->syncWithoutDetaching([$moderatorRoleId]);

        return $user->only(['id', 'first_name', 'last_name', 'email']);
    })->name('api.moderadores.store');

    Route::get('api/usuarios', function (Request $request) {
        abort_unless(
            $request->user()->can('conferences.create') || $request->user()->can('conferences.edit'),
            403
        );

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
        abort_unless(
            $request->user()->can('conferences.create') || $request->user()->can('conferences.edit'),
            403
        );

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'semblanza' => 'nullable|string|max:5000',
        ]);

        $speakerRoleId = Role::where('name', 'Speaker')->value('id');

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
            EventAudit::emit('user.welcome', $user, $request->user(), [
                'destinatario' => $user->email,
                'nombre_completo' => $user->name,
                'nombre' => $user->first_name,
                'url_activacion' => $url,
            ]);
        }

        $user->roles()->syncWithoutDetaching([$speakerRoleId]);

        return $user->only(['id', 'first_name', 'last_name', 'email']);
    })->name('api.usuarios.store');

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
    Route::get('constancias/invitacion/descargar', [ConstanciaController::class, 'downloadInvitacion'])->middleware('can:constancias.download')->name('constancias.invitacion.download');
    Route::get('constancias/invitacion/ponencia/{presentation}/download', [ConstanciaController::class, 'downloadInvitacionPonencia'])->middleware('can:constancias.download')->name('constancias.invitacion.ponencia.download');
    Route::get('admin/constancias/evento/{user}/download', [ConstanciaController::class, 'adminDownloadEvento'])->middleware('can:constancias.download')->name('constancias.evento.admin-download');

    Route::get('constancias/{id}/download', [ConstanciaController::class, 'download'])->middleware('can:constancias.download')->name('constancias.download');
    Route::get('constancias/{certificate}/pdf', [ConstanciaController::class, 'downloadPdf'])->middleware('can:constancias.download')->name('constancias.pdf');
    Route::get('admin/constancias/{workshopId}/{userId}/download', [ConstanciaController::class, 'adminDownload'])->name('constancias.admin.download');

    // Constancias de ponencia
    Route::get('constancias/ponencia/{presentation}/download', [ConstanciaController::class, 'downloadPonencia'])->middleware('can:constancias.download')->name('constancias.ponencia.download');
    Route::get('admin/constancias/ponencia/{presentation}/{user}/download', [ConstanciaController::class, 'adminDownloadPonencia'])->name('constancias.ponencia.admin-download');

    // Constancias de conferencia
    Route::get('constancias/conferencia/{conference}/download', [ConstanciaController::class, 'downloadConferencia'])->middleware('can:constancias.download')->name('constancias.conferencia.download');
    Route::get('admin/constancias/conferencia/{conference}/{user}/download', [ConstanciaController::class, 'adminDownloadConferencia'])->name('constancias.conferencia.admin-download');

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
        Route::get('checkin/gafete/{user}/imprimir', [GafeteController::class, 'staffPrint'])->name('checkin.gafete.print');
        Route::get('checkin/gafete/{user}/imprimir/pdf', [GafeteController::class, 'staffPrintPdf'])->name('checkin.gafete.print-pdf');
    });

    // Admin: plantillas (unificado)
    Route::get('admin/plantillas', [CertificateTemplateController::class, 'plantillas'])->name('plantillas.index');

    // Admin: plantillas de correo y disparadores
    Route::middleware('can:correos.templates.manage')->group(function () {
        Route::post('admin/correos/plantillas', [EmailTemplateController::class, 'store'])->name('correos.templates.store');
        Route::get('admin/correos/plantillas/{template}/edit', [EmailTemplateController::class, 'edit'])->name('correos.templates.edit');
        Route::put('admin/correos/plantillas/{template}', [EmailTemplateController::class, 'update'])->name('correos.templates.update');
        Route::delete('admin/correos/plantillas/{template}', [EmailTemplateController::class, 'destroy'])->name('correos.templates.destroy');
        Route::post('admin/correos/preview', [EmailTemplateController::class, 'preview'])->name('correos.templates.preview');

        Route::post('admin/correos/disparadores', [EmailTriggerController::class, 'store'])->name('correos.triggers.store');
        Route::put('admin/correos/disparadores/{trigger}', [EmailTriggerController::class, 'update'])->name('correos.triggers.update');
        Route::delete('admin/correos/disparadores/{trigger}', [EmailTriggerController::class, 'destroy'])->name('correos.triggers.destroy');

        Route::post('admin/correos/event-logs/{eventLog}/resend', [EmailTriggerController::class, 'resend'])->name('correos.logs.resend');
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

    // Programa del evento
    Route::middleware('can:programa.view')->group(function () {
        Route::get('programa', [ProgramController::class, 'index'])->name('programa.index');
        Route::get('programa/imprimir', [ProgramController::class, 'print'])->middleware('can:programa.print')->name('programa.print');
        Route::get('programa/imprimir/pdf', [ProgramController::class, 'printPdf'])->middleware('can:programa.print')->name('programa.print-pdf');

        Route::middleware('can:programa.manage')->group(function () {
            Route::post('programa', [ProgramController::class, 'store'])->name('programa.store');
            Route::put('programa/{programItem}', [ProgramController::class, 'update'])->name('programa.update');
            Route::delete('programa/{programItem}', [ProgramController::class, 'destroy'])->name('programa.destroy');
        });

        Route::middleware('can:programa.templates.manage')->group(function () {
            Route::get('programa/plantillas', [ProgramTemplateController::class, 'index'])->name('programa.templates.index');
            Route::post('programa/plantillas', [ProgramTemplateController::class, 'store'])->name('programa.templates.store');
            Route::get('programa/plantillas/{template}/edit', [ProgramTemplateController::class, 'edit'])->name('programa.templates.edit');
            Route::put('programa/plantillas/{template}', [ProgramTemplateController::class, 'update'])->name('programa.templates.update');
            Route::patch('programa/plantillas/{template}/activar', [ProgramTemplateController::class, 'toggleActive'])->name('programa.templates.activate');
            Route::delete('programa/plantillas/{template}', [ProgramTemplateController::class, 'destroy'])->name('programa.templates.destroy');
            Route::post('programa/plantillas/upload-image', [ProgramTemplateController::class, 'uploadImage'])->name('programa.templates.upload-image');
        });
    });

    // Admin: certificate templates
    Route::middleware('can:constancias.templates.manage')->group(function () {
        Route::get('admin/constancias/plantillas', [CertificateTemplateController::class, 'index'])->name('constancias.templates.index');
        Route::post('admin/constancias/plantillas', [CertificateTemplateController::class, 'store'])->name('constancias.templates.store');
        Route::get('admin/constancias/plantillas/{template}/edit', [CertificateTemplateController::class, 'edit'])->name('constancias.templates.edit');
        Route::put('admin/constancias/plantillas/{template}', [CertificateTemplateController::class, 'update'])->name('constancias.templates.update');
        Route::delete('admin/constancias/plantillas/{template}', [CertificateTemplateController::class, 'destroy'])->name('constancias.templates.destroy');

        // Admin: invitation letter templates
        Route::get('admin/constancias/invitaciones/plantillas', [InvitationTemplateController::class, 'index'])->name('constancias.invitaciones.templates.index');
        Route::post('admin/constancias/invitaciones/plantillas', [InvitationTemplateController::class, 'store'])->name('constancias.invitaciones.templates.store');
        Route::get('admin/constancias/invitaciones/plantillas/{template}/edit', [InvitationTemplateController::class, 'edit'])->name('constancias.invitaciones.templates.edit');
        Route::put('admin/constancias/invitaciones/plantillas/{template}', [InvitationTemplateController::class, 'update'])->name('constancias.invitaciones.templates.update');
        Route::patch('admin/constancias/invitaciones/plantillas/{template}/activar', [InvitationTemplateController::class, 'toggleActive'])->name('constancias.invitaciones.templates.activate');
        Route::delete('admin/constancias/invitaciones/plantillas/{template}', [InvitationTemplateController::class, 'destroy'])->name('constancias.invitaciones.templates.destroy');
        Route::post('admin/constancias/invitaciones/plantillas/upload-image', [InvitationTemplateController::class, 'uploadImage'])->name('constancias.invitaciones.templates.upload-image');
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
