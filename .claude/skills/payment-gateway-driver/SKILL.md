---
name: payment-gateway-driver
description: Use when adding or modifying an automated crypto payment provider.
---
1. Implement `App\Domain\Payments\Contracts\PaymentGateway`: createDepositAddress(), createInvoice(), verifyWebhook(Request): bool, parseWebhook(Request): WebhookDTO, getStatus(string $providerRef), supportedAssets().
2. Register in config/payments.php drivers map; select via PAYMENT_GATEWAY env.
3. Webhook controller: verify signature first → store raw payload in payment_webhooks (UNIQUE provider+event_id) → dispatch ProcessPaymentWebhook job → return 200 fast.
4. Job: map provider status → DepositStatus; credit only on `confirmed` with required confirmations; idempotent by provider tx id.
5. Tests with recorded fixture payloads (tests/Fixtures/<provider>/*.json) incl. bad signature, replay, partial payment, overpayment, expired.
