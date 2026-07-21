import { Feather } from "@expo/vector-icons";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  createNote,
  deleteNote,
  listNotes,
  updateNote,
  type DialerNote,
  type NoteInput,
} from "@/lib/api/notes";

// RN-web Alert.alert is a no-op; confirmations need a window.confirm branch.
function confirmAsync(title: string, message: string): Promise<boolean> {
  if (Platform.OS === "web") {
    return Promise.resolve(
      typeof window !== "undefined" && window.confirm(`${title}\n\n${message}`),
    );
  }
  return new Promise((resolve) => {
    Alert.alert(title, message, [
      { text: "Cancel", style: "cancel", onPress: () => resolve(false) },
      { text: "Delete", style: "destructive", onPress: () => resolve(true) },
    ]);
  });
}

type EditorState = {
  id: number | null;
  title: string;
  body: string;
  number: string;
  remindAt: string;
  done: boolean;
  sharePhones: string;
};

const EMPTY_EDITOR: EditorState = {
  id: null,
  title: "",
  body: "",
  number: "",
  remindAt: "",
  done: false,
  sharePhones: "",
};

function formatWhen(iso: string | null): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  return d.toLocaleString();
}

export default function NotesScreen() {
  const colors = useColors();
  const [notes, setNotes] = useState<DialerNote[]>([]);
  const [shared, setShared] = useState<DialerNote[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [editorOpen, setEditorOpen] = useState(false);
  const [editor, setEditor] = useState<EditorState>(EMPTY_EDITOR);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await listNotes();
      setNotes(res.notes);
      setShared(res.shared);
      setError(null);
    } catch {
      setError("Couldn't load your notes. Pull down to retry.");
    }
  }, []);

  useEffect(() => {
    void load().finally(() => setLoading(false));
  }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    try {
      await load();
    } finally {
      setRefreshing(false);
    }
  }, [load]);

  const openCreate = () => {
    setEditor(EMPTY_EDITOR);
    setSaveError(null);
    setEditorOpen(true);
  };

  const openEdit = (n: DialerNote) => {
    setEditor({
      id: n.id,
      title: n.title ?? "",
      body: n.body ?? "",
      number: n.number ?? "",
      remindAt: n.remind_at ?? "",
      done: n.done,
      sharePhones: n.share_phones.join(", "),
    });
    setSaveError(null);
    setEditorOpen(true);
  };

  const save = async () => {
    if (saving) return;
    const input: NoteInput = {
      title: editor.title.trim() || null,
      body: editor.body.trim() || null,
      number: editor.number.trim() || null,
      remind_at: editor.remindAt.trim() || null,
      done: editor.done,
      share_phones: editor.sharePhones
        .split(/[,\n]/)
        .map((p) => p.trim())
        .filter(Boolean),
    };
    if (!input.title && !input.body) {
      setSaveError("Add a title or some text first.");
      return;
    }
    setSaving(true);
    setSaveError(null);
    try {
      if (editor.id === null) {
        const created = await createNote(input);
        setNotes((prev) => [created, ...prev]);
      } else {
        const updated = await updateNote(editor.id, input);
        setNotes((prev) => prev.map((n) => (n.id === updated.id ? updated : n)));
      }
      setEditorOpen(false);
    } catch (e) {
      const msg =
        e instanceof Error && e.message
          ? e.message
          : "Couldn't save. Check the reminder date format (e.g. 2026-07-25 18:00) and try again.";
      setSaveError(msg);
    } finally {
      setSaving(false);
    }
  };

  const toggleDone = async (n: DialerNote) => {
    // Optimistic flip; revert on failure.
    setNotes((prev) =>
      prev.map((x) => (x.id === n.id ? { ...x, done: !n.done } : x)),
    );
    try {
      await updateNote(n.id, { done: !n.done });
    } catch {
      setNotes((prev) =>
        prev.map((x) => (x.id === n.id ? { ...x, done: n.done } : x)),
      );
    }
  };

  const remove = async (n: DialerNote) => {
    const ok = await confirmAsync(
      "Delete note?",
      "This removes the note for you and anyone it's shared with.",
    );
    if (!ok) return;
    setNotes((prev) => prev.filter((x) => x.id !== n.id));
    try {
      await deleteNote(n.id);
    } catch {
      void load();
    }
  };

  type Row =
    | { type: "header"; key: string; label: string }
    | { type: "note"; key: string; note: DialerNote };

  const rows = useMemo<Row[]>(() => {
    const out: Row[] = [];
    const reminders = notes.filter((n) => n.remind_at && !n.done);
    const rest = notes.filter((n) => !(n.remind_at && !n.done));
    if (reminders.length > 0) {
      out.push({ type: "header", key: "h:rem", label: "Reminders" });
      const sorted = [...reminders].sort(
        (a, b) =>
          new Date(a.remind_at ?? 0).getTime() -
          new Date(b.remind_at ?? 0).getTime(),
      );
      for (const n of sorted) out.push({ type: "note", key: `n:${n.id}`, note: n });
    }
    if (rest.length > 0) {
      out.push({ type: "header", key: "h:notes", label: "Notes" });
      for (const n of rest) out.push({ type: "note", key: `n:${n.id}`, note: n });
    }
    if (shared.length > 0) {
      out.push({ type: "header", key: "h:shared", label: "Shared with me" });
      for (const n of shared)
        out.push({ type: "note", key: `s:${n.id}`, note: n });
    }
    return out;
  }, [notes, shared]);

  const renderNote = (n: DialerNote) => {
    const when = formatWhen(n.remind_at);
    const overdue =
      !!n.remind_at && !n.done && new Date(n.remind_at).getTime() < Date.now();
    return (
      <Pressable
        onPress={() => (n.own ? openEdit(n) : undefined)}
        style={[
          styles.card,
          {
            backgroundColor: colors.card,
            borderColor: colors.border,
            opacity: n.done ? 0.6 : 1,
          },
        ]}
      >
        <View style={styles.cardTopRow}>
          {n.own ? (
            <Pressable hitSlop={8} onPress={() => toggleDone(n)}>
              <Feather
                name={n.done ? "check-circle" : "circle"}
                size={20}
                color={n.done ? colors.primary : colors.mutedForeground}
              />
            </Pressable>
          ) : (
            <Feather name="users" size={18} color={colors.mutedForeground} />
          )}
          <View style={{ flex: 1 }}>
            {n.title ? (
              <Text
                style={{
                  color: colors.foreground,
                  fontSize: 15,
                  fontWeight: "700",
                  textDecorationLine: n.done ? "line-through" : "none",
                }}
              >
                {n.title}
              </Text>
            ) : null}
            {n.body ? (
              <Text
                numberOfLines={3}
                style={{ color: colors.mutedForeground, fontSize: 13, marginTop: 2 }}
              >
                {n.body}
              </Text>
            ) : null}
          </View>
          {n.own ? (
            <Pressable hitSlop={8} onPress={() => remove(n)}>
              <Feather name="trash-2" size={16} color={colors.mutedForeground} />
            </Pressable>
          ) : null}
        </View>
        <View style={styles.metaRow}>
          {when ? (
            <View style={styles.metaItem}>
              <Feather
                name="bell"
                size={12}
                color={overdue ? "#e5484d" : colors.mutedForeground}
              />
              <Text
                style={{
                  color: overdue ? "#e5484d" : colors.mutedForeground,
                  fontSize: 12,
                  fontWeight: overdue ? "700" : "400",
                }}
              >
                {when}
              </Text>
            </View>
          ) : null}
          {n.number ? (
            <View style={styles.metaItem}>
              <Feather name="phone" size={12} color={colors.mutedForeground} />
              <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                {n.number}
              </Text>
            </View>
          ) : null}
          {!n.own && n.owner_name ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
              From {n.owner_name}
            </Text>
          ) : null}
          {n.own && n.share_phones.length > 0 ? (
            <View style={styles.metaItem}>
              <Feather name="share-2" size={12} color={colors.mutedForeground} />
              <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                Shared with {n.share_phones.length}
              </Text>
            </View>
          ) : null}
        </View>
      </Pressable>
    );
  };

  return (
    <View style={[styles.wrap, { backgroundColor: colors.background }]}>
      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={colors.primary} />
      ) : rows.length === 0 ? (
        <ScrollView
          contentContainerStyle={{ flexGrow: 1 }}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }
        >
          <EmptyState
            icon="edit-3"
            title={error ? "Something went wrong" : "No notes yet"}
            body={
              error ??
              "Jot down notes and reminders — they sync to your Sayzio account, and you can share them with other numbers."
            }
          />
        </ScrollView>
      ) : (
        <FlatList
          data={rows}
          keyExtractor={(r) => r.key}
          contentContainerStyle={{ padding: 16, paddingBottom: 96, gap: 10 }}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }
          renderItem={({ item }) =>
            item.type === "header" ? (
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 12,
                  fontWeight: "700",
                  textTransform: "uppercase",
                  letterSpacing: 0.6,
                  marginTop: 6,
                }}
              >
                {item.label}
              </Text>
            ) : (
              renderNote(item.note)
            )
          }
        />
      )}

      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Add note"
        onPress={openCreate}
        style={[styles.fab, { backgroundColor: colors.primary }]}
      >
        <Feather name="plus" size={24} color={colors.primaryForeground} />
      </Pressable>

      <Modal
        visible={editorOpen}
        animationType="slide"
        transparent
        onRequestClose={() => setEditorOpen(false)}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          style={styles.modalWrap}
        >
          <Pressable style={styles.modalBackdrop} onPress={() => setEditorOpen(false)} />
          <View
            style={[
              styles.sheet,
              { backgroundColor: colors.background, borderColor: colors.border },
            ]}
          >
            <ScrollView keyboardShouldPersistTaps="handled">
              <Text style={[styles.sheetTitle, { color: colors.foreground }]}>
                {editor.id === null ? "New note" : "Edit note"}
              </Text>

              <TextInput
                value={editor.title}
                onChangeText={(t) => setEditor((e) => ({ ...e, title: t }))}
                placeholder="Title"
                placeholderTextColor={colors.mutedForeground}
                style={[
                  styles.input,
                  { color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card },
                ]}
              />
              <TextInput
                value={editor.body}
                onChangeText={(t) => setEditor((e) => ({ ...e, body: t }))}
                placeholder="Write your note…"
                placeholderTextColor={colors.mutedForeground}
                multiline
                style={[
                  styles.input,
                  styles.inputMultiline,
                  { color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card },
                ]}
              />
              <TextInput
                value={editor.number}
                onChangeText={(t) => setEditor((e) => ({ ...e, number: t }))}
                placeholder="Related phone number (optional)"
                placeholderTextColor={colors.mutedForeground}
                keyboardType="phone-pad"
                style={[
                  styles.input,
                  { color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card },
                ]}
              />
              <TextInput
                value={editor.remindAt}
                onChangeText={(t) => setEditor((e) => ({ ...e, remindAt: t }))}
                placeholder="Remind me at… e.g. 2026-07-25 18:00 (optional)"
                placeholderTextColor={colors.mutedForeground}
                autoCapitalize="none"
                style={[
                  styles.input,
                  { color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card },
                ]}
              />
              <TextInput
                value={editor.sharePhones}
                onChangeText={(t) => setEditor((e) => ({ ...e, sharePhones: t }))}
                placeholder="Share with phone numbers, comma-separated (optional)"
                placeholderTextColor={colors.mutedForeground}
                autoCapitalize="none"
                keyboardType={Platform.OS === "web" ? undefined : "phone-pad"}
                style={[
                  styles.input,
                  { color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card },
                ]}
              />
              <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 4 }}>
                Numbers on Sayzio see shared notes in their own Notes tab.
              </Text>

              {editor.id !== null ? (
                <View style={styles.doneRow}>
                  <Text style={{ color: colors.foreground, fontSize: 14 }}>Done</Text>
                  <Switch
                    value={editor.done}
                    onValueChange={(v) => setEditor((e) => ({ ...e, done: v }))}
                    trackColor={{ true: colors.primary, false: colors.border }}
                  />
                </View>
              ) : null}

              {saveError ? (
                <Text style={{ color: "#e5484d", fontSize: 13, marginTop: 8 }}>
                  {saveError}
                </Text>
              ) : null}

              <View style={styles.sheetActions}>
                <Pressable
                  onPress={() => setEditorOpen(false)}
                  style={[styles.btn, { borderColor: colors.border }]}
                >
                  <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                    Cancel
                  </Text>
                </Pressable>
                <Pressable
                  onPress={() => void save()}
                  disabled={saving}
                  style={[
                    styles.btn,
                    {
                      backgroundColor: colors.primary,
                      borderColor: colors.primary,
                      opacity: saving ? 0.7 : 1,
                    },
                  ]}
                >
                  {saving ? (
                    <ActivityIndicator size="small" color={colors.primaryForeground} />
                  ) : (
                    <Text style={{ color: colors.primaryForeground, fontWeight: "700" }}>
                      Save
                    </Text>
                  )}
                </Pressable>
              </View>
            </ScrollView>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1 },
  card: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 12,
    gap: 8,
  },
  cardTopRow: { flexDirection: "row", alignItems: "flex-start", gap: 10 },
  metaRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 12,
    alignItems: "center",
  },
  metaItem: { flexDirection: "row", alignItems: "center", gap: 4 },
  fab: {
    position: "absolute",
    right: 20,
    bottom: 28,
    width: 56,
    height: 56,
    borderRadius: 28,
    alignItems: "center",
    justifyContent: "center",
    shadowColor: "#000",
    shadowOpacity: 0.25,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 4 },
    elevation: 5,
  },
  modalWrap: { flex: 1, justifyContent: "flex-end" },
  modalBackdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(0,0,0,0.45)",
  },
  sheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: 1,
    padding: 20,
    maxHeight: "88%",
  },
  sheetTitle: { fontSize: 18, fontWeight: "700", marginBottom: 12 },
  input: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 15,
    marginTop: 10,
  },
  inputMultiline: { minHeight: 90, textAlignVertical: "top" },
  doneRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginTop: 14,
  },
  sheetActions: {
    flexDirection: "row",
    justifyContent: "flex-end",
    gap: 10,
    marginTop: 18,
    marginBottom: 8,
  },
  btn: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 18,
    paddingVertical: 10,
    minWidth: 90,
    alignItems: "center",
  },
});
