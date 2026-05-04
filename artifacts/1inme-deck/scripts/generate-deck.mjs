#!/usr/bin/env node
import fs from "node:fs";
import path from "node:path";
import { randomUUID } from "node:crypto";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(__dirname, "..");
const slidesDir = path.join(projectRoot, "src/pages/slides");
const manifestPath = path.join(projectRoot, "src/data/slides-manifest.json");

const esc = (s) =>
  String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll("'", "&rsquo;")
    .replaceAll("\"", "&ldquo;");

const slugify = (s) =>
  s
    .replace(/[^a-zA-Z0-9 ]/g, "")
    .split(/\s+/)
    .filter(Boolean)
    .map((w) => w[0].toUpperCase() + w.slice(1).toLowerCase())
    .join("")
    .slice(0, 28);

// ---------- LAYOUT RENDERERS ----------

function chrome({ name, eyebrow, pos, total, body, bgClass, gradient }) {
  const grad =
    gradient ??
    `<div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(124,58,237,0.18),transparent_55%),radial-gradient(ellipse_at_bottom_left,rgba(236,72,153,0.12),transparent_55%)]" />`;
  return `const base = import.meta.env.BASE_URL;

export default function ${name}() {
  return (
    <div className="w-screen h-screen overflow-hidden relative ${bgClass ?? "bg-[#0a0a14]"} text-slate-100 font-body">
      ${grad}
      <div className="absolute top-0 left-0 right-0 h-[6vh] flex items-center justify-between px-[5vw] z-10">
        <img src={\`\${base}logo-1inme-dark.png\`} crossOrigin="anonymous" alt="1INME" className="h-[2.4vw] w-auto" />
        <span className="text-[0.95vw] uppercase tracking-[0.25em] text-slate-400">${esc(eyebrow ?? "")}</span>
      </div>
      <div className="relative h-full w-full px-[7vw] pt-[11vh] pb-[8vh] flex flex-col">
${body}
      </div>
      <div className="absolute bottom-[3vh] left-[5vw] right-[5vw] flex items-center justify-between text-[0.9vw] text-slate-500 z-10"><span>1inme.com</span><span>${pos} / ${total}</span></div>
    </div>
  );
}
`;
}

function renderCover(s, pos, total) {
  const body = `        <div className="flex-1 flex flex-col justify-center max-w-[80vw]">
          <span className="inline-block self-start px-[1.2vw] py-[0.6vh] rounded-full border border-fuchsia-400/40 bg-fuchsia-500/10 text-[1vw] tracking-[0.25em] uppercase text-fuchsia-200">${esc(s.eyebrow ?? "Product Deck")}</span>
          <h1 className="mt-[3vh] font-display text-[7.5vw] font-bold tracking-tight leading-[0.92]">${esc(s.titleA ?? "One link.")}<span className="block bg-gradient-to-r from-violet-300 via-fuchsia-300 to-pink-200 bg-clip-text text-transparent">${esc(s.titleB ?? "One identity.")}</span><span className="block text-slate-200">${esc(s.titleC ?? "One platform.")}</span></h1>
          <p className="mt-[3vh] text-[1.6vw] text-slate-300 max-w-[60vw] leading-snug">${esc(s.subtitle ?? "")}</p>
        </div>`;
  return chrome({
    name: s.name,
    eyebrow: s.headerLabel ?? "",
    pos,
    total,
    body,
    gradient: `<img src={\`\${base}hero-cover.png\`} crossOrigin="anonymous" alt="" className="absolute inset-0 w-full h-full object-cover opacity-60" />
      <div className="absolute inset-0 bg-[linear-gradient(120deg,rgba(10,10,20,0.95)_0%,rgba(20,9,31,0.78)_45%,rgba(10,10,20,0.55)_100%)]" />
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_right,rgba(236,72,153,0.25),transparent_55%)]" />`,
  });
}

