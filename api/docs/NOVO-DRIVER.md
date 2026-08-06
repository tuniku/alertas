# Como adicionar um novo canal de notificação

O sistema já está preparado para e-mail e o que mais vier. Adicionar um canal novo é **criar uma classe e registrar uma linha** — nada de migration, nada de mudança no frontend, nada de mexer nos controllers.

Hoje existem três drivers implementados, e vale olhar o mais parecido com o seu caso antes de escrever o próximo:

| Driver | Arquivo | Serve de referência para |
|---|---|---|
| Discord | `app/Notificacoes/Drivers/DiscordNotificador.php` | Um único campo de configuração (a URL do webhook já embute o destino); mensagem rica (embed com cor por severidade) |
| Telegram | `app/Notificacoes/Drivers/TelegramNotificador.php` | Vários campos de configuração (token + destino); texto com marcação limitada; erro lido do corpo da resposta, não do status HTTP |
| Tuya | `app/Notificacoes/Drivers/TuyaNotificador.php` | Canal que **aciona um dispositivo** em vez de enviar texto; autenticação em duas etapas com token de vida curta em cache; requisição assinada com HMAC |

## Passo 1 — criar o driver

Crie `app/Notificacoes/Drivers/SeuNotificador.php` implementando a interface `Notificador`. O exemplo abaixo é um driver de **e-mail** — escolhido de propósito porque mostra que o canal não precisa ser uma chamada HTTP:

```php
<?php

namespace App\Notificacoes\Drivers;

use App\Models\TipoDisparo;
use App\Notificacoes\MensagemAlerta;
use App\Notificacoes\Notificador;
use Illuminate\Support\Facades\Mail;

class EmailNotificador implements Notificador
{
    public static function rotulo(): string
    {
        return 'E-mail';
    }

    /**
     * Regras de validação da configuração. As chaves viram os campos do
     * formulário na tela "Tipos de disparo" automaticamente.
     */
    public static function regrasDeConfiguracao(): array
    {
        return [
            'configuracao.destinatario' => ['required', 'email'],
        ];
    }

    public function enviar(MensagemAlerta $mensagem, TipoDisparo $tipoDisparo): void
    {
        $corpo = "[{$mensagem->nivel()}] {$mensagem->alerta}\n"
            ."Projeto: {$mensagem->projeto}\n"
            ."Código: {$mensagem->codigo}\n\n"
            .($mensagem->descricao ?? '');

        // O Mail do Laravel já lança exceção quando o SMTP recusa, então
        // aqui não é preciso checar retorno — mas se o seu canal for uma
        // API HTTP, lance a exceção você mesmo (veja abaixo).
        Mail::raw($corpo, function ($m) use ($mensagem, $tipoDisparo) {
            $m->to($tipoDisparo->configuracao['destinatario'])
              ->subject("[{$mensagem->nivel()}] {$mensagem->alerta}");
        });
    }
}
```

### Lançar exceção em caso de falha é obrigatório

É o que faz a fila registrar o erro em `notificacao_logs` e agendar a retentativa (`tries=3`, com backoff). Em drivers HTTP, o cuidado é que **muitas APIs respondem HTTP 200 mesmo quando recusam a mensagem** — o Telegram é um exemplo: devolve 200 com `{"ok": false, "description": "..."}`. Por isso o `TelegramNotificador` checa as duas coisas:

```php
if ($resposta->failed() || $resposta->json('ok') !== true) {
    throw new RuntimeException(/* ... */);
}
```

### Escape do texto

Se o canal interpreta marcação (HTML no Telegram, Markdown no Slack), passe a `descricao` por um escape antes de montar a mensagem. Ela vem de sistemas externos e pode conter caracteres que quebram o parser — o Telegram, por exemplo, rejeita a mensagem inteira com "can't parse entities" se encontrar um `<` solto.

## Passo 2 — registrar na factory

Em `app/Notificacoes/NotificadorFactory.php`, acrescente uma linha:

```php
private const DRIVERS = [
    'discord' => DiscordNotificador::class,
    'telegram' => TelegramNotificador::class,
    'email' => EmailNotificador::class,   // <- nova linha
];
```

Pronto. O driver já aparece no seletor da tela "Tipos de disparo", com os campos de configuração corretos, validação e botão de teste funcionando.

## Passo 3 (opcional) — rótulos amigáveis no frontend

O formulário se monta sozinho a partir das `regrasDeConfiguracao()`, usando o nome bruto do campo como rótulo (`destinatario`). Para deixar apresentável, acrescente entradas em `web/src/pages/TiposDisparo.jsx`:

```js
const ROTULOS_CAMPO = { /* ... */ destinatario: 'E-mail de destino' };
const AJUDA_CAMPO = { /* ... */ destinatario: 'Quem recebe o aviso.' };
const PLACEHOLDER_CAMPO = { /* ... */ destinatario: 'suporte@empresa.com.br' };
```

É cosmético: sem isso o driver funciona igual, só fica com rótulo técnico e sem texto de ajuda.

## Passo 4 (opcional) — ação ao fechar o alerta

Se o seu canal tem algo a **desfazer** quando o alerta ativo é fechado (apagar uma lâmpada, desligar um relé, silenciar uma sirene), implemente também a interface `NotificadorReversivel`:

```php
class SeuNotificador implements Notificador, NotificadorReversivel
{
    // ...

    public function encerrar(MensagemAlerta $mensagem, TipoDisparo $tipoDisparo): void
    {
        // desfaz o efeito
    }
}
```

É uma interface separada de propósito: uma mensagem já postada no Discord não some, então obrigar todo driver a declarar um `encerrar()` vazio seria ruído. Quem implementa é acionado pelo `AlertaAtivoController` no fechamento; quem não implementa é ignorado, sem nenhuma condição por nome de driver no código.

Detalhe importante do fluxo, implementado no `EncerrarNotificacaoAlerta`: antes de encerrar, o job verifica se **outro alerta ainda ativo usa o mesmo canal**. Se usa, em vez de encerrar ele reenvia a notificação do mais grave que restou — do contrário, fechar um alerta apagaria o aviso de um problema ainda aberto.

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

## Canais que não são mensagem

O driver da Tuya não "envia mensagem" — acende uma lâmpada. Mesmo assim coube na interface sem nenhuma adaptação: `enviar()` chama a API da Tuya, a `configuracao` guarda as credenciais e o `device_id`, e a `importancia` vira cor. Se o seu canal novo for uma sirene, um relé ou um display, o molde é esse — veja [`TUYA.md`](TUYA.md).

Vale notar o que os três drivers fazem com a `importancia`: `DiscordNotificador::cor()` escolhe a cor do embed, `TelegramNotificador::icone()` escolhe o emoji e `TuyaNotificador::cor()` escolhe o HSV da lâmpada. A escala de severidade é a mesma (`MensagemAlerta::nivel()`), só a representação muda.

## Quando a notificação é disparada

Apenas quando um **alerta ativo novo** é criado — eventos deduplicados não repetem o aviso, que é justamente o propósito da deduplicação. A lógica está em `EventoController::enfileirarNotificacoes()`.

Cada canal vira um job separado na fila: se o Telegram estiver fora do ar, a retentativa atinge só ele, sem reenviar o que já chegou no Discord. Toda tentativa (com sucesso ou erro) fica registrada em `notificacao_logs`.
