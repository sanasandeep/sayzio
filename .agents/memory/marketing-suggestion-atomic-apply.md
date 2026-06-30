---
name: Marketing suggestion atomic apply
description: How concurrent apply-suggestion is made race-safe for the Marketing Strategist
---
The rule: turning a Marketing Strategy suggestion into an owned object (link/block/pixel/post) must go through `MarketingSuggestionApplier::claimAndApply()`, which does an atomic compare-and-set (`UPDATE ... WHERE status='pending'` → `applied`). Only the request whose update affects 1 row builds the object; losers get a `SuggestionNotPendingException`.

**Why:** both apply controllers (web `User/Controllers/AI/MarketingStrategistController` + API `Api/Controllers/MarketingStrategistController`) used an `if (isPending())` read-then-write guard that is NOT atomic — two near-simultaneous applies (double-tap/retry/web+mobile) could both pass it and both create duplicate objects.

**How to apply:** keep the controllers' early `isPending()` fast-path (preserves the sequential 422 behavior + tests), but the actual write goes through `claimAndApply`. It keeps the in-memory model reading `pending` (raw UPDATE doesn't refresh it) so the inner `apply()` guard passes, then flips the model to `applied` (+ref) on success or `error` (cleared applied_at) on failure — mirroring what the controllers used to write. Concurrency is exercised in tests by loading two stale `pending` model instances and calling `claimAndApply` on each (true HTTP concurrency can't run in PHPUnit).
