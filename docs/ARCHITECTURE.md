# Yoop — Arquitectura do Sistema

> Documento gerado em 2026-06-01. Reflecte o estado actual da plataforma.

---

## 1. Visão Geral

```
┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENTES EXTERNOS                          │
│          App Mobile / Web          Merchant (API Key)               │
└──────────────────┬──────────────────────────┬───────────────────────┘
                   │ HTTPS                    │ HTTPS
                   ▼                          ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        YOOP API  (Laravel)                          │
│                                                                     │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────────────────┐   │
│  │  Auth API   │  │ Customer API │  │       Stripe API          │   │
│  │ /login      │  │ /me          │  │ /create-intent-payment    │   │
│  │ /register   │  │ /update      │  │ /create-funding           │   │
│  │ /login-pin  │  │ /find/:user  │  │ /terminal/create-intent   │   │
│  │ /login-tpa  │  │ /phone/*     │  │ /fee-estimates            │   │
│  └─────────────┘  └──────────────┘  └───────────────────────────┘   │
│                                                                     │
│  ┌──────────────┐  ┌─────────────┐  ┌───────────────────────────┐   │
│  │ Account API  │  │Transaction  │  │     Merchant API          │   │
│  │ /balance     │  │ /list       │  │ /payment-requests         │   │
│  │ /details     │  │ /show       │  │                           │   │
│  └──────────────┘  │ /p2p        │  └───────────────────────────┘   │
│                    └─────────────┘                                  │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                     WEBHOOKS (incoming)                      │   │
│  │      /webhooks/stripe          /webhooks/didit               │   │
│  │      /webhooks/ebury           /webhooks/wallester           │   │
│  └──────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │
                   ┌──────────────┼──────────────┐
                   ▼              ▼              ▼
            ┌────────────┐ ┌──────────┐ ┌──────────────┐
            │ PostgreSQL │ │  Redis   │ │    Storage   │
            │  (dados)   │ │ (cache/  │ │  (avatares,  │
            │            │ │  queue)  │ │   docs)      │
            └────────────┘ └──────────┘ └──────────────┘
                   │
      ┌────────────┼────────────────────┐
      ▼            ▼                    ▼
┌──────────┐ ┌──────────┐       ┌─────────────┐
│  Stripe  │ │  Didit   │       │  Wallester  │
│  (pagam.)│ │  (KYC)   │       │  (cartões/  │
│          │ │          │       │   IBANs)    │
└──────────┘ └──────────┘       └─────────────┘
```

---

## 2. Tipos de Customer

```
customers.type
├── retail       → pessoa singular (B2C)
├── corporate    → empresa (B2B)
│   └── responsible → representante legal da corporate
│                    (corporate_id → corporate.id)
└── platform     → conta interna da plataforma
                   (recebe fees e transfers agregados)
```

**Ciclo de vida de um customer:**

```
  Registo
     │
     ▼
 [PENDING] ──── submete documentos ────▶ [ANALYSING]
                                              │
                       ┌──────────────────────┤
                       ▼                      ▼
                   [ACTIVE]              [DECLINED]
                       │
              ┌────────┴────────┐
              ▼                 ▼
          [BLOCKED]         [INACTIVE]
```

---

## 3. Fluxo de Autenticação

```
┌──────────┐         ┌──────────────┐         ┌──────────┐
│  Cliente │         │   Yoop API   │         │  Didit   │
└────┬─────┘         └──────┬───────┘         └────┬─────┘
     │                      │                      │
     │  POST /register      │                      │
     │────────────────────▶│                      │
     │                      │  createDiditSession() │
     │                      │─────────────────────▶│
     │                      │◀─────────────────────│
     │◀──────────────────────│  session_url          │
     │                      │                      │
     │  [utilizador faz KYC no Didit]               │
     │                      │                      │
     │                      │◀── webhook /didit ───│
     │                      │  (doc aprovado/recusado)
     │                      │                      │
     │  POST /login          │                      │
     │──────────────────────▶│                      │
     │◀──────────────────────│  { token: ... }      │
     │                      │                      │
     │  POST /login-pin      │  (autenticação por PIN + device token)
     │──────────────────────▶│                      │
     │◀──────────────────────│  { token: ... }      │
     │                      │                      │
     │  POST /login-tpa      │  (Third Party Access)
     │──────────────────────▶│                      │
     │◀──────────────────────│  { token: ... }      │
```

