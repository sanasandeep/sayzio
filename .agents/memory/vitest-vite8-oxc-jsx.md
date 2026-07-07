---
name: vitest vite8 oxc JSX transform
description: Why .tsx vitest suites fail to parse JSX under vite 8/oxc and the oxc.jsx config fix
---

# vitest + vite 8 (oxc) drops JSX transform in .tsx tests

`.tsx` vitest suites fail with `vite:import-analysis` "Failed to parse source
for import analysis because the content contains invalid JS syntax... make sure
to not set jsx to preserve" while sibling `.ts` suites pass.

**Why:** vitest pulls in **vite 8** (rolldown/oxc) even when the app itself
builds on vite 7 (catalog pin). Under vite 8, `config.oxc` is populated by
default, and `@vitejs/plugin-react` (built for vite 7) sets `config.esbuild.jsx`.
When BOTH are set vite uses `oxc` and ignores esbuild — but the default `oxc` has
no JSX config, so `.tsx` JSX reaches import-analysis untransformed. The warning
"Both esbuild and oxc options were set. oxc options will be used and esbuild
options will be ignored" is the tell.

**How to apply:** in the standalone `vitest.config.ts`, set the oxc JSX transform
directly (do not just rely on `plugin-react`):

```ts
oxc: { jsx: { runtime: "automatic", importSource: "react" } }
```

The `oxc` key typechecks fine (vitest's bundled vite-8 types include it). Clearing
`node_modules/.vite` does NOT fix it — the error frame showing stale line numbers
is a red herring, the transform config is the real cause.
