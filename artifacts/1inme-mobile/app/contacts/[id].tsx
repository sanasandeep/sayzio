import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { router, Stack, useLocalSearchParams } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Modal,
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
  contactActivityHref,
  getContact,
  getContactActivity,
  listContactTags,
  listMergeCandidates,
  listUndoableMerges,
  mergeContacts,
  setFollowUp,
  undoContactMerge,
  type UndoableMerge,
  updateContactNotes,
  updateContactTags,
  type MergeCandidate,
} from "@/lib/api/contacts";

export default function ContactDetailScreen() {
  const { id, focus } = useLocalSearchParams<{ id: string; focus?: string }>();
  const numId = parseInt(id ?? "0", 10);
  const colors = useColors();
  const qc = useQueryClient();
  const scrollRef = useRef<ScrollView>(null);
  const activityYRef = useRef<number | null>(null);
  const didFocusScrollRef = useRef(false);

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

  const activityQ = useQuery({
    queryKey: ["contact-activity", numId],
    queryFn: () => getContactActivity(numId),
    enabled: numId > 0,
    staleTime: 60_000,
  });

  function maybeScrollToActivity() {
    if (focus !== "activity" || didFocusScrollRef.current) return;
    const y = activityYRef.current;
    if (y == null) return;
    didFocusScrollRef.current = true;
    requestAnimationFrame(() => {
      scrollRef.current?.scrollTo({ y: Math.max(y - 12, 0), animated: true });
    });
  }

  const [editingNotes, setEditingNotes] = useState(false);
  const [notesDraft, setNotesDraft] = useState("");
  const [editingTags, setEditingTags] = useState(false);
  const [newTag, setNewTag] = useState("");
  const [mergeOpen, setMergeOpen] = useState(false);
  const [mergeSearch, setMergeSearch] = useState("");

  const candidatesQ = useQuery({
    queryKey: ["merge-candidates", numId, mergeSearch],
    queryFn: () => listMergeCandidates(numId, mergeSearch),
    enabled: numId > 0 && mergeOpen,
  });

  // Recent merges into this contact that can still be undone (web parity).
  const undoableQ = useQuery({
    queryKey: ["undoable-merges", numId],
    queryFn: () => listUndoableMerges(numId),
    enabled: numId > 0,
    staleTime: 30_000,
  });

  const undoMut = useMutation({
    mutationFn: (auditId: number) => undoContactMerge(auditId),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ["contacts"] });
      qc.invalidateQueries({ queryKey: ["contact", numId] });
      qc.invalidateQueries({ queryKey: ["contact-activity", numId] });
      qc.invalidateQueries({ queryKey: ["undoable-merges"] });
      qc.setQueryData(["contact", res.contact.id], res.contact);
      // Land on the restored contact, mirroring the web redirect.
      router.push(`/contacts/${res.contact.id}` as any);
    },
    onError: (e: any) => {
      const msg = e?.message ?? "Could not undo the merge.";
      if (Platform.OS === "web") window.alert(msg);
      else Alert.alert("Undo failed", msg);
    },
  });

  function confirmUndoMerge(m: UndoableMerge) {
    const prompt = `Undo this merge? "${m.source_name}" will be restored as its own contact with its phones, emails and activity.`;
    if (Platform.OS === "web") {
      if (window.confirm(prompt)) undoMut.mutate(m.id);
    } else {
      Alert.alert("Undo merge", prompt, [
        { text: "Cancel", style: "cancel" },
        { text: "Undo merge", onPress: () => undoMut.mutate(m.id) },
      ]);
    }
  }

  const mergeMut = useMutation({
    // This contact is absorbed INTO the target: the target survives with
    // all emails/phones/activity, this record is deleted (web parity).
    mutationFn: (targetId: number) => mergeContacts(targetId, [numId]),
    onSuccess: (res) => {
      setMergeOpen(false);
      qc.invalidateQueries({ queryKey: ["contacts"] });
      qc.invalidateQueries({ queryKey: ["contact-duplicates"] });
      qc.removeQueries({ queryKey: ["contact", numId] });
      qc.setQueryData(["contact", res.contact.id], res.contact);
      router.replace(`/contacts/${res.contact.id}` as any);
    },
    onError: (e: any) => {
      const msg = e?.message ?? "Merge failed. Please try again.";
      if (Platform.OS === "web") window.alert(msg);
      else Alert.alert("Merge failed", msg);
    },
  });

  function confirmMerge(target: MergeCandidate) {
    const name = contactQ.data?.display_name ?? "this contact";
    const prompt = `Merge "${name}" into "${target.display_name}"? All emails, phones and captured activity move over, and "${name}" will be deleted. No data is lost.`;
    if (Platform.OS === "web") {
      if (window.confirm(prompt)) mergeMut.mutate(target.id);
    } else {
      Alert.alert("Merge contacts", prompt, [
        { text: "Cancel", style: "cancel" },
        { text: "Merge", style: "destructive", onPress: () => mergeMut.mutate(target.id) },
      ]);
    }
  }

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
      <ScrollView ref={scrollRef} contentContainerStyle={{ padding: 20, paddingBottom: 60 }}>

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

        {/* Recently merged into this contact — undoable (web parity) */}
        {(undoableQ.data?.merges ?? []).map((m) => (
          <View
            key={m.id}
            style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border, marginBottom: 16 }]}
          >
            <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 4 }}>
              <Feather name="rotate-ccw" size={14} color={colors.primary} />
              <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13, color: colors.foreground, flex: 1 }}>
                “{m.source_name}” was merged into this contact
                {m.merged_at ? ` on ${new Date(m.merged_at).toLocaleDateString()}` : ""}
              </Text>
            </View>
            <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, color: colors.mutedForeground, marginBottom: 10 }}>
              Merged by mistake? You can undo a merge for {undoableQ.data?.undo_window_days ?? 30} days.
            </Text>
            <Pressable
              onPress={() => confirmUndoMerge(m)}
              disabled={undoMut.isPending}
              style={({ pressed }) => [
                styles.mergeBtn,
                { borderColor: colors.border, backgroundColor: colors.background, opacity: pressed || undoMut.isPending ? 0.6 : 1 },
              ]}
            >
              {undoMut.isPending && undoMut.variables === m.id ? (
                <ActivityIndicator size="small" color={colors.primary} />
              ) : (
                <Feather name="rotate-ccw" size={15} color={colors.primary} />
              )}
              <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13, color: colors.primary }}>
                Undo merge
              </Text>
            </Pressable>
          </View>
        ))}

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

        {/* Activity across Sayzio (unified contact linking) */}
        <View
          onLayout={(e) => {
            activityYRef.current = e.nativeEvent.layout.y;
            maybeScrollToActivity();
          }}
        >
        <Section title="Activity across Sayzio" colors={colors}>
          {(activityQ.data?.is_auto_captured || activityQ.data?.follower_bridge?.is_follower) && (
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6, marginBottom: 10 }}>
              {activityQ.data?.is_auto_captured && (
                <View style={[styles.tagChip, { backgroundColor: colors.card, borderColor: colors.border }]}>
                  <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 10, color: colors.primary }}>
                    Auto-captured
                  </Text>
                </View>
              )}
              {activityQ.data?.follower_bridge?.is_follower && (
                <View style={[styles.tagChip, { backgroundColor: colors.card, borderColor: colors.border }]}>
                  <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 10, color: "#22c55e" }}>
                    Follows you
                  </Text>
                </View>
              )}
            </View>
          )}
          {activityQ.isLoading ? (
            <ActivityIndicator color={colors.primary} />
          ) : !activityQ.data || activityQ.data.groups.length === 0 ? (
            <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, color: colors.mutedForeground }}>
              No linked activity yet. Subscriptions, orders, bookings, RSVPs, reviews and conversations from this
              person will show up here automatically.
            </Text>
          ) : (
            <View style={{ gap: 12 }}>
              {activityQ.data.groups.map((g) => (
                <View key={g.key}>
                  <View style={{ flexDirection: "row", alignItems: "center", marginBottom: 6 }}>
                    <Feather name={g.icon as never} size={13} color={colors.mutedForeground} />
                    <Text
                      style={{
                        fontFamily: "SpaceGrotesk_600SemiBold",
                        fontSize: 12,
                        color: colors.foreground,
                        marginLeft: 6,
                        flex: 1,
                      }}
                    >
                      {g.label}
                    </Text>
                    <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 11, color: colors.primary }}>
                      {g.count}
                    </Text>
                  </View>
                  {g.items.map((item, idx) => {
                    const href = contactActivityHref(g.key, item);
                    const inner = (
                      <>
                        <View style={{ flexDirection: "row", alignItems: "center" }}>
                          <Text
                            numberOfLines={1}
                            style={{
                              fontFamily: "SpaceGrotesk_500Medium",
                              fontSize: 13,
                              color: colors.foreground,
                              flex: 1,
                            }}
                          >
                            {item.title}
                          </Text>
                          {item.date && (
                            <Text
                              style={{
                                fontFamily: "SpaceGrotesk_400Regular",
                                fontSize: 10,
                                color: colors.mutedForeground,
                                marginLeft: 8,
                              }}
                            >
                              {new Date(item.date).toLocaleDateString()}
                            </Text>
                          )}
                          {href && (
                            <Feather
                              name="chevron-right"
                              size={14}
                              color={colors.mutedForeground}
                              style={{ marginLeft: 6 }}
                            />
                          )}
                        </View>
                        {item.subtitle ? (
                          <Text
                            numberOfLines={1}
                            style={{
                              fontFamily: "SpaceGrotesk_400Regular",
                              fontSize: 11,
                              color: colors.mutedForeground,
                            }}
                          >
                            {item.subtitle}
                          </Text>
                        ) : null}
                      </>
                    );
                    if (!href) {
                      return (
                        <View key={idx} style={{ marginBottom: 6, paddingLeft: 19 }}>
                          {inner}
                        </View>
                      );
                    }
                    return (
                      <Pressable
                        key={idx}
                        accessibilityRole="button"
                        accessibilityLabel={`Open ${item.title}`}
                        onPress={() => router.push(href as never)}
                        style={({ pressed }) => [
                          { marginBottom: 6, paddingLeft: 19, opacity: pressed ? 0.6 : 1 },
                        ]}
                      >
                        {inner}
                      </Pressable>
                    );
                  })}
                  {g.count > g.items.length && (
                    <Text
                      style={{
                        fontFamily: "SpaceGrotesk_400Regular",
                        fontSize: 10,
                        color: colors.mutedForeground,
                        paddingLeft: 19,
                      }}
                    >
                      + {g.count - g.items.length} more
                    </Text>
                  )}
                </View>
              ))}
            </View>
          )}
        </Section>
        </View>

        {/* Merge into another contact (web parity) */}
        <Section title="Duplicate?" colors={colors}>
          <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, color: colors.mutedForeground, marginBottom: 10 }}>
            If this is a duplicate, merge it into another contact. All emails, phones and captured activity move over; no data is lost.
          </Text>
          <Pressable
            onPress={() => { setMergeSearch(""); setMergeOpen(true); }}
            style={({ pressed }) => [
              styles.mergeBtn,
              { borderColor: colors.border, backgroundColor: colors.background, opacity: pressed ? 0.7 : 1 },
            ]}
          >
            <Feather name="git-merge" size={15} color={colors.primary} />
            <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13, color: colors.primary }}>
              Merge into…
            </Text>
          </Pressable>
        </Section>

      </ScrollView>

      {/* Merge-into picker modal */}
      <Modal visible={mergeOpen} transparent animationType="slide" onRequestClose={() => setMergeOpen(false)}>
        <View style={styles.modalOverlay}>
          <View style={[styles.modalSheet, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <View style={{ flexDirection: "row", alignItems: "center", marginBottom: 12 }}>
              <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 16, color: colors.foreground, flex: 1 }}>
                Merge into…
              </Text>
              <Pressable onPress={() => setMergeOpen(false)} hitSlop={10}>
                <Feather name="x" size={20} color={colors.mutedForeground} />
              </Pressable>
            </View>
            <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, color: colors.mutedForeground, marginBottom: 10 }}>
              Pick the contact that should survive. “{c.display_name}” will be merged into it and removed.
            </Text>
            <View style={[styles.inputRow, { backgroundColor: colors.background, borderColor: colors.border, marginBottom: 10 }]}>
              <Feather name="search" size={15} color={colors.mutedForeground} />
              <TextInput
                value={mergeSearch}
                onChangeText={setMergeSearch}
                placeholder="Search by name, company, email or phone…"
                placeholderTextColor={colors.mutedForeground}
                style={[styles.tagInput, { color: colors.foreground, fontFamily: "SpaceGrotesk_400Regular" }]}
                autoCapitalize="none"
                autoCorrect={false}
              />
            </View>
            {candidatesQ.isLoading || mergeMut.isPending ? (
              <View style={{ paddingVertical: 24, alignItems: "center" }}>
                <ActivityIndicator color={colors.primary} />
                {mergeMut.isPending && (
                  <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, color: colors.mutedForeground, marginTop: 8 }}>
                    Merging…
                  </Text>
                )}
              </View>
            ) : (candidatesQ.data ?? []).length === 0 ? (
              <Text style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, color: colors.mutedForeground, paddingVertical: 16, textAlign: "center" }}>
                {mergeSearch.trim() ? "No matching contacts." : "No other contacts to merge into."}
              </Text>
            ) : (
              <FlatList
                data={candidatesQ.data ?? []}
                keyExtractor={(item) => String(item.id)}
                keyboardShouldPersistTaps="handled"
                style={{ maxHeight: 360 }}
                renderItem={({ item }) => (
                  <Pressable
                    onPress={() => confirmMerge(item)}
                    disabled={mergeMut.isPending}
                    style={({ pressed }) => [
                      styles.candidateRow,
                      { borderColor: colors.border, backgroundColor: pressed ? colors.background : "transparent" },
                    ]}
                  >
                    {item.photo_url ? (
                      <Image source={{ uri: item.photo_url }} style={styles.candidateAvatar} />
                    ) : (
                      <View style={[styles.candidateAvatar, { backgroundColor: colors.primary + "22", alignItems: "center", justifyContent: "center" }]}>
                        <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 13, color: colors.primary }}>
                          {initials(item.display_name)}
                        </Text>
                      </View>
                    )}
                    <View style={{ flex: 1, marginLeft: 10 }}>
                      <Text numberOfLines={1} style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14, color: colors.foreground }}>
                        {item.display_name}
                      </Text>
                      {(item.organization || item.email || item.phone) && (
                        <Text numberOfLines={1} style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 11, color: colors.mutedForeground }}>
                          {[item.organization, item.email ?? item.phone].filter(Boolean).join(" · ")}
                        </Text>
                      )}
                    </View>
                    <Feather name="chevron-right" size={16} color={colors.mutedForeground} />
                  </Pressable>
                )}
              />
            )}
          </View>
        </View>
      </Modal>
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
  mergeBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    borderWidth: 1,
    borderRadius: 10,
    paddingVertical: 10,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
    justifyContent: "flex-end",
  },
  modalSheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: 1,
    padding: 16,
    paddingBottom: 30,
    maxHeight: "80%",
  },
  candidateRow: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 10,
    paddingHorizontal: 6,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  candidateAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
  },
});
