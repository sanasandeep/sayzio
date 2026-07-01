import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { useRouter } from "expo-router";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Image,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandWordmark } from "@/components/Brand";
import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import type { ApiError } from "@/lib/api";
import { completeOnboarding } from "@/lib/api/profile";
import {
  generateWizardPage,
  getWizardStartingDesigns,
  getWizardTaxonomy,
  type WizardPersona,
  type WizardStartingDesign,
} from "@/lib/api/wizard";
import {
  getWhatsappStatus,
  sendWhatsappCode,
  verifyWhatsappCode,
} from "@/lib/api/whatsapp";

/**
 * Post-sign-in first-run setup — the mobile mirror of the web onboarding
 * wizard. Same discrete, quick stages with a visible progress indicator:
 * Welcome → Pick persona → Choose template → Connect WhatsApp (optional) →
 * Done. The pre-auth intro slides (app/onboarding.tsx) are untouched; this
 * runs once, gated on the server's `onboarded_at` being null.
 *
 * Outcomes mirror the web flow: the chosen persona filters the starting
 * designs, applying a design creates the first biolink, and finishing (apply
 * or skip) stamps onboarding complete so the gate never pulls the user back.
 */

type StageKey = "welcome" | "persona" | "template" | "whatsapp" | "done";

export default function Setup() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { refresh } = useAuth();

  const [stage, setStage] = useState<StageKey>("welcome");
  const [persona, setPersona] = useState<WizardPersona | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Id of the biolink created by applying a template. When set, finishing
  // drops the user straight into that page's editor (mirrors web onboarding);
  // when null (template skipped) they land on the dashboard instead.
  const [createdLinkId, setCreatedLinkId] = useState<number | null>(null);

  // WhatsApp inline connect state.
  const [waPhase, setWaPhase] = useState<"number" | "code">("number");
  const [mobile, setMobile] = useState("");
  const [code, setCode] = useState("");
  const [demoReveal, setDemoReveal] = useState<string | null>(null);

  const taxonomy = useQuery({
    queryKey: ["wizard-taxonomy"],
    queryFn: getWizardTaxonomy,
  });

  const whatsappStatus = useQuery({
    queryKey: ["whatsapp-status"],
    queryFn: getWhatsappStatus,
  });

  const designs = useQuery({
    queryKey: ["wizard-starting-designs", persona?.slug],
    queryFn: () => getWizardStartingDesigns({ persona: persona!.slug }),
    enabled: !!persona && stage === "template",
  });

  // The WhatsApp stage is only part of the flow when a number isn't already
  // verified — mirrors the web stepper honesty about "Step X of Y".
  const whatsappNeeded = whatsappStatus.data
    ? !whatsappStatus.data.has_whatsapp_number
    : true;

  const stages: StageKey[] = useMemo(
    () => [
      "welcome",
      "persona",
      "template",
      ...(whatsappNeeded ? (["whatsapp"] as StageKey[]) : []),
      "done",
    ],
    [whatsappNeeded],
  );
  const stageLabels: Record<StageKey, string> = {
    welcome: "Welcome",
    persona: "Persona",
    template: "Template",
    whatsapp: "WhatsApp",
    done: "Done",
  };
  const stepIndex = Math.max(0, stages.indexOf(stage));

  const goStage = (key: StageKey) => {
    setError(null);
    setStage(key);
  };

  // Mark onboarding finished server-side (idempotent) then advance to the
  // WhatsApp stage or straight to Done. Called after applying a template or
  // skipping — mirrors the web applyTemplate/goToDashboard onboarded stamp.
  const finishCoreSetup = async () => {
    try {
      await completeOnboarding();
      void refresh();
    } catch {
      /* non-fatal: the gate re-checks; don't trap the user here */
    }
    goStage(whatsappNeeded ? "whatsapp" : "done");
  };

  const applyDesign = async (design: WizardStartingDesign) => {
    if (!persona || busy) return;
    setBusy(true);
    setError(null);
    try {
      const link = await generateWizardPage({
        persona: persona.slug,
        category: persona.category,
        page_type: persona.page_type,
        template_id: design.id,
        answers: {},
      });
      setCreatedLinkId(link.id);
      await finishCoreSetup();
    } catch (e) {
      setError(
        (e as ApiError)?.message ??
          "Couldn't create your page from that template. Try another, or skip for now.",
      );
    } finally {
      setBusy(false);
    }
  };

  const skipTemplate = async () => {
    if (busy) return;
    setBusy(true);
    await finishCoreSetup();
    setBusy(false);
  };

  const sendCode = async () => {
    const v = mobile.trim();
    if (!v) {
      setError("Enter your WhatsApp number with country code (e.g. +1 555 123 4567).");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await sendWhatsappCode(v);
      setDemoReveal(res.demo_reveal ?? null);
      setWaPhase("code");
    } catch (e) {
      setError((e as ApiError)?.message ?? "Could not send a code. Please try again.");
    } finally {
      setBusy(false);
    }
  };

  const verifyCode = async () => {
    if (code.trim().length !== 6) {
      setError("Enter the 6-digit code we sent.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await verifyWhatsappCode(mobile.trim(), code.trim());
      void refresh();
      goStage("done");
    } catch (e) {
      setError((e as ApiError)?.message ?? "That code didn't match. Try again.");
    } finally {
      setBusy(false);
    }
  };

  const finishToApp = () => {
    // If the user created a page from a template, drop them straight into its
    // editor (mirrors the web onboarding hand-off); otherwise the dashboard.
    if (createdLinkId != null) {
      router.replace(`/links/${createdLinkId}/edit` as any);
    } else {
      router.replace("/(tabs)");
    }
  };

  const webBottom = Platform.OS === "web" ? 34 : 0;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <ScrollView
        contentContainerStyle={[
          styles.scroll,
          { paddingTop: insets.top + 20, paddingBottom: insets.bottom + 32 + webBottom },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        {/* Brand + skip */}
        <View style={styles.topBar}>
          <BrandWordmark size={26} />
          {stage !== "done" ? (
            <Text
              accessibilityRole="button"
              onPress={() => {
                void (async () => {
                  try {
                    await completeOnboarding();
                    void refresh();
                  } catch {
                    /* non-fatal */
                  }
                  finishToApp();
                })();
              }}
              style={[styles.skip, { color: colors.mutedForeground }]}
            >
              Skip setup
            </Text>
          ) : null}
        </View>

        {/* Progress indicator */}
        <Stepper
          colors={colors}
          stages={stages}
          labels={stageLabels}
          stepIndex={stepIndex}
        />

        {error ? (
          <View
            style={[
              styles.errorBox,
              { backgroundColor: "#ef444422", borderColor: "#ef444455", borderRadius: colors.radius },
            ]}
          >
            <Text style={[styles.errorText, { color: colors.foreground }]}>{error}</Text>
          </View>
        ) : null}

        {/* ── Welcome ── */}
        {stage === "welcome" ? (
          <View style={styles.section}>
            <View style={[styles.heroIcon, { backgroundColor: colors.primary + "22" }]}>
              <Feather name="zap" size={26} color={colors.primary} />
            </View>
            <Text style={[styles.h1, { color: colors.foreground }]}>Let's set up your page</Text>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              A few quick steps and your Link in Bio is ready.
            </Text>
            <View style={{ height: 20 }} />
            {[
              { n: "1", t: "Pick your persona", d: "so we can suggest templates that fit you." },
              { n: "2", t: "Choose a template", d: "and we'll build your first page from it." },
              { n: "3", t: "Connect WhatsApp", d: "optional — sign in faster and stay reachable." },
            ].map((row) => (
              <View key={row.n} style={styles.bulletRow}>
                <View style={[styles.bulletNum, { backgroundColor: colors.card, borderColor: colors.border }]}>
                  <Text style={[styles.bulletNumText, { color: colors.foreground }]}>{row.n}</Text>
                </View>
                <Text style={[styles.bulletText, { color: colors.mutedForeground }]}>
                  <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold" }}>{row.t}</Text>
                  {"  "}
                  {row.d}
                </Text>
              </View>
            ))}
            <View style={{ height: 24 }} />
            <Button label="Let's go" onPress={() => goStage("persona")} />
          </View>
        ) : null}

        {/* ── Persona ── */}
        {stage === "persona" ? (
          <View style={styles.section}>
            <Text style={[styles.h2, { color: colors.foreground }]}>Who are you?</Text>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              Pick the closest fit — you can change this anytime.
            </Text>
            <View style={{ height: 16 }} />
            {taxonomy.isLoading ? (
              <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
            ) : taxonomy.data ? (
              taxonomy.data.groups.map((group) => {
                const items = taxonomy.data!.personas[group.key] ?? [];
                if (items.length === 0) return null;
                return (
                  <View key={group.key} style={{ marginBottom: 18 }}>
                    <Text style={[styles.groupLabel, { color: colors.mutedForeground }]}>
                      {group.label}
                    </Text>
                    {items.map((p) => {
                      const active = persona?.slug === p.slug;
                      return (
                        <Pressable
                          key={p.slug}
                          onPress={() => {
                            setPersona(p);
                            goStage("template");
                          }}
                          style={[
                            styles.personaCard,
                            {
                              backgroundColor: active ? colors.primary + "22" : colors.card,
                              borderColor: active ? colors.primary : colors.border,
                              borderRadius: colors.radius,
                            },
                          ]}
                        >
                          <View style={styles.personaInfo}>
                            <Text style={[styles.personaLabel, { color: colors.foreground }]}>
                              {p.label}
                            </Text>
                            {p.blurb ? (
                              <Text
                                numberOfLines={1}
                                style={[styles.personaBlurb, { color: colors.mutedForeground }]}
                              >
                                {p.blurb}
                              </Text>
                            ) : null}
                          </View>
                          <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
                        </Pressable>
                      );
                    })}
                  </View>
                );
              })
            ) : (
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                Couldn't load personas. You can skip and set this up later.
              </Text>
            )}
            <View style={{ height: 8 }} />
            <Button label="Back" variant="ghost" onPress={() => goStage("welcome")} />
          </View>
        ) : null}

        {/* ── Template ── */}
        {stage === "template" ? (
          <View style={styles.section}>
            <Text style={[styles.h2, { color: colors.foreground }]}>Choose a template</Text>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              {persona ? `Recommended for ${persona.label}.` : "Pick a starting point."} Tap one to
              build your page.
            </Text>
            <View style={{ height: 16 }} />
            {designs.isLoading ? (
              <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
            ) : designs.data && designs.data.length > 0 ? (
              designs.data.map((d) => (
                <Pressable
                  key={d.id}
                  onPress={() => (d.locked ? undefined : applyDesign(d))}
                  disabled={busy}
                  style={[
                    styles.designCard,
                    {
                      backgroundColor: colors.card,
                      borderColor: d.recommended ? colors.primary + "88" : colors.border,
                      borderRadius: colors.radius,
                      opacity: busy ? 0.6 : 1,
                    },
                  ]}
                >
                  {d.thumbnail_url ? (
                    <Image source={{ uri: d.thumbnail_url }} style={styles.thumb} resizeMode="cover" />
                  ) : (
                    <View style={[styles.thumb, styles.thumbFallback, { backgroundColor: colors.primary + "18" }]}>
                      <Feather name="layout" size={22} color={colors.primary} />
                    </View>
                  )}
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.designName, { color: colors.foreground }]}>{d.name}</Text>
                    <Text style={[styles.designMeta, { color: colors.mutedForeground }]}>
                      {d.category_label} · {d.blocks_count} blocks
                    </Text>
                  </View>
                  {d.locked ? (
                    <Feather name="lock" size={16} color={colors.mutedForeground} />
                  ) : (
                    <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
                  )}
                </Pressable>
              ))
            ) : (
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                No templates to show right now — skip and start from a blank page anytime.
              </Text>
            )}
            <View style={{ height: 12 }} />
            <Button
              label="Skip for now"
              variant="ghost"
              onPress={skipTemplate}
              loading={busy}
            />
            <Button label="Back" variant="ghost" onPress={() => goStage("persona")} disabled={busy} />
          </View>
        ) : null}

        {/* ── WhatsApp (optional) ── */}
        {stage === "whatsapp" ? (
          <View style={styles.section}>
            <View style={[styles.heroIcon, { backgroundColor: "#25D36622" }]}>
              <Feather name="message-circle" size={24} color="#25D366" />
            </View>
            <Text style={[styles.h2, { color: colors.foreground }]}>Add your WhatsApp number</Text>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              Verify a number to sign in faster with a one-time code and stay reachable. Optional —
              you can skip.
            </Text>
            <View style={{ height: 16 }} />
            {demoReveal ? (
              <View
                style={[
                  styles.errorBox,
                  { backgroundColor: colors.primary + "14", borderColor: colors.primary + "55", borderRadius: colors.radius },
                ]}
              >
                <Text style={[styles.errorText, { color: colors.foreground }]}>{demoReveal}</Text>
              </View>
            ) : null}
            {waPhase === "number" ? (
              <>
                <TextField
                  label="WhatsApp number"
                  placeholder="+1 555 123 4567"
                  keyboardType="phone-pad"
                  autoCapitalize="none"
                  autoCorrect={false}
                  value={mobile}
                  onChangeText={setMobile}
                />
                <View style={{ height: 16 }} />
                <Button label="Send verification code" onPress={sendCode} loading={busy} />
              </>
            ) : (
              <>
                <TextField
                  label="Verification code"
                  placeholder="123456"
                  keyboardType="number-pad"
                  autoCapitalize="none"
                  autoCorrect={false}
                  autoComplete={Platform.select({ ios: "one-time-code", android: "sms-otp" })}
                  textContentType="oneTimeCode"
                  maxLength={6}
                  value={code}
                  onChangeText={setCode}
                />
                <View style={{ height: 16 }} />
                <Button label="Verify & connect" onPress={verifyCode} loading={busy} />
                <Button
                  label="Use a different number"
                  variant="ghost"
                  onPress={() => {
                    setWaPhase("number");
                    setCode("");
                    setError(null);
                  }}
                  disabled={busy}
                />
              </>
            )}
            <View style={{ height: 8 }} />
            <Button label="Skip for now" variant="ghost" onPress={() => goStage("done")} disabled={busy} />
          </View>
        ) : null}

        {/* ── Done ── */}
        {stage === "done" ? (
          <View style={[styles.section, { alignItems: "center" }]}>
            <View style={[styles.heroIcon, { backgroundColor: colors.primary + "22" }]}>
              <Feather name="check-circle" size={30} color={colors.primary} />
            </View>
            <Text style={[styles.h1, { color: colors.foreground, textAlign: "center" }]}>You're all set</Text>
            <Text style={[styles.sub, { color: colors.mutedForeground, textAlign: "center" }]}>
              {createdLinkId != null
                ? "Your page is ready — let's customize it, add your links, and make it yours."
                : "Your dashboard is ready — tweak your page, add links, and share it anywhere."}
            </Text>
            <View style={{ height: 24 }} />
            <View style={{ alignSelf: "stretch" }}>
              <Button
                label={createdLinkId != null ? "Start editing my page" : "Go to my dashboard"}
                onPress={finishToApp}
              />
            </View>
          </View>
        ) : null}
      </ScrollView>
    </View>
  );
}

