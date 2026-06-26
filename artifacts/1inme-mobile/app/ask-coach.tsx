import { Feather } from "@expo/vector-icons";
import { Stack, useFocusEffect, useRouter } from "expo-router";
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

import { AiDisabledNotice } from "@/components/AiDisabledNotice";
import { setVoiceSurface } from "@/components/VoiceAssistant";
import { useColors } from "@/hooks/useColors";
import { useVoiceDictation } from "@/hooks/useVoiceDictation";
import { getBaseUrl } from "@/lib/api";
import {
  type CoachMessage,
  type CoachThread,
  askCoach,
  citationHref,
  citationLabel,
} from "@/lib/api/ask-coach";

/**
 * Ask Coach (mobile) — minimal multi-turn chat against
 * /api/v1/ai/ask-coach. The web app renders the same data with
 * inline charts and a thread sidebar; on mobile we keep one
 * active thread visible and surface citations + actions inline so
 * the user can deep-link straight to the relevant Sayzio page.
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
  // When the AI engine is off (404) or Ask Coach isn't on the user's
  // plan (403), we render the same friendly explainer the web shows
  // instead of bouncing the user back with an alert.
  const [disabled, setDisabled] = useState<"engine" | "plan" | null>(null);
  const scrollRef = useRef<ScrollView>(null);

  // Tell the floating Voice Assistant that voice turns started from
  // here should prefer the companion/coach tools, and append spoken
  // dictation straight into the draft.
  useFocusEffect(
    useCallback(() => {
      setVoiceSurface("companion");
      return () => setVoiceSurface(null);
    }, []),
  );
  const dictation = useVoiceDictation((t) =>
    setDraft((d) => (d ? d.trim() + " " : "") + t),
  );

  const ensureThread = useCallback(async () => {
    setLoading(true);
    try {
      const list = await askCoach.threads();
      // Graceful "AI is off" state, matching the web app (Task #1999).
      // The loader answers 200 with ai_enabled:false when the engine is
      // disabled, so render an informative panel instead of bouncing the
      // user back out with an error alert.
      if (list.ai_enabled === false) {
        setDisabled("engine");
        return;
      }
      setDisabled(null);
      let id = list.threads[0]?.id ?? null;
      if (!id) {
        const created = await askCoach.create();
        id = created.thread.id;
      }
      setThreadId(id);
      const t = await askCoach.messages(id);
      setHistory(t.messages);
    } catch (e: any) {
      const status = e?.status as number | undefined;
      if (status === 404) {
        setDisabled("engine");
      } else if (status === 403) {
        setDisabled("plan");
      } else {
        Alert.alert(
          "Account Assistant unavailable",
          e?.message || "Could not load Account Assistant.",
        );
        router.back();
      }
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

    // Optimistic user turn + an empty assistant placeholder we'll
    // append tokens to as they stream in.
    const userTempId = -Date.now();
    const assistantTempId = -(Date.now() + 1);
    setHistory((h) => [
      ...h,
      { id: userTempId, role: "user", content: text, meta: null },
      { id: assistantTempId, role: "assistant", content: "", meta: null },
    ]);

    try {
      await askCoach.sendStream(threadId, text, {
        onToken: (delta) => {
          setHistory((h) =>
            h.map((m) =>
              m.id === assistantTempId
                ? { ...m, content: m.content + delta }
                : m,
            ),
          );
          requestAnimationFrame(() => scrollRef.current?.scrollToEnd());
        },
        onDone: ({ message }) => {
          setHistory((h) =>
            h.map((m) => (m.id === assistantTempId ? message : m)),
          );
        },
        onError: (err) => {
          // Drop the placeholder and surface the failure.
          setHistory((h) => h.filter((m) => m.id !== assistantTempId));
          Alert.alert("Send failed", err.message || "Account Assistant could not reply.");
        },
      });
    } catch (e: any) {
      setHistory((h) => h.filter((m) => m.id !== assistantTempId));
      Alert.alert("Send failed", e?.message || "Account Assistant could not reply.");
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
      <Stack.Screen options={{ title: "Account Assistant" }} />
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.text} />
        </View>
      ) : disabled ? (
        <AiDisabledNotice feature="Account Assistant" variant={disabled} />
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
                Ask anything about your Sayzio — &quot;What was my best link last
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
              placeholder="Ask the assistant…"
              placeholderTextColor={colors.mutedForeground}
              style={[
                styles.input,
                { color: colors.text, backgroundColor: colors.card, borderColor: colors.border },
              ]}
              editable={!sending}
              multiline
            />
            <Pressable
              onPress={dictation.toggle}
              disabled={sending || dictation.busy}
              accessibilityLabel={
                dictation.recording ? "Stop dictation" : "Dictate your message"
              }
              style={[
                styles.sendBtn,
                {
                  backgroundColor: dictation.recording
                    ? "#dc2626"
                    : colors.card,
                  borderWidth: 1,
                  borderColor: colors.border,
                },
              ]}
            >
              {dictation.busy ? (
                <ActivityIndicator color={colors.text} />
              ) : (
                <Feather
                  name="mic"
                  size={18}
                  color={dictation.recording ? "#fff" : colors.text}
                />
              )}
            </Pressable>
            <Pressable
              onPress={send}
              disabled={sending || !draft.trim()}
              style={[
                styles.sendBtn,
                {
                  backgroundColor: sending || !draft.trim() ? colors.mutedForeground : "#3d6bff",
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
          backgroundColor: isUser ? "#3d6bff" : colors.card,
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
                  backgroundColor: "#3d6bff33",
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
          <View
            style={{
              marginTop: 6,
              flexDirection: "row",
              flexWrap: "wrap",
              alignItems: "center",
              gap: 4,
            }}
          >
            <Text style={{ color: colors.mutedForeground, fontSize: 10 }}>
              Sources:
            </Text>
            {meta.citations.map((c, i) => {
              const href = citationHref(c, getBaseUrl());
              const label = citationLabel(c) || "source";
              if (href) {
                return (
                  <Pressable
                    key={i}
                    accessibilityRole="link"
                    accessibilityLabel={`View source ${label}`}
                    onPress={() => Linking.openURL(href)}
                    style={{
                      paddingHorizontal: 6,
                      paddingVertical: 2,
                      borderRadius: 6,
                      backgroundColor: "#ffffff14",
                    }}
                  >
                    <Text
                      style={{
                        color: colors.text,
                        fontSize: 10,
                        textDecorationLine: "underline",
                      }}
                    >
                      {label}
                    </Text>
                  </Pressable>
                );
              }
              return (
                <Text
                  key={i}
                  style={{
                    color: colors.mutedForeground,
                    fontSize: 10,
                    paddingHorizontal: 6,
                    paddingVertical: 2,
                    borderRadius: 6,
                    backgroundColor: "#ffffff14",
                  }}
                >
                  {label}
                </Text>
              );
            })}
          </View>
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
              {meta.credits_spent} 🪙
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
