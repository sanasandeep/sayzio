---
name: Client invoice & receipt PDF
description: Durable decisions behind downloadable branded PDFs for client invoices/receipts.
---

Client-invoice (kind='client') and receipt PDFs reuse the existing dompdf
pattern (no new dependency).

**Decision — serve PDFs over PUBLIC signed-URL routes, not the session-guarded
invoice route.**
**Why:** mobile opens PDFs in a session-less in-app browser, so the old
session+workspace-owner invoice PDF route 403'd from mobile. A signed-URL HMAC
(the same auth model as the public pay link) lets the web button, the REST API,
and the mobile browser all share ONE route. API/links use temporary signed URLs;
keep web buttons temporary too (link-leak posture).
**How to apply:** any new "download this billing doc" surface should generate a
(temporary) signed URL to the shared route and validate `hasValidSignature()` +
the kind guard server-side — do not re-gate by session.

**Decision — branding resolves BillingCompany → merchant_snapshot → platform
merchant config; figures come straight from the persisted invoice.**
**Why:** invoices may be company-issued, snapshot-only (kanban drafts), or
platform-issued; the PDF must match the on-screen totals, which are whatever
InvoiceCalculator persisted. Re-deriving numbers risks drift.
**How to apply:** read line items/tax breakdown/totals off the invoice as-is;
logo must be inlined as a data URI (dompdf runs with remote fetching disabled).
