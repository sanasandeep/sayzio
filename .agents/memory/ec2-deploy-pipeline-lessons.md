---
name: EC2 deploy pipeline lessons
description: Hard-won fixes that make the GitHub Actions → EC2 (AL2023) deploy hands-off
---

Pipeline: push to main → GitHub Action SSHes to EC2 and runs `deploy/ec2/deploy.sh` as user `sayzio` (app at /var/www/sayzio, FPM user apache).

Rules that keep it green:
- **One-run script lag**: if deploy.sh pulls its own repo, each run executes the PRE-pull script. The workflow must `git fetch && git reset --hard origin/main` in a step BEFORE invoking deploy.sh.
- **Sudoers self-install**: the workflow SSHes as `ec2-user` (same key) each run and idempotently writes `/etc/sudoers.d/sayzio-deploy` (NOPASSWD scoped to systemctl/nginx binaries). Probe with `sudo -n systemctl --version` — `sudo -n true` fails because `true` isn't in the command-scoped grant.
- **php-fpm unit name varies** (php-fpm vs php8.4-fpm): detect via `systemctl cat <candidate>.service` loop, never grep `list-unit-files`.
- **pnpm drift**: server bootstrap used `corepack prepare pnpm@latest`, so its pnpm drifted from the workspace and started failing install (ignored-builds enforcement). Fix: pin `"packageManager": "pnpm@<exact>"` in root package.json — corepack then always uses the workspace version.
- **No TTY over SSH**: a pnpm version switch wants to recreate node_modules and aborts with ERR_PNPM_ABORTED_REMOVE_MODULES_DIR_NO_TTY; run `CI=1 pnpm install --frozen-lockfile`.
- **Mixed file ownership** (apache-owned runtime files): chmod only own files — `find ... -user $(id -un) -exec chmod ...`; workflow chowns storage/bootstrap-cache back to sayzio each run.

**Why:** runs #7–#18 each failed on one of these; #19 was the first fully hands-off green run.
**How to apply:** any change to the deploy pipeline must preserve these invariants; verify via GitHub API (workflow runs endpoint + logs zip, extract with python zipfile).

## Nginx conf sync clobbers certbot state (July 2026 incident)
- Installing repo `deploy/ec2/nginx/sayzio.conf` over the live conf wipes certbot's edits: real `server_name` list AND the 443/ssl directives. Symptoms: wrong cert (another vhost's CN) served, then 502 on PHP routes.
- **Recovery recipe:** sed real domains into `server_name`; on AL2023 sed `fastcgi_pass unix:/run/php/php8.4-fpm.sock` → `unix:/run/php-fpm/www.sock`; then ONE combined `certbot --nginx -d …` covering ALL domains that share the server block — per-domain certbot runs each rewrite the block's ssl_certificate, so the last domain's cert "wins" and breaks the others.
- deploy.sh's own nginx sync step needs passwordless sudo for the exact cp commands or it aborts ("sudo: a password is required"); running the copy manually as ec2-user works.
- Server: AL2023, ec2-user, app at /var/www/sayzio, FPM user apache. Domains on the box: sayzio.app, 1in.me (301→sayzio.app), getbio.one, bizs.club (+www). sayzio.link DNS points at the Replit deployment, not EC2.

**SSH access (user machine):** the user connects from their laptop with the key file `~/Downloads/1INME.pem` — full command: `ssh -i ~/Downloads/1INME.pem ec2-user@16.113.25.149`. Remind them of this exact command when guiding EC2 deploys.

**Nginx clobber incident (July 2026):** deploy.sh nginx sync overwrote the live customized /etc/nginx/conf.d/sayzio.conf with the repo TEMPLATE (yourdomain.com, Ubuntu socket, no SSL) → site served the wrong cert (mobile "Network request failed"). deploy.sh now skips sync when the installed config is customized but the repo copy still has the `server_name yourdomain.com;` placeholder. Recovery = sed real server_names + AL2023 socket back in, then `sudo certbot install --cert-name 1in.me` to re-add the 443 blocks (combined cert lives at /etc/letsencrypt/live/1in.me/).

**Blanket chown breaks PHP-FPM (July 2026):** `sudo chown -R ec2-user /var/www/sayzio` (used to fix git perms) took storage/ away from the web user — php-fpm (user `apache` on AL2023) could not write storage/logs or cache → API 500s ("Couldn't load dashboard") AND no new laravel.log lines (log writes themselves failed). Fix: `chown -R ec2-user:apache storage bootstrap/cache && chmod -R 775`. Laravel log path is /var/www/sayzio/artifacts/1inme/storage/logs/laravel.log (monorepo — not repo root). Tinker user model is \App\Modules\User\Models\User (App\Models\User does not exist; wrong class → empty token → misleading 401).

**view:cache as deploy user causes intermittent 500s (Aug 2026):** compiled Blade views owned by `sayzio` make PHP-FPM (apache) fail `touch(): Utime failed` in BladeCompiler when it recompiles one → "Oops! 500" on random pages (e.g. /user/visitors). deploy.sh must NEVER run `php artisan view:cache`; it ends with `view:clear` so FPM lazily compiles and owns the files. Diagnose prod errors without SSH via a temporary workflow_dispatch GH Action that SSHes with DEPLOY_SSH_KEY and tails storage/logs/laravel.log (delete the workflow after).
