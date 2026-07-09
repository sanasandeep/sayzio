import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  disableSpamKeyword,
  enableDefaultSpamKeyword,
  getSpamSettings,
  importTrustedCsv,
  updateSpamSettings,
  type SpamPayload,
} from "@/lib/api/inbox";
import { showAlert } from "@/lib/webAlert";

// Mobile parity for the web Inbox "Spam settings" page. Manage default
// keyword protections (toggle them off/on), custom blocked keywords, and a
// trusted senders allow-list (emails + phones). State lives server-side in
// user.settings['spam'] and is evaluated by SpamChecker at intake.
export default function SpamSettingsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const query = useQuery({
    queryKey: ["inbox", "spam-settings"],
    queryFn: getSpamSettings,
  });

  const [blocked, setBlocked] = useState<string[]>([]);
  const [emails, setEmails] = useState<string[]>([]);
  const [phones, setPhones] = useState<string[]>([]);
  const [disabledDefaults, setDisabledDefaults] = useState<string[]>([]);

  const [newKeyword, setNewKeyword] = useState("");
  const [newEmail, setNewEmail] = useState("");
  const [newPhone, setNewPhone] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (query.data) {
      setBlocked(query.data.spam.blocked_keywords ?? []);
      setEmails(query.data.spam.trusted_emails ?? []);
      setPhones(query.data.spam.trusted_phones ?? []);
      setDisabledDefaults(query.data.spam.disabled_default_keywords ?? []);
    }
  }, [query.data]);

  const apply = (payload: SpamPayload) => {
    qc.setQueryData(["inbox", "spam-settings"], payload);
  };

  const save = useMutation({
    mutationFn: (next: {
      blocked_keywords: string[];
      disabled_default_keywords: string[];
      trusted_emails: string[];
      trusted_phones: string[];
    }) => updateSpamSettings(next),
    onSuccess: apply,
    onError: (e: any) =>
      showAlert("Couldn't save", e?.message ?? "Try again."),
  });

  const persist = (overrides?: {
    blocked?: string[];
    emails?: string[];
    phones?: string[];
    disabledDefaults?: string[];
  }) =>
    save.mutate({
      blocked_keywords: overrides?.blocked ?? blocked,
      disabled_default_keywords:
        overrides?.disabledDefaults ?? disabledDefaults,
      trusted_emails: overrides?.emails ?? emails,
      trusted_phones: overrides?.phones ?? phones,
    });

  const toggleDefault = useMutation({
    mutationFn: ({ keyword, on }: { keyword: string; on: boolean }) =>
      on ? enableDefaultSpamKeyword(keyword) : disableSpamKeyword(keyword),
    onSuccess: (res) => {
      setDisabledDefaults(res.spam.disabled_default_keywords ?? []);
      setBlocked(res.spam.blocked_keywords ?? []);
      qc.invalidateQueries({ queryKey: ["inbox", "spam-settings"] });
    },
    onError: (e: any) =>
      showAlert("Couldn't update keyword", e?.message ?? "Try again."),
  });

  const addKeyword = () => {
    const v = newKeyword.trim();
    if (!v) return;
    const next = Array.from(new Set([...blocked, v]));
    setBlocked(next);
    setNewKeyword("");
    persist({ blocked: next });
  };
  const removeKeyword = (kw: string) => {
    const next = blocked.filter((b) => b !== kw);
    setBlocked(next);
    persist({ blocked: next });
  };

  const addEmail = () => {
    const v = newEmail.trim();
    if (!v) return;
    const next = Array.from(new Set([...emails, v]));
    setEmails(next);
    setNewEmail("");
    persist({ emails: next });
  };
  const removeEmail = (e: string) => {
    const next = emails.filter((x) => x !== e);
    setEmails(next);
    persist({ emails: next });
  };

  const addPhone = () => {
    const v = newPhone.trim();
    if (!v) return;
    const next = Array.from(new Set([...phones, v]));
    setPhones(next);
    setNewPhone("");
    persist({ phones: next });
  };
  const removePhone = (p: string) => {
    const next = phones.filter((x) => x !== p);
    setPhones(next);
    persist({ phones: next });
  };

  const importCsv = async () => {
    setBusy(true);
    try {
      const DocumentPicker = await import("expo-document-picker");
      const res = await DocumentPicker.getDocumentAsync({
        type: ["text/csv", "text/plain", "*/*"],
        copyToCacheDirectory: true,
      });
      if (res.canceled || !res.assets?.[0]) return;
      const asset = res.assets[0];
      const out = await importTrustedCsv({
        uri: asset.uri,
        name: asset.name,
        mimeType: asset.mimeType ?? undefined,
      });
      setEmails(out.spam.trusted_emails ?? []);
      setPhones(out.spam.trusted_phones ?? []);
      qc.invalidateQueries({ queryKey: ["inbox", "spam-settings"] });
      showAlert(
        "Import complete",
        `Added ${out.stats.emails_added} email(s) and ${out.stats.phones_added} phone(s). ` +
          `${out.stats.duplicates} duplicate(s) skipped.`,
      );
    } catch (e: any) {
      showAlert("Couldn't import", e?.message ?? "Try a CSV file.");
    } finally {
      setBusy(false);
    }
  };

  const defaults = query.data?.defaults ?? [];
  const isDefaultDisabled = (kw: string) =>
    disabledDefaults.some((d) => d.toLowerCase() === kw.toLowerCase());

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: "Spam settings", headerBackTitle: "Inbox" }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 64 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              Couldn't load spam settings.
            </Text>
          </View>
        ) : (
          <>
            {/* Default keyword protections */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.head}>
                <Feather name="shield" size={18} color={colors.primary} />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  Built-in protections
                </Text>
              </View>
              <Text style={{ color: colors.mutedForeground, marginTop: 8, fontSize: 13 }}>
                These keywords are blocked by default. Turn one off if it's
                catching legitimate messages.
              </Text>
              <View style={{ marginTop: 12 }}>
                {defaults.map((kw, i) => {
                  const off = isDefaultDisabled(kw);
                  return (
                    <View
                      key={kw}
                      style={[
                        styles.row,
                        {
                          borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                          borderTopColor: colors.border,
                        },
                      ]}
                    >
                      <Text style={{ flex: 1, color: colors.foreground }} numberOfLines={1}>
                        {kw}
                      </Text>
                      <Pressable
                        onPress={() =>
                          toggleDefault.mutate({ keyword: kw, on: off })
                        }
                        disabled={toggleDefault.isPending}
                        style={[
                          styles.togglePill,
                          {
                            backgroundColor: off
                              ? colors.muted
                              : colors.primary + "1a",
                            borderColor: off ? colors.border : colors.primary + "55",
                          },
                        ]}
                      >
                        <Text
                          style={{
                            color: off ? colors.mutedForeground : colors.primary,
                            fontSize: 12,
                            fontWeight: "600",
                          }}
                        >
                          {off ? "Off" : "On"}
                        </Text>
                      </Pressable>
                    </View>
                  );
                })}
              </View>
            </View>

            {/* Custom blocked keywords */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                Your blocked keywords
              </Text>
              <View style={{ flexDirection: "row", gap: 8, marginTop: 10, alignItems: "flex-end" }}>
                <View style={{ flex: 1 }}>
                  <TextField
                    label="Add a keyword"
                    placeholder="e.g. crypto"
                    autoCapitalize="none"
                    value={newKeyword}
                    onChangeText={setNewKeyword}
                    onSubmitEditing={addKeyword}
                  />
                </View>
                <Button label="Add" onPress={addKeyword} disabled={!newKeyword.trim()} />
              </View>
              <View style={styles.chipWrap}>
                {blocked.length === 0 ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                    No custom keywords yet.
                  </Text>
                ) : (
                  blocked.map((kw) => (
                    <Pressable
                      key={kw}
                      onPress={() => removeKeyword(kw)}
                      style={[styles.chip, { backgroundColor: colors.muted, borderColor: colors.border }]}
                    >
                      <Text style={{ color: colors.foreground, fontSize: 13 }}>{kw}</Text>
                      <Feather name="x" size={13} color={colors.mutedForeground} />
                    </Pressable>
                  ))
                )}
              </View>
            </View>

            {/* Trusted senders */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                Trusted senders
              </Text>
              <Text style={{ color: colors.mutedForeground, marginTop: 6, fontSize: 13 }}>
                Messages from these emails or phone numbers always reach you.
              </Text>

              <View style={{ flexDirection: "row", gap: 8, marginTop: 12, alignItems: "flex-end" }}>
                <View style={{ flex: 1 }}>
                  <TextField
                    label="Trusted email"
                    placeholder="person@example.com"
                    autoCapitalize="none"
                    keyboardType="email-address"
                    value={newEmail}
                    onChangeText={setNewEmail}
                    onSubmitEditing={addEmail}
                  />
                </View>
                <Button label="Add" onPress={addEmail} disabled={!newEmail.trim()} />
              </View>
              <View style={styles.chipWrap}>
                {emails.map((e) => (
                  <Pressable
                    key={e}
                    onPress={() => removeEmail(e)}
                    style={[styles.chip, { backgroundColor: colors.muted, borderColor: colors.border }]}
                  >
                    <Text style={{ color: colors.foreground, fontSize: 13 }}>{e}</Text>
                    <Feather name="x" size={13} color={colors.mutedForeground} />
                  </Pressable>
                ))}
              </View>

              <View style={{ flexDirection: "row", gap: 8, marginTop: 12, alignItems: "flex-end" }}>
                <View style={{ flex: 1 }}>
                  <TextField
                    label="Trusted phone"
                    placeholder="+1 555 123 4567"
                    keyboardType="phone-pad"
                    value={newPhone}
                    onChangeText={setNewPhone}
                    onSubmitEditing={addPhone}
                  />
                </View>
                <Button label="Add" onPress={addPhone} disabled={!newPhone.trim()} />
              </View>
              <View style={styles.chipWrap}>
                {phones.map((p) => (
                  <Pressable
                    key={p}
                    onPress={() => removePhone(p)}
                    style={[styles.chip, { backgroundColor: colors.muted, borderColor: colors.border }]}
                  >
                    <Text style={{ color: colors.foreground, fontSize: 13 }}>{p}</Text>
                    <Feather name="x" size={13} color={colors.mutedForeground} />
                  </Pressable>
                ))}
              </View>

              <Pressable
                onPress={importCsv}
                disabled={busy}
                style={({ pressed }) => [
                  styles.importBtn,
                  { borderColor: colors.border, opacity: pressed || busy ? 0.6 : 1 },
                ]}
              >
                {busy ? (
                  <ActivityIndicator color={colors.primary} size="small" />
                ) : (
                  <Feather name="upload" size={16} color={colors.primary} />
                )}
                <Text style={{ color: colors.primary, fontWeight: "600" }}>
                  Import trusted senders from CSV
                </Text>
              </Pressable>
            </View>
          </>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 16, padding: 16 },
  head: { flexDirection: "row", alignItems: "center", gap: 8 },
  title: { fontSize: 16, fontWeight: "700" },
  sectionTitle: { fontSize: 15, fontWeight: "700" },
  row: { flexDirection: "row", alignItems: "center", gap: 12, paddingVertical: 12 },
  togglePill: {
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 999,
    borderWidth: StyleSheet.hairlineWidth,
  },
  chipWrap: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginTop: 12 },
  chip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: StyleSheet.hairlineWidth,
  },
  importBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    marginTop: 16,
    paddingVertical: 12,
    borderRadius: 12,
    borderWidth: StyleSheet.hairlineWidth,
  },
});
