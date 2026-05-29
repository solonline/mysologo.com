# Sistema de Notificações — Yoop

## Visão Geral

O sistema tem dois canais independentes:

| Canal | Destinatário | Transporte |
|---|---|---|
| **Web (backoffice)** | Utilizadores do dashboard | Reverb (WebSockets) + DB |
| **API (in-app)** | Clientes mobile/frontend | REST (polling) + DB |

---

## Arquitectura

```
Tu chamas manualmente:
  $notificationService->notifyUsers(...)     ← backoffice
  $notificationService->notifyCustomer(...)  ← clientes

notifyUsers()
  └─ Filtra User com a permissão indicada
  └─ Insere registo em user_notifications para cada um
  └─ broadcast(DashboardNotificationCreated) via Reverb
        └─ Canal privado: private-dashboard.{userId}
        └─ Alpine.js + Echo no browser recebe e actualiza badge

notifyCustomer()
  └─ Insere registo em customer_notifications
  └─ Cliente faz GET /api/notifications para buscar
```

---

## Tabelas

### `user_notifications`
| Coluna | Tipo | Descrição |
|---|---|---|
| `user_id` | FK | Utilizador do backoffice |
| `type` | string | Identificador do evento (ex: `transaction.created`) |
| `title` | string | Título da notificação |
| `body` | text\|null | Corpo do texto |
| `data` | JSON\|null | Payload extra (ids, valores, etc.) |
| `permission_required` | string\|null | Permissão que originou a notificação |
| `url` | string\|null | Rota para onde navegar ao clicar |
| `read_at` | timestamp\|null | Nulo = não lida |

### `customer_notifications`
| Coluna | Tipo | Descrição |
|---|---|---|
| `customer_id` | FK | Cliente |
| `type` | string | Identificador do evento |
| `title` | string | Título |
| `body` | text\|null | Corpo |
| `data` | JSON\|null | Payload extra |
| `read_at` | timestamp\|null | Nulo = não lida |

---

## Como Usar

### Notificar utilizadores do backoffice

```php
use App\Services\NotificationService;

// Injecta no construtor do teu Service
public function __construct(
    protected NotificationService $notificationService
) {}

// Chama onde quiseres — ex: dentro de StripeService após criar transacção
$this->notificationService->notifyUsers(
    type:       'transaction.created',
    title:      'Nova transacção registada',
    permission: 'transactions.view',   // só quem tem esta permissão recebe
    body:       "Transacção de €{$amount} criada para {$customer->name}.",
    url:        route('dashboard.transactions.show', $transaction->id),
    data:       [
        'transaction_id' => $transaction->id,
        'amount'         => $amount,
    ],
);
```

### Notificar um cliente (API)

```php
$this->notificationService->notifyCustomer(
    customer: $customer,
    type:     'transaction.created',
    title:    'Transacção efectuada',
    body:     "Recebeste €{$amount} na tua conta.",
    data:     ['transaction_id' => $transaction->id],
);
```

---

## Endpoints API (clientes)

| Método | URL | Descrição |
|---|---|---|
| `GET` | `/api/notifications` | Lista paginada (unread first) |
| `POST` | `/api/notifications/{id}/read` | Marca uma como lida |
| `POST` | `/api/notifications/read-all` | Marca todas como lidas |

Todos requerem `Authorization: Bearer {token}`.

---

## Endpoints Dashboard (JSON fetch)

| Método | URL | Descrição |
|---|---|---|
| `GET` | `/dashboard/notifications` | Lista + contagem não lidas |
| `POST` | `/dashboard/notifications/{id}/read` | Marca uma como lida |
| `POST` | `/dashboard/notifications/read-all` | Marca todas como lidas |

Requerem sessão web autenticada (cookie).

---

## Permissões → Tipos de Notificação

