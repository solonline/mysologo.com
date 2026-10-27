# Migração do Yoop para um servidor novo

Checklist da instalação que está em produção neste VPS (Ubuntu 22.04). O objetivo é o Redis, o MySQL e os workers subirem sozinhos depois de um reboot.

O Redis ficou fora depois do reboot de 18 de outubro de 2026 porque `/var/tmp` estava imutável (`chattr +i`) e a unit do Redis usa `PrivateTmp=yes`. O systemd não conseguiu criar `/var/tmp/systemd-private-…` e o serviço morreu com `Operation not permitted`. Os workers ficaram em loop porque o Redis não estava a escutar em `127.0.0.1:6379`.

## O que não fazer

1. Não aplicar `chattr +i` nem `chattr +a` em `/tmp` ou `/var/tmp`. O systemd precisa de criar diretórios aí para serviços com `PrivateTmp=yes` (Redis, logind, resolved, timesyncd, logrotate).
2. Não copiar `/etc/systemd/system/redis-server.service` deste servidor. Esse ficheiro substitui a unit do pacote e muda `Type=notify` para `Type=simple`. No servidor novo, usar só a unit que o pacote `redis-server` instala.
3. Não abrir as portas 3306 (MySQL) nem 6379 (Redis) para a internet. Os dois ficam em `127.0.0.1`.
4. Não instalar o `xdebug` em produção.
5. Não ativar ao mesmo tempo `yoop-queue.service` e os workers por fila. Os dois consomem as mesmas filas. Neste guia ficam só os cinco workers dedicados.
6. Não levar a unit `package-agents-185.service`. Não faz parte da aplicação.

## 1. Sistema

Ubuntu 22.04 LTS, com hora de Portugal.

```bash
timedatectl set-timezone Europe/Lisbon
apt update
apt install -y ca-certificates curl gnupg git unzip ufw software-properties-common
```

Confirmar que `/tmp` e `/var/tmp` são graváveis pelo root:

```bash
lsattr -d /tmp /var/tmp
mkdir -p /var/tmp/probe-yoop && rmdir /var/tmp/probe-yoop
```

`lsattr` não pode mostrar a letra `i` nesses dois diretórios. O `mkdir` tem de funcionar.

## 2. PHP 8.4

A API corre em PHP 8.4 (PPA Ondřej) com PHP-FPM. O Apache está instalado neste VPS e está parado; no servidor novo não é preciso.

```bash
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-intl \
  php8.4-bcmath php8.4-opcache php8.4-readline php8.4-igbinary
```

O socket que o Nginx usa é `/run/php/php8.4-fpm.sock`, pool `www`, utilizador `www-data`.

```bash
systemctl enable --now php8.4-fpm
php -m | grep -E 'redis|pdo_mysql|mbstring|curl|gd|intl|zip|bcmath'
```

Composer 2:

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

## 3. MySQL 8

```bash
apt install -y mysql-server
systemctl enable --now mysql
```

O MySQL tem de escutar só em localhost. Em `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
bind-address = 127.0.0.1
mysqlx-bind-address = 127.0.0.1
```

Criar a base e o utilizador (a password fica só no `.env`, nunca no git):

```sql
CREATE DATABASE yoop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'yoop'@'127.0.0.1' IDENTIFIED BY 'TROCAR';
GRANT ALL PRIVILEGES ON yoop.* TO 'yoop'@'127.0.0.1';
FLUSH PRIVILEGES;
```

No servidor atual, copiar os dados:

```bash
mysqldump --single-transaction --routines --triggers yoop | gzip > yoop.sql.gz
```

No servidor novo:

```bash
gunzip -c yoop.sql.gz | mysql yoop
```

Reiniciar e confirmar:

```bash
systemctl restart mysql
mysqladmin ping
```

## 4. Redis

Esta é a parte que falhou.

```bash
apt install -y redis-server
systemctl enable redis-server
```

Deixar a unit do pacote. Confirmar que **não** existe override:

```bash
systemctl cat redis-server
```

O `ExecStart` deve apontar para `/usr/bin/redis-server /etc/redis/redis.conf` e o tipo deve ser `notify` (o valor do pacote Ubuntu). Se aparecer um ficheiro em `/etc/systemd/system/redis-server.service`, apagar esse ficheiro e correr `systemctl daemon-reload`.

Em `/etc/redis/redis.conf`, manter:

```conf
bind 127.0.0.1 ::1
protected-mode yes
port 6379
supervised systemd
daemonize no
dir /var/lib/redis
appendonly no
```

Não definir `requirepass` vazio de forma inconsistente com o `.env`. Se `REDIS_PASSWORD` estiver vazio, o Redis fica sem password e só acessível em localhost.

