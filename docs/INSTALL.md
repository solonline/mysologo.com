
# Yoop — Requisitos de instalação

> Levantamento do que é necessário para instalar e pôr a plataforma a correr.
> Reflecte o estado do código e da documentação em 2026-08-19.

O Yoop é uma API REST Laravel 12 (clientes via Sanctum) com backoffice Blade, integrações Stripe, Didit (KYC/KYB), Wallester (cartões/IBAN) e Ebury (corporate). Sem MySQL, Redis, worker de filas, scheduler e webhooks públicos, a API sobe mas o produto não fica operacional.

---

## 1. Visão do stack

```
  App mobile / Web / Merchant
              │ HTTPS
              ▼
         Nginx (80/443)
              │
         PHP-FPM 8.2  ── Laravel 12 (API + backoffice)
              │
     ┌────────┼────────┬──────────────┐
     ▼        ▼        ▼              ▼
  MySQL 8   Redis 7  Storage      Reverb :8080
             │       (avatares,   (WebSockets
             │        docs)        do dashboard)
             ▼
        Queue worker
        Scheduler (cron)
```

| Camada | Tecnologia |
|---|---|
| API + backoffice | Laravel 12, PHP 8.2+, Sanctum |
| Front do dashboard | Vite 7, Tailwind 4, Laravel Echo |
| Base de dados | **MySQL 8.0**, charset `utf8mb4` |
| Cache / filas | Redis 7 |
| Web | Nginx |
| Realtime (staff) | Laravel Reverb |
| Auth clientes | Sanctum (Bearer) |
| Auth staff | sessão Laravel |

O `docs/ARCHITECTURE.md` menciona PostgreSQL. O stack operacional (README e Docker) é **MySQL 8.0**. Instalar MySQL.

---

## 2. Caminhos de instalação

Há dois caminhos. O README descreve Docker; `docs/notifications.md` descreve VPS Ubuntu nativo.

| Caminho | Quando usar | Pré-requisitos de host |
|---|---|---|
| **Docker Compose** | Dev local ou VPS com Docker | Docker Engine + Compose v2 |
| **Nativo (Ubuntu/Debian)** | Produção sem Docker | PHP, Nginx, MySQL, Redis, systemd/Supervisor |

**Lacuna actual:** o repositório tem `docker/nginx.conf` e `docker/entrypoint.sh`, mas **não inclui `Dockerfile` nem `docker-compose.yml`**. Sem esses ficheiros o fluxo `docker compose up` do README não arranca. Confirmar se estão noutro branch ou repor antes de instalar via Docker.

---

## 3. Acesso, rede e máquina

### Acesso ao código

- Repositório Git privado: `git@github.com:BrunohTrindade/yoop.git`
- Chave SSH ou Personal Access Token

### Máquina

- VPS Ubuntu/Debian (o README assume isto)
- Disco com espaço para código, vendor, `node_modules`, logs e storage
- User de runtime `www-data` com escrita em `storage/` e `bootstrap/cache/`

### DNS e portas

| Porta | Uso |
|---|---|
| 80 / 443 | HTTP/HTTPS (Nginx) |
| 8080 | Reverb (interno; Nginx faz proxy `/app/`) |
| 9000 | PHP-FPM (interno) |
| 3306 | MySQL (interno) |
| 6379 | Redis (interno) |

- Domínio público a apontar para o servidor (ex.: `api.yooppayments.eu`)
- HTTPS via Let’s Encrypt / Certbot (há pasta `certbot` e bloco ACME no Nginx)
- Upload máximo: **20 MB** (`client_max_body_size` em `docker/nginx.conf`)

---

## 4. Runtime (instalação nativa)

| Requisito | Versão / notas |
|---|---|
| PHP | `^8.2` |
| Composer | `^2.2` |
| Node.js | **18+** (Vite / esbuild) |
| npm | `npm ci` + `npm run build` |
| MySQL | 8.0 |
| Redis | 7 |
| Nginx | PHP-FPM + TLS + proxy WebSocket |
| systemd ou Supervisor | workers, Reverb, opcionalmente scheduler |
| OpenSSL | gerar `APP_KEY` e chaves Reverb |
| Stripe CLI | só em desenvolvimento local |

### Extensões PHP

**Obrigatórias**

