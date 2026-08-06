<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lead recebido do FunnelsFlow pelo webhook de saída (evento
 * deal.created).
 *
 * Os campos mais usados ficam achatados em colunas próprias, para
 * permitir busca e ordenação no banco; o JSON original fica em
 * `payload` como fonte da verdade.
 */
class Lead extends Model
{
    protected $table = 'leads';

    protected $fillable = [
        'evento_id',
        'evento',
        'deal_id',
        'numero',
        'titulo',
        'valor',
        'moeda',
        'status',
        'pipeline_nome',
        'stage_nome',
        'owner_nome',
        'owner_email',
        'origem',
        'tags',
        'pessoa_nome',
        'pessoa_email',
        'pessoa_telefone',
        'organizacao_nome',
        'organizacao_dominio',
        'url',
        'criado_em_origem',
        'recebido_em',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'valor' => 'decimal:2',
            'tags' => 'array',
            'payload' => 'array',
            'criado_em_origem' => 'datetime',
            'recebido_em' => 'datetime',
        ];
    }

    /**
     * Monta os atributos a partir do payload do webhook.
     *
     * Fica no model, e não no controller, porque é conhecimento sobre o
     * formato do FunnelsFlow: se a plataforma mudar o payload, há um só
     * lugar para ajustar.
     */
    public static function dosDadosDoWebhook(array $payload): array
    {
        $deal = $payload['data']['deal'] ?? [];
        $pessoa = $deal['person'] ?? [];
        $org = $deal['organization'] ?? [];

        return [
            'evento_id' => $payload['id'],
            'evento' => $payload['event'],
            'deal_id' => $deal['id'] ?? '—',
            'numero' => $deal['number'] ?? null,
            'titulo' => $deal['title'] ?? null,
            // O webhook manda "amount"; a API de escrita do FunnelsFlow usa
            // "value" para a mesma informação. Aqui só chega o webhook.
            'valor' => $deal['amount'] ?? null,
            'moeda' => $deal['currency'] ?? null,
            'status' => $deal['status'] ?? null,
            'pipeline_nome' => $deal['pipelineName'] ?? null,
            'stage_nome' => $deal['stageName'] ?? null,
            'owner_nome' => $deal['ownerName'] ?? null,
            'owner_email' => $deal['ownerEmail'] ?? null,
            'origem' => $deal['source'] ?? null,
            'tags' => $deal['tags'] ?? [],
            'pessoa_nome' => $pessoa['name'] ?? null,
            'pessoa_email' => $pessoa['email'] ?? null,
            'pessoa_telefone' => $pessoa['phone'] ?? null,
            'organizacao_nome' => $org['name'] ?? null,
            'organizacao_dominio' => $org['domain'] ?? null,
            'url' => $deal['url'] ?? null,
            'criado_em_origem' => $deal['createdAt'] ?? $payload['createdAt'] ?? null,
            'recebido_em' => now(),
            'payload' => $payload,
        ];
    }

    /** Valor formatado para exibição, respeitando a moeda informada. */
    public function valorFormatado(): string
    {
        if ($this->valor === null) {
            return '—';
        }

        $simbolo = match ($this->moeda) {
            'BRL' => 'R$',
            'USD' => 'US$',
            'EUR' => '€',
            default => (string) $this->moeda,
        };

        return trim($simbolo.' '.number_format((float) $this->valor, 2, ',', '.'));
    }
}