function renderDivider(s, pos, total) {
  const body = `        <div className="flex-1 flex flex-col justify-center">
          <span className="font-display text-[1.1vw] uppercase tracking-[0.5em] text-fuchsia-200">${esc(s.eyebrow ?? "Section")}</span>
          <h2 className="mt-[2vh] font-display text-[7vw] font-bold leading-[0.94] tracking-tight max-w-[80vw]">${esc(s.title)}</h2>
          <p className="mt-[3vh] text-[1.7vw] text-slate-200 max-w-[60vw] leading-snug">${esc(s.subtitle ?? "")}</p>
          <div className="mt-[5vh] inline-flex items-center gap-[1.5vw]">
            <div className="h-[0.4vh] w-[6vw] bg-gradient-to-r from-violet-400 to-fuchsia-400 rounded-full" />
            <span className="text-[1vw] uppercase tracking-[0.3em] text-slate-300">${esc(s.range ?? "Appendix")}</span>
          </div>
        </div>`;
  return chrome({
    name: s.name,
    eyebrow: s.headerLabel ?? "Appendix",
    pos,
    total,
    body,
    bgClass: "bg-[#14091f]",
    gradient: `<div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(124,58,237,0.45),transparent_50%),radial-gradient(circle_at_75%_75%,rgba(236,72,153,0.35),transparent_55%)]" />
      <div className="absolute inset-0 bg-[linear-gradient(180deg,transparent,rgba(0,0,0,0.45))]" />`,
  });
}

function renderToc(s, pos, total) {
  const items = (s.items ?? [])
    .map(
      (it, i) => `          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.5vw] flex items-start gap-[1.2vw]">
            <div className="font-display text-[1.6vw] font-bold text-violet-300 w-[3vw]">${String(i + 1).padStart(2, "0")}</div>
            <div className="flex-1"><div className="font-display text-[1.5vw] font-semibold">${esc(it.name)}</div><div className="mt-[0.4vh] text-[1vw] text-slate-400">${esc(it.desc ?? "")}</div></div>
            <div className="text-[1vw] text-fuchsia-200 font-mono whitespace-nowrap">${esc(it.range)}</div>
          </div>`,
    )
    .join("\n");
  const body = `        <h2 className="font-display text-[3.6vw] font-bold leading-[1.02] tracking-tight">${esc(s.title)}</h2>
        <p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[60vw]">${esc(s.subtitle ?? "")}</p>
        <div className="mt-[4vh] grid grid-cols-2 gap-[1.5vw]">
${items}
        </div>`;
  return chrome({ name: s.name, eyebrow: s.headerLabel ?? "Table of Contents", pos, total, body });
}

function renderMetrics(s, pos, total) {
  const cards = (s.metrics ?? [])
    .map(
      (m) => `          <div className="rounded-xl border border-white/10 bg-white/[0.03] p-[1.6vw]"><div className="font-display text-[2.8vw] font-bold text-violet-300">${esc(m.value)}</div><div className="mt-[0.5vh] text-[1.05vw] text-slate-300">${esc(m.label)}</div></div>`,
    )
    .join("\n");
  const cols = (s.metrics ?? []).length;
  const body = `        <h2 className="font-display text-[3.6vw] font-bold leading-[1.04] tracking-tight max-w-[65vw]">${esc(s.title)}</h2>
        ${s.subtitle ? `<p className="mt-[2vh] text-[1.4vw] text-slate-300 max-w-[65vw]">${esc(s.subtitle)}</p>` : ""}
        <div className="mt-[5vh] grid grid-cols-${cols} gap-[1.5vw]">
${cards}
        </div>
        ${s.note ? `<p className="mt-[4vh] text-[1vw] text-slate-500 max-w-[60vw]">${esc(s.note)}</p>` : ""}`;
  return chrome({ name: s.name, eyebrow: s.headerLabel ?? "", pos, total, body });
}

