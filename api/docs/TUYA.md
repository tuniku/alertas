# Configurando o canal Tuya (lâmpada)

O driver da Tuya (`app/Notificacoes/Drivers/TuyaNotificador.php`) acende uma lâmpada inteligente na cor correspondente à severidade do alerta. É o primeiro canal que não envia mensagem — aciona um dispositivo — e ainda assim usa a mesma interface `Notificador` dos demais.

| Importância | Nível | Cor |
|---|---|---|
| ≥ 8 | Crítico | Vermelho |
| ≥ 4 | Atenção | Âmbar |
| < 4 | Informativo | Verde |

A lâmpada apaga quando o alerta ativo é **fechado** na tela "Alertas ativos". Ela não apaga por tempo: enquanto o alerta estiver aberto, o aviso continua visível. Veja "Como o desligamento funciona" abaixo.

## O que você precisa

Quatro valores, todos preenchidos na tela **Tipos de disparo**:

| Campo | Onde obter |
|---|---|
| `regiao` | Data center da sua conta: `us`, `eu`, `cn` ou `in` |
| `access_id` | Tuya IoT Platform → Cloud → seu projeto → **Overview** → Access ID/Client ID |
| `access_secret` | Mesma tela do Access ID (é segredo) |
| `device_id` | Tuya IoT Platform → Cloud → seu projeto → **Devices** → coluna Device ID |

A região precisa ser a mesma em que o projeto foi criado na Tuya. Credencial certa com região errada não dá "credencial inválida" — dá `sign invalid` ou `no permissions`, o que costuma mandar a investigação para o lado errado.

## Como a autenticação funciona

Diferente do Discord (URL de webhook) e do Telegram (token fixo), a Tuya exige **duas etapas** a cada uso:

1. **Obter um `access_token`** em `GET /v1.0/token?grant_type=1`, que vale 2 horas.
2. **Enviar o comando** em `POST /v1.0/devices/{device_id}/commands`, já com o token.

E as duas requisições precisam ser **assinadas** com HMAC-SHA256:

```
signStr = MÉTODO \n SHA256(corpo) \n headersStr \n caminho
base    = access_id [+ access_token] + t + nonce + signStr
sign    = HMAC-SHA256(base, access_secret), em MAIÚSCULAS
```

A diferença entre as duas é que o `access_token` entra no `base` só na requisição de negócio. Detalhes que a implementação respeita e que costumam ser fonte de erro:

- **`t` é timestamp em milissegundos**, não segundos.
- **`headersStr` fica vazio** (não usamos o header opcional `Signature-Headers`) — daí os dois `\n` seguidos no `signStr`.
- **Parâmetros de query entram ordenados** alfabeticamente no caminho assinado.
- **O corpo assinado tem que ser byte a byte igual ao enviado** — por isso o driver monta o JSON uma vez e envia a string pronta, em vez de deixar o cliente HTTP serializar de novo.

O token fica em **cache** (`tuya:token:<access_id>`) até 60 segundos antes de expirar, para não pedir um token novo a cada alerta. Se ainda assim a Tuya recusar por token expirado (códigos 1010/1011/1012), o driver busca um token novo e refaz o comando uma vez.

## Sobre a escala das cores

A lâmpada (categoria `dj`) expõe dois conjuntos de códigos: os antigos (`colour_data`, `bright_value`, escala 0–255) e os `_v2` (`colour_data_v2`, `bright_value_v2`, escala 0–1000). O driver usa os `_v2`:

```json
{"code": "colour_data_v2", "value": {"h": 0, "s": 1000, "v": 1000}}
```

Matiz de 0 a 360, saturação e brilho de 0 a 1000. Usar a escala antiga (máx. 255) nos códigos `_v2` **não dá erro** — a lâmpada simplesmente acende quase apagada, o que é bem mais difícil de diagnosticar do que uma falha explícita.

O comando `work_mode: "colour"` é enviado antes da cor por um motivo parecido: se a lâmpada estiver em modo branco, ela ignora o `colour_data_v2` silenciosamente e continua branca.

## Como o desligamento funciona

Quando você clica em **Fechar** na tela "Alertas ativos", a API enfileira um `EncerrarNotificacaoAlerta` para cada canal do alerta que tenha ação de encerramento. Quem decide isso é a interface `NotificadorReversivel`: o `TuyaNotificador` a implementa, o Discord e o Telegram não — uma mensagem já postada não some, então não há o que desfazer. Nenhum `if ($driver === 'tuya')` aparece no código.

O job não apaga cegamente. Antes, ele procura **outro alerta ativo que use a mesma lâmpada**:

- **Não há outro**: envia `switch_led: false` e a lâmpada apaga.
- **Há outro(s)**: em vez de apagar, reenvia a notificação do **mais grave** que restou. Na prática, se você tinha um alerta crítico (vermelho) e um informativo (verde) na mesma lâmpada e fecha o crítico, ela passa de vermelho para verde em vez de apagar.

Sem essa verificação, fechar um alerta apagaria o aviso de um problema que continua em aberto.

Dois detalhes de implementação que valem registro:

- O job é enfileirado **depois** de gravar o fechamento no banco, porque ele consulta os alertas ainda abertos — se rodasse antes, enxergaria o próprio alerta como ativo e nunca apagaria.
- A expiração é conferida em PHP (`AlertaAtivo::bloqueiaNovoDisparo()`), a mesma regra usada na deduplicação, em vez de replicada numa cláusula SQL — duplicar a condição abriria espaço para as duas divergirem com o tempo.

O desligamento só restaura o `switch_led`; não repõe a cor ou o modo que a lâmpada tinha antes do alerta. Guardar e restaurar esse estado exigiria consultar o dispositivo a cada disparo e persistir o resultado, complexidade que não se justifica para uma lâmpada de aviso.

## Testando

Na tela **Tipos de disparo** → novo registro → canal **Tuya (lâmpada)** → preencha os quatro campos → **Testar**. O teste usa importância 5, então a lâmpada deve acender em **âmbar**.

Erros comuns:

| Mensagem | Causa provável |
|---|---|
| `sign invalid` | `access_secret` errado, ou região diferente da do projeto |
| `no permissions` | O dispositivo não está vinculado ao projeto na Tuya IoT Platform |
| `token invalid` persistente | `access_id` de outro projeto/região |
| Lâmpada acende branca | `work_mode` não aplicado — confira se o modelo aceita `colour` |
| Lâmpada acende fraca | Escala de cor errada (ver seção acima) |

Toda tentativa fica registrada em `notificacao_logs`, com a mensagem de erro completa.

## Evoluções possíveis

Nenhuma delas exige migration — a `configuracao` é JSON livre:

- **Apagar sozinha após X segundos**: um campo `apagar_apos` na configuração e um `switch_led: false` agendado.
- **Cores personalizadas por faixa de importância**: campos `cor_critico`, `cor_atencao`, `cor_informativo` na configuração, substituindo o `cor()` fixo.
- **Apagar também quando o alerta expira sozinho**: hoje o encerramento acontece no fechamento manual. A expiração é "preguiçosa" (só é percebida no próximo evento recebido), então precisaria antes do job agendado que já está previsto no README.
- **Avisar no Discord/Telegram que o alerta foi fechado**: basta esses drivers implementarem `NotificadorReversivel` e postarem a mensagem de encerramento no `encerrar()`. Nenhum outro arquivo muda — o controller já enfileira o job para qualquer canal reversível.