function Stepper({
  colors,
  stages,
  labels,
  stepIndex,
}: {
  colors: ReturnType<typeof useColors>;
  stages: StageKey[];
  labels: Record<StageKey, string>;
  stepIndex: number;
}) {
  return (
    <View style={styles.stepper}>
      <View style={styles.stepperRow}>
        {stages.map((key, i) => {
          const done = i < stepIndex;
          const active = i === stepIndex;
          return (
            <View key={key} style={styles.stepperNode}>
              <View
                style={[
                  styles.stepDot,
                  {
                    backgroundColor: done
                      ? colors.primary
                      : active
                        ? colors.primary + "33"
                        : colors.card,
                    borderColor: done || active ? colors.primary : colors.border,
                  },
                ]}
              >
                {done ? (
                  <Feather name="check" size={12} color="#fff" />
                ) : (
                  <Text
                    style={[
                      styles.stepNum,
                      { color: active ? colors.primary : colors.mutedForeground },
                    ]}
                  >
                    {i + 1}
                  </Text>
                )}
              </View>
              {i < stages.length - 1 ? (
                <View
                  style={[
                    styles.stepConnector,
                    { backgroundColor: done ? colors.primary + "88" : colors.border },
                  ]}
                />
              ) : null}
            </View>
          );
        })}
      </View>
      <Text style={[styles.stepCaption, { color: colors.mutedForeground }]}>
        Step {stepIndex + 1} of {stages.length} · {labels[stages[stepIndex]]}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { paddingHorizontal: 24 },
  topBar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 20,
  },
  skip: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14, padding: 6 },
  stepper: { marginBottom: 22 },
  stepperRow: { flexDirection: "row", alignItems: "center" },
  stepperNode: { flexDirection: "row", alignItems: "center", flex: 1 },
  stepDot: {
    width: 26,
    height: 26,
    borderRadius: 13,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  stepNum: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 12 },
  stepConnector: { flex: 1, height: 2, marginHorizontal: 6, borderRadius: 1 },
  stepCaption: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12, marginTop: 10 },
  section: { gap: 4 },
  heroIcon: {
    width: 56,
    height: 56,
    borderRadius: 16,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 14,
  },
  h1: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 26, letterSpacing: -0.4 },
  h2: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22, letterSpacing: -0.3 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 15, lineHeight: 22 },
  bulletRow: { flexDirection: "row", alignItems: "flex-start", gap: 12, marginBottom: 12 },
  bulletNum: {
    width: 26,
    height: 26,
    borderRadius: 13,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  bulletNumText: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 12 },
  bulletText: { flex: 1, fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 21 },
  groupLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.6,
    textTransform: "uppercase",
    marginBottom: 8,
  },
  personaCard: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    paddingVertical: 12,
    paddingHorizontal: 14,
    marginBottom: 8,
    gap: 10,
  },
  personaInfo: { flex: 1, gap: 2 },
  personaLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  personaBlurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  designCard: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    padding: 12,
    marginBottom: 10,
    gap: 12,
  },
  thumb: { width: 52, height: 52, borderRadius: 10 },
  thumbFallback: { alignItems: "center", justifyContent: "center" },
  designName: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  designMeta: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 2 },
  errorBox: { borderWidth: 1, paddingVertical: 12, paddingHorizontal: 14, marginBottom: 16 },
  errorText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14, lineHeight: 20 },
});
