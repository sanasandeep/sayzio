import { Feather } from "@expo/vector-icons";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useRef, useState } from "react";
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
import { errorStatus } from "@/lib/api";
import {
  exportStrategy,
  marketingStrategist,
  type StrategyChatMessage,
  type StrategyPlay,
  type StrategyShow,
  type StrategySuggestion,
} from "@/lib/api/marketingStrategist";
import { isPlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";

const FEATURE_LABEL = "Performer Specialist";

export default function MarketingStrategyDetail() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);
  const queryClient = useQueryClient();
  const router = useRouter();

  const q = useQuery({
    queryKey: ["marketing-strategist", "show", id],
    queryFn: () => marketingStrategist.show(id),
    enabled: Number.isFinite(id),
  });

  // Live chat transcript + streaming buffer layered over the persisted
  // messages so the bubble updates token-by-token without a refetch.
  const [chatInput, setChatInput] = useState("");
  const [streaming, setStreaming] = useState(false);
  const [streamBuffer, setStreamBuffer] = useState("");
  const [localMessages, setLocalMessages] = useState<StrategyChatMessage[] | null>(
    null,
  );
  const [chatError, setChatError] = useState<string | null>(null);
  const [exporting, setExporting] = useState(false);
  const abortRef = useRef<AbortController | null>(null);
  const tempIdRef = useRef(-1);

  const messages = localMessages ?? q.data?.messages ?? [];

  const refreshShow = useCallback(() => {
    queryClient.invalidateQueries({
      queryKey: ["marketing-strategist", "show", id],
    });
  }, [queryClient, id]);

  if (errorStatus(q.error) === 403) {
    return <AiDisabledNotice feature={FEATURE_LABEL} variant="plan" />;
  }

  if (q.isLoading) {
    return (
      <View
        style={[
          styles.center,
          { backgroundColor: colors.background },
        ]}
      >
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (q.error || !q.data) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Text style={{ color: colors.destructive }}>
          Couldn&apos;t load this strategy.
        </Text>
      </View>
    );
  }

  const data: StrategyShow = q.data;
  const strategy = data.strategy;
  const plan = strategy.strategy ?? {};

  const onExport = (format: "md" | "pdf") => {
    setExporting(true);
    exportStrategy(id, format, strategy.title)
      .catch((e) =>
        Alert.alert(
          "Export failed",
          e instanceof Error ? e.message : "Could not export the strategy.",
        ),
      )
      .finally(() => setExporting(false));
  };

  const promptExport = () => {
    Alert.alert("Export strategy", "Choose a format to share or save.", [
      { text: "Markdown (.md)", onPress: () => onExport("md") },
      { text: "PDF", onPress: () => onExport("pdf") },
      { text: "Cancel", style: "cancel" },
    ]);
  };

  const onDelete = () => {
    Alert.alert(
      "Delete strategy",
      `Delete "${strategy.title}"? This can't be undone.`,
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Delete",
          style: "destructive",
          onPress: async () => {
            try {
              await marketingStrategist.destroy(id);
              queryClient.invalidateQueries({
                queryKey: ["marketing-strategist", "list"],
              });
              queryClient.removeQueries({
                queryKey: ["marketing-strategist", "show", id],
              });
              router.back();
            } catch (e) {
              Alert.alert(
                "Couldn't delete",
                e instanceof Error ? e.message : "Please try again.",
              );
            }
          },
        },
      ],
    );
  };

  const onApply = (s: StrategySuggestion) => {
    Alert.alert(
      "Apply suggestion?",
      `“${s.title}” will make a change to your account.`,
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Apply",
          onPress: async () => {
            try {
              await marketingStrategist.applySuggestion(s.id);
              refreshShow();
            } catch (e) {
              if (isPlanLockedError(e)) showUpgradePrompt(e);
              else
                Alert.alert(
                  "Couldn't apply",
                  e instanceof Error ? e.message : "Please try again.",
                );
            }
          },
        },
      ],
    );
  };

  const onDismiss = async (s: StrategySuggestion) => {
    try {
      await marketingStrategist.dismissSuggestion(s.id);
      refreshShow();
    } catch (e) {
      Alert.alert(
        "Couldn't dismiss",
        e instanceof Error ? e.message : "Please try again.",
      );
    }
  };

  const onSend = async () => {
    const text = chatInput.trim();
    if (!text || streaming) return;
    setChatError(null);
    setChatInput("");

    const base = localMessages ?? data.messages ?? [];
    const userMsg: StrategyChatMessage = {
      id: tempIdRef.current--,
      role: "user",
      content: text,
    };
    setLocalMessages([...base, userMsg]);
    setStreaming(true);
    setStreamBuffer("");

    const controller = new AbortController();
    abortRef.current = controller;

    try {
      await marketingStrategist.chatStream(id, text, {
        signal: controller.signal,
        onToken: (delta) => setStreamBuffer((b) => b + delta),
        onDone: ({ message }) => {
          setLocalMessages((prev) => [...(prev ?? base), message]);
          setStreamBuffer("");
          setStreaming(false);
          abortRef.current = null;
          refreshShow();
        },
        onError: (err) => {
          setStreaming(false);
          setStreamBuffer("");
          abortRef.current = null;
          setChatError(err.message);
        },
      });
    } catch (e) {
      setStreaming(false);
      setStreamBuffer("");
      abortRef.current = null;
      setChatError(
        e instanceof Error ? e.message : "The strategist could not reply.",
      );
    }
  };

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.background }}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
      keyboardVerticalOffset={Platform.OS === "ios" ? 90 : 0}
    >
      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top + 8,
          paddingBottom: 24,
          paddingHorizontal: 20,
          gap: 20,
        }}
      >
        {/* Header */}
        <View style={{ gap: 6 }}>
          <Text style={[styles.eyebrow, { color: colors.mutedForeground }]}>
            {strategy.goal}
          </Text>
          <Text style={[styles.title, { color: colors.foreground }]}>
            {strategy.title}
          </Text>
          <View style={styles.metaRow}>
            {strategy.credits_spent > 0 ? (
              <Pill
                icon="zap"
                text={`${strategy.credits_spent} coins`}
                colors={colors}
              />
            ) : null}
            {strategy.model ? (
              <Pill icon="cpu" text={strategy.model} colors={colors} />
            ) : null}
            <Pressable
              onPress={promptExport}
              disabled={exporting}
              style={[
                styles.exportBtn,
                { borderColor: colors.border, opacity: exporting ? 0.6 : 1 },
              ]}
            >
              {exporting ? (
                <ActivityIndicator size="small" color={colors.primary} />
              ) : (
                <Feather name="download" size={13} color={colors.primary} />
              )}
              <Text style={[styles.exportText, { color: colors.primary }]}>
                Export
              </Text>
            </Pressable>
            <Pressable
              onPress={onDelete}
              accessibilityLabel="Delete strategy"
              style={[styles.exportBtn, { borderColor: colors.border }]}
            >
              <Feather name="trash-2" size={13} color={colors.destructive} />
              <Text style={[styles.exportText, { color: colors.destructive }]}>
                Delete
              </Text>
            </Pressable>
          </View>
        </View>

        {/* Summary */}
        {plan.summary ? (
          <Section title="Overview" colors={colors}>
            <Text style={[styles.body, { color: colors.cardForeground }]}>
              {plan.summary}
            </Text>
          </Section>
        ) : null}

        {/* KPIs */}
        {plan.kpis && plan.kpis.length ? (
          <Section title="Key metrics to watch" colors={colors}>
            <View style={{ gap: 8 }}>
              {plan.kpis.map((k, i) => (
                <View key={i} style={styles.bulletRow}>
                  <Feather name="bar-chart-2" size={14} color={colors.primary} />
                  <Text
                    style={[styles.bulletText, { color: colors.cardForeground }]}
                  >
                    {k}
                  </Text>
                </View>
              ))}
            </View>
          </Section>
        ) : null}

        {/* Organic plays */}
        {plan.organic && plan.organic.length ? (
          <View style={{ gap: 12 }}>
            <Text style={[styles.groupTitle, { color: colors.foreground }]}>
              Organic plays
            </Text>
            {plan.organic.map((p, i) => (
              <PlayCard key={`o-${i}`} play={p} colors={colors} accent="success" />
            ))}
          </View>
        ) : null}

        {/* Paid plays */}
        {plan.paid && plan.paid.length ? (
          <View style={{ gap: 12 }}>
            <Text style={[styles.groupTitle, { color: colors.foreground }]}>
              Paid plays
            </Text>
            {plan.paid.map((p, i) => (
              <PlayCard key={`p-${i}`} play={p} colors={colors} accent="primary" />
            ))}
          </View>
        ) : null}

        {/* Suggestions */}
        {data.suggestions && data.suggestions.length ? (
          <View style={{ gap: 12 }}>
            <Text style={[styles.groupTitle, { color: colors.foreground }]}>
              One-tap actions
            </Text>
            {data.suggestions.map((s) => (
              <SuggestionCard
                key={s.id}
                suggestion={s}
                colors={colors}
                onApply={() => onApply(s)}
                onDismiss={() => onDismiss(s)}
              />
            ))}
          </View>
        ) : null}

        {/* Chat refine */}
        <View style={{ gap: 12 }}>
          <Text style={[styles.groupTitle, { color: colors.foreground }]}>
            Refine with chat
          </Text>
          <Text style={[styles.sectionHint, { color: colors.mutedForeground }]}>
            Ask follow-ups to tweak the plan — each reply uses your coins.
          </Text>

          {messages.map((m) => (
            <ChatBubble key={m.id} message={m} colors={colors} />
          ))}

          {streaming ? (
            <ChatBubble
              message={{
                id: -999,
                role: "assistant",
                content: streamBuffer || "…",
              }}
              colors={colors}
              pending
            />
          ) : null}

          {chatError ? (
            <Text style={{ color: colors.destructive }}>{chatError}</Text>
          ) : null}
        </View>
      </ScrollView>

      {/* Composer */}
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
          value={chatInput}
          onChangeText={setChatInput}
          placeholder="Ask the strategist…"
          placeholderTextColor={colors.mutedForeground}
          editable={!streaming}
          multiline
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
          onPress={onSend}
          disabled={streaming || !chatInput.trim()}
          style={[
            styles.sendBtn,
            {
              backgroundColor:
                streaming || !chatInput.trim()
                  ? colors.muted
                  : colors.primary,
            },
          ]}
        >
          {streaming ? (
            <ActivityIndicator size="small" color={colors.primaryForeground} />
          ) : (
            <Feather
              name="send"
              size={18}
              color={
                chatInput.trim()
                  ? colors.primaryForeground
                  : colors.mutedForeground
              }
            />
          )}
        </Pressable>
      </View>
    </KeyboardAvoidingView>
  );
}

