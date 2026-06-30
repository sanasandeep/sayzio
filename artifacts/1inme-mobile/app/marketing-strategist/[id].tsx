import { Feather } from "@expo/vector-icons";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
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
import { useColors } from "@/hooks/useColors";
import {
  marketingStrategist,
  type MsMessage,
  type MsPlay,
  type MsSuggestion,
} from "@/lib/api/marketingStrategist";
import { handlePlanLockedError } from "@/lib/upgradePrompt";

/**
 * Marketing Strategist detail (mobile). Renders the generated plan exactly
 * like the web `show` view: a blue-tinted summary card, one-click action
 * suggestions (Apply / Dismiss), the Organic and Paid plans as play cards,
 * the KPI chips, and a streamed chat-refine composer at the bottom.
 *
 * Applying a suggestion performs a real, state-changing action, so the
 * client confirms first (the API also enforces a `confirm:true` flag — a
 * 409 otherwise). Chat-refine streams token-by-token over SSE.
 */
export default function MarketingStrategyDetailScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const insets = useSafeAreaInsets();
  const { id } = useLocalSearchParams<{ id: string }>();
  const strategyId = Number(id);

  const q = useQuery({
    queryKey: ["marketing-strategist", "detail", strategyId],
    queryFn: () => marketingStrategist.show(strategyId),
    enabled: Number.isFinite(strategyId) && strategyId > 0,
  });

  const status = (q.error as { status?: number } | null)?.status;
  const disabled: "engine" | "plan" | null =
    status === 404 && !q.data ? null : status === 403 ? "plan" : null;

  // Local copies so streamed/optimistic updates don't fight react-query.
  const [suggestions, setSuggestions] = useState<MsSuggestion[]>([]);
  const [messages, setMessages] = useState<MsMessage[]>([]);
  const [busySuggestion, setBusySuggestion] = useState<number | null>(null);

  useEffect(() => {
    if (q.data) {
      setSuggestions(q.data.suggestions);
      setMessages(q.data.messages);
    }
  }, [q.data]);

  // ── chat-refine (streamed) ───────────────────────────────────────
  const [draft, setDraft] = useState("");
  const [streaming, setStreaming] = useState(false);
  const [streamText, setStreamText] = useState("");
  const scrollRef = useRef<ScrollView>(null);

  const scrollToEnd = useCallback(() => {
    requestAnimationFrame(() =>
      scrollRef.current?.scrollToEnd({ animated: true }),
    );
  }, []);

  const send = async () => {
    const text = draft.trim();
    if (!text || streaming) return;
    setDraft("");
    setStreaming(true);
    setStreamText("");
    setMessages((cur) => [
      ...cur,
      {
        id: -Date.now(),
        role: "user",
        content: text,
        created_at: new Date().toISOString(),
      },
    ]);
    scrollToEnd();

    try {
      await marketingStrategist.chatStream(strategyId, text, {
        onToken: (delta) => {
          setStreamText((t) => t + delta);
          scrollToEnd();
        },
        onDone: ({ message }) => {
          setMessages((cur) => [...cur, message]);
          setStreamText("");
          setStreaming(false);
          scrollToEnd();
        },
        onError: ({ code, message }) => {
          setStreaming(false);
          setStreamText("");
          if (code === "insufficient_credits") {
            handlePlanLockedError({ status: 402, message, code });
          } else {
            Alert.alert("Couldn't refine", message);
          }
        },
      });
    } catch (e: any) {
      setStreaming(false);
      setStreamText("");
      Alert.alert("Couldn't refine", e?.message || "Please try again.");
    }
  };

  const apply = (sug: MsSuggestion) => {
    const run = async () => {
      setBusySuggestion(sug.id);
      try {
        const res = await marketingStrategist.applySuggestion(sug.id);
        setSuggestions((cur) =>
          cur.map((s) =>
            s.id === sug.id ? { ...s, status: res.status, error: null } : s,
          ),
        );
        qc.invalidateQueries({ queryKey: ["marketing-strategist", "list"] });
        Alert.alert("Applied", res.message || "The suggestion was applied.");
      } catch (e: any) {
        if (handlePlanLockedError(e)) return;
        const errMsg = e?.message || "Could not apply this suggestion.";
        setSuggestions((cur) =>
          cur.map((s) =>
            s.id === sug.id
              ? { ...s, status: "error", error: errMsg }
              : s,
          ),
        );
        Alert.alert("Couldn't apply", errMsg);
      } finally {
        setBusySuggestion(null);
      }
    };

    Alert.alert(
      "Apply this suggestion?",
      `This makes a real change to your account (${sug.type_label.toLowerCase()}).`,
      [
        { text: "Cancel", style: "cancel" },
        { text: "Apply", style: "default", onPress: run },
      ],
    );
  };

  const dismiss = async (sug: MsSuggestion) => {
    setBusySuggestion(sug.id);
    try {
      const res = await marketingStrategist.dismissSuggestion(sug.id);
      setSuggestions((cur) =>
        cur.map((s) => (s.id === sug.id ? { ...s, status: res.status } : s)),
      );
    } catch (e: any) {
      Alert.alert("Couldn't dismiss", e?.message || "Please try again.");
    } finally {
      setBusySuggestion(null);
    }
  };

  const remove = () => {
    Alert.alert(
      "Delete strategy?",
      "This permanently removes the generated plan and its chat history.",
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Delete",
          style: "destructive",
          onPress: async () => {
            try {
              await marketingStrategist.destroy(strategyId);
              qc.invalidateQueries({
                queryKey: ["marketing-strategist", "list"],
              });
              router.back();
            } catch (e: any) {
              Alert.alert("Couldn't delete", e?.message || "Please try again.");
            }
          },
        },
      ],
    );
  };

  const strategy = q.data?.strategy;
  const plan = strategy?.strategy;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: strategy?.title ?? "Strategy",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
          headerRight: () =>
            q.data ? (
              <Pressable onPress={remove} hitSlop={8} style={{ paddingRight: 12 }}>
                <Feather name="trash-2" size={19} color={colors.destructive} />
              </Pressable>
            ) : null,
        }}
      />

      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : disabled ? (
        <AiDisabledNotice feature="Marketing Strategist" variant={disabled} />
      ) : !strategy || !plan ? (
        <View style={styles.center}>
          <Text style={{ color: colors.mutedForeground }}>
            {(q.error as any)?.message ?? "Strategy not found."}
          </Text>
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
          >
            {/* Summary */}
            {plan.summary ? (
              <View
                style={[
                  styles.summaryCard,
                  {
                    backgroundColor: colors.primary + "12",
                    borderColor: colors.primary + "33",
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <Text style={[styles.summaryText, { color: colors.foreground }]}>
                  {plan.summary}
                </Text>
              </View>
            ) : null}

            {/* Suggestions */}
            {suggestions.length > 0 ? (
              <View style={{ marginBottom: 20 }}>
                <View style={styles.sectionHead}>
                  <Feather name="zap" size={16} color={colors.warning} />
                  <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                    One-click actions
                  </Text>
                </View>
                <View style={{ gap: 8 }}>
                  {suggestions.map((sug) => (
                    <SuggestionCard
                      key={sug.id}
                      sug={sug}
                      colors={colors}
                      busy={busySuggestion === sug.id}
                      onApply={() => apply(sug)}
                      onDismiss={() => dismiss(sug)}
                    />
                  ))}
                </View>
              </View>
            ) : null}

            {/* Organic plan */}
            <View style={styles.sectionHead}>
              <Feather name="feather" size={16} color={colors.success} />
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                Organic plan
              </Text>
            </View>
            {(plan.organic ?? []).length > 0 ? (
              (plan.organic ?? []).map((play, i) => (
                <PlayCard key={`o-${i}`} play={play} colors={colors} />
              ))
            ) : (
              <Text style={[styles.empty, { color: colors.mutedForeground }]}>
                No organic plays generated.
              </Text>
            )}

            {/* Paid plan */}
            <View style={[styles.sectionHead, { marginTop: 16 }]}>
              <Feather name="trending-up" size={16} color={colors.primary} />
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                Paid plan
              </Text>
            </View>
            {(plan.paid ?? []).length > 0 ? (
              (plan.paid ?? []).map((play, i) => (
                <PlayCard key={`p-${i}`} play={play} colors={colors} />
              ))
            ) : (
              <Text style={[styles.empty, { color: colors.mutedForeground }]}>
                No paid plays generated.
              </Text>
            )}

            {/* KPIs */}
            {(plan.kpis ?? []).length > 0 ? (
              <View style={{ marginTop: 20 }}>
                <View style={styles.sectionHead}>
                  <Feather name="target" size={16} color={colors.primary} />
                  <Text
                    style={[styles.sectionTitle, { color: colors.foreground }]}
                  >
                    KPIs to track
                  </Text>
                </View>
                <View style={styles.kpiRow}>
                  {(plan.kpis ?? []).map((kpi, i) => (
                    <View
                      key={i}
                      style={[styles.kpiChip, { backgroundColor: colors.muted }]}
                    >
                      <Text
                        style={[styles.kpiText, { color: colors.mutedForeground }]}
                      >
                        {kpi}
                      </Text>
                    </View>
                  ))}
                </View>
              </View>
            ) : null}

            {/* Chat-refine */}
            <View style={{ marginTop: 24 }}>
              <View style={styles.sectionHead}>
                <Feather name="message-circle" size={16} color={colors.primary} />
                <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                  Refine with chat
                </Text>
              </View>
              {messages.length === 0 && !streaming ? (
                <Text style={[styles.empty, { color: colors.mutedForeground }]}>
                  Ask the strategist to tweak the plan — e.g. “Make the paid plan
                  cheaper, or focus organic on TikTok.”
                </Text>
              ) : (
                <View style={{ gap: 8 }}>
                  {messages.map((m) => (
                    <ChatBubble key={m.id} message={m} colors={colors} />
                  ))}
                  {streaming ? (
                    <ChatBubble
                      message={{
                        id: -1,
                        role: "assistant",
                        content: streamText || "…",
                      }}
                      colors={colors}
                    />
                  ) : null}
                </View>
              )}
            </View>
          </ScrollView>

          {/* Composer */}
          <View
            style={[
              styles.composer,
              {
                backgroundColor: colors.card,
                borderTopColor: colors.border,
                paddingBottom: insets.bottom + 10,
              },
            ]}
          >
            <TextInput
              value={draft}
              onChangeText={setDraft}
              placeholder="Refine the plan…"
              placeholderTextColor={colors.mutedForeground}
              editable={!streaming}
              multiline
              maxLength={4000}
              style={[
                styles.composerInput,
                {
                  color: colors.foreground,
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            />
            <Pressable
              onPress={send}
              disabled={streaming || !draft.trim()}
              style={[
                styles.sendBtn,
                {
                  backgroundColor:
                    streaming || !draft.trim()
                      ? colors.mutedForeground
                      : colors.primary,
                },
              ]}
            >
              {streaming ? (
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

function SuggestionCard({
  sug,
  colors,
  busy,
  onApply,
  onDismiss,
}: {
  sug: MsSuggestion;
  colors: ReturnType<typeof useColors>;
  busy: boolean;
  onApply: () => void;
  onDismiss: () => void;
}) {
  return (
    <View
      style={[
        styles.sugCard,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius - 2,
        },
      ]}
    >
      <View style={styles.sugHead}>
        <View
          style={[styles.sugType, { backgroundColor: colors.primary + "22" }]}
        >
          <Text style={[styles.sugTypeText, { color: colors.primary }]}>
            {sug.type_label}
          </Text>
        </View>
        <Text
          style={[styles.sugTitle, { color: colors.foreground }]}
          numberOfLines={2}
        >
          {sug.title}
        </Text>
      </View>
      {sug.description ? (
        <Text style={[styles.sugDesc, { color: colors.mutedForeground }]}>
          {sug.description}
        </Text>
      ) : null}
      {sug.status === "error" && sug.error ? (
        <Text style={[styles.sugError, { color: colors.destructive }]}>
          {sug.error}
        </Text>
      ) : null}

      <View style={styles.sugActions}>
        {sug.status === "applied" ? (
          <View style={styles.sugStatus}>
            <Feather name="check" size={14} color={colors.success} />
            <Text style={[styles.sugStatusText, { color: colors.success }]}>
              Applied
            </Text>
          </View>
        ) : sug.status === "dismissed" ? (
          <Text style={[styles.sugStatusText, { color: colors.mutedForeground }]}>
            Dismissed
          </Text>
        ) : (
          <>
            <Pressable
              onPress={onApply}
              disabled={busy}
              style={[
                styles.applyBtn,
                { backgroundColor: colors.primary, opacity: busy ? 0.6 : 1 },
              ]}
            >
              {busy ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <Text style={styles.applyText}>Apply</Text>
              )}
            </Pressable>
            <Pressable
              onPress={onDismiss}
              disabled={busy}
              style={[styles.dismissBtn, { backgroundColor: colors.muted }]}
            >
              <Text style={[styles.dismissText, { color: colors.mutedForeground }]}>
                Dismiss
              </Text>
            </Pressable>
          </>
        )}
      </View>
    </View>
  );
}

function PlayCard({
  play,
  colors,
}: {
  play: MsPlay;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View
      style={[
        styles.playCard,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <View style={styles.playHead}>
        <Text
          style={[styles.playTitle, { color: colors.foreground }]}
          numberOfLines={2}
        >
          {play.title ?? "Play"}
        </Text>
        {play.channel ? (
          <View style={[styles.channelPill, { backgroundColor: colors.muted }]}>
            <Text style={[styles.channelText, { color: colors.mutedForeground }]}>
              {play.channel}
            </Text>
          </View>
        ) : null}
      </View>

      {play.budget_hint ? (
        <View style={styles.budgetRow}>
          <Feather name="dollar-sign" size={12} color={colors.warning} />
          <Text style={[styles.budgetText, { color: colors.warning }]}>
            {play.budget_hint}
          </Text>
        </View>
      ) : null}

      {play.rationale ? (
        <Text style={[styles.rationale, { color: colors.mutedForeground }]}>
          {play.rationale}
        </Text>
      ) : null}

      {(play.steps ?? []).length > 0 ? (
        <View style={{ gap: 6, marginTop: 10 }}>
          {(play.steps ?? []).map((step, i) => (
            <View key={i} style={styles.stepRow}>
              <Feather name="check" size={13} color={colors.success} />
              <Text style={[styles.stepText, { color: colors.foreground }]}>
                {step}
              </Text>
            </View>
          ))}
        </View>
      ) : null}

      {(play.sayzio_features ?? []).length > 0 ? (
        <View style={styles.featureRow}>
          {(play.sayzio_features ?? []).map((f, i) => (
            <View
              key={i}
              style={[styles.featurePill, { backgroundColor: colors.primary + "1a" }]}
            >
              <Text style={[styles.featureText, { color: colors.primary }]}>
                {f}
              </Text>
            </View>
          ))}
        </View>
      ) : null}
    </View>
  );
}

function ChatBubble({
  message,
  colors,
}: {
  message: Pick<MsMessage, "id" | "role" | "content">;
  colors: ReturnType<typeof useColors>;
}) {
  const isUser = message.role === "user";
  return (
    <View
      style={[
        styles.bubble,
        {
          alignSelf: isUser ? "flex-end" : "flex-start",
          backgroundColor: isUser ? colors.primary : colors.card,
          borderColor: isUser ? colors.primary : colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <Text
        style={[
          styles.bubbleText,
          { color: isUser ? "#fff" : colors.foreground },
        ]}
      >
        {message.content}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  summaryCard: {
    borderWidth: 1,
    padding: 16,
    marginBottom: 20,
  },
  summaryText: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 21,
  },
  sectionHead: {
    flexDirection: "row",
    alignItems: "center",
    gap: 7,
    marginBottom: 10,
  },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 16,
  },
  empty: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
  },
  // suggestions
  sugCard: { borderWidth: 1, padding: 14, gap: 6 },
  sugHead: { flexDirection: "row", alignItems: "center", gap: 8 },
  sugType: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 999 },
  sugTypeText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 10.5 },
  sugTitle: { flex: 1, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  sugDesc: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12.5,
    lineHeight: 18,
  },
  sugError: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 17,
  },
  sugActions: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginTop: 4,
  },
  sugStatus: { flexDirection: "row", alignItems: "center", gap: 5 },
  sugStatusText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12.5 },
  applyBtn: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 10,
    minWidth: 72,
    alignItems: "center",
  },
  applyText: { color: "#fff", fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  dismissBtn: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 10 },
  dismissText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  // plays
  playCard: { borderWidth: 1, padding: 14, marginBottom: 10 },
  playHead: {
    flexDirection: "row",
    alignItems: "flex-start",
    justifyContent: "space-between",
    gap: 8,
  },
  playTitle: { flex: 1, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  channelPill: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 999,
  },
  channelText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 10.5 },
  budgetRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    marginTop: 6,
  },
  budgetText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  rationale: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12.5,
    lineHeight: 18,
    marginTop: 8,
  },
  stepRow: { flexDirection: "row", alignItems: "flex-start", gap: 8 },
  stepText: {
    flex: 1,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12.5,
    lineHeight: 18,
  },
  featureRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 6,
    marginTop: 12,
  },
  featurePill: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  featureText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 10.5 },
  // kpis
  kpiRow: { flexDirection: "row", flexWrap: "wrap", gap: 7 },
  kpiChip: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 999 },
  kpiText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  // chat
  bubble: {
    maxWidth: "88%",
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 9,
  },
  bubbleText: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13.5,
    lineHeight: 20,
  },
  composer: {
    flexDirection: "row",
    alignItems: "flex-end",
    gap: 8,
    borderTopWidth: 1,
    paddingHorizontal: 12,
    paddingTop: 10,
  },
  composerInput: {
    flex: 1,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 10,
    maxHeight: 120,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
  },
  sendBtn: {
    width: 48,
    height: 48,
    borderRadius: 24,
    alignItems: "center",
    justifyContent: "center",
  },
});
