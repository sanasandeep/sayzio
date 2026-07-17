/**
 * PublicStorageUrl::resolve() coverage guard ("slow image link" guard).
 *
 * Why this exists
 * ---------------
 * All known hot-path image URLs (avatars, post images, logos, favicons,
 * cover/OG images) were routed through `App\Support\PublicStorageUrl::resolve()`
 * so browsers hit the CloudFront CDN directly instead of the slow
 * `/storage/{path}` 302 bridge route. Nothing structural stops a FUTURE payload
 * builder from emitting a raw `'avatar' => $x->avatar` again and silently
 * reintroducing the slow path — it renders fine, it's just slow, so manual QA
 * never catches it.
 *
 * What counts as an offender
 * --------------------------
 * A line in a PHP file under `artifacts/1inme/app/Modules/{Api,Common,User}`
 * that emits a storage-backed image COLUMN as an array value:
 *
 *     'avatar' => $u->avatar,
 *     'logo'   => $s->logo,
 *     'viewer_avatar' => $c->viewer?->avatar,
 *
 * i.e. the line contains `=>` and a bare PROPERTY access on one of
 * `STORAGE_COLUMNS` (see below) with NO `PublicStorageUrl::resolve(` wrapping
 * it (same line or the immediately preceding line, for wrapped-call splits).
 *
 * What is SAFE (never flagged)
 * ----------------------------
 *   - Anything already passed through `PublicStorageUrl::resolve(...)`.
 *   - Method calls (`->photoUrl()`, `$this->favicon($link)`) — parentheses
 *     after the name mean it's not a raw column read; the helper owns its own
 *     resolution policy.
 *   - `_url`-suffixed properties (`avatar_url`, `photo_url`, `image_url`) —
 *     those store full URLs (often external), not `/storage/...` paths.
 *   - Boolean/comparison contexts: `(bool) $x->avatar`, `$x->avatar !== null`,
 *     `empty($x->avatar)`, and a bare ternary test `$x->avatar ? … : …`
 *     (but `?? null` IS still flagged — it emits the raw value).
 *   - `$request->…` reads (write-time input handling, not payload emission).
 *   - Explicit ALLOWLIST entries (file + needle + reason) for intentional raw
 *     emissions: external OAuth avatar URLs, editor round-trips that must
 *     return the raw stored value, etc.
 *
 * Adding a legit exception: append to ALLOWLIST below with a reason — never
 * weaken the matcher.
 *
 * Run:  pnpm --filter @workspace/scripts run check:storage-url-resolve
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Payload-builder roots to scan (relative to repo root). */
export const SCAN_ROOTS: string[] = [
  "artifacts/1inme/app/Modules/Api",
  "artifacts/1inme/app/Modules/Common",
  "artifacts/1inme/app/Modules/User",
  "artifacts/1inme/app/Modules/Admin",
  "artifacts/1inme/app/Services",
];

/**
 * Blade template roots. Blade files get a second pass (`scanBladeSource`) that
 * flags raw storage-column reads emitted through `{{ … }}` / `{!! … !!}`
 * echoes (e.g. `<img src="{{ $user->avatar }}">`), which reach browsers and
 * email clients directly.
 */
export const BLADE_SCAN_ROOTS: string[] = ["artifacts/1inme/resources/views"];

/**
 * Storage-backed image columns that must go through PublicStorageUrl::resolve()
 * when emitted in a payload. These are the columns whose stored value is a
 * `/storage/...` public-disk path.
 */
export const STORAGE_COLUMNS: string[] = [
  "avatar",
  "cover_image",
  "image",
  "logo",
  "favicon",
  "og_image",
  "banner",
  "photo",
  "thumbnail",
];

/** An intentional raw emission: file (repo-relative), line substring, reason. */
export type AllowlistEntry = { file: string; needle: string; reason: string };

export const ALLOWLIST: AllowlistEntry[] = [
  {
    file: "artifacts/1inme/app/Modules/User/Controllers/SocialOAuthController.php",
    needle: "'avatar'     => $user->avatar ?? null",
    reason: "Socialite provider user — avatar is an EXTERNAL OAuth profile URL, never /storage/.",
  },
  {
    file: "artifacts/1inme/app/Modules/User/Models/SplashPage.php",
    needle: "'logo'          => $this->logo",
    reason: "Editor round-trip payload — the builder must read back the raw stored path to re-save it.",
  },
  {
    file: "artifacts/1inme/app/Modules/User/Models/SplashPage.php",
    needle: "'favicon'       => $this->favicon",
    reason: "Editor round-trip payload — raw stored path (public API surface resolves separately).",
  },
  {
    file: "artifacts/1inme/app/Modules/User/Models/SplashPage.php",
    needle: "'og_image'      => $this->og_image",
    reason: "Editor round-trip payload — raw stored path (public API surface resolves separately).",
  },
  {
    file: "artifacts/1inme/resources/views/user/links/settings/advanced.blade.php",
    needle: "value=\"{{ $link->favicon ?? '' }}\"",
    reason: "Settings form input round-trip — must repopulate the raw stored value so re-saving keeps it.",
  },
];

