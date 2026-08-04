<?php

namespace App\Http\Controllers;

use App\Models\Projeto;
use Illuminate\Http\Request;

class ProjetoController extends Controller
{
    public function index()
    {
        return Projeto::withCount('alertas')->orderBy('nome')->get();
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(Projeto::create($dados), 201);
    }

    public function show(Projeto $projeto)
    {
        return $projeto->load('alertas');
    }

    public function update(Request $request, Projeto $projeto)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
        ]);

        $projeto->update($dados);

        return $projeto;
    }

    public function destroy(Projeto $projeto)
    {
        $projeto->delete();

        return response()->noContent();
    }
}
