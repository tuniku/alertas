<?php

namespace App\Notificacoes\Leads;

use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Posta um lead em um canal do Discord.
 *
 * É separado do DiscordNotificador dos alertas de propósito: aquele
 * recebe uma MensagemAlerta (projeto, importância, código) e é
 * configurado por tipo de disparo. O canal de leads tem uma
 * configuração própria e um conteúdo diferente — misturar os dois
 * obrigaria a generalizar a mensagem dos alertas sem necessidade.
 */
class LeadNoDiscord
{
    public function enviar(Lead $lead, string $webhookUrl): void
    {
        $campos = array_values(array_filter([
            $this->campo('Contato', $lead->pessoa_nome),
            $this->campo('E-mail', $lead->pessoa_email),
            $this->campo('Telefone', $lead->pessoa_telefone),
            $this->campo('Empresa', $lead->organizacao_nome),
            $this->campo('Valor', $lead->valor !== null ? $lead->valorFormatado() : null),
            $this->campo('Origem', $lead->origem),
            $this->campo('Funil', $lead->pipeline_nome),
            $this->campo('Etapa', $lead->stage_nome),
            $this->campo('Responsável', $lead->owner_nome),
            $this->campo('Tags', $lead->tags ? implode(', ', $lead->tags) : null),
        ]));

        $embed = [
            'title' => $lead->titulo ?: 'Novo lead',
            'color' => 0x2563EB, // azul — distingue visualmente dos alertas
            'fields' => $campos,
            'footer' => ['text' => 'FunnelsFlow · recebido em '.$lead->recebido_em->format('d/m/Y H:i:s')],
        ];

        // Só vira link clicável se o payload trouxe a URL do negócio.
        if ($lead->url) {
            $embed['url'] = $lead->url;
        }

        $resposta = Http::timeout(10)
            ->retry(2, 500, throw: false)
            ->post($webhookUrl, [
                'username' => 'Leads',
                'embeds' => [$embed],
            ]);

        if ($resposta->failed()) {
            throw new RuntimeException(
                "Discord recusou o lead (HTTP {$resposta->status()}): ".$resposta->body()
            );
        }
    }

    /**
     * O Discord recusa o embed inteiro se algum campo vier com valor
     * vazio, então campos sem conteúdo são descartados antes do envio.
     */
    private function campo(string $nome, ?string $valor): ?array
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : [
            'name' => $nome,
            'value' => $valor,
            'inline' => true,
        ];
    }
}
