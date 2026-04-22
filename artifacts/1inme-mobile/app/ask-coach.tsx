import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Linking,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import {
  type CoachMessage,
  type CoachThread,
  askCoach,
} from "@/lib/api/ask-coach";

/**
 * Ask Coach (mobile) — minimal multi-turn chat against
 * /api/v1/ai/ask-coach. The web app renders the same data with
 * inline charts and a thread sidebar; on mobile we keep one
 * active thread visible and surface citations + actions inline so
 * the user can deep-link straight to the relevant 1INME page.
 */
export default function AskCoachScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const [threadId, setThreadId] = useState<number | null>(null);
  const [history, setHistory] = useState<CoachMessage[]>([]);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [draft, setDraft] = useState("");
  const scrollRef = useRef<ScrollView>(null);

  const ensureThread = useCallback(async () => {
    setLoading(true);
    try {
      const list = await askCoach.threads();
      let id = list.threads[0]?.id ?? null;
      if (!id) {
        const created = await askCoach.create();
        id = created.thread.id;
      }
      setThreadId(id);
      const t = await askCoach.messages(id);
      setHistory(t.messages);
    } catch (e: any) {
      Alert.alert(
        "Coach unavailable",
        e?.message || "Could not load Ask Coach.",
      );
      router.back();
    } finally {
      setLoading(false);
    }
  }, [router]);

  useEffect(() => {
    ensureThread();
  }, [ensureThread]);

  const send = useCallback(async () => {
    const text = draft.trim();
    if (!text || !threadId || sending) return;
    setSending(true);
    setDraft("");
    // Optimistic user turn so the chat feels instant.
    setHistory((h) => [
      ...h,
      {
        id: -Date.now(),
        role: "user",
        content: text,
        meta: null,
      },
    ]);
    try {
      const out = await askCoach.send(threadId, text);
      setHistory((h) => [...h, out.message]);
    } catch (e: any) {
      Alert.alert("Send failed", e?.message || "Coach could not reply.");
    } finally {
      setSending(false);
      requestAnimationFrame(() => scrollRef.current?.scrollToEnd());
    }
  }, [draft, sending, threadId]);

  const sendFeedback = useCallback(
    async (msg: CoachMessage, kind: "up" | "down" | "clear") => {
      try {
        const out = await askCoach.feedback(msg.id, kind);
        setHistory((h) =>
          h.map((m) => (m.id === msg.id ? out.message : m)),
        );
      } catch (e: any) {
        Alert.alert("Could not save feedback", e?.message || "");
      }
    },
    [],
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Ask Coach" }} />
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.text} />
        </View>
      ) : (
        <KeyboardAvoidingView
          style={{ flex: 1 }}
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          keyboardVerticalOffset={insets.top + 44}
        >
          <ScrollView
            ref={scrollRef}
            contentContainerStyle={{ padding: 16, paddingBottom: 24 }}
            onContentSizeChange={() => scrollRef.current?.scrollToEnd()}
          >
            {history.length === 0 ? (
              <Text style={[styles.hint, { color: colors.mutedForeground }]}>
                Ask anything about your 1INME — &quot;What was my best link last
                week?&quot;, &quot;How many sales last month?&quot;
              </Text>
            ) : (
              history.map((m) => (
                <MessageBubble
                  key={m.id}
                  message={m}
                  colors={colors}
                  onFeedback={sendFeedback}
                />
              ))
            )}
          </ScrollView>

          <View
            style={[
              styles.inputRow,
              { borderTopColor: colors.border, paddingBottom: insets.bottom + 8 },
            ]}
          >
            <TextInput
              value={draft}
              onChangeText={setDraft}
              placeholder="Ask Coach…"
              placeholderTextColor={colors.mutedForeground}
              style={[
                styles.input,
                { color: colors.text, backgroundColor: colors.card, borderColor: colors.border },
              ]}
              editable={!sending}
              multiline
            />
            <Pressable
              onPress={send}
              disabled={sending || !draft.trim()}
              style={[
                styles.sendBtn,
                {
                  backgroundColor: sending || !draft.trim() ? colors.mutedForeground : "#7c3aed",
                },
              ]}
            >
              {sending ? (
                <ActivityIndicator color="#fff" />
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

function MessageBubble({
  message,
  colors,
  onFeedback,
}: {
  message: CoachMessage;
  colors: ReturnType<typeof useColors>;
  onFeedback: (m: CoachMessage, k: "up" | "down" | "clear") => void;
}) {
  const isUser = message.role === "user";
  const meta = message.meta || {};
  return (
    <View
      style={{
        alignSelf: isUser ? "flex-end" : "flex-start",
        maxWidth: "92%",
        marginVertical: 6,
      }}
    >
      <View
        style={{
          backgroundColor: isUser ? "#7c3aed" : colors.card,
          padding: 10,
          borderRadius: 14,
        }}
      >
        <Text style={{ color: isUser ? "#fff" : colors.text }}>
          {message.content}
        </Text>

        {!isUser && meta.actions?.length ? (
          <View style={{ marginTop: 8, flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
            {meta.actions.map((a, i) => (
              <Pressable
                key={i}
                onPress={() => Linking.openURL(a.url)}
                style={{
                  backgroundColor: "#7c3aed33",
                  paddingHorizontal: 8,
                  paddingVertical: 4,
                  borderRadius: 8,
                }}
              >
                <Text style={{ color: "#c4b5fd", fontSize: 12 }}>→ {a.label}</Text>
              </Pressable>
            ))}
          </View>
        ) : null}

        {!isUser && meta.citations?.length ? (
          <Text style={{ color: colors.mutedForeground, fontSize: 10, marginTop: 6 }}>
            Sources: {meta.citations.map((c) => c.label || c.source).join(" · ")}
          </Text>
        ) : null}
      </View>

      {!isUser ? (
        <View style={{ flexDirection: "row", marginTop: 4, gap: 8, alignItems: "center" }}>
          <Pressable onPress={() => onFeedback(message, message.feedback === "up" ? "clear" : "up")}>
            <Text style={{ fontSize: 14, opacity: message.feedback === "up" ? 1 : 0.4 }}>👍</Text>
          </Pressable>
          <Pressable onPress={() => onFeedback(message, message.feedback === "down" ? "clear" : "down")}>
            <Text style={{ fontSize: 14, opacity: message.feedback === "down" ? 1 : 0.4 }}>👎</Text>
          </Pressable>
          {meta.credits_spent ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 10 }}>
              {meta.credits_spent} ✦
            </Text>
          ) : null}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  hint: { textAlign: "center", padding: 24, fontStyle: "italic" },
  inputRow: {
    flexDirection: "row",
    alignItems: "flex-end",
    padding: 8,
    borderTopWidth: 1,
    gap: 8,
  },
  input: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
    minHeight: 40,
    maxHeight: 120,
  },
  sendBtn: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
  },
});
