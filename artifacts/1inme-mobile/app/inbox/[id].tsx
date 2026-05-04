import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import {
  assignConversation,
  deleteConversation,
  getConversation,
  listTeammates,
  replyConversation,
  setConversationStatus,
  type Message,
} from "@/lib/api/inbox";

export default function ConversationScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);
  const qc = useQueryClient();
  const [draft, setDraft] = useState("");
  const [assignOpen, setAssignOpen] = useState(false);
  const [assignNote, setAssignNote] = useState("");
  const [assignSelected, setAssignSelected] = useState<number | null>(null);

  const q = useQuery({
    queryKey: ["inbox", "conversation", id],
    queryFn: () => getConversation(id),
    enabled: !!id,
  });

  const reply = useMutation({
    mutationFn: (body: string) => replyConversation(id, body),
    onSuccess: () => {
      setDraft("");
      qc.invalidateQueries({ queryKey: ["inbox", "conversation", id] });
      qc.invalidateQueries({ queryKey: ["inbox", "conversations"] });
    },
    onError: (e: any) => Alert.alert("Reply failed", e?.message ?? "Try again"),
  });

  const setStatus = useMutation({
    mutationFn: (s: "open" | "archived" | "blocked") =>
      setConversationStatus(id, s),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["inbox"] });
    },
  });

  const del = useMutation({
    mutationFn: () => deleteConversation(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["inbox", "conversations"] });
      router.back();
    },
  });

  const teammatesQ = useQuery({
    queryKey: ["inbox", "teammates"],
    queryFn: listTeammates,
    enabled: assignOpen,
  });

  const assign = useMutation({
    mutationFn: (payload: { uid: number | null; note: string }) =>
      assignConversation(id, payload.uid, payload.note || undefined),
    onSuccess: () => {
      setAssignOpen(false);
      setAssignNote("");
      qc.invalidateQueries({ queryKey: ["inbox", "conversation", id] });
      qc.invalidateQueries({ queryKey: ["inbox", "conversations"] });
    },
    onError: (e: any) =>
      Alert.alert("Assignment failed", e?.message ?? "Try again"),
  });

  const conv = q.data?.conversation;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: conv?.viewer_name || "Conversation",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
          headerRight: () => (
            <View style={{ flexDirection: "row", gap: 14, paddingRight: 8 }}>
              <Pressable
                onPress={() => {
                  setAssignSelected(conv?.assignee_user_id ?? null);
                  setAssignOpen(true);
                }}
              >
                <Feather name="user-plus" size={18} color={colors.primary} />
              </Pressable>
              {conv?.status !== "archived" ? (
                <Pressable onPress={() => setStatus.mutate("archived")}>
                  <Feather name="archive" size={18} color={colors.primary} />
                </Pressable>
              ) : (
                <Pressable onPress={() => setStatus.mutate("open")}>
                  <Feather name="rotate-ccw" size={18} color={colors.primary} />
                </Pressable>
              )}
              <Pressable
                onPress={() =>
                  Alert.alert("Delete conversation?", "This cannot be undone.", [
                    { text: "Cancel", style: "cancel" },
                    { text: "Delete", style: "destructive", onPress: () => del.mutate() },
                  ])
                }
              >
                <Feather name="trash-2" size={18} color="#dc2626" />
              </Pressable>
            </View>
          ),
        }}
      />

      {conv?.assignee_name ? (
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            gap: 6,
            paddingHorizontal: 16,
            paddingVertical: 8,
            backgroundColor: colors.primary + "12",
            borderBottomWidth: 1,
            borderBottomColor: colors.border,
          }}
        >
          <Feather name="user-check" size={12} color={colors.primary} />
          <Text
            style={{
              color: colors.primary,
              fontFamily: "SpaceGrotesk_500Medium",
              fontSize: 12,
            }}
          >
            Assigned to {conv.assignee_name}
          </Text>
        </View>
      ) : null}

      {assignOpen ? (
        <View
          style={{
            paddingHorizontal: 16,
            paddingVertical: 12,
            gap: 8,
            backgroundColor: colors.card,
            borderBottomWidth: 1,
            borderBottomColor: colors.border,
          }}
        >
          <Text
            style={{
              fontFamily: "SpaceGrotesk_600SemiBold",
              fontSize: 13,
              color: colors.foreground,
            }}
          >
            Assign this thread
          </Text>
          {teammatesQ.isLoading ? (
            <ActivityIndicator color={colors.primary} />
          ) : (
            <View
              style={{
                flexDirection: "row",
                flexWrap: "wrap",
                gap: 6,
              }}
            >
              <Pressable
                onPress={() => setAssignSelected(null)}
                style={{
                  paddingHorizontal: 10,
                  paddingVertical: 6,
                  borderRadius: 999,
                  borderWidth: 1,
                  borderColor:
                    assignSelected === null ? colors.primary : colors.border,
                  backgroundColor:
                    assignSelected === null
                      ? colors.primary + "1c"
                      : "transparent",
                }}
              >
                <Text
                  style={{
                    color:
                      assignSelected === null
                        ? colors.primary
                        : colors.mutedForeground,
                    fontFamily: "SpaceGrotesk_500Medium",
                    fontSize: 12,
                  }}
                >
                  Unassigned
                </Text>
              </Pressable>
              {(teammatesQ.data ?? []).map((t) => {
                const sel = assignSelected === t.id;
                return (
                  <Pressable
                    key={t.id}
                    onPress={() => setAssignSelected(t.id)}
                    style={{
                      paddingHorizontal: 10,
                      paddingVertical: 6,
                      borderRadius: 999,
                      borderWidth: 1,
                      borderColor: sel ? colors.primary : colors.border,
                      backgroundColor: sel
                        ? colors.primary + "1c"
                        : "transparent",
                    }}
                  >
                    <Text
                      style={{
                        color: sel ? colors.primary : colors.mutedForeground,
                        fontFamily: "SpaceGrotesk_500Medium",
                        fontSize: 12,
                      }}
                    >
                      {t.name}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          )}
          <TextInput
            value={assignNote}
            onChangeText={setAssignNote}
            placeholder="Handoff note (optional)"
            placeholderTextColor={colors.mutedForeground}
            multiline
            maxLength={500}
            style={{
              color: colors.foreground,
              backgroundColor: colors.background,
              borderColor: colors.border,
              borderWidth: 1,
              borderRadius: colors.radius - 4,
              padding: 8,
              fontFamily: "SpaceGrotesk_400Regular",
              fontSize: 13,
              minHeight: 50,
            }}
          />
          <View style={{ flexDirection: "row", gap: 8, justifyContent: "flex-end" }}>
            <Pressable
              onPress={() => setAssignOpen(false)}
              style={{ paddingHorizontal: 12, paddingVertical: 8 }}
            >
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontFamily: "SpaceGrotesk_500Medium",
                  fontSize: 13,
                }}
              >
                Cancel
              </Text>
            </Pressable>
            <Pressable
              disabled={assign.isPending}
              onPress={() =>
                assign.mutate({ uid: assignSelected, note: assignNote.trim() })
              }
              style={{
                paddingHorizontal: 14,
                paddingVertical: 8,
                borderRadius: colors.radius - 4,
                backgroundColor: colors.primary,
                opacity: assign.isPending ? 0.6 : 1,
              }}
            >
              <Text
                style={{
                  color: "#fff",
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  fontSize: 13,
                }}
              >
                {assign.isPending ? "Saving…" : "Apply"}
              </Text>
            </Pressable>
          </View>
        </View>
      ) : null}

      {q.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <KeyboardAvoidingView
          style={{ flex: 1 }}
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          keyboardVerticalOffset={Platform.OS === "ios" ? 80 : 0}
        >
          <FlatList
            data={q.data?.messages ?? []}
            keyExtractor={(m) => String(m.id)}
            contentContainerStyle={{ padding: 16, gap: 8 }}
            renderItem={({ item }) => (
              <MessageBubble m={item} />
            )}
          />
          <View
            style={[
              styles.composer,
              {
                backgroundColor: colors.card,
                borderTopColor: colors.border,
                paddingBottom: insets.bottom + 8,
              },
            ]}
          >
            <TextInput
              value={draft}
              onChangeText={setDraft}
              placeholder="Write a reply…"
              placeholderTextColor={colors.mutedForeground}
              multiline
              style={[
                styles.input,
                {
                  color: colors.foreground,
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                  borderRadius: colors.radius - 4,
                },
              ]}
            />
            <Pressable
              onPress={() => draft.trim() && reply.mutate(draft.trim())}
              disabled={reply.isPending || !draft.trim()}
              style={[
                styles.sendBtn,
                {
                  backgroundColor: colors.primary,
                  opacity: !draft.trim() || reply.isPending ? 0.5 : 1,
                },
              ]}
            >
              {reply.isPending ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <Feather name="send" size={18} color="#fff" />
              )}
            </Pressable>
          </View>
        </KeyboardAvoidingView>
      )}
    </View>
  );
}

