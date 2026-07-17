import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { router, Stack, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  clearFollowUp,
  getContact,
  listContactTags,
  setFollowUp,
  updateContactNotes,
  updateContactTags,
} from "@/lib/api/contacts";

export default function ContactDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numId = parseInt(id ?? "0", 10);
  const colors = useColors();
  const qc = useQueryClient();

  const contactQ = useQuery({
    queryKey: ["contact", numId],
    queryFn: () => getContact(numId),
    enabled: numId > 0,
  });

  const tagsQ = useQuery({
    queryKey: ["contact-tags"],
    queryFn: listContactTags,
    staleTime: 60_000,
  });

  const [editingNotes, setEditingNotes] = useState(false);
  const [notesDraft, setNotesDraft] = useState("");
  const [editingTags, setEditingTags] = useState(false);
  const [newTag, setNewTag] = useState("");

  const notesMut = useMutation({
    mutationFn: (notes: string | null) => updateContactNotes(numId, notes),
    onSuccess: (updated) => {
      qc.setQueryData(["contact", numId], updated);
      qc.invalidateQueries({ queryKey: ["contacts"] });
      setEditingNotes(false);
    },
  });

  const tagsMut = useMutation({
    mutationFn: (tags: string[]) => updateContactTags(numId, tags),
    onSuccess: (updated) => {
      qc.setQueryData(["contact", numId], updated);
      qc.invalidateQueries({ queryKey: ["contacts"] });
      qc.invalidateQueries({ queryKey: ["contact-tags"] });
    },
  });

  const clearFollowUpMut = useMutation({
    mutationFn: () => clearFollowUp(numId),
    onSuccess: (updated) => {
      qc.setQueryData(["contact", numId], updated);
      qc.invalidateQueries({ queryKey: ["contacts"] });
      qc.invalidateQueries({ queryKey: ["follow-ups"] });
    },
  });

  if (contactQ.isLoading) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background, alignItems: "center", justifyContent: "center" }}>
        <Stack.Screen options={{ title: "Contact" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (!contactQ.data) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background, alignItems: "center", justifyContent: "center" }}>
        <Stack.Screen options={{ title: "Contact" }} />
        <Text style={{ color: colors.mutedForeground, fontFamily: "SpaceGrotesk_400Regular" }}>
          Contact not found.
        </Text>
      </View>
    );
  }

  const c = contactQ.data;
  const tags: string[] = c.tags ?? [];
  const suggestions = (tagsQ.data ?? []).filter((t) => !tags.includes(t));

  function addTag(tag: string) {
    const t = tag.trim();
    if (!t || tags.includes(t)) { setNewTag(""); return; }
    tagsMut.mutate([...tags, t]);
    setNewTag("");
  }

  function removeTag(tag: string) {
    tagsMut.mutate(tags.filter((t) => t !== tag));
  }

  function handleClearFollowUp() {
    if (Platform.OS === "web") {
      if (!window.confirm("Clear this follow-up reminder?")) return;
      clearFollowUpMut.mutate();
    } else {
      Alert.alert(
        "Clear follow-up",
        "Remove this follow-up reminder?",
        [
          { text: "Cancel", style: "cancel" },
          { text: "Clear", style: "destructive", onPress: () => clearFollowUpMut.mutate() },
        ],
      );
    }
  }

  const overdue = c.follow_up_at && new Date(c.follow_up_at) <= new Date();

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: c.display_name,
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: { fontFamily: "SpaceGrotesk_600SemiBold", color: colors.foreground },
          headerTintColor: colors.primary,
        }}
      />
      <ScrollView contentContainerStyle={{ padding: 20, paddingBottom: 60 }}>

        {/* Avatar + Name */}
        <View style={{ alignItems: "center", marginBottom: 24 }}>
          <View style={[styles.avatar, { backgroundColor: colors.primary + "22" }]}>
            <Text style={[styles.avatarText, { color: colors.primary, fontFamily: "SpaceGrotesk_700Bold" }]}>
              {initials(c.display_name)}
            </Text>
          </View>
          <Text style={[styles.name, { color: colors.foreground, fontFamily: "SpaceGrotesk_700Bold" }]}>
            {c.display_name}
          </Text>
          {(c.organization || c.job_title) && (
            <Text style={[styles.sub, { color: colors.mutedForeground, fontFamily: "SpaceGrotesk_400Regular" }]}>
              {[c.job_title, c.organization].filter(Boolean).join(" · ")}
            </Text>
          )}
        </View>

        {/* Follow-up reminder */}
        {c.follow_up_at && (
          <View style={[styles.card, { backgroundColor: overdue ? "#ef444418" : colors.card, borderColor: overdue ? "#ef444440" : colors.border, marginBottom: 16 }]}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 4 }}>
              <Feather name="clock" size={14} color={overdue ? "#ef4444" : colors.primary} />
              <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13, color: overdue ? "#ef4444" : colors.primary }}>
                {overdue ? "Overdue follow-up" : "Upcoming follow-up"}
              </Text>
              <Pressable onPress={handleClearFollowUp} style={{ marginLeft: "auto", opacity: clearFollowUpMut.isPending ? 0.5 : 1 }}>
                <Feather name="x-circle" size={15} color={colors.mutedForeground} />
              </Pressable>
            </View>
            <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, color: colors.mutedForeground }}>
              {new Date(c.follow_up_at).toLocaleString()}
            </Text>
            {c.follow_up_note ? (
              <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, color: colors.foreground, marginTop: 4 }}>
                {c.follow_up_note}
              </Text>
            ) : null}
          </View>
        )}

        {/* Phones */}
        {c.phones.length > 0 && (
          <Section title="Phone" colors={colors}>
            {c.phones.map((p) => (
              <InfoRow key={p.id} label={p.label ?? "Phone"} value={p.value} colors={colors} />
            ))}
          </Section>
        )}

        {/* Emails */}
        {c.emails.length > 0 && (
          <Section title="Email" colors={colors}>
            {c.emails.map((e) => (
              <InfoRow key={e.id} label={e.label ?? "Email"} value={e.value} colors={colors} />
            ))}
          </Section>
        )}

        {/* Tags */}
        <Section
          title="Tags"
          colors={colors}
          action={
            <Pressable onPress={() => setEditingTags((v) => !v)}>
              <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12, color: colors.primary }}>
                {editingTags ? "Done" : "Edit"}
              </Text>
            </Pressable>
          }
        >
          {tags.length === 0 && !editingTags && (
            <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, color: colors.mutedForeground }}>
              No tags yet
            </Text>
          )}
          {tags.length > 0 && (
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
              {tags.map((tag) => (
                <View
                  key={tag}
                  style={[styles.tagChip, { backgroundColor: colors.primary + "14", borderColor: colors.primary + "30" }]}
                >
                  <Text style={{ fontFamily: "SpaceGrotesk_500Medium", fontSize: 12, color: colors.primary }}>
                    {tag}
                  </Text>
                  {editingTags && (
                    <Pressable onPress={() => removeTag(tag)} style={{ marginLeft: 4 }}>
                      <Feather name="x" size={10} color={colors.primary} />
                    </Pressable>
                  )}
                </View>
              ))}
            </View>
          )}
          {editingTags && (
            <View style={{ marginTop: 8 }}>
              <View style={[styles.inputRow, { backgroundColor: colors.background, borderColor: colors.border }]}>
                <TextInput
                  value={newTag}
                  onChangeText={setNewTag}
                  placeholder="Add tag…"
                  placeholderTextColor={colors.mutedForeground}
                  style={[styles.tagInput, { color: colors.foreground, fontFamily: "SpaceGrotesk_400Regular" }]}
                  onSubmitEditing={() => addTag(newTag)}
                  returnKeyType="done"
                  autoCapitalize="none"
                />
                <Pressable
                  onPress={() => addTag(newTag)}
                  style={({ pressed }) => ({ opacity: pressed ? 0.6 : 1 })}
                >
                  <Feather name="plus-circle" size={18} color={colors.primary} />
                </Pressable>
              </View>
              {suggestions.length > 0 && newTag.length === 0 && (
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 8 }}>
                  <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 11, color: colors.mutedForeground, width: "100%" }}>
                    Your tags:
                  </Text>
                  {suggestions.slice(0, 10).map((s) => (
                    <Pressable
                      key={s}
                      onPress={() => addTag(s)}
                      style={[styles.tagChip, { backgroundColor: colors.card, borderColor: colors.border }]}
                    >
                      <Text style={{ fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, color: colors.mutedForeground }}>
                        {s}
                      </Text>
                    </Pressable>
                  ))}
                </View>
              )}
            </View>
          )}
        </Section>

        {/* Notes */}
        <Section
          title="Notes"
          colors={colors}
          action={
            <Pressable
              onPress={() => {
                if (!editingNotes) { setNotesDraft(c.notes ?? ""); }
                setEditingNotes((v) => !v);
              }}
            >
              <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12, color: colors.primary }}>
                {editingNotes ? "Cancel" : c.notes ? "Edit" : "Add note"}
              </Text>
            </Pressable>
          }
        >
          {!editingNotes ? (
            c.notes ? (
              <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, color: colors.foreground, lineHeight: 20 }}>
                {c.notes}
              </Text>
            ) : (
              <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, color: colors.mutedForeground }}>
                No notes yet
              </Text>
            )
          ) : (
            <View>
              <TextInput
                value={notesDraft}
                onChangeText={setNotesDraft}
                multiline
                numberOfLines={5}
                placeholder="Add notes about this contact…"
                placeholderTextColor={colors.mutedForeground}
                style={[
                  styles.notesInput,
                  {
                    color: colors.foreground,
                    fontFamily: "SpaceGrotesk_400Regular",
                    backgroundColor: colors.background,
                    borderColor: colors.border,
                  },
                ]}
                maxLength={5000}
              />
              <Pressable
                onPress={() => notesMut.mutate(notesDraft || null)}
                disabled={notesMut.isPending}
                style={({ pressed }) => [
                  styles.saveBtn,
                  { backgroundColor: colors.primary, opacity: pressed || notesMut.isPending ? 0.7 : 1, marginTop: 10 },
                ]}
              >
                <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13, color: "#fff" }}>
                  {notesMut.isPending ? "Saving…" : "Save"}
                </Text>
              </Pressable>
            </View>
          )}
        </Section>

      </ScrollView>
    </View>
  );
}

