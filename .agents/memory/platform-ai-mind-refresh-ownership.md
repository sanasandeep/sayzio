---
name: Platform default AI Mind refresh ownership
description: Which surfaces keep the platform "Sayzio Default Mind" current, and why sign-up no longer does.
---

The platform-managed "Sayzio Default Mind" (`AiMind` with `user_id=null, is_default=true`) stays current through THREE distinct surfaces, split by what "fresh" means:

1. **Content freshness (pricing + feature catalogue)** is owned by **live query-time reads**, NOT by any refresh job. `AiMindFeatureAdapter::publicSnapshot('pricing'|'features')` is called at query time in `AiMindQueryService`; the `TYPE_FEATURE` source rows are never embedded (ingestor just marks them READY). So catalogue/price changes reflect instantly with zero re-provision. This is why "the feature catalogue changed" can never make the default Mind stale.

2. **Source-set + stats reconcile** (attach any newly-declared `AiMindProvisioner::PLATFORM_FEATURE_KEYS`, run `recountStats()`) is owned by:
   - the scheduled command `minds:reseed-platform` (`ReseedPlatformAiMind`, daily 03:30, in `routes/schedules/syncing-integrations.php`) — the reliable, sign-up-independent trigger;
   - the lazy `AiMindProvisioner::ensureForUser()` path (fires on every AI dashboard visit: Mind/Coach/AskCoach/Persona/Companion/BrandKit/AiMindPicker controllers);
   - the manual admin "Re-seed default" button (`AiMindAdminController::reseedDefault`, which additionally re-queues ingestion of ALL sources).

**Why the scheduled command exists:** Task #4596 changed the `User::created` hook to dispatch `ProvisionPlatformAiMindJob` ONLY when the platform default Mind is entirely MISSING. Before that, `ensurePlatformDefault()` (hence the #2 reconcile) ran on EVERY sign-up as a side effect. After #4596 the only automatic reconcile left was the user-visit-dependent lazy path, so a scheduled reconcile was added to guarantee it happens regardless of sign-ups or admin action.

3. **Static text freshness (About + FAQ)** is owned by `AiMindProvisioner::ensureStaticTextSources()`, called unconditionally inside `ensurePlatformDefault()` (so it rides the SAME three triggers as #2: scheduled `minds:reseed-platform`, lazy `ensureForUser`, admin reseed). It create-or-refreshes the two code-defined sources (`aboutText()` = TYPE_TEXT "About Sayzio", `seedFaqs()` = TYPE_FAQ "Common questions") from the code constants and re-queues ingestion. Identity is a stable `meta['managed_key']` (`about`/`faq`), NOT the title, so a title edit can't spawn a duplicate. Idempotent: re-ingest fires ONLY when the stored body differs from the code body (drift) or on first create; an unchanged body is a true no-op. Legacy installs (seeded before `managed_key` existed) are matched by type+title, adopted (tagged) in place, and only re-embedded if their body actually drifted.

**Why this matters:** unlike #1 (pricing/features, answered live), About + FAQ ARE embedded, so their stored body is what the assistant answers from. Before this was added, editing the `aboutText()`/`seedFaqs()` constants did NOT propagate to existing installs (admin reseed re-queued ingestion but with the same stale stored body) — the assistant kept answering with the old copy until a manual DB edit.
