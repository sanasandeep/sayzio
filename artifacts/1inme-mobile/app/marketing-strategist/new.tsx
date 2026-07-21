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
import { CoinCostHint, insufficientCoins } from "@/components/CoinCostHint";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { errorStatus } from "@/lib/api";
import {
  marketingStrategist,
  type StrategyParameters,
  type StrategySelections,
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
  { key: "links", label: "Links", description: "Your links and pages", selectable: true },
  { key: "analytics", label: "Analytics", description: "Click & view stats" },
  { key: "audience", label: "Audience", description: "Followers & subscribers" },
  { key: "pixels", label: "Pixels", description: "Tracking pixels", selectable: true },
  { key: "minds", label: "AI Minds", description: "Your AI Minds", selectable: true },
  { key: "brand_kits", label: "AI Brand Kit", description: "Brand identity", selectable: true },
  { key: "personas", label: "AI Personas", description: "AI personas", selectable: true },
  { key: "companions", label: "AI Companions", description: "AI companions", selectable: true },
];

// Single-line text parameters, grouped into sections for a tidier form.
const PARAM_SECTIONS: {
  title: string;
  fields: { key: keyof StrategyParameters; label: string; placeholder: string }[];
}[] = [
  {
    title: "Budget & market",
    fields: [
      { key: "budget", label: "Budget", placeholder: "e.g. 500 / month" },
      { key: "currency", label: "Currency", placeholder: "e.g. USD, EUR, INR" },
      {
        key: "region",
        label: "Region / market",
        placeholder: "e.g. Austin, Texas · India",
      },
      {
        key: "audience",
        label: "Target audience",
        placeholder: "e.g. creators aged 18–34",
      },
    ],
  },
  {
    title: "Timing & voice",
    fields: [
      { key: "timeframe", label: "Timeframe", placeholder: "e.g. next 90 days" },
      { key: "cadence", label: "Posting cadence", placeholder: "e.g. 3 posts / week" },
      { key: "tone", label: "Tone", placeholder: "e.g. bold and playful" },
      {
        key: "brand_voice",
        label: "Brand voice",
        placeholder: "e.g. expert but approachable",
      },
    ],
  },
  {
    title: "Positioning",
    fields: [
      {
        key: "main_offer",
        label: "Main offer / product",
        placeholder: "e.g. paid newsletter",
      },
      {
        key: "competitors",
        label: "Competitors",
        placeholder: "e.g. @rival1, @rival2",
      },
      {
        key: "avoid",
        label: "Things to avoid",
        placeholder: "e.g. no paid ads",
      },
      {
        key: "channels",
        label: "Preferred channels",
        placeholder: "e.g. Instagram, TikTok, email",
      },
    ],
  },
];

const PLAN_TYPES: { key: NonNullable<StrategyParameters["plan_type"]>; label: string }[] = [
  { key: "both", label: "Organic & paid" },
  { key: "organic", label: "Organic only" },
  { key: "paid", label: "Paid only" },
];

const CONTENT_TYPES = [
  "Short-form video",
  "Reels / Shorts",
  "Stories",
  "Long-form video",
  "Live streams",
  "Carousels",
  "Blog posts",
  "Newsletters / Email",
  "Podcasts",
  "Infographics",
  "User-generated content",
  "Webinars",
  "Case studies",
];

