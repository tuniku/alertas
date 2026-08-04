# Sistema de Alertas

Central de alertas da Pentagrama: sistemas externos disparam eventos via HTTP e o sistema registra o histórico e controla alertas ativos com deduplicação.

## Estrutura do repositório

```
alertas/
├── api/   Backend Laravel 12 — API REST, autenticação Sanctum (ver api/README.md)
└── web/   Frontend React 18 + Vite (ver web/README.md)
```

API e interface ficam em pastas separadas — e não são acopladas: o backend não serve HTML e o frontend só fala com a API por HTTP. Isso permite hospedar cada uma em servidores diferentes e, no futuro, plugar outros consumidores (apps móveis, bots de WhatsApp etc.) na mesma API, mesmo estando as duas versionadas no mesmo repositório.

## Instalação rápida

1. Backend: siga [`api/README.md`](api/README.md) — inclui um `docker-compose.yml` para subir o MySQL.
2. Frontend: siga [`web/README.md`](web/README.md) — depende da API rodando.

## Documentação

- [`api/README.md`](api/README.md) — instalação, modelo de dados e contrato de todos os endpoints, incluindo o disparo público de eventos.
- [`web/README.md`](web/README.md) — instalação e telas do painel.
