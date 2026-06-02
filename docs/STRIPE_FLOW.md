# Como funciona o dinheiro na Yoop
### Guia para quem está a começar

> Este documento explica passo a passo o que acontece desde que um cliente paga
> até o dinheiro chegar na conta dele. Sem jargão desnecessário.

---

## Antes de começar — analogia do supermercado

Imagina que a Yoop é um supermercado que guarda dinheiro para as pessoas.

- O cliente deposita dinheiro no supermercado
- O supermercado guarda numa **prateleira "pendente"** (ainda a verificar)
- Depois de uns dias, move para a **prateleira "disponível"** (já podes usar)
- Se quiseres sacar, o supermercado faz uma **transferência para o teu banco**

É exactamente isso que o sistema faz, só que de forma automática e com código.

---

## As 3 grandes fases

```
FASE 1              FASE 2                    FASE 3
──────              ──────                    ──────
O cliente           Stripe confirma           O dinheiro vai
paga                o pagamento               para o IBAN
   │                     │                        │
   ▼                     ▼                        ▼
[Criamos um      [Gravamos na BD          [Settlement
 PaymentIntent]   + saldo pendente]        Pipeline]

   ~segundos          ~minutos              ~D+2 dias
```

---

## FASE 1 — O cliente quer pagar

O cliente abre a app e escolhe pagar, por exemplo, 100€ com cartão.

```
App do cliente                Yoop API                   Stripe
──────────────                ────────                   ──────

  "Quero pagar                POST /stripe/
   100€"          ──────────▶ create-intent-payment
                                    │
                                    │  Cria um PaymentIntent
                                    │  na Stripe
                                    │ ──────────────────▶
                                    │ ◀──────────────────
                                    │  { client_secret }
                              ◀─────┘
  Recebe o
  client_secret
  e confirma
  o pagamento
  na Stripe       ─────────────────────────────────────▶
                                                    Stripe processa
                                                    o cartão
```

**O que é um `PaymentIntent`?**
É como um "pedido de pagamento" que criamos na Stripe. Ele tem um ID único
(ex: `pi_3ABC...`) e fica em estado `pending` até o cliente confirmar.

Neste momento ainda não gravamos nada de financeiro. Só criamos o registo
na Stripe e devolvemos o `client_secret` para o cliente completar o pagamento.

---

## FASE 2 — A Stripe confirma e nós gravamos

Quando o cliente confirma o pagamento, a Stripe envia-nos uma notificação
automática (chamada **webhook**). É ela que diz "o pagamento foi aprovado".

```
Stripe                         Yoop API
──────                         ────────

  "payment_intent.succeeded"
  pi_3ABC... = 100€
  ──────────────────────────▶  POST /webhooks/stripe
                                        │
                                        │  1. Encontra a transação
                                        │     pelo stripe_payment_intent_id
                                        │
                                        │  2. Actualiza status → succeeded
                                        │
                                        │  3. Calcula taxas:
                                        │     100€ total
                                        │     - 1.5€ taxa Stripe
                                        │     - 0.5€ taxa plataforma
                                        │     = 98€ net_amount
                                        │
                                        │  4. Grava em stripe_transactions
                                        │
                                        │  5. Adiciona 98€ ao saldo PENDENTE
                                        │     (ainda não disponível)
```

### O que fica gravado em `stripe_transactions`

Esta tabela é o registo de **todas as operações financeiras** do sistema.
Cada linha representa uma movimentação de dinheiro.

```
stripe_transactions — exemplo de uma linha após o pagamento:

 campo                        │ valor            │ o que significa
──────────────────────────────┼──────────────────┼────────────────────────────
 id                           │ 42               │ ID interno nosso
 customer_id                  │ 7                │ qual cliente pagou
 type                         │ card             │ método de pagamento
 direction                    │ credit           │ dinheiro ENTROU na conta
 status                       │ succeeded        │ pagamento confirmado
 amount                       │ 10000            │ 100,00€ (em cêntimos!)
 stripe_fee                   │ 150              │ 1,50€ taxa da Stripe
 platform_fee                 │ 50               │ 0,50€ taxa da Yoop
 net_amount                   │ 9800             │ 98,00€ que o cliente recebe
 currency                     │ EUR              │ moeda
 available_on                 │ 2026-06-03       │ quando o dinheiro fica disponível
 balance_settled_at           │ NULL             │ ainda não foi liquidado
 stripe_payment_intent_id     │ pi_3ABC...       │ referência na Stripe
```

> ⚠️ **Importante:** os valores são sempre em **cêntimos** (inteiros).
> 100€ = 10000. Isto evita problemas com decimais em cálculos financeiros.