function MessageBubble({ m }: { m: Message }) {
  const colors = useColors();
  const own = m.sender_type === "owner";
  return (
    <View
      style={{
        alignSelf: own ? "flex-end" : "flex-start",
        maxWidth: "82%",
      }}
    >
      <View
        style={{
          backgroundColor: own ? colors.primary : colors.card,
          borderRadius: 14,
          paddingHorizontal: 12,
          paddingVertical: 10,
          borderWidth: own ? 0 : 1,
          borderColor: colors.border,
        }}
      >
        <Text
          style={{
            color: own ? "#fff" : colors.foreground,
            fontFamily: "SpaceGrotesk_400Regular",
            fontSize: 14,
            lineHeight: 19,
          }}
        >
          {m.body}
        </Text>
      </View>
      {m.created_at ? (
        <Text
          style={{
            marginTop: 2,
            color: colors.mutedForeground,
            fontFamily: "SpaceGrotesk_400Regular",
            fontSize: 10,
            alignSelf: own ? "flex-end" : "flex-start",
          }}
        >
          {new Date(m.created_at).toLocaleString()}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  composer: {
    flexDirection: "row",
    alignItems: "flex-end",
    gap: 8,
    paddingHorizontal: 12,
    paddingTop: 8,
    borderTopWidth: 1,
  },
  input: {
    flex: 1,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    padding: 10,
    borderWidth: 1,
    maxHeight: 120,
  },
  sendBtn: {
    width: 44,
    height: 44,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
});
