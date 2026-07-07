import { describe, it, expect, afterEach } from "vitest";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import {
  REPO_ROOT,
  PARTIAL_REL,
  checkPartialSource,
  scanViewSource,
  listPublicViews,
} from "./check-fontawesome-loader.js";

/**
 * Regression suite for the Font Awesome loader guard.
 *
 * Two directions are pinned:
 *   - the pure checkers flag each regression class (partial losing its swap
 *     link / safety-net script / noscript fallback; a public view rolling its
 *     own FA <link>) and stay quiet on the current healthy state;
 *   - the REAL gate script, spawned as a subprocess, exits 0 on the clean tree
 *     and non-zero when poisoned (fail-closed proof — exit-code plumbing is
 *     exactly what's under test, per the gate meta-test pattern).
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const partialAbs = path.join(REPO_ROOT, PARTIAL_REL);
const healthyPartial = fs.readFileSync(partialAbs, "utf8");

describe("checkPartialSource — current partial is healthy", () => {
  it("reports zero problems for the real partial on disk", () => {
    expect(checkPartialSource(healthyPartial)).toEqual([]);
  });
});

describe("checkPartialSource — flags each regression class", () => {
  it("flags removal of the media=print swap link", () => {
    const src = healthyPartial.replace(/<link rel="stylesheet"[^>]*data-fa-async[^>]*>/i, "");
    expect(checkPartialSource(src).join("\n")).toMatch(/swap link/i);
  });

  it("flags a swap link that lost its data-fa-async tag", () => {
    const src = healthyPartial.replace(/\s*data-fa-async/, "");
    const problems = checkPartialSource(src).join("\n");
    expect(problems).toMatch(/swap link|safety-net/i);
    expect(problems.length).toBeGreaterThan(0);
  });

  it("flags removal of the safety-net script", () => {
    const src = healthyPartial.replace(/<script>[\s\S]*<\/script>/i, "");
    expect(checkPartialSource(src).join("\n")).toMatch(/safety-net script/i);
  });

  it("flags a safety-net script missing the window load fallback", () => {
    const src = healthyPartial.replace(/window\.addEventListener\(\s*['"]load['"][^)]*\);?/, "");
    expect(checkPartialSource(src).join("\n")).toMatch(/safety-net script/i);
  });

  it("flags a safety-net script missing the DOMContentLoaded hook", () => {
    const src = healthyPartial.replace(/document\.addEventListener\(\s*['"]DOMContentLoaded['"][^)]*\);?/, "");
    expect(checkPartialSource(src).join("\n")).toMatch(/safety-net script/i);
  });

  it("flags removal of the <noscript> fallback", () => {
    const src = healthyPartial.replace(/<noscript>[\s\S]*?<\/noscript>/i, "");
    expect(checkPartialSource(src).join("\n")).toMatch(/noscript/i);
  });
});

describe("scanViewSource — raw FA links in public views", () => {
  it("flags a plain blocking FA stylesheet link", () => {
    const src = `<link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">`;
    expect(scanViewSource("f.blade.php", src)).toHaveLength(1);
  });

  it("flags a hand-rolled print-swap FA link", () => {
    const src = `<link rel="stylesheet" href="/css/vendor/fontawesome-free-6.5.1/css/all.min.css" media="print" onload="this.media='all'">`;
    expect(scanViewSource("f.blade.php", src)).toHaveLength(1);
  });

  it("flags a CDN font-awesome link", () => {
    const src = `<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">`;
    expect(scanViewSource("f.blade.php", src)).toHaveLength(1);
  });

  it("flags an FA preload link", () => {
    const src = `<link rel="preload" as="style" href="/css/vendor/fontawesome-free-6.5.1/css/all.min.css">`;
    expect(scanViewSource("f.blade.php", src)).toHaveLength(1);
  });

  it("ignores the @include of the shared partial", () => {
    expect(scanViewSource("f.blade.php", `@include('common.partials.fontawesome')`)).toEqual([]);
  });

  it("ignores non-FA stylesheet links", () => {
    expect(
      scanViewSource("f.blade.php", `<link rel="stylesheet" href="{{ asset('css/app.css') }}">`),
    ).toEqual([]);
  });

  it("ignores an FA link inside a Blade comment", () => {
    const src = `{{-- <link rel="stylesheet" href="/css/vendor/fontawesome-free-6.5.1/css/all.min.css"> --}}`;
    expect(scanViewSource("f.blade.php", src)).toEqual([]);
  });
});

describe("live repo — public views are clean today", () => {
  it("no public-facing view outside the partial ships a raw FA <link>", () => {
    const offenders = listPublicViews()
      .filter((rel) => path.normalize(rel) !== path.normalize(PARTIAL_REL))
      .flatMap((rel) => {
        try {
          return scanViewSource(rel, fs.readFileSync(path.join(REPO_ROOT, rel), "utf8"));
        } catch {
          return [];
        }
      });
    expect(offenders).toEqual([]);
  });
});

// ---------------------------------------------------------------------------
// Meta-test: drive the REAL gate as a subprocess, both directions.
// ---------------------------------------------------------------------------

const GATE = path.join(REPO_ROOT, "scripts/src/check-fontawesome-loader.ts");
const TSX = path.join(REPO_ROOT, "scripts/node_modules/.bin/tsx");
const POISON_VIEW = path.join(
  REPO_ROOT,
  "artifacts/1inme/resources/views/public/__fa_guard_poison.blade.php",
);

function runGate() {
  return spawnSync(TSX, [GATE], { cwd: REPO_ROOT, encoding: "utf8", timeout: 120_000 });
}

describe("gate meta-test (subprocess, fail-closed)", () => {
  // Defensive pre-clean: a leaked poison file from an aborted run must not
  // fail the clean case for the wrong reason.
  afterEach(() => {
    fs.rmSync(POISON_VIEW, { force: true });
    fs.writeFileSync(partialAbs, healthyPartial);
  });

  it(
    "exits 0 on the clean tree",
    () => {
      fs.rmSync(POISON_VIEW, { force: true });
      const res = runGate();
      expect(res.status).toBe(0);
      expect(res.stdout).toMatch(/passed/i);
    },
    120_000,
  );

  it(
    "exits non-zero when a public view ships a raw FA <link>",
    () => {
      fs.writeFileSync(
        POISON_VIEW,
        `<link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">\n`,
      );
      try {
        const res = runGate();
        expect(res.status).not.toBeNull();
        expect(res.status).not.toBe(0);
        expect(res.stderr).toMatch(/__fa_guard_poison/);
      } finally {
        fs.rmSync(POISON_VIEW, { force: true });
      }
    },
    120_000,
  );

  it(
    "exits non-zero when the partial loses its safety-net script",
    () => {
      fs.writeFileSync(partialAbs, healthyPartial.replace(/<script>[\s\S]*<\/script>/i, ""));
      try {
        const res = runGate();
        expect(res.status).not.toBeNull();
        expect(res.status).not.toBe(0);
        expect(res.stderr).toMatch(/safety-net/i);
      } finally {
        fs.writeFileSync(partialAbs, healthyPartial);
      }
    },
    120_000,
  );
});