O Redis avisa quando o overcommit está desligado e o `BGSAVE` pode falhar. Gravar isto:

```bash
echo 'vm.overcommit_memory = 1' > /etc/sysctl.d/99-redis.conf
sysctl -p /etc/sysctl.d/99-redis.conf
```

Subir e testar:

```bash
systemctl restart redis-server
redis-cli ping
```

A resposta tem de ser `PONG`. `ss -lnt | grep 6379` tem de mostrar `127.0.0.1:6379`, nunca `0.0.0.0:6379`.

O contentor Docker `evolution-api_redis_1` deste VPS é outro Redis, de outro projeto, e não publica a porta no host. A API do Yoop usa o Redis do sistema.

## 5. Código da API

Repositório: `git@github.com:BrunohTrindade/yoop.git`.

```bash
git clone git@github.com:BrunohTrindade/yoop.git /var/www/yoop
cd /var/www/yoop
composer install --no-dev --optimize-autoloader
```

Copiar o `.env` de produção à mão. Valores que a aplicação usa hoje:

| Variável | Valor em produção |
| --- | --- |
| `APP_ENV` | `production` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `yoop` |
| `QUEUE_CONNECTION` | `redis` |
| `CACHE_STORE` | `redis` |
| `REDIS_CLIENT` | `phpredis` |
| `REDIS_HOST` | `127.0.0.1` |
| `REDIS_PORT` | `6379` |
| `SESSION_DRIVER` | `database` |
| `BROADCAST_CONNECTION` | `log` |
| `MAIL_MAILER` | `smtp` |

Gerar a key só se o `.env` novo não trouxer `APP_KEY`. Se a key mudar, as sessões e os dados cifrados antigos deixam de abrir.