function renderCards(s, pos, total) {
  const cols = s.cols ?? 3;
  const cards = (s.cards ?? [])
    .map(
      (c) => `          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            ${c.tag ? `<div className="text-[0.85vw] uppercase tracking-[0.25em] text-fuchsia-200">${esc(c.tag)}</div>` : ""}
            <div class="font-display text-[1.5vw] font-semibold mt-[0.5vh]">${esc(c.title)}</div>
            ${c.body ? `<div className="mt-[1vh] text-[1.05vw] text-slate-300 leading-snug">${esc(c.body)}</div>` : ""}
            ${
              c.bullets
                ? `<ul className="mt-[1vh] space-y-[0.5vh] text-[0.95vw] text-slate-400">${c.bullets.map((b) => `<li>&middot; ${esc(b)}</li>`).join("")}</ul>`
                : ""
            }
          </div>`,
    )
    .join("\n")
    .replaceAll('class="', 'className="');
  const body = `        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight max-w-[65vw]">${esc(s.title)}</h2>
        ${s.subtitle ? `<p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[65vw]">${esc(s.subtitle)}</p>` : ""}
        <div className="mt-[4vh] grid grid-cols-${cols} gap-[1.4vw] flex-1 content-start">
${cards}
        </div>`;
  return chrome({ name: s.name, eyebrow: s.headerLabel ?? "", pos, total, body });
}

function renderBullets(s, pos, total) {
  const left = (s.bullets ?? [])
    .map(
      (b) => `            <li className="flex gap-[1vw]"><span className="font-display text-[1.4vw] text-fuchsia-300 leading-none">&rarr;</span><div><div className="font-display text-[1.4vw] font-semibold">${esc(b.title)}</div>${b.body ? `<div className="mt-[0.4vh] text-[1.05vw] text-slate-300 leading-snug">${esc(b.body)}</div>` : ""}</div></li>`,
    )
    .join("\n");
  const right = s.aside
    ? `<div className="col-span-5 rounded-2xl border border-white/10 bg-white/[0.04] p-[2vw] flex flex-col justify-center">
            <div className="text-[1vw] uppercase tracking-[0.3em] text-fuchsia-200">${esc(s.aside.eyebrow ?? "")}</div>
            <div className="mt-[1vh] font-display text-[2vw] font-semibold leading-snug">${esc(s.aside.title)}</div>
            ${s.aside.body ? `<div className="mt-[1.5vh] text-[1.1vw] text-slate-300 leading-snug">${esc(s.aside.body)}</div>` : ""}
          </div>`
    : "";
  const cols = s.aside ? "grid grid-cols-12 gap-[2vw]" : "";
  const listWrap = s.aside ? `<ul className="col-span-7 space-y-[1.6vh]">${left}</ul>` : `<ul className="space-y-[1.6vh]">${left}</ul>`;
  const body = `        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight max-w-[65vw]">${esc(s.title)}</h2>
        ${s.subtitle ? `<p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[65vw]">${esc(s.subtitle)}</p>` : ""}
        <div className="mt-[4vh] flex-1 ${cols}">
          ${listWrap}
          ${right}
        </div>`;
  return chrome({ name: s.name, eyebrow: s.headerLabel ?? "", pos, total, body });
}

function renderMockup(s, pos, total) {
  const items = (s.mock ?? [])
    .map(
      (row) => `            <div className="rounded-lg border border-white/10 bg-white/[0.03] px-[1vw] py-[0.8vh] flex items-center justify-between"><span className="text-[1vw] text-slate-200">${esc(row.label)}</span><span className="text-[0.9vw] text-fuchsia-200 font-mono">${esc(row.value)}</span></div>`,
    )
    .join("\n");
  const body = `        <div className="grid grid-cols-12 gap-[2.5vw] flex-1">
          <div className="col-span-5 flex flex-col justify-center">
            <h2 className="font-display text-[3.2vw] font-bold leading-[1.04] tracking-tight">${esc(s.title)}</h2>
            ${s.subtitle ? `<p className="mt-[2vh] text-[1.25vw] text-slate-300 max-w-[26vw]">${esc(s.subtitle)}</p>` : ""}
            ${
              s.bullets
                ? `<ul className="mt-[3vh] space-y-[1vh] text-[1.05vw] text-slate-300">${s.bullets.map((b) => `<li className="flex gap-[0.6vw]"><span className="text-fuchsia-300">&bull;</span><span>${esc(b)}</span></li>`).join("")}</ul>`
                : ""
            }
          </div>
          <div className="col-span-7 rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.06] to-white/[0.02] p-[1.6vw] flex flex-col">
            <div className="flex items-center gap-[0.5vw] pb-[1vh] border-b border-white/10">
              <span className="h-[0.9vw] w-[0.9vw] rounded-full bg-rose-400/70" /><span className="h-[0.9vw] w-[0.9vw] rounded-full bg-amber-300/70" /><span className="h-[0.9vw] w-[0.9vw] rounded-full bg-emerald-400/70" />
              <span className="ml-[1vw] text-[0.9vw] text-slate-400 font-mono">${esc(s.mockTitle ?? "1INME")}</span>
            </div>
            <div className="mt-[1.5vh] flex flex-col gap-[0.7vh]">
${items}
            </div>
            ${s.mockFooter ? `<div className="mt-auto pt-[1.5vh] text-[0.9vw] text-slate-500">${esc(s.mockFooter)}</div>` : ""}
          </div>
        </div>`;
  return chrome({ name: s.name, eyebrow: s.headerLabel ?? "", pos, total, body });
}

function renderPricing(s, pos, total) {
  const cards = (s.tiers ?? [])
    .map(
      (t, i) => `          <div className="rounded-2xl border ${
        t.popular ? "border-fuchsia-400/50 bg-gradient-to-br from-fuchsia-500/15 to-violet-500/10" : "border-white/10 bg-white/[0.04]"
      } p-[1.6vw] flex flex-col">
            <div className="flex items-center justify-between"><div className="font-display text-[1.5vw] font-semibold ${
              t.popular ? "text-fuchsia-200" : ""
            }">${esc(t.name)}</div>${t.popular ? `<div className="px-[0.6vw] py-[0.2vh] text-[0.8vw] rounded bg-fuchsia-500/30 text-fuchsia-100">popular</div>` : ""}</div>
            <div className="mt-[0.5vh] font-display text-[2.8vw] font-bold leading-none">${esc(t.price)}</div>
            <div className="text-[0.95vw] text-slate-400">${esc(t.cadence)}</div>
            <div className="mt-[2vh] flex flex-col gap-[0.5vh] text-[0.95vw] text-slate-200">${(t.features ?? []).map((f) => `<div>&middot; ${esc(f)}</div>`).join("")}</div>
          </div>`,
    )
    .join("\n");
  const body = `        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight">${esc(s.title)}</h2>
        ${s.subtitle ? `<p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[65vw]">${esc(s.subtitle)}</p>` : ""}
        <div className="mt-[4vh] grid grid-cols-${(s.tiers ?? []).length} gap-[1.4vw]">
