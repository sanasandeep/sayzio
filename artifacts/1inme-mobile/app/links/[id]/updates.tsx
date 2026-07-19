import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Modal,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  createUpdateEntry,
  deleteUpdateEntry,
  ENTRY_TAG_LABELS,
  ENTRY_TAGS,
  listOwnerEntries,
  updateUpdateEntry,
  type CreateEntryInput,
  type UpdateEntry,
  type UpdateEntryInput,
} from "@/lib/api/updates";
import { showAlert } from "@/lib/webAlert";

type Colors = ReturnType<typeof useColors>;

function tagColor(tag: string | null, colors: Colors): string {
  switch (tag) {
    case "feature":
      return colors.primary;
    case "fix":
      return colors.destructive;
    case "improvement":
      return colors.success;
    case "breaking":
      return "#f59e0b";
    case "announcement":
      return "#8b5cf6";
    default:
      return colors.mutedForeground;
  }
}

function statusBadge(status: string, colors: Colors) {
  const isDraft = status === "draft";
  return (
    <View
      style={{
        paddingHorizontal: 8,
        paddingVertical: 2,
        borderRadius: 6,
        backgroundColor: isDraft ? colors.muted : colors.success + "33",
      }}
    >
      <Text
        style={{
          fontSize: 11,
          fontWeight: "600",
          color: isDraft ? colors.mutedForeground : colors.success,
        }}
      >
        {isDraft ? "Draft" : "Published"}
      </Text>
    </View>
  );
}

function EntryRow({
  entry,
  colors,
  onEdit,
  onDelete,
}: {
  entry: UpdateEntry;
  colors: Colors;
  onEdit: (e: UpdateEntry) => void;
  onDelete: (e: UpdateEntry) => void;
}) {
  return (
    <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
      <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 8 }}>
        <View style={{ flex: 1 }}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6, marginBottom: 4 }}>
            {statusBadge(entry.status, colors)}
            {entry.tag ? (
              <View
                style={{
                  paddingHorizontal: 8,
                  paddingVertical: 2,
                  borderRadius: 6,
                  backgroundColor: tagColor(entry.tag, colors) + "22",
                }}
              >
                <Text style={{ fontSize: 11, fontWeight: "600", color: tagColor(entry.tag, colors) }}>
                  {ENTRY_TAG_LABELS[entry.tag] ?? entry.tag}
                </Text>
              </View>
            ) : null}
          </View>
          <Text style={{ fontSize: 15, fontWeight: "600", color: colors.foreground }} numberOfLines={2}>
            {entry.title}
          </Text>
          {entry.published_date ? (
            <Text style={{ fontSize: 12, color: colors.mutedForeground, marginTop: 2 }}>
              {entry.published_date}
            </Text>
          ) : null}
          {entry.body ? (
            <Text style={{ fontSize: 13, color: colors.mutedForeground, marginTop: 4 }} numberOfLines={3}>
              {entry.body}
            </Text>
          ) : null}
        </View>
        <View style={{ gap: 8 }}>
          <Pressable onPress={() => onEdit(entry)} hitSlop={8}>
            <Feather name="edit-2" size={18} color={colors.primary} />
          </Pressable>
          <Pressable onPress={() => onDelete(entry)} hitSlop={8}>
            <Feather name="trash-2" size={18} color={colors.destructive} />
          </Pressable>
        </View>
      </View>
    </View>
  );
}

