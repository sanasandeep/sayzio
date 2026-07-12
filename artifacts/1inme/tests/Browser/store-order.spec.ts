import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Per-run unique alias: fixed-alias fixtures collide across parallel task envs
// on the shared RDS, so every run gets its own store link and prunes stale
// same-prefix links from earlier runs.
const ALIAS = `e2e-store-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

/**
 * Seed a Store (`store_menu`) link OWNED BY THE DEMO USER in order mode. The
 * owner MUST be DEMO_LOGIN_EMAIL or the owner editor/dashboard routes 403
 * silently. Catalog (category + product) and the visitor order are then driven
 * through the real UI in the test itself. Echoes `LINK_ID=<id>` so the spec can
 * open the owner editor/dashboard by id.
 *
 * NOTE: this string is passed straight to `tinker --execute=`. In a JS template
 * literal `\\` becomes the single backslash PHP namespaces need; `$var` stays
 * literal. Never write `\\$` — that yields invalid `\$var` PHP.
 */
function seedStoreLink(): number {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\StoreMenu;
use App\\Modules\\User\\Models\\StoreOrder;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;

$email = '${DEMO_LOGIN_EMAIL}';
$alias = '${ALIAS}';
$u = User::where('email', $email)->first();
if (!$u) {
  $u = User::create(['name'=>'Sayzio Demo','email'=>$email,'password'=>Hash::make('demo-password'),'status'=>'active']);
}
$ws = app(WorkspaceContext::class)->resolve($u);

try {
  $stale = Link::withoutGlobalScope('workspace')->where('user_id',$u->id)
    ->where('alias','like','e2e-store-%')->where('created_at','<', now()->subHours(6))->get();
  foreach ($stale as $s) {
    $menuIds = StoreMenu::where('link_id',$s->id)->pluck('id');
    StoreOrder::whereIn('menu_id',$menuIds)->delete();
    StoreMenu::where('link_id',$s->id)->delete();
    $s->delete();
  }
} catch (\\Throwable $e) {}

$link = Link::withoutGlobalScope('workspace')->where('alias',$alias)->first();
if (!$link) {
  $link = Link::create(['user_id'=>$u->id,'workspace_id'=>$ws?->id,'type'=>'store_menu','alias'=>$alias,'title'=>'E2E Store','is_active'=>true]);
}
$menu = StoreMenu::where('link_id',$link->id)->first();
if (!$menu) {
  $menu = StoreMenu::create(['link_id'=>$link->id,'user_id'=>$u->id,'mode'=>'order','currency'=>'USD','settings'=>['accepting_orders'=>true]]);
}
echo 'LINK_ID='.$link->id;
`.trim();

  const out = execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
  const m = out.match(/LINK_ID=(\d+)/);
  if (!m) {
    throw new Error("Store seed did not echo LINK_ID; output was:\n" + out);
  }
  return Number(m[1]);
}

/**
 * Log in as the demo user via the real CSRF-protected demo-login route.
 * Posts directly with maxRedirects:0 so we authenticate the browser context's
 * cookie jar without following the 302 into the heavy post-login page render
 * (which otherwise blows the request timeout; see memory "1inme browser e2e
 * fast login").
 */
async function demoLogin(page: import("@playwright/test").Page): Promise<void> {
  await page.goto("/user/login");
  const token = await page
    .locator('input[name="_token"]')
    .first()
    .inputValue();
  const res = await page.request.post("/user/demo-login", {
    form: { _token: token },
    maxRedirects: 0,
  });
  expect([200, 302, 303].includes(res.status())).toBeTruthy();
}

