import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { AiDashboardDemo } from "@/components/AiDashboardDemo";
import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { handlePlanLockedError } from "@/lib/upgradePrompt";
import {
  applyDashboardPreset,
  estimateDashboardAiDesign,
  generateDashboardAiDesign,
  getDashboardLayout,
  type DashboardAiAnswers,
  type DashboardLayoutState,
} from "@/lib/api/dashboard";
import { showAlert } from "@/lib/webAlert";

type Density = "minimal" | "balanced" | "detailed";

const DENSITY_OPTIONS: { value: Density; label: string }[] = [
  { value: "minimal", label: "Minimal" },
  { value: "balanced", label: "Balanced" },
  { value: "detailed", label: "Detailed" },
];

type Step = "picker" | "ai-form" | "ai-confirm" | "ai-result";

/**
 * "Customize dashboard" — mobile parity for the web /user/dashboard chooser
 * (Task #3525). Lets a creator apply one of the 5 curated presets or hand a
 * goal to the AI designer (mirrors the web customize-modal.blade.php flow:
 * picker → ai-form → ai-confirm → ai-result). The catalog, presets, and AI
 * designer all live server-side (DashboardWidgetCatalog / DashboardPresets /
 * DashboardAiDesignerService) — this screen only orchestrates the flow.
 */
