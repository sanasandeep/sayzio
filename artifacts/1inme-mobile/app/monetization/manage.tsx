import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { cancelMySubscription, getMySubscription } from "@/lib/api/monetization";
import { showAlert } from "@/lib/webAlert";

/**
 * Mobile parity for /@handle/manage-subscription. Lets a fan see
 * what they're paying for, when it renews, and cancel at period end.
 */
export default function ManageSubscriptionScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const { handle = "" } = useLocalSearchParams<{ handle?: string }>();

  const q = useQuery({
    queryKey: ["my-subscription", handle],
    queryFn: () => getMySubscription(handle),
    enabled: !!handle,
  });

  const cancel = useMutation({
    mutationFn: () => cancelMySubscription(handle),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["my-subscription", handle] }),
    onError: (e: Error) => showAlert("Couldn't cancel", e.message || "Try again"),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Manage subscription" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
        {q.isLoading && <ActivityIndicator />}
        {q.data ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Text style={{ color: colors.mutedForeground, fontSize: 11, textTransform: "uppercase" }}>Tier</Text>
            <Text style={{ color: colors.text, fontWeight: "800", fontSize: 18, marginTop: 2 }}>
              {q.data.tier?.badge ? q.data.tier.badge + " " : ""}
              {q.data.tier?.name ?? "Subscriber"}
            </Text>

            <View style={{ flexDirection: "row", marginTop: 14, gap: 16, flexWrap: "wrap" }}>
              <Field label="Cycle" value={q.data.billing_cycle} colors={colors} />
              <Field
                label="Price"
                value={`$${(q.data.price_cents / 100).toFixed(2)} ${q.data.currency.toUpperCase()}`}
                colors={colors}
              />
              <Field
                label={q.data.cancel_at_period_end ? "Ends" : "Renews"}
                value={
                  q.data.current_period_end
                    ? new Date(q.data.current_period_end).toLocaleDateString()
                    : "—"
                }
                colors={colors}
              />
              <Field label="Status" value={q.data.status_label} colors={colors} />
            </View>

            {q.data.cancel_at_period_end ? (
              <View
                style={{
                  marginTop: 14,
                  padding: 10,
                  borderRadius: 10,
                  backgroundColor: "rgba(245,158,11,0.12)",
                }}
              >
                <Text style={{ color: "#b45309", fontSize: 12 }}>
                  Your subscription will end on{" "}
                  {q.data.current_period_end
                    ? new Date(q.data.current_period_end).toLocaleDateString()
                    : "—"}
                  . You'll keep access until then.
                </Text>
              </View>
            ) : (
              <View style={{ marginTop: 14 }}>
                <Button
                  label="Cancel at period end"
                  variant="ghost"
                  onPress={() =>
                    showAlert(
                      "Cancel subscription?",
                      "You'll keep access until the period ends.",
                      [
                        { text: "Keep it", style: "cancel" },
                        { text: "Cancel", style: "destructive", onPress: () => cancel.mutate() },
                      ],
                    )
                  }
                  loading={cancel.isPending}
                />
              </View>
            )}

            <View style={{ marginTop: 10 }}>
              <Button
                label="Switch tier"
                variant="ghost"
                onPress={() => router.replace({ pathname: "/monetization/subscribe", params: { handle } })}
              />
            </View>
          </View>
        ) : !q.isLoading ? (
          <Text style={{ color: colors.mutedForeground }}>You don't have an active subscription.</Text>
        ) : null}
      </ScrollView>
    </View>
  );
}

function Field({
  label,
  value,
  colors,
}: {
  label: string;
  value: string;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={{ minWidth: 120 }}>
      <Text style={{ color: colors.mutedForeground, fontSize: 11, textTransform: "uppercase" }}>
        {label}
      </Text>
      <Text style={{ color: colors.text, fontWeight: "600", marginTop: 2 }}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    padding: 16,
    borderRadius: 14,
    borderWidth: 1,
  },
});