| `permission_required` | Tipo de evento sugerido |
|---|---|
| `transactions.view` | `transaction.created`, `transaction.failed` |
| `customers.view` | `customer.registered`, `customer.kyc_approved`, `customer.kyc_rejected` |
| `settings.edit` | `system.maintenance_toggled` |

Adiciona novos tipos livremente — é apenas uma string de identificação.

---

## Produção — Ubuntu VPS

### Requisitos
- PHP 8.2+, Composer, Node.js 18+, Supervisor, Nginx
- Queue worker já a correr (o broadcast usa a queue)

---

### 1. Variáveis de ambiente (`.env` de produção)

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=yoop-prod
REVERB_APP_KEY=chave-aleatoria-forte-32chars
REVERB_APP_SECRET=segredo-aleatorio-forte-32chars
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=wss.teudominio.com   # subdomínio que aponta para o VPS
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

> **Gera chaves seguras:**
> ```bash
> openssl rand -hex 16   # usa o output para APP_KEY, REVERB_APP_KEY, REVERB_APP_SECRET
> ```

---

### 2. systemd — manter o Reverb e o Horizon a correr

Cria `/etc/systemd/system/yoop-reverb.service`:

```ini
[Unit]
Description=Yoop Laravel Reverb
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/yoop
ExecStart=/usr/bin/php /var/www/yoop/artisan reverb:start --host=0.0.0.0 --port=8080
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

> A queue é gerida pelo **Horizon** — ver `docs/performance.md`. Não criar um serviço separado para `queue:work`.

```bash
sudo systemctl daemon-reload
sudo systemctl enable yoop-reverb
sudo systemctl start yoop-reverb
sudo systemctl status yoop-reverb
```

---

### 3. Nginx — proxy WebSocket

Adiciona ao bloco `server` do teu site (dentro do `server { ... }` do Nginx):

```nginx
# Proxy WebSocket para o Reverb
location /app/ {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header SERVER_PORT $server_port;
    proxy_set_header REMOTE_ADDR $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_pass http://0.0.0.0:8080;
}
```

Depois:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

> O frontend irá ligar via `wss://teudominio.com/app/` (porta 443, SSL) e o Nginx faz o proxy para o Reverb na porta 8080 internamente.

---

### 4. SSL para WebSockets

Se usas **Certbot/Let's Encrypt**, o SSL já está no Nginx — não precisas de nada extra. O Reverb corre em HTTP (8080) internamente e o Nginx termina o TLS.

No `.env`:
```dotenv
REVERB_SCHEME=https   # força wss:// no broadcasting
```

---

### 5. Deploy checklist

```bash
# 1. Pull do código
cd /var/www/yoop
git pull origin main

# 2. Dependências
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Migrations
php artisan migrate --force

# 4. Cache de produção
php artisan config:cache
php artisan route:cache
php artisan event:cache

# 5. Reiniciar serviços
sudo systemctl restart yoop-reverb
sudo systemctl restart yoop-horizon
```

---

### 6. Verificar que está a funcionar

```bash
# Estado do Reverb
sudo systemctl status yoop-reverb

# Logs em tempo real
sudo journalctl -u yoop-reverb -f

# Testar conexão WebSocket (instala wscat se necessário)
wscat -c ws://localhost:8080/app/yoop-key?protocol=7&client=js&version=8.0.0
```

---

### Troubleshooting

| Problema | Causa provável | Solução |
|---|---|---|
| Badge não actualiza em tempo real | Reverb não está a correr | `systemctl status yoop-reverb` |
| `Class Pusher not found` | Falta `pusher/pusher-php-server` | `composer require pusher/pusher-php-server` |
| WebSocket recusado em produção | Nginx sem bloco `/app/` | Adiciona o bloco de proxy acima |
| Notificações criadas mas não chegam | Queue não está a processar | `systemctl status yoop-horizon` |
| `forceTLS` error em localhost | Scheme errado | `REVERB_SCHEME=http` em desenvolvimento |
