#!/usr/bin/env node
// Regenerates the "What you can create" slide from the Laravel source of truth.
//
// Source of truth:
//   artifacts/1inme/app/Modules/User/Support/LinkTypeCategories.php
//
// This keeps SlideWhatYouCanCreate.tsx aligned with the live link-type catalog.
// Whenever product adds, renames, or re-describes a link type / category there,
// re-run this script:
//
//   pnpm --filter @workspace/1inme-deck run sync-link-types
//
// The generated slide is still plain, static, inline JSX (no `.map()` in the
// component) so it stays compatible with the slides visual editor.

import { execFileSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(__dirname, "..");
const workspaceRoot = path.resolve(projectRoot, "..", "..");

const phpSource = path.join(
  workspaceRoot,
  "artifacts/1inme/app/Modules/User/Support/LinkTypeCategories.php",
);
const slidePath = path.join(
  projectRoot,
  "src/pages/slides/SlideWhatYouCanCreate.tsx",
);
const manifestPath = path.join(projectRoot, "src/data/slides-manifest.json");
const slideFilepath = "src/pages/slides/SlideWhatYouCanCreate.tsx";

// ---------- load the source of truth ----------

function loadCategories() {
  const json = execFileSync(
    "php",
    [
      "-r",
      "require $argv[1]; echo json_encode(\\App\\Modules\\User\\Support\\LinkTypeCategories::categories());",
      phpSource,
    ],
    { encoding: "utf8" },
  );
  return JSON.parse(json);
}

// ---------- helpers ----------

const NUMBER_WORDS = [
  "zero",
  "one",
  "two",
  "three",
  "four",
  "five",
  "six",
  "seven",
  "eight",
  "nine",
  "ten",
  "eleven",
  "twelve",
  "thirteen",
  "fourteen",
  "fifteen",
  "sixteen",
  "seventeen",
  "eighteen",
  "nineteen",
  "twenty",
];

function numberWord(n) {
  return NUMBER_WORDS[n] ?? String(n);
}

function capitalize(s) {
  return s.charAt(0).toUpperCase() + s.slice(1);
}

// Escape text that lands inside JSX element bodies. A bare `&` is valid in JSX
// text and the source copy uses it (e.g. "Pages & mini-sites"), so it is left
// literal; only the characters that would actually break JSX are escaped.
function esc(s) {
  return String(s)
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll("{", "&#123;")
    .replaceAll("}", "&#125;");
}

// The PHP badge looks like "bg-violet-500/15 text-violet-300". Pull the colour
// name so we can derive the slide's dot colour and the category accent.
function colorFromBadge(badge) {
  const match = /text-([a-z]+)-\d+/.exec(String(badge));
  if (!match) {
    throw new Error(`Could not parse colour from badge: ${badge}`);
  }
  return match[1];
}

// ---------- render ----------

function renderType(type) {
  const color = colorFromBadge(type.badge);
  return `            <div className="mt-[1.3vh] flex items-start gap-[0.7vw]">
              <span className="mt-[0.4vh] h-[0.9vw] w-[0.9vw] rounded-md bg-${color}-400 shrink-0" />
              <div>
                <div className="font-display text-[1.05vw] font-semibold leading-tight">${esc(type.label)}</div>
                <div className="text-[0.85vw] text-slate-400 leading-snug">${esc(type.desc)}</div>
              </div>
            </div>`;
}

function renderCategory(category) {
  if (!category.types.length) {
    throw new Error(`Category "${category.label}" has no types`);
  }
  const accent = colorFromBadge(category.types[0].badge);
  const firstType = renderType(category.types[0]).replace(
    "mt-[1.3vh]",
    "mt-[1.6vh]",
  );
  const restTypes = category.types.slice(1).map(renderType).join("\n");
  const typesBlock = restTypes ? `${firstType}\n${restTypes}` : firstType;
  return `          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.3vw] flex flex-col">
            <div className="text-[0.85vw] uppercase tracking-[0.22em] text-${accent}-300">${esc(category.label)}</div>
            <div className="mt-[0.8vh] text-[0.92vw] text-slate-400 leading-snug">${esc(category.desc)}</div>
${typesBlock}
          </div>`;
}

function renderSlide(categories, pos, total) {
  const typeCount = categories.reduce((sum, c) => sum + c.types.length, 0);
  const catCount = categories.length;
  const subtitle = `${capitalize(numberWord(typeCount))} link types, grouped ${numberWord(catCount)} ways — every one lives at a single 1inme.com link.`;
  const cards = categories.map(renderCategory).join("\n\n");

  return `const base = import.meta.env.BASE_URL;

export default function SlideWhatYouCanCreate() {
  return (
    <div className="w-screen h-screen overflow-hidden relative bg-[#0a0a14] text-slate-100 font-body">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={\`\${base}logo-1inme-dark.png\`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400"></span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[10vh] pb-[8vh] flex flex-col">
        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight max-w-[70vw]">What you can create.</h2>
        <p className="mt-[1.6vh] text-[1.3vw] text-slate-300 max-w-[68vw]">${esc(subtitle)}</p>

        <div className="mt-[3.5vh] grid grid-cols-${catCount} gap-[1.4vw] flex-1 content-start">

${cards}

        </div>
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>${pos} / ${total}</span></div>
    </div>
  );
}
`;
}

// ---------- manifest description (also drifts) ----------

function manifestDescription(categories) {
  const typeCount = categories.reduce((sum, c) => sum + c.types.length, 0);
  const labels = categories.map((c) => c.label);
  let joined;
  if (labels.length <= 1) {
    joined = labels.join("");
  } else {
    joined = `${labels.slice(0, -1).join(", ")}, and ${labels[labels.length - 1]}`;
  }
  return `All ${typeCount} link types grouped into ${categories.length} categories — ${joined}.`;
}

function updateManifest(categories) {
  const raw = fs.readFileSync(manifestPath, "utf8");
  const manifest = JSON.parse(raw);
  const slides = Array.isArray(manifest) ? manifest : manifest.slides;
  if (!Array.isArray(slides)) {
    throw new Error("Could not locate slides array in manifest");
  }
  const entry = slides.find((s) => s.filepath === slideFilepath);
  if (!entry) {
    throw new Error(`No manifest entry for ${slideFilepath}`);
  }
  entry.description = manifestDescription(categories);
  fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
  return { entry, total: slides.length };
}

// ---------- run ----------

const categories = loadCategories();
const { entry, total } = updateManifest(categories);
const pos = entry.position ?? "";
fs.writeFileSync(slidePath, renderSlide(categories, pos, total));

console.log(
  `Synced ${slideFilepath} from LinkTypeCategories.php ` +
    `(${categories.length} categories, ` +
    `${categories.reduce((s, c) => s + c.types.length, 0)} types).`,
);