${cards}
        </div>
        ${s.note ? `<p className="mt-[3vh] text-[1vw] text-slate-500">${esc(s.note)}</p>` : ""}`;
  return chrome({ name: s.name, eyebrow: s.headerLabel ?? "Pricing", pos, total, body });
}

function renderTimeline(s, pos, total) {
  const cols = (s.columns ?? [])
    .map(
      (c) => `          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-[1.6vw] flex flex-col">
            <div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">${esc(c.label)}</div>
            <div className="mt-[0.5vh] font-display text-[1.8vw] font-semibold">${esc(c.title)}</div>
            <ul className="mt-[2vh] space-y-[0.8vh] text-[1.05vw] text-slate-300 leading-snug">${(c.items ?? []).map((i) => `<li>&middot; ${esc(i)}</li>`).join("")}</ul>
          </div>`,
    )
    .join("\n");
  const body = `        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight">${esc(s.title)}</h2>
        ${s.subtitle ? `<p className="mt-[2vh] text-[1.3vw] text-slate-300 max-w-[65vw]">${esc(s.subtitle)}</p>` : ""}
        <div className="mt-[4vh] grid grid-cols-${(s.columns ?? []).length} gap-[1.5vw] flex-1">
${cols}
        </div>`;
  return chrome({ name: s.name, eyebrow: s.headerLabel ?? "Roadmap", pos, total, body });
}

function renderQuarters(s, pos, total) {
  const rows = (s.rows ?? [])
    .map(
      (r) => `            <div className="grid grid-cols-5 gap-[1vw] py-[1vh] border-t border-white/10">
              <div className="font-display text-[1.1vw] font-semibold text-violet-200">${esc(r.theme)}</div>
              ${(r.quarters ?? []).map((q) => `<div className="text-[0.95vw] text-slate-300">${esc(q)}</div>`).join("")}
            </div>`,
    )
    .join("\n");
  const head = (s.headers ?? ["Theme", "Q1", "Q2", "Q3", "Q4"])
    .map((h, i) => `<div className="text-[0.9vw] uppercase tracking-[0.25em] text-slate-400 ${i === 0 ? "" : ""}">${esc(h)}</div>`)
    .join("");
  const body = `        <h2 className="font-display text-[3.2vw] font-bold leading-[1.04] tracking-tight">${esc(s.title)}</h2>
        ${s.subtitle ? `<p className="mt-[2vh] text-[1.2vw] text-slate-300 max-w-[65vw]">${esc(s.subtitle)}</p>` : ""}
        <div className="mt-[4vh] flex-1 flex flex-col">
          <div className="grid grid-cols-5 gap-[1vw] pb-[1vh]">${head}</div>
