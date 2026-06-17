---
name: Adding a token-charged AI feature (1inme)
description: How to add a new OpenAI-backed feature that bills the existing AI credit ledger.
---

To add a new AI feature on the existing token-based AI credit system, mirror
`ResumeTailorService` + `ResumeTailorController` (the canonical example) and remember:

- **Register the feature key** in `AiEngineSettings::FEATURES` (falls back to gpt-4o-mini);
  resolve the model via `AiEngineSettings::featureModel($key)`.
- **`OpenAiService::chat()` charges on a successful API call — BEFORE you parse/validate
  its output.** So any post-chat failure (bad JSON, empty result, apply error) leaves the
  user charged. Wrap parse→materialise in try/catch and `AiCreditService::refund($user,
  $result['credits_spent'], [...])` on failure, then rethrow. `OpenAiService::$credits` is
  protected — inject `AiCreditService` yourself rather than reaching through it.
- **Gate UI on `AiEngineSettings::isEnabled()`** — it is OFF by default in fresh/dev envs
  (no API key), so live generation can't be smoke-tested locally; verify wiring instead.
- Error mapping: `InsufficientAiCreditsException` → HTTP 402 (public `required`/`balance`),
  `RuntimeException` → 422.

**Why relative URLs are valid for biolink images (not links):** biolink image blocks store
root-relative URLs natively (uploader returns `/f/{id}/{filename}`, placeholders are
`/block-placeholders/...`). Image-URL validation must accept single-leading-slash paths;
destination-link validation must still require absolute http(s).

**Don't trust an LLM to use every supplied resource.** When a feature feeds user-supplied
links/images/files into a prompt, deterministically reconcile afterwards: collect every
string already present in the generated output (recursively) and append real blocks for any
supplied resource the model failed to reference, so nothing the user provided is silently dropped.
