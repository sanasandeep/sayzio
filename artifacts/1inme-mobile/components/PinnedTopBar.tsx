import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { BlurView } from "expo-blur";
import { useRouter } from "expo-router";
import { Platform, Pressable, StyleSheet, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandIcon } from "@/components/Brand";
import { useDrawer } from "@/contexts/DrawerContext";
import { TOP_BAR_H } from "@/contexts/TabBarContext";
import { useColors, useResolvedScheme } from "@/hooks/useColors";
import { listNotifications } from "@/lib/api/notifications";

export function PinnedTopBar() {
  const colors = useColors();
  const scheme = useResolvedScheme();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { openDrawer } = useDrawer();
  const isDark = scheme === "dark";

  const notifQ = useQuery({
    queryKey: ["notifications-unread-count"],
    queryFn: listNotifications,
    select: (d) => d.unreadCount,
    refetchInterval: 30_000,
    staleTime: 15_000,
  });
  const hasUnread = (notifQ.data ?? 0) > 0;

  const glassBackground =
    Platform.OS === "web"
      ? isDark
        ? "rgba(19,19,28,0.92)"
        : "rgba(247,248,252,0.92)"
      : "transparent";

  const chipBg = isDark ? "rgba(255,255,255,0.10)" : "rgba(0,0,0,0.06)";

  const totalHeight = insets.top + TOP_BAR_H;

  return (
    <View
      style={[
        styles.wrapper,
        {
          height: totalHeight,
        },
      ]}
    >
      {Platform.OS !== "web" ? (
        <BlurView
          intensity={80}
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
          styles.bottomBorder,
          {
            borderColor: isDark
              ? "rgba(255,255,255,0.08)"
              : "rgba(0,0,0,0.07)",
          },
        ]}
        pointerEvents="none"
      />

      <View style={[styles.row, { marginTop: insets.top, height: TOP_BAR_H }]}>
        <Pressable
          onPress={openDrawer}
          onLongPress={openDrawer}
          hitSlop={8}
          accessibilityLabel="Open menu"
          accessibilityRole="button"
          style={[styles.chip, { backgroundColor: chipBg }]}
        >
          <Feather name="menu" size={19} color={colors.foreground} />
        </Pressable>

        <View style={styles.center} pointerEvents="none">
          <BrandIcon size={40} />
        </View>

        <Pressable
          onPress={() => router.push("/notifications")}
          hitSlop={8}
          accessibilityLabel="Notifications"
          accessibilityRole="button"
          style={[styles.chip, { backgroundColor: chipBg }]}
        >
          <Feather name="bell" size={19} color={colors.foreground} />
          {hasUnread && (
            <View
              style={[
                styles.dot,
                {
                  backgroundColor: "#ef4444",
                  borderColor: isDark ? "rgba(19,19,28,1)" : "rgba(247,248,252,1)",
                },
              ]}
            />
          )}
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    zIndex: 10,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 6,
    elevation: 8,
  },
  bottomBorder: {
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 16,
  },
  center: {
    position: "absolute",
    left: 0,
    right: 0,
    alignItems: "center",
    justifyContent: "center",
    height: TOP_BAR_H,
  },
  chip: {
    width: 42,
    height: 36,
    borderRadius: 18,
    alignItems: "center",
    justifyContent: "center",
  },
  dot: {
    position: "absolute",
    top: 5,
    right: 5,
    width: 9,
    height: 9,
    borderRadius: 5,
    borderWidth: 1.5,
  },
});
