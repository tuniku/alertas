<?php

namespace App\Notificacoes\Drivers;

use App\Models\TipoDisparo;
use App\Notificacoes\MensagemAlerta;
use App\Notificacoes\Notificador;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Posta o alerta em um canal do Discord via webhook.
 *
 * Configuração esperada em tipos_disparo.configuracao:
 *   { "webhook_url": "https://discord.com/api/webhooks/..." }
 */
class DiscordNotificador implements Notificador
{
    public static function rotulo(): string
    {
        return 'Discord (webhook)';
    }

    public static function regrasDeConfiguracao(): array
    {
        return [
            'configuracao.webhook_url' => [
                'required',
                'url',
                'starts_with:https://discord.com/api/webhooks/,https://discordapp.com/api/webhooks/',
            ],
        ];
    }

    public function enviar(MensagemAlerta $mensagem, TipoDisparo $tipoDisparo): void
    {
        $url = $tipoDisparo->configuracao['webhook_url'] ?? null;

        if (! $url) {
            throw new RuntimeException("Tipo de disparo '{$tipoDisparo->nome}' está sem webhook_url configurada.");
        }

        $campos = [
            ['name' => 'Projeto', 'value' => $mensagem->projeto, 'inline' => true],
            ['name' => 'Importância', 'value' => "{$mensagem->importancia}/10 ({$mensagem->nivel()})", 'inline' => true],
            ['name' => 'Código', 'value' => "`{$mensagem->codigo}`", 'inline' => false],
        ];

        if ($mensagem->eventoEm) {
            $campos[] = ['name' => 'Ocorrido em', 'value' => $mensagem->eventoEm, 'inline' => true];
        }

        $resposta = Http::timeout(10)
            ->retry(2, 500, throw: false)
            ->post($url, [
                'username' => 'Alertas',
                'embeds' => [[
                    'title' => $mensagem->alerta,
                    'description' => $mensagem->descricao ?: null,
                    'color' => $this->cor($mensagem->importancia),
                    'fields' => $campos,
                    'footer' => ['text' => "Recebido em {$mensagem->recebidoEm}"],
                ]],
            ]);

        // O Discord responde 204 (sem corpo) quando aceita o webhook.
        if ($resposta->failed()) {
            throw new RuntimeException(
                "Discord recusou a notificação (HTTP {$resposta->status()}): ".$resposta->body()
            );
        }
    }

    /** Cor da barra lateral do embed, por severidade. */
    private function cor(int $importancia): int
    {
        return match (true) {
            $importancia >= 8 => 0xDC2626, // vermelho
            $importancia >= 4 => 0xF59E0B, // âmbar
            default => 0x16A34A,           // verde
        };
    }
}
