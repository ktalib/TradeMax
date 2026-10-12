# API — TradeMax v1

> Public + private REST API for the mobile app and API traders. OpenAPI 3 spec generated with `dedoc/scramble` at `/docs/api`.

## Auth

- First-party mobile: Sanctum tokens.
- Trading bots: HMAC-signed keys — headers `X-API-KEY`, `X-TIMESTAMP`, `X-SIGNATURE = HMAC-SHA256(secret, timestamp+method+path+body)`, 30s window.

## Endpoints (to implement, Phase 12)

- **Public:** markets, ticker, order book depth, trades, candles.
- **Private (read):** balances, deposits, withdrawals, orders (list), trades, staking, copy stats.
- **Private (write):** place/cancel order. Withdrawal creation is never available via API key.

## Conventions

- JSON:API-ish responses, cursor pagination.
- Error envelope: `{ "error": { "code", "message", "details" } }`.

_(Endpoint details added per module.)_
