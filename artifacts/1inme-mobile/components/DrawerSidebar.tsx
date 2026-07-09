import { Feather } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import { useRouter } from "expo-router";
import { useEffect } from "react";
import {
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from "react-native";
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withSpring,
  withTiming,
} from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useDrawer } from "@/contexts/DrawerContext";
import { useAuth } from "@/contexts/AuthContext";
import { useColors, useResolvedScheme } from "@/hooks/useColors";
import { BrandWordmark } from "@/components/Brand";

const DRAWER_WIDTH_FRAC = 0.78;
const MAX_DRAWER_W = 320;

type NavItem = {
  label: string;
  icon: keyof typeof Feather.glyphMap;
  href: string;
};

type NavGroup = {
  title: string;
  items: NavItem[];
};

const NAV_GROUPS: NavGroup[] = [
  {
    title: "Main",
    items: [
      { label: "Dashboard", icon: "home", href: "/" },
      { label: "Links", icon: "link", href: "/links" },
      { label: "Create", icon: "plus-circle", href: "/create" },
      { label: "Inbox", icon: "message-circle", href: "/inbox" },
    ],
  },
  {
    title: "Analytics & QR",
    items: [
      { label: "Visitors", icon: "users", href: "/visitors" },
      { label: "QR Studio", icon: "grid", href: "/qr-studio" },
      { label: "QR Codes", icon: "grid", href: "/qr" },
      { label: "Backlinks", icon: "link-2", href: "/backlinks" },
    ],
  },
  {
    title: "Content",
    items: [
      { label: "Posts", icon: "message-square", href: "/posts" },
      { label: "Forms", icon: "file-text", href: "/forms" },
      { label: "Events", icon: "map-pin", href: "/events" },
      { label: "My Tickets", icon: "credit-card", href: "/events/my-tickets" },
      { label: "Resume Builder", icon: "file-text", href: "/resume" },
      { label: "My Calendar", icon: "calendar", href: "/calendars" },
      { label: "Calendar Sync", icon: "refresh-cw", href: "/calendar" },
      { label: "Splash Pages", icon: "layout", href: "/splash" },
    ],
  },
  {
    title: "Audience",
    items: [
      { label: "Subscribers", icon: "user-plus", href: "/subscribers" },
      { label: "Followers", icon: "user-check", href: "/followers" },
      { label: "Social Accounts", icon: "share-2", href: "/social" },
      { label: "Leaderboard", icon: "award", href: "/leaderboard" },
    ],
  },
  {
    title: "Business",
    items: [
      { label: "Orders", icon: "shopping-bag", href: "/orders" },
      { label: "Client Portals", icon: "briefcase", href: "/client-portals" },
      { label: "Invoices", icon: "file-text", href: "/invoices" },
      { label: "Projects", icon: "folder", href: "/projects" },
      { label: "Delivery Projects", icon: "clipboard", href: "/delivery-projects" },
    ],
  },
  {
    title: "Tools",
    items: [
      { label: "Cloud Files", icon: "cloud", href: "/cloud-files" },
      { label: "AI Brand Kit", icon: "feather", href: "/brand-kits" },
      { label: "Workspaces", icon: "briefcase", href: "/workspaces" },
      { label: "Vault", icon: "lock", href: "/vault" },
      { label: "Insider & Referrals", icon: "award", href: "/insider" },
    ],
  },
  {
    title: "Settings",
    items: [
      { label: "Edit Profile", icon: "edit-3", href: "/profile-edit" },
      { label: "Security", icon: "shield", href: "/security" },
      { label: "Notifications", icon: "bell", href: "/notifications" },
      { label: "Custom Domains", icon: "globe", href: "/domains" },
      { label: "Integrations", icon: "link", href: "/integrations" },
      { label: "Verification", icon: "award", href: "/verification" },
    ],
  },
];

