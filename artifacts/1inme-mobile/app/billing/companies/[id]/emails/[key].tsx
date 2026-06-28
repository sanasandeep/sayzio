import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmailPreviewBox } from "@/components/EmailPreviewBox";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  getCompanyEmailTemplate,
  previewCompanyEmailTemplate,
  resetCompanyEmailTemplate,
  updateCompanyEmailTemplate,
  type CompanyEmailFormat,
  type CompanyEmailPreview,
} from "@/lib/api/company-mail";

// Mobile parity for the web per-company template editor: rewrite the subject/
// body override for one client-facing accounting email (invoice/receipt), flip
// html/text, see a live preview rendered through the same central pipeline as
// real sends, then save the override or reset back to the inherited content.

type Draft = { subject: string; body: string; format: CompanyEmailFormat };

export default function CompanyEmailTemplateEditorScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { id: rawId, key: rawKey } = useLocalSearchParams<{ id: string; key: string }>();
  const companyId = Number(rawId);
  const key = String(rawKey ?? "");

  const query = useQuery({
    queryKey: ["company-email-template", companyId, key],
    queryFn: () => getCompanyEmailTemplate(companyId, key),
    enabled: Number.isFinite(companyId) && key !== "",
  });

  const [draft, setDraft] = useState<Draft | null>(null);
  const seededRef = useRef(false);
  const [preview, setPreview] = useState<CompanyEmailPreview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  useEffect(() => {
    if (query.data && !seededRef.current) {
      seededRef.current = true;
      const d = query.data;
      setDraft({
        subject: d.override?.subject ?? d.default.subject ?? "",
        body: d.override?.body ?? d.preview.body ?? "",
        format: (d.override?.format ?? d.format) === "text" ? "text" : "html",
      });
      setPreview(d.preview);
    }
  }, [query.data]);

  const livePreview = useMutation({
    mutationFn: (d: Draft) => previewCompanyEmailTemplate(companyId, key, d),
    onSuccess: (p) => {
      setPreview(p);
      setError(null);
    },
    onError: (e: any) => setError(e?.message ?? "Couldn't render the preview."),
  });

  const save = useMutation({
    mutationFn: (d: Draft) => updateCompanyEmailTemplate(companyId, key, d),
    onSuccess: (r) => {
      setPreview(r.preview);
      setNotice("Template saved.");
      setError(null);
      qc.invalidateQueries({ queryKey: ["company-email-templates", companyId] });
      qc.invalidateQueries({ queryKey: ["company-email-template", companyId, key] });
    },
    onError: (e: any) => {
      setNotice(null);
      setError(e?.message ?? "Couldn't save the template.");
    },
  });

  const reset = useMutation({
    mutationFn: () => resetCompanyEmailTemplate(companyId, key),
    onSuccess: (r) => {
      seededRef.current = false;
      setPreview(r.preview);
      setNotice("Reset to the inherited default.");
      setError(null);
      qc.invalidateQueries({ queryKey: ["company-email-templates", companyId] });
      query.refetch();
    },
    onError: (e: any) => {
      setNotice(null);
      setError(e?.message ?? "Couldn't reset the template.");
    },
  });

  const set = <K extends keyof Draft>(k: K, v: Draft[K]) => {
    setDraft((p) => (p ? { ...p, [k]: v } : p));
    setNotice(null);
  };

  const data = query.data;
  const hasOverride = !!data?.override;
  const variables = data
    ? Array.isArray(data.variables)
      ? data.variables.map((v) => ({ token: v, label: "" }))
      : Object.entries(data.variables).map(([token, label]) => ({ token, label }))
    : [];

  const canSave = !!draft && draft.body.trim() !== "" && !save.isPending;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: data?.label ?? "Template", headerBackTitle: "Back" }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 48 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 404
                ? "Unknown email template."
                : "Couldn't load this template."}
            </Text>
          </View>
        ) : data && draft ? (
          <>
            {data.description ? (
              <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                {data.description}
              </Text>
            ) : null}

            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>Content</Text>

              <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>Format</Text>
              <View
                style={[
                  styles.segment,
                  { backgroundColor: colors.background, borderColor: colors.border, borderRadius: colors.radius },
                ]}
              >
                {(["html", "text"] as const).map((opt) => {
                  const on = draft.format === opt;
                  return (
                    <Pressable
                      key={opt}
                      onPress={() => set("format", opt)}
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
                label="Subject"
                value={draft.subject}
                onChangeText={(t) => set("subject", t)}
                placeholder="Leave blank to inherit the default subject"
              />

              <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>Body</Text>
              <TextInput
                value={draft.body}
                onChangeText={(t) => set("body", t)}
                placeholder={draft.format === "html" ? "<p>Body HTML…</p>" : "Body text…"}
                placeholderTextColor={colors.mutedForeground}
                multiline
                textAlignVertical="top"
                autoCapitalize="none"
                autoCorrect={false}
                style={[
                  styles.bodyInput,
                  {
                    color: colors.foreground,
                    backgroundColor: colors.background,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              />

              {variables.length > 0 ? (
                <View style={{ gap: 4, marginTop: 4 }}>
                  <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
                    Available tokens
                  </Text>
                  <View style={styles.tokenWrap}>
                    {variables.map((v) => (
                      <View
                        key={v.token}
                        style={[styles.token, { backgroundColor: colors.background, borderColor: colors.border }]}
                      >
                        <Text style={[styles.tokenText, { color: colors.foreground }]}>
                          {`{{${v.token}}}`}
                        </Text>
                      </View>
                    ))}
                  </View>
                </View>
              ) : null}

              <View style={styles.actionRow}>
                <Button
                  label="Refresh preview"
                  variant="outline"
                  onPress={() => livePreview.mutate(draft)}
                  loading={livePreview.isPending}
                  style={{ flex: 1 }}
                />
                <Button
                  label="Save"
                  onPress={() => save.mutate(draft)}
                  loading={save.isPending}
                  disabled={!canSave}
                  style={{ flex: 1 }}
                />
              </View>

              {hasOverride ? (
                <Pressable
                  onPress={() => reset.mutate()}
                  disabled={reset.isPending}
                  style={({ pressed }) => [styles.resetBtn, { opacity: pressed ? 0.6 : 1 }]}
                >
                  {reset.isPending ? (
                    <ActivityIndicator color={colors.destructive} size="small" />
                  ) : (
                    <Feather name="rotate-ccw" size={15} color={colors.destructive} />
                  )}
                  <Text style={{ color: colors.destructive, fontFamily: "SpaceGrotesk_600SemiBold" }}>
                    Reset to default
                  </Text>
                </Pressable>
              ) : null}

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
            </View>

            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>Live preview</Text>
              <Text style={[styles.note, { color: colors.mutedForeground }]}>
                Rendered with sample data through the same pipeline as real sends.
              </Text>
              {preview ? (
                <EmailPreviewBox
                  subject={preview.subject}
                  body={preview.body}
                  format={preview.format}
                />
              ) : (
                <ActivityIndicator color={colors.primary} style={{ marginTop: 8 }} />
              )}
            </View>
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 10 },
  cardTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15 },
  note: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium", lineHeight: 17 },
  fieldLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
  segment: { flexDirection: "row", padding: 4, borderWidth: 1 },
  segmentItem: { flex: 1, alignItems: "center", justifyContent: "center", paddingVertical: 10 },
  segmentText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12, textTransform: "uppercase" },
  bodyInput: {
    borderWidth: 1,
    padding: 12,
    minHeight: 160,
    fontSize: 13,
    fontFamily: "SpaceGrotesk_400Regular",
  },
  tokenWrap: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  token: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 6, paddingHorizontal: 8, paddingVertical: 3 },
  tokenText: { fontSize: 11, fontFamily: Platform.OS === "ios" ? "Menlo" : "monospace" },
  actionRow: { flexDirection: "row", gap: 10, marginTop: 2 },
  resetBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 10,
  },
  resultBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 12,
    borderWidth: 1,
    borderRadius: 10,
  },
});
