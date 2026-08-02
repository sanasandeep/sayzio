import { Feather } from "@expo/vector-icons";
import { router, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  KeyboardAvoidingView,
  Linking,
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
  type ChecklistItem,
  type DialerNote,
  type NoteInput,
} from "@/lib/api/notes";
import { cancelNoteAlarm, syncNoteAlarm } from "@/lib/localReminders";

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
  kind: "note" | "checklist";
  title: string;
  body: string;
  checklist: ChecklistItem[];
  number: string;
  remindAt: string;
  done: boolean;
  sharePhones: string;
  attachedUrl: string | null;
  attachedTitle: string | null;
};

const EMPTY_EDITOR: EditorState = {
  id: null,
  kind: "note",
  title: "",
  body: "",
  checklist: [],
  number: "",
  remindAt: "",
  done: false,
  sharePhones: "",
  attachedUrl: null,
  attachedTitle: null,
};

/** Host shown for an attached website (www-stripped; falls back to the URL). */
function attachmentHost(url: string): string {
  try {
    return new URL(url).hostname.replace(/^www\./, "");
  } catch {
    return url;
  }
}

function formatWhen(iso: string | null): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  return d.toLocaleString();
}

function noteAlarmTitle(n: DialerNote): string {
  return n.title || (n.kind === "checklist" ? "To-do reminder" : "Note reminder");
}

