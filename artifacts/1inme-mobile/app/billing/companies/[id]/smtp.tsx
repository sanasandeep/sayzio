import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
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
  getCompanySmtp,
  testCompanySmtp,
  updateCompanySmtp,
  verifyCompanySmtp,
  type CompanySmtpStatus,
  type CompanySmtpVerify,
} from "@/lib/api/company-mail";

// Mobile parity for the web per-company "SMTP" section: a creator can point a
// billing company at their own outbound mail server so client invoices and
// receipts are delivered from their domain. The stored password is never sent
// back — the field shows a masked tail and stays blank to leave it untouched.

type Draft = {
  smtp_enabled: boolean;
  smtp_host: string;
  smtp_port: string;
  smtp_encryption: string;
  smtp_username: string;
  smtp_password: string;
  smtp_from_address: string;
  smtp_from_name: string;
};

function toDraft(s: CompanySmtpStatus): Draft {
  return {
    smtp_enabled: s.smtp_enabled,
    smtp_host: s.smtp_host ?? "",
    smtp_port: s.smtp_port != null ? String(s.smtp_port) : "",
    smtp_encryption: s.smtp_encryption || "tls",
    smtp_username: s.smtp_username ?? "",
    smtp_password: "",
    smtp_from_address: s.smtp_from_address ?? "",
    smtp_from_name: s.smtp_from_name ?? "",
  };
}

