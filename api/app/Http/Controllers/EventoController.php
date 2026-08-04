<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\AlertaAtivo;
use App\Models\AlertaLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint público chamado pelos sistemas externos para disparar alertas.
 *
 * Regras:
 *  1. Todo evento recebido SEMPRE gera um registro em alerta_logs.
 *  2. Deduplicação em alertas_ativos: se já existe um registro ativo e não
 *     expirado para o alerta, apenas o updated_at dele é atualizado; caso
 *     contrário, um novo registro ativo é criado (e o expirado, se houver,
 *     é encerrado pelo sistema, com fechado_por = null).
 */
class EventoController extends Controller
{
    public function disparar(Request $request)
    {
        $dados = $request->validate([
            'codigo' => ['required', 'string'],
            'evento_em' => ['nullable', 'date'],
            'descricao' => ['nullable', 'string'],
        ]);

        $alerta = Alerta::where('codigo', $dados['codigo'])->first();

        if (! $alerta) {
            return response()->json([
                'mensagem' => "Alerta com código '{$dados['codigo']}' não encontrado.",
            ], 404);
        }

        $agora = now();

        return DB::transaction(function () use ($alerta, $dados, $agora) {
            // 1. Log: sempre gravado, sem exceção.
            $log = AlertaLog::create([
                'alerta_id' => $alerta->id,
                'recebido_em' => $agora,
                'evento_em' => $dados['evento_em'] ?? null,
                'descricao' => $dados['descricao'] ?? null,
            ]);

            // 2. Deduplicação. lockForUpdate evita corrida entre dois
            // disparos simultâneos do mesmo alerta criando dois ativos.
            $existente = AlertaAtivo::where('alerta_id', $alerta->id)
                ->where('ativo', true)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existente && $existente->bloqueiaNovoDisparo()) {
                $existente->touch();

                return response()->json([
                    'deduplicado' => true,
                    'log_id' => $log->id,
                    'alerta_ativo_id' => $existente->id,
                ]);
            }

            // Registro ativo porém expirado: encerrado pelo sistema.
            if ($existente) {
                $existente->update([
                    'ativo' => false,
                    'fechado_em' => $agora,
                ]);
            }

            $novo = AlertaAtivo::create([
                'alerta_id' => $alerta->id,
                'ativo' => true,
                'expira_em' => $alerta->expiracao_minutos
                    ? $agora->copy()->addMinutes($alerta->expiracao_minutos)
                    : null,
            ]);

            return response()->json([
                'deduplicado' => false,
                'log_id' => $log->id,
                'alerta_ativo_id' => $novo->id,
            ], 201);
        });
    }
}
