---
name: Adding a token-charged AI feature (1inme)
description: How to add a new OpenAI-backed feature that bills the coin wallet (AI usage is metered on the wallet, not a separate credit ledger).
---

**AI usage is charged directly from the coin wallet** — there is no separate "AI credits"
balance/packs/exchange anymore. `AiCreditService` is a thin adapter over `WalletService`
(only `getBalance`/`charge`/`refund`, each returning a `WalletTransaction`). Rates are stored
as floats (coins-per-1k-tokens); per-call cost = `ceil(float)` integer coins. AI calls must
never leave the wallet negative.

To add a new AI feature, mirror `ResumeTailorService` + `ResumeTailorController` and remember:

- **Register the feature key** in `AiEngineSettings::FEATURES` (falls back to gpt-4o-mini);
  resolve the model via `AiEngineSettings::featureModel($key)`.
- **`OpenAiService::chat()` charges on a successful API call — BEFORE you parse/validate
  its output.** Any post-chat failure (bad JSON, empty result, apply error) leaves the user
  charged. Wrap parse→materialise in try/catch and `AiCreditService::refund($user,
  $result['credits_spent'], [...])` on failure, then rethrow. `OpenAiService::$credits` is
  protected — inject `AiCreditService` yourself rather than reaching through it.
- **AI wallet txs are meta-tagged**, NOT given dedicated columns. `charge`/`refund` fold
  `feature`/`related_id`/`model`/`tokens_in`/`tokens_out` into `meta` (plus `ai => true`),
  merge any caller `meta` (e.g. `kind`/`mind_id`/`source_id`/`call_id`), and **omit null
  keys**. The wallet `type` column stays (`spend`/`refund`); the integer charge is
  `delta_coins` (negative for spend). Return-array key is still `credits_spent` (now coins).
  **Why:** usage analytics (`MindCreditUsageService`, `ResumeTailorService::recentRuns`) and
  tests query by `where('meta->ai', true)->where('meta->feature', …)` and group by
  `meta->mind_id`/`meta->kind`/`meta->related_id`. **How to apply:** any new AI feature must
  tag its charges this way or it won't surface in usage reports; and a null `related_id` MUST
  be omitted from meta (not stored as JSON null) or `whereNotNull('meta->related_id')` matches
  it incorrectly on Postgres.
- **Gate UI on `AiEngineSettings::isEnabled()`** — OFF by default in fresh/dev envs (no API
  key), so live generation can't be smoke-tested locally; verify wiring instead.
- Error mapping: `InsufficientAiCreditsException` (class name kept) → HTTP 402 (public
  `required`/`balance`), `RuntimeException` → 422. The 402 top-up CTA routes to the wallet.
- The `AiCreditTransaction`/`AiCreditBalance` models + tables are kept read-only (history +
  the `AiCreditTransaction::FEATURES` constant map); do not write to them.

**Why relative URLs are valid for biolink images (not links):** biolink image blocks store
root-relative URLs natively (uploader returns `/f/{id}/{filename}`, placeholders are
`/block-placeholders/...`). Image-URL validation must accept single-leading-slash paths;
destination-link validation must still require absolute http(s).

**Don't trust an LLM to use every supplied resource.** When a feature feeds user-supplied
links/images/files into a prompt, deterministically reconcile afterwards: collect every
string already present in the generated output (recursively) and append real blocks for any
supplied resource the model failed to reference, so nothing the user provided is silently dropped.
