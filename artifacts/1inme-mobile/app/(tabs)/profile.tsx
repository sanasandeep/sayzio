import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { LinearGradient } from "expo-linear-gradient";
import { useFocusEffect, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandWordmark } from "@/components/Brand";
import { Button } from "@/components/Button";
import { useAuth } from "@/contexts/AuthContext";
import { useThemeControls } from "@/contexts/ThemeContext";
import { useColors } from "@/hooks/useColors";
import { wallet as walletApi } from "@/lib/api";
import { getAdminContext } from "@/lib/api/admin";
import {
  formatIdleTimeout,
  formatLockWarningLead,
  getLastCustomIdleTimeoutMs,
  getVoiceWakeWordEnabled,
  IDLE_TIMEOUT_CUSTOM_MAX_MS,
  IDLE_TIMEOUT_CUSTOM_MIN_MS,
  IDLE_TIMEOUT_PRESETS_MS,
  LOCK_WARNING_LEAD_PRESETS_MS,
  setLastCustomIdleTimeoutMs,
  setVoiceWakeWordEnabled,
  type ThemePref,
} from "@/lib/secure";

const IDLE_TIMEOUT_OPTIONS: { value: number; label: string }[] =
  IDLE_TIMEOUT_PRESETS_MS.map((ms) => ({
    value: ms,
    label: ms === 0 ? "Off" : `${Math.round(ms / 60000)} min`,
  }));

const PRESET_VALUES = new Set<number>(IDLE_TIMEOUT_PRESETS_MS);

const LOCK_WARNING_OPTIONS: { value: number; label: string }[] =
  LOCK_WARNING_LEAD_PRESETS_MS.map((ms) => ({
    value: ms,
    label: formatLockWarningLead(ms),
  }));

type CustomUnit = "sec" | "min";

const INFO_PAGES: {
  href:
    | "/info/about"
    | "/info/nfc"
    | "/info/privacy"
    | "/info/terms"
    | "/info/help"
    | "/security-logins";
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { href: "/security-logins", label: "Recent logins", icon: "shield" },
  { href: "/info/about", label: "About Sayzio", icon: "info" },
  { href: "/info/nfc", label: "How NFC works", icon: "wifi" },
  { href: "/info/help", label: "Help & support", icon: "life-buoy" },
  { href: "/info/privacy", label: "Privacy", icon: "shield" },
  { href: "/info/terms", label: "Terms of service", icon: "file-text" },
];

const TOOL_PAGES: {
  href:
    | "/posts"
    | "/contacts"
    | "/dialer"
    | "/forms"
    | "/cloud-files"
    | "/followers"
    | "/social"
    | "/notifications"
    | "/subscribers"
    | "/projects"
    | "/qr"
    | "/qr-studio"
    | "/splash"
    | "/calendar"
    | "/resume"
    | "/backlinks"
    | "/visitors"
    | "/carbon"
    | "/team"
    | "/client-portals"
    | "/invoices"
    | "/insider"
    | "/leaderboard"
    | "/vault-audit"
    | "/orders"
    | "/links/conversational";
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { href: "/orders", label: "Orders", icon: "shopping-bag" },
  { href: "/posts", label: "Posts", icon: "message-square" },
  { href: "/contacts", label: "Contacts", icon: "users" },
  { href: "/dialer", label: "Dialer", icon: "phone" },
  { href: "/forms", label: "Forms", icon: "file-text" },
  { href: "/cloud-files", label: "Cloud files", icon: "cloud" },
  { href: "/followers", label: "Followers & Following", icon: "user-check" },
  { href: "/subscribers", label: "Subscribers", icon: "user-plus" },
  { href: "/social", label: "Social accounts", icon: "share-2" },
  { href: "/projects", label: "Projects", icon: "folder" },
  { href: "/qr", label: "QR codes", icon: "grid" },
  { href: "/qr-studio", label: "QR studio", icon: "grid" },
  { href: "/splash", label: "Splash pages", icon: "layout" },
  { href: "/calendar", label: "Calendars", icon: "calendar" },
  { href: "/notifications", label: "Notifications", icon: "bell" },
  { href: "/resume", label: "Resume builder", icon: "file-text" },
  { href: "/backlinks", label: "Backlinks", icon: "link" },
  { href: "/visitors", label: "Visitors", icon: "users" },
  { href: "/carbon", label: "Carbon footprint", icon: "cloud" },
  { href: "/team", label: "Team & staff", icon: "users" },
  { href: "/client-portals", label: "Client portals", icon: "briefcase" },
  { href: "/invoices", label: "Invoices", icon: "file-text" },
  { href: "/insider", label: "Insider & referrals", icon: "award" },
  { href: "/leaderboard", label: "Leaderboard", icon: "award" },
  { href: "/vault-audit", label: "Vault audit", icon: "shield" },
  { href: "/links/conversational", label: "Conversational links", icon: "message-circle" },
];

