<?php

namespace App\Jobs;

use App\Models\Configuracao;
use App\Models\Lead;
use App\Notificacoes\Leads\LeadNoDiscord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posta no Discord o lead recebido do FunnelsFlow.
 *
 * Vai para a fila pela mesma razão das notificações de alerta: o
 * FunnelsFlow espera uma resposta rápida do webhook e reenvia o evento
 * se demorarmos. Postar no Discord dentro da requisição colocaria a
 * latência do Discord no caminho crítico.
 */
class EnviarLeadParaDiscord implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int> segundos entre as tentativas */
    public array $backoff = [10, 60];

    public function __construct(public int $leadId)
    {
    }

    public function handle(): void
    {
        $lead = Lead::find($this->leadId);

        if (! $lead) {
            return;
        }

        $webhook = Configuracao::obter(Configuracao::LEADS_DISCORD_WEBHOOK);

        // Sem canal configurado não é erro: o lead continua gravado e
        // visível na tela. Repetir o job não adiantaria nada, então
        // apenas registramos e saímos.
        if (! $webhook) {
            Log::info('Lead recebido sem canal do Discord configurado', ['lead_id' => $lead->id]);

            return;
        }

        try {
            (new LeadNoDiscord)->enviar($lead, $webhook);
        } catch (Throwable $e) {
            Log::warning('Falha ao postar lead no Discord', [
                'lead_id' => $lead->id,
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
