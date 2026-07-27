---
name: Showcase account is a real user's account
description: sana@sayzio.app (id 229) doubles as the showcase seeder target AND the owner's personal account — reseeding wipes her edits.
---
The showcase seeder (`showcase:seed`, workflows `showcase-prod-seed` / `showcase-seed`) WIPES and rebuilds ALL content on sana@sayzio.app, including on PROD. That account is now the owner's personal account.

**Why:** On 2026-07-27 an accidental mass workflow restart re-ran `showcase-prod-seed`, deleting all her links and recreating them with seeder content ("Sana Rahman" name, `sanashowcase` alias), which looked like "save buttons not working".

**How to apply:** Never run showcase seed workflows against prod without explicit user consent; treat "my edits vanished" reports on this account as possible reseed events (check links.created_at for fresh IDs). Her real primary alias is `sana` (with `sanashowcase` kept as extra alias).
