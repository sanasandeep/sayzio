import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * Repro for "can't even put a cursor in the Name field" on a template-draft
 * profile card block. Seeds a draft biolink (mirroring an admin design
 * session: `_template_draft` link settings + a fixed, placeholder-seeded
 * profile_card_v2 block), opens the inline block editor, and verifies the
 * Name input actually receives pointer + keyboard focus — reporting whatever
 * element is covering it if not.
 */

const ALIAS = "e2e-pc-name-focus";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

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

function seedFixtures(): { linkId: number; blockId: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

$bio = Link::withoutGlobalScope('workspace')->where('alias', '${ALIAS}')->first();
if (!$bio) {
  $bio = Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => '${ALIAS}', 'title' => 'Template draft: Repro', 'is_active' => true,
  ]);
} else {
  $bio->user_id = $u->id; $bio->workspace_id = $ws?->id; $bio->save();
}
$settings = $bio->settings ?? [];
$settings['_template_draft'] = ['template_id' => 1, 'template_name' => 'Repro', 'started_at' => now()->toIso8601String()];
$bio->settings = $settings;
$bio->save();

BiolinkBlock::where('link_id', $bio->id)->delete();
$blk = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'profile_card_v2', 'sort_order' => 0, 'is_active' => true,
  'settings' => [
    'name' => 'Your Name', 'title' => '', 'bio' => 'Creator. Storyteller.',
    'location' => 'Your City, Country', 'website' => '',
    'avatar' => '', 'cover' => '',
    '_fixed' => true, '_placeholder' => true,
  ],
]);
echo 'IDS=' . json_encode(['linkId' => $bio->id, 'blockId' => $blk->id]);
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

test.describe("profile card Name field focus", () => {
  test.describe.configure({ timeout: 240_000 });

  test("Name input takes cursor via real mouse click", async ({ page }) => {
    const ids = seedFixtures();
    await loginAsDemo(page);

    await page.goto(`/user/links/${ids.linkId}/blocks`, { timeout: 120_000 });

    // Open the inline block editor via the pencil button on the block card.
    const card = page.locator(
      `.block-card-wrapper[data-block-id="${ids.blockId}"]`,
    );
    await expect(card).toBeVisible({ timeout: 60_000 });
    await card.locator(".edit-btn").first().click();

    const nameInput = page.locator('input[name="settings[name]"]').first();
    await expect(nameInput).toBeVisible({ timeout: 60_000 });
    await nameInput.scrollIntoViewIfNeeded();

    // What element actually sits at the input's center point?
    const covering = await nameInput.evaluate((el) => {
      const r = el.getBoundingClientRect();
      const hit = document.elementFromPoint(
        r.left + r.width / 2,
        r.top + r.height / 2,
      );
      const describe = (n: Element | null) =>
        n
          ? `${n.tagName.toLowerCase()}${n.id ? "#" + n.id : ""}.${(n as HTMLElement).className?.toString().slice(0, 120)}`
          : "null";
      return {
        hitIsInput: hit === el,
        hit: describe(hit),
        rect: { w: r.width, h: r.height, top: r.top, left: r.left },
        readOnly: (el as HTMLInputElement).readOnly,
        disabled: (el as HTMLInputElement).disabled,
        pointerEvents: getComputedStyle(el).pointerEvents,
      };
    });
    console.log("HIT-TEST:", JSON.stringify(covering));

    // Real mouse click, then confirm the input took focus and accepts typing.
    await nameInput.click();
    const focused = await nameInput.evaluate(
      (el) => document.activeElement === el,
    );
    console.log("FOCUSED-AFTER-CLICK:", focused);
    expect(covering.hitIsInput, `covered by: ${covering.hit}`).toBe(true);
    expect(focused).toBe(true);

    await nameInput.fill("");
    await nameInput.type("Sana");
    await expect(nameInput).toHaveValue("Sana");
  });
});