const PAID_MEDIA = [
  "Instagram Ads",
  "Facebook Ads",
  "TikTok Ads",
  "YouTube Ads",
  "Google Search Ads",
  "Google Display",
  "LinkedIn Ads",
  "X / Twitter Ads",
  "Pinterest Ads",
  "Snapchat Ads",
  "Local newspaper",
  "Digital newspaper",
  "Influencer partnerships",
  "Podcast sponsorships",
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
  // Per-source chosen item IDs. An empty list = "use all".
  const [selectedItems, setSelectedItems] = useState<StrategySelections>({});
  const [expanded, setExpanded] = useState<Record<string, boolean>>({});
  const [preset, setPreset] = useState<string>("revenue");
  const [goal, setGoal] = useState<string>(GOAL_PRESETS[0].goal);
  const [params, setParams] = useState<StrategyParameters>({ plan_type: "both" });
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
  // Shared pre-run affordability check (Task #4178): once the creator has
  // an estimate, block Generate when the wallet can't cover it instead of
  // letting the run fail with an insufficient-credits error.
  const balance =
    typeof indexQuery.data?.balance === "number"
      ? indexQuery.data.balance
      : null;
  const shortOnCoins = insufficientCoins(estimate, balance);

  const buildInput = () => ({
    goal: goal.trim(),
    sources: activeSources,
    selections: selectedItems,
    parameters: params,
  });

  const setParam = (key: keyof StrategyParameters, value: string) => {
    setParams((p) => ({ ...p, [key]: value }));
    setEstimate(null);
  };

  const toggleMulti = (key: "content_types" | "paid_media", value: string) => {
    setParams((p) => {
      const current = (p[key] ?? []) as string[];
      const next = current.includes(value)
        ? current.filter((v) => v !== value)
        : [...current, value];
      return { ...p, [key]: next };
    });
    setEstimate(null);
  };

  const toggleItem = (sourceKey: string, id: number) => {
    setSelectedItems((prev) => {
      const current = prev[sourceKey] ?? [];
      const next = current.includes(id)
        ? current.filter((n) => n !== id)
        : [...current, id];
      const out = { ...prev };
      if (next.length) out[sourceKey] = next;
      else delete out[sourceKey];
      return out;
    });
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
            The strategist only uses the sources you turn on. For sources with
            items, pick a few or leave all unselected to use everything.
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
            {sources.map((s, i) => {
              const on = !!selectedSources[s.key];
              const hasItems =
                !!s.selectable && !!s.items && s.items.length > 0;
              const isOpen = !!expanded[s.key];
              const chosen = selectedItems[s.key] ?? [];
              return (
                <View
                  key={s.key}
                  style={
                    i < sources.length - 1 && {
                      borderBottomWidth: StyleSheet.hairlineWidth,
                      borderBottomColor: colors.border,
                    }
                  }
                >
                  <View style={styles.sourceRow}>
                    <View style={{ flex: 1, gap: 2 }}>
                      <Text
                        style={[
                          styles.sourceLabel,
                          { color: colors.foreground },
                        ]}
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
                          {hasItems && chosen.length > 0
                            ? ` · ${chosen.length} selected`
                            : hasItems
                              ? " · all"
                              : ""}
                        </Text>
                      ) : null}
                    </View>
                    {on && hasItems ? (
                      <Pressable
                        onPress={() =>
                          setExpanded((prev) => ({
                            ...prev,
                            [s.key]: !prev[s.key],
                          }))
                        }
                        hitSlop={8}
                        style={styles.chooseBtn}
                      >
                        <Text
                          style={[styles.chooseText, { color: colors.primary }]}
                        >
                          {isOpen ? "Hide" : "Choose"}
                        </Text>
                        <Feather
                          name={isOpen ? "chevron-up" : "chevron-down"}
                          size={14}
                          color={colors.primary}
                        />
                      </Pressable>
                    ) : null}
                    <Switch
                      value={on}
                      onValueChange={(v) => {
                        setSelectedSources((prev) => ({ ...prev, [s.key]: v }));
                        setEstimate(null);
                      }}
                      trackColor={{ false: colors.border, true: colors.primary }}
                      thumbColor={colors.background}
                    />
                  </View>

                  {on && hasItems && isOpen ? (
                    <View style={styles.itemList}>
                      {s.items!.map((item) => {
                        const picked = chosen.includes(item.id);
                        return (
                          <Pressable
                            key={item.id}
                            onPress={() => toggleItem(s.key, item.id)}
                            style={[
                              styles.itemRow,
                              {
                                backgroundColor: picked
                                  ? colors.primary + "14"
                                  : colors.background,
                                borderColor: picked
                                  ? colors.primary
                                  : colors.border,
                                borderRadius: colors.radius,
                              },
                            ]}
                          >
                            <View
                              style={[
                                styles.checkbox,
                                {
                                  borderColor: picked
                                    ? colors.primary
                                    : colors.border,
                                  backgroundColor: picked
                                    ? colors.primary
                                    : "transparent",
                                },
                              ]}
                            >
                              {picked ? (
                                <Feather
                                  name="check"
                                  size={12}
                                  color={colors.primaryForeground}
                                />
                              ) : null}
                            </View>
                            <View style={{ flex: 1 }}>
                              <Text
                                numberOfLines={1}
                                style={[
                                  styles.itemLabel,
                                  { color: colors.foreground },
                                ]}
                              >
                                {item.label}
                              </Text>
                              {item.sub ? (
                                <Text
                                  numberOfLines={1}
                                  style={[
                                    styles.itemSub,
                                    { color: colors.mutedForeground },
                                  ]}
                                >
                                  {item.sub}
                                </Text>
                              ) : null}
                            </View>
                          </Pressable>
                        );
                      })}
                    </View>
                  ) : null}
                </View>
              );
            })}
          </View>
        </View>

        {/* Parameters */}
        <View style={{ gap: 18 }}>
          <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
            Constraints{" "}
            <Text style={{ color: colors.mutedForeground }}>(optional)</Text>
          </Text>

          {PARAM_SECTIONS.map((section) => (
            <View key={section.title} style={{ gap: 12 }}>
              <Text style={[styles.groupTitle, { color: colors.mutedForeground }]}>
                {section.title.toUpperCase()}
              </Text>
              {section.fields.map((f) => (
                <TextField
                  key={f.key}
                  label={f.label}
                  value={(params[f.key] as string) ?? ""}
                  onChangeText={(t) => setParam(f.key, t)}
                  placeholder={f.placeholder}
                />
              ))}
            </View>
          ))}

          {/* Plan focus */}
          <View style={{ gap: 8 }}>
            <Text style={[styles.groupTitle, { color: colors.mutedForeground }]}>
              PLAN FOCUS
            </Text>
            <View style={styles.chipWrap}>
              {PLAN_TYPES.map((pt) => {
                const active = (params.plan_type ?? "both") === pt.key;
                return (
                  <Pressable
                    key={pt.key}
                    onPress={() => {
                      setParams((p) => ({ ...p, plan_type: pt.key }));
                      setEstimate(null);
                    }}
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
                      {pt.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </View>

          {/* Content types */}
          <View style={{ gap: 8 }}>
            <Text style={[styles.groupTitle, { color: colors.mutedForeground }]}>
              CONTENT TYPES
            </Text>
            <View style={styles.chipWrap}>
              {CONTENT_TYPES.map((ct) => {
                const active = (params.content_types ?? []).includes(ct);
                return (
                  <Pressable
                    key={ct}
                    onPress={() => toggleMulti("content_types", ct)}
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
                      {ct}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </View>

          {/* Paid media */}
          <View style={{ gap: 8 }}>
            <Text style={[styles.groupTitle, { color: colors.mutedForeground }]}>
              PAID MEDIA (INCL. LOCAL & DIGITAL NEWSPAPERS)
            </Text>
            <View style={styles.chipWrap}>
              {PAID_MEDIA.map((pm) => {
                const active = (params.paid_media ?? []).includes(pm);
                return (
                  <Pressable
                    key={pm}
                    onPress={() => toggleMulti("paid_media", pm)}
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
                      {pm}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </View>
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

        <CoinCostHint
          cost={estimate}
          balance={balance}
          actionLabel="this strategy"
          verb="generate"
        />

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
            disabled={!canSubmit || submitting || shortOnCoins}
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
  groupTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.5,
  },
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
  chooseBtn: { flexDirection: "row", alignItems: "center", gap: 2 },
  chooseText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  itemList: { gap: 6, paddingBottom: 12 },
  itemRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 10,
    borderWidth: 1,
  },
  checkbox: {
    width: 18,
    height: 18,
    borderRadius: 5,
    borderWidth: 1.5,
    alignItems: "center",
    justifyContent: "center",
  },
  itemLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  itemSub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  estimateBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 12,
    borderWidth: 1,
  },
  estimateText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, flex: 1 },
});
