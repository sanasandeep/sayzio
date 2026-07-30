// Recently used custom colors (Task: remember creators' custom colors as
// extra swatches). A small AsyncStorage-backed list shared by every
// ColorSwatchRow: when a creator types a valid custom color into any color
// field and applies it, it's remembered here and surfaces as an extra
// tap-to-pick swatch across the block editor and appearance/block-theme
// settings. Persists across app restarts.
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useEffect, useState } from "react";

const STORAGE_KEY = "sayzio.recentCustomColors.v1";
export const MAX_RECENT_COLORS = 8;

// Module-level cache + subscribers so every mounted swatch row stays in
// sync without prop drilling or a context provider.
let cache: string[] = [];
let hydrated = false;
let hydrating: Promise<void> | null = null;
const listeners = new Set<(colors: string[]) => void>();

function notify() {
  for (const l of listeners) l(cache);
}

/** Loose but safe color validation: hex (#rgb/#rgba/#rrggbb/#rrggbbaa) or
 * rgb()/rgba()/hsl()/hsla() function syntax. Rejects anything else so junk
 * half-typed values never become swatches. */
export function isValidColor(value: string): boolean {
  const v = value.trim();
  if (/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(v)) return true;
  return /^(?:rgb|rgba|hsl|hsla)\(\s*[\d.,%\s/-]+\s*\)$/i.test(v);
}

/** Normalize for dedupe/display: trim, lowercase hex. */
export function normalizeColor(value: string): string {
  const v = value.trim();
  return v.startsWith("#") ? v.toLowerCase() : v.replace(/\s+/g, " ");
}

async function hydrate(): Promise<void> {
  if (hydrated) return;
  if (!hydrating) {
    hydrating = AsyncStorage.getItem(STORAGE_KEY)
      .then((raw) => {
        if (raw) {
          try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
              cache = parsed
                .filter((c): c is string => typeof c === "string" && isValidColor(c))
                .slice(0, MAX_RECENT_COLORS);
            }
          } catch {
            // Corrupt payload: start fresh.
          }
        }
        hydrated = true;
        notify();
      })
      .catch(() => {
        hydrated = true;
      });
  }
  return hydrating;
}

/** Remember a custom color the creator actually used. No-op for invalid
 * values. Most-recent-first, deduped, capped. */
export function rememberRecentColor(value: string): void {
  if (!isValidColor(value)) return;
  const norm = normalizeColor(value);
  void hydrate().then(() => {
    const next = [norm, ...cache.filter((c) => c !== norm)].slice(0, MAX_RECENT_COLORS);
    if (next.length === cache.length && next.every((c, i) => c === cache[i])) return;
    cache = next;
    notify();
    AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(cache)).catch(() => {});
  });
}

// Debounced typing capture: while a creator types into a color field we
// wait for a short pause, then remember the value if it's valid. Keyed per
// field (the caller passes a stable key) so switching fields mid-type
// doesn't drop the previous field's finished value.
const typingTimers = new Map<string, ReturnType<typeof setTimeout>>();
export const TYPING_REMEMBER_DELAY_MS = 700;

/** Remember a color as the creator types it (debounced per field).
 * Invalid/half-typed values are ignored by rememberRecentColor. */
export function rememberRecentColorFromTyping(fieldKey: string, value: string): void {
  const prev = typingTimers.get(fieldKey);
  if (prev) clearTimeout(prev);
  typingTimers.set(
    fieldKey,
    setTimeout(() => {
      typingTimers.delete(fieldKey);
      rememberRecentColor(value);
    }, TYPING_REMEMBER_DELAY_MS),
  );
}

/** Remember several colors at once (e.g. all color fields on Apply). */
export function rememberRecentColors(values: Array<string | null | undefined>): void {
  for (const v of values) if (typeof v === "string" && v.trim() !== "") rememberRecentColor(v);
}

/** Hook: the live recent-colors list, hydrated from AsyncStorage. */
export function useRecentColors(): string[] {
  const [colors, setColors] = useState<string[]>(cache);
  useEffect(() => {
    listeners.add(setColors);
    void hydrate().then(() => setColors(cache));
    return () => {
      listeners.delete(setColors);
    };
  }, []);
  return colors;
}