`ctype`, `filter`, `hash`, `mbstring`, `openssl`, `session`, `tokenizer`, `json`, `curl`, `pdo`, `pdo_mysql`, `fileinfo`, `xml`/`dom`, `zip`

**Fortemente recomendadas**

| Extensão | Motivo |
|---|---|
| `pcntl`, `posix` | queue worker |
| `redis` (phpredis) | cache e filas Redis |
| `intl` | telefones, IDN |
| `bcmath` | valores monetários |
| `sodium` | JWT / assinaturas |
| `gd` | imagens |

Pacote Ubuntu típico:

```bash
apt install php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath \
  php8.2-cli unzip git nginx mysql-server redis-server supervisor
```

---

## 5. Processos que têm de estar sempre a correr

A API HTTP sozinha **não chega**.

| Processo | Comando / unidade | Função |
|---|---|---|
| PHP-FPM + Nginx | `php8.2-fpm`, `nginx` | API e dashboard |
| Queue worker | `php artisan queue:work` | jobs assíncronos |
| Scheduler | cron `* * * * * php artisan schedule:run` | settlement, sync, permissões |
| Reverb | `php artisan reverb:start --host=0.0.0.0 --port=8080` | notificações do backoffice |

Laravel Horizon e Octane estão documentados em `docs/performance.md` mas **ainda não estão instalados**. Em produção usar `queue:work` (ou o contentor `queue` do Compose).

### Filas que o worker tem de escutar

O código despacha jobs para várias filas. Se o worker só escuta `default`, webhooks e emails ficam parados.

| Fila | Origem |
|---|---|
| `stripe-webhooks` | webhooks Stripe, payouts |
| `didit-webhooks` | webhooks Didit (KYC) |
| `wallester` | funding e sync de movimentos |
| `emails` | emails de KYC / onboarding |
| `default` | resto (merchant webhooks, provisioning) |

```bash
php artisan queue:work redis --queue=stripe-webhooks,didit-webhooks,wallester,emails,default
```

### Tarefas agendadas (`routes/console.php`)

Timezone da aplicação: **Europe/Lisbon** (fixo em `config/app.php`).

| Comando | Quando |
|---|---|
| `stripe:settle-balances` | 01:00 |
| `ExpireStaleStripePaymentIntentsJob` | 02:00 |
| `wallester:sync-card-movements` | 00:00 e 12:00 |
| `logs:fix-permissions` | 00:01 |

Cron:

```cron
* * * * * cd /var/www/yoop && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Variáveis de ambiente

Copiar `.env.example` para `.env` (Docker: `.env.docker`). Gerar a chave com `php artisan key:generate`.

### Obrigatórias para a app arrancar

| Variável | Função |
|---|---|
| `APP_NAME` | Nome da aplicação |
| `APP_ENV` | `local` ou `production` |
| `APP_DEBUG` | `false` em produção |
| `APP_KEY` | chave de cifra Laravel |
| `APP_URL` | URL pública da API |
| `URL_FRONTEND` | app cliente (default `https://app.yooppayments.eu`) |
| `API_DOMAIN` | subdomínio da API (opcional) |
| `DASHBOARD_DOMAIN` | subdomínio do backoffice (opcional) |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `127.0.0.1` no host; `mysql` dentro do Compose |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | ex. `yoop` |
| `DB_USERNAME` / `DB_PASSWORD` | credenciais MySQL |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | Redis |
| `CACHE_STORE` | `redis` em produção |
| `QUEUE_CONNECTION` | `redis` em produção (`database` é o default Laravel) |
| `SESSION_DRIVER` | `database` ou `redis` |
| `FILESYSTEM_DISK` | `local` (ou `s3`) |
| `LOG_CHANNEL` / `LOG_LEVEL` | logging |

### Mail (verificação de email)

Sem SMTP real o ciclo `PENDING_EMAIL_VERIFICATION` não funciona em produção.

`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`

### Reverb (notificações do backoffice)

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=wss.teudominio.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Em local: `REVERB_SCHEME=http`. Gerar chaves com `openssl rand -hex 16`.

O Nginx tem de fazer proxy WebSocket de `/app/` para `127.0.0.1:8080`. Ver `docs/notifications.md`.

### Integrações (env e/ou tabela `settings`)

