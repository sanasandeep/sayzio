---
name: Variable form pricing (per-field)
description: How paid forms compute a per-field/per-option order total across builder, public form, gateway, and submission display.
---

# Variable form pricing

Paid forms support two modes via `settings.payment.mode`: `fixed` (one flat price) and `per_field` (base fee + priced fields/options).

**Canonical compute** lives in `Form` (app/Modules/User/Models/Form.php):
- `PRICED_FIELD_TYPES = number, select, radio, checkbox, consent` — the only priceable types.
- Per-field price stored as `price_cents` (number unit price, consent flat add-on); per-option as `option_prices` map keyed by the **option label string** → cents.
- `computeAmountCents($data)` and `priceLineItems($data)` are the authoritative server-side total + breakdown. Line-item shape: `{field,label,detail,amount_cents}`, base row keyed `field='__base__'`.
- number = unit×qty; consent = flat when truthy; select/radio = option_prices[value]; checkbox = sum of selected option_prices.

**Why all amounts are cents end-to-end:** the gateway charges integer cents. The builder editor is the ONLY surface that works in dollars — it normalizes `price_cents/100` and `option_prices` cents→dollars in Alpine `init()`, and converts back to cents (Math.round) in hidden inputs on save. Never store dollars.

**How to apply (lockstep surfaces — change one, change all):**
1. `FormController::updateBuilder` whitelists `price_cents`+`option_prices` for priced types — NOT plan-gated (preserves prices on downgrade); gating is only on the builder UI (`$canPrice` = `paid_forms` feature).
2. `common/form-field.blade.php` emits price tags + data attrs (`data-price-unit`, `data-price`, `data-price-addon`, `select[data-priced]`) only when caller passes `showPrices`.
3. `common/form.blade.php` shows live order total via vanilla JS keyed on `form.form-paid-pricing` + `#orderTotal`; it mirrors the PHP math (display only — server recomputes on submit).
4. `publicSubmit` recomputes amount + line_items server-side and stores them; `submission-show.blade.php` renders the breakdown.

**Gotcha hit:** `@endif@if` concatenated with no separator silently leaves the 2nd directive literal → orphan endif → compiled-PHP parse error. Always separate (see blade-endif-if-gotcha.md). Verify views by compiling with a standalone `BladeCompiler` (register custom `@canInWorkspace` if-directive to avoid false positives) — full app bootstrap hangs over distant RDS.

**Mobile = WebView, no native form renderer.** Complex forms (`form`/`contact_form`/`quiz`/`review`) on mobile open an in-app WebView, not a native RN form. So responder-side pricing (price tags + live total + per-field payment) is delivered by the SAME web form inside the WebView — don't rebuild it natively. The `form` block's settings only store `form_id` (not slug); `BiolinkController::decorateFormBlock()` resolves `form_id → {public_url(/f/{slug}), is_paid, payment{mode,amount_cents,currency,label}}` and injects it on `form` blocks across all 3 serialization paths (live / A/B-snapshot / slides). Mobile then opens `/f/{slug}` directly (priced standalone page, `showFieldPrices = isPaid && mode===per_field`) and shows a "Paid form · …" hint. Owner submission breakdown is native: API `FormController::submissions` must include `line_items` (it serializes payment fields but originally omitted them); mobile `app/forms/[id].tsx` renders the breakdown. **Code-review will REJECT a submissions-only diff as missing the responder requirement** — wire the responder WebView path too.
