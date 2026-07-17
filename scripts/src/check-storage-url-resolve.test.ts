import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  ALLOWLIST,
  REPO_ROOT,
  SCAN_ROOTS,
  STORAGE_COLUMNS,
  scanSource,
  stripPhpComments,
} from "./check-storage-url-resolve.js";

/**
 * Regression suite for the storage-url-resolve guard.
 *
 * The guard's value is that it fires when a payload builder emits a raw
 * storage-backed image column (`'avatar' => $x->avatar`) without routing it
 * through PublicStorageUrl::resolve() — silently reintroducing the slow
 * `/storage` 302 bridge path — while staying quiet on resolved values, method
 * calls, `_url` properties, boolean tests, and allowlisted intentional raws.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const FILE = "artifacts/1inme/app/Modules/Api/Controllers/Fake.php";
const cols = (src: string) => scanSource(FILE, src).map((o) => o.column);

describe("scanSource — flags raw storage-column emissions", () => {
  it("flags a raw avatar emission", () => {
    expect(cols("'avatar' => $u->avatar,")).toEqual(["avatar"]);
  });

  it("flags a nullsafe chained read", () => {
    expect(cols("'viewer_avatar' => $c->viewer?->avatar,")).toEqual(["avatar"]);
  });

  it("flags a `?? null` raw emission (value still raw)", () => {
    expect(cols("'logo' => $s->logo ?? null,")).toEqual(["logo"]);
  });

  it("flags every storage column in the set", () => {
    for (const c of STORAGE_COLUMNS) {
      expect(cols(`'x' => $m->${c},`)).toEqual([c]);
    }
  });

  it("flags a raw column wrapped in an unrelated helper", () => {
    expect(cols("'coverImage' => $this->absoluteUrl($p->cover_image),")).toEqual(["cover_image"]);
  });

  it("reports the correct line number and text", () => {
    const src = "<?php\nreturn [\n    'avatar' => $u->avatar,\n];";
    const [o] = scanSource(FILE, src);
    expect(o?.line).toBe(3);
    expect(o?.text).toContain("$u->avatar");
  });
});

describe("scanSource — stays quiet on benign patterns", () => {
  it("ignores a resolved emission", () => {
    expect(cols("'avatar' => \\App\\Support\\PublicStorageUrl::resolve($u->avatar),")).toEqual([]);
  });

  it("ignores a resolve( call split across two lines", () => {
    const src = "'avatar' => \\App\\Support\\PublicStorageUrl::resolve(\n    $u->avatar\n),";
    // Property line has no `=>` anyway, but the wrap check also covers
    // `'x' => resolve(` on the previous line with the value on the next.
    expect(scanSource(FILE, src)).toEqual([]);
  });

  it("ignores method calls (helper owns its resolution)", () => {
    expect(cols("'favicon' => $this->favicon($link),")).toEqual([]);
    expect(cols("'photo_url' => $c->photoUrl(),")).toEqual([]);
  });

  it("ignores `_url`-suffixed full-URL properties", () => {
    expect(cols("'avatar_url' => $c->avatar_url,")).toEqual([]);
    expect(cols("'photo_url' => $p->photo_url,")).toEqual([]);
    expect(cols("'image_url' => $it->image_url,")).toEqual([]);
  });

  it("ignores boolean / comparison contexts", () => {
    expect(cols("'has_avatar' => (bool) $u->avatar,")).toEqual([]);
    expect(cols("'set' => $u->avatar !== null,")).toEqual([]);
    expect(cols("'missing' => empty($u->avatar),")).toEqual([]);
  });

  it("ignores a bare ternary truthiness test", () => {
    expect(cols("'x' => $u->avatar ? 'yes' : 'no',")).toEqual([]);
  });

  it("ignores $request-> input reads (write-time, not emission)", () => {
    expect(cols("'image' => $request->image,")).toEqual([]);
  });

  it("ignores lines without an array emission (`=>`)", () => {
    expect(cols("return $u->avatar;")).toEqual([]);
  });

  it("ignores commented-out code", () => {
    expect(cols("// 'avatar' => $u->avatar,")).toEqual([]);
    expect(cols("/* 'avatar' => $u->avatar, */")).toEqual([]);
  });

  it("does not strip a // inside a string", () => {
    const out = stripPhpComments("'url' => 'https://x.com', // trailing");
    expect(out).toContain("https://x.com");
    expect(out).not.toContain("trailing");
  });

  it("honors the allowlist for the exact file + needle", () => {
    const entry = ALLOWLIST[0]!;
    const src = `${entry.needle},`;
    expect(scanSource(entry.file, src)).toEqual([]);
    // Same line in a different file IS flagged.
    expect(scanSource(FILE, src).length).toBeGreaterThan(0);
  });
});

describe("live-tree invariants", () => {
  it("every allowlist needle still exists in its file", () => {
    for (const a of ALLOWLIST) {
      const src = fs.readFileSync(path.join(REPO_ROOT, a.file), "utf8");
      expect(src, `${a.file} should contain "${a.needle}"`).toContain(a.needle);
    }
  });

  it("scan roots exist", () => {
    for (const r of SCAN_ROOTS) {
      expect(fs.existsSync(path.join(REPO_ROOT, r)), r).toBe(true);
    }
  });

  it("the live tree is clean (no unresolved raw emissions)", () => {
    const offenders: string[] = [];
    const walk = (dir: string) => {
      for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) walk(p);
        else if (e.name.endsWith(".php")) {
          const rel = path.relative(REPO_ROOT, p);
          for (const o of scanSource(rel, fs.readFileSync(p, "utf8"))) {
            offenders.push(`${o.file}:${o.line} ${o.text}`);
          }
        }
      }
    };
    for (const r of SCAN_ROOTS) walk(path.join(REPO_ROOT, r));
    expect(offenders).toEqual([]);
  });
});
