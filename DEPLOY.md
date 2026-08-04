# Deploy em produção — VPS Hostinger

Runbook para colocar `alertas.tuniku.com` no ar no VPS (`srv1108610.hstgr.cloud`, Ubuntu 24.04 LTS, IP `72.61.42.227`).

## Arquitetura

Esse VPS já hospeda outros projetos seus (`adbd.tuniku.com`, `amanda15anos.tuniku.com`, sites em `salinhabh.com` etc.) atrás de um **Nginx de sistema** (instalado via apt, não em container) + **Certbot**, com um arquivo de site por domínio em `/etc/nginx/sites-enabled/`, e um **MySQL de sistema** compartilhado pelos projetos (o mesmo acessível pelo phpMyAdmin em `adbd.tuniku.com`). O `alertas` segue esse padrão em vez de trazer seu próprio proxy de borda e seu próprio banco:

```
                 Nginx do sistema (systemd, fora do Docker)
                 80/443 — mesmo Nginx que serve os outros domínios
                       │
        ┌──────────────┼──────────────┐
        │ /api/*                      │ /*
        ▼                              ▼
  127.0.0.1:8082                 127.0.0.1:8081
   container "api"               container "web"
   PHP-FPM + Nginx                Nginx servindo o
   (Laravel)                      build estático do React
        │
        ▼
   MySQL do sistema (host)
   alcançado via host.docker.internal
   — banco "alertas", visível no phpMyAdmin
```

Frontend e API respondem no mesmo domínio (`alertas.tuniku.com`), diferenciados só pelo caminho `/api`. Por isso o Sanctum não precisa de configuração de CORS nem de domínio stateful — do ponto de vista do navegador é tudo a mesma origem. O TLS é emitido pelo Certbot no Nginx do sistema, do mesmo jeito que já é feito para os outros domínios desse servidor.

Usar o MySQL do host (em vez de um container próprio) tem duas vantagens práticas aqui: o banco aparece no phpMyAdmin que você já usa, junto dos outros projetos, e há um só servidor de banco para administrar e fazer backup no VPS. Em contrapartida, o `alertas` deixa de ter isolamento de banco em relação aos demais projetos — aceitável nesse cenário, em que todos os bancos do servidor são seus.

## 1. Aponte o domínio para o VPS

No hPanel da Hostinger → **Domínios** → `tuniku.com` → **Gerenciador de DNS**, crie um registro:

| Tipo | Nome | Aponta para | TTL |
|---|---|---|---|
| A | `alertas` | `72.61.42.227` | padrão |

Confirme a propagação antes de seguir:

```bash
dig +short alertas.tuniku.com
# deve retornar 72.61.42.227
```

Não avance para o passo 6 (emissão do certificado) antes disso — o desafio HTTP do Let's Encrypt precisa que o domínio já resolva para o servidor.

## 2. Acesse o VPS e prepare o ambiente

Pelo hPanel: VPS → **Terminal** (abre um shell no navegador), ou via SSH local:

```bash
ssh root@72.61.42.227
```

Confira se o Docker já está instalado:

```bash
docker --version
```

Se não estiver:

```bash
curl -fsSL https://get.docker.com | sh
```

O firewall (`ufw`) e o Nginx/Certbot já devem estar configurados nesse servidor pelos projetos anteriores — não é preciso mexer neles para o `alertas`, só adicionar um novo site (passo 6).

## 3. Clone o repositório

```bash
mkdir -p /opt && cd /opt
git clone https://github.com/tuniku/alertas.git
cd alertas
```

## 4. Crie o banco e o usuário no MySQL do host

O banco fica no MySQL do sistema, o mesmo que você administra pelo phpMyAdmin. Crie um usuário dedicado (não use o root da aplicação: se a credencial vazar, o estrago fica limitado ao banco `alertas` em vez de atingir os bancos dos outros projetos no mesmo servidor).

```bash
SENHA=$(openssl rand -base64 24)
echo "Guarde esta senha: $SENHA"

mysql -uroot -p <<SQL
CREATE DATABASE IF NOT EXISTS alertas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'alertas'@'%' IDENTIFIED BY '$SENHA';
GRANT ALL PRIVILEGES ON alertas.* TO 'alertas'@'%';
FLUSH PRIVILEGES;
SQL
```

O host `'%'` (em vez de `'localhost'`) é necessário porque a API roda em container: do ponto de vista do MySQL, a conexão chega de outro endereço IP, não do próprio host.

Confirme também que o MySQL aceita conexões da rede Docker — se `bind-address` estiver fixo em `127.0.0.1`, o container não alcança o banco:

```bash
grep -r bind-address /etc/mysql/
# esperado: 0.0.0.0 ou * ; se for 127.0.0.1, altere e rode: systemctl restart mysql
```

## 5. Configure os segredos da aplicação

```bash
cp .env.prod.example .env

cp api/.env.example api/.env
nano api/.env
```

Ajuste em `api/.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://alertas.tuniku.com

DB_HOST=host.docker.internal   # alcança o MySQL do host a partir do container
DB_DATABASE=alertas
DB_USERNAME=alertas
DB_PASSWORD=                   # a senha gerada no passo 4
```

Gere a `APP_KEY`:

```bash
docker run --rm -v "$PWD/api":/app -w /app composer:2 sh -c \
  "composer install --no-dev --no-interaction --quiet && php artisan key:generate --show"
```

