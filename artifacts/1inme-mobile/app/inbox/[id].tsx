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
  deleteConversation,
  getConversation,
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
