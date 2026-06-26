import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import {
  Stack,
  useFocusEffect,
  useLocalSearchParams,
  useRouter,
} from "expo-router";
import * as ImagePicker from "expo-image-picker";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { AppIcon } from "@/components/AppIcon";
import { Button } from "@/components/Button";
import { DictationMic } from "@/components/DictationMic";
import { PreviewBlueprint } from "@/components/PreviewBlueprint";
import {
  onVoiceAction,
  setVoiceSurface,
} from "@/components/VoiceAssistant";
import type { VoiceClientAction } from "@/lib/api/voice";
import { TextField } from "@/components/TextField";
import type { PreviewLayoutCell } from "@/lib/api/cardTemplates";
import { UpgradeLockBadge } from "@/components/UpgradeLockBadge";
import { useColors } from "@/hooks/useColors";
import { usePlanFeatures } from "@/hooks/usePlanFeatures";
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";
import { listLinks } from "@/lib/api/links";
import {
  aiGenerateWizardPage,
  generateWizardPage,
  getWizardQuestions,
  getWizardResources,
  getWizardStartingDesigns,
  getWizardTaxonomy,
  uploadWizardImage,
  type WizardIndustry,
  type WizardPersona,
  type WizardQuestion,
  type WizardStartingDesign,
  type WizardVaultFile,
} from "@/lib/api/wizard";

// The five-step guided flow, mirroring the web wizard (PersonaCatalog taxonomy):
//   persona group → persona (+ optional inline niche) → starting design →
//   basic profile & branding → additional content.
type Step = "group" | "persona" | "design" | "basics" | "additional";

const STEP_ORDER: Step[] = [
  "group",
  "persona",
  "design",
  "basics",
  "additional",
];
const TOTAL_STEPS = STEP_ORDER.length;

