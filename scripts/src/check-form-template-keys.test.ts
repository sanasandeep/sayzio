import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  REPO_ROOT,
  CATALOG_REL,
  KEY_REFERENCES,
  parseCatalogKeys,
  checkFormTemplateKeys,
} from "./check-form-template-keys.js";

/**
 * Regression suite for the form-template key reference guard.
 *
 * Pins:
 *   - the LIVE catalog + references pass (baseline is green),
 *   - a reference to a key the catalog lacks is flagged,
 *   - a reference whose literal has vanished from its file is flagged.
 */

const readRel = (rel: string) => fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
const catalogSrc = () => readRel(CATALOG_REL);

describe("live catalog + references", () => {
  it("passes the guard with zero problems", () => {
    expect(checkFormTemplateKeys(catalogSrc(), readRel)).toEqual([]);
  });

  it("parses a healthy set of template keys including 'contact'", () => {
    const keys = parseCatalogKeys(catalogSrc());
    expect(keys.size).toBeGreaterThan(5);
    expect(keys.has("contact")).toBe(true);
    expect(keys.has("blank")).toBe(true);
  });

  it("every declared reference key exists in the catalog", () => {
    const keys = parseCatalogKeys(catalogSrc());
    for (const ref of KEY_REFERENCES) {
      expect(keys.has(ref.key)).toBe(true);
    }
  });
});

describe("parseCatalogKeys", () => {
  it("reads only top-level 12-space template keys, not nested field defs", () => {
    const src = `
    public static function all(): array
    {
        return [
            'contact' => [
                'label' => 'Contact',
                'fields' => [
                    ['id' => 'name', 'type' => 'text'],
                ],
            ],
            'lead' => [
                'label' => 'Lead',
                'fields' => [],
            ],
        ];
    }`;
    expect([...parseCatalogKeys(src)].sort()).toEqual(["contact", "lead"]);
  });
});

describe("failure modes", () => {
  const catalogMissingContact = `
    public static function all(): array
    {
        return [
            'lead' => [
                'label' => 'Lead',
                'fields' => [],
            ],
        ];
    }`;

  it("flags a reference to a key the catalog no longer contains", () => {
    const problems = checkFormTemplateKeys(catalogMissingContact, readRel);
    expect(
      problems.some((p) => p.kind === "unknown-template-key" && p.detail.includes("contact")),
    ).toBe(true);
  });

  it("flags a reference whose literal has vanished from its file", () => {
    const problems = checkFormTemplateKeys(catalogSrc(), () => "no literal here");
    expect(problems.some((p) => p.kind === "reference-literal-missing")).toBe(true);
  });

  it("flags an empty/unparseable catalog", () => {
    const problems = checkFormTemplateKeys("nothing here", readRel);
    expect(problems.some((p) => p.kind === "no-catalog-keys")).toBe(true);
  });
});
