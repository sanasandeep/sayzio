import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Per-run unique slug: fixed-slug fixtures collide across parallel task envs on
// the shared RDS, so every run gets its own form and prunes stale same-prefix
// forms from earlier runs.
const SLUG = `e2e-repce-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

const COMPANY_VALUE = "Acme Co";
const COPY0_NAME = "Alice Attendee";
const COPY0_NOTE = "Aisle seat please";
const COPY1_NOTE = "Vegetarian meal";

/**
 * Seed a public Form OWNED BY THE DEMO USER with:
 *   - a REQUIRED top-level text field ("company"), and
 *   - a REPEATABLE section ("sec1") with TWO children: a required text field
 *     ("aname") and an optional text field ("anote"), min 1 / max 5 copies.
 *
 * Two children let the test make copy 1 invalid (empty required "aname") while
 * copy 1 still carries a non-empty optional value ("anote"), so "both copies
 * keep their entered values" is a meaningful assertion.
 *
 * Echoes `FORM_SLUG=<slug>`.
 *
 * NOTE: this string is passed straight to `tinker --execute=`. In a JS template
 * literal `\\` becomes the single backslash PHP namespaces need; `$var` stays
 * literal. Never write `\\$` — that yields invalid `\$var` PHP.
 */
function seedRepeatableForm(): string {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Form;
use Illuminate\\Support\\Facades\\Hash;

$email = '${DEMO_LOGIN_EMAIL}';
$slug = '${SLUG}';
$u = User::where('email', $email)->first();
if (!$u) {
  $u = User::create(['name'=>'Sayzio Demo','email'=>$email,'password'=>Hash::make('demo-password'),'status'=>'active']);
}

try {
  Form::where('user_id',$u->id)->where('slug','like','e2e-repce-%')
    ->where('created_at','<', now()->subHours(6))->delete();
} catch (\\Throwable $e) {}

$fields = [
  ['id'=>'company','type'=>'text','label'=>'Company','required'=>true,'width'=>12],
  ['id'=>'sec1','type'=>'section','label'=>'Attendee','repeatable'=>true,'repeat_min'=>1,'repeat_max'=>5,'repeat_add_label'=>'Add attendee'],
  ['id'=>'aname','type'=>'text','label'=>'Attendee name','required'=>true,'parent'=>'sec1','width'=>12],
  ['id'=>'anote','type'=>'text','label'=>'Note','required'=>false,'parent'=>'sec1','width'=>12],
];

// Form::user_id is NOT mass-assignable (not in $fillable), so set it directly.
$form = Form::where('slug',$slug)->first();
if (!$form) {
  $form = new Form(['slug'=>$slug,'title'=>'E2E Repeatable Copy Error Form','fields'=>$fields,'is_active'=>true]);
} else {
  $form->fill(['fields'=>$fields,'is_active'=>true]);
}
$form->user_id = $u->id;
$form->save();
echo 'FORM_SLUG='.$form->slug;
`.trim();

  const out = execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
  const m = out.match(/FORM_SLUG=([a-z0-9-]+)/);
  if (!m) {
    throw new Error("Form seed did not echo FORM_SLUG; output was:\n" + out);
  }
  return m[1];
}

test.describe("a bad entry in a repeatable copy flags the right copy (web)", () => {
  // Cold public-page renders + a POST round-trip over the distant RDS are slow.
  test.describe.configure({ timeout: 180_000 });

  let slug: string;

  test.beforeAll(() => {
    slug = seedRepeatableForm();
  });

  test("a per-copy 422 flags copy 1's field (not copy 0) and both copies keep their values", async ({
    page,
  }) => {
    // Cold public-page renders over the distant RDS can exceed the 45s nav cap.
    await page.goto(`/f/${slug}`, { timeout: 120_000 });

    // Top-level required field is filled so it does NOT contribute an error —
    // this isolates the ONLY validation failure to inside copy 1.
    const company = page.locator('input[name="company"]');
    await expect(company).toBeVisible();
    await company.fill(COMPANY_VALUE);

    // Copy 0 (index 0) always renders. Fill BOTH its children — it is fully
    // valid and must not be flagged.
    const copy0Name = page.locator('input[name="rep_sec1[0][aname]"]');
    await expect(copy0Name).toBeVisible();
    await copy0Name.fill(COPY0_NAME);
    await page.locator('input[name="rep_sec1[0][anote]"]').fill(COPY0_NOTE);

    // Add a second copy via the real "Add another" button.
    await page.getByRole("button", { name: "Add attendee" }).click();
    const copy1Name = page.locator('input[name="rep_sec1[1][aname]"]');
    await expect(copy1Name).toBeVisible();
    // Leave copy 1's REQUIRED "aname" empty (the bad entry) but fill its
    // optional "anote" so it carries a non-empty value across the re-render.
    await page.locator('input[name="rep_sec1[1][anote]"]').fill(COPY1_NOTE);

    // Submit via form.submit() so the browser's HTML5 constraint check is
    // bypassed and the request actually reaches the server, which returns a 422
    // and re-renders the Blade form with old input + per-copy errors flashed.
    const submitted = page.waitForResponse(
      (r) => r.url().includes(`/f/${slug}`) && r.request().method() === "POST",
      { timeout: 60_000 },
    );
    await page.evaluate(() => {
      const form = document.querySelector<HTMLFormElement>(
        'form[action*="/f/"]',
      );
      if (!form) throw new Error("public form element not found");
      form.submit();
    });
    await submitted;

    // After the 422 re-render both copies must be re-shown (rCount restored
    // from old input).
    const reCopy0Name = page.locator('input[name="rep_sec1[0][aname]"]');
    const reCopy1Name = page.locator('input[name="rep_sec1[1][aname]"]');
    await expect(reCopy0Name).toBeVisible({ timeout: 15_000 });
    await expect(reCopy1Name).toBeVisible();

    // The error must land under COPY 1's required field — the off-by-index
    // regression this guards. The error block renders inside the same
    // .form-field wrapper as the input.
    const copy1Field = page.locator(".form-field", {
      has: page.locator('input[name="rep_sec1[1][aname]"]'),
    });
    await expect(copy1Field.locator(".form-error")).toBeVisible();

    // Copy 0's required field must NOT carry an error — it was valid.
    const copy0Field = page.locator(".form-field", {
      has: page.locator('input[name="rep_sec1[0][aname]"]'),
    });
    await expect(copy0Field.locator(".form-error")).toHaveCount(0);

    // Both copies keep the values the visitor entered: copy 0 is intact and
    // copy 1 keeps its non-empty optional note even though its required field
    // failed validation.
    await expect(reCopy0Name).toHaveValue(COPY0_NAME);
    await expect(page.locator('input[name="rep_sec1[0][anote]"]')).toHaveValue(
      COPY0_NOTE,
    );
    await expect(page.locator('input[name="rep_sec1[1][anote]"]')).toHaveValue(
      COPY1_NOTE,
    );

    // Sanity: the submission did fail validation (we are back on the form).
    await expect(company).toBeVisible();
  });
});
