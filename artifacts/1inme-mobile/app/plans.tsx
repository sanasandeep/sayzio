import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import React, { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  PurchasesOffering,
  PurchasesPackage,
} from "react-native-purchases";

import { Button } from "@/components/Button";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import {
  billing,
  planPrice,
  type Currency,
  type Plan,
} from "@/lib/api/billing";
import {
  isRevenueCatConfigured,
  useSubscription,
} from "@/lib/revenuecat";

type Cycle = "monthly" | "annual";

const CURRENCIES: Currency[] = ["USD", "INR"];

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

export default function PlansScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const qc = useQueryClient();
  const { refresh: refreshAuth } = useAuth();
  const sub = useSubscription();

  const [cycle, setCycle] = useState<Cycle>("monthly");
  const [currency, setCurrencyState] = useState<Currency | null>(null);
  const [confirm, setConfirm] = useState<{
    plan: Plan;
    pkg: PurchasesPackage;
  } | null>(null);

  const plansQuery = useQuery({
    queryKey: ["billing", "plans"],
    queryFn: () => billing.plans(),
  });

  // Shares the cache key with the dedicated Downgrade screen so cancelling
  // a scheduled downgrade from either surface keeps both in sync.
  const downgradeQuery = useQuery({
    queryKey: ["billing", "downgrade"],
    queryFn: () => billing.downgradeOptions(),
  });

  const cancelDowngrade = useMutation({
    mutationFn: () => billing.cancelDowngrade(),
    onSuccess: async (res) => {
      await Promise.all([
        qc.invalidateQueries({ queryKey: ["billing", "downgrade"] }),
        qc.invalidateQueries({ queryKey: ["billing", "plans"] }),
        qc.invalidateQueries({ queryKey: ["billing", "subscription"] }),
      ]);
      Alert.alert("Downgrade cancelled", res.data.message);
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't cancel", e?.message ?? "Please try again."),
  });

  // Seed the currency from the backend-resolved default (geo / profile /
  // saved preference) once, then let the user flip it manually.
  const resolvedCurrency = plansQuery.data?.data?.currency;
  React.useEffect(() => {
    if (currency == null && resolvedCurrency) {
      const c = resolvedCurrency.toUpperCase();
      if (c === "USD" || c === "INR") setCurrencyState(c);
    }
  }, [currency, resolvedCurrency]);

  const currencies = plansQuery.data?.data?.currencies ?? CURRENCIES;
  const activeCurrency: Currency = currency ?? "USD";

  // Persist the manual pick so purchase/activation use the same currency
  // and it follows the user across devices. Fire-and-forget — the UI has
  // already flipped client-side from the pre-computed price matrix.
  const persistCurrency = useMutation({
    mutationFn: (c: Currency) => billing.setCurrency(c),
  });

  const onCurrencyChange = (c: Currency) => {
    setCurrencyState(c);
    persistCurrency.mutate(c);
  };

  const activate = useMutation({
    mutationFn: (input: {
      plan_id: number;
      cycle: Cycle;
      entitlement: string;
    }) => billing.activateRevenueCat(input),
    onSuccess: async () => {
      await Promise.all([
        qc.invalidateQueries({ queryKey: ["billing", "plans"] }),
        refreshAuth?.(),
      ]);
    },
  });

  const restore = useMutation({
    mutationFn: async () => {
      // Use the CustomerInfo *returned by* restore() — sub.activeEntitlements
      // reflects the cached query and may not yet be invalidated by the
      // time we run this loop.
      const info = await sub.restore();
      const activeKeys = info ? Object.keys(info.entitlements.active) : [];
      const plans = plansQuery.data?.data?.plans ?? [];
      for (const p of plans) {
        if (activeKeys.includes(p.slug)) {
          try {
            await billing.activateRevenueCat({
              plan_id: p.id,
              cycle,
              entitlement: p.slug,
            });
          } catch {
            /* ignore individual failures during restore */
          }
        }
      }
    },
    onSuccess: async () => {
      await Promise.all([
        qc.invalidateQueries({ queryKey: ["billing", "plans"] }),
        refreshAuth?.(),
      ]);
      Alert.alert("Restore complete", "We re-checked your purchases.");
    },
    onError: (e: any) =>
      Alert.alert("Restore failed", e?.message ?? "Please try again."),
  });

  const offering: PurchasesOffering | null = sub.currentOffering;

  // Strict mapping: a package belongs to (plan, cycle) only when its RC
  // package identifier OR its store product identifier exactly equals
  // `<slug>_<cycle>`. We deliberately do not fall back to a `startsWith`
  // match — picking the wrong cycle here would charge the user for the
  // wrong period.
  const findPackage = (plan: Plan): PurchasesPackage | null => {
    if (!offering) return null;
    const wanted = `${plan.slug}_${cycle}`.toLowerCase();
    return (
      offering.availablePackages.find((p) => {
        const id = (p.identifier ?? "").toLowerCase();
        const prodId = (p.product?.identifier ?? "").toLowerCase();
        return id === wanted || prodId === wanted;
      }) ?? null
    );
  };

  const onPurchase = (plan: Plan) => {
    if (!isRevenueCatConfigured()) {
      Alert.alert(
        "Billing not available",
        "In-app purchases haven't been configured for this build yet.",
      );
      return;
    }
    const pkg = findPackage(plan);
    if (!pkg) {
      Alert.alert(
        "Plan unavailable",
        `No matching ${cycle} package found for ${plan.name}.`,
      );
      return;
    }
    setConfirm({ plan, pkg });
  };

  const confirmPurchase = async () => {
    if (!confirm) return;
    const { plan, pkg } = confirm;
    setConfirm(null);
    try {
      await sub.purchase(pkg);
      await activate.mutateAsync({
        plan_id: plan.id,
        cycle,
        entitlement: plan.slug,
      });
      Alert.alert("All set", `${plan.name} is now active.`);
    } catch (e: any) {
      if (e?.userCancelled) return;
      Alert.alert(
        "Purchase failed",
        e?.message ?? "Something went wrong. Please try again.",
      );
    }
  };

  const data = plansQuery.data?.data;
  const plans = data?.plans ?? [];

  const scheduledDowngrade = downgradeQuery.data?.data?.scheduled_downgrade ?? null;

  const confirmCancelDowngrade = () => {
    Alert.alert(
      "Cancel scheduled downgrade?",
      "You'll stay on your current plan and nothing will change at the end of your cycle.",
      [
        { text: "Keep downgrade", style: "cancel" },
        {
          text: "Cancel downgrade",
          style: "destructive",
          onPress: () => cancelDowngrade.mutate(),
        },
      ],
    );
  };

  // Show the downgrade entry point only when the user is on a paid plan
  // (a lower paid plan exists to move to). Free users have nothing to
  // downgrade — they'd cancel instead.
  const currentPlan = plans.find((p) => p.is_current);
  const onPaidPlan =
    !!currentPlan && (currentPlan.monthly?.amount_minor ?? 0) > 0;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Plans & billing" }} />
      <ScrollView
        contentContainerStyle={{
          paddingHorizontal: 20,
          paddingTop: 20,
          paddingBottom: insets.bottom + 32,
          gap: 16,
        }}
      >
        {scheduledDowngrade ? (
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
              <Text style={[styles.scheduledTitle, { color: colors.foreground }]}>
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
              Your plan will change to {scheduledDowngrade.plan_name} on{" "}
              {formatApplies(scheduledDowngrade.applies_at)}. Cancel to stay on
              your current plan.
            </Text>
            <View style={{ marginTop: 12 }}>
              <Button
                label={
                  cancelDowngrade.isPending ? "Cancelling…" : "Cancel downgrade"
                }
                variant="outline"
                onPress={confirmCancelDowngrade}
                disabled={cancelDowngrade.isPending}
              />
            </View>
          </View>
        ) : null}

        <View style={styles.switchers}>
          <View style={[styles.toggle, { borderColor: colors.border }]}>
            {(["monthly", "annual"] as Cycle[]).map((c) => {
              const active = cycle === c;
              return (
                <Pressable
                  key={c}
                  onPress={() => setCycle(c)}
                  style={[
                    styles.toggleBtn,
                    {
                      backgroundColor: active ? colors.primary : "transparent",
                    },
                  ]}
                >
                  <Text
                    style={{
                      fontFamily: "SpaceGrotesk_600SemiBold",
                      color: active ? colors.primaryForeground : colors.foreground,
                      textTransform: "capitalize",
                    }}
                  >
                    {c}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          <View style={[styles.toggle, { borderColor: colors.border }]}>
            {currencies.map((c) => {
              const active = activeCurrency === c;
              return (
                <Pressable
                  key={c}
                  onPress={() => onCurrencyChange(c)}
                  style={[
                    styles.toggleBtn,
                    {
                      backgroundColor: active ? colors.primary : "transparent",
                    },
                  ]}
                >
                  <Text
                    style={{
                      fontFamily: "SpaceGrotesk_600SemiBold",
                      color: active ? colors.primaryForeground : colors.foreground,
                    }}
                  >
                    {c}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        </View>

        {plansQuery.isLoading ? (
          <ActivityIndicator color={colors.primary} />
        ) : plansQuery.error ? (
          <Text style={{ color: colors.destructive }}>
            Could not load plans.
          </Text>
        ) : (
          plans.map((plan) => {
            const price = planPrice(plan, activeCurrency, cycle);
            const isCurrent = plan.is_current;
            return (
              <View
                key={plan.id}
                style={[
                  styles.card,
                  {
                    backgroundColor: colors.card,
                    borderColor: isCurrent ? colors.primary : colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View style={styles.cardHeader}>
                  <Text
                    style={[
                      styles.planName,
                      { color: colors.foreground },
                    ]}
                  >
                    {plan.name}
                  </Text>
                  {isCurrent ? (
                    <View
                      style={[
                        styles.badge,
                        { backgroundColor: colors.primary },
                      ]}
                    >
                      <Text
                        style={{
                          color: colors.primaryForeground,
                          fontFamily: "SpaceGrotesk_600SemiBold",
                          fontSize: 11,
                        }}
                      >
                        CURRENT
                      </Text>
                    </View>
                  ) : null}
                </View>
                <Text
                  style={[styles.price, { color: colors.foreground }]}
                >
                  {price.formatted ?? "—"}
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      fontSize: 14,
                      fontFamily: "SpaceGrotesk_400Regular",
                    }}
                  >
                    {" "}
                    / {cycle === "monthly" ? "mo" : "yr"}
                  </Text>
                </Text>
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
                {(plan.feature_highlights ?? []).slice(0, 6).map((f, i) => (
                  <View key={i} style={styles.featureRow}>
                    <Feather name="check" size={14} color={colors.primary} />
                    <Text
                      style={{
                        color: colors.foreground,
                        fontFamily: "SpaceGrotesk_400Regular",
                        flex: 1,
                      }}
                    >
                      {f}
                    </Text>
                  </View>
                ))}
                <View style={{ marginTop: 12 }}>
                  <Button
                    label={isCurrent ? "Current plan" : "Choose plan"}
                    onPress={() => onPurchase(plan)}
                    variant={isCurrent ? "outline" : "primary"}
                    disabled={
                      isCurrent || sub.isPurchasing || activate.isPending
                    }
                  />
                </View>
              </View>
            );
          })
        )}

        <Pressable
          onPress={() => restore.mutate()}
          disabled={restore.isPending || sub.isRestoring}
          style={({ pressed }) => [
            styles.restore,
            { opacity: pressed || restore.isPending ? 0.6 : 1 },
          ]}
        >
          <Feather name="refresh-cw" size={16} color={colors.primary} />
          <Text
            style={{
              color: colors.primary,
              fontFamily: "SpaceGrotesk_600SemiBold",
            }}
          >
            {restore.isPending || sub.isRestoring
              ? "Restoring…"
              : "Restore purchases"}
          </Text>
        </Pressable>

        {onPaidPlan ? (
          <Pressable
            onPress={() => router.push("/billing/downgrade" as never)}
            style={[
              styles.downgrade,
              { borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Feather name="arrow-down-circle" size={18} color={colors.mutedForeground} />
            <View style={{ flex: 1 }}>
              <Text
                style={{
                  color: colors.foreground,
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  fontSize: 14,
                }}
              >
                Downgrade plan
              </Text>
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontFamily: "SpaceGrotesk_400Regular",
                  fontSize: 12,
                  marginTop: 2,
                }}
              >
                Move to a lower paid plan at the end of your cycle.
              </Text>
            </View>
            <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
          </Pressable>
        ) : null}

        <Pressable onPress={() => router.push("/wallet" as never)}>
          <Text
            style={{
              color: colors.mutedForeground,
              textAlign: "center",
              fontFamily: "SpaceGrotesk_400Regular",
              marginTop: 4,
            }}
          >
            Looking for coin packs? Open Wallet & coins.
          </Text>
        </Pressable>
      </ScrollView>

      <Modal
        visible={confirm !== null}
        transparent
        animationType="fade"
        onRequestClose={() => setConfirm(null)}
      >
        <View style={styles.modalOverlay}>
          <View
            style={[
              styles.modalCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Text
              style={{
                color: colors.foreground,
                fontFamily: "SpaceGrotesk_700Bold",
                fontSize: 18,
              }}
            >
              Confirm purchase
            </Text>
            <Text
              style={{
                color: colors.mutedForeground,
                marginTop: 8,
                fontFamily: "SpaceGrotesk_400Regular",
              }}
            >
              {confirm
                ? `${confirm.plan.name} — ${confirm.pkg.product.priceString} (${cycle})`
                : ""}
            </Text>
            <View style={{ flexDirection: "row", gap: 8, marginTop: 16 }}>
              <View style={{ flex: 1 }}>
                <Button
                  label="Cancel"
                  variant="outline"
                  onPress={() => setConfirm(null)}
                />
              </View>
              <View style={{ flex: 1 }}>
                <Button label="Buy" onPress={confirmPurchase} />
              </View>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  switchers: {
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    flexWrap: "wrap",
    gap: 10,
  },
  toggle: {
    flexDirection: "row",
    borderWidth: 1,
    borderRadius: 999,
    padding: 4,
    alignSelf: "center",
  },
  toggleBtn: {
    paddingVertical: 8,
    paddingHorizontal: 18,
    borderRadius: 999,
  },
  scheduledCard: { borderWidth: 1, padding: 16 },
  scheduledHeader: { flexDirection: "row", alignItems: "center", gap: 8 },
  scheduledTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  card: {
    borderWidth: 1,
    padding: 16,
  },
  cardHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 4,
  },
  planName: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 18,
  },
  price: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 28,
    marginTop: 4,
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
  },
  featureRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginTop: 8,
  },
  restore: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 12,
  },
  downgrade: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
    alignItems: "center",
    justifyContent: "center",
    padding: 20,
  },
  modalCard: {
    width: "100%",
    maxWidth: 360,
    borderWidth: 1,
    padding: 20,
  },
});
