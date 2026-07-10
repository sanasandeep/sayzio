# Objective
Conduct a production-scope security scan across the Sayzio monorepo and report only concrete, exploitable vulnerabilities.

# Relevant information
- Production deployment is public (`sayzio.app` plus brand domains).
- Primary production backend is the Laravel app in `artifacts/1inme`.
- `artifacts/api-server` mainly provides preview support plus a contact inbox service; treat its Laravel proxy path as dev-only unless production reachability is proven.
- `artifacts/mockup-sandbox` is dev-only per assumptions and is out of scope unless a production path imports or serves it.
- Highest-risk public surfaces: unauthenticated routes in `routes/web.php`, public/mobile API routes in `routes/api.php`, `routes/webhooks.php`, file/media delivery, redirects, AI/chat ingress, and payment/share-token flows.

# Tasks

### T001: Public file/media and signed-link exposure
- **Blocked By**: []
- **Details**:
  - Review vault file serving, signed media/PDF routes, public report links, and any logic that turns private records public.
  - Files: `app/Modules/User/Controllers/UserFileController.php`, `app/Modules/Common/Controllers/SignedMediaController.php`, billing/public-report controllers.
  - Acceptance: Confirm or rule out data-exposure or IDOR issues with concrete evidence.

### T002: Public AI, webhook, and outbound-fetch surfaces
- **Blocked By**: []
- **Details**:
  - Review AI companion/site assistant/webhook ingest/mind ingestion and related remote fetch or parser execution paths for auth bypass, SSRF, unsafe command use, and unbounded public abuse.
  - Files: public AI controllers, `AiMindIngestor.php`, webhook controllers/routes.
  - Acceptance: Confirm or rule out exploitable public-ingress issues.

### T003: Auth, viewer-session, and account-boundary enforcement
- **Blocked By**: []
- **Details**:
  - Review OTP/social auth, viewer DM/community endpoints, optional-auth behavior, and any account-merging or identity-bridging logic for spoofing or authorization flaws.
  - Files: auth controllers, viewer/profile DM controllers, relevant middleware.
  - Acceptance: Confirm or rule out cross-account access or authentication bypass.

### T004: Admin and mobile-admin privilege boundaries
- **Blocked By**: []
- **Details**:
  - Review admin API/mobile-admin endpoints, impersonation, protected account handling, and permission checks.
  - Files: `AdminAccessController.php`, admin middleware/controllers, sensitive admin API controllers.
  - Acceptance: Confirm or rule out broken admin authorization or privilege escalation.

### T005: Payment, invoice, and checkout/share-token flows
- **Blocked By**: []
- **Details**:
  - Review invoice payment links, payment webhooks/returns, creator monetization public flows, and signed document URLs for tampering or unauthorized access.
  - Files: `WebhookController.php`, billing controllers/services, public monetization controllers.
  - Acceptance: Confirm or rule out exploitable payment-state or signed-link flaws.

### T006: API server production exposure calibration
- **Blocked By**: []
- **Details**:
  - Review `artifacts/api-server` to determine whether any findings are production-relevant; ignore preview-only behavior unless reachable in deployment.
  - Files: `src/app.ts`, `src/routes/*`, `src/middlewares/*`.
  - Acceptance: Either produce a production-relevant finding or explicitly treat the area as non-production for this scan.
