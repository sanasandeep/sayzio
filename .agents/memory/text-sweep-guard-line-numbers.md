---
name: Bulk copy sweeps driven by guard output
description: Pitfalls when auto-rewriting copy from a drift-guard's file:line report
---
Rule: if a scanner blanks spans (comments/placeholders) before matching, every replacement MUST preserve newlines, or reported line numbers drift and any fixer keyed on them edits the wrong lines.
**Why:** the em-dash guard's placeholder-blanking collapsed a multi-line ternary into one line; a bulk fixer using its file:line output silently missed the real offenders a few lines down.
**How to apply:** in blanking helpers use `m.replace(/[^\n]/g, " ")`, never `" ".repeat(m.length)`; keep `\s*` out of cross-line alternatives (`[^:\n]`, `[ \t]`). After a bulk sweep, re-run the guard fresh rather than trusting the pre-sweep report, and check for stray " - " artifacts where a dash sat at a line break.
