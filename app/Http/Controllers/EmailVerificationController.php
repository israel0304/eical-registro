<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash)
    {
        $user = User::find($id);

        if (! $user || ! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(404);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        if ($request->user()) {
            return redirect(config('fortify.home'))
                ->with('status', 'Tu correo electrónico ha sido verificado correctamente.');
        }

        return redirect()->route('login')
            ->with('status', 'Tu correo electrónico ha sido verificado. Ahora puedes iniciar sesión.');
    }
}
