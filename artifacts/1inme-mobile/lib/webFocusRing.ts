import { useEffect } from "react";
import { Platform } from "react-native";

// ── Shared web keyboard focus-ring helper ─────────────────────────────────
// On the web build, React Native Web renders Pressables as plain <div>s with
// NO default focus outline. A sighted keyboard user tabbing/arrowing across a
// navigation surface therefore can't tell where focus currently is. The fix,
// used across every web-navigation surface, is:
//   1. tag each focusable control with a `data-*` marker (via RNW's `dataSet`,
//      which forwards to the DOM node as a data-* attribute), and
//   2. inject a one-time global stylesheet that paints an on-brand ring scoped
//      to `:focus-visible` — so it ONLY appears for keyboard focus, never on a
//      mouse/touch press (which would leave a stray ring on tap).
// The ring colour tracks the theme's primary via a CSS custom property so it
// stays correct in light + dark. Native (iOS/Android) is untouched: the marker
// props are null and no stylesheet is added.
//
// This module is the SINGLE source of that treatment. Every navigation surface
// under `components/` MUST consume it (via `useWebFocusRing`) instead of
// re-inlining its own `dataSet` marker + `:focus-visible` stylesheet, so the
// keyboard-accessibility behaviour can never silently drift per surface. The
// `scripts/check-focus-ring-shared.mjs` guard enforces this.

export type WebFocusRingConfig = {
  /**
   * camelCase key spread into a control's `dataSet`. RNW maps it to a
   * `data-<kebab-case>` attribute that the injected stylesheet targets.
   * e.g. `tabbarTab` → `data-tabbar-tab`.
   */
  dataSetKey: string;
  /** Unique id for the injected <style> element (guards single insertion). */
  styleId: string;
  /** CSS custom property (incl. leading `--`) the ring colour is written to. */
  cssVar: string;
  /** Fallback ring colour when the CSS var is unset. Defaults to `#3d6bff`. */
  fallbackColor?: string;
  /** Ring outline width. Defaults to `2px`. */
  outlineWidth?: string;
  /** Ring outline-offset (usually negative to inset the ring). Defaults `-2px`. */
  outlineOffset?: string;
  /** Ring border-radius so it hugs the control's shape. Defaults `10px`. */
  borderRadius?: string;
};

// Preset for the floating bottom tab bar (components/FloatingTabBar.tsx).
export const TAB_BAR_FOCUS_RING: WebFocusRingConfig = {
  dataSetKey: "tabbarTab",
  styleId: "tabbar-focus-visible-style",
  cssVar: "--tabbar-focus-ring",
  outlineOffset: "-3px",
  borderRadius: "22px",
};

// Preset for the side navigation drawer (components/DrawerSidebar.tsx).
export const DRAWER_FOCUS_RING: WebFocusRingConfig = {
  dataSetKey: "drawerFocusable",
  styleId: "drawer-focus-visible-style",
  cssVar: "--drawer-focus-ring",
  outlineOffset: "-2px",
  borderRadius: "10px",
};

const isWeb = Platform.OS === "web";

// camelCase → kebab-case, mirroring how RNW turns a `dataSet` key into a
// `data-*` attribute. e.g. `tabbarTab` → `tabbar-tab`, `drawerFocusable` →
// `drawer-focusable`.
function dataSetKeyToAttr(key: string): string {
  const kebab = key.replace(/([A-Z])/g, "-$1").toLowerCase();
  return `data-${kebab}`;
}

// The CSS text for a config's `:focus-visible` ring. Exported so tooling/tests
// can assert the treatment without touching the DOM.
export function buildFocusRingCss(config: WebFocusRingConfig): string {
  const attr = dataSetKeyToAttr(config.dataSetKey);
  const fallback = config.fallbackColor ?? "#3d6bff";
  const width = config.outlineWidth ?? "2px";
  const offset = config.outlineOffset ?? "-2px";
  const radius = config.borderRadius ?? "10px";
  return [
    // RNW may set outline:none on the pressable; re-assert nothing on a plain
    // (mouse/touch) focus, then paint a ring only for keyboard focus.
    `[${attr}]:focus { outline: none; }`,
    `[${attr}]:focus-visible {`,
    `  outline: ${width} solid var(${config.cssVar}, ${fallback});`,
    `  outline-offset: ${offset};`,
    `  border-radius: ${radius};`,
    `}`,
  ].join("\n");
}

// Insert the config's stylesheet once (guarded by its id). The stylesheet is a
// permanent app-shell resource and intentionally NOT removed on unmount — the
// CSS-var-driven colour is what changes per theme, not the rule itself.
export function ensureFocusRingStyle(config: WebFocusRingConfig): void {
  if (!isWeb || typeof document === "undefined") return;
  if (document.getElementById(config.styleId)) return;
  const style = document.createElement("style");
  style.id = config.styleId;
  style.textContent = buildFocusRingCss(config);
  document.head.appendChild(style);
}

// Point the config's CSS custom property at the current theme's ring colour.
export function setFocusRingColor(
  config: WebFocusRingConfig,
  color: string,
): void {
  if (!isWeb || typeof document === "undefined") return;
  document.documentElement.style.setProperty(config.cssVar, color);
}

// The marker props to spread onto a focusable control. `null` on native so
// nothing is added there.
export function focusRingMarkerProps(
  config: WebFocusRingConfig,
): object | null {
  return isWeb ? ({ dataSet: { [config.dataSetKey]: "true" } } as object) : null;
}

/**
 * Install the shared keyboard focus-ring treatment for a navigation surface and
 * return the marker props to spread onto each focusable control.
 *
 * @param config    a preset (TAB_BAR_FOCUS_RING / DRAWER_FOCUS_RING) or custom.
 * @param ringColor the theme's ring colour (typically `colors.primary`).
 * @returns `{ dataSet: {...} }` on web (spread onto each control), else `null`.
 */
export function useWebFocusRing(
  config: WebFocusRingConfig,
  ringColor: string,
): object | null {
  const {
    styleId,
    cssVar,
    dataSetKey,
    fallbackColor,
    outlineWidth,
    outlineOffset,
    borderRadius,
  } = config;

  useEffect(() => {
    ensureFocusRingStyle(config);
    // Depend on primitive config fields so a preset object identity change
    // between renders doesn't needlessly re-run the (idempotent) insertion.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    styleId,
    cssVar,
    dataSetKey,
    fallbackColor,
    outlineWidth,
    outlineOffset,
    borderRadius,
  ]);

  useEffect(() => {
    setFocusRingColor(config, ringColor);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cssVar, ringColor]);

  return focusRingMarkerProps(config);
}
