#!/usr/bin/env node
// Export a trimmed sub-deck containing only the chosen sections.
//
// Usage:
//   node scripts/export-subdeck.mjs --sections sales,investor [--name sales-investor]
//
// Output:
//   src/data/subdecks/<name>.json          (trimmed manifest, positions reset to 1..N)
//   src/pages/subdecks/<name>/Cover.tsx    (sub-deck cover)
//   src/pages/subdecks/<name>/Toc.tsx      (sub-deck table of contents)
//
// To view a sub-deck in the existing slide viewer, append `?subdeck=<name>`
// to the deck URL (e.g. `/?subdeck=sales-investor`).

import fs from "node:fs";
import path from "node:path";
import { randomUUID } from "node:crypto";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(__dirname, "..");
const manifestPath = path.join(projectRoot, "src/data/slides-manifest.json");
const subdeckManifestDir = path.join(projectRoot, "src/data/subdecks");
const subdeckPagesRoot = path.join(projectRoot, "src/pages/subdecks");

// Section keys that the manifest emits via `section`. Order matters — it
// drives the generated TOC and intro copy.
const SECTION_META = {
  sales: { name: "Sales Presentation", desc: "Problem, pitch, ROI, pricing, next steps." },
  product: { name: "Product Presentation", desc: "Web, mobile, API, journeys, integrations." },
  features: { name: "Feature Deep-Dives", desc: "Module mini-decks for buyer questions." },
  personas: { name: "Persona Decks", desc: "How Sayzio helps each role we sell into." },
  investor: { name: "Investor Pitch", desc: "Vision, market, model, team, ask." },
  roadmap: { name: "Future Roadmap", desc: "Now / Next / Later across every area." },
};

// ---------- CLI parsing ----------
function parseArgs(argv) {
  const out = {};
  for (let i = 0; i < argv.length; i += 1) {
    const a = argv[i];
    if (a === "--sections" || a === "-s") out.sections = argv[++i];
    else if (a === "--name" || a === "-n") out.name = argv[++i];
    else if (a === "--help" || a === "-h") out.help = true;
  }
  return out;
}

function usageAndExit(code = 0) {
  console.log(
    "Usage: node scripts/export-subdeck.mjs --sections sales,investor [--name sales-investor]\n" +
      `\nKnown sections: ${Object.keys(SECTION_META).join(", ")}`,
  );
  process.exit(code);
}

const args = parseArgs(process.argv.slice(2));
if (args.help) usageAndExit(0);
if (!args.sections) {
  console.error("Missing --sections.\n");
  usageAndExit(1);
}

const requested = args.sections
  .split(",")
  .map((s) => s.trim().toLowerCase())
  .filter(Boolean);

const unknown = requested.filter((s) => !SECTION_META[s]);
if (unknown.length) {
  console.error(`Unknown section(s): ${unknown.join(", ")}`);
  usageAndExit(1);
}
if (requested.length === 0) {
  console.error("Provide at least one section.");
  usageAndExit(1);
}

const name = (args.name ?? requested.join("-")).replace(/[^a-z0-9-]/gi, "-").toLowerCase();
if (!name) {
  console.error("Resolved sub-deck name is empty.");
  process.exit(1);
}

// ---------- Read main manifest ----------
const fullManifest = JSON.parse(fs.readFileSync(manifestPath, "utf8"));

// ---------- Render helpers (subset of generate-deck.mjs) ----------
const esc = (s) =>
  String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll("'", "&rsquo;")
    .replaceAll("\"", "&ldquo;");

function chrome({ eyebrow, pos, total, body, bgClass, gradient }) {
  const grad =
    gradient ??
    `<div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />`;
  return `      ${grad}
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={\`\${base}logo-1inme-dark.png\`} crossOrigin="anonymous" alt="Sayzio" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">${esc(eyebrow ?? "")}</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
${body}
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>${pos} / ${total}</span></div>`;
}

function wrap(componentName, bgClass, inner) {
  return `const base = import.meta.env.BASE_URL;

export default function ${componentName}() {
  return (
    <div className="w-screen h-screen overflow-hidden relative ${bgClass} text-slate-100 font-body">
${inner}
    </div>
  );
}
`;
}

function renderSubCover({ titleA, titleB, titleC, eyebrow, subtitle }, pos, total) {
  const body = `        <div className="flex-1 flex flex-col justify-center max-w-[80vw]">
          <span className="inline-block self-start px-[1.2vw] py-[0.6vh] rounded-full border border-fuchsia-400/40 bg-fuchsia-500/10 text-[1vw] tracking-[0.25em] uppercase text-fuchsia-200">${esc(eyebrow)}</span>
          <h1 className="mt-[3vh] font-display text-[7.5vw] font-bold tracking-tight leading-[0.92]">${esc(titleA)}<span className="block bg-gradient-to-r from-violet-300 via-fuchsia-300 to-pink-200 bg-clip-text text-transparent">${esc(titleB)}</span><span className="block text-slate-200">${esc(titleC)}</span></h1>
          <p className="mt-[3vh] text-[1.6vw] text-slate-300 max-w-[60vw] leading-snug">${esc(subtitle)}</p>
        </div>`;
  const inner = chrome({
    eyebrow: "Sayzio",
    pos,
    total,
    body,
    gradient: `<img src={\`\${base}hero-cover.png\`} crossOrigin="anonymous" alt="" className="absolute inset-0 w-full h-full object-cover opacity-60" />
      <div className="absolute inset-0 bg-[linear-gradient(120deg,rgba(10,10,20,0.95)_0%,rgba(20,9,31,0.78)_45%,rgba(10,10,20,0.55)_100%)]" />
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(236,72,153,0.25),transparent_55%)]" />`,
  });
  return wrap("SubdeckCover", "bg-[#0a0a14]", inner);
}

