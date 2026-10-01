<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');

        // Filtro por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por nombre
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filtro por correo
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        // Filtro por roles
        if ($request->filled('rol')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->rol . '%');
            });
        }

        // Filtro por correo verificado
        if ($request->filled('verificado')) {
            if ($request->verificado === '1') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->verificado === '0') {
                $query->whereNull('email_verified_at');
            }
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por fecha de creación
        if ($request->filled('fecha')) {
            $query->whereDate('created_at', $request->fecha);
        }

        $users = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = Rol::where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],

            'roles' => [
                'nullable',
                'array',
            ],

            'roles.*' => [
                'exists:roles,id',
            ],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],

                'password' => Hash::make(
                    $validated['password']
                ),

                'estado' => $request->has('estado'),
            ]);

            $user->roles()->sync(
                $validated['roles'] ?? []
            );
        });

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Usuario creado correctamente.'
            );
    }

    public function show(User $user)
    {
        $user->load('roles');

        return view(
            'users.show',
            compact('user')
        );
    }

    public function edit(User $user)
    {
        $user->load('roles');

        // Roles activos más los que ya tenga asignados aunque estén inactivos
        $roles = Rol::where('estado', true)
            ->orWhereIn('id', $user->roles->pluck('id'))
            ->orderBy('nombre')
            ->get();

        return view(
            'users.edit',
            compact('user', 'roles')
        );
    }

    public function update(
        Request $request,
        User $user
    ) {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',

                Rule::unique('users', 'email')
                    ->ignore($user->id),
            ],

            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],

            'estado' => [
                'nullable',
                'boolean',
            ],

            'roles' => [
                'nullable',
                'array',
            ],

            'roles.*' => [
                'exists:roles,id',
            ],
        ]);

        $datos = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'estado' => $request->has('estado'),
        ];

        if (!empty($validated['password'])) {
            $datos['password'] = Hash::make(
                $validated['password']
            );
        }

        DB::transaction(function () use ($user, $datos, $validated) {
            $user->update($datos);

            $user->roles()->sync(
                $validated['roles'] ?? []
            );
        });

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Usuario actualizado correctamente.'
            );
    }

    public function cambiarEstado(User $user)
    {
        $user->update([
            'estado' => !$user->estado,
        ]);

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Estado del usuario actualizado correctamente.'
            );
    }
}