---

## 4. Fluxo de Pagamento Stripe

```
┌──────────┐    ┌──────────────┐    ┌──────────┐
│  Cliente │    │   Yoop API   │    │  Stripe  │
└────┬─────┘    └──────┬───────┘    └────┬─────┘
     │                 │                 │
     │ POST /stripe/   │                 │
     │ create-intent   │                 │
     │────────────────▶│                 │
     │                 │ createPaymentIntent()
     │                 │────────────────▶│
     │                 │◀────────────────│
     │◀────────────────│ { client_secret }│
     │                 │                 │
     │  [cliente confirma pagamento na app]
     │                 │                 │
     │                 │◀── webhook ─────│
     │                 │  payment_intent │
     │                 │  .succeeded     │
     │                 │                 │
     │           ┌─────▼────────────────────────────────────┐
     │           │  StripeService::handleWebhook()           │
     │           │                                           │
     │           │  1. Cria stripe_transactions (direction=credit)
     │           │     status=pending, available_on=D+2      │
     │           │                                           │
     │           │  2. Incrementa customer_balances.pending  │
     │           │                                           │
     │           │  3. Cria plataform_transfer transaction   │
     │           │     (platform customer recebe fee)        │
     │           └───────────────────────────────────────────┘
     │
     │   [D+2 — cron 01:00]
     │           ┌─────────────────────────────────────────────┐
     │           │  stripe:settle-balances (SettlementService) │
     │           │  (ver Secção 6 — Pipeline de Settlement)     │
     │           └─────────────────────────────────────────────┘
```

### Tipos de pagamento suportados

| Categoria | Métodos |
|---|---|
| Cartão | card, credit_card, debit_card, card_present |
| Transferência | bank_transfer, sepa_debit, us_bank_account |
| Europa local | multibanco, mb_way, ideal, bancontact, eps, p24 |
| BNPL | klarna, afterpay_clearpay |
| Wallets digitais | paypal, link, revolut_pay, amazon_pay |
| Regional | boleto, oxxo, alipay, wechat_pay |
| Interno | p2p, customer_balance |

---

## 5. Fluxo P2P

```
┌──────────┐         ┌──────────────┐         ┌───────────┐
│  Sender  │         │   Yoop API   │         │ Recipient │
└────┬─────┘         └──────┬───────┘         └─────┬─────┘
     │                      │                       │
     │  POST /transactions/p2p                       │
     │  { to: username, amount, pin, device_token }  │
     │─────────────────────▶│                       │
     │                      │                       │
     │               ┌──────▼──────────────────────────────────┐
     │               │  DeviceAuthService::verifyPin()          │
     │               │  verifica PIN + device token             │
     │               └──────┬──────────────────────────────────┘
     │                      │
     │               ┌──────▼──────────────────────────────────┐
     │               │  DB::transaction()                       │
     │               │                                          │
     │               │  1. stripe_transactions (debit, sender)  │
     │               │  2. stripe_transactions (credit, recip.) │
     │               │  3. LedgerService::record(P2P_SEND)      │
     │               │     → customer_balances.wallester_allocated -=     │
     │               │     → ledger_entries (debit)             │
     │               │  4. LedgerService::record(P2P_RECEIVE)   │
     │               │     → customer_balances.wallester_allocated +=     │
     │               │     → ledger_entries (credit)            │
     │               └──────┬──────────────────────────────────┘
     │                      │
     │◀─────────────────────│  { debit, credit }
     │                                              │
     │                      │─────────────────────▶│
     │                      │  (notificação push)   │
```

