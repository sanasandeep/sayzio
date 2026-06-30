import { Feather } from "@expo/vector-icons";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "expo-router";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AiDisabledNotice } from "@/components/AiDisabledNotice";
import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { errorStatus } from "@/lib/api";
import {
  marketingStrategist,
  type StrategyParameters,
  type StrategySource,
} from "@/lib/api/marketingStrategist";
import { isPlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";

const FEATURE_LABEL = "Performer Specialist";

// Preset goal chips → a descriptive goal sentence the freeform field is
// seeded with. "Custom" clears the field for a fully hand-written goal.
const GOAL_PRESETS: { key: string; label: string; goal: string }[] = [
  {
    key: "revenue",
    label: "Grow revenue",
    goal: "Increase revenue and sales conversions from my links and pages.",
  },
  {
    key: "reach",
    label: "Expand reach",
    goal: "Grow my reach and get my content in front of a larger audience.",
  },
  {
    key: "followers",
    label: "Gain followers",
    goal: "Grow my follower and subscriber base across my channels.",
  },
  {
    key: "branding",
    label: "Build brand",
    goal: "Strengthen my brand awareness and build a consistent identity.",
  },
  {
    key: "engagement",
    label: "Boost engagement",
    goal: "Increase engagement and repeat visits across my audience.",
  },
  { key: "custom", label: "Custom", goal: "" },
];

const FALLBACK_SOURCES: StrategySource[] = [
  { key: "links", label: "Links", description: "Your links and pages" },
  { key: "analytics", label: "Analytics", description: "Click & view stats" },
  { key: "audience", label: "Audience", description: "Followers & subscribers" },
  { key: "pixels", label: "Pixels", description: "Tracking pixels" },
  { key: "minds", label: "AI Minds", description: "Your knowledge bases" },
  { key: "brand_kits", label: "Brand Kits", description: "Brand identity" },
  { key: "personas", label: "Personas", description: "AI personas" },
  { key: "companions", label: "Companions", description: "AI companions" },
];

const PARAM_FIELDS: {
  key: keyof StrategyParameters;
  label: string;
  placeholder: string;
}[] = [
  { key: "budget", label: "Budget", placeholder: "e.g. $500 / month" },
  {
    key: "audience",
    label: "Target audience",
    placeholder: "e.g. creators aged 18–34",
  },
  { key: "timeframe", label: "Timeframe", placeholder: "e.g. next 90 days" },
  { key: "tone", label: "Tone", placeholder: "e.g. bold and playful" },
  {
    key: "channels",
    label: "Preferred channels",
    placeholder: "e.g. Instagram, TikTok, email",
  },
];

export default function NewMarketingStrategy() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();

  const indexQuery = useQuery({
    queryKey: ["marketing-strategist", "list"],
    queryFn: () => marketingStrategist.index(),
  });

  const sources = useMemo<StrategySource[]>(
    () =>
      indexQuery.data?.sources && indexQuery.data.sources.length
        ? indexQuery.data.sources
        : FALLBACK_SOURCES,
    [indexQuery.data?.sources],
  );

  const [selectedSources, setSelectedSources] = useState<Record<string, boolean>>(
    {},
  );
  const [preset, setPreset] = useState<string>("revenue");
  const [goal, setGoal] = useState<string>(GOAL_PRESETS[0].goal);
  const [params, setParams] = useState<StrategyParameters>({});
  const [estimate, setEstimate] = useState<number | null>(null);
  const [estimating, setEstimating] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Default every available source on the first load.
  const initialisedRef = useRef(false);
  useEffect(() => {
    if (initialisedRef.current || !sources.length) return;
    initialisedRef.current = true;
    const init: Record<string, boolean> = {};
    for (const s of sources) init[s.key] = true;
    setSelectedSources(init);
  }, [sources]);

  if (indexQuery.data && indexQuery.data.ai_enabled === false) {
    return <AiDisabledNotice feature={FEATURE_LABEL} variant="engine" />;
  }
  if (errorStatus(indexQuery.error) === 403) {
    return <AiDisabledNotice feature={FEATURE_LABEL} variant="plan" />;
  }

  const activeSources = Object.entries(selectedSources)
    .filter(([, on]) => on)
    .map(([k]) => k);

  const canSubmit = goal.trim().length > 0 && activeSources.length > 0;

  const buildInput = () => ({
    goal: goal.trim(),
    sources: activeSources,
    parameters: params,
  });

  const setParam = (key: keyof StrategyParameters, value: string) => {
    setParams((p) => ({ ...p, [key]: value }));
    setEstimate(null);
  };

  const pickPreset = (p: (typeof GOAL_PRESETS)[number]) => {
    setPreset(p.key);
    if (p.key !== "custom") setGoal(p.goal);
    else setGoal("");
    setEstimate(null);
  };

  const onEstimate = async () => {
    if (!canSubmit) return;
    setEstimating(true);
    setError(null);
    try {
      const res = await marketingStrategist.estimate(buildInput());
      setEstimate(res.estimate);
    } catch (e) {
      if (isPlanLockedError(e)) {
        showUpgradePrompt(e);
      } else {
        setError(
          e instanceof Error ? e.message : "Couldn't estimate the cost.",
        );
      }
    } finally {
      setEstimating(false);
    }
  };

  const onGenerate = async () => {
    if (!canSubmit) return;
    setSubmitting(true);
    setError(null);
    try {
      const res = await marketingStrategist.create(buildInput());
      queryClient.invalidateQueries({
        queryKey: ["marketing-strategist", "list"],
      });
      router.replace(`/marketing-strategist/${res.strategy.id}` as never);
    } catch (e) {
      if (isPlanLockedError(e)) {
        showUpgradePrompt(e);
      } else {
        setError(
          e instanceof Error
            ? e.message
            : "Couldn't generate the strategy. Try again.",
        );
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (indexQuery.isLoading) {
    return (
      <View
        style={[
          styles.center,
          { backgroundColor: colors.background, paddingTop: insets.top },
        ]}
      >
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top + 8,
          paddingBottom: insets.bottom + 40,
          paddingHorizontal: 20,
          gap: 22,
        }}
        keyboardShouldPersistTaps="handled"
      >
        <View style={{ gap: 6 }}>
          <Text style={[styles.title, { color: colors.foreground }]}>
            New strategy
          </Text>
          <Text style={[styles.subtitle, { color: colors.mutedForeground }]}>
            Pick a goal, choose what the strategist can learn from, and add any
            constraints. You&apos;ll get a tailored organic + paid growth plan.
          </Text>
        </View>

        {/* Goal */}
        <View style={{ gap: 10 }}>
          <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
            What&apos;s your goal?
          </Text>
          <View style={styles.chipWrap}>
            {GOAL_PRESETS.map((p) => {
              const active = preset === p.key;
              return (
                <Pressable
                  key={p.key}
                  onPress={() => pickPreset(p)}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: active ? colors.primary : colors.card,
                      borderColor: active ? colors.primary : colors.border,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.chipText,
                      {
                        color: active
                          ? colors.primaryForeground
                          : colors.foreground,
                      },
                    ]}
                  >
                    {p.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
          <TextField
            label="Describe your goal"
            value={goal}
            onChangeText={(t) => {
              setGoal(t);
              setEstimate(null);
            }}
            placeholder="Describe what you want to achieve…"
            multiline
            numberOfLines={4}
            maxLength={4000}
            style={{ minHeight: 96, textAlignVertical: "top" }}
            hint={`${goal.length}/4000`}
          />
        </View>

        {/* Data sources */}
        <View style={{ gap: 8 }}>
          <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
            What can it learn from?
          </Text>
          <Text style={[styles.sectionHint, { color: colors.mutedForeground }]}>
            The strategist only uses the sources you turn on.
          </Text>
          <View
            style={[
              styles.sourcesCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {sources.map((s, i) => (
              <View
                key={s.key}
                style={[
                  styles.sourceRow,
                  i < sources.length - 1 && {
                    borderBottomWidth: StyleSheet.hairlineWidth,
                    borderBottomColor: colors.border,
                  },
                ]}
              >
                <View style={{ flex: 1, gap: 2 }}>
                  <Text
                    style={[styles.sourceLabel, { color: colors.foreground }]}
                  >
                    {s.label}
                  </Text>
                  {s.description ? (
                    <Text
                      style={[
                        styles.sourceDesc,
                        { color: colors.mutedForeground },
                      ]}
                    >
                      {s.description}
                    </Text>
                  ) : null}
                </View>
                <Switch
                  value={!!selectedSources[s.key]}
                  onValueChange={(v) => {
                    setSelectedSources((prev) => ({ ...prev, [s.key]: v }));
                    setEstimate(null);
                  }}
                  trackColor={{ false: colors.border, true: colors.primary }}
                  thumbColor={colors.background}
                />
              </View>
            ))}
          </View>
        </View>

        {/* Parameters */}
        <View style={{ gap: 12 }}>
          <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
            Constraints{" "}
            <Text style={{ color: colors.mutedForeground }}>(optional)</Text>
          </Text>
          {PARAM_FIELDS.map((f) => (
            <TextField
              key={f.key}
              label={f.label}
              value={params[f.key] ?? ""}
              onChangeText={(t) => setParam(f.key, t)}
              placeholder={f.placeholder}
            />
          ))}
        </View>

        {error ? (
          <Text style={{ color: colors.destructive }}>{error}</Text>
        ) : null}

        {estimate !== null ? (
          <View
            style={[
              styles.estimateBox,
              {
                backgroundColor: colors.primary + "14",
                borderColor: colors.primary + "44",
                borderRadius: colors.radius,
              },
            ]}
          >
            <Feather name="zap" size={14} color={colors.primary} />
            <Text style={[styles.estimateText, { color: colors.foreground }]}>
              This will cost up to{" "}
              <Text style={{ fontFamily: "SpaceGrotesk_700Bold" }}>
                {estimate.toLocaleString()} coins
              </Text>
              .
            </Text>
          </View>
        ) : null}

        <View style={{ gap: 10 }}>
          <Button
            label={estimating ? "Estimating…" : "Estimate cost"}
            variant="outline"
            onPress={onEstimate}
            disabled={!canSubmit || estimating || submitting}
            loading={estimating}
          />
          <Button
            label="Generate strategy"
            onPress={onGenerate}
            disabled={!canSubmit || submitting}
            loading={submitting}
          />
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 24 },
  subtitle: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
  },
  sectionTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
  sectionHint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  chipWrap: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 999,
    borderWidth: 1,
  },
  chipText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  sourcesCard: { borderWidth: 1, paddingHorizontal: 14 },
  sourceRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 12,
  },
  sourceLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14 },
  sourceDesc: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  estimateBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 12,
    borderWidth: 1,
  },
  estimateText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, flex: 1 },
});
