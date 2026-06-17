---
name: 1inme.com marketing-gateway CTA mapping
description: How auth/pay CTAs on the static 1inme.com marketing site map to the real 1in.me product app.
---

1inme.com (`artifacts/1inme-com`, React/Vite/wouter) is a static marketing gateway with NO auth or billing of its own. Every auth/pay CTA must redirect to the real product app at 1in.me, via the single source of truth `src/config.ts` (`LOGIN_URL`, `SIGNUP_URL` = LOGIN_URL, `PRICING_URL`). Never hardcode `https://1in.me/...` in a page/component — import from `@/config`.

CTA label → destination convention:
- Literal auth labels ("Log in" → LOGIN_URL; "Sign up free" / "Sign up" / "Register" → SIGNUP_URL).
- Generic hero conversion CTAs ("Get started free", "Start free", "Start posting free", "Get listed free") are signup-intent → SIGNUP_URL.
- The per-plan pricing-card buttons ("Get started" / "Upgrade now") and secondary "See pricing" / "Compare all plans" are the actual pay CTAs → PRICING_URL.

**Why:** The task spec phrased it as "pricing/get-started/upgrade → pricing". Taken literally that would send hero "Get started free" buttons to pricing — but pages pair a primary "Get started free" with a secondary "See pricing", and routing both to PRICING_URL makes them redundant. So "get-started" in the spec means the pricing-page plan-selection buttons, not the generic hero signup CTA.

**How to apply:** When adding/editing a marketing page, decide by intent: account creation → SIGNUP_URL, choosing/paying for a plan → PRICING_URL. Marketing primitives (`Cta`/`MarketingHero`/`CTABand` in `src/components/marketing/marketing.tsx`) auto-render external `<a>` for `http(s)`/`#`/`mailto` hrefs and a wouter `<Link>` otherwise.

Verbatim mirror copy comes from the Laravel app: `artifacts/1inme/app/Modules/Common/Support/SitePagesContent.php` and `ComparisonContent.php`. Rich page content lives in `src/content/{features,faqs,compare,use-cases,ai-products}.ts`; reusable legal pages use `src/components/marketing/legal-page.tsx`.
