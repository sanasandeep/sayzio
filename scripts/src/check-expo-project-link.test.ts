import { describe, it, expect, afterAll } from "vitest";
import { spawnSync } from "node:child_process";
import { cpSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";

/**
 * Negative tests for the two expo-project-link guards:
 *   - artifacts/1inme-mobile/scripts/check-expo-project-link.mjs
 *   - sayzio-dialer-standalone/scripts/check-expo-project-link.mjs
 *
 * Both protect the shared free-plan EAS Android build quota by refusing to
 * let a tampered app.json (wrong owner, placeholder projectId, injected
 * extra.eas.build block) reach an EAS build. Until now they were only
 * tamper-tested manually, so a regression that made them silently pass on a
 * bad config would go unnoticed until a build was wasted.
 *
 * Each guard resolves app.json relative to its own script location
 * (`<scriptDir>/../app.json`), so we exercise tampered configs by copying the
 * REAL guard script into a temp sandbox (`<tmp>/scripts/check.mjs`) next to a
 * tampered `<tmp>/app.json`, then running it with plain `node`. The untouched
 * repo app.json files are also asserted to pass (guard + config are both
 * healthy at HEAD).
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const scriptsRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const repoRoot = path.resolve(scriptsRoot, "..");

const GUARDS = [
  {
    name: "1inme-mobile",
    guardPath: path.join(repoRoot, "artifacts/1inme-mobile/scripts/check-expo-project-link.mjs"),
    appJsonPath: path.join(repoRoot, "artifacts/1inme-mobile/app.json"),
    failHeader: "expo-project-link guard FAILED for artifacts/1inme-mobile/app.json:",
  },
  {
    name: "dialer-standalone",
    guardPath: path.join(repoRoot, "sayzio-dialer-standalone/scripts/check-expo-project-link.mjs"),
    appJsonPath: path.join(repoRoot, "sayzio-dialer-standalone/app.json"),
    failHeader: "expo-project-link guard FAILED for sayzio-dialer-standalone/app.json:",
  },
] as const;

const tempDirs: string[] = [];
afterAll(() => {
  for (const dir of tempDirs) rmSync(dir, { recursive: true, force: true });
});

type GuardSpec = (typeof GUARDS)[number];

/** Copy the real guard into a sandbox next to a (possibly tampered) app.json and run it. */
const runGuardOn = (guard: GuardSpec, mutate: (config: any) => void) => {
  const sandbox = mkdtempSync(path.join(tmpdir(), "expo-link-guard-"));
  tempDirs.push(sandbox);
  mkdirSync(path.join(sandbox, "scripts"));
  cpSync(guard.guardPath, path.join(sandbox, "scripts", "check-expo-project-link.mjs"));
  const config = JSON.parse(readFileSync(guard.appJsonPath, "utf8"));
  mutate(config);
  writeFileSync(path.join(sandbox, "app.json"), JSON.stringify(config, null, 2));
  return spawnSync(process.execPath, [path.join(sandbox, "scripts", "check-expo-project-link.mjs")], {
    encoding: "utf8",
    timeout: 30_000,
  });
};

describe.each(GUARDS)("expo-project-link guard: $name", (guard) => {
  it("passes on the untouched repo app.json", () => {
    const res = spawnSync(process.execPath, [guard.guardPath], { encoding: "utf8", timeout: 30_000 });
    expect(res.status, `guard should pass at HEAD; output:\n${res.stdout}\n${res.stderr}`).toBe(0);
    expect(res.stdout).toContain("expo-project-link guard OK");
  });

  it("fails on wrong owner", () => {
    const res = runGuardOn(guard, (c) => {
      c.expo.owner = "someone-else";
    });
    expect(res.status, `output:\n${res.stdout}\n${res.stderr}`).not.toBe(0);
    expect(res.status).not.toBeNull();
    expect(res.stderr).toContain(guard.failHeader);
    expect(res.stderr).toContain('expo.owner is "someone-else" — expected "eefind"');
  });

  it("fails on a non-UUID (placeholder) projectId", () => {
    const res = runGuardOn(guard, (c) => {
      c.expo.extra = c.expo.extra ?? {};
      c.expo.extra.eas = { ...c.expo.extra.eas, projectId: "your-project-id-here" };
    });
    expect(res.status, `output:\n${res.stdout}\n${res.stderr}`).not.toBe(0);
    expect(res.status).not.toBeNull();
    expect(res.stderr).toContain('extra.eas.projectId is "your-project-id-here" — expected a UUID');
  });

  it("fails on a missing projectId", () => {
    const res = runGuardOn(guard, (c) => {
      if (c.expo.extra?.eas) delete c.expo.extra.eas.projectId;
    });
    expect(res.status, `output:\n${res.stdout}\n${res.stderr}`).not.toBe(0);
    expect(res.stderr).toContain("extra.eas.projectId is undefined — expected a UUID");
  });

  it("fails when extra.eas.build.experimental is present", () => {
    const res = runGuardOn(guard, (c) => {
      c.expo.extra = c.expo.extra ?? {};
      c.expo.extra.eas = {
        ...c.expo.extra.eas,
        build: { experimental: { ios: { appExtensions: [{ targetName: "ShareExtension" }] } } },
      };
    });
    expect(res.status, `output:\n${res.stdout}\n${res.stderr}`).not.toBe(0);
    expect(res.status).not.toBeNull();
    expect(res.stderr).toContain("extra.eas.build.experimental block present in app.json");
  });

  it("fails when a bare extra.eas.build block is present (no .experimental)", () => {
    const res = runGuardOn(guard, (c) => {
      c.expo.extra = c.expo.extra ?? {};
      c.expo.extra.eas = { ...c.expo.extra.eas, build: {} };
    });
    expect(res.status, `output:\n${res.stdout}\n${res.stderr}`).not.toBe(0);
    expect(res.stderr).toContain("extra.eas.build block present in app.json");
    expect(res.stderr).not.toContain("extra.eas.build.experimental block");
  });

  it("reports all three violations together", () => {
    const res = runGuardOn(guard, (c) => {
      c.expo.owner = "not-eefind";
      c.expo.extra = { eas: { projectId: "nope", build: { experimental: {} } } };
    });
    expect(res.status, `output:\n${res.stdout}\n${res.stderr}`).not.toBe(0);
    expect(res.stderr).toContain(guard.failHeader);
    expect(res.stderr).toContain("expo.owner is");
    expect(res.stderr).toContain("extra.eas.projectId is");
    expect(res.stderr).toContain("extra.eas.build.experimental block present");
  });

  it("fails on unparseable app.json", () => {
    const sandbox = mkdtempSync(path.join(tmpdir(), "expo-link-guard-"));
    tempDirs.push(sandbox);
    mkdirSync(path.join(sandbox, "scripts"));
    cpSync(guard.guardPath, path.join(sandbox, "scripts", "check-expo-project-link.mjs"));
    writeFileSync(path.join(sandbox, "app.json"), "{ not json");
    const res = spawnSync(
      process.execPath,
      [path.join(sandbox, "scripts", "check-expo-project-link.mjs")],
      { encoding: "utf8", timeout: 30_000 },
    );
    expect(res.status).not.toBe(0);
    expect(res.stderr).toContain("cannot read/parse");
  });
});