function Section({
  title,
  children,
  colors,
  action,
}: {
  title: string;
  children: React.ReactNode;
  colors: ReturnType<typeof useColors>;
  action?: React.ReactNode;
}) {
  return (
    <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border, marginBottom: 12 }]}>
      <View style={{ flexDirection: "row", alignItems: "center", marginBottom: 10 }}>
        <Text style={[styles.sectionTitle, { color: colors.mutedForeground, fontFamily: "SpaceGrotesk_600SemiBold" }]}>
          {title.toUpperCase()}
        </Text>
        {action && <View style={{ marginLeft: "auto" }}>{action}</View>}
      </View>
      {children}
    </View>
  );
}

function InfoRow({
  label,
  value,
  colors,
}: {
  label: string;
  value: string;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={{ marginBottom: 6 }}>
      <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 10, color: colors.mutedForeground, marginBottom: 1 }}>
        {label}
      </Text>
      <Text style={{ fontFamily: "SpaceGrotesk_500Medium", fontSize: 14, color: colors.foreground }}>{value}</Text>
    </View>
  );
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return (parts[0]?.[0] ?? "?").toUpperCase();
}

const styles = StyleSheet.create({
  avatar: {
    width: 72,
    height: 72,
    borderRadius: 36,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 10,
  },
  avatarText: { fontSize: 26 },
  name: { fontSize: 20, marginBottom: 4 },
  sub: { fontSize: 13 },
  card: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 14,
  },
  sectionTitle: { fontSize: 10, letterSpacing: 0.6 },
  tagChip: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 20,
    borderWidth: 1,
  },
  inputRow: {
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 10,
    borderWidth: 1,
    paddingHorizontal: 10,
    height: 38,
    gap: 8,
  },
  tagInput: {
    flex: 1,
    fontSize: 13,
    paddingVertical: 0,
  },
  notesInput: {
    borderWidth: 1,
    borderRadius: 10,
    padding: 10,
    fontSize: 13,
    minHeight: 100,
    textAlignVertical: "top",
  },
  saveBtn: {
    borderRadius: 10,
    paddingVertical: 10,
    alignItems: "center",
  },
});
