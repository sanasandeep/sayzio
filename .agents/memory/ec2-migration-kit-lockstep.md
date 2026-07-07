---
name: EC2 migration kit lockstep
description: deploy/ec2/ mirrors the Replit prod pipeline and must track artifact.toml changes
---
`deploy/ec2/` is a documentation/template-only migration kit (bootstrap, deploy.sh, Nginx, systemd, env audit) — it never affects Replit dev/prod behavior.

**Why:** deploy/ec2/deploy.sh intentionally mirrors the 1inme production pipeline in `artifacts/1inme/.replit-artifact/artifact.toml` (view:clear BEFORE Vite build, `migrate --force` → `db:reconcile-migrations --force` fallback, keep-serving on failure with the `::1inme:: DEPLOY MIGRATION FAILED` marker). If the artifact.toml pipeline changes, the kit silently drifts.

**How to apply:** any change to the 1inme production build/run pipeline (or new required env vars) should also update `deploy/ec2/deploy.sh` and `deploy/ec2/env-checklist.md`. On EC2 config is .env-file-based, so the kit uses config:cache (Replit deploy only clears caches because config comes from process env). OpenAI/ElevenLabs/PayPal have NO env fallback — admin-DB-only.

The kit supports TWO distros (the user's real instance is Amazon Linux 2023, `ec2-user@`, domain sayzio.app): `bootstrap.sh` (Ubuntu) and `bootstrap-al2023.sh` (dnf). deploy.sh auto-detects PHP-FPM unit (`php8.4-fpm` vs `php-fpm`) and the ACL user (`www-data` vs `apache`). Key AL2023 facts: PHP 8.4 needs AL2023 release 2023.7+; curl ext bundles into php8.4-common (no php8.4-curl); zip package name varies (php8.4-zip vs php8.4-pecl-zip — bootstrap tries both); FPM socket /run/php-fpm/www.sock with nginx pre-listed in listen.acl_users; nginx configs go in /etc/nginx/conf.d/ (no sites-enabled) and can't use Ubuntu's snippets/fastcgi-php.conf (sayzio.conf inlines portable fastcgi params). Any change to one bootstrap must be mirrored in the other, and the README distro cheat-sheet + sudoers blocks kept in sync. Package names were desk-verified only — no live AL2023 host in this environment.
