import { describe, it, expect } from "vitest";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { parse as parseYaml } from "yaml";
import {
  REPO_ROOT,
  REQUIRED_CHECKS_MANIFEST,
  WORKFLOW_FILES,
  expandMatrix,
  expandName,
  jobCheckNames,
  checkWorkflowParity,
  checkAllWorkflows,
  discoverWorkflowFiles,
  usesPassthroughScheme,
  collectCheckNames,
  loadRequiredChecks,
  assessRequiredCoverage,
} from "./check-ci-passthrough-names.js";

/**
 * Regression suite for the CI passthrough-name parity guard.
 *
 * Pins the behaviours that keep required checks from deadlocking:
 *   - the LIVE workflows pass (real names ↔ passthrough names are balanced),
 *   - a renamed real job with a stale passthrough entry is flagged both ways,
 *   - matrix expansion handles both the include-list and plain-list forms,
 *   - the committed required-check manifest is the source of truth: a required
 *     name with no producer (deleted/renamed workflow) or no passthrough fails.
 */

const readDoc = (rel: string) =>
  parseYaml(fs.readFileSync(path.join(REPO_ROOT, rel), "utf8"));

describe("live workflows", () => {
  it("both database-safety workflows pass with zero parity problems", () => {
    expect(checkAllWorkflows()).toEqual([]);
  });

  it.each(WORKFLOW_FILES)("%s: every real check name has a passthrough", (rel) => {
    expect(checkWorkflowParity(rel, readDoc(rel))).toEqual([]);
  });
});

describe("matrix expansion", () => {
  it("expands the include-list form (real migrate matrix)", () => {
    const combos = expandMatrix({
      include: [{ display_name: "PostgreSQL" }, { display_name: "MySQL" }],
    });
    expect(combos.map((c) => c.display_name)).toEqual(["PostgreSQL", "MySQL"]);
  });

  it("expands the plain-list form (passthrough matrix)", () => {
    const combos = expandMatrix({ check_name: ["A", "B", "C"] });
    expect(combos.map((c) => c.check_name)).toEqual(["A", "B", "C"]);
  });

  it("returns a single empty combo when there is no matrix", () => {
    expect(expandMatrix(undefined)).toEqual([{}]);
  });

  it("substitutes matrix references in a name template", () => {
    expect(expandName("migrate cycle (${{ matrix.display_name }})", { display_name: "MySQL" })).toBe(
      "migrate cycle (MySQL)",
    );
  });

  it("produces every concrete check name for a matrixed job", () => {
    expect(
      jobCheckNames({
        name: "migrate cycle (${{ matrix.display_name }})",
        strategy: { matrix: { display_name: ["PostgreSQL", "MySQL"] } },
      }),
    ).toEqual(["migrate cycle (PostgreSQL)", "migrate cycle (MySQL)"]);
  });
});

describe("parity detection", () => {
  const baseDoc = () => ({
    jobs: {
      changes: { name: "Detect changes", outputs: { onein: "x" } },
      real: {
        name: "guard ${{ matrix.n }}",
        strategy: { matrix: { n: ["A", "B"] } },
      },
      "real-passthrough": {
        name: "guard ${{ matrix.n }}",
        strategy: { matrix: { n: ["A", "B"] } },
      },
    },
  });

  it("is green when real and passthrough names match exactly", () => {
    expect(checkWorkflowParity("x.yml", baseDoc())).toEqual([]);
  });

  it("flags a real job whose passthrough entry is missing", () => {
    const doc = baseDoc();
    // Rename a real matrix value without updating the passthrough.
    doc.jobs.real.strategy.matrix.n = ["A", "RENAMED"];
    const problems = checkWorkflowParity("x.yml", doc);
    expect(problems.some((p) => p.kind === "real-without-passthrough")).toBe(true);
    expect(problems.some((p) => p.kind === "passthrough-without-real")).toBe(true);
  });

  it("flags a stale passthrough entry with no real counterpart", () => {
    const doc = baseDoc();
    doc.jobs["real-passthrough"].strategy.matrix.n = ["A", "B", "GHOST"];
    const problems = checkWorkflowParity("x.yml", doc);
    expect(problems.some((p) => p.kind === "passthrough-without-real")).toBe(true);
  });

  it("flags a workflow that has real jobs but no passthrough", () => {
    const doc = {
      jobs: {
        changes: { name: "Detect changes", outputs: { onein: "x" } },
        real: { name: "guard" },
      },
    };
    const problems = checkWorkflowParity("x.yml", doc);
    expect(problems.some((p) => p.kind === "no-passthrough-job")).toBe(true);
  });
});

