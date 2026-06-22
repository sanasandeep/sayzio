import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/Browser",
  // Page renders are slow over the distant RDS (a cold home/contact/pricing
  // render can take 30-45s), so the default 30s per-test budget spuriously
  // times out. Give specs real headroom; individual specs may still raise it.
  timeout: 90_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  retries: 0,
  reporter: "list",
  use: {
    baseURL: process.env.APP_URL || "http://localhost:80",
    // Navigations and actions inherit the same slow-environment headroom.
    navigationTimeout: 60_000,
    actionTimeout: 30_000,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "off",
  },
  projects: [
    { name: "chromium", use: { ...devices["Desktop Chrome"] } },
  ],
});
