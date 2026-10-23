# Granular staff access

This change adds 110 explicit permissions alongside existing admin permissions. It applies to back-office staff roles. User-app/workspace roles remain a separate authority model.

## How access works

- A permission grants only its named action. A view grant does not imply editing, export, financial confirmation or granting privileges.
- Account listing (`users.view`) and opening the restricted account workbench (`users.details`) are separate. A user can be represented by `Account #ID` without revealing their name, handle, email or phone.
- `users.identity.view`, `users.email.view` and `users.phone.view` independently reveal fields on reviewed account and financial screens. Leave **all three off** to hide customer identity there. Wallet balance and wallet history have separate grants. Transaction history on this workbench omits free-text reasons and balances-after.
- Identity search additionally requires `users.search_identity` and the visibility permission for the searched field. Exact account-number searches require `users.search`. Filters require `users.filter` plus visibility of the filtered field. Sorting by hidden fields is rejected. Staff can choose 15/30/50/100 rows, sort direction and registration-date bounds; filtering/sorting runs against the complete query before pagination.
- Other legacy admin pages may reveal identity in text, logs, previews or downloads. They fail closed unless **all three identity/contact visibility permissions** are granted. This intentionally makes partial-identity staff use the reviewed workbenches. The same restriction applies to linked staff using `/api/v1/admin/*`; context and the projected account list remain available. Public creator pages and a staff member's personal user dashboard are outside these back-office visibility controls.
- Navigation derives its action gates from registered route middleware for both desktop and mobile. It takes one permission snapshot per render. Hidden navigation does not replace server authorization.
- Super administrators retain unrestricted owner tools, including the existing account audit panels, and can open the new workbench from account pages. This is a role-based grant model, not explicit deny: to hide a field, remove the reveal grant from the staff role. Super administrators cannot be made restricted with checkboxes.

## Financial access

`/admin/finance` is the reviewed, identity-aware report entry point. Invoice totals, invoice records, completed coin-purchase totals, purchase records, internal margins, all-user ledger, exports, AI spending, subscription timelines, credit reviews, referral rewards and financial actions are separate controls.

Totals-only roles never query individual customer records for that report. Currency amounts remain grouped by currency. Invoice totals are face values grouped by invoice status, **not net cash collected**; creators' client invoices are excluded. Coin purchase totals come from completed allocation snapshots and are not reduced by subsequent refunds. AI reports show recorded coin movements, not inferred provider costs or fiat revenue.

Each of the 30 currently catalogued AI features has its own **totals** and **transaction records** permission. A strategy grant excludes separately catalogued chat/report child features. Uncatalogued dotted sub-actions remain with their nearest product. Keys are matched by literal prefixes, not wildcard patterns. Unknown feature/mode requests are rejected. New AI features fail closed until their permission definitions are installed by a migration. The broad `finance.ai.view` retains the existing all-feature report: do not grant it to a role intended to see just one feature. Profitability access also intentionally exposes the combined cost report, including its embedded CSV export data.

Read permission for the payment queue does not authorize confirming payments. Refund viewing, initiation and manual confirmation are separate. Subscription lifecycle reports include invoice/refund details, so they require their own permission **and** both invoice/refund read grants. Legacy ledger exports require both ledger-view and ledger-export permissions and full identity/contact visibility.

## Assignments and account actions

- Permanent plan changes use `users.assign_plan`. Temporary comp days additionally require `users.plan.comp`. Plan assignments include the plan's normal coin entitlements through the existing activation service; they are not manual wallet grants.
- Coin grants and deductions use different sign-sensitive permissions, enforced on both account and AI-usage adjustment routes. The old `users.credits` flag no longer bypasses them. Starting coins during account creation also require grant permission; choosing a non-default starting plan requires plan-assignment permission.
- Individual badge edits, bulk badges, bulk plans and bulk coin grants are distinct. Bulk assignments are capped at 500 account IDs.
- Link assignment uses `users.links.assign`, requires current-owner ID, link ID and a reason, verifies their relationship, locks the source link, and delegates to the existing complete ownership-graph transfer service. The target is the selected account's personal workspace. Protected source or destination accounts are excluded. The transfer and operator action are audited; both owners receive normal transfer notifications. Existing plan-cap handling and detach rules for sender-owned domains/folders remain in the transfer service. No production reassignment was performed during development.
- Enabling a user's own asset-transfer capability is separate from moving a link. User-role assignment, granting/revoking staff access, password recovery, suspension and account deletion remain independent powers. The explicit role-management/user-role grants are powerful; review them with care.
- Delegated staff/role managers cannot assign super-admin or admin roles containing permissions they do not hold, including overwriting or revoking a superior admin's role. The reserved `super-admin` slug cannot be created through the role form. Admin-role writes require `guard=admin`; admin role listings do not expose user-app roles as editable staff roles.