describe("workflow discovery", () => {
  it("discovers both live safety workflows", () => {
    const files = discoverWorkflowFiles();
    expect(files).toContain(".github/workflows/laravel-migrations.yml");
    expect(files).toContain(".github/workflows/laravel-tests.yml");
  });

  it("returns an empty list when the workflows dir is absent", () => {
    expect(discoverWorkflowFiles(path.join(REPO_ROOT, "does-not-exist"))).toEqual([]);
  });

  it("recognises the passthrough scheme via a change detector or -passthrough job", () => {
    expect(
      usesPassthroughScheme({ jobs: { changes: { outputs: { onein: "x" } } } }),
    ).toBe(true);
    expect(
      usesPassthroughScheme({ jobs: { "foo-passthrough": { name: "foo" } } }),
    ).toBe(true);
    expect(usesPassthroughScheme({ jobs: { lint: { name: "lint" } } })).toBe(false);
  });

  it("collectCheckNames splits real and passthrough names", () => {
    const { real, passthrough } = collectCheckNames({
      jobs: {
        changes: { name: "Detect", outputs: { onein: "x" } },
        real: { name: "guard A" },
        "real-passthrough": { name: "guard A" },
      },
    });
    expect([...real]).toEqual(["guard A"]);
    expect([...passthrough]).toEqual(["guard A"]);
  });
});

describe("required-check manifest", () => {
  it("loads with every live required name produced by a real job", () => {
    const { names, problem } = loadRequiredChecks();
    expect(problem).toBeUndefined();
    expect(names.length).toBeGreaterThan(0);

    const allReal = new Set<string>();
    for (const rel of WORKFLOW_FILES) {
      for (const n of collectCheckNames(readDoc(rel)).real) allReal.add(n);
    }
    for (const name of names) expect(allReal.has(name)).toBe(true);
  });

  it("the manifest exactly matches the live real-guard names (no drift either way)", () => {
    const { names } = loadRequiredChecks();
    const allReal = new Set<string>();
    for (const rel of WORKFLOW_FILES) {
      for (const n of collectCheckNames(readDoc(rel)).real) allReal.add(n);
    }
    // Every required name has a real producer, and no live safety job is
    // silently absent from the manifest.
    expect([...names].sort()).toEqual([...allReal].sort());
  });

  it("reports manifest-read-error when the manifest is missing", () => {
    const { problem } = loadRequiredChecks(path.join(REPO_ROOT, "does-not-exist"));
    expect(problem?.kind).toBe("manifest-read-error");
  });
});

describe("required-check coverage", () => {
  const real = new Set(["A", "B"]);
  const pass = new Set(["A", "B"]);

  it("is green when every required name has a real producer and a passthrough", () => {
    expect(assessRequiredCoverage(["A", "B"], real, pass)).toEqual([]);
  });

  it("flags a required name with no producer (deleted/renamed workflow)", () => {
    const problems = assessRequiredCoverage(["A", "GONE"], real, pass);
    expect(problems.some((p) => p.kind === "required-without-producer")).toBe(true);
  });

  it("flags a required name with no passthrough (deadlock risk)", () => {
    const problems = assessRequiredCoverage(["A"], new Set(["A"]), new Set());
    expect(problems.some((p) => p.kind === "required-without-passthrough")).toBe(true);
  });

  it("checkAllWorkflows surfaces a required name whose whole workflow vanished", () => {
    // Build a throwaway fixture repo that has the manifest but NO workflow
    // files at all — the exact "someone deleted the whole safety workflow"
    // scenario. Running the full guard end-to-end must flag every required
    // name as having no producer and no passthrough.
    const fixture = fs.mkdtempSync(path.join(os.tmpdir(), "ci-passthrough-"));
    try {
      fs.mkdirSync(path.join(fixture, ".github", "workflows"), { recursive: true });
      fs.copyFileSync(
        path.join(REPO_ROOT, REQUIRED_CHECKS_MANIFEST),
        path.join(fixture, REQUIRED_CHECKS_MANIFEST),
      );

      const { names } = loadRequiredChecks(fixture);
      const problems = checkAllWorkflows(fixture);

      expect(problems.filter((p) => p.kind === "required-without-producer")).toHaveLength(
        names.length,
      );
      expect(problems.filter((p) => p.kind === "required-without-passthrough")).toHaveLength(
        names.length,
      );
    } finally {
      fs.rmSync(fixture, { recursive: true, force: true });
    }
  });
});
