# Coin Purchase & AI Credit Metering Audit

This document records the audit of two money-handling flows in 1INME and
the by-design exceptions found. Both flows were verified by tracing the
code paths end-to-end; **no defects were found**, so no remediation was
required beyond surfacing AI-credit usage on the pricing/upgrade pages.

## 1. Coin purchase → wallet credit

A coin-package purchase flows: coin-package selection
(`WalletController@buyHandoff`) → pending invoice with a `coin_package`
line-item meta (`ActivateSubscription::issuePendingInvoice`) → gateway
checkout (`GatewayManager` adapter) → gateway webhook
(`WebhookController@handle`) → `ActivateSubscription::run` → wallet credit
(`WalletService::credit`).

### How crediting is triggered
- The gateway delivers a `payment.succeeded` webhook to
  `POST /webhooks/{gateway}` → `WebhookController@handle`.
- The adapter verifies the signature and parses the event; the controller
  resolves the invoice and, for subscription/coin invoices, calls
  `ActivateSubscription::run($invoice, $gateway, $ref)`.
- `run()` detects coin-package invoices by `meta.kind === 'coin_package'`,
  sums `coins + bonus` per line item, and calls
  `WalletService::credit($user, $totalCoins, [... 'idempotency_key' => 'invoice:'.$invoice->id])`.

### Once-only guarantee (idempotency) — verified at three layers
1. **Webhook router**: `PaymentAttempt::firstOrCreate` on a unique
   `(gateway, gateway_ref)` index — a re-delivered webhook short-circuits.
   The unique-constraint race is caught and converges on the existing row.
2. **Activation action**: `Invoice::lockForUpdate()` inside a DB
   transaction, plus an early return when the invoice is already `paid`
   (and a hard error if it is `paid` but missing a subscription link).
3. **Wallet ledger**: `WalletService::record` checks the unique
   `idempotency_key` (`invoice:{id}`) before writing, locks the wallet row
   with `lockForUpdate()`, and on a unique-key race rolls back the balance
   bump and returns the winning transaction.

Base + bonus coins are credited together, exactly once per paid invoice.

### Where it could fail (acceptable / by-design)
- **Unknown invoice / stubbed adapter**: the webhook is accepted (HTTP
  202) and logged so the gateway stops retrying; no credit occurs. This is
  intentional — it never double-credits and never silently loses a known
  paid invoice.
- **Notifications & receipt email** are best-effort (wrapped in try/catch)
  and never roll back the credit.

**Verdict:** reliable, once-only crediting. No fix required.

## 2. AI credit metering across AI features

Every AI feature routes through `OpenAiService` (chat / chatStream /
embed) or the voice services (`WhisperService`, `ElevenLabsService`,
`VoiceAssistantService`), which all charge via `AiCreditService`.
`AiCreditService::charge` locks the balance row, enforces a non-negative
balance, and records a `spend` ledger row. A pre-call affordability gate
(`OpenAiService::ensureCanAfford`, or balance checks in the voice
services) rejects the request before hitting the provider when the billed
account can't afford the worst-case cost.

### Features charging the signed-in user's own balance
- **Ask Coach** — `AskCoachController@send/sendStream` (incl. tool-call turns)
- **AI Personas** — `PersonaRuntime::turn` (chat + retrieval embeddings)
- **Persona profile generation** — `PersonaController@generate`
- **Card / Brochure Scanner** — `CardBrochureExtractionService::extract`
  (refunds the owner if the scan fails after the charge)
- **AI Resume Tools** — `ResumeCoverLetterService` (tailoring, cover
  letters, import); surfaces estimated cost before running
- **AI Biolink Page Builder** — `AiBiolinkBuilderService` (feature key
  `biolink_builder`); refunds the owner if the AI response fails to
  parse/validate
- **Voice Assistant** — Whisper STT + GPT reply + ElevenLabs TTS
- **AI Minds (user minds)** — `AiMindIngestor::ingest` charges the mind owner

### By-design exceptions (explicitly NOT "fixed")
- **Site Assistant, anonymous visitors** — anonymous on-site/widget chat
  draws on the **platform admin's** balance (`SiteAssistantRuntime::billingUser`,
  resolving `billing_user_id` then the first `user.platform.admin`). Signed-in
  visitors are charged their own balance.
- **AI Companions** — a chat turn is billed to the **companion owner's**
  balance (a visitor chatting with someone's companion does not pay; the
  owner does). `CompanionRuntime` refunds the owner if a turn fails.
- **Platform AI Minds** — ingestion of platform-managed minds is billed to
  the **platform admin** (`user.ai_minds.manage_platform`), not end users.

**Verdict:** every feature meters the correct account with a pre-call
gate. No bypasses found. No fix required.

## 3. Pricing copy

The `#coins` section of `/pricing` and the in-app `/user/upgrade` page now
enumerate the AI-credit-consuming features via the shared partial
`resources/views/public/pricing/_ai_credit_uses.blade.php`, so buyers can
see where credits go. USD/INR switching, the marketing-tracking ping, and
reduced-motion behavior are unchanged (static content only).
