#!/bin/sh
#
# One-time installer: point this clone's git hooks at the committed .githooks
# directory so the pre-push view guards run automatically.
#
# Usage (from anywhere in the repo):
#   sh .githooks/install.sh

ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || {
  echo "install.sh: not inside a git repo." >&2
  exit 1
}

git -C "$ROOT" config core.hooksPath .githooks
echo "Installed git hooks: core.hooksPath -> .githooks"
echo "The pre-push hook now runs 'check:view-guards' before every push."