${rows}
        </div>`;
  return chrome({ name: s.name, eyebrow: s.headerLabel ?? "Quarterly view", pos, total, body });
}

function renderQuote(s, pos, total) {
  const body = `        <div className="flex-1 flex flex-col justify-center max-w-[80vw]">
          <span className="text-[1vw] uppercase tracking-[0.3em] text-fuchsia-200">${esc(s.eyebrow ?? "Persona quote")}</span>
          <blockquote className="mt-[3vh] font-display text-[4vw] font-semibold leading-[1.1] tracking-tight">&ldquo;${esc(s.quote)}&rdquo;</blockquote>
          <div className="mt-[4vh] flex items-center gap-[1.5vw]">
            <div className="h-[5vw] w-[5vw] rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 grid place-items-center font-display text-[2vw] font-bold">${esc((s.author ?? "?")[0])}</div>
            <div><div className="font-display text-[1.4vw] font-semibold">${esc(s.author)}</div><div className="text-[1vw] text-slate-400">${esc(s.role ?? "")}</div></div>
          </div>
        </div>`;
  return chrome({ name: s.name, eyebrow: s.headerLabel ?? "Voice of the user", pos, total, body });
}

function renderDayInLife(s, pos, total) {
  const rows = (s.steps ?? [])
    .map(
      (st) => `          <div className="grid grid-cols-12 gap-[1.5vw] items-start">
            <div className="col-span-2 font-display text-[1.6vw] font-bold text-fuchsia-200">${esc(st.time)}</div>
            <div className="col-span-3 text-[1.05vw] text-slate-400">${esc(st.module)}</div>
            <div className="col-span-7 text-[1.1vw] text-slate-200 leading-snug">${esc(st.action)}</div>
          </div>`,
    )
    .join("\n");
  const body = `        <h2 className="font-display text-[3.4vw] font-bold leading-[1.04] tracking-tight">${esc(s.title)}</h2>
        ${s.subtitle ? `<p className="mt-[2vh] text-[1.25vw] text-slate-300 max-w-[65vw]">${esc(s.subtitle)}</p>` : ""}
        <div className="mt-[4vh] flex-1 flex flex-col gap-[1.6vh]">
