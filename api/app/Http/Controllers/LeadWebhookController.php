<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarLeadParaDiscord;
use App\Models\Configuracao;
use App\Models\Lead;
use Illuminate\Http\Request;

/**
 * Recebe os eventos do webhook de saída do FunnelsFlow.
 *
 * Público (não usa Sanctum), como o /api/eventos dos alertas — quem
 * chama é uma plataforma externa. A proteção é um token compartilhado,
 * configurado na tela "Config. de leads".
 */
class LeadWebhookController extends Controller
{
    /** Único evento tratado nesta etapa. */
    private const EVENTO_ACEITO = 'deal.created';

    public function receber(Request $request)
    {
        if (! $this->tokenValido($request)) {
            return response()->json(['mensagem' => 'Token inválido.'], 401);
        }

        $dados = $request->validate([
            'id' => ['required', 'string', 'max:191'],
            'event' => ['required', 'string', 'max:191'],
        ]);

        // Eventos que não tratamos recebem 200, e não 4xx, de propósito:
        // para a plataforma, um erro significa "falhou, tente de novo", e
        // ela ficaria reenviando indefinidamente algo que ignoramos por
        // decisão de projeto.
        if ($dados['event'] !== self::EVENTO_ACEITO) {
            return response()->json([
                'ignorado' => true,
                'mensagem' => "Evento '{$dados['event']}' não é tratado por este endpoint.",
            ]);
        }

        // Idempotência: o FunnelsFlow reenvia o evento se não receber
        // resposta a tempo. Sem isto, um timeout da nossa parte viraria
        // lead duplicado no banco e mensagem repetida no Discord.
        $existente = Lead::where('evento_id', $dados['id'])->first();

        if ($existente) {
            return response()->json([
                'duplicado' => true,
                'lead_id' => $existente->id,
            ]);
        }

        $lead = Lead::create(Lead::dosDadosDoWebhook($request->all()));

        EnviarLeadParaDiscord::dispatch($lead->id);

        return response()->json(['duplicado' => false, 'lead_id' => $lead->id], 201);
    }

    /**
     * Aceita o token no header ou na query string.
     *
     * O header é o caminho preferido; a query existe porque nem toda
     * plataforma de webhook permite cabeçalhos personalizados, e nesse
     * caso a URL é o único lugar onde o segredo cabe.
     */
    private function tokenValido(Request $request): bool
    {
        $esperado = Configuracao::obter(Configuracao::LEADS_TOKEN);

        // Sem token configurado, o endpoint fica fechado — o contrário
        // (liberar quando não há token) transformaria um cadastro
        // incompleto em endpoint público sem ninguém perceber.
        if (! $esperado) {
            return false;
        }

        $recebido = $request->header('X-Webhook-Token')
            ?? $request->query('token', '');

        // hash_equals compara em tempo constante, sem vazar por quanto
        // tempo os dois valores coincidiram.
        return hash_equals($esperado, (string) $recebido);
    }
}
