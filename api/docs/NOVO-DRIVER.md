# Como adicionar um novo canal de notificação

O sistema já está preparado para e-mail, Telegram, Tuya e o que mais vier. Adicionar um canal novo é **criar uma classe e registrar uma linha** — nada de migration, nada de mudança no frontend, nada de mexer nos controllers.

## Passo 1 — criar o driver

Crie `app/Notificacoes/Drivers/SeuNotificador.php` implementando a interface `Notificador`:

```php
<?php

namespace App\Notificacoes\Drivers;

use App\Models\TipoDisparo;
use App\Notificacoes\MensagemAlerta;
use App\Notificacoes\Notificador;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramNotificador implements Notificador
{
    public static function rotulo(): string
    {
        return 'Telegram';
    }

    /**
     * Regras de validação da configuração. As chaves viram os campos do
     * formulário na tela "Tipos de disparo" automaticamente.
     */
    public static function regrasDeConfiguracao(): array
    {
        return [
            'configuracao.bot_token' => ['required', 'string'],
            'configuracao.chat_id' => ['required', 'string'],
        ];
    }

    public function enviar(MensagemAlerta $mensagem, TipoDisparo $tipoDisparo): void
    {
        $config = $tipoDisparo->configuracao;

        $resposta = Http::timeout(10)->post(
            "https://api.telegram.org/bot{$config['bot_token']}/sendMessage",
            [
                'chat_id' => $config['chat_id'],
                'parse_mode' => 'HTML',
                'text' => "<b>[{$mensagem->nivel()}] {$mensagem->alerta}</b>\n"
                    ."Projeto: {$mensagem->projeto}\n"
                    .($mensagem->descricao ?? ''),
            ]
        );

        // Lançar exceção em caso de falha é importante: é o que faz a
        // fila registrar o erro e agendar a retentativa.
        if ($resposta->failed()) {
            throw new RuntimeException("Telegram recusou: {$resposta->body()}");
        }
    }
}
```

## Passo 2 — registrar na factory

Em `app/Notificacoes/NotificadorFactory.php`, acrescente uma linha:

```php
private const DRIVERS = [
    'discord' => DiscordNotificador::class,
    'telegram' => TelegramNotificador::class,   // <- nova linha
];
```

Pronto. O driver já aparece no seletor da tela "Tipos de disparo", com os campos de configuração corretos, validação e botão de teste funcionando.

## O que a `MensagemAlerta` oferece

O driver não conhece Eloquent nem a estrutura das tabelas — recebe só um objeto neutro:

| Propriedade | Conteúdo |
|---|---|
| `projeto` | Nome do projeto |
| `alerta` | Nome do alerta |
| `codigo` | Código usado no disparo |
| `importancia` | 0 a 10 |
| `descricao` | Texto livre enviado pelo sistema externo |
| `eventoEm` | Data/hora do evento na origem (pode ser null) |
| `recebidoEm` | Data/hora em que o alerta ativo foi criado |
| `nivel()` | "Crítico" (≥8), "Atenção" (≥4) ou "Informativo" |

## Caso especial: Tuya (lâmpada)

O driver da Tuya não "envia mensagem" — aciona um dispositivo. Ainda assim se encaixa na mesma interface: `enviar()` faz a chamada à API da Tuya, e a `configuracao` guarda `device_id`, `access_id` e `access_secret`. A `importancia` da mensagem serve para escolher a cor (o `DiscordNotificador::cor()` faz exatamente isso, e vale como referência).

## Quando a notificação é disparada

Apenas quando um **alerta ativo novo** é criado — eventos deduplicados não repetem o aviso, que é justamente o propósito da deduplicação. A lógica está em `EventoController::enfileirarNotificacoes()`.

Cada canal vira um job separado na fila: se o Telegram estiver fora do ar, a retentativa atinge só ele, sem reenviar o que já chegou no Discord. Toda tentativa (com sucesso ou erro) fica registrada em `notificacao_logs`.
