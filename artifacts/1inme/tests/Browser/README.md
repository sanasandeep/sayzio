# Browser tests (Playwright)

End-to-end browser tests for the 1inme web artifact. These complement the
PHPUnit feature suite under `tests/Feature/` by validating runtime
JavaScript behavior (keyboard nav, touch gestures, network pings) that
the controller-level tests can't observe.

## Running

The Laravel app must be reachable at `APP_URL` (defaults to
`http://localhost:80`, the workspace's path-based proxy). Migrations
must be applied so the seed step in each spec can write fixtures.

```sh
# from artifacts/1inme/
pnpm install
pnpm exec playwright install chromium
pnpm test:e2e
```

Each spec is self-bootstrapping: it shells out to `php artisan tinker`
to seed the rows it needs (idempotent — re-running is a no-op once the
fixture exists), then drives a real browser against the public alias.

## Specs

- `slides-mode.spec.ts` — task #1059. Seeds a published 2-slide biolink
  at alias `e2e-slides-demo`, then in a real browser asserts both
  slides render, the active-slide class moves on `ArrowRight` /
  `ArrowLeft` and on a synthesized swipe-left gesture, and the inline
  `/sl/{alias}/view` tracker pings during navigation.
