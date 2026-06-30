import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import React from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { billing, type DowngradePlan } from "@/lib/api/billing";

function formatApplies(iso: string | null): string {
  if (!iso) return "the end of your current cycle";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "the end of your current cycle";
  return d.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

export default function DowngradeScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["billing", "downgrade"],
    queryFn: () => billing.downgradeOptions(),
  });

  const invalidate = () =>
    Promise.all([
      qc.invalidateQueries({ queryKey: ["billing", "downgrade"] }),
      qc.invalidateQueries({ queryKey: ["billing", "subscription"] }),
      qc.invalidateQueries({ queryKey: ["billing", "plans"] }),
    ]);

  const schedule = useMutation({
    mutationFn: (planId: number) => billing.scheduleDowngrade(planId),
    onSuccess: async (res) => {
      await invalidate();
      Alert.alert("Downgrade scheduled", res.data.message);
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't schedule", e?.message ?? "Please try again."),
  });

  const cancel = useMutation({
    mutationFn: () => billing.cancelDowngrade(),
    onSuccess: async (res) => {
      await invalidate();
      Alert.alert("Downgrade cancelled", res.data.message);
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't cancel", e?.message ?? "Please try again."),
  });

  const data = q.data?.data;
  const scheduled = data?.scheduled_downgrade ?? null;
  const plans = data?.plans ?? [];
  const busy = schedule.isPending || cancel.isPending;

  const confirmSchedule = (plan: DowngradePlan) => {
    const when = formatApplies(data?.subscription?.current_period_end ?? null);
    const lostLine =
      plan.lost_addons.length > 0
        ? `\n\nYou'll lose these add-ons: ${plan.lost_addons.join(", ")}.`
        : "";
    Alert.alert(
      `Downgrade to ${plan.name}?`,
      `Your plan changes to ${plan.name} on ${when}. You keep your current plan until then and can cancel anytime before it applies.${lostLine}`,
      [
        { text: "Not now", style: "cancel" },
        {
          text: "Schedule downgrade",
          style: "destructive",
          onPress: () => schedule.mutate(plan.id),
        },
      ],
    );
  };

  const confirmCancel = () => {
    Alert.alert(
      "Cancel scheduled downgrade?",
      "You'll stay on your current plan and nothing will change at the end of your cycle.",
      [
        { text: "Keep downgrade", style: "cancel" },
        {
          text: "Cancel downgrade",
          style: "destructive",
          onPress: () => cancel.mutate(),
        },
      ],
    );
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Downgrade plan" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load downgrade options"
          body={
            (q.error as { message?: string })?.message ??
            "Check your connection and try again."
          }
        />
      ) : !data?.subscription ? (
        <EmptyState
          icon="credit-card"
          title="No paid plan to downgrade"
          body="You're not on a paid subscription, so there's nothing to downgrade. Pick a plan to get started."
        />
      ) : (
        <ScrollView
          contentContainerStyle={{
            padding: 20,
            paddingBottom: insets.bottom + 32,
            gap: 16,
          }}
        >
          {data.current_plan ? (
            <View
              style={[
                styles.currentCard,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <Text style={[styles.label, { color: colors.mutedForeground }]}>
                CURRENT PLAN
              </Text>
              <Text style={[styles.currentName, { color: colors.foreground }]}>
                {data.current_plan.name ?? "—"}
                {data.current_plan.formatted ? (
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      fontSize: 14,
                      fontFamily: "SpaceGrotesk_400Regular",
                    }}
                  >
                    {"  "}
                    {data.current_plan.formatted}
                  </Text>
                ) : null}
              </Text>
            </View>
          ) : null}

          {scheduled ? (
            <View
              style={[
                styles.scheduledCard,
                {
                  backgroundColor: colors.primary + "14",
                  borderColor: colors.primary,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <View style={styles.scheduledHeader}>
                <Feather name="clock" size={18} color={colors.primary} />
                <Text
                  style={[styles.scheduledTitle, { color: colors.foreground }]}
                >
                  Downgrade scheduled
                </Text>
              </View>
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontFamily: "SpaceGrotesk_400Regular",
                  marginTop: 4,
                }}
              >
                Your plan will change to {scheduled.plan_name} on{" "}
                {formatApplies(scheduled.applies_at)}. You can cancel this
                anytime before then.
              </Text>
              <View style={{ marginTop: 12 }}>
                <Button
                  label={cancel.isPending ? "Cancelling…" : "Cancel downgrade"}
                  variant="outline"
                  onPress={confirmCancel}
                  disabled={busy}
                />
              </View>
            </View>
          ) : null}

          <Text style={[styles.intro, { color: colors.mutedForeground }]}>
            {scheduled
              ? "Pick a different lower plan to reschedule your downgrade."
              : "Pick a lower-priced paid plan. The change applies at the end of your current cycle — you keep everything you have until then."}
          </Text>

          {plans.length === 0 ? (
            <EmptyState
              icon="arrow-down-circle"
              title="No lower plans available"
              body="You're already on the lowest paid plan. To stop paying entirely, cancel your subscription instead."
            />
          ) : (
            plans.map((plan) => {
              const isScheduledTarget = scheduled?.plan_id === plan.id;
              return (
                <View
                  key={plan.id}
                  style={[
                    styles.card,
                    {
                      backgroundColor: colors.card,
                      borderColor: isScheduledTarget
                        ? colors.primary
                        : colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  <View style={styles.cardHeader}>
                    <Text style={[styles.planName, { color: colors.foreground }]}>
                      {plan.name}
                    </Text>
                    <Text style={[styles.price, { color: colors.foreground }]}>
                      {plan.formatted ?? "—"}
                    </Text>
                  </View>
                  {plan.description ? (
                    <Text
                      style={{
                        color: colors.mutedForeground,
                        marginTop: 4,
                        fontFamily: "SpaceGrotesk_400Regular",
                      }}
                    >
                      {plan.description}
                    </Text>
                  ) : null}
                  {plan.lost_addons.length > 0 ? (
                    <View
                      style={[
                        styles.warn,
                        { backgroundColor: colors.destructive + "14" },
                      ]}
                    >
                      <Feather
                        name="alert-triangle"
                        size={14}
                        color={colors.destructive}
                      />
                      <Text
                        style={{
                          color: colors.destructive,
                          flex: 1,
                          fontFamily: "SpaceGrotesk_500Medium",
                          fontSize: 12,
                        }}
                      >
                        You'll lose: {plan.lost_addons.join(", ")}
                      </Text>
                    </View>
                  ) : null}
                  <View style={{ marginTop: 12 }}>
                    <Button
                      label={
                        isScheduledTarget
                          ? "Already scheduled"
                          : `Downgrade to ${plan.name}`
                      }
                      variant={isScheduledTarget ? "outline" : "primary"}
                      onPress={() => confirmSchedule(plan)}
                      disabled={busy || isScheduledTarget}
                    />
                  </View>
                </View>
              );
            })
          )}

          <Pressable
            onPress={() => router.push("/plans" as never)}
            style={{ paddingVertical: 8 }}
          >
            <Text
              style={{
                color: colors.mutedForeground,
                textAlign: "center",
                fontFamily: "SpaceGrotesk_400Regular",
              }}
            >
              Looking to upgrade instead? Back to plans.
            </Text>
          </Pressable>
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  label: { fontSize: 11, letterSpacing: 1, fontFamily: "SpaceGrotesk_600SemiBold" },
  currentCard: { borderWidth: 1, padding: 16 },
  currentName: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 20,
    marginTop: 4,
  },
  scheduledCard: { borderWidth: 1, padding: 16 },
  scheduledHeader: { flexDirection: "row", alignItems: "center", gap: 8 },
  scheduledTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  intro: { fontSize: 13, lineHeight: 19, fontFamily: "SpaceGrotesk_400Regular" },
  card: { borderWidth: 1, padding: 16 },
  cardHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  planName: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  price: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  warn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 10,
    borderRadius: 10,
    marginTop: 10,
  },
});
