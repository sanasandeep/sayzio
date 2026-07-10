import { Feather } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import { LinearGradient } from "expo-linear-gradient";
import { usePathname, useRouter } from "expo-router";
import { useEffect, useRef } from "react";
import { Platform, Pressable, StyleSheet, Text, View, useWindowDimensions } from "react-native";
import Animated, {
  useAnimatedStyle,
  useReducedMotion,
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
import { TAB_BAR_FOCUS_RING, useWebFocusRing } from "@/lib/webFocusRing";

const TABS: {
  name: string;
  pathname: string;
  icon: keyof typeof Feather.glyphMap;
  label: string;
}[] = [
  { name: "index", pathname: "/", icon: "home", label: "Home" },
  { name: "links", pathname: "/links", icon: "link", label: "Links" },
  { name: "create", pathname: "/create", icon: "plus", label: "Create" },
  { name: "inbox", pathname: "/inbox", icon: "message-circle", label: "Inbox" },
  { name: "profile", pathname: "/profile", icon: "user", label: "Profile" },
];

const CREATE_IDX = 2;

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
  const isWeb = Platform.OS === "web";
  const tabRefs = useRef<(HTMLElement | null)[]>([]);

  const focusRingProps = useWebFocusRing(TAB_BAR_FOCUS_RING, colors.primary);

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

  const containerStyle = useAnimatedStyle(() => ({
    transform: [{ translateY: translateY.value }],
  }));

  const isDark = scheme === "dark";

  const glassBackground =
    Platform.OS === "web"
      ? isDark
        ? "rgba(19,19,28,0.88)"
        : "rgba(247,248,252,0.88)"
      : "transparent";

  // Position of the Create circle within the wrapper
  const createCircleLeft = CREATE_IDX * tabW + (tabW - CIRCLE_SIZE) / 2;

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
      {/* ── Layer 1: glass bar background ─────────────────────────────────── */}
      <View
        style={[
          styles.barBackground,
          {
            borderColor: isDark
              ? "rgba(255,255,255,0.08)"
              : "rgba(0,0,0,0.06)",
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

      {/* ── Layer 2: raised Create circle (visual only, pointer-events off) ─ */}
      <View
        style={[
          styles.createCircle,
          {
            width: CIRCLE_SIZE,
            height: CIRCLE_SIZE,
            borderRadius: CIRCLE_SIZE / 2,
            left: createCircleLeft,
            top: 0,
          },
        ]}
        pointerEvents="none"
      >
        <LinearGradient
          colors={colors.brandGradient}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={StyleSheet.absoluteFill}
        />
      </View>

      {/* ── Layer 3: tab row ─────────────────────────────────────────────── */}
      <View
        style={[styles.tabRow, { height: TAB_BAR_H + CIRCLE_OVERFLOW }]}
        accessibilityRole="tablist"
      >
        {TABS.map((tab, index) => {
          const focused = index === activeIndex;
          const isCreate = index === CREATE_IDX;

          const iconColor = isCreate
            ? "#ffffff"
            : focused
              ? colors.primary
              : colors.mutedForeground;
          const labelColor = focused ? colors.primary : colors.mutedForeground;

          const webProps = isWeb
            ? ({
                ref: (node: HTMLElement | null) => {
                  tabRefs.current[index] = node;
                },
                tabIndex: focused ? 0 : -1,
                onKeyDown: (e: { key?: string; preventDefault?: () => void }) =>
                  handleTabKeyDown(index, e),
                ...(focusRingProps ?? {}),
              } as object)
            : null;

          if (isCreate) {
            return (
              <Pressable
                key={tab.name}
                onPress={() => router.navigate(tab.pathname as never)}
                onLongPress={openDrawer}
                style={styles.createSlot}
                accessibilityRole="tab"
                accessibilityLabel={tab.label}
                accessibilityState={{ selected: focused }}
                aria-selected={focused}
                hitSlop={4}
                {...webProps}
              >
                {/* Invisible — the visual circle is Layer 2 */}
                <View
                  style={{
                    width: CIRCLE_SIZE,
                    height: CIRCLE_SIZE,
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <Feather name="plus" size={26} color={iconColor} style={{ zIndex: 5 }} />
                </View>
              </Pressable>
            );
          }

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
                size={21}
                color={iconColor}
              />
              <Text
                style={[
                  styles.label,
                  { color: labelColor },
                  focused && styles.labelActive,
                ]}
                numberOfLines={1}
              >
                {tab.label}
              </Text>
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
  createCircle: {
    position: "absolute",
    zIndex: 2,
    overflow: "hidden",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.22,
    shadowRadius: 10,
    elevation: 10,
  },
  tabRow: {
    position: "absolute",
    bottom: 0,
    left: 0,
    right: 0,
    flexDirection: "row",
    alignItems: "flex-end",
    zIndex: 3,
  },
  tab: {
    flex: 1,
    height: TAB_BAR_H,
    alignItems: "center",
    justifyContent: "center",
    gap: 4,
  },
  createSlot: {
    flex: 1,
    height: TAB_BAR_H + CIRCLE_OVERFLOW,
    alignItems: "center",
    justifyContent: "flex-start",
    paddingTop: 2,
    zIndex: 4,
  },
  label: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.2,
  },
  labelActive: {
    fontFamily: "SpaceGrotesk_700Bold",
  },
});
