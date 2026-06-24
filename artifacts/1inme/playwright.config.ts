import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/Browser",
  // The runner (tests/Browser/run-validation.sh) warms the shared app server
  // before Playwright starts, so the expensive one-time cold render (~30-45s
  // over a distant RDS) is already paid and per-spec navigations land warm
  // (~2-3s). The budgets below are therefore tightened from the old
  // cold-render-per-spec headroom so the whole suite finishes in a bounded time
  // while still leaving slack for an occasional cold worker or heavy page.
  timeout: 60_000,
  expect: { timeout: 10_000 },
  // Specs that log in serialize on the rate-limited demo-login route and assume
  // ordered execution, so the suite runs single-threaded (one worker, serial).
  fullyParallel: false,
  workers: 1,
  // One retry. This box runs the php-cli server, node, and headless Chromium
  // side by side, so a heavy editor spec can occasionally lose a CPU race and
  // miss a client-side wait (e.g. Alpine init) by a hair. A single retry against
  // the already-warm server absorbs that rare flake cheaply and keeps the
  // broader suite trustworthy as an unattended gate, without masking a real,
  // consistently-failing regression (which fails both attempts).
  retries: 1,
  reporter: "list",
  use: {
    baseURL: process.env.APP_URL || "http://localhost:80",
    // Warm renders are ~2-3s; 45s still absorbs a cold php-cli worker that the
    // warm-up loop's handful of requests didn't reach, or a heavy editor page.
    navigationTimeout: 45_000,
    actionTimeout: 30_000,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "off",
  },
  projects: [
    { name: "chromium", use: { ...devices["Desktop Chrome"] } },
  ],
});
