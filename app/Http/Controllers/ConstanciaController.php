<?php

namespace App\Http\Controllers;

use App\Models\Presentation;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConstanciaController extends Controller
{
    public function myCertificates(Request $request)
    {
        $user = $request->user();

        $workshopIds = Workshop::whereHas('enrollments', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('status', 'enrolled');
        })->pluck('id');

        $completedWorkshops = Workshop::whereIn('id', $workshopIds)
            ->whereHas('attendances', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->withCount(['attendances as attendance_count' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->get();

        $presentationCertificates = Presentation::whereHas('authors', function ($q) use ($user) {
            $q->where('users.id', $user->id)
                ->where('presentation_authors.presented', true);
        })
            ->with(['authors' => function ($q) {
                $q->where('presentation_authors.presented', true)
                    ->withPivot('presented', 'presented_at');
            }])
            ->get();

        return Inertia::render('Constancias/Index', [
            'completedWorkshops' => $completedWorkshops,
            'presentationCertificates' => $presentationCertificates,
            'user' => $user,
        ]);
    }

    public function download(Request $request, $id)
    {
        $user = $request->user();
        $workshop = Workshop::findOrFail($id);

        $isEnrolled = $workshop->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->exists();

        if (! $isEnrolled) {
            return back()->withErrors(['error' => 'No estás inscrito en este taller.']);
        }

        return $this->generateCertificatePdf($user, $workshop);
    }

    public function adminDownload($workshopId, $userId)
    {
        $workshop = Workshop::findOrFail($workshopId);
        $user = User::findOrFail($userId);

        return $this->generateCertificatePdf($user, $workshop);
    }

    public function downloadPonencia(Request $request, Presentation $presentation)
    {
        $user = $request->user();

        $presented = $presentation->authors()
            ->where('users.id', $user->id)
            ->wherePivot('presented', true)
            ->exists();

        if (! $presented) {
            return back()->withErrors(['error' => 'No tienes una ponencia presentada para descargar esta constancia.']);
        }

        return $this->generatePonenciaPdf($user, $presentation);
    }

    public function adminDownloadPonencia(Presentation $presentation, User $user)
    {
        return $this->generatePonenciaPdf($user, $presentation);
    }

    private function generatePonenciaPdf($user, $presentation)
    {
        if (! $presentation->relationLoaded('authors')) {
            $presentation->load('authors');
        }

        $html = $this->buildPonenciaHtml($user, $presentation);

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename=constancia_ponencia_'.$presentation->id.'.html',
        ]);
    }

    private function buildPonenciaHtml($user, $presentation)
    {
        $authorsList = $presentation->authors->map(function ($a) {
            return htmlspecialchars($a->first_name.' '.$a->last_name);
        })->implode(', ');

        return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia de Ponencia</title>
    <style>
        body { font-family: Georgia, serif; text-align: center; padding: 60px; }
        .title { font-size: 28px; font-weight: bold; margin-bottom: 20px; }
        .subtitle { font-size: 18px; margin-bottom: 30px; }
        .name { font-size: 24px; font-weight: bold; color: #2c5282; margin: 20px 0; }
        .details { font-size: 16px; margin: 10px 0; }
        .date { margin-top: 40px; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="title">CONSTANCIA DE PONENCIA</div>
    <div class="subtitle">Registro EICAL 2026</div>
    <div class="details">Se certifica que</div>
    <div class="name">'.htmlspecialchars($user->first_name.' '.$user->last_name).'</div>
    <div class="details">presentó la ponencia</div>
    <div class="details"><strong>'.htmlspecialchars($presentation->title).'</strong></div>
    <div class="details">Autores: '.$authorsList.'</div>
    <div class="details">Disciplina: '.htmlspecialchars($presentation->discipline ?? '—').'</div>
    <div class="date">Cinvestav, '.now()->format('d \d\e F \d\e Y').'</div>
</body>
</html>';
    }

    private function generateCertificatePdf($user, $workshop)
    {
        $html = $this->buildCertificateHtml($user, $workshop);

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "inline; filename=constancia_{$workshop->name}.html",
        ]);
    }

    private function buildCertificateHtml($user, $workshop)
    {
        $workshop->load('instructors');
        $instructorsList = $workshop->instructors->map(function ($i) {
            $text = htmlspecialchars($i->name);
            if ($i->institution) {
                $text .= ' - '.htmlspecialchars($i->institution);
            }

            return $text;
        })->implode(', ');

        return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia - '.htmlspecialchars($workshop->name).'</title>
    <style>
        body { font-family: Georgia, serif; text-align: center; padding: 60px; }
        .title { font-size: 28px; font-weight: bold; margin-bottom: 20px; }
        .subtitle { font-size: 18px; margin-bottom: 30px; }
        .name { font-size: 24px; font-weight: bold; color: #2c5282; margin: 20px 0; }
        .details { font-size: 16px; margin: 10px 0; }
        .instructor { font-style: italic; margin-top: 30px; }
        .date { margin-top: 40px; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="title">CONSTANCIA DE PARTICIPACIÓN</div>
    <div class="subtitle">Registro EICAL 2026</div>
    <div class="details">Se certifica que</div>
    <div class="name">'.htmlspecialchars($user->first_name.' '.$user->last_name).'</div>
    <div class="details">participó exitosamente en el taller</div>
    <div class="details"><strong>'.htmlspecialchars($workshop->name).'</strong></div>
    <div class="details">Impartido por: '.$instructorsList.'</div>
    <div class="details">Lugar: '.htmlspecialchars($workshop->location).'</div>
    <div class="details">Fecha: '.htmlspecialchars($workshop->day).'</div>
    <div class="date">Cinvestav, '.now()->format('d \d\e F \d\e Y').'</div>
</body>
</html>';
    }
}
