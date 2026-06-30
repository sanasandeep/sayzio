import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useMemo, useState } from "react";
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
  type MsSource,
} from "@/lib/api/marketingStrategist";
import { handlePlanLockedError } from "@/lib/upgradePrompt";

/** Default-on data sources, mirroring the web create form. */
const DEFAULT_SOURCES = ["links", "analytics", "audience"];

/** Fallback source catalogue when the index response hasn't loaded one. */
const FALLBACK_SOURCES: MsSource[] = [
  { key: "links", label: "Links & types", description: "Your links, their types and lifetime clicks." },
  { key: "analytics", label: "Analytics", description: "Recent click trends and device split." },
  { key: "audience", label: "Followers & subscribers", description: "Audience size and growth." },
  { key: "pixels", label: "Tracking pixels", description: "Ad pixels you already have connected." },
  { key: "minds", label: "AI Minds", description: "Your knowledge bases (names only)." },
  { key: "brand_kits", label: "Brand Kits", description: "Your brand palette, voice and taglines." },
  { key: "personas", label: "AI Personas", description: "Your saved AI persona agents." },
  { key: "companions", label: "AI Companions", description: "Your published AI chat companions." },
];

type ParamKey = "budget" | "timeframe" | "audience" | "tone" | "channels";

const PARAM_FIELDS: {
  key: ParamKey;
  label: string;
  placeholder: string;
  maxLength: number;
}[] = [
  { key: "budget", label: "Budget", placeholder: "e.g. $200 / month", maxLength: 120 },
  { key: "timeframe", label: "Timeframe", placeholder: "e.g. 4 weeks", maxLength: 120 },
  { key: "audience", label: "Target audience", placeholder: "e.g. fitness creators in the US", maxLength: 300 },
  { key: "tone", label: "Tone", placeholder: "e.g. friendly and bold", maxLength: 120 },
  { key: "channels", label: "Preferred channels", placeholder: "e.g. Instagram, TikTok, email", maxLength: 300 },
];

/**
 * Marketing Strategist builder (mobile). Three numbered sections mirror the
 * web create form: 1) toggle which of your data to share, 2) your goal,
 * 3) optional parameters. An "Estimate cost" affordance shows the worst-case
 * coin spend before Generate, which calls the metered store endpoint and
 * navigates to the generated strategy on success.
 */