type Colors = ReturnType<typeof useColors>;

function Section({
  title,
  colors,
  children,
}: {
  title: string;
  colors: Colors;
  children: React.ReactNode;
}) {
  return (
    <View
      style={[
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <Text style={[styles.cardTitle, { color: colors.foreground }]}>
        {title}
      </Text>
      {children}
    </View>
  );
}

function Pill({
  icon,
  text,
  colors,
}: {
  icon: keyof typeof Feather.glyphMap;
  text: string;
  colors: Colors;
}) {
  return (
    <View style={[styles.pill, { backgroundColor: colors.muted }]}>
      <Feather name={icon} size={11} color={colors.mutedForeground} />
      <Text style={[styles.pillText, { color: colors.mutedForeground }]}>
        {text}
      </Text>
    </View>
  );
}

function PlayCard({
  play,
  colors,
  accent,
}: {
  play: StrategyPlay;
  colors: Colors;
  accent: "primary" | "success";
}) {
  const accentColor = accent === "success" ? colors.success : colors.primary;
  return (
    <View
      style={[
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          gap: 10,
        },
      ]}
    >
      <View style={{ gap: 4 }}>
        {play.channel ? (
          <Text style={[styles.playChannel, { color: accentColor }]}>
            {play.channel.toUpperCase()}
          </Text>
        ) : null}
        {play.title ? (
          <Text style={[styles.playTitle, { color: colors.foreground }]}>
            {play.title}
          </Text>
        ) : null}
      </View>

      {play.rationale ? (
        <Text style={[styles.body, { color: colors.mutedForeground }]}>
          {play.rationale}
        </Text>
      ) : null}

      {play.steps && play.steps.length ? (
        <View style={{ gap: 6 }}>
          {play.steps.map((step, i) => (
            <View key={i} style={styles.bulletRow}>
              <Text style={[styles.stepNum, { color: accentColor }]}>
                {i + 1}.
              </Text>
              <Text
                style={[styles.bulletText, { color: colors.cardForeground }]}
              >
                {step}
              </Text>
            </View>
          ))}
        </View>
      ) : null}

      {play.budget_hint ? (
        <View style={styles.bulletRow}>
          <Feather name="dollar-sign" size={13} color={colors.mutedForeground} />
          <Text style={[styles.metaSmall, { color: colors.mutedForeground }]}>
            {play.budget_hint}
          </Text>
        </View>
      ) : null}

      {play.sayzio_features && play.sayzio_features.length ? (
        <View style={styles.tagWrap}>
          {play.sayzio_features.map((f, i) => (
            <View
              key={i}
              style={[styles.tag, { backgroundColor: accentColor + "1c" }]}
            >
              <Text style={[styles.tagText, { color: accentColor }]}>{f}</Text>
            </View>
          ))}
        </View>
      ) : null}
    </View>
  );
}

function SuggestionCard({
  suggestion,
  colors,
  onApply,
  onDismiss,
}: {
  suggestion: StrategySuggestion;
  colors: Colors;
  onApply: () => void;
  onDismiss: () => void;
}) {
  const s = suggestion;
  const isPending = s.status === "pending";
  const statusColor =
    s.status === "applied"
      ? colors.success
      : s.status === "error"
        ? colors.destructive
        : colors.mutedForeground;
  return (
    <View
      style={[
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          gap: 10,
        },
      ]}
    >
      <View style={{ gap: 4 }}>
        <View style={styles.suggestionHead}>
          <View style={[styles.typeTag, { backgroundColor: colors.muted }]}>
            <Text style={[styles.typeTagText, { color: colors.mutedForeground }]}>
              {s.type_label}
            </Text>
          </View>
          {!isPending ? (
            <Text style={[styles.statusText, { color: statusColor }]}>
              {s.status === "applied"
                ? "Applied"
                : s.status === "dismissed"
                  ? "Dismissed"
                  : "Error"}
            </Text>
          ) : null}
        </View>
        <Text style={[styles.suggestionTitle, { color: colors.foreground }]}>
          {s.title}
        </Text>
        {s.description ? (
          <Text style={[styles.body, { color: colors.mutedForeground }]}>
            {s.description}
          </Text>
        ) : null}
        {s.status === "error" && s.error ? (
          <Text style={[styles.body, { color: colors.destructive }]}>
            {s.error}
          </Text>
        ) : null}
      </View>

      {isPending ? (
        <View style={styles.suggestionActions}>
          <Pressable
            onPress={onApply}
            style={[styles.applyBtn, { backgroundColor: colors.primary }]}
          >
            <Feather name="check" size={14} color={colors.primaryForeground} />
            <Text
              style={[styles.applyText, { color: colors.primaryForeground }]}
            >
              Apply
            </Text>
          </Pressable>
          <Pressable
            onPress={onDismiss}
            style={[styles.dismissBtn, { borderColor: colors.border }]}
          >
            <Text style={[styles.dismissText, { color: colors.mutedForeground }]}>
              Dismiss
            </Text>
          </Pressable>
        </View>
      ) : null}
    </View>
  );
}