const SETTINGS_PAGES: {
  href:
    | "/profile-edit"
    | "/account-sessions"
    | "/workspaces"
    | "/domains"
    | "/integrations"
    | "/vault"
    | "/verification"
    | "/security";
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { href: "/profile-edit", label: "Edit profile", icon: "edit-3" },
  { href: "/account-sessions", label: "Devices & sessions", icon: "shield" },
  { href: "/workspaces", label: "Workspaces", icon: "briefcase" },
  { href: "/domains", label: "Custom domains", icon: "globe" },
  { href: "/integrations", label: "Integrations", icon: "link" },
  { href: "/vault", label: "Vault", icon: "lock" },
  { href: "/verification", label: "Verification", icon: "award" },
  { href: "/security", label: "Security & recovery", icon: "shield" },
];

const THEME_OPTIONS: {
  value: ThemePref;
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { value: "system", label: "System", icon: "smartphone" },
  { value: "light", label: "Light", icon: "sun" },
  { value: "dark", label: "Dark", icon: "moon" },
];

export default function Profile() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const {
    user,
    signOut,
    biometricCapability,
    biometricEnabled,
    enableBiometricUnlock,
    disableBiometricUnlock,
    refreshBiometricCapability,
    idleTimeoutMs,
    setIdleTimeoutMs,
    lockWarningLeadMs,
    setLockWarningLeadMs,
  } = useAuth();
  const { pref, setPref } = useThemeControls();
  const webTop = Platform.OS === "web" ? 67 : 0;

  // Whether this account is linked to an active back-office admin record.
  // Drives the Admin section + "Switch to admin dashboard" entry. The same
  // token already authorizes the admin endpoints, so switching is navigation,
  // not a re-login. Fails closed (no admin UI) on error.
  const adminCtx = useQuery({
    queryKey: ["admin-context"],
    queryFn: getAdminContext,
    retry: false,
    staleTime: 60_000,
  });
  const hasAdminAccess = !!adminCtx.data?.has_admin_access;
  const [coinBalance, setCoinBalance] = useState<number | null>(null);
  const [biometricBusy, setBiometricBusy] = useState(false);
  const [wakeWordEnabled, setWakeWordEnabledState] = useState(false);
  const [wakeWordBusy, setWakeWordBusy] = useState(false);
  const [customPickerOpen, setCustomPickerOpen] = useState(false);
  const [customAmount, setCustomAmount] = useState("30");
  const [customUnit, setCustomUnit] = useState<CustomUnit>("sec");
  const [lastCustomMs, setLastCustomMs] = useState<number | null>(null);

  const isCustomActive = idleTimeoutMs > 0 && !PRESET_VALUES.has(idleTimeoutMs);
  // Show the recall chip only when there's a remembered custom value that
  // isn't already the active selection — otherwise it would duplicate the
  // "Custom…" cell or do nothing when tapped. Also hide if the stored value
  // happens to coincide with a built-in preset, so we don't surface a chip
  // that's redundant with the segmented control itself.
  const showLastCustomChip =
    lastCustomMs != null &&
    lastCustomMs > 0 &&
    !PRESET_VALUES.has(lastCustomMs) &&
    lastCustomMs !== idleTimeoutMs;

  useEffect(() => {
    let cancelled = false;
    getLastCustomIdleTimeoutMs()
      .then((v) => {
        if (!cancelled) setLastCustomMs(v);
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, []);

  // Whenever the active timeout becomes a custom (non-preset) value, treat it
  // as the latest "last custom" so freshly-saved picks are remembered without
  // needing to re-open the modal.
  useEffect(() => {
    if (isCustomActive && idleTimeoutMs !== lastCustomMs) {
      setLastCustomMs(idleTimeoutMs);
      setLastCustomIdleTimeoutMs(idleTimeoutMs).catch(() => {});
    }
  }, [isCustomActive, idleTimeoutMs, lastCustomMs]);

  const openCustomPicker = useCallback(() => {
    // Seed the picker with the current value when it's already custom, fall
    // back to the most recent custom value if one is remembered, otherwise
    // default to 30 sec — a reasonable kiosk-friendly value.
    const seedMs = isCustomActive
      ? idleTimeoutMs
      : lastCustomMs && lastCustomMs > 0
        ? lastCustomMs
        : null;
    if (seedMs != null) {
      const totalSec = Math.round(seedMs / 1000);
      if (totalSec % 60 === 0) {
        setCustomUnit("min");
        setCustomAmount(String(totalSec / 60));
      } else {
        setCustomUnit("sec");
        setCustomAmount(String(totalSec));
      }
    } else {
      setCustomUnit("sec");
      setCustomAmount("30");
    }
    setCustomPickerOpen(true);
  }, [idleTimeoutMs, isCustomActive, lastCustomMs]);

  const recallLastCustom = useCallback(() => {
    if (lastCustomMs == null || lastCustomMs <= 0) return;
    setIdleTimeoutMs(lastCustomMs).catch(() => {});
  }, [lastCustomMs, setIdleTimeoutMs]);

  const parsedCustomMs = (() => {
    const n = Number.parseInt(customAmount, 10);
    if (!Number.isFinite(n) || n <= 0) return null;
    const ms = customUnit === "min" ? n * 60_000 : n * 1000;
    return ms;
  })();
  const clampedCustomMs =
    parsedCustomMs == null
      ? null
      : Math.min(
          IDLE_TIMEOUT_CUSTOM_MAX_MS,
          Math.max(IDLE_TIMEOUT_CUSTOM_MIN_MS, parsedCustomMs),
        );
  const customOutOfRange =
    parsedCustomMs != null && parsedCustomMs !== clampedCustomMs;

  const saveCustomPicker = useCallback(() => {
    if (clampedCustomMs == null) return;
    setIdleTimeoutMs(clampedCustomMs).catch(() => {});
    setCustomPickerOpen(false);
  }, [clampedCustomMs, setIdleTimeoutMs]);

  useFocusEffect(
    useCallback(() => {
      refreshBiometricCapability().catch(() => {});
    }, [refreshBiometricCapability]),
  );

  // Load the persisted wake-word toggle and refresh on focus so changes
  // made elsewhere (e.g. the Voice sheet) reflect here too.
  useFocusEffect(
    useCallback(() => {
      let cancelled = false;
      void getVoiceWakeWordEnabled().then((v) => {
        if (!cancelled) setWakeWordEnabledState(v);
      });
      return () => {
        cancelled = true;
      };
    }, []),
  );

  const onToggleWakeWord = useCallback(async () => {
    if (wakeWordBusy) return;
    setWakeWordBusy(true);
    try {
      const next = !wakeWordEnabled;
      await setVoiceWakeWordEnabled(next);
      setWakeWordEnabledState(next);
    } catch (e) {
      Alert.alert(
        "Couldn't save",
        e instanceof Error ? e.message : "Please try again.",
      );
    } finally {
      setWakeWordBusy(false);
    }
  }, [wakeWordBusy, wakeWordEnabled]);

  const onToggleBiometric = useCallback(async () => {
    if (biometricBusy) return;
    setBiometricBusy(true);
    try {
      if (biometricEnabled) {
        await disableBiometricUnlock();
      } else {
        const res = await enableBiometricUnlock();
        if (!res.ok && res.reason !== "cancel") {
          Alert.alert(
            "Couldn't enable",
            res.message ?? "Please try again.",
          );
        }
      }
    } finally {
      setBiometricBusy(false);
    }
  }, [biometricBusy, biometricEnabled, disableBiometricUnlock, enableBiometricUnlock]);

  const biometricLabel = biometricCapability?.label ?? "Biometric unlock";
  const biometricSupported = !!biometricCapability?.supported;
  const showBiometricRow = Platform.OS !== "web" && !!biometricCapability?.hasHardware;

  useFocusEffect(
    useCallback(() => {
      let cancelled = false;
      walletApi
        .balance()
        .then((b) => {
          if (cancelled) return;
          setCoinBalance(b.enabled ? b.balance : null);
        })
        .catch(() => {
          if (!cancelled) setCoinBalance(null);
        });
      return () => {
        cancelled = true;
      };
    }, []),
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <LinearGradient
        colors={[colors.primary + "1c", "transparent"]}
        start={{ x: 0, y: 0 }}
        end={{ x: 0, y: 0.5 }}
        style={StyleSheet.absoluteFill}
      />
      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top + 16 + webTop,
          paddingBottom: 32,
          paddingHorizontal: 24,
          gap: 24,
        }}
      >
        <View style={styles.headerRow}>
          <BrandWordmark size={26} />
        </View>

        <View
          style={[
            styles.card,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <Text style={[styles.hello, { color: colors.mutedForeground }]}>
            Signed in as
          </Text>
          <Text style={[styles.name, { color: colors.foreground }]}>
            {user?.display_name || user?.email || user?.mobile || "Sayzio member"}
          </Text>
          {user?.role ? (
            <View
              style={[styles.badge, { backgroundColor: colors.primary + "22" }]}
            >
              <Text style={[styles.badgeText, { color: colors.primary }]}>
                {user.role}
              </Text>
            </View>
          ) : null}
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Appearance
          </Text>
          <View
            style={[
              styles.segment,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {THEME_OPTIONS.map((opt) => {
              const active = pref === opt.value;
              return (
                <Pressable
                  key={opt.value}
                  onPress={() => setPref(opt.value)}
                  style={[
                    styles.segmentItem,
                    {
                      backgroundColor: active ? colors.background : "transparent",
                      borderRadius: colors.radius - 4,
                    },
                  ]}
                >
                  <Feather
                    name={opt.icon}
                    size={16}
                    color={active ? colors.primary : colors.mutedForeground}
                  />
                  <Text
                    style={[
                      styles.segmentText,
                      {
                        color: active ? colors.primary : colors.mutedForeground,
                      },
                    ]}
                  >
                    {opt.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Manage
          </Text>
          <View
            style={[
              styles.list,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {TOOL_PAGES.map((p, i) => (
              <Pressable
                key={p.href}
                onPress={() => router.push(p.href as never)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name={p.icon} size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  {p.label}
                </Text>
                <Feather
                  name="chevron-right"
                  size={18}
                  color={colors.mutedForeground}
                />
              </Pressable>
            ))}
          </View>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Settings
          </Text>
          <View
            style={[
              styles.list,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {SETTINGS_PAGES.map((p, i) => (
              <Pressable
                key={p.href}
                onPress={() => router.push(p.href as never)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name={p.icon} size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  {p.label}
                </Text>
                <Feather
                  name="chevron-right"
                  size={18}
                  color={colors.mutedForeground}
                />
              </Pressable>
            ))}
            {showBiometricRow ? (
              <Pressable
                onPress={biometricSupported ? onToggleBiometric : undefined}
                disabled={!biometricSupported || biometricBusy}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed && biometricSupported ? 0.7 : 1,
                  },
                ]}
                accessibilityRole="switch"
                accessibilityState={{
                  checked: biometricEnabled,
                  disabled: !biometricSupported,
                }}
              >
                <Feather name="shield" size={18} color={colors.primary} />
                <View style={{ flex: 1 }}>
                  <Text
                    style={[styles.listLabel, { color: colors.foreground }]}
                  >
                    Unlock with {biometricLabel}
                  </Text>
                  <Text
                    style={[
                      styles.helper,
                      { color: colors.mutedForeground },
                    ]}
                  >
                    {!biometricSupported
                      ? "Set up a fingerprint or face in your device settings to use this."
                      : biometricEnabled
                        ? "On — you'll be asked when you open the app."
                        : "Off — sign in with your code each time."}
                  </Text>
                </View>
                {biometricBusy ? (
                  <ActivityIndicator color={colors.primary} />
                ) : (
                  <Text
                    style={[
                      styles.statusPill,
                      {
                        color: !biometricSupported
                          ? colors.mutedForeground
                          : biometricEnabled
                            ? colors.primary
                            : colors.mutedForeground,
                        backgroundColor: !biometricSupported
                          ? colors.border + "55"
                          : biometricEnabled
                            ? colors.primary + "1a"
                            : colors.border + "55",
                      },
                    ]}
                  >
                    {!biometricSupported
                      ? "Unavailable"
                      : biometricEnabled
                        ? "On"
                        : "Off"}
                  </Text>
                )}
              </Pressable>
            ) : null}
            {Platform.OS !== "web" ? (
              <Pressable
                onPress={onToggleWakeWord}
                disabled={wakeWordBusy}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
                accessibilityRole="switch"
                accessibilityState={{ checked: wakeWordEnabled }}
              >
                <Feather name="mic" size={18} color={colors.primary} />
                <View style={{ flex: 1 }}>
                  <Text
                    style={[styles.listLabel, { color: colors.foreground }]}
                  >
                    Wake on &ldquo;Hey Sayzio&rdquo;
                  </Text>
                  <Text
                    style={[
                      styles.helper,
                      { color: colors.mutedForeground },
                    ]}
                  >
                    {wakeWordEnabled
                      ? "On — listens while the app is open. Wake checks don't use AI credits."
                      : "Off — tap the floating mic to start the Voice Assistant."}
                  </Text>
                </View>
                {wakeWordBusy ? (
                  <ActivityIndicator color={colors.primary} />
                ) : (
                  <Text
                    style={[
                      styles.statusPill,
                      {
                        color: wakeWordEnabled
                          ? colors.primary
                          : colors.mutedForeground,
                        backgroundColor: wakeWordEnabled
                          ? colors.primary + "1a"
                          : colors.border + "55",
                      },
                    ]}
                  >
                    {wakeWordEnabled ? "On" : "Off"}
                  </Text>
                )}
              </Pressable>
            ) : null}
            {showBiometricRow && biometricSupported && biometricEnabled ? (
              <>
              <View
                style={{
                  borderTopWidth: StyleSheet.hairlineWidth,
                  borderTopColor: colors.border,
                  paddingHorizontal: 16,
                  paddingVertical: 14,
                  gap: 10,
                }}
              >
                <View style={{ flexDirection: "row", alignItems: "center", gap: 14 }}>
                  <Feather name="clock" size={18} color={colors.primary} />
                  <View style={{ flex: 1 }}>
                    <Text
                      style={[styles.listLabel, { color: colors.foreground }]}
                    >
                      Auto-lock when idle
                    </Text>
                    <Text
                      style={[styles.helper, { color: colors.mutedForeground }]}
                    >
                      {idleTimeoutMs > 0
                        ? `Re-locks after ${formatIdleTimeout(idleTimeoutMs)} of inactivity.`
                        : "Stays unlocked until you leave the app."}
                    </Text>
                  </View>
                </View>
                <View
                  style={[
                    styles.segment,
                    {
                      backgroundColor: colors.background,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  {IDLE_TIMEOUT_OPTIONS.map((opt) => {
                    const active = idleTimeoutMs === opt.value;
                    return (
                      <Pressable
                        key={opt.value}
                        onPress={() => {
                          setIdleTimeoutMs(opt.value).catch(() => {});
                        }}
                        style={[
                          styles.segmentItem,
                          {
                            backgroundColor: active ? colors.card : "transparent",
                            borderRadius: colors.radius - 4,
                          },
                        ]}
                        accessibilityRole="button"
                        accessibilityState={{ selected: active }}
                      >
                        <Text
                          style={[
                            styles.segmentText,
                            {
                              color: active
                                ? colors.primary
                                : colors.mutedForeground,
                            },
                          ]}
                        >
                          {opt.label}
                        </Text>
                      </Pressable>
                    );
                  })}
                  <Pressable
                    key="custom"
                    onPress={openCustomPicker}
                    style={[
                      styles.segmentItem,
                      {
                        backgroundColor: isCustomActive
                          ? colors.card
                          : "transparent",
                        borderRadius: colors.radius - 4,
                      },
                    ]}
                    accessibilityRole="button"
                    accessibilityLabel="Choose a custom auto-lock duration"
                    accessibilityState={{ selected: isCustomActive }}
                  >
                    <Text
                      style={[
                        styles.segmentText,
                        {
                          color: isCustomActive
                            ? colors.primary
                            : colors.mutedForeground,
                        },
                      ]}
                      numberOfLines={1}
                    >
                      {isCustomActive
                        ? formatIdleTimeout(idleTimeoutMs)
                        : "Custom…"}
                    </Text>
                  </Pressable>
                </View>
                {showLastCustomChip ? (
                  <View
                    style={{
                      flexDirection: "row",
                      alignItems: "center",
                      flexWrap: "wrap",
                      gap: 8,
                    }}
                  >
                    <Text
                      style={[
                        styles.helper,
                        { color: colors.mutedForeground },
                      ]}
                    >
                      Recent custom
                    </Text>
                    <Pressable
                      onPress={recallLastCustom}
                      style={({ pressed }) => [
                        styles.recallChip,
                        {
                          backgroundColor: colors.primary + "1a",
                          borderColor: colors.primary + "40",
                          borderRadius: colors.radius - 4,
                          opacity: pressed ? 0.7 : 1,
                        },
                      ]}
                      accessibilityRole="button"
                      accessibilityLabel={`Use last custom auto-lock value, ${formatIdleTimeout(lastCustomMs ?? 0)}`}
                    >
                      <Feather
                        name="rotate-ccw"
                        size={12}
                        color={colors.primary}
                      />
                      <Text
                        style={[
                          styles.recallChipText,
                          { color: colors.primary },
                        ]}
                      >
                        {formatIdleTimeout(lastCustomMs ?? 0)}
                      </Text>
                    </Pressable>
                  </View>
                ) : null}
              </View>
              <View
                style={{
                  borderTopWidth: StyleSheet.hairlineWidth,
                  borderTopColor: colors.border,
                  paddingHorizontal: 16,
                  paddingVertical: 14,
                  gap: 10,
                }}
              >
                <View
                  style={{ flexDirection: "row", alignItems: "center", gap: 14 }}
                >
                  <Feather
                    name="alert-triangle"
                    size={18}
                    color={colors.primary}
                  />
                  <View style={{ flex: 1 }}>
                    <Text
                      style={[styles.listLabel, { color: colors.foreground }]}
                    >
                      Lock warning
                    </Text>
                    <Text
                      style={[
                        styles.helper,
                        { color: colors.mutedForeground },
                      ]}
                    >
                      {idleTimeoutMs > 0
                        ? `Heads-up ${formatLockWarningLead(
                            Math.min(
                              lockWarningLeadMs,
                              Math.max(
                                1000,
                                Math.floor(idleTimeoutMs / 2),
                              ),
                            ),
                          )} before auto-lock.`
                        : "Only used when auto-lock is on."}
                    </Text>
                  </View>
                </View>
                <View
                  style={[
                    styles.segment,
                    {
                      backgroundColor: colors.background,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  {LOCK_WARNING_OPTIONS.map((opt) => {
                    const active = lockWarningLeadMs === opt.value;
                    return (
                      <Pressable
                        key={opt.value}
                        onPress={() => {
                          setLockWarningLeadMs(opt.value).catch(() => {});
                        }}
                        style={[
                          styles.segmentItem,
                          {
                            backgroundColor: active
                              ? colors.card
                              : "transparent",
                            borderRadius: colors.radius - 4,
                          },
                        ]}
                        accessibilityRole="button"
                        accessibilityLabel={`Warn ${opt.label} before auto-lock`}
                        accessibilityState={{ selected: active }}
                      >
                        <Text
                          style={[
                            styles.segmentText,
                            {
                              color: active
                                ? colors.primary
                                : colors.mutedForeground,
                            },
                          ]}
                        >
                          {opt.label}
                        </Text>
                      </Pressable>
                    );
                  })}
                </View>
              </View>
              </>
            ) : null}
          </View>
        </View>

        {user?.role === "super_admin" || hasAdminAccess ? (
          <View style={styles.section}>
            <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
              Admin
            </Text>
            <View
              style={[
                styles.list,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <Pressable
                onPress={() => router.push("/admin" as never)}
                style={({ pressed }) => [
                  styles.listItem,
                  { borderTopWidth: 0, opacity: pressed ? 0.7 : 1 },
                ]}
              >
                <Feather name="shield" size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  Switch to admin dashboard
                </Text>
                <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
              </Pressable>
              <Pressable
                onPress={() => router.push("/mail-settings" as never)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name="mail" size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  Email / SMTP
                </Text>
                <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
              </Pressable>
              <Pressable
                onPress={() => router.push("/schema-health" as never)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name="database" size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  Schema health
                </Text>
                <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
              </Pressable>
              <Pressable
                onPress={() => router.push("/admin/repair-audits" as never)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name="tool" size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  Schema repair log
                </Text>
                <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
              </Pressable>
              <Pressable
                onPress={() => router.push("/cron-jobs" as never)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name="clock" size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  Cron jobs
                </Text>
                <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
              </Pressable>
            </View>
          </View>
        ) : null}

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Account
          </Text>
          <View
            style={[
              styles.list,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Pressable
              onPress={() => router.push("/wallet" as never)}
              style={({ pressed }) => [
                styles.listItem,
                { opacity: pressed ? 0.7 : 1 },
              ]}
            >
              <Feather name="credit-card" size={18} color={colors.primary} />
              <Text style={[styles.listLabel, { color: colors.foreground }]}>
                Wallet & coins
              </Text>
              {coinBalance !== null ? (
                <Text style={[styles.balancePill, { color: colors.primary, backgroundColor: colors.primary + "1a" }]}>
                  {coinBalance.toLocaleString()} 🪙
                </Text>
              ) : null}
              <Feather
                name="chevron-right"
                size={18}
                color={colors.mutedForeground}
              />
            </Pressable>
            <Pressable
              onPress={() => router.push("/upgrade" as never)}
              style={({ pressed }) => [
                styles.listItem,
                {
                  borderTopWidth: StyleSheet.hairlineWidth,
                  borderTopColor: colors.border,
                  opacity: pressed ? 0.7 : 1,
                },
              ]}
            >
              <Feather name="zap" size={18} color={colors.primary} />
              <Text style={[styles.listLabel, { color: colors.foreground }]}>
                Upgrade
              </Text>
              <Feather
                name="chevron-right"
                size={18}
                color={colors.mutedForeground}
              />
            </Pressable>
          </View>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Learn
          </Text>
          <View
            style={[
              styles.list,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {INFO_PAGES.map((p, i) => (
              <Pressable
                key={p.href}
                onPress={() => router.push(p.href as never)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name={p.icon} size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  {p.label}
                </Text>
                <Feather
                  name="chevron-right"
                  size={18}
                  color={colors.mutedForeground}
                />
              </Pressable>
            ))}
          </View>
        </View>

        <Button label="Sign out" variant="outline" onPress={signOut} />
      </ScrollView>

      <Modal
        visible={customPickerOpen}
        transparent
        animationType="fade"
        onRequestClose={() => setCustomPickerOpen(false)}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          style={styles.customBackdrop}
        >
          <Pressable
            style={StyleSheet.absoluteFill}
            onPress={() => setCustomPickerOpen(false)}
            accessibilityElementsHidden
            importantForAccessibility="no"
          />
          <View
            style={[
              styles.customSheet,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Text style={[styles.customTitle, { color: colors.foreground }]}>
              Custom auto-lock
            </Text>
            <Text style={[styles.helper, { color: colors.mutedForeground }]}>
              Choose between {formatIdleTimeout(IDLE_TIMEOUT_CUSTOM_MIN_MS)} and{" "}
              {formatIdleTimeout(IDLE_TIMEOUT_CUSTOM_MAX_MS)}.
            </Text>

            <View style={styles.customRow}>
              <TextInput
                value={customAmount}
                onChangeText={(t) => setCustomAmount(t.replace(/[^0-9]/g, ""))}
                keyboardType="number-pad"
                inputMode="numeric"
                maxLength={4}
                selectTextOnFocus
                style={[
                  styles.customInput,
                  {
                    color: colors.foreground,
                    borderColor: colors.border,
                    backgroundColor: colors.background,
                    borderRadius: colors.radius - 4,
                  },
                ]}
                accessibilityLabel="Auto-lock duration amount"
              />
              <View
                style={[
                  styles.segment,
                  {
                    backgroundColor: colors.background,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                    flex: 1,
                  },
                ]}
              >
                {(["sec", "min"] as CustomUnit[]).map((u) => {
                  const active = customUnit === u;
                  return (
                    <Pressable
                      key={u}
                      onPress={() => setCustomUnit(u)}
                      style={[
                        styles.segmentItem,
                        {
                          backgroundColor: active ? colors.card : "transparent",
                          borderRadius: colors.radius - 4,
                        },
                      ]}
                      accessibilityRole="button"
                      accessibilityState={{ selected: active }}
                    >
                      <Text
                        style={[
                          styles.segmentText,
                          {
                            color: active
                              ? colors.primary
                              : colors.mutedForeground,
                          },
                        ]}
                      >
                        {u === "sec" ? "Seconds" : "Minutes"}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            </View>

            <Text style={[styles.helper, { color: colors.mutedForeground }]}>
              {clampedCustomMs == null
                ? "Enter a number to continue."
                : customOutOfRange
                  ? `Out of range — will be saved as ${formatIdleTimeout(clampedCustomMs)}.`
                  : `Re-locks after ${formatIdleTimeout(clampedCustomMs)} of inactivity.`}
            </Text>

            <View style={styles.customActions}>
              <View style={{ flex: 1 }}>
                <Button
                  label="Cancel"
                  variant="outline"
                  onPress={() => setCustomPickerOpen(false)}
                />
              </View>
              <View style={{ flex: 1 }}>
                <Button
                  label="Save"
                  onPress={saveCustomPicker}
                  disabled={clampedCustomMs == null}
                />
              </View>
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  headerRow: { flexDirection: "row", alignItems: "center" },
  card: { padding: 20, borderWidth: 1, gap: 6 },
  hello: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  name: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 24 },
  badge: {
    alignSelf: "flex-start",
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    marginTop: 4,
  },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  section: { gap: 8 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  segment: { flexDirection: "row", padding: 4, borderWidth: 1 },
  segmentItem: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    paddingVertical: 10,
  },
  segmentText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  list: { borderWidth: 1, overflow: "hidden" },
  listItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    paddingHorizontal: 16,
    paddingVertical: 16,
  },
  listLabel: { flex: 1, fontFamily: "SpaceGrotesk_500Medium", fontSize: 16 },
  helper: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    marginTop: 2,
  },
  recallChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderWidth: StyleSheet.hairlineWidth,
  },
  recallChipText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
  },
  statusPill: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: "hidden",
    textTransform: "uppercase",
    letterSpacing: 0.4,
  },
  balancePill: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: "hidden",
  },
  customBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
    justifyContent: "center",
    alignItems: "stretch",
    paddingHorizontal: 24,
  },
  customSheet: {
    padding: 20,
    borderWidth: 1,
    gap: 14,
  },
  customTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 20,
  },
  customRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  customInput: {
    width: 80,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 18,
    textAlign: "center",
  },
  customActions: {
    flexDirection: "row",
    gap: 10,
    marginTop: 4,
  },
});
