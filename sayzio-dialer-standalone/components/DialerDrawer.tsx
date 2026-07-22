import Feather from "@expo/vector-icons/Feather";
import { useRouter } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  Alert,
  Animated,
  Easing,
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
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";
import { getBaseUrl } from "@/lib/api";
import {
  getKeypadMode,
  notifyDialerPrefsChanged,
  setKeypadMode,
  type KeypadMode,
} from "@/lib/dialerPrefs";
import {
  getCallAccounts,
  getCallMode,
  getSimPref,
  setCallMode,
  setSimPref,
  type CallMode,
  type SimPref,
} from "@/lib/placeCall";
import { type CallAccount } from "@/modules/zio-telephony";

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
  const { signOut } = useAuth();

  // RN-web Alert.alert is a no-op, so web needs a window.confirm branch.
  const confirmSignOut = () => {
    if (Platform.OS === "web") {
      if (
        typeof window !== "undefined" &&
        window.confirm("Sign out of your Sayzio account on this device?")
      ) {
        onClose();
        void signOut();
      }
      return;
    }
    Alert.alert("Sign out?", "Sign out of your Sayzio account on this device?", [
      { text: "Cancel", style: "cancel" },
      {
        text: "Sign out",
        style: "destructive",
        onPress: () => {
          onClose();
          void signOut();
        },
      },
    ]);
  };

  const baseUrl = getBaseUrl();

  // ── 3D push-in animation (mirrors the main Sayzio app's drawer) ─────────
  // The Modal itself never animates (animationType="none"); we drive a single
  // progress value: panel slides in from the left while un-rotating from a
  // slight Y-axis tilt behind a fading backdrop. `rendered` keeps the Modal
  // mounted until the close animation finishes.
  const PANEL_W = 300;
  const [rendered, setRendered] = useState(open);
  const progress = useRef(new Animated.Value(0)).current;
  useEffect(() => {
    if (open) {
      setRendered(true);
      Animated.timing(progress, {
        toValue: 1,
        duration: 320,
        easing: Easing.bezier(0.22, 1, 0.36, 1),
        useNativeDriver: true,
      }).start();
    } else {
      Animated.timing(progress, {
        toValue: 0,
        duration: 220,
        easing: Easing.in(Easing.cubic),
        useNativeDriver: true,
      }).start(({ finished }) => {
        if (finished) setRendered(false);
      });
    }
  }, [open, progress]);

  const panelTransform = [
    { perspective: 1400 },
    {
      translateX: progress.interpolate({
        inputRange: [0, 1],
        outputRange: [-PANEL_W, 0],
      }),
    },
    {
      rotateY: progress.interpolate({
        inputRange: [0, 1],
        outputRange: ["-8deg", "0deg"],
      }),
    },
  ];

  // ── Dialer settings (keypad mode / default SIM / calling mode) ──────────
  // Loaded fresh each time the drawer opens; saves notify the keypad screen
  // via the dialer-prefs listener bus so it live-reloads.
  const [keypadMode, setKeypadModeState] = useState<KeypadMode>("t9");
  const [simAccounts, setSimAccounts] = useState<CallAccount[]>([]);
  const [simPref, setSimPrefState] = useState<SimPref>("ask");
  const [callMode, setCallModeState] = useState<CallMode>(
    Platform.OS === "android" ? "direct" : "system",
  );

  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    void (async () => {
      try {
        const [km, sp, cm] = await Promise.all([
          getKeypadMode(),
          getSimPref(),
          getCallMode(),
        ]);
        if (cancelled) return;
        setKeypadModeState(km);
        setSimPrefState(sp);
        setCallModeState(cm);
        setSimAccounts(getCallAccounts());
      } catch {
        /* keep defaults */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [open]);

  const chooseKeypadMode = (m: KeypadMode) => {
    setKeypadModeState(m);
    void setKeypadMode(m).then(() => notifyDialerPrefsChanged());
  };
  const chooseSimPref = (p: SimPref) => {
    setSimPrefState(p);
    void setSimPref(p).then(() => notifyDialerPrefsChanged());
  };
  const chooseCallMode = (m: CallMode) => {
    setCallModeState(m);
    void setCallMode(m).then(() => notifyDialerPrefsChanged());
  };

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
    { key: "notes", label: "Notes & reminders", icon: "edit-3", routeName: "notes" },
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
    <Modal
      visible={rendered}
      transparent
      animationType="none"
      onRequestClose={onClose}
    >
      <View style={styles.backdropWrap}>
        {/* Tap outside the panel to dismiss. */}
        <Animated.View style={[styles.backdrop, { opacity: progress }]}>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Close menu"
            onPress={onClose}
            style={StyleSheet.absoluteFill}
          />
        </Animated.View>
        <Animated.View
          style={[
            styles.panel,
            { transform: panelTransform },
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
              DIALER SETTINGS
            </Text>

            {/* Keypad input mode */}
            <Text style={[styles.settingLabel, { color: colors.mutedForeground }]}>
              Keypad input
            </Text>
            <View style={styles.segmentRow}>
              {(
                [
                  { v: "t9", label: "T9", icon: "grid" },
                  { v: "abc", label: "Keyboard", icon: "type" },
                ] as const
              ).map((o) => {
                const active = keypadMode === o.v;
                return (
                  <Pressable
                    key={o.v}
                    onPress={() => chooseKeypadMode(o.v)}
                    style={[
                      styles.segmentBtn,
                      {
                        backgroundColor: active ? colors.primary : colors.card,
                        borderColor: active ? colors.primary : colors.border,
                      },
                    ]}
                    {...WEB_FOCUS_RING_PROPS}
                  >
                    <Feather name={o.icon} size={13} color={active ? "#fff" : colors.mutedForeground} />
                    <Text
                      style={{
                        color: active ? "#fff" : colors.mutedForeground,
                        fontSize: 12,
                        fontFamily: "SpaceGrotesk_600SemiBold",
                        marginLeft: 6,
                      }}
                    >
                      {o.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>

            {/* Default SIM — only when two call-capable SIMs exist. */}
            {simAccounts.length >= 2 && (
              <>
                <Text style={[styles.settingLabel, { color: colors.mutedForeground }]}>
                  Default SIM (recents, contacts, search)
                </Text>
                <View style={styles.segmentRow}>
                  {[
                    ...simAccounts.slice(0, 2).map((a, i) => ({
                      v: a.index as SimPref,
                      label: a.label?.trim() || `SIM ${i + 1}`,
                    })),
                    { v: "ask" as SimPref, label: "Auto" },
                  ].map((o) => {
                    const active = simPref === o.v;
                    return (
                      <Pressable
                        key={String(o.v)}
                        onPress={() => chooseSimPref(o.v)}
                        style={[
                          styles.segmentBtn,
                          {
                            backgroundColor: active ? colors.primary : colors.card,
                            borderColor: active ? colors.primary : colors.border,
                          },
                        ]}
                        {...WEB_FOCUS_RING_PROPS}
                      >
                        <Text
                          numberOfLines={1}
                          style={{
                            color: active ? "#fff" : colors.mutedForeground,
                            fontSize: 12,
                            fontFamily: "SpaceGrotesk_600SemiBold",
                          }}
                        >
                          {o.label}
                        </Text>
                      </Pressable>
                    );
                  })}
                </View>
              </>
            )}

            {/* Calling mode — Android only; iOS always opens the Phone app. */}
            <Text style={[styles.settingLabel, { color: colors.mutedForeground }]}>
              Calling
            </Text>
            {Platform.OS === "android" ? (
              <View style={styles.segmentRow}>
                {(
                  [
                    { v: "direct", label: "Direct call", icon: "phone-call" },
                    { v: "system", label: "Phone app", icon: "phone-forwarded" },
                  ] as const
                ).map((o) => {
                  const active = callMode === o.v;
                  return (
                    <Pressable
                      key={o.v}
                      onPress={() => chooseCallMode(o.v)}
                      style={[
                        styles.segmentBtn,
                        {
                          backgroundColor: active ? colors.primary : colors.card,
                          borderColor: active ? colors.primary : colors.border,
                        },
                      ]}
                      {...WEB_FOCUS_RING_PROPS}
                    >
                      <Feather name={o.icon} size={13} color={active ? "#fff" : colors.mutedForeground} />
                      <Text
                        style={{
                          color: active ? "#fff" : colors.mutedForeground,
                          fontSize: 12,
                          fontFamily: "SpaceGrotesk_600SemiBold",
                          marginLeft: 6,
                        }}
                      >
                        {o.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            ) : (
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 12,
                  paddingHorizontal: 12,
                  marginBottom: 4,
                }}
              >
                iOS always opens the Phone app with the number pre-filled.
              </Text>
            )}

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

            <View style={[styles.divider, { backgroundColor: colors.border }]} />
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Sign out"
              onPress={confirmSignOut}
              style={({ pressed }) => [
                styles.navRow,
                { backgroundColor: pressed ? colors.muted : "transparent" },
              ]}
              {...WEB_FOCUS_RING_PROPS}
            >
              <Feather name="log-out" size={18} color={colors.destructive} />
              <Text
                style={{
                  color: colors.destructive,
                  fontSize: 15,
                  fontFamily: "SpaceGrotesk_500Medium",
                }}
              >
                Sign out
              </Text>
            </Pressable>
          </ScrollView>
        </Animated.View>
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
  settingLabel: {
    fontSize: 11,
    fontFamily: "SpaceGrotesk_500Medium",
    paddingHorizontal: 12,
    marginTop: 8,
    marginBottom: 6,
  },
  segmentRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
    paddingHorizontal: 12,
    marginBottom: 4,
  },
  segmentBtn: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderRadius: 10,
    paddingVertical: 8,
    paddingHorizontal: 12,
  },
  sectionLabel: {
    fontSize: 11,
    letterSpacing: 1.2,
    fontFamily: "SpaceGrotesk_600SemiBold",
    marginBottom: 6,
    paddingHorizontal: 12,
  },
});
