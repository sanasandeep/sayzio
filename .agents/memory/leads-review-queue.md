---
name: Leads review queue
description: Aggregates captured people across 8 capture surfaces (RSVPs, form submissions, subscribers, store/restaurant orders, service bookings, reviews, event-interest) into a reviewable pending queue that can approve into Contacts or dismiss.
---

The `leads` table is deliberately sparse/state-only: a row is written ONLY when a lead is approved or dismissed (keyed by `source_type` + `source_id`). "Pending" is computed live — an item is pending as long as no `Lead` row exists for its key. Nothing on the 8 source tables themselves is ever mutated.

`LeadAggregator` re-queries all 8 source tables on every request (no caching/denormalized index) and filters out already-handled ids in PHP. This is fine at demo scale but will not scale to large source tables — see the tech-debt follow-up filed for this.

**Cap semantics:** `LeadApprover::approve()` only enforces the plan's contact cap on the *create-new-contact* path. Deduping into an *existing* contact never counts against the cap, mirroring the manual "Add contact" flow's own dedup rule (`ContactCandidateValidator`).

**Scoping:** Subscriber and FormSubmission already carry real `workspace_id` (via `BelongsToWorkspace`), so those two sources are workspace-scoped. RSVP / StoreOrder / RestaurantOrder / ServiceBookingRequest / Review / EventInterest have no workspace concept anywhere in the codebase — every existing controller for those features scopes by `Link.user_id === owner_id`, not workspace, so the aggregator mirrors that same owner-level scoping rather than inventing a stricter rule the rest of the app doesn't enforce.

**Dedup-merge enrichment:** when a lead merges into an existing contact, only add an email/phone if it's not already present by *normalized value* — comparing against the whole collection, not just "contact currently has zero of this field type". The latter silently drops a second distinct email/phone on merge.

**Source provenance:** `contacts.sources` (JSON array) already has a live convention — `CrmSyncService` tags contacts with `crm:<provider>`. New capture flows should follow the same pattern (e.g. `lead:<source_type>`) rather than inventing a separate field; append-if-missing on both create and merge.

**Why this needed a pre-existing bug fix:** approving a lead calls `Contact::create()`, which triggers a model observer that dispatches `PushLeadToCrmJob` — a job that redeclared a trait property incompatibly and fatal-crashed on class load under PHP 8.4 (see [php84-trait-property-redeclaration.md](php84-trait-property-redeclaration.md)). This was already broken for ALL contact/subscriber/form capture paths, not something introduced by Leads, but it blocked Leads' own approve action until fixed.

**Merge path CRM sync parity:** the `created` observer only fires on the *new-contact* branch, so merging a lead into an existing contact used to enrich it (add email/phone) WITHOUT any CRM push — creators saw brand-new leads sync but not merged/enriched ones. The loop-safe push logic (skip contacts carrying a `crm:` source so CRM-imported records aren't echoed back; cheap "is a CRM connected" check lives inside `PushLeadToCrmJob::forContact`) is extracted into `Contact::queueCrmPush()`, called by BOTH the `created` hook and the merge branch. Push on merge only when `fillMissingFields()` actually added a new email/phone (it now returns a bool), so re-approving the same lead is a no-op and no duplicate/conflicting CRM records are created (the connectors upsert by email/phone anyway). **Why:** keeps the two capture paths on one code path so they can't drift, and avoids redundant CRM writes on idempotent re-approvals.
