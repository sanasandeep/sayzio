import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  getMailSettings,
  sendTestEmail,
  updateMailSettings,
  type MailSettingsSaveResult,
  type MailSettingsStatus,
  type MailStatusTone,
  type MailTestResult,
} from "@/lib/api/mail";

// Super-admin parity for the web "Email / SMTP" settings. Shows the
// effective transport with a status badge, lets an admin fully edit the
// mailer / SMTP host-port-encryption-username-password and the from-identity,
// and fire a live test email — so a broken SMTP config can be fixed entirely
// from a phone. The stored SMTP password is never sent back to the device;
// leaving the password blank keeps it untouched, and the clear toggle resets
// it to the server env fallback.

function toneColors(
  tone: MailStatusTone,
  colors: ReturnType<typeof useColors>,
): { bg: string; fg: string } {
  switch (tone) {
    case "green":
      return { bg: colors.success + "22", fg: colors.success };
    case "amber":
      return { bg: "#f59e0b22", fg: "#f59e0b" };
    default:
      return { bg: colors.mutedForeground + "22", fg: colors.mutedForeground };
  }
}

type FormState = {
  mailer: string;
  host: string;
  port: string;
  encryption: string;
  username: string;
  password: string;
  clearPassword: boolean;
  fromAddress: string;
  fromName: string;
};

function seedForm(data: MailSettingsStatus): FormState {
  return {
    mailer: data.mailer,
    host: data.host ?? "",
    port: data.port != null ? String(data.port) : "",
    encryption: data.encryption,
    username: data.username ?? "",
    password: "",
    clearPassword: false,
    fromAddress: data.from_address ?? "",
    fromName: data.from_name ?? "",
  };
}

