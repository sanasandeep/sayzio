---
name: Mobile/docs parity drift guard
description: parity:check-mobile-docs — baseline+--accept guard that flags new web block/link types missing a mobile editor or docs mention.
---

# Mobile/docs parity drift guard

`parity:check-mobile-docs` (`App\Console\Commands\CheckMobileDocsParity`,
composer `check:mobile-docs-parity`) is the recurring guard that stops the Expo
mobile app and the docs from silently falling behind new web block/link types.

**The rule:** the web registries are the source of truth —
`BiolinkBlock::pickerTypes()` (blocks, excluding aliases + system/verified) and
`LinkTypeCategories::types()` (link types). Their mobile-editor coverage
(`artifacts/1inme-mobile/lib/api/blocks.ts` → `BLOCK_KINDS`, matched by exact
`type:` string) and docs coverage (`docs/{features,api,knowledge-base}.md`,
matched by slug OR human label) are hand-maintained. A committed baseline
`artifacts/1inme/docs/mobile-docs-parity.json` records the *triaged* state of
every current type. The check FAILS on: a new web type with no baseline entry
(untriaged), a stale baseline entry (type removed/renamed), or a mobile
regression (a block that was `mobile:true` in the baseline no longer in
`BLOCK_KINDS`). `--accept` regenerates the baseline.

**Why it works this way (and not 1:1 enforcement):** only ~8/128 picker block
types have a mobile editor by design (mobile is intentionally a curated subset),
so requiring full parity would be pure noise. The forcing function is instead
"you can't add a web type without consciously recording a mobile/docs decision
in the same commit" — same pattern as `demo:check-allowlist` /
`check:dialer-sync` (baseline + `--accept`).

**Why docs coverage is info-only, not a hard fail:** label matching is fuzzy
(generic words like "Video" over-match), so docs booleans are surfaced in the
report + stored in the baseline but never gate on their own. Only new/stale
entries and *mobile* regressions (exact-slug, precise) are hard failures.

**How to apply / lockstep surfaces:** the guard is wired as (1) composer script,
(2) GH job `Mobile/docs parity guard (no database)` in `laravel-tests.yml` +
its name mirrored in the `tests-passthrough` matrix, (3) Replit validation gate
`mobile-docs-parity`, (4) in-suite `MobileDocsParityDriftTest`, (5) a weekly
`Schedule::command('parity:check-mobile-docs')` audit. DB-free (reads constants
+ files). When you ship a new web block/link type: add a mobile `BLOCK_KINDS`
entry or accept it as web-only, mention it in docs, then run `--accept`.

Note the known naming mismatch that keeps mobile coverage at 8 not 11: mobile
`BLOCK_KINDS` uses `header`/`text`/`embed` while web slugs are
`heading`/`paragraph_rich`/`iframe_embed`, so those three don't match — expected,
captured in the baseline, not something this guard tries to "fix".
