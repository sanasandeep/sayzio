---
name: Biolink A/B & adaptive renderer accessor
description: Never read variant_a_snapshot/variant_b_snapshot columns directly for a biolink experiment; always go through BiolinkExperimentService::renderableBlocks().
---

`BiolinkExperiment` supports two modes: the original manual A/B split (`variant`
is `'a'`/`'b'`, backed by the `variant_a_snapshot`/`variant_b_snapshot` JSON
columns) and the adaptive multi-armed-bandit mode (`variant` is the string
`"arm:{id}"`, backed by live `biolink_blocks` rows re-ordered per bandit arm,
not a JSON snapshot at all).

Any call site that needs "the blocks this visitor should see for the active
experiment" must call `BiolinkExperimentService::renderableBlocks($exp,
$variant)` (or the equivalent `assignVariant()` + `renderableBlocks()` pair).
It is the only place that branches correctly on `$exp->isAdaptive()`.

**Why:** a mobile API controller was found reading `variant_{$assigned}_snapshot`
directly off the model. That works for manual A/B (`variant_a_snapshot` /
`variant_b_snapshot` exist as real columns) but silently returns nothing for
adaptive mode, since there is no `variant_arm:4_snapshot` column — the bug
would only surface as an empty-looking biolink for adaptive-mode visitors on
that one surface, no error thrown.

**How to apply:** when adding a new consumer of biolink experiment state
(new API endpoint, export job, preview surface, etc.), grep for
`variant_a_snapshot`/`variant_b_snapshot`/`_snapshot` direct reads before
assuming a new surface is safe, and replace with the service accessor. The
existing web renderer (`RedirectController::applyBiolinkAbExperiment` +
`common/biolink.blade.php` reading `_abVariantBlocks`) is the reference
pattern to copy.
