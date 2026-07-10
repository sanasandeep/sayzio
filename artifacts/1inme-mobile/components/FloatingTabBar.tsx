import { Feather } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import { LinearGradient } from "expo-linear-gradient";
import { usePathname, useRouter } from "expo-router";
import { useEffect } from "react";
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
      <View style={[styles.tabRow, { height: TAB_BAR_H }]}>
        {TABS.map((tab, index) => {
          const focused = index === activeIndex;
          const iconColor = focused ? colors.primaryForeground : colors.mutedForeground;
          const iconSize = tab.name === "create" ? 26 : 21;

          return (
            <Pressable
              key={tab.name}
              onPress={() => {
                if (focused) return;
                router.navigate(tab.pathname as never);
              }}
              onLongPress={openDrawer}
              style={styles.tab}
              accessibilityRole="button"
              accessibilityLabel={tab.label}
              accessibilityState={{ selected: focused }}
              hitSlop={4}
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
