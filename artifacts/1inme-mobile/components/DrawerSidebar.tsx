import { Feather } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import { usePathname, useRouter } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  AccessibilityInfo,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from "react-native";
import Animated, {
  interpolate,
  useAnimatedStyle,
  useSharedValue,
  withSpring,
  withTiming,
} from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useDrawer } from "@/contexts/DrawerContext";
import { useAuth } from "@/contexts/AuthContext";
import { useWorkspace } from "@/contexts/WorkspaceContext";
import { useThemeControls } from "@/contexts/ThemeContext";
import { useColors, useResolvedScheme } from "@/hooks/useColors";
import { BrandWordmark } from "@/components/Brand";
import type { ThemePref } from "@/lib/secure";
import type { Workspace } from "@/lib/api/workspaces";

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
      { label: "Conversational", icon: "message-circle", href: "/links/conversational" },
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
      { label: "Team & Staff", icon: "users", href: "/team" },
    ],
  },
  {
    title: "Billing & CRM",
    items: [
      { label: "Recurring", icon: "repeat", href: "/billing/recurring" },
      { label: "Companies", icon: "briefcase", href: "/billing/companies" },
      { label: "Expenses", icon: "dollar-sign", href: "/billing/expenses" },
      { label: "Catalog", icon: "book", href: "/billing/catalog" },
      { label: "Tax Rules", icon: "percent", href: "/billing/tax-rules" },
      { label: "Ledger", icon: "activity", href: "/billing/ledger" },
      { label: "Wallet", icon: "credit-card", href: "/wallet" },
      { label: "API Usage", icon: "zap", href: "/api-usage" },
    ],
  },
  {
    title: "Tools",
    items: [
      { label: "Cloud Files", icon: "cloud", href: "/cloud-files" },
      { label: "AI Brand Kit", icon: "feather", href: "/brand-kits" },
      { label: "Performer Specialist", icon: "target", href: "/marketing-strategist" },
      { label: "Competitor Teardown", icon: "crosshair", href: "/teardown" },
      { label: "Vault", icon: "lock", href: "/vault" },
      { label: "Vault Audit", icon: "shield", href: "/vault-audit" },
      { label: "Insider & Referrals", icon: "award", href: "/insider" },
    ],
  },
  {
    title: "Settings",
    items: [
      { label: "Edit Profile", icon: "edit-3", href: "/profile-edit" },
      { label: "Security", icon: "shield", href: "/security" },
      { label: "Devices & Sessions", icon: "monitor", href: "/account-sessions" },
      { label: "Linked IDs", icon: "at-sign", href: "/identifiers" },
      { label: "Connected Apps", icon: "zap", href: "/connected-apps" },
      { label: "Integrations", icon: "link", href: "/integrations" },
      { label: "Custom Domains", icon: "globe", href: "/domains" },
      { label: "Notifications", icon: "bell", href: "/notifications" },
      { label: "Contact Privacy", icon: "eye-off", href: "/contact-privacy" },
      { label: "Verification", icon: "award", href: "/verification" },
      { label: "Workspaces", icon: "briefcase", href: "/workspaces" },
    ],
  },
];

const THEME_OPTIONS: {
  value: ThemePref;
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { value: "system", label: "Auto", icon: "smartphone" },
  { value: "light", label: "Light", icon: "sun" },
  { value: "dark", label: "Dark", icon: "moon" },
];

