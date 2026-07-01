import { describe, it, expect } from "vitest";
import { readFileSync } from "node:fs";
import path from "node:path";

import { DEFAULT_CONTACT_CONTENT } from "./contact-content";

/**
 * Drift guard for the marketing site's backup contact details (task #3265).
 *
 * The correct contact details (EEFind Private Limited, Banjara Hills,
 * hello@sayzio.app, blank phone) live in TWO places:
 *   1. the product app's PHP defaults —
 *      SitePagesContent::contactExtraDefault()
 *   2. the marketing site's TypeScript fallback (DEFAULT_CONTACT_CONTENT),
 *      used when the /api/v1/site/contact fetch fails.
 *
 * If someone updates one and forgets the other, a fetch failure on the
 * marketing site would quietly surface outdated details again — the exact
 * regression task #3259 was meant to prevent. This test reads BOTH sources at
 * runtime (no hard-coded third copy) and fails if the fields the marketing
 * site shows — email, address, phone — no longer match.
 */

// The product app's PHP source, relative to this test file.
const SITE_PAGES_CONTENT_PHP = path.resolve(
  import.meta.dirname,
  "..",
  "..",
  "..",
  "1inme",
  "app",
  "Modules",
  "Common",
  "Support",
  "SitePagesContent.php",
);

/**
 * Isolate the body of contactExtraDefault(): array so key lookups can't
 * accidentally match an identically-named key in another method.
 */
function extractContactExtraDefaultBody(php: string): string {
  const start = php.indexOf("public static function contactExtraDefault(): array");
  if (start === -1) {
    throw new Error(
      "Could not find SitePagesContent::contactExtraDefault() — did the method get renamed?",
    );
  }
  const nextFn = php.indexOf(
    "public static function",
    start + "public static function contactExtraDefault(): array".length,
  );
  return nextFn === -1 ? php.slice(start) : php.slice(start, nextFn);
}

/**
 * Decode a PHP string literal into its runtime value. Double-quoted strings
 * interpret escapes like \n; single-quoted strings only interpret \\ and \'.
 */
function decodePhpString(raw: string): string {
  const quote = raw[0];
  const inner = raw.slice(1, -1);
  if (quote === '"') {
    return inner
      .replace(/\\n/g, "\n")
      .replace(/\\r/g, "\r")
      .replace(/\\t/g, "\t")
      .replace(/\\"/g, '"')
      .replace(/\\\\/g, "\\");
  }
  return inner.replace(/\\'/g, "'").replace(/\\\\/g, "\\");
}

/**
 * Read a top-level `'key' => "..."` (or '...') scalar from the method body.
 */
function readPhpDefault(body: string, key: string): string {
  const re = new RegExp(
    `'${key}'\\s*=>\\s*("(?:[^"\\\\]|\\\\.)*"|'(?:[^'\\\\]|\\\\.)*')`,
  );
  const match = body.match(re);
  if (!match) {
    throw new Error(
      `Could not find the '${key}' default in contactExtraDefault() — did the shape change?`,
    );
  }
  return decodePhpString(match[1]);
}

describe("marketing contact fallback stays in sync with the product app", () => {
  const php = readFileSync(SITE_PAGES_CONTENT_PHP, "utf8");
  const body = extractContactExtraDefaultBody(php);

  const phpDefaults = {
    email: readPhpDefault(body, "email"),
    address: readPhpDefault(body, "address"),
    phone: readPhpDefault(body, "phone"),
    hours: readPhpDefault(body, "hours"),
  };

  // The scalar fields the marketing Contact page renders from the fallback —
  // email, address, blank phone and business hours — must match the canonical
  // PHP source. Social links deliberately differ (the marketing fallback keeps
  // them blank) and there is no map field here, so those stay out of scope.
  it.each(["email", "address", "phone", "hours"] as const)(
    "DEFAULT_CONTACT_CONTENT.%s matches SitePagesContent::contactExtraDefault()",
    (field) => {
      expect(DEFAULT_CONTACT_CONTENT[field]).toBe(phpDefaults[field]);
    },
  );

  // A fake/placeholder phone number is the worst regression, so pin it blank
  // explicitly in both the canonical source and the marketing fallback.
  it("keeps the phone blank in both the PHP source and the marketing fallback", () => {
    expect(phpDefaults.phone).toBe("");
    expect(DEFAULT_CONTACT_CONTENT.phone).toBe("");
  });
});
