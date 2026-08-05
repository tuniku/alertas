<?php

namespace App\Notificacoes\Drivers;

use App\Models\TipoDisparo;
use App\Notificacoes\MensagemAlerta;
use App\Notificacoes\Notificador;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Envia o alerta por um bot do Telegram, para um chat privado, grupo
 * ou canal.
 *
 * Configuração esperada em tipos_disparo.configuracao:
 *   {
 *     "bot_token": "123456789:AAH...",   // devolvido pelo @BotFather
 *     "chat_id": "-1001234567890"        // destino (ver getUpdates)
 *   }
 *
 * Diferente do Discord, onde a URL do webhook já embute o destino, aqui
 * são dois dados separados: o token identifica *quem envia* (o bot) e o
 * chat_id, *para onde*. Por isso o mesmo bot pode alimentar vários tipos
 * de disparo, um por chat, reaproveitando o token.
 */
class TelegramNotificador implements Notificador
{
    public static function rotulo(): string
    {
        return 'Telegram (bot)';
    }

    public static function regrasDeConfiguracao(): array
    {
        return [
            // O token tem o formato "<id numérico>:<segredo>". A regex
            // pega erros de colagem (espaço sobrando, token cortado)
            // ainda no cadastro, em vez de deixar falhar só no envio.
            'configuracao.bot_token' => ['required', 'string', 'regex:/^\d+:[A-Za-z0-9_-]{30,}$/'],
            // Pode ser negativo (grupos e canais) ou positivo (chat
            // privado), então é validado como string e não como inteiro
            // — inteiro também recusaria ids muito grandes em 32 bits.
            'configuracao.chat_id' => ['required', 'string', 'regex:/^-?\d+$/'],
        ];
    }

    public function enviar(MensagemAlerta $mensagem, TipoDisparo $tipoDisparo): void
    {
        $config = $tipoDisparo->configuracao ?? [];
        $token = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        if (! $token || ! $chatId) {
            throw new RuntimeException(
                "Tipo de disparo '{$tipoDisparo->nome}' está sem bot_token ou chat_id configurado."
            );
        }

        $resposta = Http::timeout(10)
            ->retry(2, 500, throw: false)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'parse_mode' => 'HTML',
                'text' => $this->texto($mensagem),
                // O Telegram gera um "card" de preview para qualquer link
                // que apareça na descrição; num alerta isso só polui.
                'disable_web_page_preview' => true,
            ]);

        // A API do Telegram responde 200 com {"ok": true} em caso de
        // sucesso, e 4xx com {"description": "..."} quando recusa —
        // essa descrição é o que realmente explica o erro (chat não
        // encontrado, bot bloqueado, token inválido), então vale mais
        // do que o status HTTP na mensagem de falha.
        if ($resposta->failed() || $resposta->json('ok') !== true) {
            $motivo = $resposta->json('description') ?: $resposta->body();

            throw new RuntimeException(
                "Telegram recusou a notificação (HTTP {$resposta->status()}): {$motivo}"
            );
        }
    }

    /**
     * Monta o corpo da mensagem em HTML — o Telegram aceita só um
     * subconjunto pequeno de tags (b, i, u, s, a, code, pre), sem
     * cores nem layout de colunas como no embed do Discord. Por isso a
     * severidade vira emoji + texto, no lugar da barra colorida.
     */
    private function texto(MensagemAlerta $mensagem): string
    {
        $linhas = [
            "{$this->icone($mensagem->importancia)} <b>".$this->escapar($mensagem->alerta).'</b>',
            '',
            'Projeto: '.$this->escapar($mensagem->projeto),
            "Importância: {$mensagem->importancia}/10 ({$mensagem->nivel()})",
            'Código: <code>'.$this->escapar($mensagem->codigo).'</code>',
        ];

        if ($mensagem->eventoEm) {
            $linhas[] = 'Ocorrido em: '.$this->escapar($mensagem->eventoEm);
        }

        $linhas[] = "Recebido em: {$mensagem->recebidoEm}";

        if ($mensagem->descricao) {
            $linhas[] = '';
            $linhas[] = $this->escapar($mensagem->descricao);
        }

        return implode("\n", $linhas);
    }

    private function icone(int $importancia): string
    {
        return match (true) {
            $importancia >= 8 => '🔴',
            $importancia >= 4 => '🟠',
            default => '🟢',
        };
    }

    /**
     * A descrição vem de sistemas externos e pode conter "<", ">" ou
     * "&". Sem escapar, o Telegram rejeita a mensagem inteira com
     * "can't parse entities" — falha silenciosa e difícil de rastrear.
     */
    private function escapar(string $texto): string
    {
        return htmlspecialchars($texto, ENT_NOQUOTES, 'UTF-8');
    }
}
