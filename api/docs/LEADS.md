# Módulo de leads (FunnelsFlow)

Recebe os leads criados no [FunnelsFlow](https://funnelsflow.pro) pelo **webhook de saída**, grava no banco e avisa em um canal do Discord.

É um módulo separado do de alertas: outro endpoint, outra tabela, outra configuração e outro canal do Discord. A única coisa compartilhada é a infraestrutura (fila, worker, autenticação do painel).

## Fluxo

```
FunnelsFlow                    Alertas
    │  deal.created
    │  POST /api/leads/webhook ──►  valida token
    │                              ignora evento ≠ deal.created (200)
    │                              deduplica por id do evento
    │                              grava em "leads"
    │  ◄── 201 { lead_id }         enfileira o job
    │                                    │
    │                                    ▼
    │                              worker → embed no Discord
```

A resposta ao FunnelsFlow é imediata: a postagem no Discord acontece na fila. Se ela fosse feita dentro da requisição, a latência do Discord entraria no tempo de resposta do webhook e a plataforma poderia considerar a entrega falha e reenviar.

## Configuração

Na tela **Configuração de leads**:

1. **Webhook do Discord** — URL do canal dedicado a leads (Configurações do canal → Integrações → Webhooks). Use um canal separado do de alertas.
2. **Token do endpoint** — clique em *Gerar token novo* (48 caracteres aleatórios). Sem token configurado o endpoint **recusa tudo**: um cadastro pela metade não pode virar uma porta aberta sem ninguém perceber.
3. **Testar canal** — posta um lead fictício no Discord para validar a URL.
4. Copie a URL montada e cadastre no FunnelsFlow em **Configurações → Integrações → Webhooks de saída**, assinando **apenas** o evento `deal.created`.

### Sobre o token na URL

O endpoint aceita o token de duas formas:

| Forma | Quando usar |
|---|---|
| Header `X-Webhook-Token` | Preferida, se o FunnelsFlow permitir cabeçalhos personalizados |
| Query string `?token=...` | Quando só é possível cadastrar a URL |

A query string funciona em qualquer plataforma, mas o valor aparece em logs de servidor e proxies — por isso o header é preferível quando disponível.

> **Pendente:** o FunnelsFlow assina o POST com um `secret` mostrado uma única vez na criação do webhook, mas a documentação pública não informa o nome do header nem o algoritmo. Quando isso for confirmado na tela da plataforma, dá para validar a assinatura além do token, sem alterar nada do que já está feito — é só uma checagem a mais em `LeadWebhookController::tokenValido()`.

## O que é gravado

A tabela `leads` guarda os campos mais usados em colunas próprias (para permitir busca e ordenação no banco) **e** o payload original completo em `payload`:

| Coluna | Origem no payload |
|---|---|
| `evento_id` | `id` (ex.: `del-a1b2c3d4e5f6`) — **único** |
| `evento` | `event` |
| `deal_id`, `numero`, `titulo` | `data.deal.id` / `.number` / `.title` |
| `valor`, `moeda` | `data.deal.amount` / `.currency` |
| `status` | `data.deal.status` |
| `pipeline_nome`, `stage_nome` | `data.deal.pipelineName` / `.stageName` |
| `owner_nome`, `owner_email` | `data.deal.ownerName` / `.ownerEmail` |
| `origem`, `tags` | `data.deal.source` / `.tags` |
| `pessoa_*` | `data.deal.person.{name,email,phone}` |
| `organizacao_*` | `data.deal.organization.{name,domain}` |
| `url` | `data.deal.url` |
| `criado_em_origem` | `data.deal.createdAt` |
| `recebido_em` | Data do servidor no momento do POST |

Guardar o payload bruto custa pouco e evita perder informação que hoje não é exibida (UTMs, campos personalizados, `createdBy`) mas pode ser útil depois. O mapeamento fica em `Lead::dosDadosDoWebhook()` — um só lugar para ajustar se a plataforma mudar o formato.

Note que o webhook envia o valor do negócio em `amount`, enquanto a API de escrita do FunnelsFlow usa `value` para a mesma informação. Aqui só chega o webhook.

## Idempotência

O `evento_id` é único. Se a plataforma reenviar o mesmo evento (timeout, retry), o segundo POST responde `200 { "duplicado": true, "lead_id": ... }` em vez de criar um lead repetido e postar duas vezes no Discord.

Note que a unicidade é por **evento**, não por negócio: `deal_id` tem índice mas não é único, de propósito — se um dia passarmos a aceitar `deal.updated`, o mesmo negócio vai gerar vários registros.

## Eventos não tratados

O endpoint responde **200** (não 4xx) para qualquer evento diferente de `deal.created`, com `{ "ignorado": true }`. Para a plataforma, um erro significa "falhou, tente de novo" — devolver 4xx faria o FunnelsFlow reenviar indefinidamente algo que ignoramos por decisão de projeto.

O FunnelsFlow oferece 11 eventos (`deal.created`, `deal.updated`, `deal.stage_changed`, `deal.won`, `deal.lost`, `deal.deleted`, `activity.created`, `activity.completed`, `note.created`, `person.created`, `organization.created`). Passar a tratar outros é acrescentar o evento à constante `EVENTO_ACEITO` e decidir o que gravar.

## Testando sem o FunnelsFlow

```bash
curl -X POST "http://localhost:8000/api/leads/webhook?token=SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "id": "del-teste-001",
    "event": "deal.created",
    "createdAt": "2026-08-06T13:50:00.000Z",
    "workspaceId": "ws-xxx",
    "data": { "deal": {
      "id": "deal-abc12345",
      "number": 1234,
      "title": "Novo Lead - Site",
      "amount": 5000,
      "currency": "BRL",
      "status": "OPEN",
      "pipelineName": "Vendas",
      "stageName": "Novo Contato",
      "ownerName": "Maria Silva",
      "ownerEmail": "maria@empresa.com",
      "source": "landing_page",
      "tags": ["hot", "site"],
      "createdAt": "2026-08-06T13:50:00.000Z",
      "person": { "name": "João Silva", "email": "joao@email.com", "phone": "+5511999990000" },
      "organization": { "name": "Empresa LTDA", "domain": "empresa.com" },
      "url": "https://app.funnelsflow.pro/deals/1234"
    }}
  }'
```

Esperado: `201 { "duplicado": false, "lead_id": 1 }`. Repetir o mesmo comando devolve `{ "duplicado": true }` e **não** posta de novo no Discord.

Lembre-se de que o `queue:work` precisa estar rodando para a mensagem sair.

## Endpoints

| Método/rota | Função |
|---|---|
| `POST /api/leads/webhook` | **Público** (token). Recebe o evento do FunnelsFlow |
| `GET /api/leads` | Paginado; filtros `?busca=` e `?origem=` |
| `GET /api/leads/origens` | Origens distintas já recebidas (monta o filtro da tela) |
| `GET /api/leads/{id}` | Um lead, com o payload completo |
| `GET/PUT /api/configuracoes/leads` | Lê/grava webhook do Discord e token |
| `POST /api/configuracoes/leads/token` | Gera um token novo |
| `POST /api/configuracoes/leads/testar` | Posta um lead fictício no canal |