function WorkspaceSwitcherBlock({
  colors,
  isDark,
}: {
  colors: ReturnType<typeof useColors>;
  isDark: boolean;
}) {
  const { workspaces, activeWorkspace, switchWorkspace } = useWorkspace();
  const [open, setOpen] = useState(false);
  const [switching, setSwitching] = useState<number | null>(null);

  const wsColor = activeWorkspace?.color ?? colors.primary;

  if (!activeWorkspace) return null;

  const onSwitch = async (ws: Workspace) => {
    if (ws.id === activeWorkspace.id) {
      setOpen(false);
      return;
    }
    setSwitching(ws.id);
    try {
      await switchWorkspace(ws);
    } finally {
      setSwitching(null);
      setOpen(false);
    }
  };

  return (
    <View
      style={[
        styles.wsSwitcher,
        {
          borderBottomColor: isDark
            ? "rgba(255,255,255,0.08)"
            : "rgba(0,0,0,0.08)",
        },
      ]}
    >
      <Pressable
        onPress={() => setOpen((v) => !v)}
        style={({ pressed }) => [
          styles.wsActive,
          {
            backgroundColor: pressed
              ? colors.primary + "18"
              : isDark
                ? "rgba(255,255,255,0.05)"
                : "rgba(0,0,0,0.04)",
            borderRadius: 12,
          },
        ]}
        accessibilityLabel={`Active workspace: ${activeWorkspace.name}. Tap to switch.`}
      >
        <View
          style={[
            styles.wsIcon,
            { backgroundColor: wsColor + "cc", borderRadius: 9 },
          ]}
        >
          <Feather
            name={activeWorkspace.is_personal ? "user" : "users"}
            size={13}
            color="#fff"
          />
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text
            style={[styles.wsName, { color: colors.foreground }]}
            numberOfLines={1}
          >
            {activeWorkspace.name}
          </Text>
          <Text
            style={[styles.wsType, { color: colors.mutedForeground }]}
            numberOfLines={1}
          >
            {activeWorkspace.is_personal ? "Personal" : "Team workspace"}
          </Text>
        </View>
        <Feather
          name={open ? "chevron-up" : "chevron-down"}
          size={14}
          color={colors.mutedForeground}
        />
      </Pressable>

      {open && (
        <View
          style={[
            styles.wsDropdown,
            {
              backgroundColor: isDark
                ? "rgba(20,20,28,0.98)"
                : "rgba(250,251,255,0.98)",
              borderColor: isDark
                ? "rgba(255,255,255,0.1)"
                : "rgba(0,0,0,0.1)",
              shadowColor: "#000",
              shadowOpacity: isDark ? 0.5 : 0.15,
              shadowOffset: { width: 0, height: 6 },
              shadowRadius: 16,
              elevation: 10,
            },
          ]}
        >
          {workspaces.map((ws) => {
            const isActive = ws.id === activeWorkspace.id;
            const wsC = ws.color ?? colors.primary;
            return (
              <Pressable
                key={ws.id}
                onPress={() => onSwitch(ws)}
                style={({ pressed }) => [
                  styles.wsRow,
                  {
                    backgroundColor:
                      isActive
                        ? colors.primary + "18"
                        : pressed
                          ? colors.muted
                          : "transparent",
                    borderRadius: 10,
                  },
                ]}
              >
                <View
                  style={[
                    styles.wsIconSm,
                    { backgroundColor: wsC + "cc", borderRadius: 7 },
                  ]}
                >
                  <Feather
                    name={ws.is_personal ? "user" : "users"}
                    size={10}
                    color="#fff"
                  />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text
                    style={[
                      styles.wsRowName,
                      {
                        color: isActive ? colors.primary : colors.foreground,
                        fontFamily: isActive
                          ? "SpaceGrotesk_600SemiBold"
                          : "SpaceGrotesk_500Medium",
                      },
                    ]}
                    numberOfLines={1}
                  >
                    {ws.name}
                  </Text>
                </View>
                {switching === ws.id ? (
                  <Feather
                    name="loader"
                    size={12}
                    color={colors.mutedForeground}
                  />
                ) : isActive ? (
                  <Feather name="check" size={12} color={colors.primary} />
                ) : null}
              </Pressable>
            );
          })}
        </View>
      )}
    </View>
  );
}