// Mirrors Link::BIOLINK_FAMILY on the server — the wizard always produces a
// link of one of these types, so they all count toward the `max_biolinks` cap.
const BIOLINK_FAMILY = new Set<string>([
  "biolink",
  "conversational",
  "slides",
  "ai_chat",
  "restaurant_menu",
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

// Pick a crop aspect ratio for an image question so the upload lands at the
// right shape on the generated page without manual fiddling. Avatar/profile/
// logo images crop square (they render as circles on the page); cover/banner/
// header images crop to a wide banner. Anything unrecognised falls back to a
// free-form crop (undefined → no aspect lock). Matched on the question key and
// label since the server reuses the `avatar` key for both profile and cover
// images, distinguishing them only by label.
//
// NB: expo-image-picker's `aspect` only constrains the crop on Android; on iOS
// the edit UI always crops to a square. That still gives avatars the right
// shape everywhere — only wide covers fall back to square on iOS.
function imageCropAspect(q: WizardQuestion): [number, number] | undefined {
  if (q.type !== "image") return undefined;
  const hay = `${q.key} ${q.label}`.toLowerCase();
  if (/cover|banner|header|hero/.test(hay)) return [16, 9];
  if (/avatar|profile|logo|headshot|portrait|photo/.test(hay)) return [1, 1];
  return undefined;
}

export default function BiolinkWizardScreen() {
  const colors = useColors();
  const router = useRouter();
  const plan = usePlanFeatures();
  const params = useLocalSearchParams<{
    prefillCategory?: string;
    prefillAnswers?: string;
  }>();

  const [step, setStep] = useState<Step>("group");
  const [group, setGroup] = useState<string | null>(null);
  const [persona, setPersona] = useState<string | null>(null);
  const [industry, setIndustry] = useState<string | null>(null);
  // The chosen starting design (null = "Start from scratch"). Seeds the page
  // snapshot before the recipe/AI layers the user's answers on top.
  const [templateId, setTemplateId] = useState<number | null>(null);
  const [answers, setAnswers] = useState<Record<string, string>>({});
  // Optional custom alias (Custom URL), carried through to the generator. Blank
  // = the server auto-generates one, exactly as before. Mirrors the web wizard.
  const [alias, setAlias] = useState("");
  const [aliasError, setAliasError] = useState<string | null>(null);

  const [busy, setBusy] = useState(false);
  const [aiBusy, setAiBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Per-field validation errors (key → message), mirroring the web wizard's
  // inline error bag. Populated from the server's 422 `details` so a failed
  // generate highlights exactly which fields to fix instead of a generic toast.
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  // AI auto-draft grounding selections. Persisted only in memory (the mobile
  // wizard is stateless) and sent with the ai-generate payload.
  const [selectedMinds, setSelectedMinds] = useState<Set<number>>(new Set());
  const [includePlatformMind, setIncludePlatformMind] = useState(false);
  const [selectedFiles, setSelectedFiles] = useState<Set<number>>(new Set());

  const taxonomyQ = useQuery({
    queryKey: ["wizard-taxonomy"],
    queryFn: getWizardTaxonomy,
  });

  // AI-draft resources (Minds + vault files + engine flag). Loaded lazily once
  // the user reaches a content step; the auto-draft UI only appears when the
  // server reports the AI engine is enabled (OFF by default in dev).
  const resourcesQ = useQuery({
    queryKey: ["wizard-resources"],
    queryFn: getWizardResources,
    enabled: step === "basics" || step === "additional",
    staleTime: 60 * 1000,
  });
  const aiEnabled = resourcesQ.data?.ai_enabled ?? false;

  // Step 1: the personas inside the chosen group (the second-level tiles).
  const personas = useMemo<WizardPersona[]>(
    () => (group ? (taxonomyQ.data?.personas[group] ?? []) : []),
    [taxonomyQ.data, group],
  );
  // The selected persona object — carries the legacy (category, page_type)
  // combo the (unchanged) questions/generate endpoints are driven by.
  const personaObj = useMemo<WizardPersona | null>(
    () => personas.find((p) => p.slug === persona) ?? null,
    [personas, persona],
  );
  const category = personaObj?.category ?? null;
  const pageType = personaObj?.page_type ?? null;

  // The optional niche refinement, folded into the persona step. The taxonomy
  // only returns a list for personas whose combo has a *specific* industries()
  // set, so an empty list means "no refinement here" and the chips are hidden.
  const industries = useMemo<WizardIndustry[]>(() => {
    if (!persona) return [];
    return taxonomyQ.data?.industries_by_persona[persona] ?? [];
  }, [taxonomyQ.data, persona]);

  // Step 2: persona-tagged starting designs (+ a "Start from scratch" card the
  // client renders alongside). Loaded once the user reaches the design step.
  const designsQ = useQuery({
    queryKey: ["wizard-starting-designs", persona],
    queryFn: () => getWizardStartingDesigns({ persona: persona! }),
    enabled: step === "design" && !!persona,
  });

  // Questions load once the combo is locked in (on the two content steps).
  const questionsQ = useQuery({
    queryKey: ["wizard-questions", category, pageType, industry],
    queryFn: () =>
      getWizardQuestions({
        category: category!,
        page_type: pageType!,
        industry,
      }),
    enabled:
      (step === "basics" || step === "additional") && !!category && !!pageType,
  });

  // When arriving from a card/brochure scan, the review screen passes the seeded
  // answers plus a legacy category. We seed the answers and, once the taxonomy
  // has loaded, map that category to the first matching persona so the user
  // lands on the starting-design step with their details pre-filled. Applied
  // once. Falls back to a blank wizard if no persona matches.
  const prefilled = useRef(false);
  useEffect(() => {
    if (prefilled.current) return;
    if (!params.prefillCategory && !params.prefillAnswers) return;
    if (params.prefillCategory && !taxonomyQ.data) return; // wait for taxonomy

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

    const cat = params.prefillCategory;
    if (typeof cat === "string" && cat && taxonomyQ.data) {
      for (const [groupKey, list] of Object.entries(taxonomyQ.data.personas)) {
        const match = list.find((p) => p.category === cat);
        if (match) {
          setGroup(groupKey);
          setPersona(match.slug);
          setStep("design");
          break;
        }
      }
    }
  }, [params.prefillCategory, params.prefillAnswers, taxonomyQ.data]);

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
    setFieldErrors({});
    setAliasError(null);
    setStep(to);
  }

  // Step 0 → 1: pick a persona group, reset everything downstream.
  function pickGroup(key: string) {
    setGroup(key);
    setPersona(null);
    setIndustry(null);
    setTemplateId(null);
    setAnswers({});
    setAlias("");
    setSelectedMinds(new Set());
    setIncludePlatformMind(false);
    setSelectedFiles(new Set());
    reset("persona");
  }

  // Select a persona WITHOUT advancing — the optional niche refinement is shown
  // inline below the cards, and a Continue button moves on. Changing the persona
  // resets the niche, template and answers since the question set changes.
  function selectPersona(slug: string) {
    if (slug === persona) return;
    setPersona(slug);
    setIndustry(null);
    setTemplateId(null);
    setAnswers({});
    setError(null);
    setFieldErrors({});
    setSelectedMinds(new Set());
    setIncludePlatformMind(false);
    setSelectedFiles(new Set());
  }

  // Toggle the inline niche chip (tap again to clear). Purely drives the
  // placeholder imagery/accent; the question set is barely affected so answers
  // are preserved across a toggle.
  function toggleIndustry(slug: string) {
    setIndustry((cur) => (cur === slug ? null : slug));
  }

  function continueFromPersona() {
    if (!persona) return;
    reset("design");
  }

  // Step 2: choose a starting design (or "Start from scratch" → null) and
  // advance. A locked template can't be selected — prompt to upgrade instead.
  function pickTemplate(design: WizardStartingDesign | null) {
    if (design?.locked) {
      showUpgradePrompt({
        message: `"${design.name}" is available on a higher plan. Upgrade to start from this design.`,
      });
      return;
    }
    setTemplateId(design ? design.id : null);
    reset("basics");
  }

  function goBack() {
    setError(null);
    if (step === "persona") reset("group");
    else if (step === "design") reset("persona");
    else if (step === "basics") reset("design");
    else if (step === "additional") reset("basics");
    else router.back();
  }

  // Map a server 422 `details` bag (key → string[] | string) into the flat
  // key → message shape the inline field errors use. Returns true when at
  // least one field error was extracted so callers can short-circuit.
  function applyFieldErrors(e: any): boolean {
    const details = (e?.details ?? e?.errors) as
      | Record<string, unknown>
      | undefined;
    if (!details || typeof details !== "object") return false;
    const next: Record<string, string> = {};
    for (const [k, v] of Object.entries(details)) {
      const msg = Array.isArray(v) ? v[0] : v;
      if (typeof msg === "string" && msg) next[k] = msg;
    }
    if (Object.keys(next).length === 0) return false;
    setFieldErrors(next);
    return true;
  }

  // The custom alias lives outside the question set, so the server's
  // `invalid_alias` 422 is surfaced on its own inline field rather than the
  // per-question error bag. Returns true when an alias error was applied so
  // callers can short-circuit the generic error path.
  function applyAliasError(e: any): boolean {
    const details = (e?.details ?? e?.errors) as
      | Record<string, unknown>
      | undefined;
    const raw = details && typeof details === "object" ? details.alias : undefined;
    const msg = Array.isArray(raw) ? raw[0] : raw;
    if (e?.code === "invalid_alias" || (typeof msg === "string" && msg)) {
      const text =
        (typeof msg === "string" && msg) ||
        e?.message ||
        "That custom URL isn't available. Please choose another.";
      setAliasError(text);
      // Surface it in the banner too: the Custom URL field lives on the basics
      // step but generation runs from the additional step, so the inline error
      // alone wouldn't be visible at the point of failure.
      setError(text);
      return true;
    }
    return false;
  }

  // Local per-step required-field gate mirroring the server's validateAnswers.
  // Blocks advancing past a content step with empty required fields so mobile
  // matches the web wizard's step gating (instead of silently letting the user
  // through to a final-generate 422). Sets inline fieldErrors and returns true
  // only when every required field for the step has a value.
  function validateLocalStep(items: WizardQuestion[]): boolean {
    const next: Record<string, string> = {};
    for (const q of items) {
      if (!q.required) continue;
      const v = (answers[q.key] ?? "").trim();
      if (!v) next[q.key] = `${q.label} is required.`;
    }
    setFieldErrors(next);
    return Object.keys(next).length === 0;
  }

  function continueFromBasics() {
    if (!validateLocalStep(basicsQuestions)) return;
    reset("additional");
  }

  async function onGenerate() {
    if (!category || !pageType) return;
    // Gate this step's required fields locally before hitting the server so the
    // user gets the same inline blocking the basics step has.
    if (!validateLocalStep(additionalQuestions)) {
      setError("Please fix the highlighted fields before generating.");
      return;
    }
    if (quotaLocked) {
      promptQuotaUpgrade();
      return;
    }
    setError(null);
    setFieldErrors({});
    setAliasError(null);
    setBusy(true);
    try {
      const link = await generateWizardPage({
        persona,
        industry,
        template_id: templateId,
        alias: alias.trim() || null,
        answers,
      });
      router.replace(`/links/${link.id}/blocks` as any);
    } catch (e: any) {
      if (handlePlanLockedError(e)) {
        setError(null);
      } else if (applyAliasError(e)) {
        // applyAliasError already set the banner + inline error.
      } else if (applyFieldErrors(e)) {
        setError("Please fix the highlighted fields before generating.");
      } else {
        setError(e?.message || "Failed to generate your page");
      }
    } finally {
      setBusy(false);
    }
  }

  // ── Voice control ──────────────────────────────────────────────
  // Spoken commands ("fill in my name", "next step", "build it") arrive
  // as client_action intents from the floating assistant. We keep the
  // newest handler in a ref so the listener (registered once on focus)
  // always acts on current state without re-subscribing each render.
  const voiceHandlerRef = useRef<(a: VoiceClientAction) => void>(() => {});
  voiceHandlerRef.current = (a: VoiceClientAction) => {
    if (a.type === "wizard_set_answer" && "field" in a) {
      const field = String((a as { field: unknown }).field);
      const value = (a as { value: unknown }).value;
      setAnswers((prev) => ({ ...prev, [field]: String(value ?? "") }));
      setFieldErrors((prev) => {
        if (!prev[field]) return prev;
        const { [field]: _drop, ...rest } = prev;
        return rest;
      });
    } else if (a.type === "wizard_advance") {
      const dir = (a as { direction?: string }).direction;
      if (dir === "back") {
        goBack();
        return;
      }
      if (step === "group") {
        if (group) reset("persona");
      } else if (step === "persona") {
        continueFromPersona();
      } else if (step === "design") {
        reset("basics");
      } else if (step === "basics") {
        continueFromBasics();
      } else if (step === "additional") {
        void onGenerate();
      }
    } else if (a.type === "wizard_generate") {
      void onGenerate();
    }
  };
  useFocusEffect(
    useCallback(() => {
      setVoiceSurface("wizard");
      const off = onVoiceAction((a) => voiceHandlerRef.current(a));
      return () => {
        off();
        setVoiceSurface(null);
      };
    }, []),
  );

  async function onAiGenerate() {
    if (!category || !pageType) return;
    if (quotaLocked) {
      promptQuotaUpgrade();
      return;
    }
    setError(null);
    setFieldErrors({});
    setAliasError(null);
    setAiBusy(true);
    try {
      const link = await aiGenerateWizardPage({
        persona,
        industry,
        template_id: templateId,
        alias: alias.trim() || null,
        answers,
        ai_mind_ids: [...selectedMinds],
        include_platform_mind: includePlatformMind,
        file_ids: [...selectedFiles],
      });
      router.replace(`/links/${link.id}/blocks` as any);
    } catch (e: any) {
      if (handlePlanLockedError(e)) {
        setError(null);
      } else if (applyAliasError(e)) {
        // applyAliasError already set the banner + inline error.
      } else if (applyFieldErrors(e)) {
        setError("Please fix the highlighted fields before drafting.");
      } else {
        setError(e?.message || "The AI couldn't draft your page this time.");
      }
    } finally {
      setAiBusy(false);
    }
  }

  const stepIndex = STEP_ORDER.indexOf(step);

  // The server pre-splits the question set into the two content steps (single
  // source of truth shared with the web wizard): the basic profile & branding
  // fields and everything else.
  const basicsQuestions = questionsQ.data?.basics ?? [];
  const additionalQuestions = questionsQ.data?.additional ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ headerShown: true, title: "Guided Link in Bio" }}
      />
      <ScrollView contentContainerStyle={styles.body}>
        <View style={{ gap: 6 }}>
          <Text style={[styles.eyebrow, { color: colors.primary }]}>
            Step {stepIndex + 1} of {TOTAL_STEPS}
          </Text>
          <Text style={[styles.heading, { color: colors.foreground }]}>
            {step === "group"
              ? "What are you building?"
              : step === "persona"
                ? "Pick the closest match"
                : step === "design"
                  ? "Pick a starting design"
                  : step === "basics"
                    ? "Profile & branding"
                    : "Add your content"}
          </Text>
          <Text style={[styles.sub, { color: colors.mutedForeground }]}>
            {step === "group"
              ? "We'll generate an opinionated page tailored to your choice."
              : step === "persona"
                ? "Choose who you are — then optionally refine your niche."
                : step === "design"
                  ? "Start from a ready-made design, or from scratch."
                  : step === "basics"
                    ? "The essentials visitors see first — name, bio, photo and accent."
                    : "Add what applies — skip the rest. Tweak any block afterwards."}
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
                width: `${((stepIndex + 1) / TOTAL_STEPS) * 100}%`,
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

        {step === "group" && taxonomyQ.data
          ? taxonomyQ.data.groups.map((g) => (
              <ChoiceCard
                key={g.key}
                title={g.label}
                blurb={g.blurb}
                icon={g.icon}
                selected={group === g.key}
                onPress={() => pickGroup(g.key)}
              />
            ))
          : null}

        {step === "persona" ? (
          <>
            {personas.map((p) => (
              <ChoiceCard
                key={p.slug}
                title={p.label}
                blurb={p.blurb}
                icon={p.icon}
                selected={persona === p.slug}
                onPress={() => selectPersona(p.slug)}
              />
            ))}

            {/* Optional niche refinement, folded in — only for personas with a
                specific industries() list (the taxonomy omits the rest). */}
            {persona && industries.length ? (
              <View style={{ gap: 10, marginTop: 6 }}>
                <View style={{ gap: 2 }}>
                  <Text
                    style={[styles.refineTitle, { color: colors.foreground }]}
                  >
                    Refine your niche{" "}
                    <Text style={{ color: colors.mutedForeground }}>
                      · optional
                    </Text>
                  </Text>
                  <Text
                    style={[styles.sub, { color: colors.mutedForeground }]}
                  >
                    Just for the right accent and placeholder — skip if none fit.
                  </Text>
                </View>
                <View style={styles.industryGrid}>
                  {industries.map((i) => (
                    <IndustryTile
                      key={i.slug}
                      label={i.label}
                      icon={i.icon}
                      selected={industry === i.slug}
                      onPress={() => toggleIndustry(i.slug)}
                    />
                  ))}
                </View>
              </View>
            ) : null}

            <Button
              label="Continue"
              onPress={continueFromPersona}
              disabled={!persona}
            />
          </>
        ) : null}

        {step === "design" ? (
          designsQ.isLoading ? (
            <ActivityIndicator
              color={colors.primary}
              style={{ marginTop: 24 }}
            />
          ) : designsQ.isError ? (
            <>
              <Text style={{ color: colors.destructive }}>
                Couldn&apos;t load designs. Go back and retry, or start from
                scratch below.
              </Text>
              {/* Even when template fetch fails, the user can always proceed
                  from scratch so the design step never becomes a dead end. */}
              <StartingDesignCard
                title="Start from scratch"
                blurb="A clean page built from your answers — no template."
                icon="fa-wand-magic-sparkles"
                selected={templateId === null}
                onPress={() => pickTemplate(null)}
              />
            </>
          ) : (
            <>
              {/* Always-available "Start from scratch" option (templateId = null). */}
              <StartingDesignCard
                title="Start from scratch"
                blurb="A clean page built from your answers — no template."
                icon="fa-wand-magic-sparkles"
                selected={templateId === null}
                onPress={() => pickTemplate(null)}
              />

              {(designsQ.data ?? []).map((d) => (
                <StartingDesignCard
                  key={d.id}
                  title={d.name}
                  blurb={d.description}
                  thumbnailUrl={d.thumbnail_url}
                  previewLayout={d.preview_layout}
                  icon="fa-table-cells-large"
                  recommended={d.recommended}
                  locked={d.locked}
                  blocksCount={d.blocks_count}
                  selected={templateId === d.id}
                  onPress={() => pickTemplate(d)}
                />
              ))}
            </>
          )
        ) : null}

        {step === "basics" || step === "additional" ? (
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
              <QuestionSection
                title={
                  step === "basics"
                    ? "Basic profile & branding"
                    : "Additional content"
                }
                desc={
                  step === "basics"
                    ? "Who the page is for."
                    : "Add what applies — skip the rest."
                }
                icon={step === "basics" ? "fa-id-card" : "fa-sliders"}
                items={
                  step === "basics" ? basicsQuestions : additionalQuestions
                }
                answers={answers}
                errors={fieldErrors}
                onChange={(key, v) =>
                  setAnswers((prev) => ({ ...prev, [key]: v }))
                }
                emptyText={
                  step === "basics"
                    ? "Nothing to set up here — continue to add your content."
                    : "No extra fields for this page — generate when you're ready."
                }
              />

              {step === "basics" ? (
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
                      <AppIcon name="fa-link" size={16} color={colors.primary} />
                    </View>
                    <View style={{ flex: 1, gap: 1 }}>
                      <Text
                        style={[styles.sectionTitle, { color: colors.foreground }]}
                      >
                        Custom URL
                      </Text>
                      <Text
                        style={[
                          styles.sectionDesc,
                          { color: colors.mutedForeground },
                        ]}
                      >
                        Optional — leave blank to auto-generate one.
                      </Text>
                    </View>
                  </View>
                  <View style={styles.sectionBody}>
                    <TextField
                      hint="Letters, numbers, dashes and underscores only."
                      error={aliasError ?? undefined}
                      value={alias}
                      onChangeText={(v) => {
                        setAlias(v);
                        if (aliasError) setAliasError(null);
                      }}
                      placeholder="your-custom-url"
                      autoCapitalize="none"
                      autoCorrect={false}
                    />
                  </View>
                </View>
              ) : null}

              {aiEnabled ? (
                <AiDraftResources
                  myMinds={resourcesQ.data?.my_minds ?? []}
                  platformMinds={resourcesQ.data?.platform_minds ?? []}
                  vaultFiles={resourcesQ.data?.vault_files ?? []}
                  selectedMinds={selectedMinds}
                  includePlatformMind={includePlatformMind}
                  selectedFiles={selectedFiles}
                  onToggleMind={(id) =>
                    setSelectedMinds((prev) => {
                      const next = new Set(prev);
                      next.has(id) ? next.delete(id) : next.add(id);
                      return next;
                    })
                  }
                  onTogglePlatform={() =>
                    setIncludePlatformMind((v) => !v)
                  }
                  onToggleFile={(id) =>
                    setSelectedFiles((prev) => {
                      const next = new Set(prev);
                      next.has(id) ? next.delete(id) : next.add(id);
                      return next;
                    })
                  }
                />
              ) : null}

              {error ? (
                <Text style={{ color: colors.destructive }}>{error}</Text>
              ) : null}

              {step === "basics" ? (
                <Button label="Continue" onPress={continueFromBasics} />
              ) : (
                <View style={{ gap: 10 }}>
                  {aiEnabled ? (
                    <Button
                      label="Auto-draft with AI"
                      variant="secondary"
                      onPress={onAiGenerate}
                      loading={aiBusy}
                      disabled={busy}
                    />
                  ) : null}
                  <Button
                    label="Generate my page"
                    onPress={onGenerate}
                    loading={busy}
                    disabled={aiBusy}
                  />
                </View>
              )}
            </View>
          )
        ) : null}

        {error && step !== "basics" && step !== "additional" ? (
          <Text style={{ color: colors.destructive }}>{error}</Text>
        ) : null}

        {step !== "group" ? (
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

// A starting-design choice card for the design step. Shows a template thumbnail
// (or a fallback icon), a "Recommended" badge for persona-matched designs, and
// an upgrade lock for plan-gated templates. Tapping a locked card prompts an
// upgrade rather than selecting it (handled by the parent's pickTemplate).
function StartingDesignCard({
  title,
  blurb,
  icon,
  thumbnailUrl,
  previewLayout,
  recommended,
  locked,
  blocksCount,
  selected,
  onPress,
}: {
  title: string;
  blurb?: string;
  icon: string;
  thumbnailUrl?: string | null;
  previewLayout?: PreviewLayoutCell[][];
  recommended?: boolean;
  locked?: boolean;
  blocksCount?: number;
  selected?: boolean;
  onPress: () => void;
}) {
  const colors = useColors();
  // Prefer a real static thumbnail; otherwise render the auto-generated
  // mini-blueprint of the template's blocks so the card shows the actual
  // layout instead of a generic icon. Falls back to the icon only when
  // neither is available (e.g. "Start from scratch").
  const hasPreview = Array.isArray(previewLayout) && previewLayout.length > 0;
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.card,
        {
          backgroundColor: selected ? colors.primary + "14" : colors.card,
          borderColor: selected ? colors.primary : colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : locked ? 0.7 : 1,
        },
      ]}
    >
      {thumbnailUrl ? (
        <Image
          source={{ uri: thumbnailUrl }}
          style={[
            styles.designThumb,
            { borderColor: colors.border, borderRadius: colors.radius },
          ]}
        />
      ) : hasPreview ? (
        <View
          style={[
            styles.designThumb,
            {
              borderColor: colors.border,
              borderRadius: colors.radius,
              overflow: "hidden",
              backgroundColor: colors.primary + "10",
            },
          ]}
        >
          <PreviewBlueprint rows={previewLayout!} height={62} />
        </View>
      ) : (
        <View
          style={[
            styles.iconBox,
            {
              backgroundColor: selected
                ? colors.primary
                : colors.primary + "22",
            },
          ]}
        >
          <AppIcon
            name={icon}
            size={20}
            color={selected ? colors.primaryForeground : colors.primary}
          />
        </View>
      )}
      <View style={{ flex: 1, gap: 2 }}>
        <View style={styles.designTitleRow}>
          <Text
            style={[styles.cardTitle, { color: colors.foreground }]}
            numberOfLines={1}
          >
            {title}
          </Text>
          {recommended ? (
            <View
              style={[
                styles.designBadge,
                { backgroundColor: colors.primary + "22" },
              ]}
            >
              <Text style={[styles.designBadgeText, { color: colors.primary }]}>
                Recommended
              </Text>
            </View>
          ) : null}
        </View>
        {blurb ? (
          <Text
            style={[styles.cardBlurb, { color: colors.mutedForeground }]}
            numberOfLines={2}
          >
            {blurb}
          </Text>
        ) : null}
        {typeof blocksCount === "number" && blocksCount > 0 ? (
          <Text style={[styles.designMeta, { color: colors.mutedForeground }]}>
            {blocksCount} block{blocksCount === 1 ? "" : "s"}
          </Text>
        ) : null}
      </View>
      {locked ? (
        <UpgradeLockBadge />
      ) : (
        <Feather
          name={selected ? "check-circle" : "chevron-right"}
          size={20}
          color={selected ? colors.primary : colors.mutedForeground}
        />
      )}
    </Pressable>
  );
}

