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
    public function index()
    {
        $users = User::with('roles')
            ->latest()
            ->paginate(10);

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
