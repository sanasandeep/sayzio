---
name: AI monthly allowance counted from wallet transactions
description: Pattern for per-plan monthly AI usage quotas when no dedicated generation record exists.
---

# Monthly AI allowance via wallet spend/refund counting

When a coin-charged AI feature needs a per-plan monthly quota and no generation
table exists, count `wallet_transactions` `type='spend'` with
`meta->feature = '<feature>'` in the calendar month (matches the other monthly
meters), excluding spends that have a matching `type='refund'` row whose
`meta->>'related_id'` equals the spend id (that's how AiUsageCharger refunds
attribute). Refunded/failed generations then never consume allowance, and
cooldown-cached re-serves (which don't charge) are naturally free.

**Why:** avoids a new table/counter; the wallet ledger is already the source of
truth for "successful paid generation".

**How to apply:** put the check in the service `generate()` BEFORE the coin
charge (throw a dedicated exception both controllers catch). Allowance comes
from `getPlanFeature('max_<x>_monthly', -1)` normalized via `PlanLimit::normalize`
so -1 = unlimited covers both the plan default and the bypass-permission
sentinel; surfaced numbers must always be the normalized value. First use:
AI Artistic QR (`max_qr_art_monthly`, QrArtService).