---

## 6. Pipeline de Settlement (Stripe → IBAN)

```
  CRON diário 01:00
  stripe:settle-balances
         │
         ▼
  ┌──────────────────────────────────────────────────────────────┐
  │  SettlementService::runDailySettlement(date)                  │
  │                                                              │
  │  Busca stripe_transactions WHERE:                            │
  │    status = 'succeeded'                                       │
  │    direction = 'credit'                                       │
  │    available_on <= hoje                                       │
  │    balance_settled_at IS NULL                                 │
  │    type != 'p2p'                                              │
  └────────────────────────┬─────────────────────────────────────┘
                           │
                 Agrupa por currency
                           │
              ┌────────────▼────────────┐
              │  settlement_batches     │
              │  ┌──────────────────┐   │
              │  │ id               │   │
              │  │ currency         │   │
              │  │ available_date   │   │
              │  │ total_amount     │   │
              │  │ status: pending  │   │
              │  └──────────────────┘   │
              └────────────┬────────────┘
                           │
         ┌─────────────────▼──────────────────┐
         │  ETAPA 1 — Settle transações        │
         │                                    │
         │  Para cada stripe_transaction:      │
         │  ├─ LedgerService::record(          │
         │  │    PAYMENT_RECEIVED)             │
         │  │    → available += net_amount     │
         │  │    → ledger_entries (credit)     │
         │  └─ balance_settled_at = now()      │
         └─────────────────┬──────────────────┘
                           │
                  status = 'transferred'
                           │
         ┌─────────────────▼──────────────────┐
         │  ETAPA 2 — Transferência master     │
         │                                    │
         │  total_amount ──▶ Conta Master IBAN │
         │  (API terceiro — TODO integração)   │
         │                                    │
         │  settlement_batches:                │
         │  ├─ master_transfer_id = ext_id     │
         │  └─ master_transferred_at = now()   │
         └─────────────────┬──────────────────┘
                           │
                 status = 'distributing'
                           │
         ┌─────────────────▼──────────────────┐
         │  ETAPA 3 — Distribuição individual  │
         │                                    │
         │  Agrupa net_amount por customer_id  │
         │                                    │
         │  Para cada customer:                │
         │  ├─ Busca IBAN em                   │
         │  │  customer_wallester_accounts     │
         │  │                                  │
         │  │  [SEM IBAN]                      │
         │  │  customer_payouts.status=SKIPPED  │
         │  │  (saldo mantido na plataforma)   │
         │  │                                  │
         │  │  [COM IBAN]                      │
         │  │  customer_payouts.status=PROCESSING
         │  │  → API terceiro: envia para IBAN │
         │  │  → LedgerService::record(        │
         │  │      SETTLEMENT)                 │
         │  │    → available -= amount         │
         │  │    → ledger_entries (debit)      │
         │  └─ customer_payouts.status=COMPLETED
         └─────────────────┬──────────────────┘
                           │
                  status = 'completed'
```

---

## 7. Sistema de Ledger (Fonte da Verdade Financeira)