### O que acontece ao saldo do cliente

```
customer_balances (antes do pagamento):
  available = 0€
  pending   = 0€

customer_balances (depois do webhook):
  available = 0€      ← ainda não pode usar
  pending   = 98€     ← dinheiro existe mas está "retido"
```

O saldo fica **pendente** porque a Stripe demora D+2 dias úteis a confirmar
que o dinheiro foi efectivamente transferido para a nossa conta bancária.
Só depois é que movemos para "disponível".

---

## Por que existe saldo pendente?

Pensa assim: quando pagas com cartão num supermercado, o dinheiro não sai
da tua conta imediatamente. O banco do supermercado precisa de "liquidar"
a transacção. O mesmo acontece aqui.

```
Dia 0 (hoje):     Cliente paga 100€
                  → pending = 98€, available = 0€

Dia 2 (D+2):      Stripe confirma que o dinheiro chegou
                  → pending = 0€, available = 98€
                  → stripe_transactions.balance_settled_at = hoje
```

---

## FASE 3 — O Settlement Pipeline (o mais importante)

No Dia+2, um **job automático** corre todos os dias às 01:00 da manhã:

```bash
php artisan stripe:settle-balances
```

Este job executa o **pipeline de settlement** em 3 etapas:

---

### Etapa 1 de 3 — Identificar e liquidar transações

O job procura todas as transações que:
- Foram bem-sucedidas (`status = succeeded`)
- Dinheiro entrou (`direction = credit`)
- Têm data de disponibilidade no passado (`available_on <= hoje`)
- Ainda não foram liquidadas (`balance_settled_at IS NULL`)
- Não são P2P (transferências entre clientes — essas são imediatas)

```
stripe_transactions encontradas:

 id │ customer │ net_amount │ available_on │ balance_settled_at
────┼──────────┼────────────┼──────────────┼───────────────────
 42 │  Ana     │  98€       │ 2026-06-01   │ NULL  ← vai ser processada
 43 │  João    │ 150€       │ 2026-06-01   │ NULL  ← vai ser processada
 44 │  Ana     │  50€       │ 2026-06-02   │ NULL  ← ainda não (amanhã)
```

Para cada transação encontrada, o job:
1. Regista no **Ledger** (ver secção abaixo) — prova imutável que o dinheiro entrou
2. Move o saldo: `pending -= 98€`, `available += 98€`
3. Marca como liquidada: `balance_settled_at = agora()`

---

### Etapa 2 de 3 — Agrupar em batches e enviar para conta master

As transações do mesmo dia e moeda são agrupadas num **settlement batch**.

**Porquê agrupar?** Em vez de fazer 100 transferências bancárias individuais
(uma por cliente), fazemos **uma transferência agregada** para a conta master
da Yoop. Depois distribuímos internamente. É mais barato e mais simples.

```
settlement_batches — exemplo:

 campo               │ valor            │ o que significa
─────────────────────┼──────────────────┼──────────────────────────────
 id                  │ 1                │ ID do batch
 currency            │ EUR              │ moeda
 available_date      │ 2026-06-01       │ data das transações liquidadas
 total_amount        │ 24800            │ 248€ total (Ana 98€ + João 150€)
 transaction_count   │ 2                │ 2 transações neste batch
 status              │ transferred      │ já enviado para conta master
 master_transfer_id  │ TRF_XYZ...       │ ID da transferência no terceiro
 master_transferred_at│ 2026-06-03 01:02│ quando foi enviado
```

```
      Stripe (plataforma)
           │
           │  248€ (soma de Ana + João)
           ▼
    Conta Master IBAN
    (conta da Yoop no terceiro)
           │
           │  vai distribuir para cada cliente
           ▼
    (Etapa 3)
```

---

### Etapa 3 de 3 — Distribuir para cada cliente

Depois de o dinheiro chegar à conta master, criamos um **customer_payout**
por cada cliente e enviamos para o IBAN individual dele (conta Wallester).

```
customer_payouts — exemplo:

 campo                │ Ana              │ João
──────────────────────┼──────────────────┼──────────────────
 settlement_batch_id  │ 1                │ 1
 customer_id          │ 7 (Ana)          │ 8 (João)
 amount               │ 9800 (98€)       │ 15000 (150€)
 iban                 │ PT50...          │ PT50...
 external_transfer_id │ EXT_001          │ EXT_002
 status               │ completed        │ completed
 processed_at         │ 2026-06-03 01:05 │ 2026-06-03 01:06
```

