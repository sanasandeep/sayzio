import type { ComponentType } from "react";

import manifestJson from "@/data/slides-manifest.json";
import { parseSlidesManifest, type SlideEntry } from "@/data/slidesManifestSchema";

export interface LoadedSlide extends SlideEntry {
  Component: ComponentType;
}

// Eagerly include every slide module — both the master deck and any
// exported sub-decks. The lookup below resolves a manifest's
// `filepath` (which always starts with `src/pages/`) to the matching
// module key.
const slideModules: Record<string, { default: ComponentType }> = {
  ...import.meta.glob("./pages/slides/*.tsx", { eager: true }),
  ...import.meta.glob("./pages/subdecks/**/*.tsx", { eager: true }),
};

// Eagerly include every exported sub-deck manifest so the viewer can
// switch decks via the `?subdeck=<name>` URL parameter.
const subdeckManifests: Record<string, unknown> = import.meta.glob(
  "./data/subdecks/*.json",
  { eager: true, import: "default" },
);

function getSubdeckNameFromUrl(): string | null {
  if (typeof window === "undefined") return null;
  const params = new URLSearchParams(window.location.search);
  const value = params.get("subdeck");
  return value && value.trim() ? value.trim() : null;
}

function pickRawManifest(): unknown {
  const subdeckName = getSubdeckNameFromUrl();
  if (!subdeckName) return manifestJson;
  const key = `./data/subdecks/${subdeckName}.json`;
  const found = subdeckManifests[key];
  if (!found) {
    const available = Object.keys(subdeckManifests)
      .map((k) => k.replace(/^\.\/data\/subdecks\/(.+)\.json$/, "$1"))
      .join(", ");
    throw new Error(
      `Sub-deck "${subdeckName}" not found. Available: ${available || "(none)"}. ` +
        `Generate one with: node scripts/export-subdeck.mjs --sections <keys>`,
    );
  }
  return found;
}

function loadManifestSlides(): SlideEntry[] {
  try {
    return parseSlidesManifest(pickRawManifest());
  } catch (error) {
    const reason = error instanceof Error ? error.message : "unknown error";
    throw new Error(
      `Invalid slide manifest. Run "pnpm run validate-slides" for details. ${reason}`,
    );
  }
}

const manifestSlides = loadManifestSlides();

export const slides: LoadedSlide[] = [...manifestSlides]
  .sort((a, b) => a.position - b.position)
  .map((entry) => {
    // Manifest filepaths look like `src/pages/slides/X.tsx` or
    // `src/pages/subdecks/<name>/X.tsx`. Strip the leading `src/` to
    // match the import.meta.glob keys (`./pages/...`).
    const stripped = entry.filepath.replace(/^src\//, "");
    const key = `./${stripped}`;
    const mod = slideModules[key];

    if (!mod) {
      const available = Object.keys(slideModules).join(", ");
      throw new Error(
        `Slide "${entry.title}" references missing file: ${entry.filepath}. ` +
          `Available modules: ${available}`,
      );
    }

    return {
      ...entry,
      Component: mod.default,
    };
  });
