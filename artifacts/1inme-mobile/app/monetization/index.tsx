import { Text, View } from "react-native";
import { useQuery } from "@tanstack/react-query";
import { useRouter } from "expo-router";
import { FeatureScreen } from "@/components/FeatureScreen";
import { Button } from "@/components/Button";
import {
  getCreatorEarnings,
  getOwnerTiers,
  getCreatorSubscribers,
} from "@/lib/api/monetization";
import { useColors } from "@/hooks/useColors";
export default function CreatorMonetizationScreen() {
  const c = useColors();
  const router = useRouter();
  const q = useQuery({
    queryKey: ["creator-monetization-dashboard"],
    queryFn: async () => {
      const [earnings, tiers, subscribers] = await Promise.all([
        getCreatorEarnings(),
        getOwnerTiers(),
        getCreatorSubscribers(),
      ]);
      return { earnings, tiers, subscribers };
    },
  });
  function money(cents: number, currency: string) {
    return `${(cents / 100).toFixed(2)} ${currency.toUpperCase()}`;
  }
  return (
    <FeatureScreen
      title="Creator monetization"
      loading={q.isLoading}
      error={q.error}
      retry={() => q.refetch()}
    >
      {q.data ? (
        <>
          <View
            style={{
              gap: 10,
              padding: 16,
              borderWidth: 1,
              borderColor: c.border,
              borderRadius: c.radius,
            }}
          >
            <Text style={{ color: c.foreground }}>
              This month:{" "}
              {money(
                q.data.earnings.this_month_cents,
                q.data.earnings.currency,
              )}
            </Text>
            <Text style={{ color: c.foreground }}>
              All time:{" "}
              {money(q.data.earnings.all_time_cents, q.data.earnings.currency)}
            </Text>
            <Text style={{ color: c.foreground }}>
              {q.data.subscribers.meta.total} subscribers
            </Text>
          </View>
          <Text style={{ color: c.foreground, fontWeight: "700" }}>
            Subscription tiers
          </Text>
          {!q.data.tiers.items.length ? (
            <Text style={{ color: c.mutedForeground }}>
              No subscription tiers configured.
            </Text>
          ) : (
            q.data.tiers.items.map((t) => (
              <View
                key={t.id}
                style={{
                  padding: 16,
                  gap: 8,
                  borderWidth: 1,
                  borderColor: c.border,
                  borderRadius: c.radius,
                }}
              >
                <Text style={{ color: c.foreground, fontWeight: "700" }}>
                  {t.name} · {t.is_active ? "Active" : "Disabled"}
                </Text>
                <Text style={{ color: c.mutedForeground }}>
                  {t.is_free
                    ? "Free"
                    : `${money(t.price_monthly_cents, t.currency)} / month`}
                </Text>
                {t.perks.map((perk, i) => (
                  <Text key={i} style={{ color: c.foreground }}>
                    • {perk}
                  </Text>
                ))}
              </View>
            ))
          )}
        </>
      ) : null}
      <Button
        label="Manage payout connections"
        onPress={() => router.push("/payouts")}
      />
      <Button
        label="My subscriptions"
        variant="secondary"
        onPress={() => router.push("/manage-subscription")}
      />
    </FeatureScreen>
  );
}
