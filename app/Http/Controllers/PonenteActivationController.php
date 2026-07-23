<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class PonenteActivationController extends Controller
{
    public function showForm()
    {
        return Inertia::render('auth/PonenteActivation', []);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|string',
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])
            ->whereHas('role', fn ($q) => $q->where('name', 'Ponente'))
            ->whereHas('presentations', fn ($q) => $q->where('submission_id', $validated['submission_id']))
            ->first();

        if (! $user) {
            return back()->withErrors([
                'submission_id' => 'No se encontró una ponencia con esos datos.',
            ]);
        }

        if ($user->hasSetPassword()) {
            return back()->withErrors([
                'submission_id' => 'Esta cuenta ya fue activada. Inicia sesión con tu email y contraseña.',
            ]);
        }

        $request->session()->put('activating_ponente_id', $user->id);

        return Inertia::render('auth/PonenteSetPassword', [
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }

    public function setPassword(Request $request)
    {
        $userId = $request->session()->get('activating_ponente_id');

        if (! $userId) {
            return redirect()->route('ponente.activate');
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($userId);

        $user->update([
            'password' => Hash::make($validated['password']),
            'password_set_at' => now(),
            'activation_token' => null,
        ]);

        $request->session()->forget('activating_ponente_id');

        return redirect()->route('login')->with('success', 'Cuenta activada correctamente. Ya puedes iniciar sesión.');
    }
}
