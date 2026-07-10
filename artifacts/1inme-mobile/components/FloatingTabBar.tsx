import { Feather } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import { LinearGradient } from "expo-linear-gradient";
import { usePathname, useRouter } from "expo-router";
import { useEffect, useRef } from "react";
import { Platform, Pressable, StyleSheet, Text, View, useWindowDimensions } from "react-native";
import Animated, {
  useAnimatedStyle,
  useReducedMotion,
  useSharedValue,
  withSpring,
} from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import {
  CIRCLE_OVERFLOW,
  CIRCLE_SIZE,
  TAB_BAR_H,
  TAB_BOTTOM_MARGIN,
  TAB_SIDE_MARGIN,
  useTabBar,
} from "@/contexts/TabBarContext";
import { useColors, useResolvedScheme } from "@/hooks/useColors";
import { useDrawer } from "@/contexts/DrawerContext";

const TABS: {
  name: string;
  pathname: string;
  icon: keyof typeof Feather.glyphMap;
  label: string;
}[] = [
  { name: "index", pathname: "/", icon: "home", label: "Home" },
  { name: "links", pathname: "/links", icon: "link", label: "Links" },
  { name: "create", pathname: "/create", icon: "plus-circle", label: "Create" },
  { name: "inbox", pathname: "/inbox", icon: "message-circle", label: "Inbox" },
  { name: "profile", pathname: "/profile", icon: "user", label: "Profile" },
];

function getActiveIndex(pathname: string): number {
  if (pathname === "/" || pathname === "") return 0;
  const seg = "/" + (pathname.split("/").filter(Boolean)[0] ?? "");
  const idx = TABS.findIndex((t) => t.pathname === seg);
  return idx >= 0 ? idx : 0;
}