// A titled card grouping a set of question fields for one content step. Falls
// back to a friendly empty note when the step has no fields (so the user can
// still move on / generate).
function QuestionSection({
  title,
  desc,
  icon,
  items,
  answers,
  errors,
  onChange,
  emptyText,
}: {
  title: string;
  desc: string;
  icon: string;
  items: WizardQuestion[];
  answers: Record<string, string>;
  errors: Record<string, string>;
  onChange: (key: string, value: string) => void;
  emptyText: string;
}) {
  const colors = useColors();
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
      <View
        style={[styles.sectionHeader, { borderBottomColor: colors.border }]}
      >
        <View
          style={[styles.sectionIcon, { backgroundColor: colors.primary + "22" }]}
        >
          <AppIcon name={icon} size={16} color={colors.primary} />
        </View>
        <View style={{ flex: 1, gap: 1 }}>
          <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
            {title}
          </Text>
          <Text style={[styles.sectionDesc, { color: colors.mutedForeground }]}>
            {desc}
          </Text>
        </View>
      </View>

      <View style={styles.sectionBody}>
        {items.length ? (
          items.map((q) => (
            <QuestionField
              key={q.key}
              question={q}
              value={answers[q.key] ?? ""}
              error={errors[q.key]}
              onChange={(v) => onChange(q.key, v)}
            />
          ))
        ) : (
          <Text style={[styles.fieldHint, { color: colors.mutedForeground }]}>
            {emptyText}
          </Text>
        )}
      </View>
    </View>
  );
}

