# Deploy em produção — VPS Hostinger

Runbook para colocar `alertas.tuniku.com` no ar no VPS (`srv1108610.hstgr.cloud`, Ubuntu 24.04 LTS, IP `72.61.42.227`).

## Arquitetura

```
                    Traefik (80/443, TLS automático via Let's Encrypt)
                       │
        ┌──────────────┼──────────────┐
        │ /api/*                      │ /* 
        ▼                              ▼
   container "api"               container "web"
   PHP-FPM + Nginx                Nginx servindo o
   (Laravel)                      build estático do React
        │
        ▼
   container "mysql"
   (rede interna, sem porta exposta)
```

Frontend e API respondem no mesmo domínio (`alertas.tuniku.com`), diferenciados só pelo caminho `/api`. Por isso o Sanctum não precisa de configuração de CORS nem de domínio stateful — do ponto de vista do navegador é tudo a mesma origem.

## 1. Aponte o domínio para o VPS

No hPanel da Hostinger → **Domínios** → `tuniku.com` → **Gerenciador de DNS**, crie um registro:

| Tipo | Nome | Aponta para | TTL |
|---|---|---|---|
| A | `alertas` | `72.61.42.227` | padrão |

Propagação costuma levar de alguns minutos a poucas horas. Confirme antes de seguir:

```bash
dig +short alertas.tuniku.com
# deve retornar 72.61.42.227
```

Não avance para o passo 4 (emissão do certificado) antes disso — o desafio HTTP do Let's Encrypt precisa que o domínio já resolva para o servidor, senão a emissão falha.

## 2. Acesse o VPS e prepare o ambiente

Pelo hPanel: VPS → **Terminal** (abre um shell no navegador), ou via SSH local:

```bash
ssh root@72.61.42.227
```

Instale o Docker, caso o painel "Gerenciador Docker" ainda não tenha provisionado o Docker Engine (confira com `docker --version` antes de rodar isto):

```bash
curl -fsSL https://get.docker.com | sh
```

Abra o firewall para HTTP/HTTPS (o SSH já deve estar liberado, senão você não teria conectado):

```bash
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable   # confirme com "y" — não feche a sessão SSH atual antes de confirmar que a porta 22 está liberada
```

## 3. Clone o repositório

```bash
mkdir -p /opt && cd /opt
git clone https://github.com/tuniku/alertas.git
cd alertas
```

Repositório privado? O `git clone` vai pedir usuário + token (mesmo Personal Access Token usado no seu `git push` local) — ou configure uma chave SSH de deploy no GitHub (Settings → Deploy keys) e clone via `git@github.com:tuniku/alertas.git`.

## 4. Configure os segredos

```bash
cp .env.prod.example .env
nano .env   # gere senhas fortes para DB_PASSWORD e DB_ROOT_PASSWORD (ex.: openssl rand -base64 24)

cp api/.env.example api/.env
nano api/.env
```

Ajuste em `api/.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://alertas.tuniku.com

DB_HOST=mysql          # nome do serviço no docker-compose, não 127.0.0.1
DB_DATABASE=alertas
DB_USERNAME=alertas
DB_PASSWORD=            # a mesma senha que você definiu no .env da raiz
```

Gere a `APP_KEY` (precisa do PHP, mas o container ainda não existe nesse ponto — gere via um container temporário):

```bash
docker run --rm -v "$PWD/api":/app -w /app composer:2 sh -c \
  "composer install --no-dev --no-interaction --quiet && php artisan key:generate --show"
```

Copie o valor `base64:...` retornado para `APP_KEY=` em `api/.env`.

## 5. Suba a stack

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Isso builda as imagens de `api/` e `web/`, sobe `mysql`, `traefik`, `api` e `web`, roda `php artisan migrate --force` automaticamente (via `api/docker/entrypoint.sh`) e o Traefik emite o certificado TLS assim que detecta os containers com as labels — leva menos de um minuto normalmente.

Acompanhe:

```bash
docker compose -f docker-compose.prod.yml logs -f traefik   # confirme a emissão do certificado
docker compose -f docker-compose.prod.yml logs -f api       # confirme que o migrate rodou sem erro
```

Acesse **https://alertas.tuniku.com** e faça login com o usuário do seeder (`admin@alertas.local` / `admin123` — troque a senha imediatamente pela tela de Usuários, já que agora está em produção).

Teste o endpoint público:

```bash
curl -X POST https://alertas.tuniku.com/api/eventos \
  -H "Content-Type: application/json" \
  -d '{"codigo":"teste","descricao":"deploy ok"}'
```

## Atualizações futuras

```bash
cd /opt/alertas
git pull
docker compose -f docker-compose.prod.yml up -d --build
```

O `entrypoint.sh` da API roda `migrate --force` a cada subida do container — toda migration nova commitada no repositório é aplicada automaticamente no próximo deploy, sem passo manual.

## Comandos úteis

| Comando | Efeito |
|---|---|
| `docker compose -f docker-compose.prod.yml ps` | Status dos containers |
| `docker compose -f docker-compose.prod.yml logs -f <serviço>` | Logs em tempo real (`api`, `web`, `mysql`, `traefik`) |
| `docker compose -f docker-compose.prod.yml exec api php artisan tinker` | Console interativo do Laravel em produção |
| `docker compose -f docker-compose.prod.yml exec mysql mysql -u root -p alertas` | Acesso direto ao MySQL |
| `docker compose -f docker-compose.prod.yml restart api` | Reinicia só a API, sem rebuild |

## Backup do banco

```bash
docker compose -f docker-compose.prod.yml exec mysql \
  mysqldump -u root -p"$(grep DB_ROOT_PASSWORD .env | cut -d= -f2)" alertas > backup-$(date +%F).sql
```

Automatizar isso com um cron/scheduled task é uma das próximas etapas recomendadas — v1 não inclui backup automático.

## Troubleshooting

- **Certificado não emite / Traefik fica reclamando de "unable to obtain ACME certificate"**: confira se o DNS já propagou (`dig +short alertas.tuniku.com`) e se as portas 80/443 estão liberadas no `ufw` — o desafio HTTP do Let's Encrypt precisa bater na porta 80 do seu servidor.
- **`502 Bad Gateway` em `/api`**: normalmente é o container `api` ainda subindo (aguardando o `mysql` ficar healthy) — `docker compose -f docker-compose.prod.yml logs api` mostra o motivo.
- **Tela em branco no frontend**: confira no console do navegador se as chamadas estão indo para `https://alertas.tuniku.com/api/...`; se estiverem indo para `localhost`, o build foi feito sem o `VITE_API_URL` correto — rode `docker compose -f docker-compose.prod.yml up -d --build web` de novo.