export default function NewMarketingStrategyScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  // Pull the live source catalogue + balance from the index loader (cached).
  const idxQ = useQuery({
    queryKey: ["marketing-strategist", "list"],
    queryFn: marketingStrategist.index,
  });

  const sources = idxQ.data?.sources ?? FALLBACK_SOURCES;
  const balance = idxQ.data?.balance;

  const [picked, setPicked] = useState<string[]>(DEFAULT_SOURCES);
  const [goal, setGoal] = useState("");
  const [params, setParams] = useState<Record<ParamKey, string>>({
    budget: "",
    timeframe: "",
    audience: "",
    tone: "",
    channels: "",
  });

  const [estimating, setEstimating] = useState(false);
  const [estimate, setEstimate] = useState<string | null>(null);
  const [generating, setGenerating] = useState(false);

  const input = useMemo(
    () => ({
      goal: goal.trim(),
      sources: picked,
      parameters: Object.fromEntries(
        (Object.entries(params) as [ParamKey, string][])
          .filter(([, v]) => v.trim() !== "")
          .map(([k, v]) => [k, v.trim()]),
      ),
    }),
    [goal, picked, params],
  );

  const idxStatus = (idxQ.error as { status?: number } | null)?.status;
  const disabled: "engine" | "plan" | null =
    idxQ.data?.ai_enabled === false
      ? "engine"
      : idxStatus === 404
        ? "engine"
        : idxStatus === 403
          ? "plan"
          : null;

  const toggleSource = (key: string) => {
    setEstimate(null);
    setPicked((cur) =>
      cur.includes(key) ? cur.filter((k) => k !== key) : [...cur, key],
    );
  };

  const runEstimate = async () => {
    if (!goal.trim()) {
      Alert.alert("Add a goal first", "Tell the strategist what you want to achieve.");
      return;
    }
    setEstimating(true);
    setEstimate(null);
    try {
      const res = await marketingStrategist.estimate(input);
      setEstimate(
        `About ${res.estimate.toLocaleString()} coins · balance ${res.balance.toLocaleString()}`,
      );
    } catch (e: any) {
      if (handlePlanLockedError(e)) return;
      setEstimate(e?.message || "Estimate failed.");
    } finally {
      setEstimating(false);
    }
  };

  const generate = async () => {
    if (!goal.trim()) {
      Alert.alert("Add a goal first", "Tell the strategist what you want to achieve.");
      return;
    }
    if (generating) return;
    setGenerating(true);
    try {
      const res = await marketingStrategist.create(input);
      idxQ.refetch();
      router.replace(`/marketing-strategist/${res.strategy.id}` as never);
    } catch (e: any) {
      if (handlePlanLockedError(e)) return;
      Alert.alert(
        "Couldn't generate",
        e?.message || "The strategy could not be generated right now. Please try again.",
      );
    } finally {
      setGenerating(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "New strategy",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
        }}
      />

      {idxQ.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : disabled ? (
        <AiDisabledNotice feature="Marketing Strategist" variant={disabled} />
      ) : (
        <KeyboardAvoidingView
          style={{ flex: 1 }}
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          keyboardVerticalOffset={insets.top + 44}
        >
          <ScrollView
            contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 120 }}
            keyboardShouldPersistTaps="handled"
          >
            {/* 1. Data sources */}
            <Section colors={colors} index="1" title="Your data">
              <Text style={[styles.help, { color: colors.mutedForeground }]}>
                Toggle the data you want the strategist to ground its plan in.
                Only names and aggregate stats are shared — never private
                contact details.
              </Text>
              <View style={{ gap: 8, marginTop: 12 }}>
                {sources.map((src) => {
                  const on = picked.includes(src.key);
                  return (
                    <Pressable
                      key={src.key}
                      onPress={() => toggleSource(src.key)}
                      style={[
                        styles.sourceRow,
                        {
                          borderColor: on ? colors.primary : colors.border,
                          backgroundColor: on
                            ? colors.primary + "12"
                            : colors.background,
                          borderRadius: colors.radius - 2,
                        },
                      ]}
                    >
                      <View
                        style={[
                          styles.checkbox,
                          {
                            borderColor: on ? colors.primary : colors.border,
                            backgroundColor: on ? colors.primary : "transparent",
                          },
                        ]}
                      >
                        {on ? (
                          <Feather name="check" size={13} color="#fff" />
                        ) : null}
                      </View>
                      <View style={{ flex: 1 }}>
                        <Text
                          style={[styles.sourceLabel, { color: colors.foreground }]}
                        >
                          {src.label}
                        </Text>
                        <Text
                          style={[
                            styles.sourceDesc,
                            { color: colors.mutedForeground },
                          ]}
                        >
                          {src.description}
                        </Text>
                      </View>
                    </Pressable>
                  );
                })}
              </View>
            </Section>

            {/* 2. Goal */}
            <Section colors={colors} index="2" title="Your goal">
              <TextInput
                value={goal}
                onChangeText={(t) => {
                  setGoal(t);
                  setEstimate(null);
                }}
                placeholder="e.g. Grow my newsletter subscribers and drive more clicks to my link-in-bio over the next month."
                placeholderTextColor={colors.mutedForeground}
                multiline
                maxLength={4000}
                style={[
                  styles.textarea,
                  {
                    color: colors.foreground,
                    backgroundColor: colors.background,
                    borderColor: colors.border,
                    borderRadius: colors.radius - 2,
                  },
                ]}
              />
            </Section>

            {/* 3. Parameters */}
            <Section colors={colors} index="3" title="Parameters" optional>
              <View style={{ gap: 12, marginTop: 4 }}>
                {PARAM_FIELDS.map((f) => (
                  <View key={f.key} style={{ gap: 5 }}>
                    <Text style={[styles.paramLabel, { color: colors.mutedForeground }]}>
                      {f.label}
                    </Text>
                    <TextInput
                      value={params[f.key]}
                      onChangeText={(t) => {
                        setParams((p) => ({ ...p, [f.key]: t }));
                        setEstimate(null);
                      }}
                      placeholder={f.placeholder}
                      placeholderTextColor={colors.mutedForeground}
                      maxLength={f.maxLength}
                      style={[
                        styles.input,
                        {
                          color: colors.foreground,
                          backgroundColor: colors.background,
                          borderColor: colors.border,
                          borderRadius: colors.radius - 2,
                        },
                      ]}
                    />
                  </View>
                ))}
              </View>
            </Section>

            {typeof balance === "number" ? (
              <Text style={[styles.balanceLine, { color: colors.mutedForeground }]}>
                Your balance: {balance.toLocaleString()} coins
              </Text>
            ) : null}
          </ScrollView>

          {/* Action bar */}
          <View
            style={[
              styles.actionBar,
              {
                backgroundColor: colors.card,
                borderTopColor: colors.border,
                paddingBottom: insets.bottom + 10,
              },
            ]}
          >
            {estimate ? (
              <Text
                style={[styles.estimateOut, { color: colors.mutedForeground }]}
                numberOfLines={2}
              >
                {estimate}
              </Text>
            ) : null}
            <View style={styles.actionRow}>
              <Pressable
                onPress={runEstimate}
                disabled={estimating || generating}
                style={[
                  styles.estimateBtn,
                  {
                    borderColor: colors.border,
                    opacity: estimating || generating ? 0.6 : 1,
                  },
                ]}
              >
                {estimating ? (
                  <ActivityIndicator color={colors.foreground} size="small" />
                ) : (
                  <Text style={[styles.estimateBtnText, { color: colors.foreground }]}>
                    Estimate cost
                  </Text>
                )}
              </Pressable>
              <Pressable
                onPress={generate}
                disabled={generating || !goal.trim()}
                style={[
                  styles.generateBtn,
                  {
                    backgroundColor:
                      generating || !goal.trim()
                        ? colors.mutedForeground
                        : colors.primary,
                  },
                ]}
              >
                {generating ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : (
                  <>
                    <Feather name="zap" size={15} color="#fff" />
                    <Text style={styles.generateBtnText}>Generate strategy</Text>
                  </>
                )}
              </Pressable>
            </View>
          </View>
        </KeyboardAvoidingView>
      )}
    </View>
  );
}

