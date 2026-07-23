---
name: Biolink sanitizer blanks relative vault URLs
description: sanitizeSettings/sanitizeUrl strips non-http(s) URLs, so relative /f/ vault paths in block settings persist as '' via template/AI-builder apply paths.
---

BiolinkBlockController::sanitizeUrl only accepts absolute http(s) URLs; the urlFields loop in sanitizeSettings runs on every block save AND on TemplateService::insertBlockTree (template apply + AI builder applyPageToLink). Any relative vault URL (`/f/{id}/{name}`) in image/avatar/cover fields is silently blanked to ''.

**Why:** AiBiolinkBuilderTest::test_generate_constrains_to_allowed_blocks_and_keeps_relative_image_urls fails on this today — verified pre-existing by swapping the unmodified HEAD service in and re-running (same failure). Not caused by the image-sourcing feature.

**How to apply:** if a feature feeds vault-relative image URLs into biolink block settings, either emit absolute URLs (PublicStorageUrl::resolve) or fix sanitizeUrl to allow single-leading-slash `/f/` paths (reject `//` and unsafe schemes). Follow-up filed to fix the sanitizer.
