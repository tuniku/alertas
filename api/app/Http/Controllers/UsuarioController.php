<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index()
    {
        return User::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        return response()->json(User::create($dados), 201);
    }

    public function show(User $usuario)
    {
        return $usuario;
    }

    public function update(Request $request, User $usuario)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (empty($dados['password'])) {
            unset($dados['password']);
        }

        $usuario->update($dados);

        return $usuario;
    }

    public function destroy(Request $request, User $usuario)
    {
        if ($request->user()->id === $usuario->id) {
            return response()->json([
                'mensagem' => 'Você não pode excluir o próprio usuário logado.',
            ], 422);
        }

        $usuario->delete();

        return response()->noContent();
    }
}
