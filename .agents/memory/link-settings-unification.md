---
name: Link settings single-source-of-truth
description: Which link-setting fields are canonical on Link columns vs JSON, and which shared features are type-scoped, after the 8-type unification.
---

# Unified link settings (1inme)

**Rule — canonical stores:**
- SEO trio (title/description/image) + favicon + OG image live on **Link columns**
  (`seo_title`, `seo_description`, `seo_image`, `favicon`). The biolink JSON
  `settings.biolink.meta.*` / `og.image_url` / `favicon_url` are legacy mirrors and
  are stripped on save by `BiolinkBlockController::updatePageSettings`.
- Visibility tier is the `Link.visibility` column (shared with the REST API). There
  is **no** competing access-tier store. `settings.biolink.privacy.*` is *analytics
  privacy*, NOT an access tier — never merge the two.

**Why:** short links, the biolink editor, and the REST API all previously wrote
different stores for the "same" setting, so edits diverged per surface. Columns win
because the API and short-link controllers already used them.

**How to apply:** when reading any of these in a view, prefer the column with a JSON
fallback for un-backfilled rows. When writing, write the column and unset the JSON dupe.

**Type-scoped behaviors (not universal):**
- `enforceBiolinkVisibility` short-circuits (`return null`) for non-biolink-family
  types — so a visibility control only makes sense on the biolink editor; surfacing
  it on url/file/ics/vcf would be hollow.
- Deep-link / "open in app" (`settings.open_in_app`): url-type resolves the app from
  its destination URL and defaults ON; file-type is **opt-in** and resolves against
  the file's public URL (`FileLink::publicUrl()`) — usually a no-op since files rarely
  match a known app. It's UI/field parity, not a guaranteed file→app jump.
- Password gate is already uniform: `is_password_protected` → `common.link-password`
  in both `RedirectController::handle()` and the file-download path.

**Preview/interstitial concept:** `Link::interstitialMode()` returns splash|preview|none
(splash wins). `previewPageEnabled()` reads `settings.show_preview_page` for url/ics/vcf
and `FileLink.show_download_page` for file. Shared toggle partial:
`resources/views/user/links/partials/preview-toggle.blade.php` (adopted by short-link +
vcf forms; ics keeps its `ics-tile` themed variant intentionally).