## Rollout

Run the included additive migration through the normal deploy process. For each newly created permission, it copies grants from the documented old capability onto existing **admin-guard roles** so currently authorized operators generally retain their prior powers. It does not invent roles. Link reassignment is a new authority and is not automatically attached. Newly created custom roles start with only their chosen permissions. Re-running the migration does not restore permissions removed after rollout. No rollback deletes live role decisions.

The legacy coarse checkboxes remain available for capabilities still using them; they no longer imply the split controls at runtime. To narrow an existing role, remove its new field/financial/action grants individually and remove any broad report or user-app bypass grants that conflict with the intended role. Identity-bearing legacy pages now additionally require visibility permissions, even when a role previously had only a write/settings permission. Review custom roles after migration. No live role assignments are changed by this local development task.

### Example role configurations

| Intended role | Suggested grants |
|---|---|
| Anonymous support | `users.view`, `users.details`, `users.search`; only required actions such as `users.suspend` or `users.assign_plan`. No identity/contact/wallet or finance grants. |
| Totals-only finance | `finance.invoices.totals`, `finance.coins.totals`; omit records, internal margins and all mutation grants. |
| Single-feature AI analyst | For example `finance.ai.card_scan.totals`; optionally `finance.ai.card_scan.records`. Omit `finance.ai.view`, profitability and other feature grants. |
| Purchase auditor with hidden buyers | `finance.coins.view` or `finance.invoices.view`; omit the three identity/contact grants and margin permission unless required. |
| Coin-grant operator | `users.view`, `users.details`, `users.search`, `users.coins.grant`. No deduction, wallet-history or bulk permission unless required. |

These are configuration examples, not auto-created roles. UI descriptions specify dependencies; selecting a parent checkbox does not silently select related powers.

## Permission reference

