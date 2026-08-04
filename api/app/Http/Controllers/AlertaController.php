<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertaController extends Controller
{
    public function index(Request $request)
    {
        return Alerta::with(['projeto', 'tipoDisparo'])
            ->when($request->query('projeto_id'), fn ($q, $id) => $q->where('projeto_id', $id))
            ->orderBy('nome')
            ->get();
    }

    public function store(Request $request)
    {
        return response()->json(Alerta::create($this->validar($request)), 201);
    }

    public function show(Alerta $alerta)
    {
        return $alerta->load(['projeto', 'tipoDisparo']);
    }

    public function update(Request $request, Alerta $alerta)
    {
        $alerta->update($this->validar($request, $alerta));

        return $alerta->load(['projeto', 'tipoDisparo']);
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
            'tipo_disparo_id' => ['nullable', 'integer', 'exists:tipos_disparo,id'],
            'expiracao_minutos' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
