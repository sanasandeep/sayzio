import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Per-run unique slug: fixed-slug fixtures collide across parallel task envs on
// the shared RDS, so every run gets its own form and prunes stale same-prefix
// forms from earlier runs.
const SLUG = `e2e-panelscroll-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

const SELECT_LABEL = "Favourite fruit (long options)";
const TEXT_LABEL = "Your comments";

function runTinkerSeed(php: string): string {
  let lastErr: unknown;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
        cwd: ARTIFACT_ROOT,
        encoding: "utf8",
      });
    } catch (err) {
      lastErr = err;
    }
  }
  throw lastErr;
}

/**
 * Seed a Form OWNED BY THE DEMO USER with two top-level fields:
 *   - a `select` field with a long option list (so its right-panel editor —
 *     Field ID + Label + Help + Options textarea + width + Validation accordion
 *     — is tall enough to overflow the sticky, capped-height panel), and
 *   - a plain `text` field to switch to.
 *
 * The form is opened in the real /user/forms/{id}/builder editor by the spec.
 * Echoes `FORM_ID=<id>`.
 *
 * NOTE: this string is passed straight to `tinker --execute=`. In a JS template
 * literal `\\` becomes the single backslash PHP namespaces need; `$var` stays
 * literal. Never write `\\$` — that yields invalid `\$var` PHP.
 */
function seedForm(): number {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Form;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

$email = '${DEMO_LOGIN_EMAIL}';
$slug = '${SLUG}';
$u = User::where('email', $email)->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Sayzio Demo', 'email' => $email,
    'password' => Hash::make('demo-password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

try {
  Form::where('user_id',$u->id)->where('slug','like','e2e-panelscroll-%')
    ->where('created_at','<', now()->subHours(6))->delete();
} catch (\\Throwable $e) {}

$fields = [
  ['id'=>'fruit','type'=>'select','label'=>'${SELECT_LABEL}','required'=>true,'width'=>12,
   'options'=>['Apple','Apricot','Banana','Blackberry','Blueberry','Cherry','Cranberry','Date','Fig','Grape','Guava','Kiwi','Lemon','Lime','Mango','Melon','Nectarine','Orange','Papaya','Peach','Pear','Pineapple','Plum','Raspberry','Strawberry','Tangerine','Watermelon'],
   'help'=>'Pick the one you like best.'],
  ['id'=>'comments','type'=>'text','label'=>'${TEXT_LABEL}','required'=>false,'width'=>12,
   'placeholder'=>'Anything else?'],
];

$form = Form::where('slug',$slug)->first();
if (!$form) {
  $form = new Form(['slug'=>$slug,'title'=>'E2E Panel Scroll Form','fields'=>$fields,'is_active'=>true]);
} else {
  $form->fill(['fields'=>$fields,'is_active'=>true]);
}
$form->user_id = $u->id;
$form->workspace_id = $ws?->id;
$form->save();
echo 'FORM_ID='.$form->id;
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/FORM_ID=(\d+)/);
  if (!m) {
    throw new Error("Form seed did not echo FORM_ID; output was:\n" + out);
  }
  return Number(m[1]);
}

/**
 * Authenticate as the non-prod demo account (`AuthController::demoLogin`).
 *
 * We do NOT depend on a `form[action$="/user/demo-login"]` element being present
 * in the login markup: whether that button renders is environment/data driven,
 * so relying on it makes the login step silently brittle ("demo-login form not
 * found"). Instead we replicate exactly what the demo-login endpoint needs — a
 * same-session CSRF token POSTed to `/user/demo-login` — by reading the `_token`
 * that the login page's own forms already carry and submitting a synthesized
 * form. This is the identical mechanism run-validation.sh's warm step uses to
 * authenticate, so it works regardless of which login buttons are rendered.
 */
async function loginAsDemo(page: Page): Promise<void> {
  await page.goto("/user/login");
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/user/demo-login") &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    page.evaluate(() => {
      const tokenInput = document.querySelector<HTMLInputElement>(
        'input[name="_token"]',
      );
      if (!tokenInput || !tokenInput.value) {
        throw new Error("CSRF _token not found on /user/login");
      }
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "/user/demo-login";
      const token = document.createElement("input");
      token.type = "hidden";
      token.name = "_token";
      token.value = tokenInput.value;
      form.appendChild(token);
      document.body.appendChild(form);
      form.submit();
    }),
  ]);
}

/**
 * Read the right per-field panel's internal scrollTop. `x-ref="fieldPanel"`
 * does NOT emit a DOM attribute, so resolve it via the formBuilder root's
 * Alpine `_x_refs`, with a text-based fallback (the panel's direct-child <h4>
 * always carries the "Field options" label; the field-type palette's <h4> says
 * "Add a field").
 */
function panelScrollTop(page: Page): Promise<number> {
  return page.evaluate(() => {
    const root = document.querySelector<HTMLElement>(
      '[x-data^="formBuilder"]',
    ) as (HTMLElement & { _x_refs?: Record<string, HTMLElement> }) | null;
    let panel = root?._x_refs?.fieldPanel ?? null;
    if (!panel) {
      panel =
        [...document.querySelectorAll<HTMLElement>("div.card-premium")].find(
          (d) => {
            const h4 = d.querySelector(":scope > h4");
            return !!h4 && /Field options/.test(h4.textContent || "");
          },
        ) ?? null;
    }
    if (!panel) throw new Error("fieldPanel not found");
    return panel.scrollTop;
  });
}

function scrollPanelToBottom(page: Page): Promise<void> {
  return page.evaluate(() => {
    const root = document.querySelector<HTMLElement>(
      '[x-data^="formBuilder"]',
    ) as (HTMLElement & { _x_refs?: Record<string, HTMLElement> }) | null;
    let panel = root?._x_refs?.fieldPanel ?? null;
    if (!panel) {
      panel =
        [...document.querySelectorAll<HTMLElement>("div.card-premium")].find(
          (d) => {
            const h4 = d.querySelector(":scope > h4");
            return !!h4 && /Field options/.test(h4.textContent || "");
          },
        ) ?? null;
    }
    if (!panel) throw new Error("fieldPanel not found");
    panel.scrollTop = panel.scrollHeight;
  });
}

// A field card on the left, matched by its rendered label text.
function fieldCard(page: Page, label: string) {
  return page.locator(".field-card").filter({ hasText: label }).first();
}

test.describe("form builder resets the field panel scroll on field switch", () => {
  // Cold editor renders + a tinker seed over the distant RDS are slow.
  test.describe.configure({ timeout: 180_000 });

  let formId: number;

  test.beforeAll(() => {
    formId = seedForm();
  });

  test("switching fields returns the right panel to the top", async ({
    page,
  }) => {
    // A short viewport height guarantees the capped-height (max-h: 100vh-2rem)
    // panel overflows once a field with a long option editor is selected, so
    // there is real scroll distance to reset. Width stays >= lg (1024px) so the
    // panel's sticky/overflow classes are active.
    await page.setViewportSize({ width: 1366, height: 500 });

    await loginAsDemo(page);

    await page.goto(`/user/forms/${formId}/builder`, {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    // The builder is Alpine-driven; wait for the seeded field cards to render.
    await expect(fieldCard(page, SELECT_LABEL)).toBeVisible({
      timeout: 45_000,
    });

    // Select the long-options field; the right panel switches into edit mode.
    await fieldCard(page, SELECT_LABEL).click();
    await expect(
      page.getByText(`Editing: select`, { exact: false }),
    ).toBeVisible({ timeout: 15_000 });

    // Open the Validation accordion so the panel content is definitively taller
    // than the viewport-capped panel, guaranteeing scrollable distance.
    await page.getByRole("button", { name: /Validation/i }).click();

    // Scroll the panel to the bottom, then assert it actually moved (otherwise
    // the reset assertion below would pass trivially on a non-scrolling panel).
    await scrollPanelToBottom(page);
    await expect
      .poll(() => panelScrollTop(page), { timeout: 10_000 })
      .toBeGreaterThan(0);

    // Switch to the other field. The $watch('selectedIndex') hook should reset
    // the panel's internal scroll back to the top on the next tick.
    await fieldCard(page, TEXT_LABEL).click();
    await expect(
      page.getByText(`Editing: text`, { exact: false }),
    ).toBeVisible({ timeout: 15_000 });

    // The regression guard: the panel is back at the top after the switch.
    await expect.poll(() => panelScrollTop(page), { timeout: 10_000 }).toBe(0);
  });
});
