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
const SLUG = `e2e-rep-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

const COPY0_VALUE = "Alice Attendee";
const COPY1_VALUE = "Bob Attendee";

/**
 * Seed a public Form OWNED BY THE DEMO USER with:
 *   - a REQUIRED top-level text field ("company"), and
 *   - a REPEATABLE section ("sec1") whose only child is a required text field
 *     ("aname"), min 1 / max 5 copies.
 *
 * The public submit is exercised through the real /f/{slug} page in the test.
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
  Form::where('user_id',$u->id)->where('slug','like','e2e-rep-%')
    ->where('created_at','<', now()->subHours(6))->delete();
} catch (\\Throwable $e) {}

$fields = [
  ['id'=>'company','type'=>'text','label'=>'Company','required'=>true,'width'=>12],
  ['id'=>'sec1','type'=>'section','label'=>'Attendee','repeatable'=>true,'repeat_min'=>1,'repeat_max'=>5,'repeat_add_label'=>'Add attendee'],
  ['id'=>'aname','type'=>'text','label'=>'Attendee name','required'=>true,'parent'=>'sec1','width'=>12],
  ['id'=>'afile','type'=>'file','label'=>'Attendee file','required'=>false,'parent'=>'sec1','width'=>12],
];

// Form::user_id is NOT mass-assignable (not in $fillable), so set it directly.
$form = Form::where('slug',$slug)->first();
if (!$form) {
  $form = new Form(['slug'=>$slug,'title'=>'E2E Repeatable Form','fields'=>$fields,'is_active'=>true]);
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

test.describe("repeatable group survives validation failure (web)", () => {
  // Cold public-page renders + a POST round-trip over the distant RDS are slow.
  test.describe.configure({ timeout: 180_000 });

  let slug: string;

  test.beforeAll(() => {
    slug = seedRepeatableForm();
  });

  test("a top-level 422 preserves data in every repeatable copy (incl. copies beyond index 0)", async ({
    page,
  }) => {
    // Cold public-page renders over the distant RDS can exceed the 45s nav cap.
    await page.goto(`/f/${slug}`, { timeout: 120_000 });

    // The first copy (index 0) always renders. Fill its required child field.
    const copy0 = page.locator('input[name="rep_sec1[0][aname]"]');
    await expect(copy0).toBeVisible();
    await copy0.fill(COPY0_VALUE);

    // Add a second copy via the real "Add another" button, then fill it. The
    // add button carries a leading fa-plus glyph, so match by substring.
    await page.getByRole("button", { name: "Add attendee" }).click();
    const copy1 = page.locator('input[name="rep_sec1[1][aname]"]');
    await expect(copy1).toBeVisible();
    await copy1.fill(COPY1_VALUE);

    // Leave the REQUIRED top-level "company" field empty. Submit via
    // form.submit() so the browser's HTML5 constraint check is bypassed and the
    // request actually reaches the server, which returns a 422 and re-renders
    // the Blade form with old input flashed.
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

    // After the 422 re-render: the top-level required error is shown, AND both
    // repeatable copies keep the values the visitor entered — copy 1 (beyond
    // index 0) is the regression this guards.
    const reCopy0 = page.locator('input[name="rep_sec1[0][aname]"]');
    const reCopy1 = page.locator('input[name="rep_sec1[1][aname]"]');
    await expect(reCopy0).toBeVisible({ timeout: 15_000 });
    await expect(reCopy0).toHaveValue(COPY0_VALUE);

    // The second copy must be re-shown (rCount restored from old input) and
    // still carry its value.
    await expect(reCopy1).toBeVisible();
    await expect(reCopy1).toHaveValue(COPY1_VALUE);

    // Sanity: the submission did fail validation (we are back on the form, not
    // on a success screen), and the top-level field is flagged invalid.
    await expect(page.locator('input[name="company"]')).toBeVisible();
    await expect(page.locator(".form-error").first()).toBeVisible();
  });

  test("a file attached to a rep copy beyond index 0 prompts a precise re-attach on 422", async ({
    page,
  }) => {
    await page.goto(`/f/${slug}`, { timeout: 120_000 });

    // Fill copy 0's required text so the ONLY validation failure is the empty
    // top-level "company" field (a top-level 422, which re-renders the form).
    const copy0 = page.locator('input[name="rep_sec1[0][aname]"]');
    await expect(copy0).toBeVisible();
    await copy0.fill(COPY0_VALUE);

    // Add a second copy and fill its text, then attach a file to copy 1's file
    // input — the regression target is that a file in a copy beyond index 0 is
    // not silently dropped on re-render.
    await page.getByRole("button", { name: "Add attendee" }).click();
    const copy1 = page.locator('input[name="rep_sec1[1][aname]"]');
    await expect(copy1).toBeVisible();
    await copy1.fill(COPY1_VALUE);

    // Attach a file to copy 1's dropzone. The file MUST be created in-page (not
    // via setInputFiles): the dropzone's @change handler rebuilds the input's
    // FileList through a fresh DataTransfer, and Playwright's CDP-injected File
    // objects can't be re-added to a DataTransfer (dt.items.add drops them),
    // which would silently clear the input before submit. A page-created File
    // survives that rebuild, faithfully mirroring a real file-picker selection.
    // Use an extension the form_field.file upload policy allows (png).
    const FILE_NAME = "attendee-badge.png";
    await page.evaluate((fileName) => {
      const input = document.querySelector<HTMLInputElement>(
        'input[name="rep_sec1[1][afile]"]',
      );
      if (!input) throw new Error("rep copy 1 file input not found");
      const bytes = new Uint8Array([
        0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a,
      ]);
      const file = new File([bytes], fileName, { type: "image/png" });
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      input.dispatchEvent(new Event("change", { bubbles: true }));
    }, FILE_NAME);

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

    // After the 422 re-render, the file input for copy 1 is present again AND a
    // precise "please re-attach <filename>" notice names the dropped file, so
    // the visitor knows exactly which upload to re-select.
    await expect(
      page.locator('input[name="rep_sec1[1][afile]"]'),
    ).toBeVisible({ timeout: 15_000 });
    await expect(page.getByText(FILE_NAME, { exact: false })).toBeVisible();
    await expect(page.getByText(/re-attach/i).first()).toBeVisible();

    // The text sibling in the same copy still survives (old() repopulation).
    await expect(page.locator('input[name="rep_sec1[1][aname]"]')).toHaveValue(
      COPY1_VALUE,
    );
  });
});
