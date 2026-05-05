import { useMutation, useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import {
  getMySubscription,
  listCreatorTiers,
  startSubscribe,
  type SubscriptionTier,
} from "@/lib/api/monetization";

/**
 * Mobile parity for the public /@handle/subscribe page (Task #1209).
 * The screen is keyed by the creator's `?handle=…` param. It surfaces
 * the same monthly/yearly toggle, tier cards, optional promo code,
 * and opens the provider's hosted checkout in the system browser
 * exactly the way the Payouts onboarding flow does.
 */
export default function SubscribeScreen() {
  const colors = useColors();
  const router = useRouter();
  const { handle = "" } = useLocalSearchParams<{ handle?: string }>();
  const [cycle, setCycle] = useState<"monthly" | "yearly">("monthly");
  const [promo, setPromo] = useState("");

  const tiersQ = useQuery({
    queryKey: ["creator-tiers", handle],
    queryFn: () => listCreatorTiers(handle),
    enabled: !!handle,
  });
  const tiers = (tiersQ.data?.tiers ?? []) as SubscriptionTier[];
  const mine = useQuery({
    queryKey: ["my-subscription", handle],
    queryFn: () => getMySubscription(handle),
    enabled: !!handle,
  });

  const subscribe = useMutation({
    mutationFn: (tierId: number) =>
      startSubscribe(handle, { tier_id: tierId, cycle, promo_code: promo || null }),
    onSuccess: async (r) => {
      if (r.checkout_url) {
        try {
          await WebBrowser.openBrowserAsync(r.checkout_url);
        } catch {
          Linking.openURL(r.checkout_url);
        }
      } else {
        Alert.alert("Done", r.free ? "You're following for free." : "You're subscribed.");
        router.back();
      }
    },
    onError: (e: Error) => Alert.alert("Couldn't start checkout", e.message || "Try again"),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: `Support @${handle}` }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 60 }}>
        <View
          style={{
            padding: 12,
            borderRadius: 12,
            backgroundColor: "rgba(16,185,129,0.12)",
            flexDirection: "row",
            alignItems: "center",
            gap: 8,
          }}
        >
          <Text style={{ color: "#047857", fontWeight: "700", fontSize: 12 }}>
            ✓ 100% to creator · 1INME takes 0%
          </Text>
        </View>

        {mine.data?.is_current && (
          <View style={[styles.activeBanner, { borderColor: colors.border }]}>
            <Text style={{ color: colors.text, fontWeight: "700" }}>
              You're a {mine.data.tier?.name ?? "subscriber"} ✓
            </Text>
            <Pressable onPress={() => router.push({ pathname: "/monetization/manage", params: { handle } })}>
              <Text style={{ color: colors.primary, fontSize: 13, marginTop: 4 }}>
                Manage subscription →
              </Text>
            </Pressable>
          </View>
        )}

        {/* Cycle toggle */}
        <View style={[styles.cycleWrap, { backgroundColor: colors.card }]}>
          {(["monthly", "yearly"] as const).map((c) => (
            <Pressable
              key={c}
              onPress={() => setCycle(c)}
              style={[
                styles.cycleBtn,
                cycle === c && { backgroundColor: colors.primary },
              ]}
            >
              <Text
                style={{
                  color: cycle === c ? "#fff" : colors.text,
                  fontWeight: "700",
                  fontSize: 13,
                }}
              >
                {c === "monthly" ? "Monthly" : "Yearly"}
              </Text>
            </Pressable>
          ))}
        </View>

        {tiersQ.isLoading && <ActivityIndicator />}
        {tiersQ.error && (
          <Text style={{ color: colors.destructive }}>Couldn't load tiers.</Text>
        )}

        {tiers.map((t) => (
          <TierCard
            key={t.id}
            tier={t}
            cycle={cycle}
            onSubscribe={() => subscribe.mutate(t.id)}
            loading={subscribe.isPending}
            colors={colors}
          />
        ))}

        {tiers.length > 0 && (
          <View>
            <Text style={{ color: colors.mutedForeground, fontSize: 12, marginBottom: 4 }}>
              Promo code (optional)
            </Text>
            <TextInput
              value={promo}
              onChangeText={setPromo}
              autoCapitalize="characters"
              placeholder="WELCOME50"
              placeholderTextColor={colors.mutedForeground}
              style={{
                borderWidth: 1,
                borderColor: colors.border,
                borderRadius: 10,
                paddingHorizontal: 12,
                paddingVertical: 10,
                color: colors.text,
                backgroundColor: colors.card,
                fontFamily: "monospace",
              }}
            />
          </View>
        )}
      </ScrollView>
    </View>
  );
}

