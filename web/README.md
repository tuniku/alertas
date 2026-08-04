# Alertas — Web

Frontend React 18 + Vite do sistema de alertas da Pentagrama: painel para cadastrar projetos e alertas, acompanhar alertas ativos e consultar o histórico de eventos recebidos.

Este repositório contém **apenas a interface web**. A API é um projeto separado: [tuniku/alertas](https://github.com/tuniku/alertas).

## Instalação

Requer Node 18+ e a API rodando (veja o repositório `alertas`).

```bash
npm install
cp .env.example .env.local   # ajuste VITE_API_URL se a API não estiver em localhost:8000
npm run dev                  # sobe em http://localhost:5173
```

Faça login com o usuário criado pelo seeder da API: **admin@alertas.local / admin123**.

## Telas

- **Login** — autentica via `POST /api/login` e guarda o token Sanctum no `localStorage`.
- **Alertas ativos** — lista os alertas em aberto (com filtro para incluir os fechados) e permite fechamento manual.
- **Histórico** — log paginado de todos os eventos recebidos, com filtro por projeto.
- **Projetos** e **Alertas** — CRUD de cadastro.
- **Usuários** — CRUD de usuários do painel.

Todas seguem o padrão listagem + formulário na mesma tela. Sem biblioteca de UI (CSS próprio em `src/styles.css`) para manter a v1 sem dependências além de React, React Router e Axios.

## Autenticação

O token Sanctum retornado no login é enviado em toda requisição via `Authorization: Bearer <token>` (interceptor em `src/api.js`). Uma resposta `401` de qualquer chamada limpa o token local e redireciona para `/login`.
