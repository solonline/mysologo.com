# Performance e Escalabilidade — Yoop

## Visão Geral

| Camada | Mecanismo | Estado |
|---|---|---|
| **Webhooks Stripe** | Job assíncrono na fila `stripe-webhooks` | Implementado |
| **Cache de dados estáticos** | `Cache::remember` (5 min) em payment methods e fees | Implementado |
| **Rate Limiting** | Throttle named `stripe-intent` (10 req/min por utilizador) | Implementado |
| **Gestão de filas** | Laravel Horizon | **Pendente para produção** |
| **Throughput do servidor** | Laravel Octane (FrankenPHP) | **Pendente para produção** |

---

## Rate Limiting

### Como funciona

O Laravel usa um "balde" por chave (`user_id`). A cada request, incrementa o contador; quando atinge o limite, retorna `429 Too Many Requests` com o header `Retry-After` informando quantos segundos faltam para o balde resetar.

```
Utilizador A → 1ª request   [■□□□□□□□□□] 1/10
Utilizador A → 5ª request   [■■■■■□□□□□] 5/10
Utilizador A → 10ª request  [■■■■■■■■■■] 10/10
Utilizador A → 11ª request  → HTTP 429  Retry-After: 38s
```

A chave é **por `user_id`**, não por IP — evita bloquear múltiplos utilizadores na mesma rede (escritório, 4G partilhado).

### Onde está configurado

**`app/Providers/AppServiceProvider.php`** — define os limiters:

```php
// Limite geral da API: 60 req/min por utilizador+IP
RateLimiter::for('api', function (Request $request) {
    return $request->user()
        ? Limit::perMinute(60)->by($request->user()->id . '|' . $request->ip())
        : Limit::perMinute(60)->by($request->ip());
});

// Limite para criação de payment intents: 10 req/min por utilizador
RateLimiter::for('stripe-intent', function (Request $request) {
    return Limit::perMinute(10)
        ->by($request->user()?->id ?? $request->ip())
        ->response(fn () => response()->json([
            'message' => 'Muitas tentativas. Tente novamente em breve.',
        ], 429));
});
```

**`routes/api.php`** — aplica o middleware nas rotas:

```php
Route::post('/create-intent-payment', [...])
    ->middleware(['idempotency', 'throttle:stripe-intent']);
Route::post('/terminal/create-intent', [...])
    ->middleware(['idempotency', 'throttle:stripe-intent']);
Route::post('/terminal/{transaction}/capture', [...])
    ->middleware('throttle:stripe-intent');
Route::post('/create-funding', [...])
    ->middleware('throttle:stripe-intent');
```

### Ajustar limites

Para mudar o limite de 10 para outro valor, basta alterar `perMinute(10)` no `AppServiceProvider`. Para criar um limiter diferente (ex: para P2P), segue o mesmo padrão:

```php
RateLimiter::for('p2p', function (Request $request) {
    return Limit::perMinute(20)->by($request->user()?->id ?? $request->ip());
});
```

E aplica `->middleware('throttle:p2p')` na rota.

---

## Cache

### O que está em cache

| Chave | TTL | Dados |
|---|---|---|
| `payment_methods:{CURRENCY}` | 5 min | Lista de métodos de pagamento activos por moeda |
| `fee_estimates:{CURRENCY}` | 5 min | Estimativas de taxas activas por moeda |
| `platform_customer` | Forever | Customer do tipo `platform` (usado em transfers) |

### Invalidar o cache

Quando activares/desactivares um método de pagamento no backoffice, o cache fica desactualizado até o TTL expirar. Para forçar a actualização imediata, chama `Cache::forget` na operação de update:

```php
use Illuminate\Support\Facades\Cache;

// Após activar/desactivar um StripePaymentMethodFee
$fee->update(['is_active' => $value]);
Cache::forget("payment_methods:{$fee->currency}");
Cache::forget("fee_estimates:{$fee->currency}");
```

---

## Webhooks Stripe (fila assíncrona)

### Arquitectura

```
Stripe envia POST /api/webhooks/stripe
  └─ StripeWebhookController valida assinatura (STRIPE_WEBHOOK_SECRET)
  └─ Retorna 200 imediatamente ← Stripe precisa disto para não reenviar
  └─ ProcessStripeWebhook::dispatch($payload)->onQueue('stripe-webhooks')
        └─ Worker processa: handleWebhook() no StripeService
              ├─ payment_intent.succeeded → actualiza transacção
              ├─ payment_intent.payment_failed → reverte pending
              └─ charge.updated → calcula fees reais e incrementa saldo
```

### Variável de ambiente necessária

```dotenv
STRIPE_WEBHOOK_SECRET=whsec_...   # obtém no dashboard Stripe → Webhooks
```

---

