import { Feather } from "@expo/vector-icons";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import * as Clipboard from "expo-clipboard";
import { Stack, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Platform,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  View,
} from "react-native";
import QRCode from "react-native-qrcode-svg";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  disableTwoFactor,
  enableTwoFactor,
  getBackupCodeStatus,
  setupTwoFactor,
  type BackupCodeStatus,
  type TwoFactorSetup,
} from "@/lib/api/security";

type Mode = "idle" | "enrolling" | "disabling";

export default function TwoFactorScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const statusQ = useQuery({
    queryKey: ["security", "backup-codes"],
    queryFn: getBackupCodeStatus,
    retry: false,
  });

  // The backend returns 409 when 2FA is off; treat that as "disabled"
  // so the screen stays usable for first-time enrolment. Other failures
  // (network/5xx) are surfaced separately so we don't mislead a user
  // into thinking 2FA is off when we actually couldn't tell.
  const statusErr = statusQ.error as
    | { status?: number; message?: string }
    | undefined;
  const isDisabledFromBackend = statusErr?.status === 409;
  const statusLoadError =
    statusErr && !isDisabledFromBackend
      ? (statusErr.message ?? "Couldn't load two-factor status")
      : null;
  const status: BackupCodeStatus | null =
    statusQ.data ??
    (isDisabledFromBackend
      ? {
          enabled: false,
          total: 0,
          remaining: 0,
          generated_at: null,
          last_used_at: null,
        }
      : null);
  const enabled = !!status?.enabled;

  const [mode, setMode] = useState<Mode>("idle");
  const [setup, setSetup] = useState<TwoFactorSetup | null>(null);
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Plaintext codes are only ever returned once, right after enable. We
  // hold them in component state until the user dismisses the panel; they
  // are never persisted or refetched.
  const [freshCodes, setFreshCodes] = useState<string[] | null>(null);
  const [disableMode, setDisableMode] = useState<"totp" | "backup">("totp");
  const [disableValue, setDisableValue] = useState("");

  const startEnrolment = async () => {
    setError(null);
    setBusy(true);
    try {
      const s = await setupTwoFactor();
      setSetup(s);
      setMode("enrolling");
      setCode("");
    } catch (e) {
      const err = e as { message?: string; status?: number };
      setError(
        err?.status === 409
          ? "Two-factor is already on. Turn it off first if you want to re-enrol."
          : (err?.message ?? "Couldn't start enrolment"),
      );
    } finally {
      setBusy(false);
    }
  };

  const cancelEnrolment = () => {
    setSetup(null);
    setCode("");
    setError(null);
    setMode("idle");
  };

  const confirmEnrolment = async () => {
    if (!/^\d{6}$/.test(code.trim())) {
      setError("Enter the 6-digit code from your authenticator app.");
      return;
    }
    setError(null);
    setBusy(true);
    try {
      const { codes } = await enableTwoFactor(code.trim());
      setFreshCodes(codes);
      setSetup(null);
      setCode("");
      setMode("idle");
      qc.invalidateQueries({ queryKey: ["security", "backup-codes"] });
    } catch (e) {
      const err = e as { message?: string; status?: number };
      setError(
        err?.status === 400
          ? "That code didn't match. Wait for a fresh one and try again."
          : err?.status === 409
            ? "Enrolment expired — start again."
            : (err?.message ?? "Couldn't enable two-factor"),
      );
    } finally {
      setBusy(false);
    }
  };

  const confirmDisable = async () => {
    const v = disableValue.trim();
    if (!v) {
      setError(
        disableMode === "totp"
          ? "Enter your current 6-digit code."
          : "Enter one of your backup codes.",
      );
      return;
    }
    setError(null);
    setBusy(true);
    try {
      await disableTwoFactor(
        disableMode === "totp" ? { code: v } : { backup_code: v },
      );
      setDisableValue("");
      setMode("idle");
      qc.invalidateQueries({ queryKey: ["security", "backup-codes"] });
    } catch (e) {
      const err = e as { message?: string; status?: number };
      setError(
        err?.status === 400
          ? "That code didn't work. Try a fresh one."
          : (err?.message ?? "Couldn't turn two-factor off"),
      );
    } finally {
      setBusy(false);
    }
  };

  const copySecret = async () => {
    if (!setup) return;
    await Clipboard.setStringAsync(setup.secret);
    if (Platform.OS !== "web") Alert.alert("Copied", "Secret copied.");
  };

  const copyCodes = async () => {
    if (!freshCodes) return;
    await Clipboard.setStringAsync(freshCodes.join("\n"));
    if (Platform.OS !== "web") Alert.alert("Copied", "Codes copied.");
  };

  const shareCodes = async () => {
    if (!freshCodes) return;
    try {
      await Share.share({
        message: `1INME backup codes — store somewhere safe.\n\n${freshCodes.join("\n")}`,
      });
    } catch {
      // user dismissed; ignore
    }
  };

  // Keep the disable-flow error cleared as the user edits, so a stale
  // "that code didn't work" doesn't linger while they're typing the
  // next attempt or switching between TOTP and backup-code input.
  useEffect(() => {
    if (mode !== "disabling") return;
    setError(null);
  }, [disableMode, disableValue, mode]);

  if (statusQ.isLoading) {
    return (
      <View
        style={{
          flex: 1,
          backgroundColor: colors.background,
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        <Stack.Screen options={{ title: "Two-factor" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Two-factor" }} />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 16, paddingBottom: 40 }}>
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          Two-factor authentication asks for a fresh 6-digit code from your
          authenticator app every time you sign in on a new device. It's the
          single biggest jump in account safety you can make here.
        </Text>

        {/* Status pill */}
        <View
          style={[
            styles.statusCard,
            {
              backgroundColor: colors.card,
              borderColor: enabled ? colors.primary : colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <View style={styles.statusRow}>
            <View
              style={[
                styles.iconWrap,
                {
                  backgroundColor: enabled
                    ? colors.primary + "1c"
                    : colors.mutedForeground + "1c",
                },
              ]}
            >
              <Feather
                name={enabled ? "shield" : "shield-off"}
                size={18}
                color={enabled ? colors.primary : colors.mutedForeground}
              />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={[styles.statusTitle, { color: colors.foreground }]}>
                {enabled ? "Two-factor is on" : "Two-factor is off"}
              </Text>
              <Text style={[styles.statusBody, { color: colors.mutedForeground }]}>
                {enabled
                  ? `${status?.remaining ?? 0} of ${status?.total ?? 0} backup codes left.`
                  : "Add an authenticator app to require a code on every new sign-in."}
              </Text>
              {statusLoadError ? (
                <Text style={[styles.error, { color: colors.destructive, marginTop: 6 }]}>
                  {statusLoadError} — pull to retry.
                </Text>
              ) : null}
            </View>
          </View>
        </View>

        {/* Fresh backup codes (post-enable) */}
        {freshCodes ? (
          <View
            style={[
              styles.card,
              {
                backgroundColor: colors.card,
                borderColor: colors.primary,
                borderRadius: colors.radius,
                gap: 12,
              },
            ]}
          >
            <Text style={[styles.title, { color: colors.foreground }]}>
              Save your backup codes
            </Text>
            <Text style={[styles.body, { color: colors.mutedForeground }]}>
              These are your only fallback if you ever lose access to your
              authenticator. Each code works once. We won't show them again.
            </Text>
            <View
              style={[
                styles.codeBlock,
                { backgroundColor: colors.background, borderColor: colors.border },
              ]}
            >
              {freshCodes.map((c) => (
                <Text key={c} style={[styles.code, { color: colors.foreground }]}>
                  {c}
                </Text>
              ))}
            </View>
            <View style={{ flexDirection: "row", gap: 8 }}>
              <Button
                label="Copy all"
                variant="outline"
                onPress={copyCodes}
                style={{ flex: 1 }}
              />
              <Button
                label="Share"
                variant="outline"
                onPress={shareCodes}
                style={{ flex: 1 }}
              />
            </View>
            <Button
              label="I've saved them"
              onPress={() => setFreshCodes(null)}
            />
          </View>
        ) : null}

        {/* Enrolment flow */}
        {mode === "enrolling" && setup ? (
          <View
            style={[
              styles.card,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
                gap: 14,
              },
            ]}
          >
            <Text style={[styles.title, { color: colors.foreground }]}>
              Scan in your authenticator
            </Text>
            <Text style={[styles.body, { color: colors.mutedForeground }]}>
              Open your authenticator app (Google Authenticator, 1Password,
              Authy, etc.) and add a new account by scanning this QR code.
            </Text>
            <View style={[styles.qrWrap, { backgroundColor: "#fff" }]}>
              <QRCode value={setup.otpauth_uri} size={200} />
            </View>
            <View style={styles.secretBlock}>
              <Text style={[styles.secretLabel, { color: colors.mutedForeground }]}>
                Or type this secret manually
              </Text>
              <Pressable onPress={copySecret} hitSlop={8}>
                <Text style={[styles.secret, { color: colors.foreground }]}>
                  {setup.secret}
                </Text>
              </Pressable>
              <Text style={[styles.secretHint, { color: colors.mutedForeground }]}>
                Tap the secret to copy. Account: {setup.account}
              </Text>
            </View>
            <TextField
              label="Enter the 6-digit code from your app"
              placeholder="123456"
              keyboardType="number-pad"
              maxLength={6}
              value={code}
              onChangeText={(v) => {
                setCode(v.replace(/\D/g, ""));
                if (error) setError(null);
              }}
            />
            {error ? (
              <Text style={[styles.error, { color: colors.destructive }]}>
                {error}
              </Text>
            ) : null}
            <View style={{ flexDirection: "row", gap: 8 }}>
              <Button
                label="Cancel"
                variant="outline"
                onPress={cancelEnrolment}
                disabled={busy}
                style={{ flex: 1 }}
              />
              <Button
                label="Verify & turn on"
                onPress={confirmEnrolment}
                loading={busy}
                style={{ flex: 1 }}
              />
            </View>
          </View>
        ) : null}

        {/* Disable flow */}
        {mode === "disabling" ? (
          <View
            style={[
              styles.card,
              {
                backgroundColor: colors.card,
                borderColor: colors.destructive,
                borderRadius: colors.radius,
                gap: 12,
              },
            ]}
          >
            <Text style={[styles.title, { color: colors.foreground }]}>
              Turn two-factor off?
            </Text>
            <Text style={[styles.body, { color: colors.mutedForeground }]}>
              We'll wipe your authenticator secret and invalidate every
              outstanding backup code. To confirm it's really you, enter a
              live code from your app or one of your backup codes.
            </Text>
            <View style={styles.segmented}>
              {(["totp", "backup"] as const).map((k) => {
                const active = disableMode === k;
                return (
                  <Pressable
                    key={k}
                    onPress={() => {
                      setDisableMode(k);
                      setDisableValue("");
                    }}
                    style={({ pressed }) => [
                      styles.segment,
                      {
                        borderColor: active ? colors.primary : colors.border,
                        backgroundColor: active
                          ? colors.primary + "1c"
                          : "transparent",
                        borderRadius: colors.radius,
                        opacity: pressed ? 0.7 : 1,
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.segmentLabel,
                        { color: active ? colors.primary : colors.foreground },
                      ]}
                    >
                      {k === "totp" ? "Authenticator code" : "Backup code"}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            <TextField
              label={
                disableMode === "totp"
                  ? "Current 6-digit code"
                  : "One unused backup code"
              }
              placeholder={disableMode === "totp" ? "123456" : "abcd-efgh"}
              autoCapitalize="none"
              keyboardType={disableMode === "totp" ? "number-pad" : "default"}
              value={disableValue}
              onChangeText={setDisableValue}
            />
            {error ? (
              <Text style={[styles.error, { color: colors.destructive }]}>
                {error}
              </Text>
            ) : null}
            <View style={{ flexDirection: "row", gap: 8 }}>
              <Button
                label="Keep on"
                variant="outline"
                onPress={() => {
                  setMode("idle");
                  setDisableValue("");
                  setError(null);
                }}
                disabled={busy}
                style={{ flex: 1 }}
              />
              <Button
                label="Turn off"
                onPress={confirmDisable}
                loading={busy}
                style={{ flex: 1 }}
              />
            </View>
          </View>
        ) : null}

        {/* Idle action */}
        {mode === "idle" && !freshCodes ? (
          enabled ? (
            <View style={{ gap: 10 }}>
              <Button
                label="Manage backup codes"
                variant="outline"
                onPress={() => router.push("/security/backup-codes" as never)}
              />
              <Button
                label="Turn two-factor off"
                variant="outline"
                onPress={() => {
                  setMode("disabling");
                  setError(null);
                }}
              />
              {error ? (
                <Text style={[styles.error, { color: colors.destructive }]}>
                  {error}
                </Text>
              ) : null}
            </View>
          ) : (
            <View style={{ gap: 10 }}>
              <Button
                label="Turn two-factor on"
                onPress={startEnrolment}
                loading={busy}
              />
              {error ? (
                <Text style={[styles.error, { color: colors.destructive }]}>
                  {error}
                </Text>
              ) : null}
            </View>
          )
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  intro: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
  },
  statusCard: { padding: 14, borderWidth: 1 },
  statusRow: { flexDirection: "row", alignItems: "center", gap: 12 },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  statusTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  statusBody: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 17,
    marginTop: 2,
  },
  card: { padding: 16, borderWidth: 1 },
  title: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
  },
  qrWrap: {
    alignSelf: "center",
    padding: 12,
    borderRadius: 8,
  },
  secretBlock: { gap: 4 },
  secretLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  secret: {
    fontFamily: Platform.select({
      ios: "Menlo",
      android: "monospace",
      default: "monospace",
    }),
    fontSize: 15,
    letterSpacing: 1.5,
  },
  secretHint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  codeBlock: {
    borderWidth: 1,
    borderRadius: 8,
    padding: 12,
    gap: 6,
  },
  code: {
    fontFamily: Platform.select({
      ios: "Menlo",
      android: "monospace",
      default: "monospace",
    }),
    fontSize: 16,
    letterSpacing: 1.5,
    textAlign: "center",
  },
  segmented: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  segment: { paddingHorizontal: 12, paddingVertical: 8, borderWidth: 1 },
  segmentLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  error: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
});