export default function DashboardCustomizeScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [step, setStep] = useState<Step>("picker");
  const [goal, setGoal] = useState("");
  const [prioritiesText, setPrioritiesText] = useState("");
  const [density, setDensity] = useState<Density>("balanced");
  const [notes, setNotes] = useState("");
  const [selectedWidgets, setSelectedWidgets] = useState<string[]>([]);
  const [estimate, setEstimate] = useState<number | null>(null);
  const [resultWidgets, setResultWidgets] = useState<string[] | null>(null);
  const [creditsSpent, setCreditsSpent] = useState<number>(0);

  const layoutQuery = useQuery<DashboardLayoutState>({
    queryKey: ["dashboard-layout"],
    queryFn: getDashboardLayout,
  });

  const buildAnswers = (): DashboardAiAnswers => ({
    goal: goal.trim(),
    priorities: prioritiesText
      .split(/[\n,]+/)
      .map((s) => s.trim())
      .filter((s) => s.length > 0)
      .slice(0, 10),
    density,
    notes: notes.trim() || undefined,
    selected_widgets: selectedWidgets.length > 0 ? selectedWidgets : undefined,
  });

  const toggleWidget = (key: string) => {
    setEstimate(null);
    setSelectedWidgets((prev) =>
      prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key],
    );
  };

  const presetM = useMutation({
    mutationFn: (preset: string) => applyDashboardPreset(preset),
    onSuccess: async (res) => {
      await qc.invalidateQueries({ queryKey: ["dashboard-layout"] });
      showAlert(
        "Dashboard updated",
        `Switched to the "${res.preset}" layout.`,
      );
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      showAlert(
        "Couldn't apply preset",
        e?.message ?? "Please try again in a moment.",
      );
    },
  });

  const estimateM = useMutation({
    mutationFn: () => estimateDashboardAiDesign(buildAnswers()),
    onSuccess: (res) => {
      setEstimate(res.estimated_credits);
      setStep("ai-confirm");
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      showAlert(
        "Couldn't estimate",
        e?.message ?? "Please try again in a moment.",
      );
    },
  });

  const generateM = useMutation({
    mutationFn: () => generateDashboardAiDesign(buildAnswers()),
    onSuccess: async (res) => {
      setResultWidgets(res.widgets);
      setCreditsSpent(res.credits_spent);
      setStep("ai-result");
      await qc.invalidateQueries({ queryKey: ["dashboard-layout"] });
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      showAlert(
        "Couldn't design a dashboard",
        e?.message ??
          "The assistant couldn't build a layout from that. Add more detail and try again.",
      );
    },
  });

  const goalTooShort = goal.trim().length < 5;

  function resetAiFlow() {
    setStep("picker");
    setGoal("");
    setPrioritiesText("");
    setDensity("balanced");
    setNotes("");
    setSelectedWidgets([]);
    setEstimate(null);
    setResultWidgets(null);
  }

  if (layoutQuery.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Customize dashboard" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (layoutQuery.isError || !layoutQuery.data) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Customize dashboard" }} />
        <Text style={{ color: colors.mutedForeground, marginBottom: 12 }}>
          Couldn't load your dashboard settings.
        </Text>
        <Button label="Retry" onPress={() => layoutQuery.refetch()} />
      </View>
    );
  }

  const data = layoutQuery.data;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Customize dashboard" }} />
      <ScrollView contentContainerStyle={styles.body}>
        {step === "picker" ? (
          <>
            <Text style={[styles.intro, { color: colors.mutedForeground }]}>
              Pick a preset layout for your dashboard, or describe what
              matters most to you and let AI design one.
            </Text>

            {data.presets.length > 0 ? (
              <View style={styles.demoSection}>
                <Text style={[styles.demoHeading, { color: colors.foreground }]}>
                  How it works
                </Text>
                <AiDashboardDemo presets={data.presets} />
              </View>
            ) : null}

            {data.current.preset || data.current.is_custom ? (
              <View
                style={[
                  styles.currentBadge,
                  { borderColor: colors.border, borderRadius: colors.radius },
                ]}
              >
                <Feather name="layout" size={16} color={colors.primary} />
                <Text style={{ color: colors.foreground, fontSize: 13 }}>
                  Current:{" "}
                  {data.current.is_custom
                    ? "AI-designed"
                    : data.current.preset ?? "Overview"}
                </Text>
              </View>
            ) : null}

            <View style={{ gap: 10 }}>
              {data.presets.map((preset) => {
                const active =
                  !data.current.is_custom &&
                  data.current.preset === preset.key;
                return (
                  <Pressable
                    key={preset.key}
                    disabled={presetM.isPending}
                    onPress={() => presetM.mutate(preset.key)}
                    style={({ pressed }) => [
                      styles.presetCard,
                      {
                        borderColor: active ? colors.primary : colors.border,
                        borderRadius: colors.radius,
                        backgroundColor: colors.card,
                        opacity: pressed ? 0.85 : 1,
                      },
                    ]}
                  >
                    <View style={{ flex: 1, gap: 4 }}>
                      <Text
                        style={{ color: colors.foreground, fontWeight: "600" }}
                      >
                        {preset.label}
                      </Text>
                      <Text
                        style={{ color: colors.mutedForeground, fontSize: 12 }}
                      >
                        {preset.description}
                      </Text>
                    </View>
                    {active ? (
                      <Feather
                        name="check-circle"
                        size={20}
                        color={colors.primary}
                      />
                    ) : (
                      <Feather
                        name="chevron-right"
                        size={20}
                        color={colors.mutedForeground}
                      />
                    )}
                  </Pressable>
                );
              })}
            </View>

            {data.ai_designer_allowed ? (
              <Pressable
                onPress={() => setStep("ai-form")}
                style={({ pressed }) => [
                  styles.aiCard,
                  {
                    borderRadius: colors.radius,
                    opacity: pressed ? 0.9 : 1,
                  },
                ]}
              >
                <Feather name="zap" size={20} color={colors.primary} />
                <View style={{ flex: 1, gap: 2 }}>
                  <Text
                    style={{ color: colors.foreground, fontWeight: "600" }}
                  >
                    Design with AI
                  </Text>
                  <Text
                    style={{ color: colors.mutedForeground, fontSize: 12 }}
                  >
                    Describe your goal and AI will pick the right widgets.
                  </Text>
                </View>
                <Feather
                  name="chevron-right"
                  size={20}
                  color={colors.mutedForeground}
                />
              </Pressable>
            ) : (
              <View
                style={[
                  styles.aiLockedCard,
                  { borderColor: colors.border, borderRadius: colors.radius },
                ]}
              >
                <Feather
                  name="lock"
                  size={16}
                  color={colors.mutedForeground}
                />
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  Designing with AI isn't available on your plan.
                </Text>
              </View>
            )}

            {!data.ai_enabled ? (
              <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                AI generation is currently turned off.
              </Text>
            ) : null}
          </>
        ) : null}

        {step === "ai-form" ? (
          <>
            <Text style={[styles.intro, { color: colors.mutedForeground }]}>
              Tell AI what you want your dashboard to help you do. This
              replaces your current widget layout.
            </Text>

            <TextField
              label="What's your main goal?"
              placeholder="e.g. Grow my audience and track new followers at a glance."
              value={goal}
              onChangeText={(t) => {
                setGoal(t);
                setEstimate(null);
              }}
              multiline
              numberOfLines={4}
              style={{ minHeight: 96, textAlignVertical: "top" }}
              hint={`${data.ai_designer_allowed ? "" : "Plan-gated. "}Estimate before generating`}
            />

            <TextField
              label="Priorities (optional)"
              placeholder="One per line, e.g. Traffic sources, Coin balance"
              value={prioritiesText}
              onChangeText={(t) => {
                setPrioritiesText(t);
                setEstimate(null);
              }}
              multiline
              numberOfLines={3}
              style={{ minHeight: 72, textAlignVertical: "top" }}
            />

            <View style={{ gap: 8 }}>
              <Text style={[styles.label, { color: colors.mutedForeground }]}>
                Density
              </Text>
              <View style={styles.densityRow}>
                {DENSITY_OPTIONS.map((opt) => {
                  const active = density === opt.value;
                  return (
                    <Pressable
                      key={opt.value}
                      onPress={() => {
                        setDensity(opt.value);
                        setEstimate(null);
                      }}
                      style={[
                        styles.densityPill,
                        {
                          borderColor: active
                            ? colors.primary
                            : colors.border,
                          backgroundColor: active
                            ? colors.primary + "22"
                            : "transparent",
                          borderRadius: colors.radius,
                        },
                      ]}
                    >
                      <Text
                        style={{
                          color: active ? colors.primary : colors.foreground,
                          fontSize: 13,
                          fontWeight: active ? "600" : "400",
                        }}
                      >
                        {opt.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            </View>

            {data.grouped_catalog.length > 0 ? (
              <View style={{ gap: 8 }}>
                <View style={styles.widgetHeaderRow}>
                  <Text style={[styles.label, { color: colors.mutedForeground }]}>
                    Must-have widgets (optional)
                  </Text>
                  {selectedWidgets.length > 0 ? (
                    <Text
                      style={{ color: colors.mutedForeground, fontSize: 11 }}
                    >
                      {selectedWidgets.length} selected
                    </Text>
                  ) : null}
                </View>
                <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                  Pick specific widgets to guarantee they're included; the AI
                  still designs the rest of the layout around your goal.
                </Text>
                {data.grouped_catalog.map((group) => (
                  <View key={group.tab} style={{ gap: 6 }}>
                    <Text style={[styles.groupHeading, { color: colors.mutedForeground }]}>
                      {group.label.toUpperCase()}
                    </Text>
                    <View style={{ gap: 6 }}>
                      {group.widgets.map((widget) => {
                        const active = selectedWidgets.includes(widget.key);
                        return (
                          <Pressable
                            key={widget.key}
                            onPress={() => toggleWidget(widget.key)}
                            style={[
                              styles.widgetRow,
                              {
                                borderColor: active
                                  ? colors.primary
                                  : colors.border,
                                backgroundColor: active
                                  ? colors.primary + "22"
                                  : colors.card,
                                borderRadius: colors.radius,
                              },
                            ]}
                          >
                            <Feather
                              name={active ? "check-square" : "square"}
                              size={16}
                              color={
                                active ? colors.primary : colors.mutedForeground
                              }
                              style={{ marginTop: 1 }}
                            />
                            <View style={{ flex: 1, gap: 2 }}>
                              <Text
                                style={{
                                  color: colors.foreground,
                                  fontSize: 13,
                                  fontWeight: active ? "600" : "500",
                                }}
                              >
                                {widget.label}
                              </Text>
                              <Text
                                style={{
                                  color: colors.mutedForeground,
                                  fontSize: 11,
                                  lineHeight: 15,
                                }}
                              >
                                {widget.description}
                              </Text>
                            </View>
                          </Pressable>
                        );
                      })}
                    </View>
                  </View>
                ))}
              </View>
            ) : null}

            <TextField
              label="Anything else? (optional)"
              placeholder="e.g. Keep it compact, I check this on my phone."
              value={notes}
              onChangeText={(t) => {
                setNotes(t);
                setEstimate(null);
              }}
              multiline
              numberOfLines={2}
              style={{ minHeight: 56, textAlignVertical: "top" }}
            />

            <View style={{ gap: 8, marginTop: 4 }}>
              <Button
                label={estimateM.isPending ? "Estimating…" : "Estimate cost"}
                loading={estimateM.isPending}
                disabled={goalTooShort}
                onPress={() => estimateM.mutate()}
              />
              <Button
                label="Back"
                variant="ghost"
                onPress={() => setStep("picker")}
              />
              {goalTooShort ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  Add a little more detail (at least 5 characters) to
                  continue.
                </Text>
              ) : null}
            </View>
          </>
        ) : null}

        {step === "ai-confirm" ? (
          <>
            <Text style={[styles.intro, { color: colors.mutedForeground }]}>
              This will replace your current dashboard layout with an
              AI-designed one.
            </Text>
            <View
              style={[
                styles.currentBadge,
                { borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <Feather name="zap" size={16} color={colors.primary} />
              <Text style={{ color: colors.foreground, fontSize: 13 }}>
                Estimated cost: ~{estimate ?? 0} coins
              </Text>
            </View>
            <View style={{ gap: 8, marginTop: 4 }}>
              <Button
                label={
                  generateM.isPending ? "Designing…" : "Design my dashboard"
                }
                loading={generateM.isPending}
                onPress={() => generateM.mutate()}
              />
              <Button
                label="Back"
                variant="ghost"
                disabled={generateM.isPending}
                onPress={() => setStep("ai-form")}
              />
            </View>
          </>
        ) : null}

        {step === "ai-result" ? (
          <>
            <View
              style={[
                styles.resultCard,
                { borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <Feather
                name="check-circle"
                size={28}
                color={colors.primary}
              />
              <Text
                style={{
                  color: colors.foreground,
                  fontWeight: "600",
                  marginTop: 8,
                }}
              >
                Dashboard updated
              </Text>
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 13,
                  marginTop: 4,
                  textAlign: "center",
                }}
              >
                {resultWidgets?.length ?? 0} widget
                {resultWidgets?.length === 1 ? "" : "s"} selected.
                {creditsSpent > 0 ? ` Used ${creditsSpent} coins.` : ""}
              </Text>
            </View>
            <Button label="Done" onPress={resetAiFlow} />
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  body: { padding: 16, gap: 16 },
  intro: { fontSize: 13, lineHeight: 18 },
  demoSection: { gap: 10 },
  demoHeading: { fontSize: 15, fontWeight: "700" },
  label: { fontSize: 13, fontWeight: "600" },
  currentBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    padding: 10,
  },
  presetCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    borderWidth: 1,
    padding: 14,
  },
  aiCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 14,
    borderWidth: 1,
    borderStyle: "dashed",
    borderColor: "rgba(120,90,255,0.5)",
  },
  aiLockedCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    padding: 12,
  },
  widgetHeaderRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  groupHeading: {
    fontSize: 10,
    fontWeight: "700",
    letterSpacing: 0.5,
  },
  widgetRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 10,
    borderWidth: 1,
    padding: 12,
  },
  densityRow: { flexDirection: "row", gap: 8 },
  densityPill: {
    borderWidth: 1,
    paddingVertical: 8,
    paddingHorizontal: 14,
  },
  resultCard: {
    alignItems: "center",
    borderWidth: 1,
    padding: 24,
  },
});
