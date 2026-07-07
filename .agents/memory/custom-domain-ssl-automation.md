---
name: Custom-domain HTTPS automation (EC2)
description: How automatic Let's Encrypt issuance for verified custom/global domains works and its lockstep surfaces.
---

Automatic HTTPS for customer custom domains + admin global domains is EC2-only, gated by `domains.ssl.auto_issue` (`SSL_AUTO_ISSUE`, default OFF — Replit's proxy terminates TLS, so the scheduled command must stay a no-op there).

**Shape:** scheduled `domains:issue-certificates` (every 10 min) → `SslCertificateIssuer` → `Process::run` of a sudoers-whitelisted root helper (`deploy/ec2/issue-domain-cert.sh` installed as `/usr/local/sbin/sayzio-issue-cert`) → certbot **webroot** issuance + renders `deploy/ec2/nginx/custom-domain.conf.template` into `/etc/nginx/conf.d/sayzio-domain-<domain>.conf` + `nginx -t` rollback + reload. State (`ssl_status/attempts/last_error/alerted_at`) lives on the `domains` row; both verify paths (user + admin controllers) call `markPending()`.

**Why webroot works pre-vhost:** the main `sayzio.conf` server is the sole/default vhost, so unmatched Hosts fall through to the Laravel public dir — ACME HTTP-01 files are served before any per-domain conf exists.

**Lockstep surfaces (change one, check the rest):**
- Template location blocks mirror the Laravel section of `nginx/sayzio.conf`.
- Config command string is split on whitespace into argv; domain + optional email appended as separate argv entries — never shell-interpolated. Domain re-validated by regex in BOTH PHP and the bash helper (defense in depth: value reaches root).
- Per-domain confs go directly in `/etc/nginx/conf.d/*.conf` (subdirectories are NOT included by default on Ubuntu/AL2023).
- Don't include certbot's `options-ssl-nginx.conf` in the template — it only exists after a `certbot --nginx` run; inline TLS settings instead. Avoid `http2 on;` (needs nginx ≥1.25.1; Ubuntu 24.04 ships 1.24).
- PHP-FPM socket differs per distro; the helper auto-detects and substitutes `__FPM_SOCKET__`.

**Alerting:** failures log `::1inme:: SSL ISSUANCE FAILED`; after `alert_after_attempts` consecutive failures, ops admins (`user.ops_alerts.receive`) get in-app + `system.health_alert` email, deduped by `ssl_alerted_at` cooldown, with a recovery notice on later success.

**Test recipe:** `Process::fake(['*' => Process::result(...)])` drives the whole state machine; Role/Permission models for the ops admin live in `App\Modules\Admin\Models`, not `App\Modules\User\Models`.
