---
name: Invoice edit must recompute via applyEdits, not recalculate
description: Why the client-invoice update path uses InvoiceCalculator (applyEdits) and must carry per-line tax
---

Editing an invoice's line items must round-trip per-line tax and recompute totals through the shared calculator, not the legacy `recalculate` path.

**Why:** The API `updateInvoice` (BillingController) originally rebuilt line items with only label/amount/quantity and called `ClientInvoiceService::recalculate`, which preserves the OLD `tax_total_minor`. Any edit therefore silently dropped per-line tax and left stale tax totals. `createStandalone` never had this bug because it uses `applyEdits` (public, wraps `InvoiceCalculator`) which recomputes tax from the line items + discount.

**How to apply:**
- Edit paths accept per-line `tax_rate_bps` / `tax_name` / `tax_inclusive` / `catalog_item_id` and pass them into the item array, then call `$svc->applyEdits($invoice, ['line_items' => $items, 'discount_minor' => (int)$invoice->discount_minor])`. `applyEdits` resolves the billing company from `invoice->billing_company_id` when the 3rd arg is null.
- For any client to prefill an edit form, `showInvoice` per-line output and `transformInvoice` must expose the editable fields — per-line `tax_rate_bps`/`tax_inclusive`, plus top-level `notes_md` and `discount_minor` (these were missing from `transformInvoice`).
- Mobile edit flow: shared `components/InvoiceForm.tsx` is parameterized `mode: "create" | "edit"`; edit route is nested at `app/invoices/edit/[id].tsx` because `app/invoices/[id].tsx` is the detail screen. Billing company + currency are create-only (updateInvoice validation doesn't accept them).