function Section({
  colors,
  index,
  title,
  optional,
  children,
}: {
  colors: ReturnType<typeof useColors>;
  index: string;
  title: string;
  optional?: boolean;
  children: React.ReactNode;
}) {
  return (
    <View
      style={[
        styles.section,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
        <Text style={{ color: colors.primary }}>{index}. </Text>
        {title}
        {optional ? (
          <Text style={[styles.optional, { color: colors.mutedForeground }]}>
            {"  (optional)"}
          </Text>
        ) : null}
      </Text>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  section: {
    borderWidth: 1,
    padding: 16,
    marginBottom: 14,
  },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 16,
    marginBottom: 4,
  },
  optional: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
  },
  help: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12.5,
    lineHeight: 18,
  },
  sourceRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 12,
    borderWidth: 1,
    padding: 12,
  },
  checkbox: {
    width: 20,
    height: 20,
    borderRadius: 6,
    borderWidth: 1.5,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 1,
  },
  sourceLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
  },
  sourceDesc: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11.5,
    lineHeight: 16,
    marginTop: 2,
  },
  textarea: {
    minHeight: 90,
    borderWidth: 1,
    padding: 12,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    textAlignVertical: "top",
    marginTop: 4,
  },
  paramLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
  },
  input: {
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
  },
  balanceLine: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    textAlign: "center",
    marginTop: 4,
  },
  actionBar: {
    borderTopWidth: 1,
    paddingHorizontal: 16,
    paddingTop: 12,
    gap: 8,
  },
  estimateOut: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
  },
  actionRow: {
    flexDirection: "row",
    gap: 10,
  },
  estimateBtn: {
    borderWidth: 1,
    paddingHorizontal: 16,
    height: 48,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
  },
  estimateBtnText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
  },
  generateBtn: {
    flex: 1,
    flexDirection: "row",
    gap: 8,
    height: 48,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
  },
  generateBtnText: {
    color: "#fff",
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
  },
});