```
  Princípios:
  ┌──────────────────────────────────────────────────────┐
  │  • Append-only (sem UPDATE, sem DELETE, sem softDelete)│
  │  • Double-entry (cada movimentação é credit ou debit) │
  │  • balance_after = snapshot imutável no momento       │
  │  • customer_balances = cache derivado do ledger       │
  └──────────────────────────────────────────────────────┘

  Tipos de entrada (LedgerEntryType):

  CRÉDITOS (aumentam saldo)          DÉBITOS (diminuem saldo)
  ─────────────────────────          ────────────────────────
  PAYMENT_RECEIVED                   PAYMENT_FEE
  PAYMENT_REFUND                     PLATFORM_FEE
  P2P_RECEIVE                        P2P_SEND
  CARD_UNLOAD                        CARD_LOAD
  MANUAL_CREDIT                      CARD_PURCHASE
                                     SETTLEMENT
                                     MANUAL_DEBIT

  Relação com customer_balances:

  ledger_entries
  ┌────────────────────────────────────────┐
  │ id  │ direction │ amount │ balance_after│
  │  1  │  credit   │  1000  │    1000      │  ← PAYMENT_RECEIVED
  │  2  │  credit   │   500  │    1500      │  ← P2P_RECEIVE
  │  3  │  debit    │   200  │    1300      │  ← P2P_SEND
  │  4  │  debit    │  1300  │       0      │  ← SETTLEMENT
  └────────────────────────────────────────┘
                │
                ▼
  customer_balances.wallester_allocated = 0  ✓ (consistente)

  Reconciliação:
  LedgerService::recalculateBalance()  → SUM(credits) - SUM(debits)
  LedgerService::isBalanceConsistent() → compara com customer_balances
```

---

## 8. Modelo de Dados (Tabelas Principais)

```
customers
├── id, name, username, email, phone
├── type: retail | corporate | responsible | platform
├── status: pending | analysing | active | inactive | blocked
├── doc_status: pending | analysing | approved | declined
└── corporate_id → customers.id (para responsibles)
     │
     ├──▶ customer_stripe_accounts    (conta Stripe por customer)
     │      └── stripe_customer_id, viban, funding_iban/bic
     │
     ├──▶ customer_wallester_accounts (conta cartão/IBAN Wallester)
     │      └── iban, swift_code, receiver_name, viban
     │      └──▶ customer_wallester_cards
     │
     ├──▶ customer_balances           (saldo actual — cache)
     │      └── currency, available, pending
     │
     ├──▶ customer_fee_configs        (taxas personalizadas)
     │
     ├──▶ customer_retail_documents   (docs KYC retail)
     ├──▶ customer_corporate_documents(docs KYC corporate)
     ├──▶ customer_didit_sessions     (sessões KYC Didit)
     ├──▶ customer_devices            (dispositivos autorizados)
     ├──▶ customer_notifications      (notificações push)
     ├──▶ rewards                     (ganho do customer; program_id → reward_programs)
     │      └── referred_id nullable (único; só programa referral)
     ├──▶ reward_programs             (catálogo: slug, description, valid_from/until)
     └──▶ tpas                        (third party access tokens)
           └── customer_fee_configs (taxas do TPA)

stripe_transactions                   (todas as ops financeiras Stripe)
├── customer_id → customers
├── type: card | bank_transfer | p2p | ...
├── direction: credit | debit
├── amount, stripe_fee, platform_fee, net_amount
├── status: pending | succeeded | failed | refunded
├── available_on              (data de disponibilização)
├── balance_settled_at        (quando o settlement foi executado)
└── related_transaction_id → stripe_transactions (para p2p pairs)

ledger_entries                        (fonte da verdade — append-only)
├── customer_id → customers
├── currency, direction, amount, balance_after
├── entry_type: payment_received | p2p_send | card_purchase | settlement | ...
└── reference_type / reference_id    (morph → stripe_transactions, funding_partner_movements, …)

funding_partner_movements             (entradas/saídas Wallester — cartão, P2P, top-up)
├── provider, funding_account_type/id (morph → customer_wallester_accounts)
├── direction: in | out
├── type: card_purchase | card_refund | account_transfer | fee | …
├── amount, currency                  (total na moeda da conta, cêntimos)
├── original_amount, original_currency (compra no comerciante, se FX)
├── fees_amount                       (valor do câmbio na moeda da conta)
├── partner_external_id, idempotency_key
└── metadata                          (payload Wallester normalizado)

customer_balances                     (cache derivado do ledger)
└── customer_id + currency → available, pending

settlement_batches                    (uma por moeda por dia)
├── currency, available_date
├── total_amount, transaction_count
├── status: pending → transferred → distributing → completed
├── master_transfer_id                (ref. no sistema do terceiro)
└──▶ customer_payouts                 (uma por customer por batch)
       ├── amount, currency
       ├── iban, bic, account_holder_name (snapshot)
       ├── external_transfer_id      (ref. no sistema do terceiro)
       └── status: pending | processing | completed | failed | skipped

merchants                             (parceiros que geram payment requests)
└──▶ merchant_payment_requests
       ├── payer_id → payers
       ├── transaction_id → stripe_transactions
       └── status: pending | processing | completed | failed | expired

account_payables                      (contas a pagar internas)
└──▶ account_payable_attachments

settings                              (config dinâmica da plataforma)
└── key / value  (ex: stripe.eur.secret_key, system.maintenance_mode)

idempotency_keys                      (previne duplicação de requests)
```

