<?php

namespace App\Http\Controllers;

use App\Models\AlertaAtivo;
use Illuminate\Http\Request;

class AlertaAtivoController extends Controller
{
    public function index(Request $request)
    {
        return AlertaAtivo::with(['alerta.projeto', 'fechadoPor'])
            ->when(
                $request->query('somente_ativos', '1') === '1',
                fn ($q) => $q->where('ativo', true)
            )
            ->orderByDesc('updated_at')
            ->paginate(50);
    }

    public function fechar(Request $request, AlertaAtivo $alertaAtivo)
    {
        if (! $alertaAtivo->ativo) {
            return response()->json(['mensagem' => 'Este alerta já está fechado.'], 422);
        }

        $alertaAtivo->update([
            'ativo' => false,
            'fechado_por' => $request->user()->id,
            'fechado_em' => now(),
        ]);

        return $alertaAtivo->load(['alerta.projeto', 'fechadoPor']);
    }
}