**E se o cliente não tiver IBAN configurado?**
O payout fica com `status = skipped`. O dinheiro permanece na plataforma
no saldo disponível do cliente. Quando ele configurar o IBAN, pode sacar.

```
 status       │ o que significa
──────────────┼────────────────────────────────────────────────
 pending      │ aguarda distribuição
 processing   │ a chamar a API do terceiro
 completed    │ IBAN creditado ✓
 failed       │ falhou — será retentado
 skipped      │ sem IBAN — saldo mantido na plataforma
```

---

## O Ledger — o caderno de contas imutável

O ledger é um **registo de todas as movimentações financeiras**, por ordem.
É como um caderno contabilístico: só se escreve, nunca se apaga.

**Porquê existe se já temos `stripe_transactions`?**
- `stripe_transactions` é específico da Stripe. E as outras operações (P2P, settlement, carregamento de cartão)?
- O ledger é **universal** — regista tudo, independentemente da origem
- Serve para **auditoria** e **reconciliação**: se o saldo do cliente parecer errado, recalculamos do zero somando o ledger

```
ledger_entries — o registo de Ana ao longo do tempo:

 id │ direcção │ montante │ saldo_após │ tipo
────┼──────────┼──────────┼────────────┼─────────────────
 1  │ credit   │ 98€      │ 98€        │ payment_received  ← pagamento liquidado
 2  │ credit   │ 50€      │ 148€       │ p2p_receive       ← João enviou 50€
 3  │ debit    │ 20€      │ 128€       │ p2p_send          ← Ana enviou 20€
 4  │ debit    │ 128€     │ 0€         │ settlement        ← sacou tudo para o IBAN
```

**Regra de ouro do ledger:**
- `credit` = dinheiro **entrou** (saldo sobe)
- `debit` = dinheiro **saiu** (saldo desce)
- `balance_after` = foto do saldo no momento exacto da entrada

---

## Resumo visual completo

```
CLIENTE PAGA
     │
     ▼
PaymentIntent criado na Stripe
     │
     ▼
Stripe confirma → webhook chega à Yoop
     │
     ├── stripe_transactions criada (status=succeeded)
     └── customer_balances.pending += net_amount
                                          │
                                          │  espera D+2 dias
                                          ▼
                              [CRON 01:00 — stripe:settle-balances]
                                          │
                          ┌───────────────┼───────────────┐
                          ▼               ▼               ▼
                     ETAPA 1         ETAPA 2         ETAPA 3
                   Settle txns    Batch → Master   Distribuição
                          │               │               │
                   ledger_entries  settlement_batches  customer_payouts
                   balance:              │               │
                   pending→available     │               │
                                         ▼               ▼
                                   Conta Master      IBAN do cliente
                                   (terceiro)        (Wallester)
```

---

## Perguntas frequentes

**P: O que acontece se o webhook da Stripe chegar duas vezes?**
R: O sistema verifica se a `stripe_cash_balance_transaction_id` já existe.
Se sim, ignora o segundo webhook. Isto chama-se **idempotência**.

**P: O que acontece se o settlement falhar a meio?**
R: O `settlement_batch` fica com `status = failed` e `failure_reason` com
a mensagem de erro. O job pode ser re-executado com `--date=` para reprocessar
uma data específica sem criar duplicados.

**P: Como sei se o saldo do cliente está correcto?**
R: Chamamos `LedgerService::isBalanceConsistent()`. Ele soma todos os créditos
e débitos do ledger e compara com o valor em `customer_balances`. Se bater, está
tudo certo.

**P: O que é a conta "platform"?**
R: É uma conta interna da Yoop. Quando a Stripe liquida um pagamento, uma parte
(as taxas de plataforma) é registada como crédito para esta conta. É assim que
a Yoop ganha dinheiro.

---

## Ficheiros importantes no código

| O que queres ver | Ficheiro |
|---|---|
| Como um pagamento é criado | `app/Services/StripeService.php` → `createPaymentIntent()` |
| Como o webhook é processado | `app/Services/StripeService.php` → `handleWebhook()` |
| Como o settlement corre | `app/Services/SettlementService.php` → `runDailySettlement()` |
| Como o ledger é escrito | `app/Services/LedgerService.php` → `record()` |
| O job diário | `app/Console/Commands/SettleStripeBalances.php` |
| Tabela de transações | `database/migrations/..._create_stripe_transactions_table.php` |
| Tabela de batches | `database/migrations/..._create_settlement_batches_table.php` |
| Tabela de payouts | `database/migrations/..._create_customer_payouts_table.php` |
| Tabela do ledger | `database/migrations/..._create_ledger_entries_table.php` |