function sourceLabel(sourceType: string | null): string | null {
  if (sourceType === "event") return "Auto · Event";
  if (sourceType === "callback") return "Auto · Call-back";
  return sourceType ? "Auto" : null;
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

  // Reminder deep-link (notification tap → this tab with ?noteId=…).
  // `openedAt` changes per tap so re-tapping the same reminder re-fires
  // the effect even when noteId is unchanged.
  const params = useLocalSearchParams<{ noteId?: string; openedAt?: string }>();
  const [highlightId, setHighlightId] = useState<number | null>(null);
  const listRef = useRef<FlatList | null>(null);
  const handledDeepLink = useRef<string | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await listNotes();
      setNotes(res.notes);
      setShared(res.shared);
      setError(null);
      // Keep local alarms mirrored to the server state (own, not-done,
      // future reminders). Best-effort — never blocks the UI.
      for (const n of res.notes) {
        if (n.remind_at && !n.done) {
          void syncNoteAlarm(n.id, n.remind_at, noteAlarmTitle(n), n.body);
        } else {
          void cancelNoteAlarm(n.id);
        }
      }
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

  const openEdit = useCallback((n: DialerNote) => {
    setEditor({
      id: n.id,
      kind: n.kind === "checklist" ? "checklist" : "note",
      title: n.title ?? "",
      body: n.body ?? "",
      checklist: (n.checklist ?? []).map((i) => ({
        text: i.text ?? "",
        done: !!i.done,
      })),
      number: n.number ?? "",
      remindAt: n.remind_at ?? "",
      done: n.done,
      sharePhones: n.share_phones.join(", "),
      attachedUrl: n.attached_url ?? null,
      attachedTitle: n.attached_title ?? null,
    });
    setSaveError(null);
    setEditorOpen(true);
  }, []);

  const setEditorKind = (kind: "note" | "checklist") => {
    setEditor((e) => ({
      ...e,
      kind,
      checklist:
        kind === "checklist" && e.checklist.length === 0
          ? [{ text: "", done: false }]
          : e.checklist,
    }));
  };

  const addChecklistItem = () => {
    setEditor((e) => ({
      ...e,
      checklist: [...e.checklist, { text: "", done: false }],
    }));
  };

  const save = async () => {
    if (saving) return;
    const checklist = editor.checklist.filter((i) => i.text.trim() !== "");
    const input: NoteInput = {
      kind: editor.kind,
      title: editor.title.trim() || null,
      body: editor.kind === "note" ? editor.body.trim() || null : null,
      checklist: editor.kind === "checklist" ? checklist : null,
      number: editor.number.trim() || null,
      remind_at: editor.remindAt.trim() || null,
      done: editor.done,
      attached_url: editor.attachedUrl,
      attached_title: editor.attachedUrl ? editor.attachedTitle : null,
      share_phones: editor.sharePhones
        .split(/[,\n]/)
        .map((p) => p.trim())
        .filter(Boolean),
    };
    if (
      !input.title &&
      !input.body &&
      (editor.kind !== "checklist" || checklist.length === 0)
    ) {
      setSaveError(
        editor.kind === "checklist"
          ? "Add a title or at least one to-do item first."
          : "Add a title or some text first.",
      );
      return;
    }
    setSaving(true);
    setSaveError(null);
    try {
      let saved: DialerNote;
      if (editor.id === null) {
        saved = await createNote(input);
        setNotes((prev) => [saved, ...prev]);
      } else {
        saved = await updateNote(editor.id, input);
        setNotes((prev) => prev.map((n) => (n.id === saved.id ? saved : n)));
      }
      // Mirror the reminder to a local scheduled alarm so it fires even
      // offline. Done / cleared reminders drop the alarm.
      if (saved.remind_at && !saved.done) {
        void syncNoteAlarm(saved.id, saved.remind_at, noteAlarmTitle(saved), saved.body);
      } else {
        void cancelNoteAlarm(saved.id);
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
      if (!n.done) void cancelNoteAlarm(n.id);
      else if (n.remind_at)
        void syncNoteAlarm(n.id, n.remind_at, noteAlarmTitle(n), n.body);
    } catch {
      setNotes((prev) =>
        prev.map((x) => (x.id === n.id ? { ...x, done: n.done } : x)),
      );
    }
  };

  const toggleChecklistItem = async (n: DialerNote, idx: number) => {
    const next = (n.checklist ?? []).map((i, j) =>
      j === idx ? { ...i, done: !i.done } : i,
    );
    // Optimistic; revert on failure.
    setNotes((prev) =>
      prev.map((x) => (x.id === n.id ? { ...x, checklist: next } : x)),
    );
    try {
      await updateNote(n.id, { checklist: next });
    } catch {
      setNotes((prev) =>
        prev.map((x) => (x.id === n.id ? { ...x, checklist: n.checklist } : x)),
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
    void cancelNoteAlarm(n.id);
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
    const autoTasks = notes.filter((n) => n.source_type && !n.done);
    const reminders = notes.filter(
      (n) => !n.source_type && n.remind_at && !n.done,
    );
    const todos = notes.filter(
      (n) => !n.source_type && n.kind === "checklist" && !(n.remind_at && !n.done),
    );
    const rest = notes.filter(
      (n) =>
        !n.source_type &&
        n.kind !== "checklist" &&
        !(n.remind_at && !n.done),
    );
    const byRemind = (a: DialerNote, b: DialerNote) =>
      new Date(a.remind_at ?? 0).getTime() - new Date(b.remind_at ?? 0).getTime();

    if (autoTasks.length > 0) {
      out.push({ type: "header", key: "h:auto", label: "Up next" });
      for (const n of [...autoTasks].sort(byRemind))
        out.push({ type: "note", key: `n:${n.id}`, note: n });
    }
    if (reminders.length > 0) {
      out.push({ type: "header", key: "h:rem", label: "Reminders" });
      for (const n of [...reminders].sort(byRemind))
        out.push({ type: "note", key: `n:${n.id}`, note: n });
    }
    if (todos.length > 0) {
      out.push({ type: "header", key: "h:todo", label: "To-do lists" });
      for (const n of todos) out.push({ type: "note", key: `n:${n.id}`, note: n });
    }
    if (rest.length > 0) {
      out.push({ type: "header", key: "h:notes", label: "Notes" });
      for (const n of rest) out.push({ type: "note", key: `n:${n.id}`, note: n });
    }
    const doneAuto = notes.filter((n) => n.source_type && n.done);
    if (doneAuto.length > 0) {
      out.push({ type: "header", key: "h:auto-done", label: "Done tasks" });
      for (const n of doneAuto)
        out.push({ type: "note", key: `n:${n.id}`, note: n });
    }
    if (shared.length > 0) {
      out.push({ type: "header", key: "h:shared", label: "Shared with me" });
      for (const n of shared)
        out.push({ type: "note", key: `s:${n.id}`, note: n });
    }
    return out;
  }, [notes, shared]);

  // Reminder deep-link: a tapped notification lands here with ?noteId=….
  // Own notes open the editor sheet directly (mark done / snooze / edit in
  // one tap); shared/foreign notes just scroll into view + flash-highlight.
  useEffect(() => {
    const rawNoteId = typeof params.noteId === "string" ? params.noteId : null;
    if (!rawNoteId || loading) return;
    const tapKey = `${rawNoteId}:${typeof params.openedAt === "string" ? params.openedAt : ""}`;
    if (handledDeepLink.current === tapKey) return;
    const noteId = Number(rawNoteId);
    if (!Number.isFinite(noteId)) return;

    const own = notes.find((n) => n.id === noteId);
    const foreign = shared.find((n) => n.id === noteId);
    if (!own && !foreign) return; // list may still be refreshing — retry on next load
    handledDeepLink.current = tapKey;

    // Consume the param so backing out / re-rendering doesn't re-trigger.
    router.setParams({ noteId: undefined, openedAt: undefined });

    const idx = rows.findIndex(
      (r) => r.type === "note" && r.note.id === noteId,
    );
    if (idx >= 0) {
      listRef.current?.scrollToIndex({
        index: idx,
        viewPosition: 0.3,
        animated: true,
      });
    }
    setHighlightId(noteId);
    const timer = setTimeout(() => setHighlightId(null), 2400);

    if (own) openEdit(own);
    return () => clearTimeout(timer);
  }, [params.noteId, params.openedAt, loading, notes, shared, rows, openEdit]);

  const renderNote = (n: DialerNote) => {
    const highlighted = highlightId === n.id;
    const when = formatWhen(n.remind_at);
    const overdue =
      !!n.remind_at && !n.done && new Date(n.remind_at).getTime() < Date.now();
    const badge = sourceLabel(n.source_type);
    const items = n.kind === "checklist" ? (n.checklist ?? []) : [];
    const doneCount = items.filter((i) => i.done).length;
    return (
      <Pressable
        onPress={() => (n.own ? openEdit(n) : undefined)}
        style={[
          styles.card,
          {
            backgroundColor: highlighted ? `${colors.primary}18` : colors.card,
            borderColor: highlighted ? colors.primary : colors.border,
            borderLeftColor: highlighted
              ? colors.primary
              : (n.color ?? colors.border),
            borderLeftWidth: n.color || highlighted ? 3 : 1,
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
            <View style={styles.titleRow}>
              {n.title ? (
                <Text
                  style={{
                    color: colors.foreground,
                    fontSize: 15,
                    fontWeight: "700",
                    textDecorationLine: n.done ? "line-through" : "none",
                    flexShrink: 1,
                  }}
                >
                  {n.title}
                </Text>
              ) : n.kind === "checklist" ? (
                <Text
                  style={{ color: colors.foreground, fontSize: 15, fontWeight: "700" }}
                >
                  To-do list
                </Text>
              ) : null}
              {badge ? (
                <View
                  style={[styles.badge, { backgroundColor: `${colors.primary}22` }]}
                >
                  <Text
                    style={{ color: colors.primary, fontSize: 10, fontWeight: "700" }}
                  >
                    {badge}
                  </Text>
                </View>
              ) : null}
            </View>
            {n.kind === "note" && n.body ? (
              <Text
                numberOfLines={3}
                style={{ color: colors.mutedForeground, fontSize: 13, marginTop: 2 }}
              >
                {n.body}
              </Text>
            ) : null}
            {n.kind === "checklist" && items.length > 0 ? (
              <View style={{ marginTop: 6, gap: 4 }}>
                {items.map((item, idx) => (
                  <Pressable
                    key={idx}
                    hitSlop={4}
                    disabled={!n.own}
                    onPress={() => void toggleChecklistItem(n, idx)}
                    style={styles.checkItemRow}
                  >
                    <Feather
                      name={item.done ? "check-square" : "square"}
                      size={15}
                      color={item.done ? colors.primary : colors.mutedForeground}
                    />
                    <Text
                      style={{
                        color: item.done ? colors.mutedForeground : colors.foreground,
                        fontSize: 13,
                        textDecorationLine: item.done ? "line-through" : "none",
                        flexShrink: 1,
                      }}
                    >
                      {item.text}
                    </Text>
                  </Pressable>
                ))}
                <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                  {doneCount}/{items.length} done
                </Text>
              </View>
            ) : null}
          </View>
          {n.own ? (
            <Pressable hitSlop={8} onPress={() => remove(n)}>
              <Feather name="trash-2" size={16} color={colors.mutedForeground} />
            </Pressable>
          ) : null}
        </View>
        {n.attached_url ? (
          <Pressable
            onPress={() => void Linking.openURL(n.attached_url as string)}
            style={[
              styles.attachChip,
              { borderColor: colors.border, backgroundColor: colors.card },
            ]}
            accessibilityRole="link"
            accessibilityLabel={`Open attached website ${n.attached_title || attachmentHost(n.attached_url)}`}
          >
            <Feather name="globe" size={12} color={colors.primary} />
            <Text
              numberOfLines={1}
              style={{ color: colors.primary, fontSize: 12, fontWeight: "600", flexShrink: 1 }}
            >
              {n.attached_title || attachmentHost(n.attached_url)}
            </Text>
            <Text numberOfLines={1} style={{ color: colors.mutedForeground, fontSize: 11, flexShrink: 1 }}>
              {attachmentHost(n.attached_url)}
            </Text>
          </Pressable>
        ) : null}
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
              "Jot down notes, to-do lists and reminders — they sync to your Sayzio account, and events you RSVP to show up as tasks automatically."
            }
          />
        </ScrollView>
      ) : (
        <FlatList
          ref={listRef}
          data={rows}
          keyExtractor={(r) => r.key}
          onScrollToIndexFailed={({ index, averageItemLength }) => {
            // Rows aren't measured yet — approximate, then retry once.
            listRef.current?.scrollToOffset({
              offset: index * (averageItemLength || 90),
              animated: true,
            });
            setTimeout(() => {
              listRef.current?.scrollToIndex({
                index,
                viewPosition: 0.3,
                animated: true,
              });
            }, 250);
          }}
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
              <View style={styles.sheetHeader}>
                <Text style={[styles.sheetTitle, { color: colors.foreground }]}>
                  {editor.id === null ? "New" : "Edit"}
                </Text>
                <View style={[styles.kindToggle, { borderColor: colors.border }]}>
                  {(["note", "checklist"] as const).map((k) => (
                    <Pressable
                      key={k}
                      onPress={() => setEditorKind(k)}
                      style={[
                        styles.kindBtn,
                        editor.kind === k && { backgroundColor: colors.primary },
                      ]}
                    >
                      <Text
                        style={{
                          color:
                            editor.kind === k
                              ? colors.primaryForeground
                              : colors.mutedForeground,
                          fontSize: 12,
                          fontWeight: "700",
                        }}
                      >
                        {k === "note" ? "Note" : "To-do list"}
                      </Text>
                    </Pressable>
                  ))}
                </View>
              </View>

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
              {editor.kind === "note" ? (
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
              ) : (
                <View style={{ marginTop: 10, gap: 8 }}>
                  {editor.checklist.map((item, idx) => (
                    <View key={idx} style={styles.checkEditRow}>
                      <Pressable
                        hitSlop={8}
                        onPress={() =>
                          setEditor((e) => ({
                            ...e,
                            checklist: e.checklist.map((i, j) =>
                              j === idx ? { ...i, done: !i.done } : i,
                            ),
                          }))
                        }
                      >
                        <Feather
                          name={item.done ? "check-square" : "square"}
                          size={18}
                          color={item.done ? colors.primary : colors.mutedForeground}
                        />
                      </Pressable>
                      <TextInput
                        value={item.text}
                        onChangeText={(t) =>
                          setEditor((e) => ({
                            ...e,
                            checklist: e.checklist.map((i, j) =>
                              j === idx ? { ...i, text: t } : i,
                            ),
                          }))
                        }
                        placeholder="To-do item…"
                        placeholderTextColor={colors.mutedForeground}
                        onSubmitEditing={addChecklistItem}
                        style={[
                          styles.input,
                          {
                            flex: 1,
                            marginTop: 0,
                            color: colors.foreground,
                            borderColor: colors.border,
                            backgroundColor: colors.card,
                          },
                        ]}
                      />
                      <Pressable
                        hitSlop={8}
                        onPress={() =>
                          setEditor((e) => ({
                            ...e,
                            checklist: e.checklist.filter((_, j) => j !== idx),
                          }))
                        }
                      >
                        <Feather name="x" size={16} color={colors.mutedForeground} />
                      </Pressable>
                    </View>
                  ))}
                  <Pressable
                    onPress={addChecklistItem}
                    style={styles.addItemBtn}
                    accessibilityRole="button"
                    accessibilityLabel="Add to-do item"
                  >
                    <Feather name="plus" size={14} color={colors.primary} />
                    <Text style={{ color: colors.primary, fontSize: 13, fontWeight: "600" }}>
                      Add item
                    </Text>
                  </Pressable>
                </View>
              )}
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
              <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 4 }}>
                Reminders alert you here (even offline) and on the web.
              </Text>
              {editor.attachedUrl ? (
                <View
                  style={[
                    styles.attachEditorRow,
                    { borderColor: colors.border, backgroundColor: colors.card },
                  ]}
                >
                  <Feather name="globe" size={14} color={colors.primary} />
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text numberOfLines={1} style={{ color: colors.foreground, fontSize: 13, fontWeight: "600" }}>
                      {editor.attachedTitle || attachmentHost(editor.attachedUrl)}
                    </Text>
                    <Text numberOfLines={1} style={{ color: colors.mutedForeground, fontSize: 11 }}>
                      {editor.attachedUrl}
                    </Text>
                  </View>
                  <Pressable
                    hitSlop={8}
                    onPress={() =>
                      setEditor((e) => ({ ...e, attachedUrl: null, attachedTitle: null }))
                    }
                    accessibilityRole="button"
                    accessibilityLabel="Remove attached website"
                  >
                    <Feather name="x" size={16} color={colors.mutedForeground} />
                  </Pressable>
                </View>
              ) : null}
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
                Numbers on Sayzio see shared notes and get the reminder too.
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
  titleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    flexWrap: "wrap",
  },
  badge: {
    borderRadius: 999,
    paddingHorizontal: 8,
    paddingVertical: 2,
  },
  checkItemRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  checkEditRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  addItemBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingVertical: 4,
  },
  metaRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 12,
    alignItems: "center",
  },
  metaItem: { flexDirection: "row", alignItems: "center", gap: 4 },
  attachChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    alignSelf: "flex-start",
    maxWidth: "100%",
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 4,
    marginBottom: 8,
  },
  attachEditorRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
    marginTop: 10,
  },
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
  sheetHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 12,
  },
  sheetTitle: { fontSize: 18, fontWeight: "700" },
  kindToggle: {
    flexDirection: "row",
    borderWidth: 1,
    borderRadius: 10,
    overflow: "hidden",
  },
  kindBtn: { paddingHorizontal: 12, paddingVertical: 6 },
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
