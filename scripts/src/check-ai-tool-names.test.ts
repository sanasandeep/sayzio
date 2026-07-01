import { describe, it, expect } from "vitest";
import { scanSource, scanLabelContexts, blankComments } from "./check-ai-tool-names.js";

/**
 * Regression suite for the AI tool-name drift guard.
 *
 * Two delicate passes are pinned here so a future refactor can't silently
 * disable an exception (false negatives) or re-introduce false positives:
 *   - scanSource() — the multi-word-phrase scanner: comment blanking per mode,
 *     the "AI · X" kicker lookbehind, map/object-key detection, count badges,
 *     and not treating https:// as a comment.
 *   - scanLabelContexts() — whole-label matching for bare single-word labels: it
 *     must flag a label that is nothing but a bare tool name ("Personas",
 *     "Coach") while passing entity-noun labels that merely contain that word
 *     ("All Personas", "Coach usage & quality", "Coach Defaults", "AI Usage").
 * Run: pnpm --filter @workspace/scripts run test
 */

const ADMIN = "artifacts/1inme/resources/views/admin";
const AI_VIEW = `${ADMIN}/ai-personas/index.blade.php`;
const NON_AI_VIEW = `${ADMIN}/coach-defaults/index.blade.php`;
const GENERIC_VIEW = `${ADMIN}/dashboard/index.blade.php`;

const canonicals = (src: string, mode: "blade" | "js" = "blade") =>
  scanSource("test.file", src, mode).map((o) => o.canonical);

const labelCanonicals = (relFile: string, src: string) =>
  scanLabelContexts(relFile, src).map((o) => o.canonical);

describe("scanSource — bare phrases are flagged", () => {
  it("flags a bare distinctive phrase", () => {
    expect(canonicals("<h1>Knowledge Base</h1>")).toEqual(["AI Knowledge Base(s)"]);
  });

  it("flags the retired 'Ask Coach' name", () => {
    expect(canonicals("<span>Ask Coach</span>")).toEqual(["AI Coach"]);
  });

  it("flags each of the guarded phrases", () => {
    const src = [
      "Knowledge Bases",
      "Ask Coach",
      "Voice Assistant",
      "Marketing Strategist",
      "Inbox Agent",
      "Brand Kit",
      "Persona Generator",
    ].join("\n");
    expect(canonicals(src)).toEqual([
      "AI Knowledge Base(s)",
      "AI Coach",
      "AI Voice Assistant",
      "AI Marketing Strategist",
      "AI Inbox Agent",
      "AI Brand Kit",
      "AI Persona Generator",
    ]);
  });
});

describe("scanSource — 'AI '-prefixed spellings are not flagged", () => {
  it("ignores the correct 'AI '-prefixed forms", () => {
    const src = [
      "AI Knowledge Bases",
      "AI Voice Assistant",
      "AI Marketing Strategist",
      "AI Inbox Agent",
      "AI Brand Kit",
      "AI Persona Generator",
    ].join("\n");
    expect(canonicals(src)).toEqual([]);
  });

  it("does not flag lowercase descriptive prose (case-sensitive match)", () => {
    expect(canonicals("our knowledge bases and voice assistant")).toEqual([]);
  });
});

describe("scanSource — comments are ignored", () => {
  it("ignores blade {{-- --}} comments (blade mode)", () => {
    expect(canonicals("{{-- Ask Coach --}}", "blade")).toEqual([]);
  });

  it("ignores HTML <!-- --> comments (blade mode)", () => {
    expect(canonicals("<!-- Voice Assistant -->", "blade")).toEqual([]);
  });

  it("ignores C-style /* */ block comments", () => {
    expect(canonicals("/* Marketing Strategist */", "js")).toEqual([]);
  });

  it("ignores // line comments (js mode)", () => {
    expect(canonicals("const x = 1; // Ask Coach here", "js")).toEqual([]);
  });

  it("does NOT treat blade {{-- --}} as a comment in js mode", () => {
    // js mode only blanks C-style and // — a blade comment span is scanned.
    expect(canonicals("{{-- Ask Coach --}}", "js")).toEqual(["AI Coach"]);
  });
});

describe("scanSource — map/object keys are ignored", () => {
  it("ignores a blade array key ('Ask Coach' => 'AI Coach')", () => {
    expect(canonicals("'Ask Coach' => 'AI Coach',", "blade")).toEqual([]);
  });

  it("ignores a TS object key (\"Marketing Strategist\":)", () => {
    expect(canonicals('"Marketing Strategist": "AI Marketing Strategist",', "js")).toEqual([]);
  });

  it("still flags a bare phrase that is NOT a key on the same shape", () => {
    // Not quote-wrapped-then-=>/: — this is display copy, must be flagged.
    expect(canonicals("<label>Marketing Strategist</label>", "js")).toEqual([
      "AI Marketing Strategist",
    ]);
  });
});

describe("scanSource — 'AI · X' kicker form is ignored", () => {
  it("ignores the 'AI · Marketing Strategist' kicker", () => {
    expect(canonicals("AI \u00B7 Marketing Strategist")).toEqual([]);
  });

  it("ignores the 'AI · Voice Assistant' kicker", () => {
    expect(canonicals("AI \u00B7 Voice Assistant")).toEqual([]);
  });
});

