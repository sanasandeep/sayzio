// Visual light-mode verification for the admin ak-* sweep (Task: make the
// remaining baselined admin pages readable in light mode).
//
// Boots an ephemeral `php artisan serve`, logs in as the demo admin (synthesized
// CSRF POST to /admin/demo-login — the demo button is not reliably rendered in
// fresh envs), then for each fixed page: adds html.light-mode, and asserts the
// ak-treated elements render DARK text (effective luminance below threshold),
// while solid-surface buttons keep white text.
import { spawn, execSync } from "node:child_process";
import { chromium } from "@playwright/test";

const PORT = process.env.LM_VERIFY_PORT || "5071";
const BASE = `http://127.0.0.1:${PORT}`;

execSync("pnpm exec playwright install chromium", { stdio: "ignore" });

const server = spawn(
  "php",
  ["artisan", "serve", "--host=127.0.0.1", `--port=${PORT}`, "--no-reload"],
  {
    stdio: ["ignore", "inherit", "inherit"],
    env: { ...process.env, PHP_CLI_SERVER_WORKERS: "10" },
  },
);
const stop = () => {
  try {
    server.kill();
  } catch {}
};
process.on("exit", stop);

async function waitUp() {
  for (let i = 0; i < 150; i++) {
    try {
      execSync(`curl -fsS -o /dev/null --max-time 20 ${BASE}/up`, { stdio: "ignore" });
      return;
    } catch {}
    await new Promise((r) => setTimeout(r, 1000));
  }
  throw new Error("server never came up");
}

function lum([r, g, b]) {
  return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
}
function parseRgb(s) {
  const m = s.match(/rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)/);
  return m ? [Number(m[1]), Number(m[2]), Number(m[3])] : null;
}

// page path -> [{ selector, expect: "dark" | "white", label }]
const CHECKS = [
  {
    path: "/admin/plans/compare",
    slow: true,
    checks: [
      { selector: "span.ak-blue, span.ak-note", expect: "dark", label: "compare Yes/No toggles" },
    ],
  },
  {
    // tab=card: the page tab runs wasCustomized()/isOutdatedBlueprint() over all
    // 422 seeded templates (pre-existing perf issue, minutes of CPU); the card
    // tab renders the same ak-muted tab links without that block.
    path: "/admin/templates?tab=card",
    slow: true,
    checks: [{ selector: "a.ak-muted", expect: "dark", label: "templates tabs" }],
  },
  {
    path: "/admin/biolink-reports",
    checks: [{ selector: "a.ak-muted", expect: "dark", label: "biolink-reports filter pills" }],
  },
  {
    path: "/admin/contact-inbox",
    checks: [{ selector: "a.ak-muted", expect: "dark", label: "contact-inbox filter pills" }],
  },
  {
    path: "/admin/credit-reviews",
    checks: [{ selector: "a.ak-muted", expect: "dark", label: "credit-reviews filter pills" }],
  },
  {
    path: "/admin/privacy-requests",
    checks: [{ selector: "a.ak-muted", expect: "dark", label: "privacy-requests filter pills" }],
  },
  {
    path: "/admin/referrals",
    checks: [
      {
        selector: 'form[action$="/admin/referrals/toggle"] button',
        expect: "white",
        label: "referrals solid toggle button",
      },
    ],
  },
  {
    path: "/admin/cron-jobs",
    slow: true,
    checks: [
      { selector: "button.ak-muted[title='Recent run history']", expect: "dark", label: "cron history buttons" },
    ],
  },
  {
    path: "/admin/api-keys",
    checks: [
      {
        selector: 'button[class*="bg-[#4A154B]"], button[class*="bg-[#5865F2]"]',
        expect: "white",
        label: "Slack/Discord solid test buttons",
        optional: true, // rendered only when webhook URLs are configured
      },
    ],
  },
];

await waitUp();
const browser = await chromium.launch();
const page = await browser.newPage();

// demo-admin login via synthesized CSRF POST (see tests/Browser/login-as-demo-admin.ts)
await page.goto(`${BASE}/admin/login`, { timeout: 120_000 });
await Promise.all([
  page.waitForResponse(
    (r) => r.url().endsWith("/admin/demo-login") && r.request().method() === "POST",
    { timeout: 90_000 },
  ),
  page.evaluate(() => {
    const token =
      document.querySelector('input[name="_token"]')?.value ??
      document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ??
      "";
    if (!token) throw new Error("CSRF _token not found on /admin/login");
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "/admin/demo-login";
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "_token";
    input.value = token;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
  }),
]);

let failures = 0;
for (const { path, checks, slow } of CHECKS) {
  await page.goto(`${BASE}${path}`, {
    timeout: slow ? 300_000 : 240_000,
    waitUntil: "domcontentloaded",
  });
  await page.evaluate(() => document.documentElement.classList.add("light-mode"));
  await page.waitForTimeout(300);
  for (const { selector, expect, label, optional } of checks) {
    const colors = await page.$$eval(selector, (els) =>
      els.slice(0, 6).map((el) => getComputedStyle(el).color),
    );
    if (colors.length === 0) {
      if (optional) {
        console.log(`SKIP  ${label} (${path}) — no elements rendered`);
        continue;
      }
      console.error(`FAIL  ${label} (${path}) — selector matched nothing: ${selector}`);
      failures++;
      continue;
    }
    const lums = colors.map(parseRgb).filter(Boolean).map(lum);
    const bad =
      expect === "dark" ? lums.filter((l) => l > 0.55) : lums.filter((l) => l < 0.8);
    if (bad.length) {
      console.error(`FAIL  ${label} (${path}) — expected ${expect} text, got colors: ${colors.join("; ")}`);
      failures++;
    } else {
      console.log(`PASS  ${label} (${path}) — ${colors[0]}`);
    }
  }
}

await browser.close();
stop();
if (failures) {
  console.error(`${failures} light-mode check(s) failed`);
  process.exit(1);
}
console.log("All light-mode visual checks passed.");
process.exit(0);
