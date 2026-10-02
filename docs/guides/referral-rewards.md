# Recompensas

## Objetivo

Listar na app as recompensas do customer autenticado. Hoje só existe o programa **indicação**; a tabela `rewards` e o catálogo `reward_programs` servem para outros meios no futuro.

O grafo (quem indicou quem) continua em `customers.referred_by_customer_id`. O domínio Reward materializa o **ganho** (elegível ou não) e, mais tarde, o pagamento.

📖 **Contrato HTTP:** [GET /reward/my-rewards](/docs) (tag Reward)

## Programas

| Campo | Significado |
|-------|-------------|
| `slug` | Código estável (`referral`, …) |
| `name` | Nome na app |
| `description` | Detalhes, termos e condições |
| `valid_from` / `valid_until` | Validade da campanha. Ambos `null` = sem prazo |
| `is_active` | Programa ligado ou não |

| slug | name | Como nasce |
|------|------|------------|
| `referral` | Indicação | Indicado aprovado (`CustomerApproved`) |

Novos meios = nova row em `reward_programs` + política própria. Não se acrescentam colunas de indicação em `rewards` (o indicado fica em `referred_id`, nullable).

## Como nasce um reward de indicação

1. O indicado regista-se com o `username` do indicador (`referred_by_customer_id`).
2. Quando o indicado é aprovado (`CustomerApproved`), o listener grava **um** `rewards` com `program = referral` (único por `referred_id`).
3. Se o indicado já está `ACTIVE` → `eligible` + `qualified_at`. Caso contrário → `pending`.
4. Quem ganha é o indicador (`customer_id`).

Este `GET` **não** cria rewards. Só lê os do customer autenticado.

## Estados

| Status | Significado |
|--------|-------------|
| `pending` | Registado, ainda não elegível |
| `eligible` | Pode vir a ser pago |
| `cancelled` | Anulado (ainda sem fluxo HTTP) |
| `paid` | Pago (ainda sem fluxo HTTP) |

`amount_cents`, `currency` e `paid_at` ficam `null` até existir pagamento no ledger.

## Endpoint da app

### `GET /api/reward/my-rewards`

**Quando:** ecrã de recompensas.

**Pré-requisitos:** Bearer (`auth:sanctum`). Sem tríade de elegibilidade.

**Resposta:** paginação Laravel (`data`, `links`, `meta`), 20 por página, query `page`. Cada item tem `program` (`slug`, `name`, `description`, `valid_from`, `valid_until`). No programa `referral` inclui o indicado (`referred`).

## O que ainda não existe

- Outros programas além de `referral`
- Atribuir valor (`amount_cents`)
- Pagar para a wallet / ledger
- Cancelar ou marcar como pago via API

📖 **Jornada:** [Lógica do sistema](/docs/guides/logica-sistema)
