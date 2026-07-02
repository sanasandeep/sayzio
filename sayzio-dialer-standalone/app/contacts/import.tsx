import { Feather } from "@expo/vector-icons";
import { useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Modal,
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
  contactImport,
  type ContactImportStatus,
  type ImportPreview,
  type ImportRow,
  type ImportRowPayload,
} from "@/lib/api/contacts";

// Bulk-import preview parity with the web "Import contacts" wizard: pick a
// CSV/vCard file, parse it server-side, review the staged rows (with
// per-row warnings + plan-cap notice), drop bad rows, then commit. Large
// files are queued and we poll the import status.
export default function ContactsImportScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const [busy, setBusy] = useState(false);
  const [preview, setPreview] = useState<ImportPreview | null>(null);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<ContactImportStatus | null>(null);
  const [editing, setEditing] = useState<{ index: number; row: ImportRow } | null>(
    null,
  );
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    return () => {
      if (pollRef.current) clearInterval(pollRef.current);
    };
  }, []);

  const pickAndParse = useCallback(async () => {
    setBusy(true);
    try {
      const DocumentPicker = await import("expo-document-picker");
      const res = await DocumentPicker.getDocumentAsync({
        type: ["text/csv", "text/vcard", "text/x-vcard", "text/plain", "*/*"],
        copyToCacheDirectory: true,
      });
      if (res.canceled || !res.assets?.[0]) return;
      const asset = res.assets[0];
      const data = await contactImport.parse({
        uri: asset.uri,
        name: asset.name,
        mimeType: asset.mimeType ?? undefined,
      });
      setPreview(data);
      setPage(1);
    } catch (e: any) {
      Alert.alert("Couldn't read file", e?.message ?? "Try a CSV or vCard file.");
    } finally {
      setBusy(false);
    }
  }, []);

  const reloadPage = useCallback(
    async (token: string, p: number) => {
      setBusy(true);
      try {
        const data = await contactImport.preview(token, p);
        setPreview(data);
        setPage(p);
      } catch (e: any) {
        Alert.alert("Error", e?.message ?? "Could not load page.");
      } finally {
        setBusy(false);
      }
    },
    [],
  );

  const skipRow = useCallback(
    async (token: string, index: number) => {
      setBusy(true);
      try {
        const data = await contactImport.skipRow(token, index, page);
        setPreview(data);
      } catch (e: any) {
        Alert.alert("Error", e?.message ?? "Could not skip row.");
      } finally {
        setBusy(false);
      }
    },
    [page],
  );

  const saveRow = useCallback(
    async (token: string, index: number, payload: ImportRowPayload) => {
      setBusy(true);
      try {
        const data = await contactImport.updateRow(token, index, payload, page);
        setPreview(data);
        setEditing(null);
      } catch (e: any) {
        Alert.alert("Error", e?.message ?? "Could not save row.");
      } finally {
        setBusy(false);
      }
    },
    [page],
  );

  const cancel = useCallback(async () => {
    if (!preview) return;
    try {
      await contactImport.cancel(preview.token);
    } catch {
      // best-effort — stash may already be gone
    }
    setPreview(null);
    setPage(1);
  }, [preview]);

  const pollStatus = useCallback(
    (id: number) => {
      if (pollRef.current) clearInterval(pollRef.current);
      pollRef.current = setInterval(async () => {
        try {
          const s = await contactImport.status(id);
          setStatus(s);
          if (!s.in_progress) {
            if (pollRef.current) clearInterval(pollRef.current);
            qc.invalidateQueries({ queryKey: ["contacts"] });
          }
        } catch {
          if (pollRef.current) clearInterval(pollRef.current);
        }
      }, 2000);
    },
    [qc],
  );

  const confirm = useCallback(async () => {
    if (!preview) return;
    setBusy(true);
    try {
      const r = await contactImport.confirm(preview.token);
      setPreview(null);
      if (r.queued && r.import.in_progress) {
        setStatus(r.import);
        pollStatus(r.import.id);
      } else {
        const res = r.results;
        setStatus(r.import);
        qc.invalidateQueries({ queryKey: ["contacts"] });
        Alert.alert(
          "Import complete",
          `Created ${res?.created ?? r.import.created} contact(s)` +
            (res?.failed?.length ? `, ${res.failed.length} failed` : "") +
            (res?.skippedCap ? `, ${res.skippedCap} skipped (plan cap)` : "") +
            ".",
        );
      }
    } catch (e: any) {
      Alert.alert("Import failed", e?.message ?? "Try again");
    } finally {
      setBusy(false);
    }
  }, [preview, pollStatus, qc]);

  // ── Render: queued / completed status ──────────────────────────
  if (status) {
    const done = !status.in_progress;
    return (
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen options={{ title: "Importing contacts" }} />
        <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
          <View style={[card(colors)]}>
            <View style={styles.head}>
              <Feather
                name={done ? "check-circle" : "loader"}
                size={20}
                color={done ? colors.primary : colors.mutedForeground}
              />
              <Text style={[styles.title, { color: colors.foreground }]}>
                {done ? "Import complete" : "Import in progress…"}
              </Text>
            </View>
            <Text style={{ color: colors.mutedForeground, marginTop: 8 }}>
              {status.processed} / {status.total} processed · {status.created}{" "}
              created
              {status.failed_count ? ` · ${status.failed_count} failed` : ""}
              {status.skipped_cap ? ` · ${status.skipped_cap} skipped` : ""}
            </Text>
            <View
              style={[styles.bar, { backgroundColor: colors.border }]}
            >
              <View
                style={{
                  width: `${Math.min(100, status.percent)}%`,
                  height: "100%",
                  backgroundColor: colors.primary,
                  borderRadius: 999,
                }}
              />
            </View>
          </View>

          {status.failed?.length ? (
            <View style={[card(colors)]}>
              <Text style={[styles.title, { color: colors.foreground }]}>
                Failed rows
              </Text>
              {status.failed.slice(0, 50).map((f, i) => (
                <Text
                  key={i}
                  style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 4 }}
                >
                  Row {f.row}: {f.name || "—"} — {f.reason}
                </Text>
              ))}
            </View>
          ) : null}

          {done ? (
            <Pressable
              onPress={() => router.back()}
              style={[btn(colors, colors.primary)]}
            >
              <Text style={styles.btnText}>Done</Text>
            </Pressable>
          ) : (
            <ActivityIndicator color={colors.primary} />
          )}
        </ScrollView>
      </View>
    );
  }

  // ── Render: preview rows ───────────────────────────────────────
  if (preview) {
    const s = preview.stats;
    return (
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen options={{ title: "Review import" }} />
        <ScrollView contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 120 }}>
          <View style={[card(colors)]}>
            <Text style={[styles.title, { color: colors.foreground }]}>
              {preview.original_name}
            </Text>
            <Text style={{ color: colors.mutedForeground, marginTop: 6 }}>
              {s.total} contact(s) ready
              {s.warnings ? ` · ${s.warnings} with warnings` : ""}
            </Text>
            {s.over_cap > 0 ? (
              <View
                style={[styles.notice, { backgroundColor: colors.destructive + "1a" }]}
              >
                <Feather name="alert-triangle" size={14} color={colors.destructive} />
                <Text style={{ color: colors.destructive, fontSize: 12, flex: 1 }}>
                  {s.over_cap} contact(s) exceed your plan limit and won't be
                  imported.
                </Text>
              </View>
            ) : null}
          </View>

          {preview.rows.map((row, i) => {
            const absIndex = preview.meta.offset + i;
            const name =
              row.display_name ||
              [row.given_name, row.family_name].filter(Boolean).join(" ") ||
              row.organization ||
              "Unnamed";
            return (
              <View key={absIndex} style={[card(colors)]}>
                <View style={styles.rowHead}>
                  <Text
                    style={{ color: colors.foreground, fontWeight: "600", flex: 1 }}
                    numberOfLines={1}
                  >
                    {name}
                  </Text>
                  <Pressable
                    onPress={() => setEditing({ index: absIndex, row })}
                    hitSlop={8}
                    style={{ marginRight: 14 }}
                  >
                    <Feather name="edit-2" size={16} color={colors.mutedForeground} />
                  </Pressable>
                  <Pressable
                    onPress={() => skipRow(preview.token, absIndex)}
                    hitSlop={8}
                  >
                    <Feather name="x" size={18} color={colors.mutedForeground} />
                  </Pressable>
                </View>
                {(row.phones ?? []).map((p, pi) => (
                  <Text key={`p${pi}`} style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    {p.label ? `${p.label}: ` : ""}
                    {p.value}
                  </Text>
                ))}
                {(row.emails ?? []).map((e, ei) => (
                  <Text key={`e${ei}`} style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    {e.label ? `${e.label}: ` : ""}
                    {e.value}
                  </Text>
                ))}
                {row.warnings?.length ? (
                  <View
                    style={[styles.notice, { backgroundColor: colors.primary + "12" }]}
                  >
                    <Feather name="info" size={12} color={colors.mutedForeground} />
                    <Text style={{ color: colors.mutedForeground, fontSize: 11, flex: 1 }}>
                      {row.warnings.join(" · ")}
                    </Text>
                  </View>
                ) : null}
              </View>
            );
          })}

          {preview.meta.last_page > 1 ? (
            <View style={styles.pager}>
              <Pressable
                disabled={page <= 1 || busy}
                onPress={() => reloadPage(preview.token, page - 1)}
                style={[btnSm(colors), { opacity: page <= 1 ? 0.4 : 1 }]}
              >
                <Feather name="chevron-left" size={16} color={colors.foreground} />
              </Pressable>
              <Text style={{ color: colors.mutedForeground }}>
                Page {preview.meta.current_page} / {preview.meta.last_page}
              </Text>
              <Pressable
                disabled={page >= preview.meta.last_page || busy}
                onPress={() => reloadPage(preview.token, page + 1)}
                style={[btnSm(colors), { opacity: page >= preview.meta.last_page ? 0.4 : 1 }]}
              >
                <Feather name="chevron-right" size={16} color={colors.foreground} />
              </Pressable>
            </View>
          ) : null}
        </ScrollView>

        <View style={[styles.footer, { backgroundColor: colors.card, borderTopColor: colors.border }]}>
          <Pressable onPress={cancel} style={[btn(colors, colors.border), { flex: 1 }]}>
            <Text style={{ color: colors.foreground, fontWeight: "600" }}>Cancel</Text>
          </Pressable>
          <Pressable
            onPress={confirm}
            disabled={busy || s.total === 0}
            style={[btn(colors, colors.primary), { flex: 2, opacity: s.total === 0 ? 0.5 : 1 }]}
          >
            {busy ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.btnText}>Import {s.total} contact(s)</Text>
            )}
          </Pressable>
        </View>

        <Modal
          visible={!!editing}
          animationType="slide"
          presentationStyle="pageSheet"
          onRequestClose={() => setEditing(null)}
        >
          {editing ? (
            <RowEditor
              colors={colors}
              row={editing.row}
              busy={busy}
              onCancel={() => setEditing(null)}
              onSave={(payload) =>
                saveRow(preview.token, editing.index, payload)
              }
            />
          ) : null}
        </Modal>
      </View>
    );
  }

  // ── Render: empty / pick file ──────────────────────────────────
  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Import from file" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 16 }}>
        <View style={[card(colors)]}>
          <Feather name="upload-cloud" size={28} color={colors.primary} />
          <Text style={[styles.title, { color: colors.foreground, marginTop: 10 }]}>
            Import contacts from a file
          </Text>
          <Text style={{ color: colors.mutedForeground, marginTop: 6 }}>
            Upload a CSV or vCard (.vcf) file. We'll show you a preview so you
            can review and remove any rows before importing.
          </Text>
        </View>
        <Pressable
          onPress={pickAndParse}
          disabled={busy}
          style={[btn(colors, colors.primary)]}
        >
          {busy ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.btnText}>Choose a file</Text>
          )}
        </Pressable>
      </ScrollView>
    </View>
  );
}