${rows}
        </div>`;
  return chrome({ name: s.name, eyebrow: s.headerLabel ?? "A day in the life", pos, total, body });
}

function renderClosing(s, pos, total) {
  const body = `        <div className="flex-1 flex flex-col justify-center">
          <span className="font-display text-[1.1vw] uppercase tracking-[0.5em] text-fuchsia-200">${esc(s.eyebrow ?? "Thank you")}</span>
          <h1 className="mt-[2vh] font-display text-[8vw] font-bold tracking-tight leading-[0.92]">${esc(s.titleA ?? "Thank you.")}</h1>
          <p className="mt-[3vh] text-[1.6vw] text-slate-200 max-w-[60vw] leading-snug">${esc(s.subtitle ?? "")}</p>
          <div className="mt-[6vh] grid grid-cols-3 gap-[2vw] max-w-[70vw]">
            ${(s.contacts ?? []).map((c) => `<div><div className="text-[0.95vw] uppercase tracking-[0.3em] text-fuchsia-200">${esc(c.label)}</div><div className="mt-[0.5vh] font-display text-[1.5vw] font-semibold">${esc(c.value)}</div></div>`).join("")}
          </div>
        </div>`;
  return chrome({
    name: s.name,
    eyebrow: s.headerLabel ?? "Get in touch",
    pos,
    total,
    body,
    gradient: `<div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(124,58,237,0.4),transparent_55%),radial-gradient(circle_at_75%_75%,rgba(236,72,153,0.35),transparent_55%)]" />`,
  });
}

const RENDERERS = {
  cover: renderCover,
  divider: renderDivider,
  toc: renderToc,
  metrics: renderMetrics,
  cards: renderCards,
  bullets: renderBullets,
  mockup: renderMockup,
  pricing: renderPricing,
  timeline: renderTimeline,
  quarters: renderQuarters,
  quote: renderQuote,
  dayInLife: renderDayInLife,
  closing: renderClosing,
};

// ---------- DECK SPEC ----------

import { spec } from "./deck-spec.mjs";

// ---------- BUILD ----------

// Wipe existing slides
for (const f of fs.readdirSync(slidesDir)) {
  if (f.endsWith(".tsx")) fs.unlinkSync(path.join(slidesDir, f));
}

// ----- Dynamic range patching (TOC + top-level dividers) -----
const findIdx = (slug) => spec.findIndex((s) => s.slug === slug);
const sectionBounds = (startSlug, nextStartSlug) => {
  const start = findIdx(startSlug);
  const end = nextStartSlug ? findIdx(nextStartSlug) - 1 : spec.length - 1;
  return { start: start + 1, end: end + 1 }; // 1-indexed positions
};

const sections = [
  { divider: "SalesDivider", next: "ProductDivider", tocName: "Sales Presentation" },
  { divider: "ProductDivider", next: "FeaturesDivider", tocName: "Product Presentation" },
  { divider: "FeaturesDivider", next: "PersonasDivider", tocName: "Feature Deep-Dives" },
  { divider: "PersonasDivider", next: "InvestorDivider", tocName: "Persona Decks" },
  { divider: "InvestorDivider", next: "RoadmapDivider", tocName: "Investor Pitch" },
  { divider: "RoadmapDivider", next: null, tocName: "Future Roadmap" },
];

const sectionRanges = sections.map((sec) => {
  const { start, end } = sectionBounds(sec.divider, sec.next);
  return { ...sec, start, end };
});