export function DrawerSidebar() {
  const { isOpen, closeDrawer } = useDrawer();
  const { user } = useAuth();
  const colors = useColors();
  const scheme = useResolvedScheme();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const router = useRouter();

  const drawerW = Math.min(width * DRAWER_WIDTH_FRAC, MAX_DRAWER_W);
  const isDark = scheme === "dark";

  const translateX = useSharedValue(-drawerW);
  const backdropOpacity = useSharedValue(0);

  useEffect(() => {
    if (isOpen) {
      translateX.value = withSpring(0, { damping: 22, stiffness: 200, mass: 0.9 });
      backdropOpacity.value = withTiming(1, { duration: 250 });
    } else {
      translateX.value = withSpring(-drawerW, { damping: 22, stiffness: 200, mass: 0.9 });
      backdropOpacity.value = withTiming(0, { duration: 220 });
    }
  }, [isOpen, drawerW, translateX, backdropOpacity]);

  const backdropStyle = useAnimatedStyle(() => ({
    opacity: backdropOpacity.value,
  }));

  const panelStyle = useAnimatedStyle(() => ({
    transform: [{ translateX: translateX.value }],
  }));

  const navigate = (href: string) => {
    closeDrawer();
    router.push(href as never);
  };

  return (
    <Animated.View
      style={[
        StyleSheet.absoluteFill,
        styles.container,
        { pointerEvents: isOpen ? "box-none" : "none" },
      ]}
    >
      {/* Backdrop */}
      <Animated.View style={[StyleSheet.absoluteFill, backdropStyle]}>
        <Pressable style={StyleSheet.absoluteFill} onPress={closeDrawer}>
          <View
            style={[
              StyleSheet.absoluteFill,
              {
                backgroundColor: isDark
                  ? "rgba(0,0,0,0.6)"
                  : "rgba(0,0,0,0.35)",
              },
            ]}
          />
        </Pressable>
      </Animated.View>

      {/* Drawer panel */}
      <Animated.View
        style={[
          styles.panel,
          {
            width: drawerW,
            paddingTop: insets.top,
            paddingBottom: insets.bottom,
            borderRightColor: colors.border,
          },
          panelStyle,
        ]}
        pointerEvents={isOpen ? "box-none" : "none"}
      >
        {Platform.OS !== "web" ? (
          <BlurView
            intensity={80}
            tint={isDark ? "dark" : "light"}
            style={StyleSheet.absoluteFill}
          />
        ) : (
          <View
            style={[
              StyleSheet.absoluteFill,
              {
                backgroundColor: isDark
                  ? "rgba(10,10,15,0.92)"
                  : "rgba(247,248,252,0.94)",
              },
            ]}
          />
        )}

        {/* Right-edge subtle border */}
        <View
          style={[
            StyleSheet.absoluteFill,
            {
              borderRightWidth: 1,
              borderRightColor: isDark
                ? "rgba(255,255,255,0.07)"
                : "rgba(0,0,0,0.07)",
            },
          ]}
          pointerEvents="none"
        />

        {/* Content */}
        <View style={styles.panelContent}>
          {/* Header: identity */}
          <View style={[styles.identity, { borderBottomColor: colors.border }]}>
            <View style={{ flex: 1 }}>
              <BrandWordmark size={22} />
              <Text
                style={[styles.identityName, { color: colors.foreground }]}
                numberOfLines={1}
              >
                {user?.display_name || user?.email || "Member"}
              </Text>
              {user?.role ? (
                <Text
                  style={[styles.identityRole, { color: colors.mutedForeground }]}
                  numberOfLines={1}
                >
                  {user.role}
                </Text>
              ) : null}
            </View>
            <Pressable
              onPress={closeDrawer}
              hitSlop={8}
              style={[
                styles.closeBtn,
                { backgroundColor: colors.muted, borderRadius: 999 },
              ]}
            >
              <Feather name="x" size={18} color={colors.mutedForeground} />
            </Pressable>
          </View>

          {/* Nav groups */}
          <ScrollView
            style={{ flex: 1 }}
            contentContainerStyle={styles.navScrollContent}
            showsVerticalScrollIndicator={false}
          >
            {NAV_GROUPS.map((group) => (
              <View key={group.title} style={styles.group}>
                <Text
                  style={[styles.groupTitle, { color: colors.mutedForeground }]}
                >
                  {group.title}
                </Text>
                {group.items.map((item) => (
                  <Pressable
                    key={item.href}
                    onPress={() => navigate(item.href)}
                    style={({ pressed }) => [
                      styles.navItem,
                      {
                        backgroundColor: pressed
                          ? colors.primary + "18"
                          : "transparent",
                        borderRadius: colors.radius - 2,
                      },
                    ]}
                  >
                    <View
                      style={[
                        styles.navIconWrap,
                        { backgroundColor: colors.muted, borderRadius: 8 },
                      ]}
                    >
                      <Feather
                        name={item.icon}
                        size={15}
                        color={colors.primary}
                      />
                    </View>
                    <Text
                      style={[styles.navLabel, { color: colors.foreground }]}
                      numberOfLines={1}
                    >
                      {item.label}
                    </Text>
                    <Feather
                      name="chevron-right"
                      size={14}
                      color={colors.mutedForeground}
                    />
                  </Pressable>
                ))}
              </View>
            ))}
            <View style={{ height: 20 }} />
          </ScrollView>
        </View>
      </Animated.View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    zIndex: 100,
  },
  panel: {
    position: "absolute",
    top: 0,
    bottom: 0,
    left: 0,
    overflow: "hidden",
    borderRightWidth: 1,
  },
  panelContent: {
    flex: 1,
    zIndex: 1,
  },
  identity: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingHorizontal: 20,
    paddingTop: 16,
    paddingBottom: 16,
    borderBottomWidth: 1,
  },
  identityName: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    marginTop: 8,
  },
  identityRole: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    marginTop: 2,
    textTransform: "capitalize",
  },
  closeBtn: {
    width: 36,
    height: 36,
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  navScrollContent: {
    paddingTop: 12,
    paddingHorizontal: 12,
  },
  group: {
    marginBottom: 20,
  },
  groupTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.8,
    textTransform: "uppercase",
    marginBottom: 6,
    paddingHorizontal: 8,
  },
  navItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingHorizontal: 8,
    paddingVertical: 10,
  },
  navIconWrap: {
    width: 30,
    height: 30,
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  navLabel: {
    flex: 1,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
  },
});
