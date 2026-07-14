#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if ! gh auth status >/dev/null 2>&1; then
  echo "Autentica primeiro: gh auth login --hostname github.com --git-protocol ssh --web"
  exit 1
fi

gh pr create --base main --head wallester \
  --title "feat: integração Wallester, wallet ledger e top-up Stripe imediato" \
  --body "$(cat <<'EOF'
## Summary

- Integra Wallester como parceiro de funding: contas main, subcontas, cartões, 3DS, movimentos e transferências Main → cliente.
- Implementa fluxo de top-up Stripe com funding imediato na Wallester (≤ €100 bruto/dia por cliente), ledger `pending` → `partner_funding_released`, retry e auditoria (`wallet:audit`).
- Adiciona backoffice operacional: dashboard de transações, timeline de atividade do cliente, retry de top-ups bloqueados, sync de saldo Main e notificações de top-up por transferência bancária.
- Refatora `Transaction` → `StripeTransaction` e introduz `funding_partner_movements` como registo de movimentos no parceiro.

## Test plan

- [ ] Top-up Stripe < €100: `charge.updated` → job Wallester → movimento Main OUT / subconta IN → `partner_funding_status = completed`
- [ ] Top-up > limite diário: rota `stripe_deferred`, sem funding Wallester imediato
- [ ] P2P entre clientes com transferência no parceiro de funding
- [ ] Webhook movimento de cartão Wallester → `funding_partner_movements` + débito no ledger
- [ ] `php artisan wallet:audit` sem findings críticos em ambiente de teste
- [ ] Backoffice: listagem de transações, modal de detalhes, retry de top-ups `awaiting_liquidity`
- [ ] Cash balance / transferência bancária: webhook `customer_cash_balance_transaction.created`

## Known limitations / follow-up

- Sem handler `payout.paid` — payout Stripe → banco ainda sem reconciliação automática no sistema
- `SettlementService::transferToMaster` permanece `PENDING_INTEGRATION`
- Reposição da liquidez Wallester Main após payout Stripe é operação manual
- Reconciliação ledger vs `customer_balances` em alguns clientes retail (ver `wallet:audit`)
EOF
)"