const card = (colors: ReturnType<typeof useColors>) => ({
  backgroundColor: colors.card,
  borderColor: colors.border,
  borderWidth: StyleSheet.hairlineWidth,
  borderRadius: colors.radius,
  padding: 16,
});

const btn = (colors: ReturnType<typeof useColors>, bg: string) => ({
  backgroundColor: bg,
  borderRadius: colors.radius,
  paddingVertical: 14,
  alignItems: "center" as const,
  justifyContent: "center" as const,
});

const btnSm = (colors: ReturnType<typeof useColors>) => ({
  backgroundColor: colors.card,
  borderColor: colors.border,
  borderWidth: StyleSheet.hairlineWidth,
  borderRadius: colors.radius - 4,
  padding: 8,
});

function RowEditor({
  colors,
  row,
  busy,
  onCancel,
  onSave,
}: {
  colors: ReturnType<typeof useColors>;
  row: ImportRow;
  busy: boolean;
  onCancel: () => void;
  onSave: (payload: ImportRowPayload) => void;
}) {
  const [displayName, setDisplayName] = useState(row.display_name ?? "");
  const [givenName, setGivenName] = useState(row.given_name ?? "");
  const [familyName, setFamilyName] = useState(row.family_name ?? "");
  const [organization, setOrganization] = useState(row.organization ?? "");
  const [phones, setPhones] = useState<{ label: string; value: string }[]>(
    (row.phones ?? []).map((p) => ({ label: p.label ?? "", value: p.value })),
  );
  const [emails, setEmails] = useState<{ label: string; value: string }[]>(
    (row.emails ?? []).map((e) => ({ label: e.label ?? "", value: e.value })),
  );

  const submit = () => {
    onSave({
      display_name: displayName.trim() || null,
      given_name: givenName.trim() || null,
      family_name: familyName.trim() || null,
      organization: organization.trim() || null,
      phones: phones
        .filter((p) => p.value.trim())
        .map((p) => ({ label: p.label.trim() || null, value: p.value.trim() })),
      emails: emails
        .filter((e) => e.value.trim())
        .map((e) => ({ label: e.label.trim() || null, value: e.value.trim() })),
    });
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View
        style={[
          styles.editorHead,
          { borderBottomColor: colors.border, backgroundColor: colors.card },
        ]}
      >
        <Pressable onPress={onCancel} hitSlop={8}>
          <Text style={{ color: colors.mutedForeground, fontSize: 15 }}>Cancel</Text>
        </Pressable>
        <Text style={{ color: colors.foreground, fontWeight: "700", fontSize: 16 }}>
          Edit contact
        </Text>
        <Pressable onPress={submit} hitSlop={8} disabled={busy}>
          <Text style={{ color: colors.primary, fontWeight: "700", fontSize: 15 }}>
            Save
          </Text>
        </Pressable>
      </View>
      <ScrollView contentContainerStyle={{ padding: 16, gap: 12 }}>
        <TextField label="Display name" value={displayName} onChangeText={setDisplayName} />
        <TextField label="First name" value={givenName} onChangeText={setGivenName} />
        <TextField label="Last name" value={familyName} onChangeText={setFamilyName} />
        <TextField label="Organization" value={organization} onChangeText={setOrganization} />

        <Text style={[styles.editorSection, { color: colors.foreground }]}>Phones</Text>
        {phones.map((p, i) => (
          <View key={`ph${i}`} style={styles.editorPairRow}>
            <View style={{ width: 96 }}>
              <TextField
                label="Label"
                value={p.label}
                onChangeText={(t) =>
                  setPhones((prev) =>
                    prev.map((x, xi) => (xi === i ? { ...x, label: t } : x)),
                  )
                }
              />
            </View>
            <View style={{ flex: 1 }}>
              <TextField
                label="Number"
                keyboardType="phone-pad"
                value={p.value}
                onChangeText={(t) =>
                  setPhones((prev) =>
                    prev.map((x, xi) => (xi === i ? { ...x, value: t } : x)),
                  )
                }
              />
            </View>
            <Pressable
              onPress={() => setPhones((prev) => prev.filter((_, xi) => xi !== i))}
              hitSlop={8}
              style={{ paddingBottom: 12 }}
            >
              <Feather name="trash-2" size={18} color={colors.destructive} />
            </Pressable>
          </View>
        ))}
        <Button
          label="Add phone"
          variant="secondary"
          leading={<Feather name="plus" size={16} color={colors.foreground} />}
          onPress={() => setPhones((prev) => [...prev, { label: "", value: "" }])}
        />

        <Text style={[styles.editorSection, { color: colors.foreground }]}>Emails</Text>
        {emails.map((e, i) => (
          <View key={`em${i}`} style={styles.editorPairRow}>
            <View style={{ width: 96 }}>
              <TextField
                label="Label"
                value={e.label}
                onChangeText={(t) =>
                  setEmails((prev) =>
                    prev.map((x, xi) => (xi === i ? { ...x, label: t } : x)),
                  )
                }
              />
            </View>
            <View style={{ flex: 1 }}>
              <TextField
                label="Email"
                keyboardType="email-address"
                autoCapitalize="none"
                value={e.value}
                onChangeText={(t) =>
                  setEmails((prev) =>
                    prev.map((x, xi) => (xi === i ? { ...x, value: t } : x)),
                  )
                }
              />
            </View>
            <Pressable
              onPress={() => setEmails((prev) => prev.filter((_, xi) => xi !== i))}
              hitSlop={8}
              style={{ paddingBottom: 12 }}
            >
              <Feather name="trash-2" size={18} color={colors.destructive} />
            </Pressable>
          </View>
        ))}
        <Button
          label="Add email"
          variant="secondary"
          leading={<Feather name="plus" size={16} color={colors.foreground} />}
          onPress={() => setEmails((prev) => [...prev, { label: "", value: "" }])}
        />

        <Button label="Save changes" onPress={submit} loading={busy} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: "row", alignItems: "center", gap: 8 },
  title: { fontSize: 16, fontWeight: "700" },
  btnText: { color: "#fff", fontWeight: "700", fontSize: 15 },
  notice: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    padding: 8,
    borderRadius: 8,
    marginTop: 8,
  },
  rowHead: { flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 4 },
  editorHead: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  editorSection: { fontWeight: "700", fontSize: 14, marginTop: 8 },
  editorPairRow: { flexDirection: "row", alignItems: "flex-end", gap: 8 },
  bar: {
    height: 8,
    borderRadius: 999,
    marginTop: 12,
    overflow: "hidden",
  },
  pager: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 16,
    marginTop: 8,
  },
  footer: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: "row",
    gap: 10,
    padding: 16,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
});