## Laravel Horizon — Gestão de Filas

O Horizon substitui o `queue:listen` do dev e adiciona painel visual, métricas e alertas.

### Instalação

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

### Configurar filas no `config/horizon.php`

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue'      => ['stripe-webhooks', 'default'],
            'balance'    => 'auto',
            'processes'  => 5,
            'tries'      => 3,
            'timeout'    => 60,
        ],
    ],
    'local' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue'      => ['stripe-webhooks', 'default'],
            'balance'    => 'simple',
            'processes'  => 2,
            'tries'      => 1,
        ],
    ],
],
```

### Proteger o painel `/horizon`

Em `app/Providers/HorizonServiceProvider.php`, o método `gate()` controla o acesso.
Substitui o email hardcoded pelo teu:

```php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user) {
        return in_array($user->email, [
            'teu@email.com',
        ]);
    });
}
```

### systemd — produção (Ubuntu)

Cria o ficheiro `/etc/systemd/system/yoop-horizon.service`:

```ini
[Unit]
Description=Yoop Laravel Horizon
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/yoop
ExecStart=/usr/bin/php /var/www/yoop/artisan horizon
ExecStop=/usr/bin/php /var/www/yoop/artisan horizon:terminate
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable yoop-horizon
sudo systemctl start yoop-horizon
sudo systemctl status yoop-horizon
```

### Deploy com Horizon activo

Após `git pull` e `composer install`, reinicia o Horizon graciosamente (termina jobs em curso antes de parar):

```bash
php artisan horizon:terminate
sudo systemctl restart yoop-horizon
```

---

## Laravel Octane — Throughput do Servidor PHP

O Octane mantém a aplicação em memória entre requests — elimina o boot do Laravel a cada request (bootstrap, providers, bindings). Resultado: 10-100× mais requests/seg dependendo do servidor.

### Instalação

```bash
composer require laravel/octane
php artisan octane:install --server=frankenphp
```

### Iniciar em desenvolvimento

```bash
php artisan octane:start --watch   # --watch reinicia ao alterar ficheiros
```

### Atenção: estado entre requests

No Octane, a aplicação **não reinicia entre requests**. Singletons e variáveis estáticas persistem. Verificar se algum service guarda estado mutável:

```php
// PROBLEMA — $this->data persiste entre requests
class MyService {
    private array $data = [];
    public function add($item) { $this->data[] = $item; }
}

// CORRECTO — estado local ao método, não à instância
class MyService {
    public function process(array $items) { /* usa $items directamente */ }
}
```

Os bindings actuais do `AppServiceProvider` são todos `bind` (não `singleton`), por isso são seguros para Octane.

### systemd — produção

Cria o ficheiro `/etc/systemd/system/yoop-octane.service`:

```ini
[Unit]
Description=Yoop Laravel Octane
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/yoop
ExecStart=/usr/bin/php /var/www/yoop/artisan octane:frankenphp --port=8000
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable yoop-octane
sudo systemctl start yoop-octane
sudo systemctl status yoop-octane
```

### Nginx — proxy para o Octane

```nginx
location / {
    proxy_pass         http://127.0.0.1:8000;
    proxy_http_version 1.1;
    proxy_set_header   Host $http_host;
    proxy_set_header   X-Real-IP $remote_addr;
    proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header   X-Forwarded-Proto $scheme;
}
```

---

## Deploy Checklist — Produção

```bash
# 1. Pull e dependências
cd /var/www/yoop
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Migrations
php artisan migrate --force

# 3. Cache de produção
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache

# 4. Reiniciar serviços
php artisan horizon:terminate          # graceful — aguarda jobs em curso
sudo systemctl restart yoop-horizon
sudo systemctl restart yoop-octane     # se Octane estiver instalado
sudo systemctl restart yoop-reverb
```

---

## Troubleshooting

| Problema | Causa provável | Solução |
|---|---|---|
| Jobs da fila `stripe-webhooks` não processam | Horizon/queue worker não está a correr | `supervisorctl status yoop-horizon` |
| Stripe reenvia webhooks repetidamente | Controller demorou > 30s a responder | Confirma que o Job é despachado e retorna 200 imediato |
| Cache desactualizado após mudar fees | Cache não foi invalidado | Chama `Cache::forget("payment_methods:{$currency}")` |
| `429 Too Many Requests` inesperado | Limite `stripe-intent` atingido | Verifica o header `Retry-After`; ajusta `perMinute(N)` se necessário |
| Octane: dados de utilizador anteriores na request | Singleton com estado mutável | Muda para `bind` no `AppServiceProvider` ou usa `ScopedInstance` |
| `/horizon` retorna 403 | Email não está na gate | Adiciona o email em `HorizonServiceProvider::gate()` |
