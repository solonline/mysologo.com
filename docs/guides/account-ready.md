# Conta pronta a usar

## Objetivo

Saber quando um customer pode usar wallet, top-up e P2P.

## Checklist

| Requisito | Onde verificar |
|-----------|----------------|
| Lifecycle `ACTIVE` | `lifecycle.status` no login ou `GET /customer/me` → `status` |
| Email verificado | `verification.email` ou `email_verified_at` |
| Telefone verificado | `verification.phone` ou `phone_verified` |
| KYC aprovado | `verification.identity` / `kyc_status` |
| Funding partner | `integrations` — Ebury **ou** Wallester activo |
| Stripe | `integrations.stripe.status` + `integrations.stripe.iban` quando existir |
| Pode transaccionar | `capabilities.can_transact` |

## Regra de negócio

```
ACTIVE = (Ebury aprovado OU Wallester aprovado) E Stripe OK
```

Não é necessário ter Ebury **e** Wallester ao mesmo tempo.

## Middlewares da API

Endpoints de wallet e stripe exigem **todos**:

- `customer.active`
- `customer.email.verified`
- `customer.document.approved`

Se falhar → **403** com mensagem no JSON.

## Endpoints úteis para validar

| Endpoint | O que confirma |
|----------|----------------|
| `GET /api/customer/me` | Perfil + flags de verificação |
| `GET /api/wallet/dashboard` | Só funciona se elegível |
| `GET /api/wallet/account` | Dados Stripe/Wallester (legado; preferir `integrations.*.iban` no login) |

📖 **Campos do login:** [AuthSession](/docs/guides/auth-session)

## Ainda não está pronto?

| Sintoma | Provável causa |
|---------|----------------|
| 403 em wallet | Lifecycle não `ACTIVE` ou KYC pendente |
| `PROVISIONING` longo | Aguardar webhooks dos parceiros |
| `EBURY_REJECTED` mas conta activa | Wallester aprovado — fluxo normal |
| `BLOCKED` | Ambos funding partners recusados |

📖 **Seguinte:** [Top-up com cartão](/docs/guides/topup-card) · [Ciclo de vida](/docs/guides/onboarding-lifecycle)