// Patch top-level divider range labels
sectionRanges.forEach((sec) => {
  const idx = findIdx(sec.divider);
  spec[idx].range = `Slides ${sec.start} – ${sec.end}`;
});

// Patch TOC items
const tocIdx = spec.findIndex((s) => s.layout === "toc");
if (tocIdx >= 0) {
  sectionRanges.forEach((sec, i) => {
    if (spec[tocIdx].items[i]) {
      spec[tocIdx].items[i].name = sec.tocName;
      spec[tocIdx].items[i].range = `${sec.start} – ${sec.end}`;
    }
  });
}

// ----- Generate speaker notes for every slide -----
const noteFor = (s) => {
  if (s.notes) return s.notes;
  const t = s.title ?? s.titleA ?? s.quote ?? s.eyebrow ?? "Slide";
  const sub = s.subtitle ?? s.description ?? "";
  switch (s.layout) {
    case "cover":
      return `Open the deck. Introduce 1INME as one identity, one platform. ${sub}`.trim();
    case "toc":
      return `Walk the audience through the six sections. Each section is appendix-separated by a divider — jump to the divider you need.`;
    case "divider":
      return `Section divider for "${t}". Pause here, name the section, set expectations, then dive in.`;
    case "metrics":
      return `Land the numbers in "${t}". ${sub} Say each metric out loud and tie it to the audience's pain.`.trim();
    case "cards":
      return `Walk through each card on "${t}" — name it, give one example, move on. ${sub}`.trim();
    case "bullets":
      return `Cover each bullet under "${t}". Pace yourself; one breath per bullet. ${sub}`.trim();
    case "mockup":
      return `Show the mockup for "${t}". Point at the rows, narrate the scenario, then end on the footer caption. ${sub}`.trim();
    case "pricing":
      return `Anchor on the popular tier first, then explain why each other tier exists. ${sub}`.trim();
    case "timeline":
      return `Read Now / Next / Later for "${t}". Be honest about what's shipped vs. directional. ${sub}`.trim();
    case "quarters":
      return `Quarterly view for "${t}". Read across themes, not down — investors care about pace per area. ${sub}`.trim();
    case "quote":
      return `Read the quote out loud, then attribute. Let it breathe before clicking next.`;
    case "dayInLife":
      return `Walk the day chronologically for "${t}". Emphasise that every step happens inside 1INME — no app switching.`;
    case "closing":
      return `Close the section. Name the contacts on screen and the single next step you want from the room. ${sub}`.trim();
    default:
      return `${t}. ${sub}`.trim();
  }
};

// ----- Section tagging for sub-deck export -----
const sectionForPosition = (pos) => {
  for (const sec of sectionRanges) {
    if (pos >= sec.start && pos <= sec.end) {
      return sec.divider.replace(/Divider$/, "").toLowerCase();
    }
  }
  return "intro";
};

const total = spec.length;
const manifest = [];
spec.forEach((s, i) => {
  const pos = i + 1;
  const slug = slugify(s.slug ?? s.title);
  const name = `Slide${String(pos).padStart(3, "0")}${slug}`;
  s.name = name;
  const renderer = RENDERERS[s.layout];
  if (!renderer) throw new Error(`Unknown layout: ${s.layout} for slide ${pos}`);
  const tsx = renderer(s, pos, total);
  const filename = `${name}.tsx`;
  fs.writeFileSync(path.join(slidesDir, filename), tsx);
  manifest.push({
    id: randomUUID(),
    position: pos,
    filepath: `src/pages/slides/${filename}`,
    title: s.title || s.quote || s.eyebrow || `Slide ${pos}`,
    description: (s.description ?? s.subtitle ?? s.title ?? s.quote ?? s.eyebrow ?? `Slide ${pos}`) || `Slide ${pos}`,
    speakerNotes: noteFor(s),
    section: sectionForPosition(pos),
  });
});

fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
console.log(`Generated ${total} slides.`);
