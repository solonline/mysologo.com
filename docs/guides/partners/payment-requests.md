# Payment requests

## Objetivo

Criar um link de pagamento para um cliente final pagar na página Yoop (`/pay/{uuid}`).

## Autenticação

```
X-Merchant-Key: {api_key}
```

Sem Bearer token neste fluxo.

## Fluxo

```mermaid
sequenceDiagram
    participant Shop as E-commerce / POS
    participant API as Yoop API
    participant User as Pagador

    Shop->>API: POST /merchant/payment-requests
    API-->>Shop: uuid + url
    Shop->>User: Redirecciona para url
    User->>User: Paga em /pay/{uuid}
```

## Criar pedido

```
POST /api/merchant/payment-requests
```

```json
{
  "amount": 5000,
  "description": "Order #123",
  "external_reference": "ORD-123",
  "callback_url": "https://loja.com/webhook/yoop",
  "expires_in": 60
}
```

| Campo | Notas |
|-------|--------|
| `amount` | Cêntimos, mínimo 1 |
| `expires_in` | Minutos (5–1440), opcional |
| `callback_url` | URL para notificação futura, opcional |

Resposta `201`:

```json
{
  "uuid": "...",
  "url": "https://yoop.test/pay/...",
  "status": "pending",
  "amount": 5000,
  "currency": "EUR"
}
```

## Rate limit

10 pedidos/minuto (`stripe-intent`).

## Erros

| HTTP | Causa |
|------|--------|
| 401 | API key inválida |
| 422 | Validação |
| 429 | Rate limit |

📖 **API:** [referência parceiros](/docs/partners)
