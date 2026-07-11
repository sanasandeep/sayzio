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

**Known limitation (pre-existing, unrelated to #4596):** the static `aboutText()` / `seedFaqs()` text sources are seeded only on first Mind creation and are NOT re-synced from code by any path (admin reseed re-queues ingestion but with the same stored body). Editing those code constants does not propagate to existing installs without a manual DB edit.
