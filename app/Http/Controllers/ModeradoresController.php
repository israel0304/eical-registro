<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Conference;
use App\Models\ModeradorConstancia;
use App\Models\ParticipationType;
use App\Models\User;
use App\Services\CertificateRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModeradoresController extends Controller
{
    public function __construct(private readonly CertificateRenderer $renderer) {}

    public function index()
    {
        $moderatorIds = Conference::query()
            ->whereHas('moderators')
            ->with('moderators:id')
            ->get()
            ->flatMap(fn ($conference) => $conference->moderators->pluck('id'))
            ->unique()
            ->values();

        $users = User::whereIn('id', $moderatorIds)
            ->with(['constanciaModerador', 'moderatedConferences' => function ($q) {
                $q->orderBy('day')->select(['conferences.id', 'conferences.title', 'conferences.day']);
            }])
            ->orderBy('last_name')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => trim($user->first_name.' '.$user->last_name),
                    'email' => $user->email,
                    'affiliation' => $user->affiliation,
                    'activated' => (bool) $user->constanciaModerador?->activated,
                    'activated_at' => $user->constanciaModerador?->activated_at,
                    'conference_count' => $user->moderatedConferences->count(),
                    'conference_titles' => $user->moderatedConferences->pluck('title')->map(fn ($t) => (string) $t)->all(),
                    'folio' => $this->moderatorFolio($user),
                ];
            })
            ->values();

        return Inertia::render('Constancias/Moderadores/Index', [
            'moderators' => $users,
        ]);
    }

    public function toggle(Request $request, User $user)
    {
        abort_unless($request->user()->can('constancias.moderators.manage'), 403);

        $isModerator = $user->moderatedConferences()->exists();

        if (! $isModerator) {
            return back()->withErrors(['error' => 'Este usuario no es moderador de ninguna conferencia.']);
        }

        $activation = ModeradorConstancia::firstOrCreate(['user_id' => $user->id]);
        $activated = ! $activation->activated;

        $activation->update([
            'activated' => $activated,
            'activated_at' => $activated ? now() : null,
        ]);

        return back()->with('success', $activated ? 'Constancia de moderador activada.' : 'Constancia de moderador desactivada.');
    }

    public function download(Request $request, User $user)
    {
        abort_unless($request->user()->can('constancias.moderators.manage'), 403);

        $certificate = $this->renderer->issueModerador($user);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia de moderador.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        $html = $this->renderer->render($certificate);

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename=constancia_'.$certificate->folio.'.html',
        ]);
    }

    private function moderatorFolio(User $user): ?string
    {
        $type = ParticipationType::query()
            ->where('key', 'moderador')
            ->where('event_kind', 'conference')
            ->whereNull('kind')
            ->where('role', 'moderator')
            ->where('is_active', true)
            ->first();

        if ($type === null) {
            return null;
        }

        return Certificate::query()
            ->where('user_id', $user->id)
            ->where('participation_type_id', $type->id)
            ->where('event_type', 'conference')
            ->where('event_id', 0)
            ->value('folio');
    }
}
