---
name: iOS horizontal pan vs body overflow-x hidden
description: Why dark-mode-only decorative glows made the mobile dashboard pan sideways on iPhone, and the html overflow-x clip fix.
---

# iOS ignores `body { overflow-x: hidden }` for viewport panning

Decorative absolutely-positioned elements that bleed past the right edge
(dark-mode aurora `::before/::after` glows with negative `right`, tile corner
orbs at `right: -40px`) make the whole page horizontally pannable on iOS
Safari/WKWebView even though `body { overflow-x: hidden }` is set — desktop
Chromium shows scrollWidth == viewport, so the bug does NOT reproduce in a
local Playwright probe; trust the device report.

**Why:** iOS applies viewport panning at the `html`/viewport level; `hidden` on
body only affects body's own scroll box.

**How to apply:** `html { overflow-x: clip; }` (clip, not hidden — clip never
creates a scroll container so it can't break `position: sticky`). Also clip the
decorated container itself (`overflow-x: clip` on the stage) as belt and
braces. Mode-specific symptom = look for decorations hidden in the other mode
(`html.light-mode ... { display: none }`).
