# Postman — Wallester API (direct)

Testes **directos** na API Wallester (`api-frontend.wallester.com`), sem passar pelo Yoop.

## Setup rapido (recomendado)

### 1. Obrigatorio no `.env`

```env
WALLESTER_API_KEY=...
WALLESTER_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----"
WALLESTER_PRODUCT_CODE=...   # obrigatorio — sem isto a API devolve 401
WALLESTER_BASE_URL=https://api-frontend.wallester.com/v1/
```

O **product code** vem da Wallester (um codigo por produto). Sem ele, o header `X-Product-Code` vai vazio e todos os pedidos falham com **401 Unauthorized**.

### 2. Gerar environment Postman

```bash
php postman/scripts/sync-postman-env.php
```

Gera `postman/wallester-sandbox.local.postman_environment.json` com JWT e credenciais do `.env`.

### Importar no Postman

**Opcao A — collection separada (raiz do repo):**
- `wallester-api.postman_collection.json` (na raiz, igual a `postman/`)

**Opcao B — pasta dentro da Yoop API (se a separada falhar):**
- Re-importa `yoop-api.postman_collection.json` → pasta **Wallester Direct**

**Environment (obrigatorio):**
- Importa `postman/wallester-sandbox.local.postman_environment.json`
- Selecciona **Wallester Sandbox (local)** no canto superior direito

Se der **401**, volta a correr o script sync (JWT expira).

## Porque nao usar o template vazio?

O template `wallester-sandbox.postman_environment.json` nao tem credenciais nem JWT.

O script `sync-postman-env.php` preenche tudo a partir do Laravel — evita colar chave PEM manualmente no Postman (que quase sempre falha).

## JWT manual (alternativa)

```bash
php postman/scripts/wallester-jwt.php
```

Cola o output em **Environment → wallester_jwt**.

## Fluxo de teste

1. Create Account
2. Create Card Virtual
3. Activate Card (se status Created)
4. List Account Transactions / List Card Transactions
5. Get Transaction

## Ficheiros

| Ficheiro | Uso |
|----------|-----|
| `wallester-api.postman_collection.json` | Collection |
| `wallester-sandbox.local.postman_environment.json` | Gerado pelo sync — **usar este** |
| `wallester-sandbox.postman_environment.json` | Template vazio (fallback) |
| `scripts/sync-postman-env.php` | Gera environment com JWT |
| `scripts/wallester-jwt.php` | So o token |

## Team workspace

1. Postman → Create Team Workspace
2. Import collection + environment **local** de cada dev (ou partilha o workspace, cada um corre o sync no seu PC)

## API Yoop (proxy)

Para `/api/wallester/*` usa `yoop-api.postman_collection.json` na raiz.