export default function CompanySmtpScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { id: rawId } = useLocalSearchParams<{ id: string }>();
  const companyId = Number(rawId);

  const query = useQuery({
    queryKey: ["company-smtp", companyId],
    queryFn: () => getCompanySmtp(companyId),
    enabled: Number.isFinite(companyId),
  });

  const [draft, setDraft] = useState<Draft | null>(null);
  const [clearPassword, setClearPassword] = useState(false);
  const [testEmail, setTestEmail] = useState("");
  const seededRef = useRef(false);
  const [verify, setVerify] = useState<CompanySmtpVerify | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const data = query.data;

  useEffect(() => {
    if (data && !seededRef.current) {
      seededRef.current = true;
      setDraft(toDraft(data));
    }
  }, [data]);

  const set = <K extends keyof Draft>(k: K, v: Draft[K]) => {
    setDraft((p) => (p ? { ...p, [k]: v } : p));
    setNotice(null);
  };

  const save = useMutation({
    mutationFn: (d: Draft) =>
      updateCompanySmtp(companyId, {
        smtp_enabled: d.smtp_enabled,
        smtp_host: d.smtp_host.trim() || null,
        smtp_port: d.smtp_port.trim() ? Number(d.smtp_port.trim()) : null,
        smtp_encryption: d.smtp_encryption,
        smtp_username: d.smtp_username.trim() || null,
        smtp_password: clearPassword ? null : d.smtp_password || null,
        smtp_clear_password: clearPassword,
        smtp_from_address: d.smtp_from_address.trim() || null,
        smtp_from_name: d.smtp_from_name.trim() || null,
      }),
    onSuccess: (r) => {
      qc.setQueryData(["company-smtp", companyId], r);
      setDraft(toDraft(r));
      setClearPassword(false);
      setVerify(r.verify);
      setError(null);
      setNotice(
        r.verify && !r.verify.ok
          ? "Saved, but the connection check failed; see below."
          : "SMTP settings saved.",
      );
    },
    onError: (e: any) => {
      setNotice(null);
      setError(e?.message ?? "Couldn't save the SMTP settings.");
    },
  });

  const check = useMutation({
    mutationFn: () => verifyCompanySmtp(companyId),
    onSuccess: (r) => {
      qc.setQueryData(["company-smtp", companyId], r);
      setVerify(r.verify);
      setError(null);
      setNotice(r.verify?.ok ? "Connection looks good." : null);
    },
    onError: (e: any) => {
      setNotice(null);
      setError(e?.message ?? "Couldn't run the connection check.");
    },
  });

  const sendTest = useMutation({
    mutationFn: (to: string) => testCompanySmtp(companyId, to),
    onSuccess: (r) => {
      setError(null);
      setNotice(r.message);
    },
    onError: (e: any) => {
      setNotice(null);
      setError(e?.message ?? "Couldn't send the test email.");
    },
  });

  const encOptions = data?.encryption_options ?? ["tls", "ssl", "none"];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: data?.company_name ?? "Company SMTP", headerBackTitle: "Back" }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 48 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 404
                ? "This company couldn't be found."
                : "Couldn't load the SMTP settings."}
            </Text>
          </View>
        ) : data && draft ? (
          <>
            <Text style={{ color: colors.mutedForeground, fontSize: 13, lineHeight: 18 }}>
              Send this company's client-facing invoices and receipts from your own
              mail server. Leave SMTP off to keep using the platform default.
            </Text>

            {data.delivery_warning ? (
              <View
                style={[
                  styles.warningBox,
                  {
                    backgroundColor:
                      (data.delivery_warning.level === "danger"
                        ? colors.destructive
                        : colors.warning) + "15",
                    borderColor:
                      data.delivery_warning.level === "danger"
                        ? colors.destructive
                        : colors.warning,
                  },
                ]}
              >
                <Feather
                  name="alert-triangle"
                  size={18}
                  color={
                    data.delivery_warning.level === "danger"
                      ? colors.destructive
                      : colors.warning
                  }
                />
                <View style={{ flex: 1, gap: 4 }}>
                  <Text style={[styles.warningTitle, { color: colors.foreground }]}>
                    {data.delivery_warning.title}
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12, lineHeight: 17 }}>
                    {data.delivery_warning.body}
                  </Text>
                </View>
              </View>
            ) : null}

            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.switchRow}>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                    Use company SMTP
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }}>
                    Route this company's emails through the server below.
                  </Text>
                </View>
                <Switch
                  value={draft.smtp_enabled}
                  onValueChange={(v) => set("smtp_enabled", v)}
                />
              </View>

              <TextField
                label="Host"
                value={draft.smtp_host}
                onChangeText={(t) => set("smtp_host", t)}
                placeholder="smtp.example.com"
                autoCapitalize="none"
                autoCorrect={false}
              />
              <TextField
                label="Port"
                value={draft.smtp_port}
                onChangeText={(t) => set("smtp_port", t.replace(/[^0-9]/g, ""))}
                placeholder="587"
                keyboardType="number-pad"
              />

              <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
                Encryption
              </Text>
              <View
                style={[
                  styles.segment,
                  { backgroundColor: colors.background, borderColor: colors.border, borderRadius: colors.radius },
                ]}
              >
                {encOptions.map((opt) => {
                  const on = draft.smtp_encryption === opt;
                  return (
                    <Pressable
                      key={opt}
                      onPress={() => set("smtp_encryption", opt)}
                      style={[
                        styles.segmentItem,
                        { backgroundColor: on ? colors.card : "transparent", borderRadius: colors.radius - 4 },
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

              <TextField
                label="Username"
                value={draft.smtp_username}
                onChangeText={(t) => set("smtp_username", t)}
                placeholder="login@example.com"
                autoCapitalize="none"
                autoCorrect={false}
              />
              <TextField
                label={
                  data.has_password
                    ? `Password (saved: ${data.masked_password ?? "••••"})`
                    : "Password"
                }
                value={draft.smtp_password}
                onChangeText={(t) => {
                  set("smtp_password", t);
                  if (t) setClearPassword(false);
                }}
                placeholder={data.has_password ? "Leave blank to keep current" : "SMTP password"}
                secureTextEntry
                autoCapitalize="none"
                autoCorrect={false}
                editable={!clearPassword}
              />
              {data.has_password ? (
                <Pressable
                  onPress={() => {
                    setClearPassword((v) => !v);
                    set("smtp_password", "");
                  }}
                  style={styles.checkRow}
                >
                  <Feather
                    name={clearPassword ? "check-square" : "square"}
                    size={18}
                    color={clearPassword ? colors.primary : colors.mutedForeground}
                  />
                  <Text style={{ color: colors.foreground, fontSize: 13 }}>
                    Clear saved password (revert to the inherited sender)
                  </Text>
                </Pressable>
              ) : null}
            </View>

            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>Sender identity</Text>
              <TextField
                label="From address"
                value={draft.smtp_from_address}
                onChangeText={(t) => set("smtp_from_address", t)}
                placeholder="billing@yourdomain.com"
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
              />
              <TextField
                label="From name"
                value={draft.smtp_from_name}
                onChangeText={(t) => set("smtp_from_name", t)}
                placeholder="Your Company"
              />
            </View>

            <Button
              label="Save SMTP settings"
              onPress={() => save.mutate(draft)}
              loading={save.isPending}
            />

            {notice ? (
              <View style={[styles.resultBox, { backgroundColor: colors.success + "15", borderColor: colors.success }]}>
                <Feather name="check-circle" size={16} color={colors.success} />
                <Text style={{ color: colors.foreground, flex: 1 }}>{notice}</Text>
              </View>
            ) : null}
            {error ? (
              <View style={[styles.resultBox, { backgroundColor: colors.destructive + "15", borderColor: colors.destructive }]}>
                <Feather name="alert-circle" size={16} color={colors.destructive} />
                <Text style={{ color: colors.foreground, flex: 1 }}>{error}</Text>
              </View>
            ) : null}
            {verify && !verify.ok ? (
              <View style={[styles.resultBox, { backgroundColor: colors.destructive + "15", borderColor: colors.destructive }]}>
                <Feather name="alert-triangle" size={16} color={colors.destructive} />
                <Text style={{ color: colors.foreground, flex: 1 }}>
                  Connection check failed: {verify.error ?? "unknown error"}
                </Text>
              </View>
            ) : null}

            {/* Connection check + test send */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>Test it</Text>
              <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                {data.verified_at
                  ? `Last verified ${new Date(data.verified_at).toLocaleString()}.`
                  : "Not verified yet."}
              </Text>
              <Button
                label="Check connection"
                variant="outline"
                onPress={() => check.mutate()}
                loading={check.isPending}
                disabled={!data.is_configured}
              />
              <TextField
                label="Send a test email to"
                value={testEmail}
                onChangeText={setTestEmail}
                placeholder="you@example.com"
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
              />
              {data.allowed_test_recipients?.length ? (
                <View style={{ gap: 6 }}>
                  <Text style={{ color: colors.mutedForeground, fontSize: 12, lineHeight: 17 }}>
                    To prevent abuse, test emails can only go to an address you
                    control. Tap one to use it:
                  </Text>
                  <View style={styles.chipRow}>
                    {data.allowed_test_recipients.map((addr) => {
                      const on = testEmail.trim().toLowerCase() === addr.toLowerCase();
                      return (
                        <Pressable
                          key={addr}
                          onPress={() => {
                            setTestEmail(addr);
                            setError(null);
                          }}
                          style={[
                            styles.chip,
                            {
                              backgroundColor: on ? colors.primary + "22" : colors.background,
                              borderColor: on ? colors.primary : colors.border,
                              borderRadius: colors.radius - 4,
                            },
                          ]}
                        >
                          <Text
                            style={{
                              color: on ? colors.primary : colors.foreground,
                              fontSize: 12,
                            }}
                          >
                            {addr}
                          </Text>
                        </Pressable>
                      );
                    })}
                  </View>
                </View>
              ) : null}
              <Button
                label="Send test email"
                variant="outline"
                onPress={() => testEmail.trim() && sendTest.mutate(testEmail.trim())}
                loading={sendTest.isPending}
                disabled={!data.is_configured || !testEmail.trim()}
              />
              {!data.is_configured ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  Enable SMTP and save a host before testing.
                </Text>
              ) : null}
            </View>
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 12 },
  cardTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15 },
  fieldLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
  switchRow: { flexDirection: "row", alignItems: "center", gap: 12 },
  checkRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: { paddingVertical: 7, paddingHorizontal: 10, borderWidth: 1 },
  segment: { flexDirection: "row", padding: 4, borderWidth: 1 },
  segmentItem: { flex: 1, alignItems: "center", justifyContent: "center", paddingVertical: 10 },
  segmentText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12, textTransform: "uppercase" },
  resultBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 12,
    borderWidth: 1,
    borderRadius: 10,
  },
  warningBox: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 10,
    padding: 12,
    borderWidth: 1,
    borderRadius: 12,
  },
  warningTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13, lineHeight: 18 },
});
