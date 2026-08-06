<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\Lead;
use App\Notificacoes\Leads\LeadNoDiscord;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Configuração do módulo de leads: o canal do Discord que recebe os
 * avisos e o token que protege o endpoint público do webhook.
 */
class ConfiguracaoLeadController extends Controller
{
    public function show()
    {
        return response()->json([
            'discord_webhook' => Configuracao::obter(Configuracao::LEADS_DISCORD_WEBHOOK),
            'token' => Configuracao::obter(Configuracao::LEADS_TOKEN),
            // A URL pronta para colar no FunnelsFlow, montada a partir do
            // APP_URL — evita o erro de apontar o webhook de produção para
            // o ambiente local (ou o contrário).
            'url_webhook' => rtrim(config('app.url'), '/').'/api/leads/webhook',
        ]);
    }

    public function update(Request $request)
    {
        $dados = $request->validate([
            'discord_webhook' => [
                'nullable',
                'url',
                'starts_with:https://discord.com/api/webhooks/,https://discordapp.com/api/webhooks/',
            ],
            'token' => ['nullable', 'string', 'min:16', 'max:191'],
        ]);

        Configuracao::definir(Configuracao::LEADS_DISCORD_WEBHOOK, $dados['discord_webhook'] ?? null);
        Configuracao::definir(Configuracao::LEADS_TOKEN, $dados['token'] ?? null);

        return $this->show();
    }

    /**
     * Gera um token novo. Fica no servidor (e não no navegador) para que
     * a aleatoriedade venha de uma fonte criptográfica.
     */
    public function gerarToken()
    {
        $token = Str::random(48);

        Configuracao::definir(Configuracao::LEADS_TOKEN, $token);

        return response()->json(['token' => $token]);
    }

    /**
     * Posta um lead fictício no canal configurado, para validar a URL do
     * webhook sem precisar esperar um lead real.
     */
    public function testar()
    {
        $webhook = Configuracao::obter(Configuracao::LEADS_DISCORD_WEBHOOK);

        if (! $webhook) {
            return response()->json(['mensagem' => 'Nenhum canal do Discord configurado.'], 422);
        }

        $exemplo = new Lead([
            'titulo' => 'Lead de teste',
            'valor' => 1234.56,
            'moeda' => 'BRL',
            'pipeline_nome' => 'Vendas',
            'stage_nome' => 'Novo Contato',
            'owner_nome' => 'Responsável de teste',
            'origem' => 'teste-configuracao',
            'tags' => ['teste'],
            'pessoa_nome' => 'Fulano de Tal',
            'pessoa_email' => 'fulano@exemplo.com',
            'pessoa_telefone' => '+5511999990000',
            'organizacao_nome' => 'Empresa Exemplo',
            'recebido_em' => now(),
        ]);

        try {
            (new LeadNoDiscord)->enviar($exemplo, $webhook);

            return response()->json(['mensagem' => 'Lead de teste enviado ao Discord.']);
        } catch (Throwable $e) {
            return response()->json(['mensagem' => 'Falha ao enviar: '.$e->getMessage()], 422);
        }
    }
}
