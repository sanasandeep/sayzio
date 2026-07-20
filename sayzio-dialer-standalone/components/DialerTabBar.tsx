import Feather from "@expo/vector-icons/Feather";
import type { BottomTabBarProps } from "@react-navigation/bottom-tabs";
import { LinearGradient } from "expo-linear-gradient";
import { useRouter } from "expo-router";
import { Platform, Pressable, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";

/**
 * Floating glass tab bar mirroring the main Sayzio app's FloatingTabBar
 * style: a rounded pill hovering above the bottom edge with a raised
 * gradient "Call" circle in the centre.
 *
 * Slots (left → right): Contacts · Caller ID · [Call] · Scan · Events.
 * "Call" activates the dialer tab (keypad); "Scan" pushes the card-scan
 * screen — it is a pushed route, not a tab, so it never highlights.
 */
export const TAB_BAR_CLEARANCE = 96;

type SideTab = {
  key: string;
  label: string;
  icon: React.ComponentProps<typeof Feather>["name"];
  routeName?: string; // Tabs route to focus
  push?: string; // stack route to push instead
  badge?: number;
};

export function DialerTabBar({ state, navigation, eventBadgeCount }: BottomTabBarProps & { eventBadgeCount?: number }) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();

  const activeName = state.routes[state.index]?.name;

  const goTab = (routeName: string) => {
    const route = state.routes.find((r) => r.name === routeName);
    if (!route) return;
    const event = navigation.emit({
      type: "tabPress",
      target: route.key,
      canPreventDefault: true,
    });
    if (!event.defaultPrevented) {
      navigation.navigate(route.name);
    }
  };

  const leftTabs: SideTab[] = [
    { key: "contacts", label: "Contacts", icon: "users", routeName: "contacts" },
    { key: "caller-id", label: "Caller ID", icon: "search", routeName: "caller-id" },
  ];
  const rightTabs: SideTab[] = [
    { key: "scan", label: "Scan", icon: "camera", push: "/card-scan" },
    {
      key: "events",
      label: "Events",
      icon: "calendar",
      routeName: "events",
      badge: eventBadgeCount,
    },
  ];

  const renderSide = (tab: SideTab) => {
    const active = tab.routeName != null && activeName === tab.routeName;
    const tint = active ? colors.primary : colors.mutedForeground;
    return (
      <Pressable
        key={tab.key}
        accessibilityRole="button"
        accessibilityState={active ? { selected: true } : {}}
        accessibilityLabel={tab.label}
        onPress={() => {
          if (tab.push) router.push(tab.push as never);
          else if (tab.routeName) goTab(tab.routeName);
        }}
        style={styles.sideItem}
        {...WEB_FOCUS_RING_PROPS}
      >
        <View>
          <Feather name={tab.icon} size={21} color={tint} />
          {tab.badge && tab.badge > 0 ? (
            <View style={[styles.badge, { backgroundColor: colors.primary }]} />
          ) : null}
        </View>
        <Text
          style={[
            styles.sideLabel,
            {
              color: tint,
              fontFamily: active
                ? "SpaceGrotesk_600SemiBold"
                : "SpaceGrotesk_500Medium",
            },
          ]}
          numberOfLines={1}
        >
          {tab.label}
        </Text>
      </Pressable>
    );
  };

  const dialerActive = activeName === "dialer";

  return (
    <View
      pointerEvents="box-none"
      style={[styles.wrap, { bottom: Math.max(insets.bottom, 8) + 8 }]}
    >
      <View
        style={[
          styles.bar,
          {
            backgroundColor: colors.card,
            borderColor: colors.border,
          },
        ]}
      >
        {leftTabs.map(renderSide)}

        {/* Raised centre Call button — opens the dialer keypad. */}
        <View style={styles.centerSlot} pointerEvents="box-none">
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Call"
            accessibilityState={dialerActive ? { selected: true } : {}}
            onPress={() => goTab("dialer")}
            style={styles.centerPressable}
            {...WEB_FOCUS_RING_PROPS}
          >
            <LinearGradient
              colors={colors.ctaGradient}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={[
                styles.centerCircle,
                dialerActive && { borderColor: colors.primary },
              ]}
            >
              <Feather name="phone" size={24} color="#ffffff" />
            </LinearGradient>
            <Text
              style={[
                styles.centerLabel,
                {
                  color: dialerActive ? colors.primary : colors.mutedForeground,
                  fontFamily: dialerActive
                    ? "SpaceGrotesk_600SemiBold"
                    : "SpaceGrotesk_500Medium",
                },
              ]}
            >
              Call
            </Text>
          </Pressable>
        </View>

        {rightTabs.map(renderSide)}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    position: "absolute",
    left: 14,
    right: 14,
    alignItems: "stretch",
  },
  bar: {
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 30,
    borderWidth: 1,
    height: 62,
    paddingHorizontal: 6,
    shadowColor: "#000",
    shadowOpacity: 0.22,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 6 },
    elevation: 10,
    ...(Platform.OS === "web"
      ? ({ backdropFilter: "blur(14px)" } as object)
      : null),
  },
  sideItem: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    gap: 3,
    paddingVertical: 6,
    borderRadius: 18,
  },
  sideLabel: {
    fontSize: 10.5,
  },
  badge: {
    position: "absolute",
    top: -3,
    right: -6,
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  centerSlot: {
    flex: 1.15,
    alignItems: "center",
  },
  centerPressable: {
    alignItems: "center",
    marginTop: -26,
    borderRadius: 34,
  },
  centerCircle: {
    width: 58,
    height: 58,
    borderRadius: 29,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 2,
    borderColor: "rgba(255,255,255,0.25)",
    shadowColor: "#000",
    shadowOpacity: 0.3,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 5 },
    elevation: 12,
  },
  centerLabel: {
    fontSize: 10.5,
    marginTop: 2,
  },
});
