import Feather from "@expo/vector-icons/Feather";
import { useRouter } from "expo-router";
import {
  Linking,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandWordmark } from "@/components/Brand";
import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";
import { getBaseUrl } from "@/lib/api";

type NavItem = {
  key: string;
  label: string;
  icon: React.ComponentProps<typeof Feather>["name"];
  routeName?: string; // Tabs route to focus
  push?: string; // stack route to push instead
  badge?: number;
};

/**
 * Slide-in navigation drawer replacing the bottom tab bar. Opened from the
 * hamburger button in the header. Shows the brand wordmark, the main
 * sections (Keypad / Contacts / Caller ID / Scan / Events) and useful
 * links (website, main Sayzio app, terms, privacy).
 */
export function DialerDrawer({
  open,
  onClose,
  activeRoute,
  onNavigateTab,
  eventBadgeCount,
}: {
  open: boolean;
  onClose: () => void;
  activeRoute: string | null;
  onNavigateTab: (routeName: string) => void;
  eventBadgeCount?: number;
}) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();

  const baseUrl = getBaseUrl();

  const navItems: NavItem[] = [
    { key: "dialer", label: "Keypad", icon: "grid", routeName: "dialer" },
    { key: "contacts", label: "Contacts", icon: "users", routeName: "contacts" },
    { key: "caller-id", label: "Caller ID", icon: "search", routeName: "caller-id" },
    { key: "scan", label: "Scan business card", icon: "camera", push: "/card-scan" },
    {
      key: "events",
      label: "Events",
      icon: "calendar",
      routeName: "events",
      badge: eventBadgeCount,
    },
  ];

  const linkItems: {
    key: string;
    label: string;
    icon: React.ComponentProps<typeof Feather>["name"];
    onPress: () => void;
  }[] = [
    {
      key: "website",
      label: "Sayzio website",
      icon: "globe",
      onPress: () => void Linking.openURL(baseUrl),
    },
    {
      key: "app",
      label: "Open Sayzio app",
      icon: "external-link",
      onPress: () => void Linking.openURL(`${baseUrl}/user/dashboard`),
    },
    {
      key: "terms",
      label: "Terms of Service",
      icon: "file-text",
      onPress: () => router.push("/info/terms"),
    },
    {
      key: "privacy",
      label: "Privacy Policy",
      icon: "lock",
      onPress: () => router.push("/info/privacy"),
    },
    {
      key: "about",
      label: "About",
      icon: "info",
      onPress: () => router.push("/info/about"),
    },
    {
      key: "help",
      label: "Help & FAQ",
      icon: "help-circle",
      onPress: () => router.push("/info/help"),
    },
  ];

  return (
    <Modal visible={open} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.backdropWrap}>
        {/* Tap outside the panel to dismiss. */}
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Close menu"
          onPress={onClose}
          style={styles.backdrop}
        />
        <View
          style={[
            styles.panel,
            {
              backgroundColor: colors.background,
              borderColor: colors.border,
              paddingTop: insets.top + 18,
              paddingBottom: Math.max(insets.bottom, 16),
            },
          ]}
        >
          <ScrollView showsVerticalScrollIndicator={false}>
            <View style={styles.logoRow}>
              <BrandWordmark size={34} />
              <Pressable
                onPress={onClose}
                hitSlop={10}
                accessibilityRole="button"
                accessibilityLabel="Close menu"
                style={styles.closeBtn}
                {...WEB_FOCUS_RING_PROPS}
              >
                <Feather name="x" size={22} color={colors.mutedForeground} />
              </Pressable>
            </View>

            {navItems.map((item) => {
              const active =
                item.routeName != null && activeRoute === item.routeName;
              const tint = active ? colors.primary : colors.foreground;
              return (
                <Pressable
                  key={item.key}
                  accessibilityRole="button"
                  accessibilityState={active ? { selected: true } : {}}
                  onPress={() => {
                    onClose();
                    if (item.push) router.push(item.push as never);
                    else if (item.routeName) onNavigateTab(item.routeName);
                  }}
                  style={({ pressed }) => [
                    styles.navRow,
                    {
                      backgroundColor: active
                        ? colors.muted
                        : pressed
                          ? colors.muted
                          : "transparent",
                    },
                  ]}
                  {...WEB_FOCUS_RING_PROPS}
                >
                  <View>
                    <Feather name={item.icon} size={20} color={tint} />
                    {item.badge && item.badge > 0 ? (
                      <View
                        style={[styles.badge, { backgroundColor: colors.primary }]}
                      />
                    ) : null}
                  </View>
                  <Text
                    style={{
                      color: tint,
                      fontSize: 16,
                      fontFamily: active
                        ? "SpaceGrotesk_600SemiBold"
                        : "SpaceGrotesk_500Medium",
                    }}
                  >
                    {item.label}
                  </Text>
                </Pressable>
              );
            })}

            <View style={[styles.divider, { backgroundColor: colors.border }]} />
            <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
              LINKS
            </Text>

            {linkItems.map((item) => (
              <Pressable
                key={item.key}
                accessibilityRole="button"
                onPress={() => {
                  onClose();
                  item.onPress();
                }}
                style={({ pressed }) => [
                  styles.navRow,
                  { backgroundColor: pressed ? colors.muted : "transparent" },
                ]}
                {...WEB_FOCUS_RING_PROPS}
              >
                <Feather name={item.icon} size={18} color={colors.mutedForeground} />
                <Text
                  style={{
                    color: colors.foreground,
                    fontSize: 15,
                    fontFamily: "SpaceGrotesk_500Medium",
                  }}
                >
                  {item.label}
                </Text>
              </Pressable>
            ))}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdropWrap: { flex: 1, flexDirection: "row" },
  backdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(0,0,0,0.45)",
  },
  panel: {
    width: 300,
    maxWidth: "84%",
    height: "100%",
    borderRightWidth: 1,
    paddingHorizontal: 18,
    shadowColor: "#000",
    shadowOpacity: 0.3,
    shadowRadius: 18,
    shadowOffset: { width: 6, height: 0 },
    elevation: 16,
    ...(Platform.OS === "web" ? ({ backdropFilter: "blur(6px)" } as object) : null),
  },
  logoRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 22,
  },
  closeBtn: { padding: 4, borderRadius: 12 },
  navRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    paddingVertical: 13,
    paddingHorizontal: 12,
    borderRadius: 14,
  },
  badge: {
    position: "absolute",
    top: -3,
    right: -6,
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  divider: { height: 1, marginVertical: 14 },
  sectionLabel: {
    fontSize: 11,
    letterSpacing: 1.2,
    fontFamily: "SpaceGrotesk_600SemiBold",
    marginBottom: 6,
    paddingHorizontal: 12,
  },
});
