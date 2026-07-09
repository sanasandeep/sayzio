import { Feather } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import { usePathname, useRouter } from "expo-router";
import { useEffect } from "react";
import { Platform, Pressable, StyleSheet, Text, View, useWindowDimensions } from "react-native";
import Animated, {
  useAnimatedStyle,
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

  const barWidth = width - TAB_SIDE_MARGIN * 2;
  const numTabs = TABS.length;
  const tabW = barWidth / numTabs;

  const activeIndex = getActiveIndex(pathname);

  const circleX = useSharedValue(activeIndex * tabW + (tabW - CIRCLE_SIZE) / 2);

  useEffect(() => {
    circleX.value = withSpring(activeIndex * tabW + (tabW - CIRCLE_SIZE) / 2, {
      damping: 22,
      stiffness: 220,
      mass: 0.8,
    });
  }, [activeIndex, tabW, circleX]);

  const containerStyle = useAnimatedStyle(() => ({
    transform: [{ translateY: translateY.value }],
  }));

  const circleStyle = useAnimatedStyle(() => ({
    left: circleX.value,
  }));

  const isDark = scheme === "dark";

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
          pointerEvents: "box-none",
        },
        containerStyle,
      ]}
    >
      {/* Active indicator circle — rendered first so it sits behind icons */}
      <Animated.View
        style={[
          styles.circle,
          {
            backgroundColor: colors.primary,
            width: CIRCLE_SIZE,
            height: CIRCLE_SIZE,
            borderRadius: CIRCLE_SIZE / 2,
            top: -CIRCLE_OVERFLOW,
          },
          circleStyle,
        ]}
      />

      {/* Glass bar */}
      <View
        style={[
          styles.bar,
          {
            height: TAB_BAR_H,
            borderColor: colors.border,
            borderRadius: 32,
            overflow: "hidden",
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

        {/* Subtle border overlay for glass look */}
        <View
          style={[
            StyleSheet.absoluteFill,
            {
              borderRadius: 32,
              borderWidth: 1,
              borderColor: isDark
                ? "rgba(255,255,255,0.08)"
                : "rgba(0,0,0,0.06)",
            },
          ]}
          pointerEvents="none"
        />

        {/* Tab row */}
        <View style={styles.tabRow}>
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
                <Feather name={tab.icon} size={iconSize} color={iconColor} />
                {tab.name !== "create" && (
                  <Text
                    style={[
                      styles.label,
                      { color: iconColor },
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
  circle: {
    position: "absolute",
    zIndex: 1,
  },
  bar: {
    width: "100%",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.12,
    shadowRadius: 16,
    elevation: 12,
  },
  tabRow: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    zIndex: 2,
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
});
