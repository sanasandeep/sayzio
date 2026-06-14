---
name: Coin invoice activation re-delivery
description: Why re-delivering a coin-package webhook throws instead of cleanly no-op'ing in 1inme billing.
---

# Coin invoice re-delivery throws

ActivateSubscription has an early guard: `status === 'paid' && !subscription_id` → throws "already marked paid but has no subscription". Coin-package invoices intentionally never get a `subscription_id`, so on the FIRST re-delivery of an already-paid coin invoice this guard fires and throws — BEFORE the coin block's own idempotency short-circuit (`status === 'paid' return`) can no-op.

**Why:** the early guard was meant for plan/subscription invoices; it doesn't account for coin invoices. Net effect: re-delivered coin webhooks 500, though no double-credit ever happens (money is safe).

**How to apply:** When testing or touching coin crediting, don't assume re-running ActivateSubscription on a paid coin invoice is a graceful no-op — it throws. The real no-double-credit safety net is the WalletService idempotency key (`invoice:<id>`), not activator re-entrancy. Tolerate the throw in tests and assert balance/transaction count instead. (Bug tracked as a follow-up to fix the guard ordering.)