function renderSubToc({ title, subtitle, items }, pos, total) {
  const itemsHtml = items
    .map(
      (it, i) => `          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.5vw] flex items-start gap-[1.2vw]">
            <div className="font-display text-[1.6vw] font-bold text-violet-300 w-[3vw]">${String(i + 1).padStart(2, "0")}</div>
            <div className="flex-1"><div className="font-display text-[1.5vw] font-semibold">${esc(it.name)}</div><div className="mt-[0.4vh] text-[1vw] text-slate-400">${esc(it.desc ?? "")}</div></div>
            <div className="text-[1vw] text-fuchsia-200 font-mono whitespace-nowrap">${esc(it.range)}</div>
          </div>`,
    )
    .join("\n");
  const body = `        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">${esc(title)}</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[60vw]">${esc(subtitle)}</p>
        <div className="mt-[4vh] grid grid-cols-${items.length > 1 ? 2 : 1} gap-[1.5vw]">
${itemsHtml}
        </div>`;
  const inner = chrome({ eyebrow: "Table of Contents", pos, total, body });
  return wrap("SubdeckToc", "bg-[#0a0a14]", inner);
}

// ---------- Build trimmed slide list ----------
const picked = fullManifest.filter((s) => requested.includes(s.section));
if (picked.length === 0) {
  console.error(
    `No slides matched sections: ${requested.join(", ")}. ` +
      `Did you regenerate the deck after adding section tags? Run: node scripts/generate-deck.mjs`,
  );
  process.exit(1);
}

// Group picked slides by section to compute ranges in the new deck (offset by 2 cover+toc).
const sectionGroups = [];
for (const key of requested) {
  const slides = picked.filter((s) => s.section === key);
  if (slides.length > 0) sectionGroups.push({ key, slides });
}

const total = picked.length + 2; // cover + toc + slides
const tocItems = [];
let cursor = 3; // first content slide sits at position 3
for (const g of sectionGroups) {
  const start = cursor;
  const end = cursor + g.slides.length - 1;
  tocItems.push({
    name: SECTION_META[g.key].name,
    desc: SECTION_META[g.key].desc,
    range: `${start} – ${end}`,
  });
  cursor = end + 1;
}

const sectionLabels = sectionGroups.map((g) => SECTION_META[g.key].name).join(" + ");
const audience = sectionGroups.length === 1 ? sectionGroups[0].key : "this audience";

// ---------- Write cover + TOC tsx files ----------
const subPagesDir = path.join(subdeckPagesRoot, name);
fs.mkdirSync(subPagesDir, { recursive: true });
fs.mkdirSync(subdeckManifestDir, { recursive: true });

const coverTsx = renderSubCover(
  {
    eyebrow: `Sub-deck · ${sectionLabels}`,
    titleA: "Sayzio.",
    titleB: `For ${audience}.`,
    titleC: "One platform.",
    subtitle: `A trimmed deck containing only: ${sectionLabels}.`,
  },
  1,
  total,
);
const tocTsx = renderSubToc(
  {
    title: "Table of contents",
    subtitle: `Sub-deck of ${sectionGroups.length} section${sectionGroups.length === 1 ? "" : "s"}, exported from the master deck.`,
    items: tocItems,
  },
  2,
  total,
);

fs.writeFileSync(path.join(subPagesDir, "Cover.tsx"), coverTsx);
fs.writeFileSync(path.join(subPagesDir, "Toc.tsx"), tocTsx);

// ---------- Build trimmed manifest ----------
const newManifest = [
  {
    id: randomUUID(),
    position: 1,
    filepath: `src/pages/subdecks/${name}/Cover.tsx`,
    title: `Sayzio · ${sectionLabels}`,
    description: `Sub-deck cover for ${sectionLabels}.`,
    speakerNotes: `Open the sub-deck. Frame it for ${audience} only — fewer slides, sharper focus.`,
    section: "intro",
  },
  {
    id: randomUUID(),
    position: 2,
    filepath: `src/pages/subdecks/${name}/Toc.tsx`,
    title: "Table of contents",
    description: `Sub-deck table of contents.`,
    speakerNotes: "Walk the audience through the sub-deck sections.",
    section: "intro",
  },
];

let pos = 3;
for (const g of sectionGroups) {
  for (const slide of g.slides) {
    newManifest.push({
      id: randomUUID(),
      position: pos,
      filepath: slide.filepath,
      title: slide.title,
      description: slide.description,
      ...(slide.speakerNotes ? { speakerNotes: slide.speakerNotes } : {}),
      section: slide.section,
    });
    pos += 1;
  }
}

const outPath = path.join(subdeckManifestDir, `${name}.json`);
fs.writeFileSync(outPath, JSON.stringify(newManifest, null, 2));

console.log(
  `Exported sub-deck "${name}" with ${newManifest.length} slides ` +
    `(sections: ${requested.join(", ")}).\n` +
    `  manifest: ${path.relative(projectRoot, outPath)}\n` +
    `  pages:    ${path.relative(projectRoot, subPagesDir)}/\n` +
    `  view:     /?subdeck=${name}`,
);
