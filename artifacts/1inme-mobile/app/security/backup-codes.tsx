import { Feather } from "@expo/vector-icons";
import * as Clipboard from "expo-clipboard";
import { Stack, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Platform,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import {
  generateBackupCodes,
  getBackupCodeStatus,
  type BackupCodeStatus,
} from "@/lib/api/security";

export default function BackupCodesScreen() {
  const colors = useColors();
  const router = useRouter();
  const [status, setStatus] = useState<BackupCodeStatus | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  // Plaintext codes are only ever returned once, right after generation.
  // We keep them in memory until the user dismisses the panel; we never
  // persist them or re-fetch them from the server.
  const [freshCodes, setFreshCodes] = useState<string[] | null>(null);
  const [confirming, setConfirming] = useState(false);

  useEffect(() => {
    let cancelled = false;
    getBackupCodeStatus()
      .then((s) => {
        if (!cancelled) setStatus(s);
      })
      .catch((e: { message?: string; status?: number }) => {
        if (cancelled) return;
        if (e?.status === 409) {
          setStatus({
            enabled: false,
            total: 0,
            remaining: 0,
            generated_at: null,
            last_used_at: null,
          });
        } else {
          setError(e?.message ?? "Couldn't load backup codes");
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const generate = async () => {
    setBusy(true);
    setError(null);
    try {
      const { codes, status: next } = await generateBackupCodes();
      setFreshCodes(codes);
      setStatus(next);
    } catch (e) {
      const err = e as { message?: string; status?: number };
      setError(
        err?.status === 409
          ? "Turn on two-factor authentication first."
          : (err?.message ?? "Couldn't generate codes"),
      );
    } finally {
      setBusy(false);
    }
  };

  const onRegenerate = () => {
    setConfirming(true);
  };

  const confirmRegenerate = async () => {
    setConfirming(false);
    await generate();
  };

  const copyAll = async () => {
    if (!freshCodes) return;
    await Clipboard.setStringAsync(freshCodes.join("\n"));
    if (Platform.OS !== "web") Alert.alert("Copied", "Codes copied to clipboard.");
  };

  const shareAll = async () => {
    if (!freshCodes) return;
    try {
      await Share.share({
        message: `Sayzio backup codes — store somewhere safe.\n\n${freshCodes.join("\n")}`,
      });
    } catch {
      // user dismissed; ignore
    }
  };

  if (loading) {
    return (
      <View
        style={{
          flex: 1,
          backgroundColor: colors.background,
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        <Stack.Screen options={{ title: "Backup codes" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Backup codes" }} />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 16, paddingBottom: 40 }}>
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          Backup codes are single-use fallbacks for your two-factor sign-in.
          Print them or save them somewhere offline — we can't recover them
          for you later.
        </Text>

        {!status?.enabled ? (
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
            <View style={styles.titleRow}>
              <Feather name="alert-circle" size={18} color={colors.mutedForeground} />
              <Text style={[styles.title, { color: colors.foreground }]}>
                Two-factor authentication is off
              </Text>
            </View>
            <Text style={[styles.body, { color: colors.mutedForeground }]}>
              Backup codes only make sense as a fallback for two-factor.
              Turn 2FA on first and we'll generate your codes right away.
            </Text>
            <Button
              label="Set up two-factor"
              onPress={() => router.push("/security/two-factor" as never)}
              style={{ marginTop: 8 }}
            />
          </View>
        ) : (
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
            <View style={styles.titleRow}>
              <Feather name="key" size={18} color={colors.primary} />
              <Text style={[styles.title, { color: colors.foreground }]}>
                {status.total === 0
                  ? "No codes generated yet"
                  : `${status.remaining} of ${status.total} codes left`}
              </Text>
            </View>
            {status.generated_at ? (
              <Text style={[styles.meta, { color: colors.mutedForeground }]}>
                Generated {new Date(status.generated_at).toLocaleString()}
              </Text>
            ) : null}
            {status.last_used_at ? (
              <Text style={[styles.meta, { color: colors.mutedForeground }]}>
                Last used {new Date(status.last_used_at).toLocaleString()}
              </Text>
            ) : null}
          </View>
        )}

        {error ? (
          <Text style={[styles.error, { color: colors.destructive }]}>
            {error}
          </Text>
        ) : null}

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
              Your new codes — save them now
            </Text>
            <Text style={[styles.body, { color: colors.mutedForeground }]}>
              We won't show them again. Copy or share them somewhere only you
              can reach.
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
                onPress={copyAll}
                style={{ flex: 1 }}
              />
              <Button
                label="Share"
                variant="outline"
                onPress={shareAll}
                style={{ flex: 1 }}
              />
            </View>
            <Button
              label="I've saved them"
              onPress={() => setFreshCodes(null)}
            />
          </View>
        ) : null}

        {status?.enabled ? (
          confirming ? (
            <View
              style={[
                styles.card,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.destructive,
                  borderRadius: colors.radius,
                  gap: 10,
                },
              ]}
            >
              <Text style={[styles.title, { color: colors.foreground }]}>
                Replace existing codes?
              </Text>
              <Text style={[styles.body, { color: colors.mutedForeground }]}>
                Every code you printed earlier will stop working immediately.
                Only do this if you've lost them or want to retire the old set.
              </Text>
              <View style={{ flexDirection: "row", gap: 8 }}>
                <Button
                  label="Cancel"
                  variant="outline"
                  onPress={() => setConfirming(false)}
                  style={{ flex: 1 }}
                />
                <Button
                  label="Yes, regenerate"
                  onPress={confirmRegenerate}
                  loading={busy}
                  style={{ flex: 1 }}
                />
              </View>
            </View>
          ) : status.total === 0 ? (
            <Button label="Generate backup codes" onPress={generate} loading={busy} />
          ) : (
            <Button
              label="Regenerate codes"
              variant="outline"
              onPress={onRegenerate}
              disabled={busy}
            />
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
  card: { padding: 16, borderWidth: 1, gap: 6 },
  titleRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  title: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15, flexShrink: 1 },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
  },
  meta: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  error: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  codeBlock: {
    borderWidth: 1,
    borderRadius: 8,
    padding: 12,
    gap: 6,
  },
  code: {
    fontFamily: Platform.select({ ios: "Menlo", android: "monospace", default: "monospace" }),
    fontSize: 16,
    letterSpacing: 1.5,
    textAlign: "center",
  },
});