export function FloatingTabBar() {
  const colors = useColors();
  const scheme = useResolvedScheme();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const { translateY } = useTabBar();
  const router = useRouter();
  const pathname = usePathname();
  const { openDrawer } = useDrawer();
  const reducedMotion = useReducedMotion();

  const barWidth = width - TAB_SIDE_MARGIN * 2;
  const numTabs = TABS.length;
  const tabW = barWidth / numTabs;

  const activeIndex = getActiveIndex(pathname);

  // ── Web keyboard accessibility (WAI-ARIA tab pattern) ──────────────────
  // On web the tab row is a role="tablist". The pattern expects a roving
  // tabindex: only the active tab sits in the natural tab order (tabIndex 0)
  // and the arrow keys move focus between tabs (Left/Up ← previous, Right/Down
  // → next, Home/End → first/last, wrapping). Enter/Space activates the focused
  // tab (a role="tab" renders as a <div>, which does NOT synthesise a click on
  // Enter/Space the way a native <button> would, so we navigate explicitly).
  // Native (iOS/Android) is untouched — none of these props are set there.
  const isWeb = Platform.OS === "web";
  const tabRefs = useRef<(HTMLElement | null)[]>([]);

  // ── Web keyboard focus indicator (:focus-visible ring) ─────────────────
  // Each tab renders as a React Native Web <div>, which has NO default focus
  // outline, so a sighted keyboard user arrowing across the tabs can't see
  // where focus currently is. We tag every tab with `data-tabbar-tab` and
  // inject a one-time global stylesheet that paints an on-brand focus ring —
  // scoped to `:focus-visible` so it ONLY appears for keyboard focus, never on
  // mouse/touch press (which would leave a stray ring on tap). The ring colour
  // tracks the theme's primary (blue) via a CSS custom property so it stays
  // correct in light + dark. Native (iOS/Android) is untouched.
  useEffect(() => {
    if (!isWeb || typeof document === "undefined") return;
    // Deliberate persistent singleton: the stylesheet is inserted once (guarded
    // by its id) and intentionally NOT removed on unmount. The tab bar is a
    // permanent app shell element, so re-inserting/removing on every mount would
    // just churn; the CSS-var-driven ring colour is what changes per theme.
    const STYLE_ID = "tabbar-focus-visible-style";
    if (!document.getElementById(STYLE_ID)) {
      const style = document.createElement("style");
      style.id = STYLE_ID;
      style.textContent = [
        // RNW may set outline:none on the pressable; re-assert nothing on a
        // plain (mouse/touch) focus, then paint a ring only for keyboard focus.
        "[data-tabbar-tab]:focus { outline: none; }",
        "[data-tabbar-tab]:focus-visible {",
        "  outline: 2px solid var(--tabbar-focus-ring, #3d6bff);",
        "  outline-offset: -3px;",
        "  border-radius: 22px;",
        "}",
      ].join("\n");
      document.head.appendChild(style);
    }
  }, [isWeb]);

  useEffect(() => {
    if (!isWeb || typeof document === "undefined") return;
    document.documentElement.style.setProperty(
      "--tabbar-focus-ring",
      colors.primary,
    );
  }, [isWeb, colors.primary]);

  const navigateToTab = (index: number) => {
    const tab = TABS[index];
    if (!tab || index === activeIndex) return;
    router.navigate(tab.pathname as never);
  };

  const handleTabKeyDown = (index: number, e: { key?: string; preventDefault?: () => void }) => {
    const key = e?.key;
    if (!key) return;
    const last = TABS.length - 1;
    let target: number | null = null;
    if (key === "ArrowRight" || key === "ArrowDown") {
      target = index === last ? 0 : index + 1;
    } else if (key === "ArrowLeft" || key === "ArrowUp") {
      target = index === 0 ? last : index - 1;
    } else if (key === "Home") {
      target = 0;
    } else if (key === "End") {
      target = last;
    } else if (key === "Enter" || key === " " || key === "Spacebar") {
      e.preventDefault?.();
      navigateToTab(index);
      return;
    }
    if (target === null) return;
    e.preventDefault?.();
    tabRefs.current[target]?.focus?.();
  };

  const circleX = useSharedValue(activeIndex * tabW + (tabW - CIRCLE_SIZE) / 2);
  const circleScale = useSharedValue(1);

  useEffect(() => {
    circleX.value = withSpring(activeIndex * tabW + (tabW - CIRCLE_SIZE) / 2, {
      damping: 22,
      stiffness: 220,
      mass: 0.8,
    });
    if (!reducedMotion) {
      circleScale.value = withSpring(
        1.12,
        { damping: 8, stiffness: 400, mass: 0.4 },
        () => {
          circleScale.value = withSpring(1, { damping: 14, stiffness: 220 });
        },
      );
    }
  }, [activeIndex, tabW, circleX, circleScale, reducedMotion]);

  const containerStyle = useAnimatedStyle(() => ({
    transform: [{ translateY: translateY.value }],
  }));

  const circleStyle = useAnimatedStyle(() => ({
    left: circleX.value,
    transform: [{ scale: circleScale.value }],
  }));

  const isDark = scheme === "dark";

  // Legibility treatment for the ACTIVE tab's icon + label, which sit on top
  // of the sliding blue→indigo→magenta gradient circle. A subtle text shadow
  // keeps the glyph/word crisp over every hue stop; the shadow contrasts with
  // the foreground colour (dark halo under white text in light mode, light
  // halo under near-black text in dark mode). Paired with a bolder weight on
  // the label so the active state never washes out over the mid-tone stops.
  const activeTextShadow = {
    textShadowColor: isDark ? "rgba(255,255,255,0.45)" : "rgba(0,0,0,0.35)",
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 3,
  } as const;

  const glassBackground =
    Platform.OS === "web"
      ? isDark
        ? "rgba(19,19,28,0.88)"
        : "rgba(247,248,252,0.88)"
      : "transparent";

  return (
    <Animated.View
      style={[
        styles.wrapper,
        {
          bottom: insets.bottom + TAB_BOTTOM_MARGIN,
          left: TAB_SIDE_MARGIN,
          right: TAB_SIDE_MARGIN,
          height: TAB_BAR_H + CIRCLE_OVERFLOW,
          pointerEvents: "box-none",
        },
        containerStyle,
      ]}
    >
      {/* ── Layer 1 (bottom): glass bar background ─────────────────────── */}
      {/* Rendered first so the BlurView sits below the circle.
          overflow:hidden here is safe because it only clips its own
          BlurView/border children — the circle is a sibling, not a child. */}
      <View
        style={[
          styles.barBackground,
          {
            borderColor: colors.border,
          },
        ]}
      >
        {Platform.OS !== "web" ? (
          <BlurView
            intensity={72}
            tint={isDark ? "dark" : "light"}
            style={StyleSheet.absoluteFill}
          />
        ) : (
          <View
            style={[StyleSheet.absoluteFill, { backgroundColor: glassBackground }]}
          />
        )}

        <View
          style={[
            StyleSheet.absoluteFill,
            styles.barBorder,
            {
              borderColor: isDark
                ? "rgba(255,255,255,0.08)"
                : "rgba(0,0,0,0.06)",
            },
          ]}
          pointerEvents="none"
        />
      </View>

      {/* ── Layer 2 (middle): active indicator circle ───────────────────── */}
      {/* Positioned absolutely at top:0 within the taller wrapper so it
          rises CIRCLE_OVERFLOW pixels above the bar background.  Painted
          after the bar background so it always sits on top of the blur —
          no distortion. zIndex:2 ensures it stays above the bar layer. */}
      <Animated.View
        style={[
          styles.circle,
          {
            width: CIRCLE_SIZE,
            height: CIRCLE_SIZE,
            borderRadius: CIRCLE_SIZE / 2,
            top: CIRCLE_OVERFLOW + (TAB_BAR_H - CIRCLE_SIZE) / 2,
            overflow: "hidden",
          },
          circleStyle,
        ]}
      >
        <LinearGradient
          colors={colors.brandGradient}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={StyleSheet.absoluteFill}
        />
      </Animated.View>

      {/* ── Layer 3 (top): tab icon row ─────────────────────────────────── */}
      {/* Absolutely positioned to fill the bar area (bottom:0).
          zIndex:3 keeps icons readable above the circle at all positions. */}
      <View
        style={[styles.tabRow, { height: TAB_BAR_H }]}
        accessibilityRole="tablist"
      >
        {TABS.map((tab, index) => {
          const focused = index === activeIndex;
          const iconColor = focused ? colors.primaryForeground : colors.mutedForeground;
          const iconSize = tab.name === "create" ? 26 : 21;

          // Web-only roving tabindex + keyboard handling. These props are not
          // part of the React Native Pressable type, but React Native Web
          // forwards `tabIndex`/`onKeyDown` to the underlying DOM node.
          const webProps = isWeb
            ? ({
                ref: (node: HTMLElement | null) => {
                  tabRefs.current[index] = node;
                },
                tabIndex: focused ? 0 : -1,
                onKeyDown: (e: { key?: string; preventDefault?: () => void }) =>
                  handleTabKeyDown(index, e),
                // Marker the injected :focus-visible stylesheet targets so a
                // keyboard-focused tab shows an on-brand focus ring.
                dataSet: { tabbarTab: "true" },
              } as object)
            : null;

          return (
            <Pressable
              key={tab.name}
              onPress={() => {
                if (focused) return;
                router.navigate(tab.pathname as never);
              }}
              onLongPress={openDrawer}
              style={styles.tab}
              accessibilityRole="tab"
              accessibilityLabel={tab.label}
              accessibilityState={{ selected: focused }}
              aria-selected={focused}
              hitSlop={4}
              {...webProps}
            >
              <Feather
                name={tab.icon}
                size={iconSize}
                color={iconColor}
                style={focused ? activeTextShadow : undefined}
              />
              {tab.name !== "create" && (
                <Text
                  style={[
                    styles.label,
                    { color: iconColor },
                    focused && styles.labelActive,
                    focused && activeTextShadow,
                  ]}
                  numberOfLines={1}
                >
                  {tab.label}
                </Text>
              )}
            </Pressable>
          );
        })}
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    position: "absolute",
    left: 0,
    right: 0,
  },
  // ── Layer 1: bar background (blur + border) ────────────────────────────
  // Sits at the bottom of the wrapper (height TAB_BAR_H), CIRCLE_OVERFLOW
  // below the wrapper top.  overflow:hidden clips the blur/border inside
  // the rounded pill without affecting the circle sibling.
  barBackground: {
    position: "absolute",
    bottom: 0,
    left: 0,
    right: 0,
    height: TAB_BAR_H,
    borderRadius: 32,
    overflow: "hidden",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.12,
    shadowRadius: 16,
    elevation: 12,
  },
  barBorder: {
    borderRadius: 32,
    borderWidth: 1,
  },
  // ── Layer 2: circle ───────────────────────────────────────────────────
  // top:0 = flush with wrapper top = CIRCLE_OVERFLOW above bar background.
  circle: {
    position: "absolute",
    zIndex: 2,
  },
  // ── Layer 3: tab row ──────────────────────────────────────────────────
  // Fills the bar area (bottom:0, height:TAB_BAR_H). zIndex:3 keeps
  // icons above the circle.
  tabRow: {
    position: "absolute",
    bottom: 0,
    left: 0,
    right: 0,
    flexDirection: "row",
    alignItems: "center",
    zIndex: 3,
  },
  tab: {
    flex: 1,
    height: "100%",
    alignItems: "center",
    justifyContent: "center",
    gap: 3,
  },
  label: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.2,
  },
  // Active label uses the bolder weight so it reads clearly over the gradient
  // circle; the theme-aware text shadow is applied inline (activeTextShadow).
  labelActive: {
    fontFamily: "SpaceGrotesk_700Bold",
  },
});