```bash
php artisan key:generate --force   # só num .env sem APP_KEY
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permissões:

```bash
chown -R www-data:www-data /var/www/yoop/storage /var/www/yoop/bootstrap/cache
chmod -R ug+rwx /var/www/yoop/storage /var/www/yoop/bootstrap/cache
mkdir -p /var/log/yoop /var/www/yoop/storage/logs/queue
chown www-data:www-data /var/log/yoop /var/www/yoop/storage/logs/queue
```

Os workers escrevem em `/var/log/yoop/*.log`. Esse diretório tem de ser gravável por `www-data` antes de os ativar.

## 6. Workers

Cinco units, todas com `User=www-data`, `WorkingDirectory=/var/www/yoop`, `Restart=always` e `RestartSec=5`. Dependem do Redis e do MySQL para não arrancarem antes deles.

`/etc/systemd/system/yoop-worker-default.service`

```ini
[Unit]
Description=Yoop Queue Worker - default
After=network.target redis-server.service mysql.service
Wants=redis-server.service mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/yoop
ExecStart=/usr/bin/php /var/www/yoop/artisan queue:work redis --queue=default --tries=3 --timeout=120 --sleep=3
Restart=always
RestartSec=5
StandardOutput=append:/var/log/yoop/default.log
StandardError=append:/var/log/yoop/default.log

[Install]
WantedBy=multi-user.target
```

`/etc/systemd/system/yoop-worker-emails.service`

```ini
[Unit]
Description=Yoop Queue Worker - emails
After=network.target redis-server.service mysql.service
Wants=redis-server.service mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/yoop
ExecStart=/usr/bin/php /var/www/yoop/artisan queue:work redis --queue=emails --tries=3 --timeout=90 --sleep=3
Restart=always
RestartSec=5
StandardOutput=append:/var/log/yoop/emails.log
StandardError=append:/var/log/yoop/emails.log

[Install]
WantedBy=multi-user.target
```

`/etc/systemd/system/yoop-worker-stripe-webhooks.service`

```ini
[Unit]
Description=Yoop Queue Worker - stripe-webhooks
After=network.target redis-server.service mysql.service
Wants=redis-server.service mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/yoop
ExecStart=/usr/bin/php /var/www/yoop/artisan queue:work redis --queue=stripe-webhooks --tries=3 --timeout=60 --sleep=3
Restart=always
RestartSec=5
StandardOutput=append:/var/log/yoop/stripe-webhooks.log
StandardError=append:/var/log/yoop/stripe-webhooks.log

[Install]
WantedBy=multi-user.target
```

`/etc/systemd/system/yoop-worker-didit-webhooks.service`

```ini
[Unit]
Description=Yoop Queue Worker - didit-webhooks
After=network.target redis-server.service mysql.service
Wants=redis-server.service mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/yoop
ExecStart=/usr/bin/php /var/www/yoop/artisan queue:work redis --queue=didit-webhooks --tries=3 --timeout=120 --sleep=3
Restart=always
RestartSec=5
StandardOutput=append:/var/log/yoop/didit-webhooks.log
StandardError=append:/var/log/yoop/didit-webhooks.log

[Install]
WantedBy=multi-user.target
```

`/etc/systemd/system/yoop-worker-wallester.service`

```ini
[Unit]
Description=Yoop Queue Worker - wallester
After=network.target redis-server.service mysql.service
Wants=redis-server.service mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/yoop
ExecStart=/usr/bin/php /var/www/yoop/artisan queue:work redis --queue=wallester --tries=3 --timeout=180 --sleep=3
Restart=always
RestartSec=5
StandardOutput=append:/var/log/yoop/wallester.log
StandardError=append:/var/log/yoop/wallester.log

[Install]
WantedBy=multi-user.target
```

Ativar:

```bash
systemctl daemon-reload
systemctl enable --now \
  yoop-worker-default \
  yoop-worker-emails \
  yoop-worker-stripe-webhooks \
  yoop-worker-didit-webhooks \
  yoop-worker-wallester
```

Depois de cada deploy:

```bash
php artisan queue:restart
```

## 7. Cron do scheduler

Crontab do utilizador `www-data` (não do root):

```bash
crontab -u www-data -e
```

```cron
* * * * * cd /var/www/yoop && php artisan schedule:run >> /dev/null 2>&1
```

Tarefas definidas em `routes/console.php`:

| Quando | Comando |
| --- | --- |
| todos os dias 00:01 | `logs:fix-permissions` |
| todos os dias 01:00 | `stripe:settle-balances` |
| todos os dias 02:00 | `ExpireStaleStripePaymentIntentsJob` |
| 00:00 e 12:00 | `wallester:sync-card-movements` |
| de 6 em 6 horas | `wallet:backfill-stripe-fees-to-sub-master` |

Sem esta linha, as filas Redis até processam jobs, mas o agendamento diário não corre.

## 8. Nginx e TLS

```bash
apt install -y nginx certbot python3-certbot-nginx
mkdir -p /var/www/certbot
```

Dois vhosts apontam para o mesmo `public` do Laravel.

- `api.yooppayments.eu` → `/var/www/yoop/public`
- `dashboard.yooppayments.eu` → `/var/www/yoop/public`, com redirect de `/` para `/dashboard`

Os dois usam `fastcgi_pass unix:/run/php/php8.4-fpm.sock`, `client_max_body_size 20M` e `fastcgi_read_timeout 180`. HTTP na porta 80 só serve o desafio do Let's Encrypt e redireciona para HTTPS.

Certificados:

```bash
certbot certonly --webroot -w /var/www/certbot \
  -d api.yooppayments.eu -d dashboard.yooppayments.eu
```

No servidor atual o dashboard reutiliza o certificado de `api.yooppayments.eu`. No servidor novo, emitir um certificado que cubra os dois nomes.

Renovação: o pacote já instala `/etc/cron.d/certbot`. Confirmar com `systemctl status certbot.timer` ou `certbot renew --dry-run`.

A app em `/var/www/yoopfront` (Vite, `yoop-app`) é servida à parte, na porta 8080, com raiz `/var/www/yoopfront/dist` e `try_files` para `index.html`. Node.js 20:

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
cd /var/www/yoopfront
npm ci
npm run build
```

## 9. Firewall

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw deny 3306/tcp
ufw deny 6379/tcp
ufw enable
```

Abrir a 8080 só se o frontend continuar a ser publicado nessa porta. MySQL e Redis ficam fechados.

## 10. Prova depois do reboot

Reiniciar o servidor uma vez, de propósito, e correr:

```bash
lsattr -d /tmp /var/tmp
systemctl is-active redis-server mysql nginx php8.4-fpm
redis-cli ping
systemctl is-active \
  yoop-worker-default \
  yoop-worker-emails \
  yoop-worker-stripe-webhooks \
  yoop-worker-didit-webhooks \
  yoop-worker-wallester
cd /var/www/yoop && php artisan schedule:list
```

Tudo tem de estar `active`, o `ping` tem de responder `PONG`, e `lsattr` não pode mostrar `i` em `/tmp` nem em `/var/tmp`.

Se o Redis falhar outra vez, o comando que mostra o motivo é:

```bash
systemctl status redis-server --no-pager -l
journalctl -u redis-server -b --no-pager
```

`Failed to run 'start' task: Operation not permitted` com `Result=resources` significa que o `PrivateTmp` não conseguiu escrever em `/tmp` ou `/var/tmp`.
