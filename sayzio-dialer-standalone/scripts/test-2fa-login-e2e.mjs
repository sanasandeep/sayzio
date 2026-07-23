// Repro harness: password login on a TOTP-enrolled account must land on the
// authenticator-code step of the verify screen. Boots the dialer's Expo web
// build against a tiny mock API, then drives it with Playwright.
import { createServer } from "node:http";
import { spawn } from "node:child_process";
import { createRequire } from "node:module";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "..");
const WORKSPACE = path.resolve(ROOT, "..");
const require2 = createRequire(
  path.join(WORKSPACE, "artifacts/1inme/package.json"),
);
const { chromium } = require2("@playwright/test");

const API_PORT = 9955;
const WEB_PORT = 8093;
// Long-ish token like Laravel Crypt::encrypt output (base64 w/ + and /)
const CHALLENGE_TOKEN =
  "eyJpdiI6IkFCQ0RFRkdISUpLTE1OT1AifQ==+/abcdefghijklmnopqrstuvwxyz0123456789+/ABCDEFGHIJKLMNOPQRSTUVWXYZ==";

let loginHits = 0;
let verifyHits = 0;
let lastVerifyBody = null;

const api = createServer((req, res) => {
  let body = "";
  req.on("data", (c) => (body += c));
  req.on("end", () => {
    const send = (status, obj) => {
      res.writeHead(status, {
        "Content-Type": "application/json",
        "Access-Control-Allow-Origin": "*",
        "Access-Control-Allow-Headers": "*",
        "Access-Control-Allow-Methods": "*",
      });
      res.end(JSON.stringify(obj));
    };
    if (req.method === "OPTIONS") return send(204, {});
    const url = req.url || "";
    if (url.startsWith("/api/v1/auth/config")) {
      return send(200, { data: { mobile_login_enabled: false, allowed_country_codes: [] } });
    }
    if (url.startsWith("/api/v1/auth/login")) {
      loginHits++;
      return send(403, {
        error: {
          message:
            "This account has two-factor authentication enabled. Enter your authenticator code to finish signing in.",
          code: "totp_required",
          details: { challenge_token: CHALLENGE_TOKEN },
        },
      });
    }
    if (url.startsWith("/api/v1/auth/2fa/challenge/verify")) {
      verifyHits++;
      lastVerifyBody = body;
      return send(200, {
        data: {
          token: "test-session-token",
          user: { id: 1, name: "Test", email: "totp@example.com" },
        },
      });
    }
    // Everything else: benign empty payload
    return send(200, { data: {} });
  });
});

function waitHttp(url, timeoutMs) {
  const start = Date.now();
  return new Promise((resolve, reject) => {
    const tick = async () => {
      try {
        const r = await fetch(url, { signal: AbortSignal.timeout(8000) });
        if (r.ok) return resolve();
      } catch {}
      if (Date.now() - start > timeoutMs) return reject(new Error("timeout waiting for " + url));
      setTimeout(tick, 3000);
    };
    tick();
  });
}

let expo;
let browser;
let failed = false;
const fail = (msg) => {
  failed = true;
  console.error("FAIL: " + msg);
};

try {
  await new Promise((r) => api.listen(API_PORT, r));
  console.log("mock api on :" + API_PORT);

  expo = spawn("npx", ["expo", "start", "--web", "--port", String(WEB_PORT)], {
    cwd: ROOT,
    env: {
      ...process.env,
      EXPO_PUBLIC_API_BASE_URL: `http://127.0.0.1:${API_PORT}`,
      CI: "1",
      EXPO_NO_TELEMETRY: "1",
      BROWSER: "none",
    },
    stdio: ["ignore", "pipe", "pipe"],
  });
  expo.stdout.on("data", (d) => process.stdout.write("[expo] " + d));
  expo.stderr.on("data", (d) => process.stdout.write("[expo!] " + d));

  console.log("waiting for expo web...");
  await waitHttp(`http://127.0.0.1:${WEB_PORT}/`, 420000);
  console.log("expo web up; launching browser");

  browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 420, height: 900 } });
  page.on("console", (m) => {
    if (m.type() === "error") console.log("[console.error]", m.text().slice(0, 400));
  });
  page.on("pageerror", (e) => console.log("[pageerror]", String(e).slice(0, 600)));

  await page.goto(`http://127.0.0.1:${WEB_PORT}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
  // First load triggers the metro bundle; wait generously for the login UI.
  await page.waitForSelector("text=Sign in with the same account", { timeout: 300000 });
  console.log("login screen rendered");

  // Password login is the default method (loginMethod useState 'password').
  await page.fill('input[placeholder="you@example.com"]', "totp@example.com");
  const pw = page.locator('input[placeholder="Your password"]');
  if ((await pw.count()) === 0) {
    // toggle to password mode if needed
    await page.click("text=Sign in with password instead");
  }
  await page.fill('input[placeholder="Your password"]', "secret1234");
  await page.click('text="Sign in"');
  console.log("clicked Sign in");

  // Wait for the login API call then observe what the UI does.
  await page.waitForTimeout(6000);
  console.log("loginHits=" + loginHits);

  const url = page.url();
  console.log("current url: " + url);
  const totpVisible = await page
    .locator("text=authenticator app")
    .first()
    .isVisible()
    .catch(() => false);
  const bodyText = (await page.locator("body").innerText()).slice(0, 1500);
  console.log("totp step visible:", totpVisible);
  if (!totpVisible) {
    console.log("---- page text ----\n" + bodyText + "\n-------------------");
    fail("2FA (authenticator) step did not appear after password login");
  } else {
    // Complete the flow: enter a code and submit; token must round-trip intact.
    await page.fill('input[placeholder="123456"]', "123456");
    await page.click("text=Verify and sign in");
    await page.waitForTimeout(4000);
    console.log("verifyHits=" + verifyHits);
    if (verifyHits > 0) {
      const parsed = JSON.parse(lastVerifyBody || "{}");
      console.log("challenge_token intact:", parsed.challenge_token === CHALLENGE_TOKEN);
      if (parsed.challenge_token !== CHALLENGE_TOKEN) {
        console.log("sent token:", String(parsed.challenge_token).slice(0, 120));
        fail("challenge_token was corrupted in transit through the router params");
      }
    } else {
      console.log("---- page text ----\n" + (await page.locator("body").innerText()).slice(0, 1200));
      fail("verify endpoint never hit after submitting the code");
    }
  }
} catch (e) {
  fail(String(e && e.stack ? e.stack : e).slice(0, 1500));
} finally {
  try { if (browser) await browser.close(); } catch {}
  try { if (expo) expo.kill("SIGKILL"); } catch {}
  try { api.close(); } catch {}
  console.log(failed ? "RESULT: FAIL" : "RESULT: PASS");
  process.exit(failed ? 1 : 0);
}
