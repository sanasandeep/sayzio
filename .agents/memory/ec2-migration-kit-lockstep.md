---
name: EC2 migration kit lockstep
description: deploy/ec2/ mirrors the Replit prod pipeline and must track artifact.toml changes
---
`deploy/ec2/` is a documentation/template-only migration kit (bootstrap, deploy.sh, Nginx, systemd, env audit) — it never affects Replit dev/prod behavior.

**Why:** deploy/ec2/deploy.sh intentionally mirrors the 1inme production pipeline in `artifacts/1inme/.replit-artifact/artifact.toml` (view:clear BEFORE Vite build, `migrate --force` → `db:reconcile-migrations --force` fallback, keep-serving on failure with the `::1inme:: DEPLOY MIGRATION FAILED` marker). If the artifact.toml pipeline changes, the kit silently drifts.

**How to apply:** any change to the 1inme production build/run pipeline (or new required env vars) should also update `deploy/ec2/deploy.sh` and `deploy/ec2/env-checklist.md`. On EC2 config is .env-file-based, so the kit uses config:cache (Replit deploy only clears caches because config comes from process env). OpenAI/ElevenLabs/PayPal have NO env fallback — admin-DB-only.