| Group | Permission | Action / disclosure |
|---|---|---|
| User visibility | `users.details` | Open account details. Open the account workbench; does not reveal identity, contact data or finances. |
| User visibility | `users.identity.view` | Reveal name and handle. Without this, reviewed account screens use only the internal account number. Unreviewed identity-bearing screens are blocked. |
| User visibility | `users.email.view` | Reveal email addresses. Independent of name and phone visibility. |
| User visibility | `users.phone.view` | Reveal phone numbers. Independent of name and email visibility. |
| User visibility | `users.activity.view` | View login and account activity. Login dates, timezone and the separate activity log. Requires identity visibility for legacy audit screens. |
| User search | `users.search` | Search by account number. Search an exact account ID. Does not grant identity lookup. |
| User search | `users.search_identity` | Search names, emails, handles and phones. Requires permission to reveal each searched field; hidden fields cannot be queried. |
| User search | `users.filter` | Filter accounts. Filter by status, plan, badge and joined date; does not grant financial filters. |
| User display | `users.plan.view` | View assigned plan. Display the current plan and expiry. Does not permit a plan change. |
| User display | `users.status.view` | View account status. Display active/suspended state. |
| User display | `users.joined.view` | View registration date. Display account creation date. |
| User display | `users.badges.view` | View account badges. Display assigned badges. |
| User display | `users.wallet.view` | View coin balance. Balance only. Transaction history is separate. |
| User display | `users.wallet.history` | View coin transaction history. Show transaction dates, types and amounts. Free-text reasons are omitted from the account workbench. |
| Individual assignments | `users.coins.grant` | Grant coins to one user. Credit a wallet. Does not permit debits or bulk grants. |
| Individual assignments | `users.coins.deduct` | Deduct coins from one user. Debit a wallet. Does not permit grants. |
| Individual assignments | `users.plan.comp` | Grant a temporary complimentary plan. Allow comp_days on the dedicated plan assignment action. Also requires Assign / Comp Plans. |
| Individual assignments | `users.badges.assign` | Assign or remove account badges. Does not grant profile editing or bulk badge changes. |
| Individual assignments | `users.badges.bulk` | Assign or remove badges in bulk. Separate from individual badges and bulk coins/plans. |
| Individual assignments | `users.transfer.manage` | Enable user asset-transfer capability. Allow a user to transfer their own assets. Does not move any asset itself. |
| Individual assignments | `users.links.assign` | Reassign a link to a user. Move a link and its ownership graph to the destination personal workspace; audit and notify both owners. |
| Account management | `users.profile.edit` | Edit user profile. Change the user name. Does not grant status, plan or password changes. |
| Account management | `users.password.set` | Set a user password. Independent recovery power. Requires full identity/contact visibility on legacy forms. |
| Account management | `users.audit.view` | Read user role audit history. Read role audit pages; requires full identity/contact visibility. |
| Account management | `users.audit.export` | Export user role audit history. Download role audit records; separate from reading. |
| Link management | `links.view` | View links. Read link content and owner details; requires full identity/contact visibility. |
| Link management | `links.toggle` | Enable or disable links. Change one link status. |
| Link management | `links.delete` | Delete links. Delete one link. Separate from staff deletion. |
| Link management | `links.bulk` | Bulk link operations. Also requires the action-specific toggle or delete permission. |
| Financial summaries | `finance.invoices.totals` | View invoice totals. Currency-separated totals and counts; no invoice records or customer identities. |
| Financial summaries | `finance.coins.totals` | View coin purchase totals. Currency-separated completed purchase totals; no buyer records. |
| Financial summaries | `finance.profit.view` | View profitability and provider costs. Existing monetization report, including embedded CSV data. Does not grant other financial pages. |
| Financial records | `finance.invoices.view` | View invoice records. Invoice number, account ID, kind, status and amount; identity fields are independently controlled. |
| Financial records | `finance.coins.view` | View coin purchase records. Purchase rows on the finance workbench. Internal allocation report also requires margin permission and full identity visibility. |
| Financial records | `finance.margin.view` | View internal purchase allocation. API budget and margin breakdown, separately from purchase amounts. |
| Financial records | `finance.ledger.view` | View global coin ledger. Global transaction statement. Requires full identity/contact visibility on the legacy report. |
| Financial records | `finance.ledger.export` | Export global coin ledger. Download coin ledger CSV. Requires ledger view and full identity/contact visibility. |
| Financial records | `finance.ai.view` | View AI spending details. Cross-user and individual AI usage reports. Requires full identity/contact visibility on legacy reports. |
| Financial actions | `finance.payments.view` | View pending payment queue. Read payment references and review attempts; requires full identity/contact visibility. |
| Financial actions | `finance.payments.confirm` | Confirm manual payments. Mark an invoice paid; does not grant refunds. |
| Financial actions | `finance.refunds.view` | View invoice refund details. Read the existing refund page; requires full identity/contact visibility. |
| Financial actions | `finance.refunds.create` | Initiate refunds. Separate from manual refund confirmation. |
| Financial actions | `finance.refunds.confirm` | Confirm manual refunds. Confirm an existing refund; separate from initiation. |
| Financial actions | `finance.subscriptions.view` | View subscription lifecycle. Read subscription timeline, invoices, credit notes and refunds. Requires invoice and refund read permissions. |
| Financial actions | `finance.credit_reviews.view` | View credit review queue. Read unused subscription time awaiting review. |
| Financial actions | `finance.credit_reviews.approve` | Approve subscription time credits. Extend subscription time through the review queue. |
| Financial actions | `finance.credit_reviews.dismiss` | Dismiss subscription credit reviews. Close a review without granting subscription time. |
| Financial actions | `finance.referrals.view` | View referral rewards. Read referral conversion totals and individual reward details. |
| Financial actions | `finance.referrals.manage` | Enable or disable referral rewards. Change program availability. Separate from viewing rewards. |
| Account management | `users.activity.export` | Export account activity. Download the activity log. Requires full identity/contact visibility. |
| AI feature finances | `finance.ai.mind.totals` | View totals · Notes → Action Plan. Only mind. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.mind.records` | View transaction records · Notes → Action Plan. Only mind. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.planner_review.totals` | View totals · AI Planner reviews. Only planner_review. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.planner_review.records` | View transaction records · AI Planner reviews. Only planner_review. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.persona.totals` | View totals · Persona Generator. Only persona. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.persona.records` | View transaction records · Persona Generator. Only persona. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.companion.totals` | View totals · AI Chat. Only companion. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.companion.records` | View transaction records · AI Chat. Only companion. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.ask_coach.totals` | View totals · Account Assistant. Only ask_coach. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.ask_coach.records` | View transaction records · Account Assistant. Only ask_coach. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.voice_stt.totals` | View totals · Voice — Transcription. Only voice_stt. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.voice_stt.records` | View transaction records · Voice — Transcription. Only voice_stt. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.voice_llm.totals` | View totals · Voice — Reasoning. Only voice_llm. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.voice_llm.records` | View transaction records · Voice — Reasoning. Only voice_llm. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.voice_tts.totals` | View totals · Voice — Speech. Only voice_tts. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.voice_tts.records` | View transaction records · Voice — Speech. Only voice_tts. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.card_scan.totals` | View totals · Card / Brochure Scan. Only card_scan. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.card_scan.records` | View transaction records · Card / Brochure Scan. Only card_scan. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.resume_import.totals` | View totals · Resume — Import. Only resume_import. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.resume_import.records` | View transaction records · Resume — Import. Only resume_import. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.resume_tailor.totals` | View totals · Resume — Tailor to Job. Only resume_tailor. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.resume_tailor.records` | View transaction records · Resume — Tailor to Job. Only resume_tailor. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.inbox_agent.totals` | View totals · Inbox Agent. Only inbox_agent. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.inbox_agent.records` | View transaction records · Inbox Agent. Only inbox_agent. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.brand_kit.totals` | View totals · Brand Kit. Only brand_kit. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.brand_kit.records` | View transaction records · Brand Kit. Only brand_kit. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.brand_asset.totals` | View totals · Brand Kit — Visual Asset. Only brand_asset. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.brand_asset.records` | View transaction records · Brand Kit — Visual Asset. Only brand_asset. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.qr_art.totals` | View totals · QR — AI Art. Only qr_art. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.qr_art.records` | View transaction records · QR — AI Art. Only qr_art. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.marketing_strategist.totals` | View totals · Marketing Studio · strategy. Only marketing_strategist. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.marketing_strategist.records` | View transaction records · Marketing Studio · strategy. Only marketing_strategist. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.marketing_strategist.chat.totals` | View totals · Marketing Strategist — Chat. Only marketing_strategist.chat. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.marketing_strategist.chat.records` | View transaction records · Marketing Strategist — Chat. Only marketing_strategist.chat. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.marketing_strategist.report.totals` | View totals · Marketing Strategist — Report. Only marketing_strategist.report. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.marketing_strategist.report.records` | View transaction records · Marketing Strategist — Report. Only marketing_strategist.report. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.ai_staff_billing.totals` | View totals · AI Staff — Billing. Only ai_staff_billing. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.ai_staff_billing.records` | View transaction records · AI Staff — Billing. Only ai_staff_billing. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.ai_staff_contacts.totals` | View totals · AI Staff — Contacts. Only ai_staff_contacts. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.ai_staff_contacts.records` | View transaction records · AI Staff — Contacts. Only ai_staff_contacts. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.ai_staff_general.totals` | View totals · AI Staff — General Assistant. Only ai_staff_general. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.ai_staff_general.records` | View transaction records · AI Staff — General Assistant. Only ai_staff_general. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.competitor_teardown.totals` | View totals · Competitor Biolink Teardown. Only competitor_teardown. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.competitor_teardown.records` | View transaction records · Competitor Biolink Teardown. Only competitor_teardown. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.biolink_builder.totals` | View totals · AI Link in Bio Builder. Only biolink_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.biolink_builder.records` | View transaction records · AI Link in Bio Builder. Only biolink_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.slides_builder.totals` | View totals · AI Slides Builder. Only slides_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.slides_builder.records` | View transaction records · AI Slides Builder. Only slides_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.restaurant_menu_builder.totals` | View totals · AI Restaurant Menu Builder. Only restaurant_menu_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.restaurant_menu_builder.records` | View transaction records · AI Restaurant Menu Builder. Only restaurant_menu_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.store_menu_builder.totals` | View totals · AI Store Builder. Only store_menu_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.store_menu_builder.records` | View transaction records · AI Store Builder. Only store_menu_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.service_booking_builder.totals` | View totals · AI Service Booking Builder. Only service_booking_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.service_booking_builder.records` | View transaction records · AI Service Booking Builder. Only service_booking_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.resume_builder.totals` | View totals · AI Resume Builder. Only resume_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.resume_builder.records` | View transaction records · AI Resume Builder. Only resume_builder. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.audience_type_estimation.totals` | View totals · AI Audience Type Estimation. Only audience_type_estimation. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.audience_type_estimation.records` | View transaction records · AI Audience Type Estimation. Only audience_type_estimation. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.brand_studio.totals` | View totals · Marketing Studio · material creation. Only brand_studio. Totals and records are independent. Does not reveal identity or unlock other AI features. |
| AI feature finances | `finance.ai.brand_studio.records` | View transaction records · Marketing Studio · material creation. Only brand_studio. Totals and records are independent. Does not reveal identity or unlock other AI features. |