function QuestionField({
  question,
  value,
  error,
  onChange,
}: {
  question: WizardQuestion;
  value: string;
  error?: string;
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
        {error ? (
          <Text style={[styles.fieldError, { color: colors.destructive }]}>
            {error}
          </Text>
        ) : question.help ? (
          <Text style={[styles.fieldHint, { color: colors.mutedForeground }]}>
            {question.help}
          </Text>
        ) : null}
      </View>
    );
  }

  const isImage = question.type === "image";

  if (isImage) {
    return (
      <ImageQuestionField
        question={question}
        value={value}
        error={error}
        onChange={onChange}
      />
    );
  }

  return (
    <View style={{ gap: 6 }}>
      <View style={styles.fieldLabelRow}>
        <AppIcon name={icon} size={13} color={colors.primary} />
        <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
          {label}
        </Text>
      </View>
      <TextField
        hint={question.help}
        error={error}
        value={value}
        onChangeText={onChange}
        placeholder={question.placeholder ?? undefined}
        trailing={
          question.type === "text" || question.type === "textarea" ? (
            <DictationMic
              onText={(t) => onChange(value ? value.trim() + " " + t : t)}
            />
          ) : undefined
        }
        multiline={question.type === "textarea"}
        keyboardType={
          question.type === "email"
            ? "email-address"
            : question.type === "phone"
              ? "phone-pad"
              : question.type === "url"
                ? "url"
                : "default"
        }
        autoCapitalize={
          question.type === "email" ||
          question.type === "url" ||
          question.type === "color"
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

// Image questions (avatar/cover/etc.) get a device picker that uploads to the
// user's file storage and fills the answer with the resulting URL — closing the
// parity gap with the web editor. Pasting a URL by hand stays a valid fallback.
function ImageQuestionField({
  question,
  value,
  error,
  onChange,
}: {
  question: WizardQuestion;
  value: string;
  error?: string;
  onChange: (v: string) => void;
}) {
  const colors = useColors();
  const label = question.required ? `${question.label} *` : question.label;
  const icon = fieldIcon(question);
  const [uploading, setUploading] = useState(false);
  // Per-field crop shape: square for avatars/logos, wide for covers/banners,
  // undefined (free-form) for anything else.
  const aspect = imageCropAspect(question);

  async function uploadAsset(asset: ImagePicker.ImagePickerAsset) {
    setUploading(true);
    try {
      const url = await uploadWizardImage({
        uri: asset.uri,
        mime: asset.mimeType ?? undefined,
        name: asset.fileName ?? undefined,
      });
      onChange(url);
    } catch (e: any) {
      Alert.alert(
        "Couldn't upload image",
        e?.message ?? "Please try again, or paste an image URL instead.",
      );
    } finally {
      setUploading(false);
    }
  }

  async function pickFromLibrary() {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
        "Photos access needed",
        "Allow access to your photo library in Settings to pick an image.",
      );
      return;
    }
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      aspect,
      quality: 0.85,
    });
    if (res.canceled || !res.assets?.[0]) return;
    await uploadAsset(res.assets[0]);
  }

  async function takePhoto() {
    const perm = await ImagePicker.requestCameraPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
        "Camera access needed",
        "Allow camera access in Settings to take a photo.",
      );
      return;
    }
    const res = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      aspect,
      quality: 0.85,
    });
    if (res.canceled || !res.assets?.[0]) return;
    await uploadAsset(res.assets[0]);
  }

  function openSourceMenu() {
    Alert.alert(label, undefined, [
      { text: "Choose from library", onPress: pickFromLibrary },
      { text: "Take photo", onPress: takePhoto },
      { text: "Cancel", style: "cancel" },
    ]);
  }

  return (
    <View style={{ gap: 6 }}>
      <View style={styles.fieldLabelRow}>
        <AppIcon name={icon} size={13} color={colors.primary} />
        <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
          {label}
        </Text>
      </View>

      <View style={styles.imageRow}>
        <Pressable
          onPress={openSourceMenu}
          disabled={uploading}
          style={[
            styles.imagePreview,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          {value ? (
            <Image source={{ uri: value }} style={styles.imagePreviewImg} />
          ) : (
            <AppIcon name={icon} size={22} color={colors.mutedForeground} />
          )}
          {uploading ? (
            <View style={styles.imagePreviewOverlay}>
              <ActivityIndicator color={colors.primaryForeground} />
            </View>
          ) : null}
        </Pressable>

        <View style={{ flex: 1, gap: 8 }}>
          <Pressable
            onPress={openSourceMenu}
            disabled={uploading}
            style={[
              styles.uploadBtn,
              {
                backgroundColor: colors.primary + "14",
                borderColor: colors.primary + "44",
                opacity: uploading ? 0.6 : 1,
              },
            ]}
          >
            <AppIcon name="fa-upload" size={14} color={colors.primary} />
            <Text style={[styles.uploadBtnText, { color: colors.primary }]}>
              {uploading
                ? "Uploading…"
                : value
                  ? "Replace image"
                  : "Upload from device"}
            </Text>
          </Pressable>
          {value ? (
            <Pressable onPress={() => onChange("")} disabled={uploading}>
              <Text style={[styles.imageClear, { color: colors.mutedForeground }]}>
                Remove
              </Text>
            </Pressable>
          ) : null}
        </View>
      </View>

      <TextField
        hint="…or paste an image URL. Leave blank for a themed placeholder."
        error={error}
        value={value}
        onChangeText={onChange}
        placeholder={question.placeholder ?? "https://…/photo.jpg"}
        keyboardType="url"
        autoCapitalize="none"
        autoCorrect={false}
        editable={!uploading}
      />
    </View>
  );
}

// The optional AI auto-draft grounding picker — the user's AI Brains (Minds),
// the default platform Mind, and their vault files. Mirrors the web wizard's
// wizard-resources partial. Only rendered when the AI engine is enabled.
function AiDraftResources({
  myMinds,
  platformMinds,
  vaultFiles,
  selectedMinds,
  includePlatformMind,
  selectedFiles,
  onToggleMind,
  onTogglePlatform,
  onToggleFile,
}: {
  myMinds: { id: number; name: string }[];
  platformMinds: { id: number; name: string }[];
  vaultFiles: WizardVaultFile[];
  selectedMinds: Set<number>;
  includePlatformMind: boolean;
  selectedFiles: Set<number>;
  onToggleMind: (id: number) => void;
  onTogglePlatform: () => void;
  onToggleFile: (id: number) => void;
}) {
  const colors = useColors();
  const hasMinds = myMinds.length > 0 || platformMinds.length > 0;

  return (
    <View
      style={[
        styles.section,
        {
          backgroundColor: colors.card,
          borderColor: colors.primary + "44",
          borderRadius: colors.radius,
        },
      ]}
    >
      <View style={[styles.sectionHeader, { borderBottomColor: colors.border }]}>
        <View
          style={[styles.sectionIcon, { backgroundColor: colors.primary + "22" }]}
        >
          <AppIcon name="fa-wand-magic-sparkles" size={16} color={colors.primary} />
        </View>
        <View style={{ flex: 1, gap: 1 }}>
          <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
            Ground your AI draft
          </Text>
          <Text style={[styles.sectionDesc, { color: colors.mutedForeground }]}>
            Optional — pick AI Brains &amp; files to inform the auto-draft.
          </Text>
        </View>
      </View>

      <View style={styles.sectionBody}>
        <View style={{ gap: 8 }}>
          <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
            AI Brains
          </Text>
          {hasMinds ? (
            <View style={styles.chips}>
              {platformMinds.map((m) => (
                <Pressable
                  key={`platform-${m.id}`}
                  onPress={onTogglePlatform}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: includePlatformMind
                        ? colors.primary
                        : colors.card,
                      borderColor: includePlatformMind
                        ? colors.primary
                        : colors.border,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.chipText,
                      {
                        color: includePlatformMind
                          ? colors.primaryForeground
                          : colors.foreground,
                      },
                    ]}
                  >
                    {m.name}
                  </Text>
                </Pressable>
              ))}
              {myMinds.map((m) => {
                const active = selectedMinds.has(m.id);
                return (
                  <Pressable
                    key={`mine-${m.id}`}
                    onPress={() => onToggleMind(m.id)}
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
                      {m.name}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          ) : (
            <Text style={[styles.fieldHint, { color: colors.mutedForeground }]}>
              No AI Brains yet — create one to teach the AI about you.
            </Text>
          )}
        </View>

        <View style={{ gap: 8 }}>
          <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
            Vault files
          </Text>
          {vaultFiles.length ? (
            <View style={styles.chips}>
              {vaultFiles.map((f) => {
                const active = selectedFiles.has(f.id);
                return (
                  <Pressable
                    key={f.id}
                    onPress={() => onToggleFile(f.id)}
                    style={[
                      styles.chip,
                      {
                        backgroundColor: active ? colors.primary : colors.card,
                        borderColor: active ? colors.primary : colors.border,
                      },
                    ]}
                  >
                    <AppIcon
                      name={f.type === "image" ? "fa-image" : "fa-file"}
                      size={12}
                      color={active ? colors.primaryForeground : colors.primary}
                    />
                    <Text
                      numberOfLines={1}
                      style={[
                        styles.chipText,
                        {
                          color: active
                            ? colors.primaryForeground
                            : colors.foreground,
                          maxWidth: 140,
                        },
                      ]}
                    >
                      {f.name}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          ) : (
            <Text style={[styles.fieldHint, { color: colors.mutedForeground }]}>
              No files in your vault yet — upload some to use them here.
            </Text>
          )}
        </View>
      </View>
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
  designThumb: {
    width: 64,
    height: 64,
    borderWidth: 1,
    flexShrink: 0,
    resizeMode: "cover",
  },
  designTitleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    flexWrap: "wrap",
  },
  designBadge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 999,
  },
  designBadgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
  },
  designMeta: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    marginTop: 2,
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
  refineTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
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
  fieldError: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  imageRow: { flexDirection: "row", alignItems: "center", gap: 12 },
  imagePreview: {
    width: 64,
    height: 64,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  imagePreviewImg: { width: "100%", height: "100%" },
  imagePreviewOverlay: {
    ...StyleSheet.absoluteFillObject,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(0,0,0,0.4)",
  },
  uploadBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 11,
    paddingHorizontal: 14,
    borderRadius: 12,
    borderWidth: 1,
  },
  uploadBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  imageClear: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    textAlign: "center",
  },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 9,
    borderRadius: 999,
    borderWidth: 1,
  },
  chipText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14 },
});