Muitas chaves são lidas do env no seed e depois vivem em `settings` (editáveis no backoffice).

**Didit (KYC / KYB)**

`DIDIT_API_KEY`, `DIDIT_API_SECRET`, `DIDIT_BASE_URL`, `DIDIT_WORKFLOW_ID`, `DIDIT_WORKFLOW_KYB_ID`, `DIDIT_WEBHOOK_SECRET`

**Wallester (cartões / IBAN)**

| Variável | Função |
|---|---|
| `WALLESTER_BASE_URL` | API Wallester |
| `WALLESTER_API_KEY` | API key EUR |
| `WALLESTER_PRIVATE_KEY` | chave privada PEM EUR |
| `WALLESTER_PRODUCT_CODE` | product code EUR |
| `WALLESTER_PLATFORM_MASTER_EUR` | conta master EUR |
| `WALLESTER_USD_API_KEY` | API key USD |
| `WALLESTER_USD_PRIVATE_KEY` | chave privada PEM USD |
| `WALLESTER_USD_PRODUCT_CODE` | product code USD |
| `WALLESTER_PLATFORM_MASTER_USD` | conta master USD |
| `WALLESTER_CARD_RSA_PRIVATE_KEY` | desencriptar PAN / dados de cartão |
| `WALLESTER_AUDIT_SOURCE_TYPE` | default `Backend` |
| `WALLESTER_3DS_CHALLENGE_TTL` | TTL do desafio 3DS (segundos) |

Credenciais 3DS (`wallester.3ds_webhook_username` / `password`) configuram-se nas settings, não no env.

**Ebury** (corporate; pode simular em não-produção)

`EBURY_SIMULATE` (default `true`), `EBURY_SIMULATE_AUTO_APPROVE`, `EBURY_SIMULATE_AUTO_APPROVE_DELAY`, `EBURY_SIMULATE_USE_QUEUE`

**Stripe**

O README ainda menciona `STRIPE_KEY` / `STRIPE_WEBHOOK_SECRET`. O código actual usa **chaves por moeda na tabela `settings`**: `stripe.{eur,usd,gbp,chf}.{public_key,secret_key,webhook_secret}`.

**Opcionais**

AWS S3 (`AWS_*`), Postmark/Resend, Slack, Papertrail, `SANCTUM_STATEFUL_DOMAINS`, `DOCS_ENABLED`, `DOCS_INTERNAL_PUBLIC`.

---

## 7. Contas e webhooks externos

Sem estes parceiros o sistema sobe, mas onboarding, pagamentos e wallet não ficam operacionais.

| Parceiro | Função | Endpoint a registar no painel deles |
|---|---|---|
| Stripe | pagamentos, top-up, settlement | `POST /api/webhooks/stripe` |
| Didit | KYC retail / KYB corporate | `POST /api/webhooks/didit` |
| Wallester | contas, cartões, IBAN, movimentos | `POST /api/webhooks/wallester` |
| Wallester 3DS | desafio 3DS (HTTP Basic) | `POST /api/webhooks/wallester/3ds/initiate-action-event` e `/validate-event` |
| Ebury | onboarding corporate | `POST /api/webhooks/ebury` |
| GatewayAPI | SMS (OTP de telefone) | credenciais em `sms.*` nas settings |
| SMTP | emails de verificação | — |

Em local, Stripe precisa de Stripe CLI:

```bash
stripe listen --forward-to http://localhost/api/webhooks/stripe
```

Os outros parceiros precisam de túnel (ngrok, Cloudflare Tunnel, etc.).

Moedas previstas: **EUR, USD, GBP, CHF**. EUR é o mínimo. USD exige credenciais Stripe e Wallester próprias.

Detalhe do efeito de cada webhook: [Webhooks (inbound)](guides/webhooks.md).

---

## 8. Passos de instalação

Ordem lógica, Docker ou nativo.

### 8.1 Código e ambiente

```bash
git clone git@github.com:BrunohTrindade/yoop.git
cd yoop
cp .env.example .env   # ou: cp .env.docker .env
# Preencher APP_URL, DB_*, REDIS_*, MAIL_*, DIDIT_*, WALLESTER_*
```

### 8.2 Dependências

```bash
composer install                          # produção: --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
```

### 8.3 Base de dados e storage

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

