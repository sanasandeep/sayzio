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

import { AppIcon } from "@/components/AppIcon";
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
  type WizardIndustry,
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

// Defensive fallback that mirrors BiolinkWizardQuestions::genericIndustries().
// The taxonomy endpoint now always returns an industry list per combo (specific
// or generic), so this only kicks in if that data is unexpectedly missing — the
// industry step must never be blank.
const GENERIC_INDUSTRIES: WizardIndustry[] = [
  { slug: "local", label: "Local / In-person", icon: "fa-store" },
  { slug: "online", label: "Online / Digital", icon: "fa-globe" },
  { slug: "creative", label: "Creative / Media", icon: "fa-palette" },
  { slug: "services", label: "Professional Services", icon: "fa-briefcase" },
  { slug: "community", label: "Community / Nonprofit", icon: "fa-people-group" },
  { slug: "other", label: "Something else", icon: "fa-ellipsis" },
];

// Identity fields surfaced first as "The basics"; everything else is grouped
// into "Links & details". Mirrors the web wizard's question sectioning.
const IDENTITY_KEYS = new Set([
  "display_name",
  "headline",
  "bio",
  "avatar",
  "brand_color",
]);

// Leading icon (FontAwesome name, resolved to a native glyph by AppIcon) per
// question — keyed first, then by input type. Mirrors the web wizard's
// fieldIcon() so the two surfaces feel the same.
function fieldIcon(q: WizardQuestion): string {
  const byKey: Record<string, string> = {
    instagram: "fa-hashtag",
    tiktok: "fa-hashtag",
    twitter: "fa-at",
    whatsapp: "fa-comment-dots",
    phone: "fa-phone",
    address: "fa-location-dot",
    hours: "fa-clock",
    discount_code: "fa-ticket",
  };
  if (byKey[q.key]) return byKey[q.key];
  switch (q.type) {
    case "textarea":
      return "fa-align-left";
    case "select":
      return "fa-list";
    case "color":
      return "fa-palette";
    case "image":
      return "fa-image";
    case "url":
      return "fa-link";
    case "email":
      return "fa-envelope";
    case "phone":
      return "fa-phone";
    default:
      return "fa-pen";
  }
}

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
  // The industry step is always shown — use the combo's list from the taxonomy
  // (specific or generic) and fall back to the generic set if it's missing.
  const industries = useMemo<WizardIndustry[]>(() => {
    if (!category || !pageType) return [];
    const fromTaxonomy =
      taxonomyQ.data?.industries[`${category}.${pageType}`] ?? [];
    return fromTaxonomy.length ? fromTaxonomy : GENERIC_INDUSTRIES;
  }, [taxonomyQ.data, category, pageType]);

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
    // The industry step is always a real step now (generic fallback when a
    // combo has no specific list), matching the web wizard.
    reset("industry");
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
    else if (step === "questions") reset("industry");
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

  // Split the flat question set into friendly sections so the form scans as a
  // couple of short groups instead of one long list — matching the web wizard.
  const questionGroups = useMemo(() => {
    const all = questionsQ.data?.questions ?? [];
    const basics = all.filter((q) => IDENTITY_KEYS.has(q.key));
    const details = all.filter((q) => !IDENTITY_KEYS.has(q.key));
    const groups: {
      title: string;
      desc: string;
      icon: string;
      items: WizardQuestion[];
    }[] = [];
    if (basics.length)
      groups.push({
        title: "The basics",
        desc: "Who the page is for.",
        icon: "fa-user",
        items: basics,
      });
    if (details.length)
      groups.push({
        title: "Links & details",
        desc: "Add what applies — skip the rest.",
        icon: "fa-sliders",
        items: details,
      });
    return groups;
  }, [questionsQ.data]);

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
              : step === "industry"
                ? "Just for picking the right accent and placeholder — totally optional."
                : "We'll generate an opinionated page tailored to your choice."}
          </Text>
        </View>

        {/* Progress bar — mirrors the web wizard's gradient step indicator. */}
        <View
          style={[styles.progressTrack, { backgroundColor: colors.border }]}
        >
          <View
            style={[
              styles.progressFill,
              {
                backgroundColor: colors.primary,
                width: `${((stepIndex + 1) / 4) * 100}%`,
              },
            ]}
          />
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
                icon={c.icon}
                selected={category === c.slug}
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
                icon={p.icon}
                selected={pageType === p.slug}
                onPress={() => pickPageType(p.slug)}
              />
            ))
          : null}

        {step === "industry" ? (
          <>
            <View style={styles.industryGrid}>
              {industries.map((i) => (
                <IndustryTile
                  key={i.slug}
                  label={i.label}
                  icon={i.icon}
                  selected={industry === i.slug}
                  onPress={() => pickIndustry(i.slug)}
                />
              ))}
            </View>
            <Pressable onPress={() => pickIndustry(null)}>
              <Text style={[styles.skip, { color: colors.primary }]}>
                Skip this step
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
            <View style={{ gap: 16, marginTop: 4 }}>
              {questionGroups.map((group) => (
                <View
                  key={group.title}
                  style={[
                    styles.section,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  <View
                    style={[
                      styles.sectionHeader,
                      { borderBottomColor: colors.border },
                    ]}
                  >
                    <View
                      style={[
                        styles.sectionIcon,
                        { backgroundColor: colors.primary + "22" },
                      ]}
                    >
                      <AppIcon
                        name={group.icon}
                        size={16}
                        color={colors.primary}
                      />
                    </View>
                    <View style={{ flex: 1, gap: 1 }}>
                      <Text
                        style={[
                          styles.sectionTitle,
                          { color: colors.foreground },
                        ]}
                      >
                        {group.title}
                      </Text>
                      <Text
                        style={[
                          styles.sectionDesc,
                          { color: colors.mutedForeground },
                        ]}
                      >
                        {group.desc}
                      </Text>
                    </View>
                  </View>

                  <View style={styles.sectionBody}>
                    {group.items.map((q) => (
                      <QuestionField
                        key={q.key}
                        question={q}
                        value={answers[q.key] ?? ""}
                        onChange={(v) =>
                          setAnswers((prev) => ({ ...prev, [q.key]: v }))
                        }
                      />
                    ))}
                  </View>
                </View>
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
  icon,
  selected,
  onPress,
}: {
  title: string;
  blurb?: string;
  icon?: string;
  selected?: boolean;
  onPress: () => void;
}) {
  const colors = useColors();
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.card,
        {
          backgroundColor: selected ? colors.primary + "14" : colors.card,
          borderColor: selected ? colors.primary : colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : 1,
        },
      ]}
    >
      {icon ? (
        <View
          style={[
            styles.iconBox,
            {
              backgroundColor: selected ? colors.primary : colors.primary + "22",
            },
          ]}
        >
          <AppIcon
            name={icon}
            size={20}
            color={selected ? colors.primaryForeground : colors.primary}
          />
        </View>
      ) : null}
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
      <Feather
        name="chevron-right"
        size={20}
        color={selected ? colors.primary : colors.mutedForeground}
      />
    </Pressable>
  );
}

function IndustryTile({
  label,
  icon,
  selected,
  onPress,
}: {
  label: string;
  icon?: string;
  selected?: boolean;
  onPress: () => void;
}) {
  const colors = useColors();
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.industryTile,
        {
          backgroundColor: selected ? colors.primary + "14" : colors.card,
          borderColor: selected ? colors.primary : colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : 1,
        },
      ]}
    >
      <View
        style={[
          styles.iconBox,
          {
            backgroundColor: selected ? colors.primary : colors.primary + "22",
          },
        ]}
      >
        <AppIcon
          name={icon ?? "fa-tag"}
          size={18}
          color={selected ? colors.primaryForeground : colors.primary}
        />
      </View>
      <Text
        style={[
          styles.industryLabel,
          { color: selected ? colors.foreground : colors.mutedForeground },
        ]}
      >
        {label}
      </Text>
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
  const icon = fieldIcon(question);

  if (question.type === "select" && question.options?.length) {
    return (
      <View style={{ gap: 8 }}>
        <View style={styles.fieldLabelRow}>
          <AppIcon name={icon} size={13} color={colors.primary} />
          <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
            {label}
          </Text>
        </View>
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
    <View style={{ gap: 6 }}>
      <View style={styles.fieldLabelRow}>
        <AppIcon name={icon} size={13} color={colors.primary} />
        <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
          {label}
        </Text>
      </View>
      <TextField
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
    </View>
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
  progressTrack: {
    height: 6,
    borderRadius: 999,
    overflow: "hidden",
    marginBottom: 2,
  },
  progressFill: { height: "100%", borderRadius: 999 },
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 16,
    borderWidth: 1,
  },
  iconBox: {
    width: 44,
    height: 44,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  cardTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
  cardBlurb: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
  },
  industryGrid: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
  },
  industryTile: {
    width: "47%",
    flexGrow: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 12,
    paddingHorizontal: 12,
    borderWidth: 1,
  },
  industryLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    flex: 1,
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
  section: {
    borderWidth: 1,
    overflow: "hidden",
  },
  sectionHeader: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
  },
  sectionIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
  },
  sectionTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  sectionDesc: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  sectionBody: { padding: 16, gap: 14 },
  fieldLabelRow: { flexDirection: "row", alignItems: "center", gap: 6 },
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
