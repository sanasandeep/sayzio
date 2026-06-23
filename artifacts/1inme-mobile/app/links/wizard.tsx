import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { UpgradeLockBadge } from "@/components/UpgradeLockBadge";
import { useColors } from "@/hooks/useColors";
import { usePlanFeatures } from "@/hooks/usePlanFeatures";
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";
import { listLinks } from "@/lib/api/links";
import {
  generateWizardPage,
  getWizardQuestions,
  getWizardTaxonomy,
  type WizardQuestion,
} from "@/lib/api/wizard";

type Step = "category" | "page_type" | "industry" | "questions";

// Mirrors Link::BIOLINK_FAMILY on the server — the wizard always produces a
// link of one of these types, so they all count toward the `max_biolinks` cap.
const BIOLINK_FAMILY = new Set<string>([
  "biolink",
  "conversational",
  "slides",
  "ai_chat",
  "restaurant_menu",
]);

export default function BiolinkWizardScreen() {
  const colors = useColors();
  const router = useRouter();
  const plan = usePlanFeatures();
  const params = useLocalSearchParams<{
    prefillCategory?: string;
    prefillAnswers?: string;
  }>();

  const [step, setStep] = useState<Step>("category");
  const [category, setCategory] = useState<string | null>(null);
  const [pageType, setPageType] = useState<string | null>(null);
  const [industry, setIndustry] = useState<string | null>(null);
  const [answers, setAnswers] = useState<Record<string, string>>({});

  // When arriving from a card/brochure scan, the review screen passes the
  // seeded answers (and a category) so the user lands a step in with their
  // details pre-filled. Applied once; answers persist as they pick a page type.
  const prefilled = useRef(false);
  useEffect(() => {
    if (prefilled.current) return;
    if (!params.prefillCategory && !params.prefillAnswers) return;
    prefilled.current = true;
    if (params.prefillAnswers) {
      try {
        const parsed = JSON.parse(params.prefillAnswers) as Record<
          string,
          unknown
        >;
        const seeded: Record<string, string> = {};
        for (const [k, v] of Object.entries(parsed)) {
          if (typeof v === "string" && v.trim() !== "") seeded[k] = v;
        }
        if (Object.keys(seeded).length) setAnswers(seeded);
      } catch {
        // Ignore malformed prefill — fall back to a blank wizard.
      }
    }
    if (typeof params.prefillCategory === "string" && params.prefillCategory) {
      setCategory(params.prefillCategory);
      setStep("page_type");
    }
  }, [params.prefillCategory, params.prefillAnswers]);

  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const taxonomyQ = useQuery({
    queryKey: ["wizard-taxonomy"],
    queryFn: getWizardTaxonomy,
  });

  const pageTypes = useMemo(
    () => (category ? (taxonomyQ.data?.page_types[category] ?? []) : []),
    [taxonomyQ.data, category],
  );
  const industries = useMemo(
    () =>
      category && pageType
        ? (taxonomyQ.data?.industries[`${category}.${pageType}`] ?? [])
        : [],
    [taxonomyQ.data, category, pageType],
  );

  // Questions load once the combo is locked in (on the Q&A step).
  const questionsQ = useQuery({
    queryKey: ["wizard-questions", category, pageType, industry],
    queryFn: () =>
      getWizardQuestions({
        category: category!,
        page_type: pageType!,
        industry,
      }),
    enabled: step === "questions" && !!category && !!pageType,
  });

  // The wizard always produces a biolink-family link, so its real upfront
  // plan gate is the `max_biolinks` / `max_links` quota (mirrors the server
  // BiolinkWizardController::generate guard). Count current usage so we can
  // surface the lock BEFORE the user invests time answering questions.
  const linksQ = useQuery({
    queryKey: ["links", "wizard-quota"],
    queryFn: () => listLinks({ per_page: 100 }),
    staleTime: 60 * 1000,
  });

  const usedLinks = linksQ.data?.meta.total ?? 0;
  const usedBiolinks = useMemo(
    () =>
      (linksQ.data?.items ?? []).filter((l) => BIOLINK_FAMILY.has(l.type))
        .length,
    [linksQ.data],
  );

  // Only consider a quota "reached" once BOTH plan data and the usage count
  // have loaded — fail open so we never put up a false barrier.
  const quotaReady = plan.ready && !linksQ.isLoading && !linksQ.isError;
  const biolinkCap = plan.numericLimit("max_biolinks");
  const linkCap = plan.numericLimit("max_links");
  const biolinkLocked =
    quotaReady && plan.isQuotaReached("max_biolinks", usedBiolinks);
  const linkLocked = quotaReady && plan.isQuotaReached("max_links", usedLinks);
  const quotaLocked = biolinkLocked || linkLocked;

  const lockMessage = biolinkLocked
    ? `You've reached your plan's Link in Bio limit${
        biolinkCap !== null ? ` (${biolinkCap})` : ""
      }. Upgrade to build more.`
    : `You've reached your plan's link limit${
        linkCap !== null ? ` (${linkCap})` : ""
      }. Upgrade for more links.`;

  function promptQuotaUpgrade() {
    showUpgradePrompt({ message: lockMessage });
  }

  function reset(to: Step) {
    setError(null);
    setStep(to);
  }

  function pickCategory(slug: string) {
    setCategory(slug);
    setPageType(null);
    setIndustry(null);
    setAnswers({});
    reset("page_type");
  }

  function pickPageType(slug: string) {
    setPageType(slug);
    setIndustry(null);
    setAnswers({});
    const hasIndustry =
      (taxonomyQ.data?.industries[`${category}.${slug}`] ?? []).length > 0;
    reset(hasIndustry ? "industry" : "questions");
  }

  function pickIndustry(slug: string | null) {
    setIndustry(slug);
    setAnswers({});
    reset("questions");
  }

  function goBack() {
    setError(null);
    if (step === "page_type") reset("category");
    else if (step === "industry") reset("page_type");
    else if (step === "questions") reset(industries.length ? "industry" : "page_type");
    else router.back();
  }

  async function onGenerate() {
    if (!category || !pageType) return;
    if (quotaLocked) {
      promptQuotaUpgrade();
      return;
    }
    setError(null);
    setBusy(true);
    try {
      const link = await generateWizardPage({
        category,
        page_type: pageType,
        industry,
        answers,
      });
      router.replace(`/links/${link.id}/blocks` as any);
    } catch (e: any) {
      if (handlePlanLockedError(e)) {
        setError(null);
      } else {
        setError(e?.message || "Failed to generate your page");
      }
    } finally {
      setBusy(false);
    }
  }

  const stepIndex =
    step === "category"
      ? 0
      : step === "page_type"
        ? 1
        : step === "industry"
          ? 2
          : 3;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ headerShown: true, title: "Guided Link in Bio" }}
      />
      <ScrollView contentContainerStyle={styles.body}>
        <View style={{ gap: 6 }}>
          <Text style={[styles.eyebrow, { color: colors.primary }]}>
            Step {stepIndex + 1} of 4
          </Text>
          <Text style={[styles.heading, { color: colors.foreground }]}>
            {step === "category"
              ? "What are you building?"
              : step === "page_type"
                ? "Pick a page type"
                : step === "industry"
                  ? "What's your niche?"
                  : "Tell us the details"}
          </Text>
          <Text style={[styles.sub, { color: colors.mutedForeground }]}>
            {step === "questions"
              ? "We'll auto-build your page from these answers — tweak any block afterwards."
              : "We'll generate an opinionated page tailored to your choice."}
          </Text>
        </View>

        {quotaLocked ? (
          <Pressable
            onPress={promptQuotaUpgrade}
            style={[
              styles.lockBanner,
              {
                backgroundColor: colors.primary + "12",
                borderColor: colors.primary + "44",
                borderRadius: colors.radius,
              },
            ]}
          >
            <View style={{ flex: 1, gap: 2 }}>
              <Text style={[styles.lockTitle, { color: colors.foreground }]}>
                {biolinkLocked ? "Link in Bio limit reached" : "Link limit reached"}
              </Text>
              <Text style={[styles.lockBody, { color: colors.mutedForeground }]}>
                {lockMessage} Tap to see your options.
              </Text>
            </View>
            <UpgradeLockBadge />
          </Pressable>
        ) : null}

        {taxonomyQ.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : taxonomyQ.isError ? (
          <Text style={{ color: colors.destructive }}>
            Couldn&apos;t load the wizard. Pull back and try again.
          </Text>
        ) : null}

        {step === "category" && taxonomyQ.data
          ? taxonomyQ.data.categories.map((c) => (
              <ChoiceCard
                key={c.slug}
                title={c.label}
                blurb={c.blurb}
                onPress={() => pickCategory(c.slug)}
              />
            ))
          : null}

        {step === "page_type"
          ? pageTypes.map((p) => (
              <ChoiceCard
                key={p.slug}
                title={p.label}
                blurb={p.blurb}
                onPress={() => pickPageType(p.slug)}
              />
            ))
          : null}

        {step === "industry" ? (
          <>
            {industries.map((i) => (
              <ChoiceCard
                key={i.slug}
                title={i.label}
                onPress={() => pickIndustry(i.slug)}
              />
            ))}
            <Pressable onPress={() => pickIndustry(null)}>
              <Text style={[styles.skip, { color: colors.mutedForeground }]}>
                Skip — none of these
              </Text>
            </Pressable>
          </>
        ) : null}

        {step === "questions" ? (
          questionsQ.isLoading ? (
            <ActivityIndicator
              color={colors.primary}
              style={{ marginTop: 24 }}
            />
          ) : questionsQ.isError ? (
            <Text style={{ color: colors.destructive }}>
              Couldn&apos;t load the questions. Go back and retry.
            </Text>
          ) : (
            <View style={{ gap: 14, marginTop: 4 }}>
              {(questionsQ.data?.questions ?? []).map((q) => (
                <QuestionField
                  key={q.key}
                  question={q}
                  value={answers[q.key] ?? ""}
                  onChange={(v) =>
                    setAnswers((prev) => ({ ...prev, [q.key]: v }))
                  }
                />
              ))}

              {error ? (
                <Text style={{ color: colors.destructive }}>{error}</Text>
              ) : null}

              <Button
                label="Generate my page"
                onPress={onGenerate}
                loading={busy}
              />
            </View>
          )
        ) : null}

        {error && step !== "questions" ? (
          <Text style={{ color: colors.destructive }}>{error}</Text>
        ) : null}

        {step !== "category" ? (
          <Pressable onPress={goBack} style={{ marginTop: 8 }}>
            <Text style={[styles.back, { color: colors.mutedForeground }]}>
              ← Back
            </Text>
          </Pressable>
        ) : null}
      </ScrollView>
    </View>
  );
}

