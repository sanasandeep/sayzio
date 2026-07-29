---
name: Showcase account is a real user's account
description: the showcase owner account (see showcase:seed source) (id 229) doubles as the showcase seeder target AND the owner's personal account — reseeding wipes her edits.
---
The showcase seeder (`showcase:seed`, workflows `showcase-prod-seed` / `showcase-seed`) WIPES and rebuilds ALL content on the showcase owner account (see showcase:seed source), including on PROD. That account is now the owner's personal account.

**Why:** On 2026-07-27 an accidental mass workflow restart re-ran `showcase-prod-seed`, deleting all her links and recreating them with seeder content ("Sana Rahman" name, `sanashowcase` alias), which looked like "save buttons not working".

**How to apply:** Never run showcase seed workflows against prod without explicit user consent; treat "my edits vanished" reports on this account as possible reseed events (check links.created_at for fresh IDs). Her real primary alias is `sana` (with `sanashowcase` kept as extra alias).

**Safety latch (July 2026):** `showcase:seed` now refuses destructive `--force` runs unless env `SHOWCASE_SEED_CONFIRM=yes` is set (guard lives in the SeedShowcaseAccount command, not the workflow — workflow limit blocks reconfiguring). `--analytics-only` stays unrestricted. Intentional prod reseed: `SHOWCASE_SEED_CONFIRM=yes DB_HOST="$PROD_DATABASE_URL" php artisan showcase:seed --force`.