Copie o valor **completo, incluindo o prefixo `base64:`** para `APP_KEY=` em `api/.env`. Sem o prefixo, o Laravel usa a chave crua em vez de decodificá-la, e qualquer recurso que dependa de criptografia (cookies, links assinados, redefinição de senha) quebra silenciosamente.

## 6. Suba a stack

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Isso builda as imagens de `api/` e `web/`, sobe `api` (publicado só em `127.0.0.1:8082`) e `web` (só em `127.0.0.1:8081`), e roda `php artisan migrate --force` automaticamente via `api/docker/entrypoint.sh`.

Acompanhe:

```bash
docker compose -f docker-compose.prod.yml logs -f api    # confirme que o migrate rodou sem erro
docker compose -f docker-compose.prod.yml ps             # confirme os 2 containers "Up"
```

Crie o usuário inicial (**só no primeiro deploy**):

```bash
docker compose -f docker-compose.prod.yml exec api php artisan db:seed --force
```

O seeder não roda automaticamente junto com o `migrate` de propósito: em produção, semear dados a cada deploy é arriscado — um seeder que evolua no futuro poderia sobrescrever registros reais. O `DatabaseSeeder` usa `firstOrCreate`, então mesmo se você rodar de novo por engano ele não duplica o usuário nem redefine uma senha já alterada.

Nesse ponto o app ainda não está acessível pela internet — falta o passo 7 (site no Nginx do sistema).

## 7. Registre o site no Nginx do sistema e emita o certificado

```bash
cp deploy/alertas.tuniku.com.conf /etc/nginx/sites-available/alertas.tuniku.com
ln -s /etc/nginx/sites-available/alertas.tuniku.com /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

`nginx -t` valida a sintaxe antes de recarregar — se der erro, **não** rode o `systemctl reload` (evita derrubar os outros sites hospedados no mesmo Nginx).

Emita o certificado:

```bash
certbot --nginx -d alertas.tuniku.com
```

O Certbot reescreve automaticamente `/etc/nginx/sites-available/alertas.tuniku.com` para adicionar o bloco HTTPS (porta 443) e o redirect de HTTP para HTTPS — é esperado o arquivo mudar sozinho depois desse comando.

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

Isso só afeta os containers `api` e `web` — não mexe no Nginx do sistema, no MySQL nem nos outros sites. O `entrypoint.sh` da API roda `migrate --force` a cada subida do container, então toda migration nova commitada no repositório é aplicada automaticamente, sem passo manual.

## Comandos úteis

| Comando | Efeito |
|---|---|
| `docker compose -f docker-compose.prod.yml ps` | Status dos containers |
| `docker compose -f docker-compose.prod.yml logs -f <serviço>` | Logs em tempo real (`api`, `web`) |
| `docker compose -f docker-compose.prod.yml exec api php artisan tinker` | Console interativo do Laravel em produção |
| `mysql -u root -p alertas` | Acesso direto ao banco (ou pelo phpMyAdmin em `adbd.tuniku.com`) |
| `docker compose -f docker-compose.prod.yml restart api` | Reinicia só a API, sem rebuild |
| `certbot renew --dry-run` | Testa a renovação do certificado sem aplicar (o Certbot já instala um timer/cron para renovar automaticamente) |

## Backup do banco

```bash
mysqldump -u root -p alertas > backup-alertas-$(date +%F).sql
```

Automatizar isso com um cron é uma das próximas etapas recomendadas — v1 não inclui backup automático.

## Troubleshooting

- **`address already in use` na porta 80/443 ao subir algum serviço**: é o Nginx do sistema, que já usa essas portas para os outros domínios — os containers do `alertas` nunca devem tentar publicar 80/443 diretamente (por isso usam `127.0.0.1:8081`/`127.0.0.1:8082`). Se aparecer, confira se algum serviço no `docker-compose.prod.yml` ganhou uma porta pública por engano.
- **`502 Bad Gateway` em `/api` ou no site**: normalmente é o container `api`/`web` ainda subindo, ou caído — `docker compose -f docker-compose.prod.yml ps` e `logs` mostram o motivo. Confirme também que `127.0.0.1:8081`/`8082` respondem localmente: `curl http://127.0.0.1:8082/api/tipos-disparo` (vai dar 401, mas confirma que o container responde).
- **Certificado não emite / Certbot reclama do desafio HTTP**: confira se o DNS já propagou (`dig +short alertas.tuniku.com`) e se o site já está em `sites-enabled` com `nginx -t` passando antes de rodar o certbot.
- **Tela em branco no frontend**: confira no console do navegador se as chamadas estão indo para `https://alertas.tuniku.com/api/...`; se estiverem indo para `localhost`, o build foi feito sem o `VITE_API_URL` correto — rode `docker compose -f docker-compose.prod.yml up -d --build web` de novo.
- **`Connection refused` para o banco**: o container não está alcançando o MySQL do host. Confira, nesta ordem: `DB_HOST=host.docker.internal` em `api/.env`; o `bind-address` do MySQL (não pode ser `127.0.0.1`); e se o usuário foi criado com host `'%'`, não `'localhost'`.
- **"Credenciais inválidas" no login mesmo com a senha certa**: o seeder não rodou — veja o passo do `db:seed --force`.
