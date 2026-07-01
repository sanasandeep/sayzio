---
name: AI tool-name drift guard (admin blades)
description: Validation gate that keeps admin AI tool names spelled the same as the customer app (with the "AI " prefix).
---

# AI tool-name drift guard

Validation step `ai-tool-names` (script `scripts/src/check-ai-tool-names.ts`,
`pnpm --filter @workspace/scripts run check:ai-tool-names`) fails CI if an admin
blade renders a DISTINCTIVE multi-word AI tool name WITHOUT the required "AI "
prefix. Keeps the nine tool names in sync between the customer app and
back-office admin (see `knowledge-bases-display-rename.md`).

**Why multi-word phrases only:** bare single words (Personas, Companions, Coach,
Resume) are ordinary entity nouns used all over admin ("All Personas", "Disable
Companion", "Coach Defaults", the "Resume / Portfolio" link type) — guarding them
false-positives on correct copy. The guarded phrases (Knowledge Base(s), Ask
Coach, Voice Assistant, Marketing Strategist, Inbox Agent, Brand Kit, Persona
Generator) unambiguously name the tool, so a bare hit is real drift. The script
is the source of truth for the exact phrase list + exceptions (`-- --explain`).

**How it stays quiet on legit copy:** case-sensitive match; negative lookbehind
for "AI "; blade `{{-- --}}` + HTML `<!-- -->` comments are blanked (line/col
preserved) before scan; Knowledge Base(s) preceded by a digit or `}}` echo is a
count badge (allowed). Route names / `ask_coach.*` feature tags / CSS classes
are lowercase/hyphenated/underscored so they never match.

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

Registered as a validation gate via the validation skill (not editable in
`.replit` directly — those are tool-owned).
