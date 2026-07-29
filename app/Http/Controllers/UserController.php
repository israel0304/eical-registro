<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['role']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('dni', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->input('role_id'));
        }

        if ($request->has('status') && $request->input('status') === 'inactive') {
            $query->where('is_active', false);
        } else {
            $query->where('is_active', true);
        }

        $users = $query->paginate(15)->withQueryString();
        $roles = Role::all();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role_id', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'affiliation' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'boolean',
            'photo' => 'nullable|image|max:5120',
            'semblanza' => 'nullable|string|max:5000',
        ]);

        if ($request->hasFile('photo')) {
            $validated['profile_photo_path'] = $request->file('photo')->store('profile-photos', 'public');
        }

        $validated['password'] = Hash::make(Str::random(12));
        $validated['dni'] = 'CNV-'.strtoupper(Str::random(7));

        User::create($validated);

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'role_id' => 'required|exists:roles,id',
            'affiliation' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'photo' => 'nullable|image|max:5120',
            'delete_photo' => 'nullable|boolean',
            'semblanza' => 'nullable|string|max:5000',
        ]);

        if ($request->boolean('delete_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $user->profile_photo_path = null;
        }

        if ($request->hasFile('photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $validated['profile_photo_path'] = $request->file('photo')->store('profile-photos', 'public');
        }

        $user->update($validated);

        return back()->with('success', 'Usuario modificado correctamente.');
    }

    public function destroy(User $user)
    {
        $user->update(['is_active' => false]);

        return back()->with('success', 'El usuario pasó a estado inactivo.');
    }

    public function forceDelete($id)
    {
        $user = User::withTrashed()->findOrFail($id);

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->forceDelete();

        return back()->with('success', 'Usuario eliminado permanentemente de la base de datos.');
    }

    public function resetPassword(User $user)
    {
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', 'Se envió un correo al usuario para restablecer su contraseña.');
        }

        return back()->withErrors(['email' => __($status)]);
    }

    public function exportCsv(Request $request)
    {
        $users = User::with(['role'])->get();
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=usuarios_registro_eical.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($users) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['#', 'DNI', 'Nombres', 'Apellidos', 'Email', 'Perfil', 'Institución', 'País', 'Estatus']);
            $i = 1;
            foreach ($users as $user) {
                fputcsv($file, [
                    $i++,
                    $user->dni,
                    $user->first_name,
                    $user->last_name,
                    $user->email,
                    $user->role->name ?? '',
                    $user->affiliation ?? '',
                    $user->country ?? '',
                    $user->is_active ? 'Activo' : 'Inactivo',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $data = array_map('str_getcsv', file($path));

        if (count($data) > 0) {
            array_shift($data);
        }

        $imported = 0;
        foreach ($data as $row) {
            if (count($row) < 3) {
                continue;
            }

            $roleId = 3;
            if (! empty($row[6])) {
                $role = Role::where('name', 'like', "%{$row[6]}%")->first();
                if ($role) {
                    $roleId = $role->id;
                }
            }

            User::updateOrCreate(
                ['email' => $row[2] ?? ''],
                [
                    'first_name' => $row[0] ?? 'CSV',
                    'last_name' => $row[1] ?? 'User',
                    'dni' => 'CNV-'.strtoupper(Str::random(7)),
                    'role_id' => $roleId,
                    'is_active' => true,
                    'password' => Hash::make(Str::random(12)),
                ]
            );
            $imported++;
        }

        return back()->with('success', "Se procesaron $imported usuarios exitosamente.");
    }
}