function ChatBubble({
  message,
  colors,
  pending,
}: {
  message: StrategyChatMessage;
  colors: Colors;
  pending?: boolean;
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
          {
            color: isUser ? colors.primaryForeground : colors.cardForeground,
            opacity: pending ? 0.85 : 1,
          },
        ]}
      >
        {message.content}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  eyebrow: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 24 },
  metaRow: { flexDirection: "row", flexWrap: "wrap", alignItems: "center", gap: 8, marginTop: 4 },
  pill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingVertical: 4,
    paddingHorizontal: 9,
    borderRadius: 999,
  },
  pillText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11 },
  exportBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    paddingVertical: 5,
    paddingHorizontal: 11,
    borderRadius: 999,
    borderWidth: 1,
  },
  exportText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  card: { padding: 14, borderWidth: 1 },
  cardTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    marginBottom: 8,
  },
  groupTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  sectionHint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  body: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 19 },
  bulletRow: { flexDirection: "row", gap: 8, alignItems: "flex-start" },
  bulletText: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
    flex: 1,
  },
  stepNum: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 13, lineHeight: 19 },
  playChannel: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 11,
    letterSpacing: 0.5,
  },
  playTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
  metaSmall: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  tagWrap: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  tag: { paddingVertical: 4, paddingHorizontal: 9, borderRadius: 999 },
  tagText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11 },
  suggestionHead: { flexDirection: "row", alignItems: "center", gap: 8 },
  typeTag: { paddingVertical: 3, paddingHorizontal: 8, borderRadius: 6 },
  typeTagText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
  statusText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  suggestionTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  suggestionActions: { flexDirection: "row", gap: 10 },
  applyBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingVertical: 9,
    paddingHorizontal: 16,
    borderRadius: 10,
  },
  applyText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  dismissBtn: {
    paddingVertical: 9,
    paddingHorizontal: 16,
    borderRadius: 10,
    borderWidth: 1,
  },
  dismissText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  bubble: {
    maxWidth: "85%",
    paddingVertical: 10,
    paddingHorizontal: 13,
    borderWidth: 1,
  },
  bubbleText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  composer: {
    flexDirection: "row",
    alignItems: "flex-end",
    gap: 8,
    paddingHorizontal: 16,
    paddingTop: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  composerInput: {
    flex: 1,
    maxHeight: 120,
    minHeight: 44,
    paddingHorizontal: 14,
    paddingTop: 12,
    paddingBottom: 12,
    borderWidth: 1,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
  },
  sendBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: "center",
    justifyContent: "center",
  },
});
