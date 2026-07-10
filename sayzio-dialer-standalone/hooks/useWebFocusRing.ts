import { useEffect } from "react";
import { Platform } from "react-native";

import { useColors } from "@/hooks/useColors";

// ── App-wide web keyboard focus indicator (:focus-visible ring) ────────────
// React Native Web renders Pressables as <div>s (and TextInputs as
// <input>/<textarea>) which RNW often strips of their default focus outline —
// so a sighted keyboard user tabbing through form fields, buttons and other
// controls can't see where focus currently is. This is the shared version of
// the treatment the FloatingTabBar / DrawerSidebar already carry, generalised
// so every high-traffic surface (auth screens, block/settings editors, the
// primary Button + TextField primitives) can opt in with one marker.
//
// Usage:
//   • Spread `WEB_FOCUS_RING_PROPS` onto any focusable control. On web this
//     tags the DOM node with `data-focus-ring="true"`; on native it is null and
//     nothing is added (native rendering is completely untouched).
//   • Call `useWebFocusRing()` once high in the tree (the root layout) to inject
//     the one-time global stylesheet and keep the ring colour tracking the
//     active theme's primary via a CSS custom property.
//
// The ring is scoped to `:focus-visible` so it ONLY appears for keyboard focus,
// never on a mouse/touch press (which would otherwise leave a stray ring on
// tap). The outline follows each control's own border-radius in modern
// browsers, so buttons, pills and inputs all get a correctly-shaped ring.
export const WEB_FOCUS_RING_PROPS =
  Platform.OS === "web"
    ? ({ dataSet: { focusRing: "true" } } as object)
    : null;

const STYLE_ID = "app-focus-visible-style";

export function useWebFocusRing() {
  const colors = useColors();
  const isWeb = Platform.OS === "web";

  // Inject the :focus-visible stylesheet once (guarded by its id; a permanent
  // shell resource, so intentionally NOT removed on unmount). Native is a no-op.
  useEffect(() => {
    if (!isWeb || typeof document === "undefined") return;
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement("style");
    style.id = STYLE_ID;
    style.textContent = [
      // RNW may set outline:none on the control; re-assert nothing on a plain
      // (mouse/touch) focus, then paint a ring only for keyboard focus.
      "[data-focus-ring]:focus { outline: none; }",
      "[data-focus-ring]:focus-visible {",
      "  outline: 2px solid var(--app-focus-ring, #3d6bff);",
      "  outline-offset: 2px;",
      "}",
    ].join("\n");
    document.head.appendChild(style);
  }, [isWeb]);

  // Keep the ring colour in sync with the theme's primary (blue) so it stays
  // correct in light + dark.
  useEffect(() => {
    if (!isWeb || typeof document === "undefined") return;
    document.documentElement.style.setProperty("--app-focus-ring", colors.primary);
  }, [isWeb, colors.primary]);
}
