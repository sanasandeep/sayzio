---
name: Mobile source-driven test extraction helper
description: How lifted screen expressions are evaluated resiliently in artifacts/1inme-mobile/scripts tests
---
Mobile .mjs source-driven tests that lift real screen expressions must evaluate them via
`scripts/lib/extract.mjs` (`runExtractedCall` for an expression, `runExtractedStatements`
for statement blocks + a return expression), never `new Function` with hand-listed params.

**Why:** hand-listed free variables hard-crash the whole mobile-unit chain with a raw
ReferenceError the moment a screen gains a new variable. The helper runs code inside
`with(Proxy)` — unknown identifiers fall to scope → globalThis → null, with a one-time
actionable warning ("new variable X — extend the scope"). Returned functions/promises are
wrapped so lazily-invoked callbacks still warn; only ReferenceErrors are re-wrapped, so
deliberate throws (ApiError etc.) keep their shape for assertions.

**How to apply:** in new tests, pass a scope object of the vars you pin; lexical bindings
inside the lifted body correctly shadow the proxy. Strict/failing mode is a proposed
follow-up (env-var gate in extract.mjs).
