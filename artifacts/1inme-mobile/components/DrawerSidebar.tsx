import { Feather } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import { usePathname, useRouter } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  AccessibilityInfo,
  Alert,
  Image,
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
import {
  DRAWER_FOCUS_RING,
  focusRingMarkerProps,
  useWebFocusRing,
} from "@/lib/webFocusRing";
import type { ThemePref } from "@/lib/secure";
import type { Workspace } from "@/lib/api/workspaces";

const DRAWER_WIDTH_FRAC = 0.78;
const MAX_DRAWER_W = 320;

type NavItem = {
  label: string;
  icon: keyof typeof Feather.glyphMap;
  href: string;
  soon?: boolean;
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
      { label: "Notifications", icon: "bell", href: "/notifications" },
      { label: "Stats", icon: "bar-chart-2", href: "/stats" },
      { label: "Visitors", icon: "users", href: "/visitors" },
    ],
  },
  {
    title: "Links & Pages",
    items: [
      { label: "QR Codes", icon: "grid", href: "/qr" },
      { label: "QR Studio", icon: "maximize", href: "/qr-studio" },
      { label: "Forms", icon: "file-text", href: "/forms" },
      { label: "Backlinks", icon: "crosshair", href: "/backlinks" },
      { label: "Intros", icon: "layout", href: "/splash" },
      { label: "AI Resume / Portfolio", icon: "file", href: "/resume" },
      { label: "Projects", icon: "folder", href: "/projects" },
      { label: "Cloud Files", icon: "cloud", href: "/cloud-files" },
    ],
  },
  {
    title: "Audience & Community",
    items: [
      { label: "Subscribers", icon: "user-plus", href: "/subscribers" },
      { label: "Followers", icon: "user-check", href: "/followers" },
      { label: "Feed", icon: "activity", href: "/feed", soon: true },
      { label: "My Posts", icon: "edit-2", href: "/posts" },
      { label: "Leads", icon: "target", href: "/leads" },
      { label: "Social Accounts", icon: "share-2", href: "/social" },
      { label: "Leaderboard", icon: "award", href: "/leaderboard" },
    ],
  },
  {
    title: "Monetization",
    items: [
      { label: "Earnings & Payouts", icon: "dollar-sign", href: "/payouts", soon: true },
      { label: "Monetization", icon: "trending-up", href: "/monetization", soon: true },
      { label: "Orders", icon: "shopping-bag", href: "/orders" },
    ],
  },
  {
    title: "Growth & Marketing",
    items: [
      { label: "Buzz", icon: "bell", href: "/social-proofs", soon: true },
      { label: "Pixel", icon: "crosshair", href: "/pixels", soon: true },
      { label: "Referrals", icon: "gift", href: "/insider" },
    ],
  },
  {
    title: "AI",
    items: [
      { label: "AI Note Summarizer", icon: "cpu", href: "/ai-mind", soon: true },
      { label: "AI Knowledge Bases", icon: "database", href: "/ai-minds", soon: true },
      { label: "AI Persona Generator", icon: "user", href: "/ai-persona" },
      { label: "AI Agents", icon: "zap", href: "/ai-agents", soon: true },
      { label: "AI Chat", icon: "message-square", href: "/ai-coach" },
      { label: "Chat Widgets", icon: "message-circle", href: "/ai-companions", soon: true },
      { label: "AI Brand Kit", icon: "feather", href: "/brand-kits" },
      { label: "AI Growth Coach", icon: "trending-up", href: "/ai-growth-coach", soon: true },
      { label: "AI Coach", icon: "compass", href: "/ask-coach" },
      { label: "AI Marketing Strategist", icon: "bar-chart", href: "/marketing-strategist" },
      { label: "AI Staff", icon: "users", href: "/ai-staff", soon: true },
    ],
  },
  {
    title: "Workspace & Tools",
    items: [
      { label: "Tasks", icon: "check-square", href: "/tasks", soon: true },
      { label: "Delivery Projects", icon: "clipboard", href: "/delivery-projects" },
      { label: "Vault", icon: "lock", href: "/vault" },
      { label: "Vault Audit", icon: "shield", href: "/vault-audit" },
      { label: "Events", icon: "calendar", href: "/events" },
      { label: "My Calendar", icon: "calendar", href: "/calendars" },
      { label: "Calendar Sync", icon: "refresh-cw", href: "/calendar" },
      { label: "AI Competitor Teardown", icon: "search", href: "/teardown" },
      { label: "AI Performer Specialist", icon: "target", href: "/marketing-strategist" },
    ],
  },
  {
    title: "Billing & Accounting",
    items: [
      { label: "Invoices", icon: "file-text", href: "/invoices" },
      { label: "Client Portals", icon: "briefcase", href: "/client-portals" },
      { label: "Recurring", icon: "repeat", href: "/billing/recurring" },
      { label: "Expenses", icon: "dollar-sign", href: "/billing/expenses" },
      { label: "Catalog", icon: "book", href: "/billing/catalog" },
      { label: "Tax Rules", icon: "percent", href: "/billing/tax-rules" },
      { label: "Ledger", icon: "activity", href: "/billing/ledger" },
      { label: "Wallet", icon: "credit-card", href: "/wallet" },
      { label: "API Usage", icon: "zap", href: "/api-usage" },
    ],
  },
  {
    title: "Account",
    items: [
      { label: "Settings", icon: "sliders", href: "/profile-edit" },
      { label: "Security", icon: "shield", href: "/security" },
      { label: "Devices & Sessions", icon: "monitor", href: "/account-sessions" },
      { label: "Linked IDs", icon: "at-sign", href: "/identifiers" },
      { label: "Connected Apps", icon: "zap", href: "/connected-apps" },
      { label: "Integrations", icon: "link", href: "/integrations" },
      { label: "Custom Domains", icon: "globe", href: "/domains" },
      { label: "Contact Privacy", icon: "eye-off", href: "/contact-privacy" },
      { label: "Verification", icon: "award", href: "/verification" },
      { label: "Workspaces", icon: "briefcase", href: "/workspaces" },
      { label: "Team & Staff", icon: "users", href: "/team" },
    ],
  },
];

