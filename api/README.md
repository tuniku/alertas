# Alertas — API

Backend Laravel 12 do sistema de alertas da Pentagrama: expõe uma API REST que centraliza alertas disparados por sistemas externos, com deduplicação de ocorrências ativas.

Este repositório contém **apenas a API**. A interface web é um projeto separado: [tuniku/alertas-web](https://github.com/tuniku/alertas-web).

## Instalação

> O projeto foi versionado sem a pasta `vendor/` — as dependências são instaladas na sua máquina.

Requer PHP >= 8.2, Composer e MySQL 8 (ou Docker, veja abaixo).

```bash
composer install
cp .env.example .env
php artisan key:generate

# Suba o MySQL com Docker (recomendado)...
docker compose up -d
# ...ou aponte DB_HOST/DB_USERNAME/DB_PASSWORD no .env para um MySQL já existente

php artisan migrate --seed
php artisan serve   # sobe em http://localhost:8000
```

O seeder cria o usuário inicial **admin@alertas.local / admin123** (troque a senha após o primeiro login — usado pelo frontend para autenticar).

Para desenvolver sem MySQL/Docker, use SQLite: no `.env`, troque para `DB_CONNECTION=sqlite`, remova as demais variáveis `DB_*` e rode `touch database/database.sqlite` antes do migrate.

O `docker-compose.yml` deste repositório sobe só o MySQL (porta 3306) e, opcionalmente, um phpMyAdmin (`http://localhost:8080`, login root/root) para inspecionar o banco.

## Modelo de dados

| Tabela | Papel |
|---|---|
| `projetos` | Agrupador de alertas (id, nome) |
| `alertas` | Tipo de evento que um sistema externo pode disparar |
| `tipos_disparo` | Canais de notificação configurados (driver + configuração JSON) |
| `alerta_tipo_disparo` | Ligação N:N — um alerta pode notificar em vários canais |
| `alerta_logs` | Histórico: todo evento recebido gera um registro, sempre |
| `alertas_ativos` | Controle de deduplicação (um registro ativo por alerta) |
| `notificacao_logs` | Cada tentativa de notificação, com sucesso ou erro |
| `users` | Usuários do painel (auth padrão Laravel + Sanctum) |
| `jobs` / `failed_jobs` | Fila de notificações (driver `database`) |

Decisões de modelagem e o porquê de cada uma:

- **`alertas.codigo` (slug único global)** — é o identificador que o sistema externo envia no disparo. Um código legível ("backup-falhou") não quebra quando o banco é recriado entre ambientes, ao contrário do id numérico. É único globalmente porque o endpoint de disparo é público e não carrega contexto de projeto nesta etapa.
- **`alertas.nome`** — campo adicional ao que foi especificado, para dar um rótulo legível nas telas (o código é técnico).
- **`alertas.expiracao_minutos`** — origem da data de expiração do alerta ativo, conforme definido: o TTL vem do cadastro do alerta. `null` significa que o alerta ativo nunca expira e bloqueia duplicados até ser fechado manualmente.
- **`tipos_disparo.driver` + `configuracao` (JSON)** — cada registro é um destino concreto ("Discord #alertas-carbel"), não uma categoria abstrata. Guardar a configuração em JSON evita uma coluna nova a cada integração futura: o driver de e-mail precisa de remetente e destinatário, o da Tuya precisa de `device_id` e credenciais, e nenhum deles força alteração de schema.
- **`alerta_tipo_disparo` (N:N)** — um alerta crítico pode postar no Discord e acender a lâmpada ao mesmo tempo. Substituiu a FK única `alertas.tipo_disparo_id` da primeira versão.
- **`alertas_ativos.created_at/updated_at`** — usados como "data de criação" e "data de atualização" da especificação, aproveitando os timestamps automáticos do Eloquent.
- **`alertas_ativos.fechado_por` nullable com `nullOnDelete`** — quando o registro é encerrado pelo próprio sistema (expiração), fica `null`; quando fechado manualmente, guarda o usuário.

## Notificações

Quando um **alerta ativo novo** é criado, a API enfileira um job por canal vinculado ao alerta. Eventos deduplicados não notificam — é justamente o propósito da deduplicação.

A fila usa o driver `database`, então em desenvolvimento é preciso rodar um worker em outro terminal:

```bash
php artisan queue:work
```

Sem o worker rodando, os jobs ficam acumulados na tabela `jobs` e nada é enviado. Em produção há um container `worker` dedicado (ver `docker-compose.prod.yml`).

Canais disponíveis hoje: **Discord** (webhook) e **Telegram** (bot) — a configuração do lado do Telegram está em [`docs/TELEGRAM.md`](docs/TELEGRAM.md). Para adicionar e-mail, Tuya e outros, veja [`docs/NOVO-DRIVER.md`](docs/NOVO-DRIVER.md) — é uma classe nova e uma linha na factory, sem migration nem alteração no frontend.

Cada tentativa fica registrada em `notificacao_logs` (sucesso ou mensagem de erro), e a tela "Tipos de disparo" tem um botão **Testar** que envia uma notificação fictícia para validar a configuração sem esperar um alerta real.

## Endpoint público de disparo

`POST /api/eventos` — **sem autenticação nesta etapa** (decisão de projeto: roda em rede interna; um token por projeto está previsto para etapa futura).

```bash
curl -X POST http://localhost:8000/api/eventos \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "codigo": "backup-falhou",
    "evento_em": "2026-08-04 03:15:00",
    "descricao": "Backup do banco XYZ falhou: disco cheio"
  }'
```

| Campo | Obrigatório | Descrição |
|---|---|---|
| `codigo` | sim | Código do alerta cadastrado |
| `evento_em` | não | Data/hora do evento no sistema de origem |
| `descricao` | não | Texto livre com informação adicional |

Comportamento (implementado em `EventoController`, dentro de uma transação com `lockForUpdate` para evitar corrida entre disparos simultâneos):

1. **Log**: grava sempre um registro em `alerta_logs`, com `recebido_em` preenchido pelo servidor.
2. **Deduplicação**: se existe um registro em `alertas_ativos` com `ativo = true` e não expirado, apenas atualiza o `updated_at` dele e responde `200` com `"deduplicado": true`. Caso contrário, cria um novo registro ativo (com `expira_em = agora + expiracao_minutos`, se configurado) e responde `201`. Um registro ativo já expirado é encerrado pelo sistema (`ativo = false`, `fechado_por = null`) no momento em que o novo é criado.

Respostas: `201`/`200` com `{ deduplicado, log_id, alerta_ativo_id }`, `404` se o código não existir, `422` se o corpo for inválido.

## Demais endpoints (autenticados via `Authorization: Bearer <token>`)

| Método/rota | Função |
|---|---|
| `POST /api/login` | Retorna `{ token, usuario }` |
| `POST /api/logout` · `GET /api/me` | Sessão |
| `GET/POST/PUT/DELETE /api/projetos` | CRUD de projetos |
| `GET/POST/PUT/DELETE /api/alertas` | CRUD de alertas (`?projeto_id=` filtra a listagem) |
| `GET/POST/PUT/DELETE /api/usuarios` | CRUD de usuários (senha opcional no update; não permite excluir o próprio usuário) |
| `GET /api/tipos-disparo` | Listagem simples (referência futura) |
| `GET /api/alertas-ativos` | Paginado; `?somente_ativos=0` inclui fechados |
| `POST /api/alertas-ativos/{id}/fechar` | Fecha manualmente, gravando usuário e data |
| `GET /api/logs` | Paginado; filtros `?alerta_id=` e `?projeto_id=` |

A autenticação usa **tokens Sanctum** (modo Bearer) em vez de cookies de sessão porque simplifica o CORS com o frontend (origem separada) e já serve de base se outros clientes precisarem consumir a API.

## Próximas etapas previstas

- Detalhar `tipos_disparo` e as integrações de notificação (WhatsApp etc.).
- Autenticação do endpoint de disparo (token por projeto).
- Job agendado para encerrar alertas ativos expirados mesmo sem novo disparo (hoje o encerramento acontece de forma "preguiçosa", no próximo evento recebido).
