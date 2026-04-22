import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useFocusEffect, useRouter } from "expo-router";
import { useCallback, useState } from "react";
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
import { aiCredits as aiCreditsApi, wallet as walletApi } from "@/lib/api";
import {
  formatIdleTimeout,
  IDLE_TIMEOUT_CUSTOM_MAX_MS,
  IDLE_TIMEOUT_CUSTOM_MIN_MS,
  IDLE_TIMEOUT_PRESETS_MS,
  type ThemePref,
} from "@/lib/secure";

const IDLE_TIMEOUT_OPTIONS: { value: number; label: string }[] =
  IDLE_TIMEOUT_PRESETS_MS.map((ms) => ({
    value: ms,
    label: ms === 0 ? "Off" : `${Math.round(ms / 60000)} min`,
  }));

const PRESET_VALUES = new Set<number>(IDLE_TIMEOUT_PRESETS_MS);

type CustomUnit = "sec" | "min";

const INFO_PAGES: {
  href: "/info/about" | "/info/nfc" | "/info/privacy" | "/info/terms" | "/info/help";
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { href: "/info/about", label: "About 1INME", icon: "info" },
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
    | "/followers"
    | "/social"
    | "/notifications"
    | "/subscribers"
    | "/projects"
    | "/qr"
    | "/splash"
    | "/calendar";
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { href: "/posts", label: "Posts", icon: "message-square" },
  { href: "/contacts", label: "Contacts", icon: "users" },
  { href: "/dialer", label: "Dialer", icon: "phone" },
  { href: "/forms", label: "Forms", icon: "file-text" },
  { href: "/followers", label: "Followers & Following", icon: "user-check" },
  { href: "/subscribers", label: "Subscribers", icon: "user-plus" },
  { href: "/social", label: "Social accounts", icon: "share-2" },
  { href: "/projects", label: "Projects", icon: "folder" },
  { href: "/qr", label: "QR codes", icon: "grid" },
  { href: "/splash", label: "Splash pages", icon: "layout" },
  { href: "/calendar", label: "Calendars", icon: "calendar" },
  { href: "/notifications", label: "Notifications", icon: "bell" },
];

const SETTINGS_PAGES: {
  href: "/profile-edit" | "/workspaces" | "/domains" | "/integrations" | "/vault" | "/verification";
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { href: "/profile-edit", label: "Edit profile", icon: "edit-3" },
  { href: "/workspaces", label: "Workspaces", icon: "briefcase" },
  { href: "/domains", label: "Custom domains", icon: "globe" },
  { href: "/integrations", label: "Integrations", icon: "link" },
  { href: "/vault", label: "Vault", icon: "lock" },
  { href: "/verification", label: "Verification", icon: "award" },
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
  } = useAuth();
  const { pref, setPref } = useThemeControls();
  const webTop = Platform.OS === "web" ? 67 : 0;
  const [coinBalance, setCoinBalance] = useState<number | null>(null);
  const [aiCreditBalance, setAiCreditBalance] = useState<number | null>(null);
  const [biometricBusy, setBiometricBusy] = useState(false);
  const [customPickerOpen, setCustomPickerOpen] = useState(false);
  const [customAmount, setCustomAmount] = useState("30");
  const [customUnit, setCustomUnit] = useState<CustomUnit>("sec");

  const isCustomActive = idleTimeoutMs > 0 && !PRESET_VALUES.has(idleTimeoutMs);

  const openCustomPicker = useCallback(() => {
    // Seed the picker with the current value when it's already custom,
    // otherwise default to 30 sec — a reasonable kiosk-friendly value.
    if (isCustomActive) {
      const totalSec = Math.round(idleTimeoutMs / 1000);
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
  }, [idleTimeoutMs, isCustomActive]);

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
      aiCreditsApi
        .balance()
        .then((b) => {
          if (cancelled) return;
          setAiCreditBalance(b.enabled ? b.balance : null);
        })
        .catch(() => {
          if (!cancelled) setAiCreditBalance(null);
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
            {user?.display_name || user?.email || user?.mobile || "1INME member"}
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
                onPress={() => router.push(p.href)}
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
                onPress={() => router.push(p.href)}
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
            {showBiometricRow && biometricSupported && biometricEnabled ? (
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
              </View>
            ) : null}
          </View>
        </View>

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
            {aiCreditBalance !== null ? (
              <Pressable
                onPress={() => router.push("/ai-credits" as never)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name="cpu" size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  AI credits
                </Text>
                <Text style={[styles.balancePill, { color: colors.primary, backgroundColor: colors.primary + "1a" }]}>
                  {aiCreditBalance.toLocaleString()} ✦
                </Text>
                <Feather
                  name="chevron-right"
                  size={18}
                  color={colors.mutedForeground}
                />
              </Pressable>
            ) : null}
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
                onPress={() => router.push(p.href)}
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
