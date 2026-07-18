/**
 * Form-template key reference guard.
 *
 * Curated form templates live in a single catalog,
 * `artifacts/1inme/app/Modules/User/Services/FormTemplateCatalog.php`
 * (`FormTemplateCatalog::all()` → `::keys()`). The form create flow validates
 * the picker's `template` value against `::keys()` on store, and gracefully
 * falls back to Contact when a key no longer resolves.
 *
 * The failure this guards against
 * --------------------------------
 * A few code paths hardcode a specific template key as a LITERAL — the
 * controller's create/store default and the create-page Blade fallback both
 * seed `'contact'`. If someone renames or retires that key from the catalog,
 * the hardcoded literal silently points at a template that no longer exists:
 * the fallback stops resolving, `templateFields()` degrades to Form defaults,
 * and the picker pre-selects a card that isn't there — all with no error.
 *
 * What this check enforces (fast, static — parses PHP/Blade source, no server)
 * ---------------------------------------------------------------------------
 *   1. Every hardcoded template-key literal declared in KEY_REFERENCES below
 *      still exists in FormTemplateCatalog::all().
 *   2. Each declared reference is actually PRESENT in its source file, so a
 *      moved/renamed literal can't leave the guard asserting nothing.
 *
 * Adding a new hardcoded template-key literal to a code path? Declare it in
 * KEY_REFERENCES so this guard keeps it honest against the catalog.
 *
 * Run:  pnpm --filter @workspace/scripts run check:form-template-keys
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

export const CATALOG_REL =
  "artifacts/1inme/app/Modules/User/Services/FormTemplateCatalog.php";

/**
 * Hardcoded template-key literals in non-catalog code paths. Each entry names
 * the source file, the literal key it references, and a short reason so the
 * intent is clear. The guard fails if a `key` here is missing from the catalog
 * OR if `literal` no longer appears in `file` (a moved reference).
 */
export const KEY_REFERENCES: Array<{ file: string; key: string; literal: string; why: string }> = [
  {
    file: "artifacts/1inme/app/Modules/User/Controllers/FormController.php",
    key: "contact",
    literal: "'contact'",
    why: "create()/store() default when no ?template= or template value is supplied",
  },
  {
    file: "artifacts/1inme/resources/views/user/forms/create.blade.php",
    key: "contact",
    literal: "'contact'",
    why: "create-page Blade fallback for the pre-selected / checked template card",
  },
];

/**
 * Parse the top-level template keys declared in FormTemplateCatalog::all().
 * Template entries are `'key' => [` at exactly 12 spaces of indentation inside
 * the `all()` method; nested field definitions start with `[` (no quoted key)
 * and are more deeply indented, so this stays precise.
 */
export function parseCatalogKeys(src: string): Set<string> {
  const keys = new Set<string>();
  const start = src.indexOf("function all()");
  const body = start === -1 ? src : src.slice(start);
  const re = /^ {12}'([a-z0-9_]+)'\s*=>\s*\[/gm;
  let m: RegExpExecArray | null;
  while ((m = re.exec(body)) !== null) keys.add(m[1]);
  return keys;
}

export type KeyProblem = { kind: string; detail: string };

export function checkFormTemplateKeys(catalogSrc: string, readFile: (rel: string) => string): KeyProblem[] {
  const problems: KeyProblem[] = [];

  const keys = parseCatalogKeys(catalogSrc);
  if (keys.size === 0) {
    problems.push({
      kind: "no-catalog-keys",
      detail:
        "Parsed zero template keys from FormTemplateCatalog::all() — the catalog format changed. Update this guard.",
    });
    return problems;
  }

  for (const ref of KEY_REFERENCES) {
    if (!keys.has(ref.key)) {
      problems.push({
        kind: "unknown-template-key",
        detail: `${ref.file} references template key "${ref.key}" (${ref.why}), but FormTemplateCatalog::all() no longer contains it.`,
      });
    }
    let refSrc = "";
    try {
      refSrc = readFile(ref.file);
    } catch (e) {
      problems.push({
        kind: "reference-file-missing",
        detail: `cannot read ${ref.file}: ${(e as Error).message}`,
      });
      continue;
    }
    if (!refSrc.includes(ref.literal)) {
      problems.push({
        kind: "reference-literal-missing",
        detail: `${ref.file} no longer contains the literal ${ref.literal} — the reference moved or changed. Update KEY_REFERENCES so this guard keeps verifying it.`,
      });
    }
  }

  return problems;
}

function main(): void {
  const readRel = (rel: string) => fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");

  let catalogSrc: string;
  try {
    catalogSrc = readRel(CATALOG_REL);
  } catch (e) {
    console.error(`form-template-keys guard: cannot read ${CATALOG_REL}: ${(e as Error).message}`);
    process.exit(2);
  }

  const problems = checkFormTemplateKeys(catalogSrc, readRel);

  if (problems.length === 0) {
    console.log(
      "✓ form-template-keys guard passed — every hardcoded template-key literal still exists in FormTemplateCatalog.",
    );
    process.exit(0);
  }

  console.error("✗ form-template-keys guard FAILED:\n");
  for (const p of problems) {
    console.error(`  [${p.kind}] ${p.detail}`);
  }
  console.error(
    `\n${problems.length} problem(s). A hardcoded template key that the catalog no longer contains silently breaks the form create fallback.`,
  );
  console.error(
    "Fix the catalog/reference, or update KEY_REFERENCES in scripts/src/check-form-template-keys.ts.",
  );
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
