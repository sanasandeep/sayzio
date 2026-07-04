---
name: Per-record override + shared margins must fall back field-by-field
description: Why a per-invoice letterhead image override without its own margins must inherit the parent record's margins, not collapse to 0.
---

When a "per-X override, else parent default" pattern spans multiple related fields (here: a letterhead *image* is overridable per invoice, but its margins/dimensions are separate columns that the override UI doesn't necessarily set), do not pick one whole record as the "margin source" based on which record owns the overridden field.

**Why:** `ClientInvoicePdfRenderer::resolveLetterhead()` originally did `$marginSource = $invoice->letterhead_path ? $invoice : $company;` — as soon as an invoice had its own letterhead image, ALL of its margin/width/height fields were read from the invoice, even though the invoice's own margin columns were still null (the create/edit forms only let you swap the image, not the margins). Null margins were then coerced to 0 via `?? 0`, silently discarding the company's configured safe area and letting invoice content collide with the letterhead artwork.

**How to apply:** resolve each field independently with its own null-coalescing fallback chain (`$invoice->field ?? $company?->field ?? default`), not a single record-level source. This generalizes to any "override wins, else inherit" feature where the override doesn't necessarily populate every related field.
