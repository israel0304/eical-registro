<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\Stand;
use App\Models\Unit;
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
        $query = User::query()->with(['role', 'stand', 'unit', 'department']);

        // Búsqueda por texto (Nombre / Correo)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('dni', 'like', "%{$search}%");
            });
        }

        // Filtro rápido por tipo de perfil (role_id)
        if ($request->filled('role_id')) {
            $roleId = $request->input('role_id');
            $query->where(function ($q) use ($roleId) {
                $q->where('role_id', $roleId);
            });
        }

        // Navegación por tabs (Activos vs Inactivos)
        if ($request->has('status') && $request->input('status') === 'inactive') {
            $query->where(function ($q) {
                $q->where('is_active', false);
            });
        } else {
            $query->where(function ($q) {
                $q->where('is_active', true);
            });
        }

        $users = $query->paginate(15)->withQueryString();

        $roles = Role::all();
        $stands = Stand::all();
        $units = Unit::all();
        $departments = Department::all();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'stands' => $stands,
            'units' => $units,
            'departments' => $departments,
            'filters' => $request->only(['search', 'role_id', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'extension' => 'nullable|string|max:10',
            'tshirt_size' => 'nullable|string|max:5',
            'role_id' => 'required|exists:roles,id',
            'stand_id' => 'nullable|exists:stands,id',
            'unit_id' => 'nullable|exists:units,id',
            'department_id' => 'nullable|exists:departments,id',
            'custom_unit' => 'nullable|string|max:255',
            'custom_department' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'photo' => 'nullable|image|max:5120',
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
            'phone' => 'nullable|string|max:20',
            'extension' => 'nullable|string|max:10',
            'tshirt_size' => 'nullable|string|max:5',
            'role_id' => 'required|exists:roles,id',
            'stand_id' => 'nullable|exists:stands,id',
            'unit_id' => 'nullable|exists:units,id',
            'department_id' => 'nullable|exists:departments,id',
            'custom_unit' => 'nullable|string|max:255',
            'custom_department' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'photo' => 'nullable|image|max:5120',
            'delete_photo' => 'nullable|boolean',
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

        // Opcional: Borrar foto física si existe
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
        $users = User::with(['role', 'stand', 'unit', 'department'])->get();
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=usuarios_cinvesninos.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($users) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            // Headers
            fputcsv($file, ['#', 'DNI', 'Nombres', 'Apellidos', 'Email', 'Telefono', 'Ext', 'Playera', 'Perfil', 'Unidad', 'Departamento', 'Stand', 'Estatus']);
            $i = 1;
            foreach ($users as $user) {
                fputcsv($file, [
                    $i++,
                    $user->dni,
                    $user->first_name,
                    $user->last_name,
                    $user->email,
                    $user->phone,
                    $user->extension,
                    $user->tshirt_size,
                    $user->role->name ?? '',
                    $user->unit->name ?? $user->custom_unit ?? '',
                    $user->department->name ?? $user->custom_department ?? '',
                    $user->stand->name ?? '',
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
            array_shift($data); // Omitir el header
        }

        $imported = 0;
        foreach ($data as $row) {
            if (count($row) < 3) {
                continue;
            }

            // Mapeo de Perfil (Role) por nombre
            $roleId = 4; // Default Participante
            if (! empty($row[6])) {
                $role = Role::where('name', 'like', "%{$row[6]}%")->first();
                if ($role) {
                    $roleId = $role->id;
                }
            }

            // Mapeo de Stand por nombre
            $standId = null;
            if (! empty($row[7])) {
                $stand = Stand::where('name', 'like', "%{$row[7]}%")->first();
                if ($stand) {
                    $standId = $stand->id;
                }
            }

            User::updateOrCreate(
                ['email' => $row[2] ?? ''],
                [
                    'first_name' => $row[0] ?? 'CSV',
                    'last_name' => $row[1] ?? 'User',
                    'dni' => 'CNV-'.strtoupper(Str::random(7)),
                    'phone' => $row[3] ?? null,
                    'extension' => $row[4] ?? null,
                    'tshirt_size' => $row[5] ?? null,
                    'role_id' => $roleId,
                    'stand_id' => $standId,
                    'is_active' => true, // Estatus activo por defecto
                    'password' => Hash::make(Str::random(12)),
                ]
            );
            $imported++;
        }

        return back()->with('success', "Se procesaron $imported usuarios exitosamente.");
    }
}