---

## 9. Serviços e Responsabilidades

| Serviço | Responsabilidade |
|---|---|
| `AuthService` | Registo, login, PIN, TPA |
| `CustomerService` | CRUD customer, KYC, avatar, documentos |
| `StripeService` | PaymentIntents, webhooks Stripe, settle transactions |
| `SettlementService` | Pipeline diário: settle → master → IBANs |
| `LedgerService` | Registo imutável + actualização de saldo |
| `TransactionService` | P2P transfers, listagem |
| `WallesterService` | Criação de contas e cartões Wallester |
| `WallesterCardMovementIngestService` | Webhook/sync de gastos de cartão → `funding_partner_movements` + ledger |
| `NormalizeSwallowedWallesterFxPurchasesService` | Unifica linhas antigas que gravaram só a taxa FX |
| `KycService` | Orquestração do fluxo KYC (Didit + docs) |
| `DiditService` | Integração com Didit para verificação de identidade |
| `DeviceAuthService` | Verificação de PIN por dispositivo |
| `MerchantService` | Payment requests de merchants |
| `NotificationService` | Push notifications para customers |
| `ReferralRewardService` | Grava reward de indicação no `CustomerApproved` e lista os ganhos do customer |
| `SettingsService` | Leitura/escrita de configurações da plataforma |

---

## 10. Jobs e Crons

| Comando | Schedule | O que faz |
|---|---|---|
| `stripe:settle-balances` | Diariamente às 01:00 | Executa o pipeline completo de settlement |
| `wallester:sync-card-movements` | 00:00 e 12:00 | Importa transacções de cartão Wallester em falta |
| `wallester:normalize-fx-fee-purchases` | manual | Converte movimentos só-taxa FX no total unificado |

---

## 11. Middlewares

| Alias | Classe | Aplicado em |
|---|---|---|
| `auth:sanctum` | Laravel Sanctum | Todas as rotas autenticadas |
| `auth.merchant` | `AuthMerchant` | Rotas de merchant (API Key) |
| `idempotency` | `IdempotencyRequiredMiddleware` | Create payment intent |
| `customer.active` | `EnsureCustomerIsActiveMiddleware` | Rotas de customer activo |
| `customer.email.verified` | `EnsureCustomerEmailVerifiedMiddleware` | Rotas pós-verificação |
| `customer.document.approved` | `EnsureCustomerDocumentApprovedMiddleware` | Rotas pós-KYC |
| `maintenance` | `MaintenanceModeMiddleware` | Todas as rotas API (global) |
| `throttle:api` | Laravel RateLimit | 60 req/min por utilizador |
| `throttle:stripe-intent` | Laravel RateLimit | 10 req/min — top-up da app (por utilizador) |
| `throttle:merchant-payment-requests` | Laravel RateLimit | 120 req/min — criar payment request (por merchant) |
| `throttle:merchant-terminal-token` | Laravel RateLimit | 60 req/min — connection token POS (por merchant) |
| `throttle:merchant-terminal-intent` | Laravel RateLimit | 60 req/min — create-intent POS (por merchant) |
