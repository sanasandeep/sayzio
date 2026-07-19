import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import React, { useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Button } from "@/components/Button";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { useForegroundRefresh } from "@/hooks/useForegroundRefresh";
import {
  billing,
  planPrice,
  type Currency,
  type Plan,
} from "@/lib/api/billing";
import { getBaseUrl } from "@/lib/api";
import { showAlert } from "@/lib/webAlert";

type Cycle = "monthly" | "annual";

const CURRENCIES: Currency[] = ["USD", "INR"];

/**
 * Open the website's pricing page in the OS external browser (Safari / Chrome).
 * An external browser session does not carry the app's bearer token, so the
 * user lands on the public /pricing page and can sign in there to complete
 * a purchase.
 */
function openPricingPage(): void {
  // The `client=app` marker tells the website this checkout originated from
  // the native app, so its post-payment success page fires the
  // `sayzio://billing/refresh` deep link to bounce the user back here with a
  // freshly-refreshed plan (see DeepLinkRouter).
  const url = `${getBaseUrl()}/pricing?client=app`;
  if (Platform.OS === "web") {
    if (typeof window !== "undefined") {
      window.open(url, "_blank");
    }
    return;
  }
  Linking.openURL(url).catch(() => {});
}

/**
 * Format a minor-unit amount in the same style as PHP PricingResolver::money().
 * e.g. 750 (minor) USD → "$7.50"
 */