export default function MailSettingsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [form, setForm] = useState<FormState | null>(null);
  const seededRef = useRef(false);
  const reseedRef = useRef(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saveResult, setSaveResult] = useState<MailSettingsSaveResult | null>(null);

  const [testEmail, setTestEmail] = useState("");
  const [result, setResult] = useState<MailTestResult | null>(null);
  const [testError, setTestError] = useState<string | null>(null);

  const query = useQuery({
    queryKey: ["mail-settings"],
    queryFn: getMailSettings,
  });

  // Seed the editable form once from the server, then leave it under the
  // admin's control so a background refetch doesn't clobber in-progress edits.
  // A successful save flips reseedRef so we re-seed from the freshly-refetched
  // server truth (e.g. has_password after a clear), never a stale response.
  useEffect(() => {
    if (query.data && (!seededRef.current || reseedRef.current)) {
      seededRef.current = true;
      reseedRef.current = false;
      setForm(seedForm(query.data));
    }
  }, [query.data]);

  const save = useMutation({
    mutationFn: (payload: FormState) =>
      updateMailSettings({
        mailer: payload.mailer,
        host: payload.mailer === "smtp" ? payload.host.trim() || null : null,
        port:
          payload.mailer === "smtp" && payload.port.trim() !== ""
            ? Number(payload.port)
            : null,
        encryption: payload.encryption,
        username: payload.username.trim() || null,
        password: payload.clearPassword ? "" : payload.password,
        clear_password: payload.clearPassword,
        from_address: payload.fromAddress.trim(),
        from_name: payload.fromName.trim(),
      }),
    onSuccess: (r) => {
      setSaveResult(r);
      setSaveError(null);
      // Re-seed optimistically from the saved state (clears the password
      // field), then refetch so the has_password indicator reflects server
      // truth even if a momentary cache made the response lag behind.
      setForm(seedForm(r));
      reseedRef.current = true;
      qc.invalidateQueries({ queryKey: ["mail-settings"] });
    },
    onError: (e: any) => {
      setSaveResult(null);
      setSaveError(e?.message ?? "Couldn't save the mail settings.");
    },
  });

  const test = useMutation({
    mutationFn: (email: string) => sendTestEmail(email),
    onSuccess: (r) => {
      setResult(r);
      setTestError(null);
    },
    onError: (e: any) => {
      setResult(null);
      setTestError(e?.message ?? "Couldn't send the test email.");
    },
  });

  const data = query.data;
  const badge = data ? toneColors(data.status.tone, colors) : null;

  const trimmedTest = testEmail.trim();
  const canSend =
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedTest) && !test.isPending;

  // Save gating mirrors the web validation: from-identity always required;
  // host + port required only for the SMTP transport.
  const fromOk =
    !!form &&
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.fromAddress.trim()) &&
    form.fromName.trim() !== "";
  const smtpOk =
    !!form &&
    (form.mailer !== "smtp" ||
      (form.host.trim() !== "" &&
        form.port.trim() !== "" &&
        Number(form.port) > 0));
  const canSave = !!form && fromOk && smtpOk && !save.isPending;

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => {
    setForm((p) => (p ? { ...p, [key]: value } : p));
    setSaveResult(null);
    setSaveError(null);
  };

  const segment = (
    options: string[],
    value: string,
    onPick: (v: string) => void,
  ) => (
    <View
      style={[
        styles.segment,
        { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
      ]}
    >
      {options.map((opt) => {
        const on = value === opt;
        return (
          <Pressable
            key={opt}
            onPress={() => onPick(opt)}
            style={[
              styles.segmentItem,
              {
                backgroundColor: on ? colors.background : "transparent",
                borderRadius: colors.radius - 4,
              },
            ]}
          >
            <Text
              style={[
                styles.segmentText,
                { color: on ? colors.primary : colors.mutedForeground },
              ]}
            >
              {opt}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );

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
        ) : data && form ? (
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
                  Choose the SMTP mailer below to send live.
                </Text>
              ) : data.status.key === "env" ? (
                <Text style={[styles.note, { color: colors.mutedForeground }]}>
                  Using the server's environment config — no admin overrides saved yet.
                </Text>
              ) : null}
            </View>

            {/* Editable transport */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>Transport</Text>

              <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>Mailer</Text>
              {segment(data.mailers, form.mailer, (v) => set("mailer", v))}

              {form.mailer === "smtp" ? (
                <>
                  <TextField
                    label="SMTP host"
                    value={form.host}
                    onChangeText={(t) => set("host", t)}
                    placeholder="smtp.example.com"
                    autoCapitalize="none"
                    autoCorrect={false}
                  />
                  <TextField
                    label="SMTP port"
                    value={form.port}
                    onChangeText={(t) => set("port", t.replace(/[^0-9]/g, ""))}
                    placeholder="587"
                    keyboardType="number-pad"
                  />
                  <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
                    Encryption
                  </Text>
                  {segment(data.encryption_options, form.encryption, (v) =>
                    set("encryption", v),
                  )}
                  <TextField
                    label="Username"
                    value={form.username}
                    onChangeText={(t) => set("username", t)}
                    placeholder="Optional"
                    autoCapitalize="none"
                    autoCorrect={false}
                  />
                  <TextField
                    label="Password"
                    value={form.password}
                    onChangeText={(t) => set("password", t)}
                    placeholder={
                      data.has_password
                        ? "•••••••• (leave blank to keep)"
                        : "Not set"
                    }
                    secureTextEntry
                    autoCapitalize="none"
                    autoCorrect={false}
                    editable={!form.clearPassword}
                  />
                  {data.has_password ? (
                    <View style={styles.switchRow}>
                      <Text style={[styles.switchLabel, { color: colors.foreground }]}>
                        Clear saved password (reset to env)
                      </Text>
                      <Switch
                        value={form.clearPassword}
                        onValueChange={(nv) => set("clearPassword", nv)}
                        trackColor={{ true: colors.primary, false: colors.border }}
                      />
                    </View>
                  ) : null}
                </>
              ) : null}
            </View>

            {/* From identity */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>From identity</Text>
              <TextField
                label="From address"
                value={form.fromAddress}
                onChangeText={(t) => set("fromAddress", t)}
                placeholder="hello@example.com"
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
              />
              <TextField
                label="From name"
                value={form.fromName}
                onChangeText={(t) => set("fromName", t)}
                placeholder="Sayzio"
              />

              <Button
                label="Save settings"
                onPress={() => form && save.mutate(form)}
                loading={save.isPending}
                disabled={!canSave}
              />

              {saveResult ? (
                <View
                  style={[
                    styles.resultBox,
                    {
                      backgroundColor:
                        saveResult.verify && !saveResult.verify.ok
                          ? "#f59e0b15"
                          : colors.success + "15",
                      borderColor:
                        saveResult.verify && !saveResult.verify.ok ? "#f59e0b" : colors.success,
                    },
                  ]}
                >
                  <Feather
                    name={
                      saveResult.verify && !saveResult.verify.ok ? "info" : "check-circle"
                    }
                    size={16}
                    color={saveResult.verify && !saveResult.verify.ok ? "#f59e0b" : colors.success}
                  />
                  <Text style={{ color: colors.foreground, flex: 1 }}>
                    {saveResult.verify
                      ? saveResult.verify.ok
                        ? "Settings saved — SMTP connection verified."
                        : "Settings saved, but the SMTP connection check failed: " +
                          (saveResult.verify.error ?? "unknown error")
                      : "Settings saved."}
                  </Text>
                </View>
              ) : null}

              {saveError ? (
                <View
                  style={[
                    styles.resultBox,
                    { backgroundColor: colors.destructive + "15", borderColor: colors.destructive },
                  ]}
                >
                  <Feather name="alert-circle" size={16} color={colors.destructive} />
                  <Text style={{ color: colors.foreground, flex: 1 }}>{saveError}</Text>
                </View>
              ) : null}
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
                  setTestError(null);
                }}
                placeholder="you@example.com"
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
              />
              <Button
                label="Send test email"
                onPress={() => test.mutate(trimmedTest)}
                loading={test.isPending}
                disabled={!canSend}
              />

              {result ? (
                <View
                  style={[
                    styles.resultBox,
                    {
                      backgroundColor: result.sent ? colors.success + "15" : "#f59e0b15",
                      borderColor: result.sent ? colors.success : "#f59e0b",
                    },
                  ]}
                >
                  <Feather
                    name={result.sent ? "check-circle" : "info"}
                    size={16}
                    color={result.sent ? colors.success : "#f59e0b"}
                  />
                  <Text style={{ color: colors.foreground, flex: 1 }}>{result.message}</Text>
                </View>
              ) : null}

              {testError ? (
                <View
                  style={[
                    styles.resultBox,
                    { backgroundColor: colors.destructive + "15", borderColor: colors.destructive },
                  ]}
                >
                  <Feather name="alert-circle" size={16} color={colors.destructive} />
                  <Text style={{ color: colors.foreground, flex: 1 }}>{testError}</Text>
                </View>
              ) : null}
            </View>
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
  note: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium", lineHeight: 17 },
  badge: { paddingHorizontal: 10, paddingVertical: 3, borderRadius: 999 },
  badgeText: { fontSize: 11, fontFamily: "SpaceGrotesk_700Bold" },
  fieldLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
  segment: { flexDirection: "row", padding: 4, borderWidth: 1 },
  segmentItem: { flex: 1, alignItems: "center", justifyContent: "center", paddingVertical: 10 },
  segmentText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    textTransform: "uppercase",
  },
  switchRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
  },
  switchLabel: { fontSize: 13, fontFamily: "SpaceGrotesk_500Medium", flex: 1 },
  resultBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 12,
    borderWidth: 1,
    borderRadius: 10,
  },
});