function EntryModal({
  visible,
  entry,
  colors,
  onClose,
  onSave,
}: {
  visible: boolean;
  entry: UpdateEntry | null;
  colors: Colors;
  onClose: () => void;
  onSave: (input: CreateEntryInput | UpdateEntryInput) => void;
}) {
  const isNew = entry === null;
  const [title, setTitle] = useState(entry?.title ?? "");
  const [body, setBody] = useState(entry?.body ?? "");
  const [tag, setTag] = useState<string | null>(entry?.tag ?? null);
  const [publishedDate, setPublishedDate] = useState(
    entry?.published_date ?? new Date().toISOString().slice(0, 10),
  );
  const [status, setStatus] = useState<"draft" | "published">(entry?.status ?? "draft");

  const handleSave = () => {
    if (!title.trim()) {
      showAlert("Validation", "Title is required.", [{ text: "OK" }]);
      return;
    }
    onSave({
      title: title.trim(),
      body: body.trim() || null,
      tag: tag || null,
      published_date: publishedDate,
      status,
    });
  };

  return (
    <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            paddingHorizontal: 16,
            paddingVertical: 14,
            borderBottomWidth: 1,
            borderBottomColor: colors.border,
          }}
        >
          <Pressable onPress={onClose} hitSlop={8}>
            <Text style={{ fontSize: 16, color: colors.primary }}>Cancel</Text>
          </Pressable>
          <Text style={{ flex: 1, textAlign: "center", fontSize: 16, fontWeight: "600", color: colors.foreground }}>
            {isNew ? "New Entry" : "Edit Entry"}
          </Text>
          <Pressable onPress={handleSave} hitSlop={8}>
            <Text style={{ fontSize: 16, fontWeight: "600", color: colors.primary }}>Save</Text>
          </Pressable>
        </View>

        <ScrollView contentContainerStyle={{ padding: 16, gap: 16 }}>
          <View>
            <Text style={[styles.label, { color: colors.mutedForeground }]}>Title *</Text>
            <TextInput
              style={[styles.input, { borderColor: colors.border, color: colors.foreground, backgroundColor: colors.card }]}
              value={title}
              onChangeText={setTitle}
              placeholder="What's new?"
              placeholderTextColor={colors.mutedForeground}
            />
          </View>

          <View>
            <Text style={[styles.label, { color: colors.mutedForeground }]}>Body</Text>
            <TextInput
              style={[
                styles.input,
                { borderColor: colors.border, color: colors.foreground, backgroundColor: colors.card, minHeight: 100, textAlignVertical: "top" },
              ]}
              value={body}
              onChangeText={setBody}
              placeholder="Describe the update…"
              placeholderTextColor={colors.mutedForeground}
              multiline
            />
          </View>

          <View>
            <Text style={[styles.label, { color: colors.mutedForeground }]}>Tag</Text>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
              {[null, ...ENTRY_TAGS].map((t) => (
                <Pressable
                  key={t ?? "__none"}
                  onPress={() => setTag(t)}
                  style={{
                    paddingHorizontal: 12,
                    paddingVertical: 6,
                    borderRadius: 20,
                    borderWidth: 1.5,
                    borderColor: tag === t ? colors.primary : colors.border,
                    backgroundColor: tag === t ? colors.primary + "22" : "transparent",
                  }}
                >
                  <Text style={{ fontSize: 13, color: tag === t ? colors.primary : colors.mutedForeground }}>
                    {t ? (ENTRY_TAG_LABELS[t] ?? t) : "None"}
                  </Text>
                </Pressable>
              ))}
            </View>
          </View>

          <View>
            <Text style={[styles.label, { color: colors.mutedForeground }]}>Publish Date (YYYY-MM-DD)</Text>
            <TextInput
              style={[styles.input, { borderColor: colors.border, color: colors.foreground, backgroundColor: colors.card }]}
              value={publishedDate}
              onChangeText={setPublishedDate}
              placeholder="2025-01-01"
              placeholderTextColor={colors.mutedForeground}
            />
          </View>

          <View>
            <Text style={[styles.label, { color: colors.mutedForeground }]}>Status</Text>
            <View style={{ flexDirection: "row", gap: 12 }}>
              {(["draft", "published"] as const).map((s) => (
                <Pressable
                  key={s}
                  onPress={() => setStatus(s)}
                  style={{
                    flex: 1,
                    paddingVertical: 10,
                    borderRadius: 10,
                    borderWidth: 1.5,
                    borderColor: status === s ? colors.primary : colors.border,
                    backgroundColor: status === s ? colors.primary + "22" : "transparent",
                    alignItems: "center",
                  }}
                >
                  <Text style={{ fontSize: 14, fontWeight: "600", color: status === s ? colors.primary : colors.mutedForeground }}>
                    {s === "draft" ? "Draft" : "Published"}
                  </Text>
                </Pressable>
              ))}
            </View>
          </View>
        </ScrollView>
      </View>
    </Modal>
  );
}

export default function UpdatesScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const linkId = Number(idParam);

  const [modalVisible, setModalVisible] = useState(false);
  const [editing, setEditing] = useState<UpdateEntry | null>(null);

  const { data, isLoading, isRefetching, refetch } = useQuery({
    queryKey: ["updates-entries", linkId],
    queryFn: () => listOwnerEntries(linkId),
    enabled: !!linkId,
  });

  const invalidate = () => qc.invalidateQueries({ queryKey: ["updates-entries", linkId] });

  const createMutation = useMutation({
    mutationFn: (input: Parameters<typeof createUpdateEntry>[1]) =>
      createUpdateEntry(linkId, input),
    onSuccess: () => {
      invalidate();
      setModalVisible(false);
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ entryId, input }: { entryId: number; input: Parameters<typeof updateUpdateEntry>[2] }) =>
      updateUpdateEntry(linkId, entryId, input),
    onSuccess: () => {
      invalidate();
      setModalVisible(false);
      setEditing(null);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (entryId: number) => deleteUpdateEntry(linkId, entryId),
    onSuccess: invalidate,
  });

  const handleDelete = (entry: UpdateEntry) => {
    showAlert("Delete Entry", `Delete "${entry.title}"? This cannot be undone.`, [
      { text: "Cancel", style: "cancel" },
      {
        text: "Delete",
        style: "destructive",
        onPress: () => deleteMutation.mutate(entry.id),
      },
    ]);
  };

  const handleSave = (input: CreateEntryInput | UpdateEntryInput) => {
    if (editing) {
      updateMutation.mutate({ entryId: editing.id, input: input as UpdateEntryInput });
    } else {
      createMutation.mutate(input as CreateEntryInput);
    }
  };

  const openNew = () => {
    setEditing(null);
    setModalVisible(true);
  };

  const openEdit = (entry: UpdateEntry) => {
    setEditing(entry);
    setModalVisible(true);
  };

  return (
    <>
      <Stack.Screen
        options={{
          title: "Updates / Changelog",
          headerRight: () => (
            <Pressable onPress={openNew} hitSlop={8} style={{ marginRight: 4 }}>
              <Feather name="plus" size={22} color={colors.primary} />
            </Pressable>
          ),
        }}
      />

      {isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={data ?? []}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16, gap: 12 }}
          refreshControl={
            <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={colors.primary} />
          }
          ListEmptyComponent={
            <EmptyState
              icon="file-text"
              title="No entries yet"
              body="Tap + to add your first update or announcement."
            />
          }
          renderItem={({ item }) => (
            <EntryRow
              entry={item}
              colors={colors}
              onEdit={openEdit}
              onDelete={handleDelete}
            />
          )}
        />
      )}

      {modalVisible ? (
        <EntryModal
          visible={modalVisible}
          entry={editing}
          colors={colors}
          onClose={() => {
            setModalVisible(false);
            setEditing(null);
          }}
          onSave={handleSave}
        />
      ) : null}
    </>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 14,
  },
  label: {
    fontSize: 12,
    fontWeight: "600",
    marginBottom: 6,
    textTransform: "uppercase",
    letterSpacing: 0.5,
  },
  input: {
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 15,
  },
});
