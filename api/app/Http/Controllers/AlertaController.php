<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertaController extends Controller
{
    public function index(Request $request)
    {
        return Alerta::with(['projeto', 'tiposDisparo'])
            ->when($request->query('projeto_id'), fn ($q, $id) => $q->where('projeto_id', $id))
            ->orderBy('nome')
            ->get();
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);
        $canais = $dados['tipos_disparo'] ?? [];
        unset($dados['tipos_disparo']);

        $alerta = Alerta::create($dados);
        $alerta->tiposDisparo()->sync($canais);

        return response()->json($alerta->load(['projeto', 'tiposDisparo']), 201);
    }

    public function show(Alerta $alerta)
    {
        return $alerta->load(['projeto', 'tiposDisparo']);
    }

    public function update(Request $request, Alerta $alerta)
    {
        $dados = $this->validar($request, $alerta);
        $canais = $dados['tipos_disparo'] ?? [];
        unset($dados['tipos_disparo']);

        $alerta->update($dados);
        $alerta->tiposDisparo()->sync($canais);

        return $alerta->load(['projeto', 'tiposDisparo']);
    }

    public function destroy(Alerta $alerta)
    {
        $alerta->delete();

        return response()->noContent();
    }

    private function validar(Request $request, ?Alerta $alerta = null): array
    {
        return $request->validate([
            'projeto_id' => ['required', 'integer', 'exists:projetos,id'],
            'codigo' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('alertas', 'codigo')->ignore($alerta?->id),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'importancia' => ['required', 'integer', 'min:0', 'max:10'],
            'expiracao_minutos' => ['nullable', 'integer', 'min:1'],

            // Faz o alerta aparecer no aplicativo Android.
            'disponivel_app' => ['boolean'],

            // Canais em que este alerta notifica (vários por alerta).
            'tipos_disparo' => ['array'],
            'tipos_disparo.*' => ['integer', 'exists:tipos_disparo,id'],
        ]);
    }
}