/** An offending raw emission. */
export type Offender = { file: string; line: number; column: string; text: string };

/** Matches a raw property read of a storage column: `->avatar` / `?->logo`, not a call, not `_url`. */
const COLUMN_RE = new RegExp(
  String.raw`(\??->)\s*(${STORAGE_COLUMNS.join("|")})\b(?!\s*\()`,
  "g",
);

/** Boolean / comparison contexts where the raw value is tested, not emitted. */
const BOOLEAN_CONTEXT_RE = /\(bool\)|!==|===|\bempty\(|\bfilled\(|\bis_null\(/;

/**
 * Blank single-line `//` / `#` comments and `/* … *\/` block comments,
 * newline-preserving, so commented-out code never trips the guard.
 */
export function stripPhpComments(src: string): string {
  let out = src.replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, " "));
  out = out
    .split("\n")
    .map((line) => {
      // Cheap string-awareness: only strip a `//` / `#` that is not inside quotes.
      let quote: string | null = null;
      for (let i = 0; i < line.length; i++) {
        const c = line[i];
        if (quote) {
          if (c === "\\") i++;
          else if (c === quote) quote = null;
          continue;
        }
        if (c === "'" || c === '"') quote = c;
        else if ((c === "/" && line[i + 1] === "/") || c === "#") return line.slice(0, i);
      }
      return line;
    })
    .join("\n");
  return out;
}

/**
 * Pure scanner: return every raw storage-column emission in `src`.
 * Exposed for unit tests.
 */
export function scanSource(relFile: string, src: string): Offender[] {
  const offenders: Offender[] = [];
  const rawLines = src.split("\n");
  const lines = stripPhpComments(src).split("\n");

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i] ?? "";
    if (!line.includes("=>")) continue;
    // Already resolved on this line, or a resolve( call opened on the previous
    // line and the property is its (wrapped) argument.
    const prev = lines[i - 1] ?? "";
    if (line.includes("PublicStorageUrl::resolve")) continue;
    if (prev.includes("PublicStorageUrl::resolve(") && !prev.includes(")")) continue;
    if (BOOLEAN_CONTEXT_RE.test(line)) continue;

    COLUMN_RE.lastIndex = 0;
    for (const m of line.matchAll(COLUMN_RE)) {
      const idx = m.index ?? 0;
      // Must be an array-value emission: `=>` appears before the property read.
      if (line.lastIndexOf("=>", idx) === -1) continue;
      // `$request->image` is write-time input handling, not payload emission.
      const before = line.slice(0, idx);
      if (/\$request$/.test(before.trimEnd())) continue;
      // Bare ternary truthiness test (`$x->avatar ? … : …`) — value not emitted
      // directly. `??` (null-coalescing raw emission) stays flagged.
      const after = line.slice(idx + m[0].length).trimStart();
      if (after.startsWith("?") && !after.startsWith("??")) continue;

      const allowed = ALLOWLIST.some(
        (a) => a.file === relFile && (rawLines[i] ?? "").includes(a.needle),
      );
      if (allowed) continue;

      offenders.push({
        file: relFile,
        line: i + 1,
        column: m[2] ?? "",
        text: (rawLines[i] ?? "").trim(),
      });
    }
  }
  return offenders;
}

/**
 * Blank `{{-- … --}}` Blade comments, newline-preserving, so commented-out
 * markup never trips the guard.
 */
export function stripBladeComments(src: string): string {
  return src.replace(/\{\{--[\s\S]*?--\}\}/g, (m) => m.replace(/[^\n]/g, " "));
}

/**
 * Pure Blade scanner: return every raw storage-column read emitted through a
 * `{{ … }}` / `{!! … !!}` echo in `src` (e.g. `<img src="{{ $u->avatar }}">`).
 * Exposed for unit tests.
 *
 * Safe (never flagged), mirroring the PHP scanner where applicable:
 *   - Echoes already wrapped in `PublicStorageUrl::resolve(...)`.
 *   - Method calls, `_url` properties (COLUMN_RE handles both).
 *   - Boolean / comparison contexts and bare ternary truthiness tests.
 *   - `old(...)` form-repopulation echoes — those are input-value round-trips
 *     that must re-emit the raw stored value.
 *   - Reads outside an echo (e.g. `@if($u->avatar)` conditions).
 *   - Explicit ALLOWLIST entries.
 */
