# Configurando o canal do Telegram

O driver do Telegram já está implementado (`app/Notificacoes/Drivers/TelegramNotificador.php`). O que falta é a parte que só existe do lado do Telegram: criar um bot e descobrir para onde ele deve mandar as mensagens. São dois valores no final — `bot_token` e `chat_id` — que você cola na tela **Tipos de disparo**.

Por que um bot e não algo como o webhook do Discord: o Telegram não permite que um sistema externo mande mensagem para uma pessoa sem que ela tenha autorizado antes. Toda comunicação sai de um **bot**, e o destinatário precisa ter iniciado conversa com ele (ou o bot precisa ter sido adicionado ao grupo/canal).

## 1. Criar o bot

No Telegram, procure por **@BotFather** (é o bot oficial do próprio Telegram para gerenciar bots) e:

1. Envie `/newbot`.
2. Informe o **nome de exibição** (livre, ex.: `Alertas Pentagrama`).
3. Informe o **username**, que é único no Telegram inteiro e precisa terminar em `bot` (ex.: `pentagrama_alertas_bot`).

O BotFather responde com o **token**, no formato `123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw`.

> O token é uma credencial: quem o tiver consegue enviar mensagens como o bot. Ele fica salvo em `tipos_disparo.configuracao` no banco. Se vazar, volte ao BotFather e use `/revoke` para gerar outro.

## 2. Escolher o destino

| Destino | Quando faz sentido | O que é preciso |
|---|---|---|
| **Chat privado** | Só você recebe | Abrir o bot e enviar `/start` — sem isso o Telegram bloqueia o envio |
| **Grupo** | Equipe recebe e pode conversar sobre o alerta | Adicionar o bot como membro |
| **Canal** | Transmissão: só o bot posta, o resto lê | Adicionar o bot como **administrador** (membro comum não posta em canal) |

O equivalente mais próximo do que já foi feito no Discord é um **grupo ou canal dedicado a alertas**.

## 3. Descobrir o `chat_id`

O `chat_id` é o identificador numérico do destino. A API do Telegram exige esse número — não aceita o nome nem o @username do grupo.

1. Garanta que o bot já está no destino (passo 2).
2. Envie **qualquer mensagem** no chat/grupo/canal (em grupo, se o bot estiver em modo privacidade, mencione ele: `@pentagrama_alertas_bot oi`).
3. Abra no navegador:

```
https://api.telegram.org/bot<SEU_TOKEN>/getUpdates
```

(o `bot` na frente do token faz parte da URL — fica `.../bot123456789:AAH...`)

4. Na resposta JSON, procure `"chat": { "id": ... }`.

Como interpretar o número:

- **Positivo** (`123456789`) → chat privado.
- **Negativo** (`-1001234567890`) → grupo ou canal.

Se o `getUpdates` voltar `{"ok":true,"result":[]}`, é porque não há mensagem recente para o bot ver: mande uma mensagem nova no destino e recarregue. Vale lembrar que o Telegram só guarda essas atualizações por cerca de 24 horas.

## 4. Cadastrar no sistema

Na tela **Tipos de disparo**:

1. **Nome**: algo reconhecível, ex.: `Telegram — Suporte`.
2. **Canal**: `Telegram (bot)`.
3. **Token do bot** e **ID do chat**: os valores dos passos 1 e 3.
4. Salvar e clicar em **Testar** — deve chegar uma mensagem no destino imediatamente (o teste é síncrono, não passa pela fila).
5. Em **Alertas**, marque esse tipo de disparo nos alertas que devem notificar por lá.

Um mesmo bot pode servir a vários tipos de disparo: basta repetir o cadastro com o mesmo `bot_token` e um `chat_id` diferente. Isso é útil para separar, por exemplo, alertas críticos de um cliente em um grupo próprio.

## Erros comuns no teste

| Mensagem | Causa |
|---|---|
| `chat not found` | `chat_id` errado, ou o bot não está no grupo/canal |
| `bot was blocked by the user` | Chat privado sem `/start`, ou o bot foi bloqueado |
| `Unauthorized` | Token inválido, incompleto ou revogado |
| `not enough rights to send text messages to the chat` | Em canal, o bot está como membro e não como administrador |

Toda tentativa, com sucesso ou erro, fica registrada na tabela `notificacao_logs`.
