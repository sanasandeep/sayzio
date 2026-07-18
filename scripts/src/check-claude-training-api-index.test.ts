import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  REPO_ROOT,
  API_MD_REL,
  CLAUDE_TRAINING_REL,
  githubSlug,
  stripFencedCodeBlocks,
  extractHeadings,
  extractApiCrossLinks,
  checkDocs,
} from "./check-claude-training-api-index.js";

/**
 * Regression suite for the claude-training ⇄ api.md cross-link drift guard.
 *
 * The guard's value is that it fires when a §12 index cross-link points at an
 * api.md anchor that no longer exists, and stays quiet when every anchor
 * resolves. Both directions are pinned here, plus the GitHub-slug algorithm and
 * the code-fence skipping that keeps curl-example `# ...` comments from being
 * mistaken for headings. The live docs are also asserted to currently pass.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

describe("githubSlug", () => {
  it("lower-cases and hyphenates spaces", () => {
    expect(githubSlug("Sessions & security")).toBe("sessions--security");
  });

  it("strips parentheses and commas", () => {
    expect(githubSlug("AI (credits, Knowledge Bases, voice)")).toBe(
      "ai-credits-knowledge-bases-voice",
    );
  });

  it("keeps underscores (connector punctuation)", () => {
    expect(githubSlug("foo_bar baz")).toBe("foo_bar-baz");
  });

  it("removes em dashes but keeps the surrounding spaces as hyphens", () => {
    expect(githubSlug("New extension capabilities — endpoint mapping")).toBe(
      "new-extension-capabilities--endpoint-mapping",
    );
  });

  it("keeps regular hyphens", () => {
    expect(githubSlug("A/B links")).toBe("ab-links");
  });
});

describe("stripFencedCodeBlocks", () => {
  it("blanks fenced content while preserving line numbers", () => {
    const md = ["# Real", "```bash", "# Not a heading", "```", "## Also real"].join("\n");
    const out = stripFencedCodeBlocks(md).split("\n");
    expect(out[0]).toBe("# Real");
    expect(out[1]).toBe("");
    expect(out[2]).toBe("");
    expect(out[3]).toBe("");
    expect(out[4]).toBe("## Also real");
  });
});

describe("extractHeadings", () => {
  it("skips headings inside code fences and de-duplicates slugs", () => {
    const md = [
      "# Title",
      "## Group",
      "```bash",
      "# Register",
      "```",
      "## Group",
    ].join("\n");
    const heads = extractHeadings(md);
    expect(heads.map((h) => h.slug)).toEqual(["title", "group", "group-1"]);
    // "# Register" inside the fence must NOT appear.
    expect(heads.find((h) => h.text === "Register")).toBeUndefined();
  });
});

describe("extractApiCrossLinks", () => {
  it("captures both ./api.md# and api.md# anchor links", () => {
    const md = "See [Profile](./api.md#profile) and [Feed](api.md#feed).";
    expect(extractApiCrossLinks(md).map((l) => l.anchor)).toEqual(["profile", "feed"]);
  });

  it("ignores api.md links with no anchor fragment", () => {
    const md = "The contract is in [`api.md`](./api.md).";
    expect(extractApiCrossLinks(md)).toHaveLength(0);
  });

  it("ignores cross-links inside code fences", () => {
    const md = ["```", "[x](./api.md#gone)", "```"].join("\n");
    expect(extractApiCrossLinks(md)).toHaveLength(0);
  });
});

describe("checkDocs", () => {
  const apiMd = ["# API", "## Profile", "## Feed", "## Contents"].join("\n");

  it("passes when every cross-link resolves", () => {
    const claude = "[Profile](./api.md#profile) [Feed](./api.md#feed)";
    const res = checkDocs(apiMd, claude);
    expect(res.broken).toHaveLength(0);
  });

  it("flags a cross-link whose anchor no longer exists", () => {
    const claude = "[Profile](./api.md#profile) [Old](./api.md#removed-section)";
    const res = checkDocs(apiMd, claude);
    expect(res.broken.map((b) => b.anchor)).toEqual(["removed-section"]);
  });

  it("reports uncovered groups only once the index has cross-links", () => {
    const withLinks = checkDocs(apiMd, "[Profile](./api.md#profile)");
    // "Feed" is an uncovered ## group; "Contents" is ignored.
    expect(withLinks.uncovered.map((h) => h.slug)).toEqual(["feed"]);

    const noLinks = checkDocs(apiMd, "no cross links here");
    expect(noLinks.uncovered).toHaveLength(0);
  });
});

describe("live docs", () => {
  const apiMd = fs.readFileSync(path.join(REPO_ROOT, API_MD_REL), "utf8");
  const claude = fs.readFileSync(path.join(REPO_ROOT, CLAUDE_TRAINING_REL), "utf8");

  it("has no broken api.md cross-links in claude-training.md", () => {
    const res = checkDocs(apiMd, claude);
    const detail = res.broken.map((b) => `${b.raw} (line ${b.line})`).join(", ");
    expect(res.broken, `broken cross-links: ${detail}`).toHaveLength(0);
  });
});
