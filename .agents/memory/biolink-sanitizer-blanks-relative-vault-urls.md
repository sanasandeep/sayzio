---
name: Biolink sanitizer blanks relative vault URLs
description: sanitizeSettings/sanitizeUrl strips non-http(s) URLs, so relative /f/ vault paths in block settings persist as '' via template/AI-builder apply paths.
---

BiolinkBlockController::sanitizeUrl only accepts absolute http(s) URLs; the urlFields loop in sanitizeSettings runs on every block save AND on TemplateService::insertBlockTree (template apply + AI builder applyPageToLink). Any relative vault URL (`/f/{id}/{name}`) in image/avatar/cover fields is silently blanked to ''.

**Why:** AiBiolinkBuilderTest::test_generate_constrains_to_allowed_blocks_and_keeps_relative_image_urls fails on this today — verified pre-existing by swapping the unmodified HEAD service in and re-running (same failure). Not caused by the image-sourcing feature.

**How to apply:** FIXED July 2026 — sanitizeUrl now accepts single-leading-slash `/f/...` vault paths (rejects `//` anywhere, backslashes, whitespace, and non-http(s) schemes). Any other relative path is still blanked; features feeding non-vault relative URLs must emit absolute URLs (PublicStorageUrl::resolve).

Note: This also fixes the pre-existing test failure at HEAD where `AiBiolinkBuilderTest::test_generate_constrains_to_allowed_blocks_and_keeps_relative_image_urls` failed because image block URLs were being blanked. Verified as resolved with the sanitizer fix.
