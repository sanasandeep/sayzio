import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Per-run unique alias (shared-RDS collision avoidance + stale pruning).
const ALIAS = `e2e-booking-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

const CUSTOMER = "E2E Booker";

/**
 * Seed a Service Booking (`service_booking`) link OWNED BY THE DEMO USER in
 * booking mode with generous scheduling (0 lead time, 30-day window) so a
 * single weekday availability rule added via the UI always yields a bookable
 * slot. Service + availability are added through the real editor UI in the test;
 * the visitor slot request goes through the real public /sb endpoints. Echoes
 * `LINK_ID=<id>`.
 *
 * NOTE: `\\` → single backslash for PHP namespaces; `$var` stays literal; never
 * write `\\$`.
 */
function seedBookingLink(): number {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\ServiceBooking;
use App\\Modules\\User\\Models\\ServiceBookingRequest;
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
    ->where('alias','like','e2e-booking-%')->where('created_at','<', now()->subHours(6))->get();
  foreach ($stale as $s) {
    $cfgIds = ServiceBooking::where('link_id',$s->id)->pluck('id');
    ServiceBookingRequest::whereIn('service_booking_id',$cfgIds)->delete();
    ServiceBooking::where('link_id',$s->id)->delete();
    $s->delete();
  }
} catch (\\Throwable $e) {}

$link = Link::withoutGlobalScope('workspace')->where('alias',$alias)->first();
if (!$link) {
  $link = Link::create(['user_id'=>$u->id,'workspace_id'=>$ws?->id,'type'=>'service_booking','alias'=>$alias,'title'=>'E2E Booking','is_active'=>true]);
}
$cfg = ServiceBooking::where('link_id',$link->id)->first();
if (!$cfg) {
  $cfg = ServiceBooking::create(['link_id'=>$link->id,'user_id'=>$u->id,'mode'=>'booking','currency'=>'USD','slot_length_minutes'=>30,'lead_time_minutes'=>0,'max_days_ahead'=>30,'timezone'=>'UTC']);
}
echo 'LINK_ID='.$link->id;
`.trim();

  const out = execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
  const m = out.match(/LINK_ID=(\d+)/);
  if (!m) {
    throw new Error("Booking seed did not echo LINK_ID; output was:\n" + out);
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

test.describe("service_booking request flow (web)", () => {
  // Cold authenticated editor renders over the distant RDS are slow, so give
  // the whole flow real headroom (mirrors dashboard-layout.spec.ts).
  test.describe.configure({ timeout: 180_000 });

  let linkId: number;

  test.beforeAll(() => {
    linkId = seedBookingLink();
  });

  test("owner adds a service + hours, visitor books a slot, it shows as Pending on the dashboard", async ({
    page,
  }) => {
    await demoLogin(page);

    // ── Owner: add a service via the editor UI ─────────────────────────
    // The authenticated editor is heavy and can exceed the config's 45s
    // navigation cap when cold over the distant RDS, so give it real headroom.
    await page.goto(`/user/links/${linkId}/service-booking`, {
      timeout: 120_000,
    });

    await page
      .getByRole("button", { name: "Service" })
      .first()
      .click();
    const svcModal = page.locator(".sb-modal-bg", {
      has: page.locator('input[x-model="svcModal.name"]'),
    });
    await svcModal
      .locator('input[x-model="svcModal.name"]')
      .fill("E2E Consultation");
    await svcModal
      .locator('input[x-model="svcModal.price"]')
      .fill("50");

    // The first service-create hit is a cold controller compile + an insert
    // over the distant RDS, which can exceed the default assertion timeout, so
    // wait for the POST to resolve before asserting the row renders.
    const serviceCreated = page.waitForResponse(
      (r) =>
        /\/service-booking\/services(\?|$)/.test(r.url()) &&
        r.request().method() === "POST",
      { timeout: 60_000 },
    );
    await svcModal.getByRole("button", { name: "Save" }).click();
    await serviceCreated;
    await expect(
      page.locator(".sb-item .nm", { hasText: "E2E Consultation" }),
    ).toBeVisible({ timeout: 15_000 });

    // ── Owner: add one weekly availability rule (first day row) ─────────
    const firstDayRow = page.locator(".sb-day-row").first();
    await firstDayRow.locator("button").last().click();
    const ruleModal = page.locator(".sb-modal-bg", {
      has: page.locator('input[x-model="ruleModal.start_time"]'),
    });
    await ruleModal
      .locator('input[x-model="ruleModal.start_time"]')
      .fill("09:00");
    await ruleModal.locator('input[x-model="ruleModal.end_time"]').fill("17:00");
    await ruleModal.getByRole("button", { name: "Add" }).click();
    // The rule chip (with its own delete button) only renders once the rule is
    // saved — more robust than matching a time string whose format may vary.
    await expect(firstDayRow.locator("button.sb-btn.sm.danger")).toBeVisible();

    // ── Visitor: request the first free slot via the public /sb API ─────
    await page.goto(`/${ALIAS}`);
    const serviceId = Number(
      await page.locator("[data-add]").first().getAttribute("data-add"),
    );
    expect(serviceId).toBeGreaterThan(0);

    const result = await page.evaluate(
      async ({ alias, serviceId, customer }) => {
        const csrf =
          document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") ?? "";
        const headers = {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrf,
          "X-Requested-With": "XMLHttpRequest",
        };
        const slotsRes = await fetch(`/sb/${alias}/slots`, {
          method: "POST",
          headers,
          body: JSON.stringify({
            services: [{ service_id: serviceId, quantity: 1 }],
          }),
        });
        const slotsJson = await slotsRes.json();
        const days = slotsJson?.data?.days ?? [];
        let firstStart: string | null = null;
        for (const d of days) {
          if (d.slots && d.slots.length) {
            firstStart = d.slots[0].start;
            break;
          }
        }
        if (!firstStart) {
          return { ok: false, stage: "slots", slotsJson };
        }
        const bookRes = await fetch(`/sb/${alias}/book`, {
          method: "POST",
          headers,
          body: JSON.stringify({
            customer_name: customer,
            slot_start: firstStart,
            services: [{ service_id: serviceId, quantity: 1 }],
          }),
        });
        const bookJson = await bookRes.json();
        return { ok: bookRes.ok, status: bookRes.status, bookJson };
      },
      { alias: ALIAS, serviceId, customer: CUSTOMER },
    );

    expect(result.ok, JSON.stringify(result)).toBeTruthy();

    // ── Owner: the request appears on the Bookings dashboard as Pending ─
    await page.goto(`/user/links/${linkId}/service-booking/bookings`);
    const bookingCard = page.locator(".sb-card", {
      has: page.locator(".sb-meta", { hasText: CUSTOMER }),
    });
    await expect(bookingCard).toBeVisible();
    await expect(bookingCard.locator(".sb-status.st-pending")).toHaveText(
      "Pending",
    );
  });
});