function TierCard({
  tier,
  cycle,
  onSubscribe,
  loading,
  colors,
}: {
  tier: SubscriptionTier;
  cycle: "monthly" | "yearly";
  onSubscribe: () => void;
  loading: boolean;
  colors: ReturnType<typeof useColors>;
}) {
  const cents =
    cycle === "yearly"
      ? tier.price_yearly_cents ?? tier.price_monthly_cents * 12
      : tier.price_monthly_cents;
  const dollars = (cents / 100).toFixed(cents % 100 ? 2 : 0);
  const tint = tierTint(tier.color);

  return (
    <View style={[styles.tierCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 6, marginBottom: 6 }}>
        {tier.badge ? <Text style={{ fontSize: 16 }}>{tier.badge}</Text> : null}
        <Text style={{ color: colors.text, fontWeight: "800", fontSize: 17 }}>{tier.name}</Text>
      </View>
      {tier.is_free ? (
        <Text style={{ color: colors.text, fontSize: 22, fontWeight: "800" }}>Free</Text>
      ) : (
        <View style={{ flexDirection: "row", alignItems: "baseline", gap: 4 }}>
          <Text style={{ color: colors.text, fontSize: 28, fontWeight: "800" }}>${dollars}</Text>
          <Text style={{ color: colors.mutedForeground }}>
            / {cycle === "yearly" ? "year" : "month"}
          </Text>
        </View>
      )}
      {cycle === "yearly" && tier.yearly_discount_percent ? (
        <Text style={{ color: "#10b981", fontSize: 11, marginTop: 2 }}>
          Save {tier.yearly_discount_percent}% vs monthly
        </Text>
      ) : null}

      {(tier.perks ?? []).length > 0 && (
        <View style={{ marginTop: 10, gap: 6 }}>
          {tier.perks.slice(0, 6).map((p, i) => (
            <View key={i} style={{ flexDirection: "row", alignItems: "flex-start", gap: 6 }}>
              <Text style={{ color: tint, fontSize: 12, marginTop: 2 }}>✓</Text>
              <Text style={{ color: colors.text, flex: 1, fontSize: 13 }}>{p}</Text>
            </View>
          ))}
        </View>
      )}

      <View style={{ marginTop: 14 }}>
        <Button
          label={tier.is_free ? "Follow for free" : "Subscribe"}
          onPress={onSubscribe}
          loading={loading}
          variant="primary"
        />
      </View>
    </View>
  );
}

function tierTint(color: string | null): string {
  switch (color) {
    case "sky": return "#0ea5e9";
    case "emerald": return "#10b981";
    case "amber": return "#f59e0b";
    case "rose": return "#f43f5e";
    case "fuchsia": return "#d946ef";
    case "slate": return "#64748b";
    default: return "#8b5cf6";
  }
}

const styles = StyleSheet.create({
  tierCard: {
    padding: 16,
    borderWidth: 1,
    borderRadius: 16,
  },
  cycleWrap: {
    flexDirection: "row",
    padding: 4,
    borderRadius: 999,
    alignSelf: "center",
    gap: 4,
  },
  cycleBtn: {
    paddingHorizontal: 18,
    paddingVertical: 8,
    borderRadius: 999,
  },
  activeBanner: {
    padding: 14,
    borderWidth: 1,
    borderRadius: 12,
  },
});
