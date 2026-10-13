# AI Growth Insights

Individual link analytics and folder reports offer an on-demand growth report, using the existing admin-configured Coach model and AI wallet. Users can enter purpose, audience, market/location and goal. Context drafts stay in this browser; generated reports and their context are saved in the database.

The card estimates coins before generation. Estimates include a conservative budget for enabled fallback models and bounded source snippets; actual usage is displayed after generation and capped at the approved estimate. Refreshing or reopening saved results never calls AI. Quotes expire after 10 minutes, are private to the requesting account/workspace, and reject changed analytics or models. Repeated generation requests reuse the result. Invalid or unsaved reports are refunded and require a fresh quote.

Reports contain overview, opportunities, a prioritized action plan, optional market research and separately estimated follow-up answers. They use the selected link/folder filters and retained date range. Only aggregate analytics, link titles/types, item totals and configured context are sent to AI. Customer details, raw IPs, membership details and form submissions are excluded. Engagement is unavailable when tracking is missing or traffic filters prevent a reliable comparison. Business outcomes are Sayzio-recorded outcomes for all traffic; offline/external outcomes are unknown. Detailed folder comparisons are bounded to 100 links while aggregate totals cover the full scope.

## Deployment and configuration

Run normal Laravel migrations: `ai_growth_reports` stores durable reports. Existing AI Engine Coach model configuration, provider keys and wallet pricing apply. Generation requires link edit permission; report viewing requires link view permission.

Optional market research reuses the admin-managed Google Programmable Search API key and engine ID in platform integration settings. It makes one web search only after the user approves generation, honors the existing daily user cap, and records search quota usage. Without configuration the research checkbox is disabled; analytics-only reports still work. Sources are search snippets with validated HTTP(S) links and retrieval dates, not verified full-page research or publication dates. No competitor benchmarks or forecasts are asserted without evidence.

## Validation

`AiGrowthInsightsTest` uses mocked AI and HTTP providers and the isolated test database. It checks privacy/scoping, free state/estimate calls, paid result idempotency, stale quote rejection, refunds, estimate ceilings, follow-ups, optional sourced search and both Blade surfaces. Browser checks cover estimate/generate interaction, safe text rendering, section navigation and mobile overflow.
