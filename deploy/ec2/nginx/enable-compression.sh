#!/usr/bin/env bash
#
# Turn on gzip for sayzio.app. Run once, on the EC2 box, as a user with sudo.
#
#   bash enable-compression.sh
#
# WHY THIS SCRIPT EXISTS, AND WHY IT IS NOT PART OF THE DEPLOY.
#
# Measured on the live site 2026-09-11, nothing the server sends is
# compressed -- not the HTML, and not the static assets either. Every
# response came back with encoded size identical to decoded size:
#
#     homepage HTML          803 KB
#     app-*.css              309 KB
#     all.min.css            100 KB
#     marketing-anim.css      64 KB
#     alpine.js + app.js      88 KB
#
# About 1.4 MB on the wire for a first visit, where gzip would make it
# roughly 250 KB. Most of that is text and compresses ten to one. On a phone
# on mobile data -- most of this audience -- it is the difference between a
# page that arrives and a page that gets abandoned, and page speed is a
# ranking factor on top of that.
#
# `deploy-ec2.yml` cannot do this. It grants the deploy user NOPASSWD sudo
# for exactly three binaries -- systemctl, and the two nginx paths -- so it
# can reload nginx but cannot write a file into /etc. Automating this would
# mean widening that sudo rule, which is a bigger change to the server's
# security posture than the thing it automates. So: one manual run.
#
# WHAT IT DOES, AND WHAT IT DELIBERATELY DOES NOT DO.
#
# It adds ONE new file at /etc/nginx/conf.d/sayzio-compression.conf holding
# only http-level gzip directives. It does not touch the vhost, the
# server_name, the PHP-FPM socket, or any existing file. The worst case if
# something is wrong is that `nginx -t` fails and the script stops without
# reloading -- the running config is never replaced.
#
# This is on purpose. deploy/ec2/nginx/sayzio.conf also carries these
# directives, but installing that whole file over the live vhost would
# replace a working, hand-edited config with a template whose server_name is
# still the literal string "yourdomain.com". Adding a snippet is the smaller
# blast radius.
#
# /etc/nginx/conf.d/*.conf is included at http level on both Ubuntu and
# Amazon Linux 2023, so this works on either without knowing which is which.
#
# Safe to re-run: it overwrites its own file and nothing else.

set -euo pipefail

SNIPPET=/etc/nginx/conf.d/sayzio-compression.conf

echo "Writing ${SNIPPET}..."

sudo tee "${SNIPPET}" >/dev/null <<'NGINX'
# Managed by deploy/ec2/nginx/enable-compression.sh — safe to re-run.
#
# http-level gzip. Applies to every server block, so the static assets
# nginx serves straight off disk (/build/*.css, /build/*.js, fonts) are
# compressed as well as the PHP responses.

gzip              on;
gzip_comp_level   5;
gzip_min_length   256;
gzip_vary         on;
gzip_proxied      any;

# text/html is ALWAYS compressed by nginx and must not be listed here --
# listing it is a duplicate at best and a config warning at worst.
gzip_types
    text/plain
    text/css
    text/xml
    text/javascript
    application/javascript
    application/json
    application/xml
    application/rss+xml
    application/manifest+json
    image/svg+xml
    font/woff
    font/woff2;
NGINX

echo "Testing nginx config..."
if ! sudo nginx -t; then
    echo
    echo "nginx -t FAILED. Nothing has been reloaded, so the site is still"
    echo "running the config it was running before this script started."
    echo "Removing the snippet and leaving the server untouched:"
    sudo rm -f "${SNIPPET}"
    sudo nginx -t
    exit 1
fi

echo "Reloading nginx (no downtime — existing connections finish)..."
sudo systemctl reload nginx

echo
echo "Done. Verify from any machine:"
echo
echo "    curl -sI -H 'Accept-Encoding: gzip' https://sayzio.app/ | grep -i content-encoding"
echo
echo "Expected: 'content-encoding: gzip'. No output means it did not take"
echo "effect — check that this file is inside the http block:"
echo
echo "    sudo nginx -T | grep -n 'sayzio-compression'"
echo