test.describe("store_menu order flow (web)", () => {
  // Cold authenticated editor renders over the distant RDS are slow, and this
  // flow now drives the order through its full status lifecycle (several extra
  // POST round-trips), so give the whole flow real headroom.
  test.describe.configure({ timeout: 300_000 });

  let linkId: number;

  test.beforeAll(() => {
    linkId = seedStoreLink();
  });

  test("owner adds a catalog, visitor sends an order, it shows as New on the dashboard", async ({
    page,
  }) => {
    await demoLogin(page);

    // ── Owner: add a category + product via the editor UI ──────────────
    // The authenticated editor is heavy and can exceed the config's 45s
    // navigation cap when cold over the distant RDS, so give it real headroom.
    await page.goto(`/user/links/${linkId}/store`, { timeout: 120_000 });

    // The add buttons render a leading fa-plus glyph ("+ Category"), so match
    // by substring rather than exact text (also robust if Font Awesome fails).
    await page
      .getByRole("button", { name: "Category" })
      .first()
      .click();
    const catModal = page.locator(".rm-modal-bg", {
      has: page.locator('input[x-model="catModal.name"]'),
    });
    await catModal.locator('input[x-model="catModal.name"]').fill("E2E Category");
    // Create POSTs insert over the distant RDS and can occasionally exceed the
    // default assertion timeout, so wait for the POST to resolve first.
    const categoryCreated = page.waitForResponse(
      (r) =>
        /\/categories(\?|$)/.test(r.url()) && r.request().method() === "POST",
      { timeout: 60_000 },
    );
    await catModal.getByRole("button", { name: "Save" }).click();
    await categoryCreated;
    await expect(
      page.locator(".rm-cat .ct", { hasText: "E2E Category" }),
    ).toBeVisible({ timeout: 15_000 });

    await page
      .getByRole("button", { name: "Product" })
      .first()
      .click();
    const prodModal = page.locator(".rm-modal-bg", {
      has: page.locator('input[x-model="productModal.name"]'),
    });
    await prodModal
      .locator('input[x-model="productModal.name"]')
      .fill("E2E Product");
    await prodModal
      .locator('input[x-model="productModal.price"]')
      .fill("9.99");
    const productCreated = page.waitForResponse(
      (r) =>
        /\/products(\?|$)/.test(r.url()) && r.request().method() === "POST",
      { timeout: 60_000 },
    );
    await prodModal.getByRole("button", { name: "Save" }).click();
    await productCreated;
    await expect(
      page.locator(".rm-item .nm", { hasText: "E2E Product" }),
    ).toBeVisible({ timeout: 15_000 });

    // ── Visitor: place an order request through the public page UI ──────
    // Cold public-page renders over the distant RDS can exceed the 45s nav cap.
    await page.goto(`/${ALIAS}`, { timeout: 120_000 });
    await expect(page.locator(".item .name", { hasText: "E2E Product" })).toBeVisible();

    await page.locator("button.add", { hasText: "Add" }).first().click();
    await page.getByRole("button", { name: "Review request" }).click();
    await page.fill("#fName", "E2E Shopper");

    // The first hit to the public order endpoint is a cold controller compile +
    // an insert over the distant RDS, which can take well over the default
    // assertion timeout, so wait for the POST to resolve before asserting the
    // done modal (the button otherwise sits at "Sending…").
    const orderResponse = page.waitForResponse(
      (r) =>
        r.url().includes(`/sm/${ALIAS}/order`) &&
        r.request().method() === "POST",
      { timeout: 60_000 },
    );
    await page.getByRole("button", { name: "Send order request" }).click();
    await orderResponse;

    // The done modal confirms the request landed and shows the initial "New".
    await expect(page.locator("#doneModal")).toHaveClass(/\bshow\b/, {
      timeout: 15_000,
    });
    await expect(page.locator("#ordStatus")).toHaveText("New");

    // ── Owner: the order appears on the Order Requests dashboard as New ──
    // The authenticated dashboard render can exceed the config's 45s nav cap
    // when cold over the distant RDS, so give it real headroom (mirrors the
    // editor goto above).
    await page.goto(`/user/links/${linkId}/store/orders`, { timeout: 120_000 });
    const orderCard = page.locator(".ro-card", {
      has: page.locator(".ro-table", { hasText: "E2E Shopper" }),
    });
    await expect(orderCard).toBeVisible();
    await expect(orderCard.locator(".ro-status.st-new")).toHaveText("New");

    // ── Owner: advance the order through its status lifecycle ───────────
    // The status buttons POST to /orders/{id}/status and the board merges the
    // returned order in place. Drive the real dashboard buttons and assert the
    // badge updates after each transition. The first status POST is a cold
    // controller compile + update over the distant RDS, so wait for it (later
    // transitions are warm, but wait on each to avoid asserting mid-flight).
    async function advance(actionLabel: string): Promise<void> {
      const statusPosted = page.waitForResponse(
        (r) =>
          /\/orders\/\d+\/status(\?|$)/.test(r.url()) &&
          r.request().method() === "POST",
        { timeout: 60_000 },
      );
      await orderCard.getByRole("button", { name: actionLabel }).click();
      await statusPosted;
    }

    // New → Accepted (stays in the default "open" filter).
    await advance("Accept");
    await expect(orderCard.locator(".ro-status.st-accepted")).toHaveText(
      "Accepted",
      { timeout: 15_000 },
    );

    // Accepted → Ready.
    await advance("Mark ready");
    await expect(orderCard.locator(".ro-status.st-ready")).toHaveText("Ready", {
      timeout: 15_000,
    });

    // Ready → Completed. Completed orders drop out of the "open" filter, so
    // switch to "All" to confirm the terminal status landed.
    await advance("Complete");
    // `exact:true` — a substring match on "All" also hits the header's "Coin
    // wallet balance" button (…b-ALL-ance…), tripping strict-mode.
    await page.getByRole("button", { name: "All", exact: true }).click();
    await expect(orderCard.locator(".ro-status.st-completed")).toHaveText(
      "Completed",
      { timeout: 15_000 },
    );
  });
});