O seed corre:

| Seeder | O que cria |
|---|---|
| `RolesSeeder` | roles e permissões do backoffice |
| `SuperAdminSeeder` | `admin@yoop.com` / `password` — **mudar já** |
| `SettingsSeeder` | empresa, Stripe, SMS, Didit, Wallester |
| `StripePaymentMethodFeeSeeder` | taxas por método de pagamento |
| `PlatformAccountSeeder` | customer platform `master@yoop.com` e contas main Wallester |

O `SettingsSeeder` inclui valores de teste/placeholder (Stripe, SMS). Substituir no backoffice ou na tabela `settings` antes de produção.

### 8.4 Cache de produção

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 8.5 Serviços

1. Arrancar Nginx + PHP-FPM (ou `docker compose up -d --build`)
2. Arrancar queue worker em todas as filas
3. Instalar cron do scheduler
4. Arrancar Reverb (`docs/notifications.md`)
5. Registar webhooks nos painéis Stripe, Didit, Wallester e Ebury

API docs (Scramble): `https://<APP_URL>/docs/api`.

---

## 9. Checklist por ambiente

### Desenvolvimento mínimo (API a responder)

- [ ] PHP 8.2, Composer, MySQL, Redis
- [ ] `.env` com `APP_KEY` e credenciais de BD
- [ ] `migrate` + `db:seed`
- [ ] `php artisan serve` **e** `php artisan queue:listen`
- [ ] Stripe CLI se fores testar pagamentos
- [ ] `EBURY_SIMULATE=true` é aceitável

### Produção completa (pagamentos + KYC + wallet)

- [ ] Tudo o de desenvolvimento
- [ ] HTTPS e DNS
- [ ] Nginx com proxy `/app/` para Reverb
- [ ] Worker nas filas `stripe-webhooks,didit-webhooks,wallester,emails,default`
- [ ] Cron do scheduler
- [ ] Reverb via systemd
- [ ] Contas reais Stripe, Didit, Wallester, SMS, SMTP
- [ ] Webhooks públicos acessíveis
- [ ] `APP_DEBUG=false`
- [ ] Password do super-admin alterada
- [ ] Chaves PEM Wallester (API + cartão)
- [ ] Credenciais 3DS nas settings
- [ ] Settings de teste substituídas

---

## 10. Docker (quando os ficheiros existirem)

O README espera este Compose:

| Serviço | Imagem |
|---|---|
| `app` | PHP 8.2 FPM (Dockerfile custom) |
| `nginx` | `nginx:alpine` |
| `mysql` | `mysql:8.0` |
| `redis` | `redis:7-alpine` |
| `queue` | mesmo Dockerfile da app |

```bash
cp .env.docker .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan db:seed --force
docker compose ps
```

O `docker/entrypoint.sh` corre `migrate --force` no arranque. Em produção também faz `config:cache`, `route:cache` e `view:cache`.

Hostname `mysql` só resolve **dentro** da rede Docker. Não correr Artisan no host com `DB_HOST=mysql`.

---

## 11. Lacunas conhecidas

1. **Faltam `Dockerfile` e `docker-compose.yml`** no tree actual.
2. **Horizon não está no `composer.json`**, apesar da doc de produção o assumir. Usar `queue:work`.
3. **PostgreSQL vs MySQL** — ignorar o diagrama em `ARCHITECTURE.md`; instalar MySQL 8.
4. O seed traz **credenciais de teste/placeholder**. Não usar em produção.
5. Existem rotas de debug em `routes/web.php` (`/debug/stripe/...`, `/test-wallester-account`). Não as deixar expostas em produção.

---

## 12. Documentação relacionada

| Documento | Conteúdo |
|---|---|
| [README.md](../README.md) | Quick start Docker |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Domínios, fluxos, modelo de dados |
| [notifications.md](notifications.md) | Reverb, systemd, proxy Nginx |
| [performance.md](performance.md) | Filas Stripe, rate limit, Horizon (pendente) |
| [guides/webhooks.md](guides/webhooks.md) | Efeito de cada webhook inbound |
| [guides/getting-started.md](guides/getting-started.md) | Primeira chamada autenticada à API |
| [STRIPE_FLOW.md](STRIPE_FLOW.md) | PaymentIntent → saldo → settlement |
