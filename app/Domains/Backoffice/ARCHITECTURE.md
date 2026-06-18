# Domínio Backoffice

Painel administrativo (staff). Autenticação por sessão Laravel (`User`), separado do domínio `Auth` (clientes via Sanctum).

## Responsabilidade

- UI web do backoffice (Blade)
- CRUD administrativo (clientes, users, roles, settings, fees, contas a pagar, audit)
- Notificações in-app dos utilizadores staff
- Queries de leitura para ecrãs (stats, listagens)

**Não inclui** (por agora):

- API mobile do cliente (`DashboardCustomerController` → futuro domínio `Wallet` ou `CustomerApp`)
- Regras de lifecycle/onboarding (domínio `Onboarding` — o backoffice só consulta/edita com cuidado)
- Integrações Stripe/Wallester/Ebury (domínios respectivos)

## Estrutura alvo

```
app/Domains/Backoffice/
├── ARCHITECTURE.md
├── BackofficeServiceProvider.php
├── Controllers/          # HTTP — finos, delegam a Queries/Services
├── Queries/              # Casos de uso só-leitura (CQRS light)
├── Actions/              # Comandos de escrita (1 caso de uso)
├── Services/             # Escrita e orquestração do backoffice
├── Requests/             # Form requests do backoffice
├── DTOs/                 # Dados para views/API JSON interna
├── Resources/            # JsonResource para endpoints fetch do navbar
└── Routes/
    ├── web.php             # login staff + dashboard (Blade)
    └── backoffice.php      # API v2 health
```

## Dependências permitidas

| Pode usar | Não deve |
|-----------|----------|
| `Domains/Onboarding/Enums/*` (leitura) | Gateways de integração directamente |
| Models Eloquent (`Customer`, `User`, …) | Lógica de provisionamento |
| `SettingsService` (infra partilhada) | Duplicar regras de `CustomerLifecycleService` |
| Repositories existentes (fase inicial) | |

## Migração incremental

### Fase 1 — Fundação (feito)
- [x] `BackofficeServiceProvider` + registo em `DomainServiceProvider`
- [x] `GetDashboardOverviewQuery` — stats da home
- [x] `StaffNotificationService` + `NotificationController` no domínio
- [x] `NotificationService` delega métodos staff (compatibilidade)

### Fase 2 — Clientes (feito)
- [x] `ListCustomersQuery` / `GetCustomerDetailQuery`
- [x] `CustomerController` no domínio Backoffice
- [x] `UpdateCustomerFromBackofficeAction` — `status` via `CustomerLifecycleService::transitionTo`

### Fase 2b — TPA do cliente (feito)
- [x] `CustomerTpaController` + actions de fees/unlink

### Fase 3 — Administração (feito)
- [x] Users → `ListUsersQuery` + `Create/Update/DeleteStaffUserAction`
- [x] Roles → `ListRolesQuery` + `Create/Update/DeleteRoleAction`
- [x] Settings → `SettingsManagementService`

### Fase 4 — Operações financeiras (feito)
- [x] Stripe fees → `ListStripeFeesQuery` + CRUD actions + `StripeFeeCacheService`
- [x] Accounts payable → `ListAccountsPayableQuery` + actions de ciclo de vida e anexos
- [x] `GetAcceptedCurrenciesQuery` partilhado (settings)

### Fase 5 — Auth staff + rotas (feito)
- [x] `LoginController` → `Backoffice\Controllers\Auth\LoginController`
- [x] `StaffSessionService` + `StaffLoginRequest`
- [x] Rotas web em `Domains/Backoffice/Routes/web.php` (carregadas por `routes/web.php`)
- [x] Audit logs → `ListAuditLogsQuery` + `AuditLogController`

### Fase 6 — Limpeza (feito)
- [x] Removido `app/Http/Controllers/Dashboard/*`
- [x] Removido `app/Http/Controllers/Auth/LoginController`
- [x] `routes/web.php` só rotas não-backoffice + require do domínio

## Rotas

**Web:** `routes/web.php` carrega `Domains/Backoffice/Routes/web.php` (nomes `login`, `dashboard.*` inalterados).

**API v2:** `GET /api/v2/backoffice/health`

## Pós-migração (limpeza)

- [x] `app/Clients/` removido (SMS e Didit em `Domains/*/Clients`)
- [x] `app/Providers/SmsProvider` removido
- [x] Webhook Ebury em `Domains/Ebury`
- [x] `NotificationService` só clientes; staff em `StaffNotificationService`
- [x] Resource morto `CustomerDiditSession` removido

### Ainda em `app/` (intencional)

- `Models/`, `Http/Middleware`, `Http/Requests`, `Http/Resources` (API v1)
- `Services/` — Customer, Settlement, DeviceAuth, Settings, Notification (cliente)
- `Contracts/` + `Repositories/` — partilhados entre domínios
- `Http/Controllers/Api/` — Customer, Tpa, Notification, Setting, BasicSystemInfo

### Domínios Wallet + Merchant (novo)

- **Wallet** — dashboard/saldos/transações/P2P (`api/v2/wallet/*`)
- **Merchant** — registo merchant, payment requests, página `/pay/{uuid}` (`api/v2/merchant/*`)

## Padrão por ecrã

```
Controller (Backoffice)
    → Query (leitura) ou Service/Action (escrita)
        → Repository / Model / Domínio externo (só leitura ou comando explícito)
    → view('dashboard.*')  — views ficam em resources/views/dashboard/ até fase posterior
```
