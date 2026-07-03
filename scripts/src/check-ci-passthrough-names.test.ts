import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import { parse as parseYaml } from "yaml";
import {
  REPO_ROOT,
  WORKFLOW_FILES,
  expandMatrix,
  expandName,
  jobCheckNames,
  checkWorkflowParity,
  checkAllWorkflows,
} from "./check-ci-passthrough-names.js";

/**
 * Regression suite for the CI passthrough-name parity guard.
 *
 * Pins the behaviours that keep required checks from deadlocking:
 *   - the LIVE workflows pass (real names ↔ passthrough names are balanced),
 *   - a renamed real job with a stale passthrough entry is flagged both ways,
 *   - matrix expansion handles both the include-list and plain-list forms.
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