function fmtMinor(minor: number, currency: Currency): string {
  const symbol = currency === "INR" ? "₹" : "$";
  return (
    symbol +
    (minor / 100).toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

/**
 * On the annual cycle, return the effective per-month price (annual ÷ 12).
 * On the monthly cycle, return the normal monthly formatted string.
 */
function planHeadlineFormatted(
  plan: Plan,
  currency: Currency,
  cycle: Cycle,
): string {
  if (cycle === "monthly") {
    return planPrice(plan, currency, "monthly").formatted ?? "—";
  }
  const annual = planPrice(plan, currency, "annual");
  if (!annual.amount_minor) return planPrice(plan, currency, "monthly").formatted ?? "—";
  return fmtMinor(Math.round(annual.amount_minor / 12), currency);
}

/**
 * Fineprint for the annual billing note shown below the per-month headline.
 */
function annualBillingNote(plan: Plan, currency: Currency): string {
  const annual = planPrice(plan, currency, "annual");
  const monthly = planPrice(plan, currency, "monthly");
  if (!annual.amount_minor) return "";
  const annualFmt = annual.formatted ?? "—";
  const monthlyFmt = monthly.formatted;
  return monthlyFmt && monthly.amount_minor
    ? `Billed annually at ${annualFmt}/yr, or ${monthlyFmt}/mo month-to-month`
    : `Billed annually at ${annualFmt}/yr`;
}

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

  const [cycle, setCycle] = useState<Cycle>("monthly");
  const [currency, setCurrencyState] = useState<Currency | null>(null);

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
      showAlert("Downgrade cancelled", res.data.message);
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't cancel", e?.message ?? "Please try again."),
  });

  // Surfaces the cancel-at-period-end state so a mobile user who scheduled a
  // cancellation can undo it here (web parity — no longer web-only).
  const subscriptionQuery = useQuery({
    queryKey: ["billing", "subscription"],
    queryFn: () => billing.subscription(),
  });

  const resume = useMutation({
    mutationFn: () => billing.resume(),
    onSuccess: async (res) => {
      await Promise.all([
        qc.invalidateQueries({ queryKey: ["billing", "subscription"] }),
        qc.invalidateQueries({ queryKey: ["billing", "plans"] }),
        qc.invalidateQueries({ queryKey: ["billing", "downgrade"] }),
      ]);
      refreshAuth?.();
      showAlert("Plan resumed", res.data.message);
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't resume", e?.message ?? "Please try again."),
  });

  // Start a cancel-at-period-end. Mirrors web BillingController::cancel() so a
  // mobile user can stop renewing without contacting support or using the web.
  const cancel = useMutation({
    mutationFn: () => billing.cancel(),
    onSuccess: async (res) => {
      await Promise.all([
        qc.invalidateQueries({ queryKey: ["billing", "subscription"] }),
        qc.invalidateQueries({ queryKey: ["billing", "plans"] }),
        qc.invalidateQueries({ queryKey: ["billing", "downgrade"] }),
      ]);
      showAlert("Cancellation scheduled", res.data.message);
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't cancel", e?.message ?? "Please try again."),
  });

  // The "Upgrade on the web" flow hands off to the OS browser and relies on
  // the user returning to the app. Nothing in an external browser session can
  // push the new plan back into the app, so when the app returns to the
  // foreground we invalidate every billing query (plans, subscription,
  // downgrade) and refresh auth. This makes the "CURRENT" badge move to the
  // just-purchased plan and the upgrade CTAs disappear without a manual reload.
  useForegroundRefresh(() => {
    qc.invalidateQueries({ queryKey: ["billing", "plans"] });
    qc.invalidateQueries({ queryKey: ["billing", "subscription"] });
    qc.invalidateQueries({ queryKey: ["billing", "downgrade"] });
    refreshAuth?.();
  });

  // Seed the currency from the backend-resolved default once, then let the
  // user flip it manually for price display purposes.
  const resolvedCurrency = plansQuery.data?.data?.currency;
  React.useEffect(() => {
    if (currency == null && resolvedCurrency) {
      const c = resolvedCurrency.toUpperCase();
      if (c === "USD" || c === "INR") setCurrencyState(c);
    }
  }, [currency, resolvedCurrency]);

  const currencies = plansQuery.data?.data?.currencies ?? CURRENCIES;
  const activeCurrency: Currency = currency ?? "USD";

  // Persist the manual currency pick so price display is consistent.
  const persistCurrency = useMutation({
    mutationFn: (c: Currency) => billing.setCurrency(c),
  });

  const onCurrencyChange = (c: Currency) => {
    setCurrencyState(c);
    persistCurrency.mutate(c);
  };

  const data = plansQuery.data?.data;
  const plans = data?.plans ?? [];

  const scheduledDowngrade = downgradeQuery.data?.data?.scheduled_downgrade ?? null;

  const subscription = subscriptionQuery.data?.data?.subscription ?? null;
  const cancelAtPeriodEnd = subscription?.cancel_at_period_end === true;

  const confirmResume = () => {
    showAlert(
      "Resume subscription?",
      "Your plan will continue renewing and won't cancel at the end of your cycle.",
      [
        { text: "Keep cancellation", style: "cancel" },
        { text: "Resume", onPress: () => resume.mutate() },
      ],
    );
  };

  const confirmCancel = () => {
    showAlert(
      "Stop renewing at period end?",
      "You will keep paid features until the current period ends.",
      [
        { text: "Keep my plan", style: "cancel" },
        {
          text: "Cancel renewal",
          style: "destructive",
          onPress: () => cancel.mutate(),
        },
      ],
    );
  };

  const confirmCancelDowngrade = () => {
    showAlert(
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

  // Show the downgrade entry point only when the user is on a paid plan.
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

        {cancelAtPeriodEnd ? (
          <View
            style={[
              styles.scheduledCard,
              {
                backgroundColor: colors.destructive + "14",
                borderColor: colors.destructive,
                borderRadius: colors.radius,
              },
            ]}
          >
            <View style={styles.scheduledHeader}>
              <Feather name="x-circle" size={18} color={colors.destructive} />
              <Text style={[styles.scheduledTitle, { color: colors.foreground }]}>
                Cancellation scheduled
              </Text>
            </View>
            <Text
              style={{
                color: colors.mutedForeground,
                fontFamily: "SpaceGrotesk_400Regular",
                marginTop: 4,
              }}
            >
              Your plan will cancel on{" "}
              {formatApplies(
                subscription?.cancel_at ??
                  subscription?.current_period_end ??
                  null,
              )}
              . Resume to keep your plan and continue renewing.
            </Text>
            <View style={{ marginTop: 12 }}>
              <Button
                label={resume.isPending ? "Resuming…" : "Resume"}
                variant="cta"
                onPress={confirmResume}
                disabled={resume.isPending}
              />
            </View>
          </View>
        ) : null}

        {/* Upgrade banner — always visible so the user knows how to upgrade */}
        <View
          style={[
            styles.webBanner,
            {
              backgroundColor: colors.primary + "14",
              borderColor: colors.primary + "44",
              borderRadius: colors.radius,
            },
          ]}
        >
          <Feather name="external-link" size={15} color={colors.primary} />
          <Text style={[styles.webBannerText, { color: colors.mutedForeground }]}>
            Plan upgrades are completed on the website.{" "}
            <Text
              style={{ color: colors.primary, fontFamily: "SpaceGrotesk_600SemiBold" }}
              onPress={openPricingPage}
            >
              Open pricing page
            </Text>{" "}
            in your browser to choose a plan.
          </Text>
        </View>

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
            const isCurrent = plan.is_current;
            const isFree = (planPrice(plan, activeCurrency, "monthly").amount_minor ?? 0) === 0;
            const headline = isFree
              ? (planPrice(plan, activeCurrency, "monthly").formatted ?? "$0.00")
              : planHeadlineFormatted(plan, activeCurrency, cycle);
            const billingNote =
              !isFree && cycle === "annual"
                ? annualBillingNote(plan, activeCurrency)
                : null;
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
                  {headline}
                  {!isFree ? (
                    <Text
                      style={{
                        color: colors.mutedForeground,
                        fontSize: 14,
                        fontFamily: "SpaceGrotesk_400Regular",
                      }}
                    >
                      {" "}/ mo
                    </Text>
                  ) : null}
                </Text>
                {isFree ? (
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      fontSize: 11,
                      fontFamily: "SpaceGrotesk_400Regular",
                      marginTop: 2,
                    }}
                  >
                    Free forever, no card required
                  </Text>
                ) : billingNote ? (
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      fontSize: 11,
                      fontFamily: "SpaceGrotesk_400Regular",
                      marginTop: 2,
                    }}
                  >
                    {billingNote}
                  </Text>
                ) : null}
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
                {!isCurrent ? (
                  <View style={{ marginTop: 12 }}>
                    <Button
                      label="Upgrade on the web"
                      onPress={openPricingPage}
                      variant="cta"
                    />
                  </View>
                ) : null}
              </View>
            );
          })
        )}

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

        {onPaidPlan && !cancelAtPeriodEnd ? (
          <Pressable
            onPress={confirmCancel}
            disabled={cancel.isPending}
            style={[
              styles.downgrade,
              {
                borderColor: colors.border,
                borderRadius: colors.radius,
                opacity: cancel.isPending ? 0.6 : 1,
              },
            ]}
          >
            <Feather name="x-circle" size={18} color={colors.destructive} />
            <View style={{ flex: 1 }}>
              <Text
                style={{
                  color: colors.foreground,
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  fontSize: 14,
                }}
              >
                {cancel.isPending ? "Cancelling…" : "Cancel at period end"}
              </Text>
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontFamily: "SpaceGrotesk_400Regular",
                  fontSize: 12,
                  marginTop: 2,
                }}
              >
                Stop renewing. Keep paid features until the period ends.
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
  webBanner: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
    padding: 12,
    borderWidth: 1,
  },
  webBannerText: {
    flex: 1,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
  },
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
  downgrade: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
});
