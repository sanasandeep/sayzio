import { Feather } from "@expo/vector-icons";
import React from "react";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useMutation, useQuery } from "@tanstack/react-query";

import { useColors } from "@/hooks/useColors";
import {
  billing,
  planPrice,
  type Currency,
  type Plan,
} from "@/lib/api/billing";

type Cycle = "monthly" | "annual";

function fmtMinorUpg(minor: number, currency: Currency): string {
  const symbol = currency === "INR" ? "₹" : "$";
  return (
    symbol +
    (minor / 100).toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

function upgradeHeadline(plan: Plan, currency: Currency, cycle: Cycle): string {
  if (cycle === "monthly") {
    return planPrice(plan, currency, "monthly").formatted ?? "—";
  }
  const annual = planPrice(plan, currency, "annual");
  if (!annual.amount_minor) return planPrice(plan, currency, "monthly").formatted ?? "—";
  return fmtMinorUpg(Math.round(annual.amount_minor / 12), currency);
}

function upgradeAnnualNote(plan: Plan, currency: Currency): string {
  const annual = planPrice(plan, currency, "annual");
  const monthly = planPrice(plan, currency, "monthly");
  if (!annual.amount_minor) return "";
  return monthly.amount_minor
    ? `Billed annually at ${annual.formatted ?? "—"}/yr — or ${monthly.formatted ?? "—"}/mo month-to-month`
    : `Billed annually at ${annual.formatted ?? "—"}/yr`;
}

const CURRENCIES: Currency[] = ["USD", "INR"];

/**
 * Resolve the plan to pre-highlight from the upgrade hint passed by the
 * plan-gating prompt (see `lib/upgradePrompt.ts`). Prefers the explicit
 * `plan` slug the Laravel side computed via `planThatUnlocks`; otherwise
 * falls back to resolving the cheapest non-current plan whose `features_map`
 * unlocks the blocked `feature` key. Returns `null` when no hint applies.
 */
function resolveRecommended(
  plans: Plan[],
  planSlug?: string,
  feature?: string,
): Plan | null {
  if (planSlug) {
    const bySlug = plans.find((p) => p.slug === planSlug);
    if (bySlug) return bySlug;
  }
  if (feature) {
    const current = plans.find((p) => p.is_current);
    const currentVal = Number(current?.features_map?.[feature] ?? 0);
    const unlocks = (p: Plan): boolean => {
      if (p.is_current) return false;
      const raw = p.features_map?.[feature];
      if (raw == null) return false;
      // Numeric caps (max_*, storage, contacts): qualify when the plan raises
      // the current cap (or is unlimited). Boolean flags: any truthy value.
      if (typeof raw === "number" || /^-?\d+$/.test(String(raw))) {
        const n = Number(raw);
        return n === -1 || n > currentVal;
      }
      return Boolean(raw);
    };
    const candidates = plans
      .filter(unlocks)
      .sort(
        (a, b) =>
          (a.monthly?.amount_minor ?? 0) - (b.monthly?.amount_minor ?? 0),
      );
    if (candidates[0]) return candidates[0];
  }
  return null;
}

export default function UpgradeScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ plan?: string; feature?: string }>();
  const planHint = typeof params.plan === "string" ? params.plan : undefined;
  const featureHint =
    typeof params.feature === "string" ? params.feature : undefined;

  const scrollRef = React.useRef<ScrollView>(null);
  const [recommendedY, setRecommendedY] = React.useState<number | null>(null);

  const plansQuery = useQuery({
    queryKey: ["billing", "plans"],
    queryFn: () => billing.plans(),
  });

  const [cycle, setCycle] = React.useState<Cycle>("monthly");
  const [currency, setCurrencyState] = React.useState<Currency | null>(null);
  const resolvedCurrency = plansQuery.data?.data?.currency;
  React.useEffect(() => {
    if (currency == null && resolvedCurrency) {
      const c = resolvedCurrency.toUpperCase();
      if (c === "USD" || c === "INR") setCurrencyState(c);
    }
  }, [currency, resolvedCurrency]);

  const currencies = plansQuery.data?.data?.currencies ?? CURRENCIES;
  const activeCurrency: Currency = currency ?? "USD";

  const persistCurrency = useMutation({
    mutationFn: (c: Currency) => billing.setCurrency(c),
  });
  const onCurrencyChange = (c: Currency) => {
    setCurrencyState(c);
    persistCurrency.mutate(c);
  };

  const plans = plansQuery.data?.data?.plans ?? [];
  const free = plans.find((p) => (p.monthly?.amount_minor ?? 0) === 0);
  const popular =
    plans.find((p) => p.is_popular) ??
    plans.find((p) => (p.monthly?.amount_minor ?? 0) > 0);

  const recommended = React.useMemo(
    () => resolveRecommended(plans, planHint, featureHint),
    [plans, planHint, featureHint],
  );

  // Lead with the recommended plan so it's the first card the user sees, then
  // the usual free + popular pair (deduped). Falls back to the generic pair
  // when there's no hint.
  const featured: Plan[] = [recommended, free, popular]
    .filter((p): p is Plan => p != null)
    .filter((p, i, arr) => arr.findIndex((q) => q.id === p.id) === i);

  // Once the recommended card has measured its position, scroll it into view.
  React.useEffect(() => {
    if (recommended && recommendedY != null) {
      const y = Math.max(0, recommendedY - 12);
      const t = setTimeout(
        () => scrollRef.current?.scrollTo({ y, animated: true }),
        250,
      );
      return () => clearTimeout(t);
    }
  }, [recommended, recommendedY]);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Upgrade", headerShown: true }} />
      <ScrollView
        ref={scrollRef}
        contentContainerStyle={{
          paddingTop: 16,
          paddingHorizontal: 20,
          paddingBottom: insets.bottom + 32,
          gap: 16,
        }}
      >
        <Text style={[styles.heading, { color: colors.foreground }]}>
          {recommended
            ? `${recommended.name} unlocks what you just tried.`
            : "Pick the plan that fits your work."}
        </Text>
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          {recommended
            ? "We've highlighted the cheapest plan that includes it below."
            : "Start free. Upgrade only when you outgrow it."}
        </Text>

        <View style={{ flexDirection: "row", gap: 8, justifyContent: "center", flexWrap: "wrap" }}>
          <View style={[styles.toggle, { borderColor: colors.border }]}>
            {(["monthly", "annual"] as Cycle[]).map((c) => {
              const active = cycle === c;
              return (
                <Pressable
                  key={c}
                  onPress={() => setCycle(c)}
                  style={[
                    styles.toggleBtn,
                    { backgroundColor: active ? colors.primary : "transparent" },
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
                    { backgroundColor: active ? colors.primary : "transparent" },
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
          <Text style={{ color: colors.destructive }}>Could not load plans.</Text>
        ) : (
          featured.map((plan) => {
            const isRecommended = recommended?.id === plan.id;
            const popularBadge = !!plan.is_popular && !isRecommended;
            const emphasised = isRecommended || popularBadge;
            const isFree = (planPrice(plan, activeCurrency, "monthly").amount_minor ?? 0) === 0;
            const headline = isFree
              ? (planPrice(plan, activeCurrency, "monthly").formatted ?? "$0.00")
              : upgradeHeadline(plan, activeCurrency, cycle);
            const billingNote =
              !isFree && cycle === "annual"
                ? upgradeAnnualNote(plan, activeCurrency)
                : null;
            return (
              <View
                key={plan.id}
                onLayout={
                  isRecommended
                    ? (e) => setRecommendedY(e.nativeEvent.layout.y)
                    : undefined
                }
                style={[
                  styles.card,
                  {
                    backgroundColor: colors.card,
                    borderColor: emphasised ? colors.primary : colors.border,
                    borderWidth: isRecommended ? 2 : 1,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View style={styles.cardHeader}>
                  <Text style={[styles.planName, { color: colors.foreground }]}>{plan.name}</Text>
                  {isRecommended ? (
                    <View style={[styles.badge, { backgroundColor: colors.primary }]}>
                      <Text style={{ color: colors.primaryForeground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 11 }}>
                        RECOMMENDED
                      </Text>
                    </View>
                  ) : popularBadge ? (
                    <View style={[styles.badge, { backgroundColor: colors.primary }]}>
                      <Text style={{ color: colors.primaryForeground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 11 }}>
                        MOST POPULAR
                      </Text>
                    </View>
                  ) : null}
                </View>
                <Text style={[styles.price, { color: colors.foreground }]}>
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
                    Free forever — no card required
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
                  <Text style={[styles.desc, { color: colors.mutedForeground }]}>
                    {plan.description}
                  </Text>
                ) : null}
                {(plan.feature_highlights ?? []).slice(0, 5).map((f, i) => (
                  <View key={i} style={styles.featureRow}>
                    <Feather name="check" size={14} color={colors.primary} />
                    <Text style={{ color: colors.foreground, flex: 1, fontFamily: "SpaceGrotesk_400Regular" }}>
                      {f}
                    </Text>
                  </View>
                ))}
                <Button
                  label={emphasised ? `Choose ${plan.name}` : "Stay on Free"}
                  variant={emphasised ? "cta" : "outline"}
                  onPress={() => router.push("/plans" as never)}
                  style={{ marginTop: 14 }}
                />
              </View>
            );
          })
        )}

        <Text style={[styles.linksHeader, { color: colors.mutedForeground }]}>
          Want the full picture?
        </Text>
        <Pressable
          onPress={() => router.push("/plans" as never)}
          style={[styles.linkCard, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
        >
          <Feather name="tag" size={18} color={colors.primary} />
          <View style={{ flex: 1 }}>
            <Text style={[styles.linkTitle, { color: colors.foreground }]}>See all plans</Text>
            <Text style={[styles.linkSub, { color: colors.mutedForeground }]}>Compare every tier side by side.</Text>
          </View>
          <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
        </Pressable>
        <Pressable
          onPress={() => router.push("/coin-packages" as never)}
          style={[styles.linkCard, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
        >
          <Feather name="dollar-sign" size={18} color={colors.primary} />
          <View style={{ flex: 1 }}>
            <Text style={[styles.linkTitle, { color: colors.foreground }]}>Coin packages</Text>
            <Text style={[styles.linkSub, { color: colors.mutedForeground }]}>Top up coins for paid add-ons.</Text>
          </View>
          <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
        </Pressable>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  heading: { fontSize: 22, fontFamily: "SpaceGrotesk_700Bold" },
  intro: { fontSize: 13, lineHeight: 18 },
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
  card: { borderWidth: 1, padding: 16 },
  cardHeader: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 4 },
  planName: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  badge: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: 999 },
  price: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 28, marginTop: 4 },
  desc: { marginTop: 4, fontFamily: "SpaceGrotesk_400Regular" },
  featureRow: { flexDirection: "row", alignItems: "center", gap: 8, marginTop: 6 },
  linksHeader: { fontSize: 12, textTransform: "uppercase", letterSpacing: 1, marginTop: 12 },
  linkCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  linkTitle: { fontSize: 14, fontFamily: "SpaceGrotesk_700Bold" },
  linkSub: { fontSize: 12, fontFamily: "SpaceGrotesk_400Regular", marginTop: 2 },
});
