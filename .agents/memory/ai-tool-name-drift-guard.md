---
name: AI tool-name drift guard (all human-facing surfaces)
description: Validation gate that keeps AI tool names spelled the same (with the "AI " prefix) across admin, customer app, marketing site and mobile.
---

# AI tool-name drift guard

Validation step `ai-tool-names` (script `scripts/src/check-ai-tool-names.ts`,
`pnpm --filter @workspace/scripts run check:ai-tool-names`) fails CI if a
DISTINCTIVE multi-word AI tool name is rendered WITHOUT the required "AI "
prefix. Keeps the nine tool names in sync (see `knowledge-bases-display-rename.md`).

**Scope (SCAN_ROOTS, all human-facing surfaces):** every `*.blade.php` under
`artifacts/1inme/resources/views` (admin + user + common, vendor/ excluded),
`artifacts/1inme-com/src` `*.{ts,tsx}`, and mobile `app|components|constants|lib`
`*.{ts,tsx}`. Each root declares its comment syntax (`blade` vs `js`); the
script is the source of truth for exact phrases + exceptions (`-- --explain`).

**Why multi-word phrases only:** bare single words (Personas, Companions, Coach,
Resume) are ordinary entity nouns used all over admin ("All Personas", "Disable
Companion", "Coach Defaults", the "Resume / Portfolio" link type) — guarding them
false-positives on correct copy. The guarded phrases (Knowledge Base(s), Ask
Coach, Voice Assistant, Marketing Strategist, Inbox Agent, Brand Kit, Persona
Generator) unambiguously name the tool, so a bare hit is real drift. The script
is the source of truth for the exact phrase list + exceptions (`-- --explain`).

**How it stays quiet on legit copy:** case-sensitive match; negative lookbehind
for "AI " AND the "AI · X" kicker form; comments blanked before scan (blade
`{{-- --}}` + HTML `<!-- -->` for blade roots, plus C-style `/* */` and `//` for
all roots — `//` skipped when preceded by `:` so `https://` survives); a
quote-wrapped phrase immediately followed by `=>`/`:` is a map/object KEY whose
VALUE carries the prefix (allowed); Knowledge Base(s) preceded by a digit or `}}`
echo is a count badge (allowed). Route names / `ask_coach.*` feature tags / CSS
classes are lowercase/hyphenated/underscored so they never match; mobile
Marketing-Strategist alias "Performer Specialist" doesn't match either.

**Label-context pass (single words):** the multi-word scan deliberately skips bare
single words (Personas/Companions/Coach/Resume). A second pass (`scanLabelContexts`)
closes the copy-edit gap by flagging a WHOLE label that is exactly a bare tool name in
label contexts only: `@section('title'|'page-title')`, `nav-label`/`sidebar-tooltip`
spans (all admin blades), and `<h1..h6>` headings but ONLY in `AI_HEADING_DIRS`
(ai-personas, ai-companions, ask-coach, ai-minds — coach-defaults excluded on purpose).
Whole-label equality is what keeps "All Personas"/"Coach usage & quality"/"Coach
Defaults"/"AI Usage" from flagging. Add new single-word labels to `LABEL_TOOL_NAMES`.

**How to apply:** if a bare occurrence is genuinely intentional, add the file to
the script's `ALLOWLIST` with a reason — never weaken the regex.

**Regression test:** `scripts/src/check-ai-tool-names.test.ts` (vitest, run via
`pnpm --filter @workspace/scripts run test`, gate `scripts-tests`) pins BOTH scan
passes. `scanSource` exception logic: bare vs "AI "-prefixed, per-mode comment
blanking (blade `{{-- --}}` / HTML only in blade mode; C-style + `//` in both),
map/object keys, the "AI · X" kicker, count badges, and https:// not being read
as a comment. `scanLabelContexts`: drift cases (bare label in
section-title/nav-label/sidebar-tooltip/AI-view heading) AND the must-not-flag
false-positives (All Personas, Coach usage & quality, Coach Defaults, AI Usage,
comments, concatenated/dynamic section titles, headings outside AI dirs). The
test imports `scanSource`/`scanLabelContexts`/`blankComments` with a `.js`
specifier (bundler moduleResolution → `.ts` source) so `tsc --noEmit` stays
happy. Refactor either regex ⇒ update this spec in lockstep.

Registered as a validation gate via the validation skill (not editable in
`.replit` directly — those are tool-owned).
