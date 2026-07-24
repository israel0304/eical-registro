<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ConstanciaController;
use App\Http\Controllers\PonenteActivationController;
use App\Http\Controllers\PresentationController;
use App\Http\Controllers\PresentationImportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkshopController;
use App\Http\Controllers\WorkshopEnrollmentController;
use App\Models\Presentation;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/ponente/activar', [PonenteActivationController::class, 'showForm'])->name('ponente.activate');
Route::post('/ponente/activar', [PonenteActivationController::class, 'verify'])->name('ponente.verify');
Route::post('/ponente/establecer-contrasena', [PonenteActivationController::class, 'setPassword'])->name('ponente.set-password');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();
        $stats = [];

        if ($user->isAdmin()) {
            $stats = [
                'total_users' => User::count(),
                'asistentes' => User::whereHas('role', fn ($q) => $q->where('name', 'Asistente'))->count(),
                'ponentes' => User::whereHas('role', fn ($q) => $q->where('name', 'Ponente'))->count(),
                'talleres' => Workshop::count(),
                'ponencias' => Presentation::count(),
                'inscripciones' => WorkshopEnrollment::where('status', 'enrolled')->count(),
            ];
        } elseif ($user->isPonente()) {
            $stats = [
                'mis_ponencias' => $user->presentations()->count(),
                'talleres_inscritos' => $user->enrolledWorkshops()->wherePivot('status', 'enrolled')->count(),
            ];
        } else {
            $stats = [
                'talleres_inscritos' => $user->enrolledWorkshops()->wherePivot('status', 'enrolled')->count(),
            ];
        }

        return Inertia::render('Dashboard', ['stats' => $stats]);
    })->name('dashboard');

    Route::get('users/export/csv', [UserController::class, 'exportCsv'])->name('users.export');
    Route::post('users/import/csv', [UserController::class, 'importCsv'])->name('users.import');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::delete('users/{user}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');
    Route::resource('users', UserController::class);

    // Workshops
    Route::get('workshops', [WorkshopController::class, 'index'])->name('workshops.index');
    Route::get('workshops/{workshop}', [WorkshopController::class, 'show'])->name('workshops.show');
    Route::post('workshops', [WorkshopController::class, 'store'])->name('workshops.store');
    Route::put('workshops/{workshop}', [WorkshopController::class, 'update'])->name('workshops.update');
    Route::delete('workshops/{workshop}', [WorkshopController::class, 'destroy'])->name('workshops.destroy');

    // Workshop enrollment
    Route::post('workshops/{workshop}/enroll', [WorkshopEnrollmentController::class, 'store'])->name('workshops.enroll');
    Route::delete('workshops/{workshop}/unenroll', [WorkshopEnrollmentController::class, 'destroy'])->name('workshops.unenroll');
    Route::get('my-workshops', [WorkshopEnrollmentController::class, 'myWorkshops'])->name('workshops.my');

    // Workshop attendance (QR scan)
    Route::get('workshops/{workshop}/scan', [AttendanceController::class, 'scan'])->name('workshops.scan');

    // Admin: workshop enrollment management
    Route::delete('admin/workshops/{workshop}/enrollments/{enrollment}', [WorkshopEnrollmentController::class, 'adminDestroy'])->name('workshops.admin-enrollment-destroy');
    Route::get('admin/workshops/{workshop}/enrollments', [AttendanceController::class, 'showEnrollments'])->name('workshops.admin-enrollments');

    // Admin: attendance
    Route::post('admin/workshops/{workshop}/attendance/{userId}', [AttendanceController::class, 'toggleAttendance'])->name('workshops.admin-attendance-toggle');
    Route::post('admin/workshops/{workshop}/send-qr', [AttendanceController::class, 'sendQRToInstructor'])->name('workshops.admin-send-qr');
    Route::post('admin/workshops/{workshop}/send-qr-all', [AttendanceController::class, 'sendQRToAll'])->name('workshops.admin-send-qr-all');

    // Presentations
    Route::get('presentations', [PresentationController::class, 'index'])->name('presentations.index');
    Route::get('presentations/{presentation}', [PresentationController::class, 'show'])->name('presentations.show');
    Route::put('presentations/{presentation}', [PresentationController::class, 'update'])->name('presentations.update');

    Route::get('admin/presentations/import', [PresentationImportController::class, 'index'])->name('presentations.import');
    Route::post('admin/presentations/import', [PresentationImportController::class, 'store'])->name('presentations.import.store');

    Route::prefix('admin/reportes')->name('reportes.')->group(function () {
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
    Route::get('constancias', [ConstanciaController::class, 'myCertificates'])->name('constancias.my');
    Route::get('constancias/{id}/download', [ConstanciaController::class, 'download'])->name('constancias.download');
    Route::get('admin/constancias', [ConstanciaController::class, 'adminCertificates'])->name('constancias.admin');
    Route::get('admin/constancias/{workshopId}/{userId}/download', [ConstanciaController::class, 'adminDownload'])->name('constancias.admin.download');
});

require __DIR__.'/settings.php';
