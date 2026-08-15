<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()
            ->paginate(10);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
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
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],

            'password' => Hash::make(
                $validated['password']
            ),

            'estado' => $request->has('estado'),
        ]);

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Usuario creado correctamente.'
            );
    }

    public function show(User $user)
    {
        return view(
            'users.show',
            compact('user')
        );
    }

    public function edit(User $user)
    {
        return view(
            'users.edit',
            compact('user')
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

        $user->update($datos);

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
