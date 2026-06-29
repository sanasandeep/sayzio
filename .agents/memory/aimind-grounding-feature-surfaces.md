---
name: Adding AiMind (KB) grounding to an AI feature
description: The lockstep surfaces required to give a Sayzio AI feature Knowledge-Base grounding the way Coach/Persona do it.
---

Giving an existing 1inme AI feature optional Knowledge-Base (AiMind) grounding is a multi-surface lockstep. Coach (`CoachController`) + Persona are the canonical reference; copy their shapes exactly.

**Why:** the picker UI, server resolution, prompt injection, saved-defaults round-trip, and persisted `minds_used`/citations all have to agree or grounding silently no-ops, mis-charges, or breaks the breakdown UI.

**How to apply — touch all of these:**
- **Service**: add `string $kbContext = ''` (default empty!) to the prompt-building + estimate + generate methods; append it to the system prompt only when non-empty. Empty default = selecting no Mind preserves today's behavior/spend exactly.
- **Controller**: inject `AiMindQueryService`; resolve via `resolveMindsForUser($user, $mindIds, $includePlatform)` (ownership validated server-side), then `retrieveContext($user, $minds, $query)` → `{context, citations, credits_spent, mind_stats}`. Wrap retrieval in try/catch (rethrow `InsufficientCoinsForAiException`, swallow others). Add KB credits to spend, merge KB citations, persist `minds_used` (id/name/is_platform/chunks_used/top_score) in the message meta. Add `saveDefaults`(POST)/`clearDefaults`(DELETE) + `userMinds()`/`platformMind()` helpers mirroring Coach. Prefill the picker from `AiMindDefault::forUserFeature($userId, $feature)` merged into the view `$input`, only when the key isn't already present.
- **Routes**: add `<feature>.defaults.save` (POST) + `.defaults.clear` (DELETE).
- **Picker partial** (`user/ai/_partials/mind-picker.blade.php`): pass `$defaultFeature` AND, when the feature's routes don't live under `user.ai.<feature>`, an explicit `$defaultRoute` (e.g. Brand Kit = `user.brand-kits`). The clear button routes to DELETE via `name="_method" value="DELETE"` as the submitter.
- **View**: the picker lives in its OWN `<form action="...defaults.save">` (so save/clear round-trips) tagged `data-kb-picker`. For AJAX/stream features (Brand Kit generate, Ask Coach send) the JS reads checked `input[name="mind_ids[]"]` + the `include_platform` checkbox straight from that form and merges them into the request body — do NOT rely on the form's own submit.
- **Streamed features**: thread `array $mindIds, bool $includePlatform` through the stream method signature and into the SSE `done`-frame meta too, not just the non-stream path.

Gotcha: inserting the picker form between the chat panel and the compose form breaks any JS using `form.previousElementSibling` to find the scroll panel — select the panel by class/relationship instead.