export function scanBladeSource(relFile: string, src: string): Offender[] {
  const offenders: Offender[] = [];
  const rawLines = src.split("\n");
  const lines = stripBladeComments(src).split("\n");

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i] ?? "";
    if (!line.includes("{{") && !line.includes("{!!")) continue;

    COLUMN_RE.lastIndex = 0;
    for (const m of line.matchAll(COLUMN_RE)) {
      const idx = m.index ?? 0;
      // The read must sit inside an echo opened earlier on this line.
      const open = Math.max(line.lastIndexOf("{{", idx), line.lastIndexOf("{!!", idx));
      if (open === -1) continue;
      const between = line.slice(open, idx);
      if (between.includes("}}") || between.includes("!!}")) continue; // echo already closed
      if (between.includes("PublicStorageUrl::resolve")) continue;
      if (between.includes("old(")) continue; // form-repopulation round-trip
      if (BOOLEAN_CONTEXT_RE.test(between)) continue;
      // Bare ternary truthiness test — value not emitted directly.
      const after = line.slice(idx + m[0].length).trimStart();
      if (after.startsWith("?") && !after.startsWith("??")) continue;

      const allowed = ALLOWLIST.some(
        (a) => a.file === relFile && (rawLines[i] ?? "").includes(a.needle),
      );
      if (allowed) continue;

      offenders.push({
        file: relFile,
        line: i + 1,
        column: m[2] ?? "",
        text: (rawLines[i] ?? "").trim(),
      });
    }
  }
  return offenders;
}

/** List every `.php` file under SCAN_ROOTS. */
function listFiles(): string[] {
  const res = spawnSync("rg", ["--files", "-g", "*.php", ...SCAN_ROOTS], {
    cwd: REPO_ROOT,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.error || res.status === 2) {
    console.error("storage-url-resolve guard: failed to list files:", res.error?.message ?? res.stderr);
    process.exit(2);
  }
  return res.stdout.split("\n").map((l) => l.trim()).filter(Boolean);
}

/** List every `.blade.php` file under BLADE_SCAN_ROOTS. */
function listBladeFiles(): string[] {
  const res = spawnSync("rg", ["--files", "-g", "*.blade.php", ...BLADE_SCAN_ROOTS], {
    cwd: REPO_ROOT,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.error || res.status === 2) {
    console.error("storage-url-resolve guard: failed to list blade files:", res.error?.message ?? res.stderr);
    process.exit(2);
  }
  return res.stdout.split("\n").map((l) => l.trim()).filter(Boolean);
}

function staleAllowlistEntries(): AllowlistEntry[] {
  return ALLOWLIST.filter((a) => {
    try {
      const src = fs.readFileSync(path.join(REPO_ROOT, a.file), "utf8");
      return !src.includes(a.needle);
    } catch {
      return true;
    }
  });
}

function main(): void {
  const offenders: Offender[] = [];
  for (const rel of listFiles()) {
    let src: string;
    try {
      src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
    } catch {
      continue;
    }
    offenders.push(...scanSource(rel, src));
  }
  for (const rel of listBladeFiles()) {
    let src: string;
    try {
      src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
    } catch {
      continue;
    }
    offenders.push(...scanBladeSource(rel, src));
  }

  const stale = staleAllowlistEntries();
  if (stale.length > 0) {
    console.error("✗ storage-url-resolve guard: STALE allowlist entries (needle no longer found):");
    for (const a of stale) console.error(`  ${a.file}: "${a.needle}"`);
    console.error("Remove or update them in scripts/src/check-storage-url-resolve.ts.");
    process.exit(1);
  }

  if (offenders.length === 0) {
    console.log(
      "✓ storage-url-resolve guard passed — every storage-backed image column emission goes through PublicStorageUrl::resolve().",
    );
    process.exit(0);
  }

  console.error(
    "✗ storage-url-resolve guard FAILED — raw storage-backed image column(s) emitted without PublicStorageUrl::resolve():\n",
  );
  for (const o of offenders) console.error(`  ${o.file}:${o.line} [${o.column}]  ${o.text}`);
  console.error(
    `\n${offenders.length} match(es). A raw '/storage/...' value goes through the slow 302 bridge route ` +
      "instead of the CloudFront CDN.",
  );
  console.error(
    "Fix: wrap the value in \\App\\Support\\PublicStorageUrl::resolve(...) — or, if the value is " +
      "intentionally raw (external OAuth URL, editor round-trip, bundled asset), add an ALLOWLIST entry " +
      "with a reason in scripts/src/check-storage-url-resolve.ts.",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