function ThemeToggleBlock({
  colors,
  isDark,
}: {
  colors: ReturnType<typeof useColors>;
  isDark: boolean;
}) {
  const { pref, setPref } = useThemeControls();

  return (
    <View
      style={[
        styles.themeBlock,
        {
          borderBottomColor: isDark
            ? "rgba(255,255,255,0.08)"
            : "rgba(0,0,0,0.08)",
        },
      ]}
    >
      <Text style={[styles.themeLabel, { color: colors.mutedForeground }]}>
        Theme
      </Text>
      <View
        style={[
          styles.themeRow,
          {
            backgroundColor: isDark
              ? "rgba(255,255,255,0.06)"
              : "rgba(0,0,0,0.05)",
            borderRadius: 10,
          },
        ]}
      >
        {THEME_OPTIONS.map((opt) => {
          const active = pref === opt.value;
          return (
            <Pressable
              key={opt.value}
              onPress={() => setPref(opt.value)}
              style={({ pressed }) => [
                styles.themeBtn,
                {
                  backgroundColor: active
                    ? colors.primary
                    : pressed
                      ? colors.primary + "22"
                      : "transparent",
                  borderRadius: 8,
                },
              ]}
              accessibilityLabel={`Theme: ${opt.label}`}
              accessibilityState={{ selected: active }}
            >
              <Feather
                name={opt.icon}
                size={13}
                color={active ? "#fff" : colors.mutedForeground}
              />
              <Text
                style={[
                  styles.themeBtnLabel,
                  { color: active ? "#fff" : colors.mutedForeground },
                ]}
              >
                {opt.label}
              </Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

export function DrawerSidebar() {
  const { isOpen, closeDrawer, contentProgress } = useDrawer();
  const { user } = useAuth();
  const colors = useColors();
  const scheme = useResolvedScheme();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const router = useRouter();
  const pathname = usePathname() ?? "";

  const drawerW = Math.min(width * DRAWER_WIDTH_FRAC, MAX_DRAWER_W);
  const isDark = scheme === "dark";

  const [reduceMotion, setReduceMotion] = useState(false);

  useEffect(() => {
    AccessibilityInfo.isReduceMotionEnabled().then(setReduceMotion).catch(() => {});
    const sub = AccessibilityInfo.addEventListener(
      "reduceMotionChanged",
      setReduceMotion,
    );
    return () => sub.remove();
  }, []);

  const backdropOpacity = useSharedValue(0);

  useEffect(() => {
    if (isOpen) {
      backdropOpacity.value = withTiming(1, { duration: 220 });
    } else {
      backdropOpacity.value = withTiming(0, { duration: 200 });
    }
  }, [isOpen, backdropOpacity]);

  const backdropStyle = useAnimatedStyle(() => ({
    opacity: backdropOpacity.value,
  }));

  const panelStyle = useAnimatedStyle(() => {
    const tx = interpolate(contentProgress.value, [0, 1], [-drawerW, 0]);
    if (reduceMotion) {
      return { transform: [{ translateX: tx }], opacity: contentProgress.value };
    }
    const rotateY = interpolate(contentProgress.value, [0, 1], [-8, 0]);
    return {
      transform: [
        { perspective: 1400 },
        { translateX: tx },
        { rotateY: `${rotateY}deg` },
      ],
    };
  });

  const navigate = (href: string) => {
    closeDrawer();
    setTimeout(() => router.push(href as never), 50);
  };

  const isActive = (href: string) => {
    if (href === "/") return pathname === "/";
    return pathname.startsWith(href);
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
                  ? "rgba(0,0,0,0.65)"
                  : "rgba(0,0,0,0.38)",
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
          },
          panelStyle,
        ]}
        pointerEvents={isOpen ? "box-none" : "none"}
      >
        {Platform.OS !== "web" ? (
          <BlurView
            intensity={85}
            tint={isDark ? "dark" : "light"}
            style={StyleSheet.absoluteFill}
          />
        ) : (
          <View
            style={[
              StyleSheet.absoluteFill,
              {
                backgroundColor: isDark
                  ? "rgba(10,10,18,0.94)"
                  : "rgba(246,247,252,0.96)",
              },
            ]}
          />
        )}

        {/* Right-edge border */}
        <View
          style={[
            StyleSheet.absoluteFill,
            {
              borderRightWidth: 1,
              borderRightColor: isDark
                ? "rgba(255,255,255,0.08)"
                : "rgba(0,0,0,0.08)",
            },
          ]}
          pointerEvents="none"
        />

        {/* Content */}
        <View style={styles.panelContent}>
          {/* Header */}
          <View
            style={[
              styles.identity,
              {
                borderBottomColor: isDark
                  ? "rgba(255,255,255,0.08)"
                  : "rgba(0,0,0,0.08)",
              },
            ]}
          >
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
                  style={[
                    styles.identityRole,
                    { color: colors.mutedForeground },
                  ]}
                  numberOfLines={1}
                >
                  {user.role}
                </Text>
              ) : null}
            </View>
            <Pressable
              onPress={closeDrawer}
              hitSlop={8}
              style={({ pressed }) => [
                styles.closeBtn,
                {
                  backgroundColor: pressed
                    ? colors.primary + "22"
                    : colors.muted,
                  borderRadius: 999,
                },
              ]}
              accessibilityLabel="Close menu"
            >
              <Feather name="x" size={18} color={colors.mutedForeground} />
            </Pressable>
          </View>

          {/* Workspace switcher */}
          <WorkspaceSwitcherBlock colors={colors} isDark={isDark} />

          {/* Theme switcher */}
          <ThemeToggleBlock colors={colors} isDark={isDark} />

          {/* Nav groups */}
          <ScrollView
            style={{ flex: 1 }}
            contentContainerStyle={styles.navScrollContent}
            showsVerticalScrollIndicator={false}
          >
            {NAV_GROUPS.map((group) => (
              <View key={group.title} style={styles.group}>
                <View
                  style={[
                    styles.groupHeader,
                    {
                      borderBottomColor: isDark
                        ? "rgba(255,255,255,0.05)"
                        : "rgba(0,0,0,0.05)",
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.groupTitle,
                      { color: colors.mutedForeground },
                    ]}
                  >
                    {group.title}
                  </Text>
                </View>
                {group.items.map((item) => {
                  const active = isActive(item.href);
                  return (
                    <Pressable
                      key={item.href}
                      onPress={() => navigate(item.href)}
                      style={({ pressed }) => [
                        styles.navItem,
                        {
                          backgroundColor: active
                            ? colors.primary + "20"
                            : pressed
                              ? colors.primary + "12"
                              : "transparent",
                          borderRadius: 10,
                        },
                      ]}
                      accessibilityRole="menuitem"
                      accessibilityState={{ selected: active }}
                    >
                      <View
                        style={[
                          styles.navIconWrap,
                          {
                            backgroundColor: active
                              ? colors.primary + "30"
                              : colors.muted,
                            borderRadius: 8,
                          },
                        ]}
                      >
                        <Feather
                          name={item.icon}
                          size={14}
                          color={active ? colors.primary : colors.mutedForeground}
                        />
                      </View>
                      <Text
                        style={[
                          styles.navLabel,
                          {
                            color: active
                              ? colors.primary
                              : colors.foreground,
                            fontFamily: active
                              ? "SpaceGrotesk_600SemiBold"
                              : "SpaceGrotesk_500Medium",
                          },
                        ]}
                        numberOfLines={1}
                      >
                        {item.label}
                      </Text>
                      {active ? (
                        <View
                          style={[
                            styles.activeIndicator,
                            { backgroundColor: colors.primary },
                          ]}
                        />
                      ) : (
                        <Feather
                          name="chevron-right"
                          size={13}
                          color={colors.mutedForeground}
                          style={{ opacity: 0.5 }}
                        />
                      )}
                    </Pressable>
                  );
                })}
              </View>
            ))}
            <View style={{ height: 24 }} />
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
    borderBottomWidth: StyleSheet.hairlineWidth,
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
  wsSwitcher: {
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  wsActive: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingHorizontal: 10,
    paddingVertical: 9,
  },
  wsIcon: {
    width: 30,
    height: 30,
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  wsName: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
  },
  wsType: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11,
    marginTop: 1,
  },
  wsDropdown: {
    marginTop: 6,
    borderRadius: 12,
    borderWidth: StyleSheet.hairlineWidth,
    overflow: "hidden",
    paddingVertical: 4,
    paddingHorizontal: 6,
  },
  wsRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 8,
    paddingVertical: 8,
    marginVertical: 1,
  },
  wsIconSm: {
    width: 24,
    height: 24,
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  wsRowName: {
    fontSize: 13,
  },
  themeBlock: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 6,
  },
  themeLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.8,
    textTransform: "uppercase",
  },
  themeRow: {
    flexDirection: "row",
    padding: 3,
    gap: 2,
  },
  themeBtn: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 5,
    paddingVertical: 7,
    paddingHorizontal: 4,
  },
  themeBtnLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
  },
  navScrollContent: {
    paddingTop: 8,
    paddingHorizontal: 10,
  },
  group: {
    marginBottom: 12,
  },
  groupHeader: {
    borderBottomWidth: StyleSheet.hairlineWidth,
    marginBottom: 4,
    paddingBottom: 4,
  },
  groupTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.8,
    textTransform: "uppercase",
    paddingHorizontal: 6,
  },
  navItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingHorizontal: 8,
    paddingVertical: 9,
    marginVertical: 1,
  },
  navIconWrap: {
    width: 28,
    height: 28,
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  navLabel: {
    flex: 1,
    fontSize: 13.5,
  },
  activeIndicator: {
    width: 4,
    height: 4,
    borderRadius: 2,
    flexShrink: 0,
  },
});
