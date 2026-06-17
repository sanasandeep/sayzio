import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Linking,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import {
  getMailSettings,
  sendTestEmail,
  type MailStatusTone,
  type MailTestResult,
} from "@/lib/api/mail";

// Task #1589 — super-admin parity for the web "Email / SMTP" settings.
// Read-only: shows the effective mailer + from-identity and a status badge,
// and lets an admin fire a live test email. Editing the transport stays on
// the web admin page (linked at the bottom).

function toneColors(
  tone: MailStatusTone,
  colors: ReturnType<typeof useColors>,
): { bg: string; fg: string } {
  switch (tone) {
    case "green":
      return { bg: "#10b98122", fg: "#10b981" };
    case "amber":
      return { bg: "#f59e0b22", fg: "#f59e0b" };
    default:
      return { bg: colors.mutedForeground + "22", fg: colors.mutedForeground };
  }
}

export default function MailSettingsScreen() {
  const colors = useColors();
  const [testEmail, setTestEmail] = useState("");
  const [result, setResult] = useState<MailTestResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  const query = useQuery({
    queryKey: ["mail-settings"],
    queryFn: getMailSettings,
  });

  const test = useMutation({
    mutationFn: (email: string) => sendTestEmail(email),
    onSuccess: (r) => {
      setResult(r);
      setError(null);
    },
    onError: (e: any) => {
      setResult(null);
      setError(e?.message ?? "Couldn't send the test email.");
    },
  });

  const trimmed = testEmail.trim();
  const canSend = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed) && !test.isPending;

  const row = (label: string, value: string | null | undefined) => (
    <View style={styles.row}>
      <Text style={[styles.rowLabel, { color: colors.mutedForeground }]}>{label}</Text>
      <Text style={[styles.rowValue, { color: colors.foreground }]} numberOfLines={1}>
        {value && String(value).trim() !== "" ? String(value) : "—"}
      </Text>
    </View>
  );

  const data = query.data;
  const badge = data ? toneColors(data.status.tone, colors) : null;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Email / SMTP", headerBackTitle: "Back" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 48 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You need admin access to view mail settings."
                : "Couldn't load mail settings."}
            </Text>
          </View>
        ) : data ? (
          <>
            {/* Status badge */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.cardHead}>
                <Text style={[styles.cardTitle, { color: colors.foreground }]}>Current status</Text>
                {badge ? (
                  <View style={[styles.badge, { backgroundColor: badge.bg }]}>
                    <Text style={[styles.badgeText, { color: badge.fg }]}>{data.status.label}</Text>
                  </View>
                ) : null}
              </View>
              {data.status.key === "log" ? (
                <Text style={[styles.note, { color: colors.mutedForeground }]}>
                  The log driver writes mail to the server log instead of delivering it.
                  Choose the SMTP mailer on the web admin to send live.
                </Text>
              ) : data.status.key === "env" ? (
                <Text style={[styles.note, { color: colors.mutedForeground }]}>
                  Using the server's environment config — no admin overrides saved yet.
                </Text>
              ) : null}
            </View>

            {/* Effective transport */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.foreground, marginBottom: 4 }]}>
                Transport
              </Text>
              {row("Mailer", data.mailer)}
              {data.mailer === "smtp" ? (
                <>
                  {row("Host", data.host)}
                  {row("Port", data.port != null ? String(data.port) : null)}
                  {row("Encryption", data.encryption?.toUpperCase())}
                  {row("Password", data.has_password ? "•••••••• (set)" : "Not set")}
                </>
              ) : null}
            </View>

            {/* From identity */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.foreground, marginBottom: 4 }]}>
                From identity
              </Text>
              {row("From address", data.from_address)}
              {row("From name", data.from_name)}
            </View>

            {/* Send test */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>Send a test email</Text>
              <Text style={[styles.note, { color: colors.mutedForeground, marginBottom: 4 }]}>
                Sends a real message through the saved transport so you can confirm delivery.
              </Text>
              <TextField
                label="Recipient"
                value={testEmail}
                onChangeText={(t) => {
                  setTestEmail(t);
                  setResult(null);
                  setError(null);
                }}
                placeholder="you@example.com"
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
              />
              <Button
                label="Send test email"
                onPress={() => test.mutate(trimmed)}
                loading={test.isPending}
                disabled={!canSend}
              />

              {result ? (
                <View
                  style={[
                    styles.resultBox,
                    {
                      backgroundColor: result.sent ? "#10b98115" : "#f59e0b15",
                      borderColor: result.sent ? "#10b981" : "#f59e0b",
                    },
                  ]}
                >
                  <Feather
                    name={result.sent ? "check-circle" : "info"}
                    size={16}
                    color={result.sent ? "#10b981" : "#f59e0b"}
                  />
                  <Text style={{ color: colors.foreground, flex: 1 }}>{result.message}</Text>
                </View>
              ) : null}

              {error ? (
                <View
                  style={[
                    styles.resultBox,
                    { backgroundColor: colors.destructive + "15", borderColor: colors.destructive },
                  ]}
                >
                  <Feather name="alert-circle" size={16} color={colors.destructive} />
                  <Text style={{ color: colors.foreground, flex: 1 }}>{error}</Text>
                </View>
              ) : null}
            </View>

            <Button
              label="Edit settings on web"
              variant="outline"
              onPress={() =>
                Linking.openURL(
                  `${getBaseUrl().replace(/\/api\/?$/, "")}/admin/mail-settings`,
                )
              }
            />
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 8 },
  cardHead: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  cardTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15 },
  row: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 12 },
  rowLabel: { fontSize: 13, fontFamily: "SpaceGrotesk_500Medium" },
  rowValue: { fontSize: 13, fontFamily: "SpaceGrotesk_600SemiBold", flexShrink: 1, textAlign: "right" },
  note: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium", lineHeight: 17 },
  badge: { paddingHorizontal: 10, paddingVertical: 3, borderRadius: 999 },
  badgeText: { fontSize: 11, fontFamily: "SpaceGrotesk_700Bold" },
  resultBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 12,
    borderWidth: 1,
    borderRadius: 10,
  },
});
