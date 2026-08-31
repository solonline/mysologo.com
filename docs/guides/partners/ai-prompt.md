# Prompt para IA — API Merchant Yoop

Prompt pronto a colar num assistente de código para implementar a integração de parceiro Yoop (payment requests + webhook HMAC + opcionalmente terminal POS).

Copie o bloco completo.

````markdown
# Integração Yoop Merchant API

És um engenheiro a integrar a API de parceiros Yoop no nosso produto.

## Contexto
- API de merchants/parceiros (não app do consumidor final)
- Autenticação: header `X-Merchant-Key: {api_key}` em todos os pedidos
- Base URL: `{YOOP_BASE_URL}/api`
- Credenciais (`api_key`, `webhook_secret`) são fornecidas na activação do merchant / app Yoop — nunca as hardcodes; usa env/secrets

## A) Payment requests (links de pagamento)
1. `POST /merchant/payment-requests` com JSON:
   - obrigatório: `amount` (inteiro em cêntimos, ≥ 1)
   - opcional: `description`, `external_reference`, `webhook_url`, `expires_in` (5–1440 minutos; omitir ≈ 1 ano), `payment_method_types` (`card`, `mb_way`), `items`
   - `items` (opcional): array de `{ "name": string, "quantity": number, "unit_price": integer em cêntimos }`. Se enviado, aparece na página `/pay/{uuid}` ao finalizar o pagamento; se omitido, não é mostrado
   - se `webhook_url` for omitido no pedido, usa-se o `webhook_url` do merchant
2. Resposta 201: `uuid`, `url`, `status`, `amount`, `currency`
3. Redireccionar o pagador para `url` (página Yoop `/pay/{uuid}`), browser ou iframe
   - Card: largura máx. 384px; iframe sugerido `width="400"` `height="720"` (altura dinâmica)
   - Em iframe: usar `?embed=1`; sucesso fica no iframe; ouvir `postMessage` (`source=yoop-pay`, `type=payment_request.completed`); não esperar `return_url` no iframe
   - Full-page opcional: `?return_url=https://loja.../obrigado` (só fora de iframe)
4. Rate limit ≈ 10 pedidos/minuto → tratar 429

## B) Webhook quando o pagamento completa
- Yoop faz `POST` para o `webhook_url` do pedido (ou do merchant) com tipo `payment_request.completed`
- Headers: `X-Yoop-Timestamp`, `X-Yoop-Signature` (`v1=` + hex HMAC-SHA256), `X-Yoop-Delivery-Id`
- Verificar assinatura:
  - `signed_payload = "{timestamp}.{raw_http_body}"`
  - `expected = HMAC_SHA256(webhook_secret, signed_payload)`
  - rejeitar se |now - timestamp| > 300 segundos
  - comparar com comparação em tempo constante (`hash_equals` / equivalente)
- Payload relevante em `data`: `uuid`, `status`, `settlement_status`, `funds_available`, `external_reference`, `amount`, `fee_total`, `net_amount`, `currency`, `paid_at`, `funds_available_at`
- Interpretação:
  - `status=completed` → cliente pagou
  - `amount` = cobrado; `fee_total` = taxas somadas; `net_amount` = líquido (podem ser `null` até o Stripe confirmar fees — há reenvio)
  - `funds_available_at` = data prevista `YYYY-MM-DD`
  - `settlement_status=pending` / `funds_available=false` → pago, fundos ainda a liquidar
  - `settlement_status=available` / `funds_available=true` → fundos disponíveis
- Retries: até 10 com backoff; endpoint deve ser idempotente (`uuid` ou `X-Yoop-Delivery-Id`)
- Responder 2xx só após processar; erros 4xx (exceto 408/429) não são reintentados

## C) Terminal POS (opcional, se o produto tiver TPA)
1. `POST /merchant/terminal/connection-token` → `secret` para o SDK do terminal
2. `POST /merchant/terminal/create-intent` com `Idempotency-Key` (UUID) e body `{ tpa_serial_number, amount, currency }`
3. Usar `client_secret` no SDK para cobrar no reader
4. `payment_intent.id` é o ID interno Yoop

## Regras
- Não uses Bearer token nestes endpoints de parceiro
- Não inventes rotas ou campos
- Não exponhas secrets no frontend público
- Preferir testes: criar pedido (mock HTTP), verificar HMAC do webhook, idempotência em retry

## Entregáveis
1. Serviço/cliente HTTP tipado
2. Handler de webhook seguro + idempotente
3. Fluxo criar → abrir `url` → confirmar no webhook
4. `.env.example` com `YOOP_BASE_URL`, `YOOP_MERCHANT_API_KEY`, `YOOP_WEBHOOK_SECRET`
5. README curto do fluxo para a equipa
````

## Guias relacionados

- [Payment requests](/docs/partners/guides/payment-requests)
- [Terminal (POS)](/docs/partners/guides/terminal-topup)
- [Referência OpenAPI parceiros](/docs/partners)