## Validation and limits

Isolated SQLite tests cover field projection, forbidden searches/filters/sorts, separate financial reads/writes/exports, currency grouping, margin omission, per-feature SQL scope, one-time migration backfill, delegation boundaries and rendering a restricted workbench. They create an in-memory schema and never run migrations against the shared development/production database. Local browser previews use synthetic data; no financial action, password change, staff grant, notification or asset transfer is submitted against production. The production PostgreSQL suite and end-to-end transfer/payment integration checks remain CI validation; local success is not a certification of every admin workflow.

## Role editing, copies and presets

The role list provides View, Edit and Copy / duplicate. Management actions require `roles.manage` and the ability to delegate every permission in the source role. The duplicate GET route only prepares a new form; saving creates a separate admin role with a unique slug. Staff assignments are never copied. Existing delegation checks run again when saving.

Super Admin is displayed as unrestricted, rather than showing its historical permission-pivot count. Its editor only changes name and description. Copying it snapshots all currently registered permissions into an ordinary role, with a visible warning: it has no system bypass and does not inherit future capabilities.

Six standard presets are available in both create and edit: Anonymous support, Customer support, Finance overview, Purchase auditor, Coin operator and Plan operator. Presets replace the entire draft selection only after pressing the explicit apply button. They remain unavailable if the permission catalog is incomplete or the actor cannot delegate any required permission. They never create roles or change staff assignments automatically.

| Label | Meaning |
| --- | --- |
| Level 1 · Basic access | Account lookup, basic display and aggregate totals |
| Level 2 · Sensitive data | Identity, financial records, history and exports |
| Level 3 · Changes | Mutations and capabilities without a lower-risk classification |
| Critical · Security access | Role/staff administration, credentials, impersonation, account deletion, security settings, legacy user-edit bundle and user-app override capabilities |

These labels are review aids, not hierarchical grants. Each capability remains independent. Search and risk filters affect visibility only. Category selection and clearing affect visible matches only; hidden selections stay intact. Critical permissions are skipped by bulk selection unless the separate opt-in is enabled. Individual Critical checkboxes remain available, and a live warning counts all selected Critical permissions, including hidden ones. Colors have text labels and light/dark variants.
