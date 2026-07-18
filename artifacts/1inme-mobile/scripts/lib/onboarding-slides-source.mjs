// Shared source-of-truth parser for the mobile onboarding intro slides.
//
// The onboarding intro arc is defined in exactly one shipped place —
// app/onboarding.tsx — as:
//   • FALLBACK_SLIDES: the bundled slide arc rendered on a fresh install /
//     when the admin hasn't seeded yet / offline.
//   • AI_DASHBOARD_SLIDE: an extra page the app inserts CLIENT-SIDE, just
//     before the final "get-started" CTA (see the ctaIdx / pages logic).
//
// Historically the e2e harness (test-onboarding-slider-e2e.mjs) hand-kept its
// own EXPECTED_PAGES copy of this arc, which could silently drift from the
// real shipped slides. This helper reads the arc straight from onboarding.tsx
// so any consumer (the e2e harness, and a slides-sync drift guard) derives the
// SAME arc from the ONE real source instead of maintaining a parallel list.
//
// It parses the source text (no TS/RN runtime) — the object literals only need
// their slug/category/title, all of which are simple double-quoted strings.

import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
// scripts/lib -> package root (artifacts/1inme-mobile)
const PACKAGE_ROOT = join(__dirname, "..", "..");

export const ONBOARDING_TSX = join(PACKAGE_ROOT, "app", "onboarding.tsx");

function readOnboardingSource() {
  return readFileSync(ONBOARDING_TSX, "utf8");
}

// Return the substring of `src` that is the balanced literal starting at the
// first `open` character at/after `fromIdx`, e.g. the `[...]` of an array or
// the `{...}` of an object. Throws if the opener or its match can't be found.
function sliceBalanced(src, fromIdx, open, close, label) {
  const start = src.indexOf(open, fromIdx);
  if (start === -1) {
    throw new Error(
      `onboarding-slides-source: could not find opening '${open}' for ${label} in ${ONBOARDING_TSX}`,
    );
  }
  let depth = 0;
  for (let i = start; i < src.length; i++) {
    const ch = src[i];
    if (ch === open) depth += 1;
    else if (ch === close) {
      depth -= 1;
      if (depth === 0) return src.slice(start, i + 1);
    }
  }
  throw new Error(
    `onboarding-slides-source: unbalanced '${open}${close}' for ${label} in ${ONBOARDING_TSX}`,
  );
}

// Parse FALLBACK_SLIDES into the ordered arc of { slug, category, title }.
// In every slide object slug/category/title appear adjacently and are plain
// double-quoted strings, so one ordered global match reads them reliably.
export function parseFallbackSlides(src = readOnboardingSource()) {
  const declIdx = src.indexOf("const FALLBACK_SLIDES");
  if (declIdx === -1) {
    throw new Error(
      `onboarding-slides-source: could not find 'const FALLBACK_SLIDES' in ${ONBOARDING_TSX}`,
    );
  }
  // Start after the assignment '=' so the '[]' in the 'OnboardingSlide[]' type
  // annotation isn't mistaken for the (empty) array literal.
  const assignIdx = src.indexOf("=", declIdx);
  if (assignIdx === -1) {
    throw new Error(
      `onboarding-slides-source: FALLBACK_SLIDES has no assignment in ${ONBOARDING_TSX}`,
    );
  }
  const body = sliceBalanced(src, assignIdx, "[", "]", "FALLBACK_SLIDES");
  const re =
    /slug:\s*"([^"]+)"\s*,\s*category:\s*"([^"]+)"\s*,\s*title:\s*"([^"]+)"/g;
  const slides = [];
  let m;
  while ((m = re.exec(body)) !== null) {
    slides.push({ slug: m[1], category: m[2], title: m[3] });
  }
  if (slides.length === 0) {
    throw new Error(
      `onboarding-slides-source: parsed 0 slides from FALLBACK_SLIDES in ${ONBOARDING_TSX}`,
    );
  }
  return slides;
}

// Parse the client-inserted AI dashboard page: its slug comes from the
// AI_DASHBOARD_SLUG const, its category from the AI_DASHBOARD_SLIDE literal.
export function parseAiDashboardSlide(src = readOnboardingSource()) {
  const slugMatch = /const\s+AI_DASHBOARD_SLUG\s*=\s*"([^"]+)"/.exec(src);
  if (!slugMatch) {
    throw new Error(
      `onboarding-slides-source: could not find AI_DASHBOARD_SLUG in ${ONBOARDING_TSX}`,
    );
  }
  const slideIdx = src.indexOf("const AI_DASHBOARD_SLIDE");
  if (slideIdx === -1) {
    throw new Error(
      `onboarding-slides-source: could not find AI_DASHBOARD_SLIDE in ${ONBOARDING_TSX}`,
    );
  }
  const body = sliceBalanced(src, slideIdx, "{", "}", "AI_DASHBOARD_SLIDE");
  const catMatch = /category:\s*"([^"]+)"/.exec(body);
  if (!catMatch) {
    throw new Error(
      `onboarding-slides-source: could not find AI_DASHBOARD_SLIDE category in ${ONBOARDING_TSX}`,
    );
  }
  return { slug: slugMatch[1], category: catMatch[1] };
}

// Build the full ordered arc the app RENDERS: the FALLBACK_SLIDES with the
// AI dashboard page inserted just before "get-started" — mirroring the ctaIdx
// / pages logic in onboarding.tsx. Throws if the shipped arc is missing the
// "get-started" CTA the insertion pivots on (a structural regression).
export function buildExpectedPages(src = readOnboardingSource()) {
  const fallback = parseFallbackSlides(src);
  const ai = parseAiDashboardSlide(src);
  const ctaIdx = fallback.findIndex((s) => s.slug === "get-started");
  if (ctaIdx === -1) {
    throw new Error(
      `onboarding-slides-source: FALLBACK_SLIDES has no "get-started" slide to insert "${ai.slug}" before in ${ONBOARDING_TSX}`,
    );
  }
  return [...fallback.slice(0, ctaIdx), ai, ...fallback.slice(ctaIdx)];
}
