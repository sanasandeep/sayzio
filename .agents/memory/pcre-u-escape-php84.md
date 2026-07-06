---
name: PCRE \u escape crashes on PHP 8.4
description: Single-quoted regex using \u{XXXX} is invalid PCRE2 and crashes on the platform's PHP 8.4; use \x{XXXX}.
---

A single-quoted PHP regex containing `\u{00A0}` (or any `\u{...}`) is NOT valid
PCRE2 syntax and throws `preg_replace(): Compilation failed: PCRE2 does not
support \F, \L, \l, \N{name}, \U, or \u` on the platform's PHP 8.4 build. The
correct PCRE form for a Unicode codepoint is `\x{00A0}`.

**Why it hides:** in a DOUBLE-quoted PHP string, `"\u{00A0}"` is a PHP-level
unicode escape resolved to the literal char before it ever reaches PCRE, so it
"works". In a SINGLE-quoted string the `\u{...}` reaches PCRE verbatim and
fails. Grepping for `\u{` will surface both — only the single-quoted-regex ones
are bugs. It can also pass on older PCRE2 builds that enable ALT_BSUX, so it may
have shipped green on the 8.3 CI while crashing on 8.4.

**How to apply:** when a phone/text normalizer or any regex crashes only under
PHP 8.4 with the "does not support \u" message, switch `\\u{XXXX}` → `\x{XXXX}`.
Seen in ContactCandidateValidator::toE164 — it hard-crashed approval of any
phone-bearing lead (Leads review queue) before it could push to the CRM.

**Testing afterCommit CRM push:** PushLeadToCrmJob is dispatched with
`afterCommit()`, but `Bus::fake()` records the dispatch immediately (it does
not honour transaction deferral), so `Bus::assertDispatched` is reliable under
RefreshDatabase. To avoid the existing-contact's own `created` observer
polluting the fake, create the seed contact BEFORE connecting the CRM (then
`shouldQueue` is false and the observer is a no-op), and only `Bus::fake()`
right before the action under test. `leads.source_id` is a bigint — seed leads
with numeric source ids.