describe("scanSource — count badges are ignored", () => {
  it("ignores a digit-prefixed count ('5 Knowledge Bases')", () => {
    expect(canonicals("5 Knowledge Bases")).toEqual([]);
  });

  it("ignores a blade-echo count ('{{ $n }} Knowledge Bases')", () => {
    expect(canonicals("{{ $n }} Knowledge Bases")).toEqual([]);
  });

  it("still flags a bare 'Knowledge Base' with no count prefix", () => {
    expect(canonicals("Manage your Knowledge Bases")).toEqual(["AI Knowledge Base(s)"]);
  });
});

describe("scanSource — https:// is not mistaken for a comment", () => {
  it("keeps scanning after a URL and still catches real drift on the line", () => {
    expect(
      canonicals('<a href="https://sayzio.com/coach">Voice Assistant</a>', "blade"),
    ).toEqual(["AI Voice Assistant"]);
  });

  it("blankComments leaves https:// intact but strips a real // comment", () => {
    const out = blankComments("see https://x.io // Ask Coach", "js");
    expect(out).toContain("https://x.io");
    expect(out).not.toContain("Ask Coach");
  });
});

describe("scanSource — reported location", () => {
  it("reports 1-based line and column of the match", () => {
    const [hit] = scanSource("test.file", "line one\nfoo Ask Coach bar", "blade");
    expect(hit).toMatchObject({ line: 2, col: 5, canonical: "AI Coach" });
  });
});

describe("scanLabelContexts — DRIFT cases (a bare single-word label must be flagged)", () => {
  it("flags a bare tool name in @section('title')", () => {
    expect(labelCanonicals(GENERIC_VIEW, `@section('title', 'Personas')`)).toEqual([
      "AI Personas",
    ]);
  });

  it("flags a bare tool name in @section('page-title')", () => {
    expect(labelCanonicals(GENERIC_VIEW, `@section('page-title', 'Resume')`)).toEqual([
      "AI Resume",
    ]);
  });

  it("flags a bare tool name in a nav-label span", () => {
    expect(
      labelCanonicals(GENERIC_VIEW, `<span class="nav-label">Companions</span>`),
    ).toEqual(["AI Companions"]);
  });

  it("flags a bare tool name in a sidebar-tooltip span", () => {
    expect(
      labelCanonicals(GENERIC_VIEW, `<span class="sidebar-tooltip">Coach</span>`),
    ).toEqual(["AI Coach"]);
  });

  it("flags a bare tool-name heading inside an AI view dir", () => {
    expect(labelCanonicals(AI_VIEW, `<h1>Personas</h1>`)).toEqual(["AI Personas"]);
  });
});

describe("scanLabelContexts — MUST-NOT-FLAG cases (entity nouns / out of context)", () => {
  it("passes 'All Personas' (longer entity-noun label)", () => {
    expect(labelCanonicals(GENERIC_VIEW, `<span class="nav-label">All Personas</span>`)).toEqual(
      [],
    );
  });

  it("passes 'Coach usage & quality' in a section title", () => {
    expect(labelCanonicals(GENERIC_VIEW, `@section('title', 'Coach usage & quality')`)).toEqual(
      [],
    );
  });

  it("passes 'Coach usage &amp; quality' (encoded entity)", () => {
    expect(
      labelCanonicals(GENERIC_VIEW, `<span class="nav-label">Coach usage &amp; quality</span>`),
    ).toEqual([]);
  });

  it("passes 'Coach Defaults' (entity noun, not the tool)", () => {
    expect(
      labelCanonicals(GENERIC_VIEW, `<span class="nav-label">Coach Defaults</span>`),
    ).toEqual([]);
  });

  it("passes 'AI Usage' (already prefixed / different noun)", () => {
    expect(labelCanonicals(GENERIC_VIEW, `<span class="nav-label">AI Usage</span>`)).toEqual([]);
  });

  it("passes a bare tool name inside a blade comment", () => {
    expect(
      labelCanonicals(GENERIC_VIEW, `{{-- <span class="nav-label">Personas</span> --}}`),
    ).toEqual([]);
  });

  it("passes a bare tool name inside an HTML comment", () => {
    expect(labelCanonicals(AI_VIEW, `<!-- <h1>Coach</h1> -->`)).toEqual([]);
  });

  it("passes a dynamic (concatenated) section title", () => {
    // Not a plain string literal, so the section-title regex never matches it.
    expect(labelCanonicals(GENERIC_VIEW, `@section('title', 'AI Usage — '.$user->name)`)).toEqual(
      [],
    );
  });

  it("ignores a bare heading OUTSIDE the AI view dirs", () => {
    expect(labelCanonicals(NON_AI_VIEW, `<h1>Coach</h1>`)).toEqual([]);
  });
});

describe("blankComments — preserves line/column geometry", () => {
  it("blanks comment bodies while preserving newlines", () => {
    const src = `a\n{{-- Brand Kit --}}\nb`;
    const out = blankComments(src);
    expect(out.split("\n").length).toBe(src.split("\n").length);
    expect(out).not.toContain("Brand Kit");
    expect(out.split("\n")[0]).toBe("a");
    expect(out.split("\n")[2]).toBe("b");
  });
});