function ChoiceCard({
  title,
  blurb,
  onPress,
}: {
  title: string;
  blurb?: string;
  onPress: () => void;
}) {
  const colors = useColors();
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : 1,
        },
      ]}
    >
      <View style={{ flex: 1, gap: 2 }}>
        <Text style={[styles.cardTitle, { color: colors.foreground }]}>
          {title}
        </Text>
        {blurb ? (
          <Text style={[styles.cardBlurb, { color: colors.mutedForeground }]}>
            {blurb}
          </Text>
        ) : null}
      </View>
      <Feather name="chevron-right" size={20} color={colors.mutedForeground} />
    </Pressable>
  );
}

function QuestionField({
  question,
  value,
  onChange,
}: {
  question: WizardQuestion;
  value: string;
  onChange: (v: string) => void;
}) {
  const colors = useColors();
  const label = question.required ? `${question.label} *` : question.label;

  if (question.type === "select" && question.options?.length) {
    return (
      <View style={{ gap: 8 }}>
        <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
          {label}
        </Text>
        <View style={styles.chips}>
          {question.options.map((opt) => {
            const active = value === opt.v;
            return (
              <Pressable
                key={opt.v}
                onPress={() => onChange(active ? "" : opt.v)}
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
                  {opt.l}
                </Text>
              </Pressable>
            );
          })}
        </View>
        {question.help ? (
          <Text style={[styles.fieldHint, { color: colors.mutedForeground }]}>
            {question.help}
          </Text>
        ) : null}
      </View>
    );
  }

  const isImage = question.type === "image";
  const hint = isImage
    ? "Paste an image URL, or leave blank for a themed placeholder."
    : question.help;

  return (
    <TextField
      label={label}
      hint={hint}
      value={value}
      onChangeText={onChange}
      placeholder={
        question.placeholder ??
        (isImage ? "https://…/photo.jpg" : undefined)
      }
      multiline={question.type === "textarea"}
      keyboardType={
        question.type === "email"
          ? "email-address"
          : question.type === "phone"
            ? "phone-pad"
            : question.type === "url" || isImage
              ? "url"
              : "default"
      }
      autoCapitalize={
        question.type === "email" ||
        question.type === "url" ||
        question.type === "color" ||
        isImage
          ? "none"
          : "sentences"
      }
      autoCorrect={question.type !== "email" && question.type !== "url"}
      style={
        question.type === "textarea"
          ? { minHeight: 96, paddingTop: 14, textAlignVertical: "top" }
          : undefined
      }
    />
  );
}

const styles = StyleSheet.create({
  body: { padding: 20, gap: 12, paddingBottom: 48 },
  eyebrow: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    letterSpacing: 0.5,
    textTransform: "uppercase",
  },
  heading: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 24 },
  sub: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
  },
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 16,
    borderWidth: 1,
  },
  cardTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
  cardBlurb: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
  },
  skip: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
    textAlign: "center",
    paddingVertical: 12,
  },
  back: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14 },
  lockBanner: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  lockTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  lockBody: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 16,
  },
  fieldLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  fieldHint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 9,
    borderRadius: 999,
    borderWidth: 1,
  },
  chipText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14 },
});
