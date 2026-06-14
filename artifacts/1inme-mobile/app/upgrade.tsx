import { Feather } from "@expo/vector-icons";
import React from "react";
import { Stack, useRouter } from "expo-router";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useMutation, useQuery } from "@tanstack/react-query";

import { useColors } from "@/hooks/useColors";
import { billing, planPrice, type Currency, type Plan } from "@/lib/api/billing";

const CURRENCIES: Currency[] = ["USD", "INR"];

export default function UpgradeScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const plansQuery = useQuery({
    queryKey: ["billing", "plans"],
    queryFn: () => billing.plans(),
  });

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

  const featured: Plan[] = [free, popular]
    .filter((p): p is Plan => p != null)
    .filter((p, i, arr) => arr.findIndex((q) => q.id === p.id) === i);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Upgrade", headerShown: true }} />
      <ScrollView
        contentContainerStyle={{
          paddingTop: 16,
          paddingHorizontal: 20,
          paddingBottom: insets.bottom + 32,
          gap: 16,
        }}
      >
        <Text style={[styles.heading, { color: colors.foreground }]}>
          Pick the plan that fits your work.
        </Text>
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          Start free. Upgrade only when you outgrow it.
        </Text>

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

        {plansQuery.isLoading ? (
          <ActivityIndicator color={colors.primary} />
        ) : plansQuery.error ? (
          <Text style={{ color: colors.destructive }}>Could not load plans.</Text>
        ) : (
          featured.map((plan) => {
            const price = planPrice(plan, activeCurrency, "monthly");
            const popularBadge = !!plan.is_popular;
            return (
              <View
                key={plan.id}
                style={[
                  styles.card,
                  {
                    backgroundColor: colors.card,
                    borderColor: popularBadge ? colors.primary : colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View style={styles.cardHeader}>
                  <Text style={[styles.planName, { color: colors.foreground }]}>{plan.name}</Text>
                  {popularBadge ? (
                    <View style={[styles.badge, { backgroundColor: colors.primary }]}>
                      <Text style={{ color: colors.primaryForeground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 11 }}>
                        MOST POPULAR
                      </Text>
                    </View>
                  ) : null}
                </View>
                <Text style={[styles.price, { color: colors.foreground }]}>
                  {price.formatted ?? "—"}
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      fontSize: 14,
                      fontFamily: "SpaceGrotesk_400Regular",
                    }}
                  >
                    {" "}/ mo
                  </Text>
                </Text>
                {plan.description ? (
                  <Text style={[styles.desc, { color: colors.mutedForeground }]}>
                    {plan.description}
                  </Text>
                ) : null}
                {plan.features.slice(0, 5).map((f, i) => (
                  <View key={i} style={styles.featureRow}>
                    <Feather name="check" size={14} color={colors.primary} />
                    <Text style={{ color: colors.foreground, flex: 1, fontFamily: "SpaceGrotesk_400Regular" }}>
                      {f}
                    </Text>
                  </View>
                ))}
                <Pressable
                  onPress={() => router.push("/plans" as never)}
                  style={[
                    styles.cta,
                    {
                      backgroundColor: popularBadge ? colors.primary : "transparent",
                      borderColor: colors.primary,
                    },
                  ]}
                >
                  <Text
                    style={{
                      color: popularBadge ? colors.primaryForeground : colors.primary,
                      fontFamily: "SpaceGrotesk_700Bold",
                    }}
                  >
                    {popularBadge ? `Choose ${plan.name}` : "Stay on Free"}
                  </Text>
                </Pressable>
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
        <Pressable
          onPress={() => router.push("/premium-features" as never)}
          style={[styles.linkCard, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}
        >
          <Feather name="star" size={18} color={colors.primary} />
          <View style={{ flex: 1 }}>
            <Text style={[styles.linkTitle, { color: colors.foreground }]}>Premium features</Text>
            <Text style={[styles.linkSub, { color: colors.mutedForeground }]}>What unlocks on a paid plan.</Text>
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
  cta: {
    marginTop: 14,
    paddingVertical: 12,
    borderRadius: 999,
    borderWidth: 1,
    alignItems: "center",
  },
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
