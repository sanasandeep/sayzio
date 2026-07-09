import { Platform } from "react-native";

// ---------------------------------------------------------------------------
// Onboarding slide-image file cache.
//
// The intro carousel caches the admin-managed slide JSON (see lib/secure.ts),
// but the photos themselves used to rely on Image.prefetch warming the
// platform image cache — which can be evicted between launches or be empty
// offline, leaving the bundled underlay visible instead of the admin photo.
//
// This module persists each remote slide image to app storage
// (expo-file-system) alongside the slide JSON, and maps remote URLs to local
// file URIs so cached slides render their real photos immediately on
// relaunch, even offline. Stale files for removed slides are pruned whenever
// a fresh set is persisted.
//
// Web has no app filesystem; every function no-ops there (the browser's own
// HTTP cache covers repeat visits well enough).
// ---------------------------------------------------------------------------

// Maps a remote image URL -> local file URI ready to hand to <Image>.
export type SlideImageMap = Record<string, string>;

// Minimal slide shape we need: just the image URL fields.
export type SlideWithImages = {
  image_url?: string | null;
  image_urls?: readonly string[] | null;
};

const CACHE_DIR_NAME = "onboarding-slide-images";

// Collect every remote image URL referenced by a slide set, deduped,
// preserving first-seen order. Mirrors the precedence used by the
// onboarding screen's resolveImages (image_urls wins over image_url).
export function collectSlideImageUrls(
  slides: readonly SlideWithImages[],
): string[] {
  const seen = new Set<string>();
  const urls: string[] = [];
  for (const s of slides) {
    const list =
      s.image_urls && s.image_urls.length > 0
        ? s.image_urls
        : s.image_url
          ? [s.image_url]
          : [];
    for (const u of list) {
      if (typeof u === "string" && u && !seen.has(u)) {
        seen.add(u);
        urls.push(u);
      }
    }
  }
  return urls;
}

// Deterministic filename for a URL. djb2-xor hash rendered as hex — no
// crypto dependency needed; collisions across a handful of slide URLs are
// practically impossible, and a collision would only mean one image renders
// in place of another until the next refresh. The original extension is
// preserved when it looks like a normal image extension so the platform
// image decoder gets a familiar hint.
function fileNameForUrl(url: string): string {
  let h1 = 5381;
  let h2 = 52711;
  for (let i = 0; i < url.length; i++) {
    const c = url.charCodeAt(i);
    h1 = ((h1 << 5) + h1) ^ c;
    h2 = ((h2 << 5) + h2) ^ (c + 1);
  }
  const hex =
    (h1 >>> 0).toString(16).padStart(8, "0") +
    (h2 >>> 0).toString(16).padStart(8, "0");
  const m = /\.(png|jpe?g|gif|webp|bmp|heic|avif)(?:[?#]|$)/i.exec(url);
  const ext = m ? `.${m[1].toLowerCase()}` : ".img";
  return `${hex}${ext}`;
}

async function loadFs() {
  // Legacy API for parity with the rest of the codebase (calendars, links,
  // marketing strategist all import expo-file-system/legacy).
  return import("expo-file-system/legacy");
}

async function cacheDir(): Promise<string | null> {
  if (Platform.OS === "web") return null;
  try {
    const FileSystem = await loadFs();
    const base = FileSystem.documentDirectory;
    if (!base) return null;
    return `${base}${CACHE_DIR_NAME}/`;
  } catch {
    return null;
  }
}

// Resolve which of a slide set's images already exist on disk. Fast path
// for launch: no network, just existence checks. Returns an empty map on
// web or when nothing is cached.
export async function getLocalSlideImageMap(
  slides: readonly SlideWithImages[],
): Promise<SlideImageMap> {
  const map: SlideImageMap = {};
  const dir = await cacheDir();
  if (!dir) return map;
  try {
    const FileSystem = await loadFs();
    const urls = collectSlideImageUrls(slides);
    if (urls.length === 0) return map;
    await Promise.all(
      urls.map(async (url) => {
        const path = `${dir}${fileNameForUrl(url)}`;
        try {
          const info = await FileSystem.getInfoAsync(path);
          if (info.exists && !info.isDirectory) map[url] = path;
        } catch {
          // Treat as a miss.
        }
      }),
    );
  } catch {
    // Best-effort: an FS failure just means we render remote URLs.
  }
  return map;
}

// Download every image in the slide set to app storage (skipping files
// already present) and prune files that no longer belong to any slide.
// Returns the URL -> local URI map for everything that's on disk after
// the sync. Best-effort throughout: individual download failures leave
// that URL out of the map (the caller falls back to the remote URI and
// the bundled underlay still covers a blank).
export async function persistSlideImages(
  slides: readonly SlideWithImages[],
): Promise<SlideImageMap> {
  const map: SlideImageMap = {};
  const dir = await cacheDir();
  if (!dir) return map;
  try {
    const FileSystem = await loadFs();
    await FileSystem.makeDirectoryAsync(dir, { intermediates: true }).catch(
      () => {},
    );

    const urls = collectSlideImageUrls(slides);
    const wanted = new Set(urls.map(fileNameForUrl));

    await Promise.all(
      urls.map(async (url) => {
        const name = fileNameForUrl(url);
        const path = `${dir}${name}`;
        try {
          const info = await FileSystem.getInfoAsync(path);
          if (info.exists && !info.isDirectory) {
            map[url] = path;
            return;
          }
          const res = await FileSystem.downloadAsync(url, path);
          if (res.status >= 200 && res.status < 300) {
            map[url] = res.uri;
          } else {
            // Don't keep an error body (HTML, etc.) around as an "image".
            await FileSystem.deleteAsync(path, { idempotent: true }).catch(
              () => {},
            );
          }
        } catch {
          // Offline / bad URL — leave this URL unmapped.
        }
      }),
    );

    // Prune files for images no longer referenced by any slide so the
    // cache directory can't grow forever as admins rotate photos.
    try {
      const existing = await FileSystem.readDirectoryAsync(dir);
      await Promise.all(
        existing
          .filter((name) => !wanted.has(name))
          .map((name) =>
            FileSystem.deleteAsync(`${dir}${name}`, { idempotent: true }).catch(
              () => {},
            ),
          ),
      );
    } catch {
      // Pruning is housekeeping only.
    }
  } catch {
    // Best-effort.
  }
  return map;
}
