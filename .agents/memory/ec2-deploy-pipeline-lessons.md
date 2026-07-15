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