// ── Web keyboard focus indicator (:focus-visible ring) ────────────────────
// Every drawer control (nav items, close, workspace switcher, theme buttons,
// sign out) renders as a React Native Web <div>/Pressable, which has NO default
// focus outline — so a sighted keyboard user tabbing through the drawer can't
// see where focus currently is. The shared helper (lib/webFocusRing) tags each
// focusable control with the `data-drawer-focusable` marker and injects the
// on-brand `:focus-visible` stylesheet (keyboard-only, no stray ring on tap),
// mirroring the FloatingTabBar treatment for consistent keyboard a11y across
// the whole mobile-web navigation. `focusRingMarkerProps` is null on native so
// nothing is added there. The stylesheet + colour tracking are installed once
// via `useWebFocusRing` inside the DrawerSidebar component below.
const WEB_FOCUS_RING_PROPS = focusRingMarkerProps(DRAWER_FOCUS_RING);

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
        {...WEB_FOCUS_RING_PROPS}
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
                {...WEB_FOCUS_RING_PROPS}
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
              {...WEB_FOCUS_RING_PROPS}
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
  const { user, signOut } = useAuth();
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

  // ── Web keyboard focus ring ──────────────────────────────────────────
  // Install the shared :focus-visible treatment once and keep its colour
  // tracking the theme's primary. The marker props spread onto each focusable
  // control are the module-level WEB_FOCUS_RING_PROPS above. Native is
  // untouched (the helper is a no-op there).
  useWebFocusRing(DRAWER_FOCUS_RING, colors.primary);

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

  const handleSignOut = () => {
    const confirmed = () => {
      closeDrawer();
      signOut();
    };
    // react-native-web's Alert.alert is a NO-OP, so on web the native Alert
    // would silently swallow the tap. Fall back to window.confirm there
    // (same pattern as lib/upgradePrompt.ts). Dismissing either dialog
    // (Cancel, or the Android hardware back button closing the Alert) must
    // never sign the user out — only the explicit confirm does.
    if (
      Platform.OS === "web" &&
      typeof window !== "undefined" &&
      typeof window.confirm === "function"
    ) {
      if (window.confirm("Are you sure you want to sign out?")) confirmed();
      return;
    }
    Alert.alert(
      "Sign out",
      "Are you sure you want to sign out?",
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Sign out",
          style: "destructive",
          onPress: confirmed,
        },
      ],
    );
  };

  const isActive = (href: string) => {
    if (href === "/") return pathname === "/";
    return pathname.startsWith(href);
  };

  const avatarInitial = (user?.display_name || user?.email || "M")
    .charAt(0)
    .toUpperCase();

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

          {/* ── Header: brand wordmark + close ───────────────────────── */}
          <View
            style={[
              styles.headerTopRow,
              {
                borderBottomColor: isDark
                  ? "rgba(255,255,255,0.06)"
                  : "rgba(0,0,0,0.06)",
              },
            ]}
          >
            <BrandWordmark size={20} />
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
              {...WEB_FOCUS_RING_PROPS}
            >
              <Feather name="x" size={18} color={colors.mutedForeground} />
            </Pressable>
          </View>

          {/* ── User identity: avatar + name + email ─────────────────── */}
          <View
            style={[
              styles.identityRow,
              {
                borderBottomColor: isDark
                  ? "rgba(255,255,255,0.08)"
                  : "rgba(0,0,0,0.08)",
              },
            ]}
          >
            {/* Avatar */}
            {user?.avatar_url ? (
              <Image
                source={{ uri: user.avatar_url }}
                style={[
                  styles.avatar,
                  { borderColor: colors.primary + "55" },
                ]}
              />
            ) : (
              <View
                style={[
                  styles.avatar,
                  styles.avatarFallback,
                  {
                    backgroundColor: colors.primary + "30",
                    borderColor: colors.primary + "55",
                  },
                ]}
              >
                <Text
                  style={[
                    styles.avatarInitial,
                    { color: colors.primary },
                  ]}
                >
                  {avatarInitial}
                </Text>
              </View>
            )}

            {/* Name + email */}
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text
                style={[styles.identityName, { color: colors.foreground }]}
                numberOfLines={1}
              >
                {user?.display_name || "Member"}
              </Text>
              <Text
                style={[styles.identityEmail, { color: colors.mutedForeground }]}
                numberOfLines={1}
              >
                {user?.email ?? ""}
              </Text>
            </View>
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
                  const active = !item.soon && isActive(item.href);
                  return (
                    <Pressable
                      key={item.label}
                      onPress={() => !item.soon && navigate(item.href)}
                      style={({ pressed }) => [
                        styles.navItem,
                        {
                          backgroundColor: active
                            ? colors.primary + "20"
                            : pressed && !item.soon
                              ? colors.primary + "12"
                              : "transparent",
                          borderRadius: 10,
                          opacity: item.soon ? 0.6 : 1,
                        },
                      ]}
                      accessibilityRole="menuitem"
                      accessibilityState={{ selected: active }}
                      {...WEB_FOCUS_RING_PROPS}
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
                      {item.soon ? (
                        <View
                          style={[
                            styles.soonBadge,
                            {
                              backgroundColor: isDark
                                ? "rgba(255,255,255,0.10)"
                                : "rgba(0,0,0,0.07)",
                            },
                          ]}
                        >
                          <Text
                            style={[
                              styles.soonBadgeText,
                              { color: colors.mutedForeground },
                            ]}
                          >
                            Soon
                          </Text>
                        </View>
                      ) : active ? (
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

          {/* ── Sign out ─────────────────────────────────────────────── */}
          <View
            style={[
              styles.signOutRow,
              {
                borderTopColor: isDark
                  ? "rgba(255,255,255,0.08)"
                  : "rgba(0,0,0,0.08)",
              },
            ]}
          >
            <Pressable
              onPress={handleSignOut}
              style={({ pressed }) => [
                styles.signOutBtn,
                {
                  backgroundColor: pressed
                    ? "rgba(239,68,68,0.12)"
                    : "transparent",
                  borderRadius: 10,
                },
              ]}
              accessibilityRole="button"
              accessibilityLabel="Sign out"
              {...WEB_FOCUS_RING_PROPS}
            >
              <View
                style={[
                  styles.signOutIconWrap,
                  { backgroundColor: "rgba(239,68,68,0.12)", borderRadius: 8 },
                ]}
              >
                <Feather name="log-out" size={14} color="#ef4444" />
              </View>
              <Text style={styles.signOutLabel}>Sign out</Text>
            </Pressable>
          </View>
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
  headerTopRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 20,
    paddingTop: 14,
    paddingBottom: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  closeBtn: {
    width: 34,
    height: 34,
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  identityRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingHorizontal: 20,
    paddingTop: 14,
    paddingBottom: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    borderWidth: 2,
    flexShrink: 0,
  },
  avatarFallback: {
    alignItems: "center",
    justifyContent: "center",
  },
  avatarInitial: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 18,
  },
  identityName: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
    marginBottom: 2,
  },
  identityEmail: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
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
  soonBadge: {
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 6,
    flexShrink: 0,
  },
  soonBadgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 9,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
  signOutRow: {
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  signOutBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingHorizontal: 8,
    paddingVertical: 9,
  },
  signOutIconWrap: {
    width: 28,
    height: 28,
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  signOutLabel: {
    flex: 1,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13.5,
    color: "#ef4444",
  },
});
