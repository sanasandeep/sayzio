import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "expo-router";
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AiDisabledNotice } from "@/components/AiDisabledNotice";
import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { errorStatus } from "@/lib/api";
import {
  marketingStrategist,
  type StrategySummary,
} from "@/lib/api/marketingStrategist";
import { showAlert } from "@/lib/webAlert";

const FEATURE_LABEL = "Performer Specialist";

export default function MarketingStrategistList() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const queryClient = useQueryClient();

  const q = useQuery({
    queryKey: ["marketing-strategist", "list"],
    queryFn: () => marketingStrategist.index(),
  });

  const del = useMutation({
    mutationFn: (id: number) => marketingStrategist.destroy(id),
    onSuccess: (_data, id) => {
      queryClient.invalidateQueries({
        queryKey: ["marketing-strategist", "list"],
      });
      queryClient.removeQueries({
        queryKey: ["marketing-strategist", "show", id],
      });
    },
    onError: () => {
      showAlert("Couldn't delete", "Please try again in a moment.");
    },
  });

  const confirmDelete = (strategy: StrategySummary) => {
    showAlert(
      "Delete strategy",
      `Delete "${strategy.title}"? This can't be undone.`,
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Delete",
          style: "destructive",
          onPress: () => del.mutate(strategy.id),
        },
      ],
    );
  };

  const refreshing = q.isFetching && !q.isLoading;

  // Engine off → server returns 200 with ai_enabled:false (no throw).
  if (q.data && q.data.ai_enabled === false) {
    return <AiDisabledNotice feature={FEATURE_LABEL} variant="engine" />;
  }

  // Plan-locked → server aborts 403 on every call.
  if (errorStatus(q.error) === 403) {
    return <AiDisabledNotice feature={FEATURE_LABEL} variant="plan" />;
  }

  const strategies = q.data?.strategies ?? [];
  const balance = q.data?.balance;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top + 8,
          paddingBottom: insets.bottom + 32,
          paddingHorizontal: 20,
          gap: 18,
        }}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => q.refetch()}
            tintColor={colors.primary}
          />
        }
      >
        <View style={{ gap: 6 }}>
          <Text style={[styles.eyebrow, { color: colors.mutedForeground }]}>
            AI Digital Performer Specialist
          </Text>
          <Text style={[styles.title, { color: colors.foreground }]}>
            Marketing strategies
          </Text>
          <Text style={[styles.subtitle, { color: colors.mutedForeground }]}>
            A growth plan built from your own links, audience and brand:
            organic plays, paid plays and one-tap actions.
          </Text>
        </View>

        <Button
          label="New strategy"
          onPress={() => router.push("/marketing-strategist/new" as never)}
        />

        {typeof balance === "number" ? (
          <View style={styles.balanceRow}>
            <Feather name="zap" size={13} color={colors.primary} />
            <Text style={[styles.balance, { color: colors.mutedForeground }]}>
              {balance.toLocaleString()} coins available
            </Text>
          </View>
        ) : null}

        {q.isLoading ? (
          <View style={{ paddingVertical: 40 }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : q.error ? (
          <Text style={{ color: colors.destructive }}>
            Couldn&apos;t load your strategies. Pull to retry.
          </Text>
        ) : strategies.length === 0 ? (
          <EmptyState
            icon="trending-up"
            title="No strategies yet"
            body="Generate your first marketing strategy to get a tailored organic + paid growth plan."
          />
        ) : (
          <View style={{ gap: 10 }}>
            {strategies.map((s) => (
              <StrategyRow
                key={s.id}
                strategy={s}
                onPress={() =>
                  router.push(`/marketing-strategist/${s.id}` as never)
                }
                onDelete={() => confirmDelete(s)}
              />
            ))}
          </View>
        )}
      </ScrollView>
    </View>
  );
}

function StrategyRow({
  strategy,
  onPress,
  onDelete,
}: {
  strategy: StrategySummary;
  onPress: () => void;
  onDelete: () => void;
}) {
  const colors = useColors();
  const created = strategy.created_at
    ? new Date(strategy.created_at).toLocaleDateString()
    : null;
  return (
    <Pressable
      onPress={onPress}
      onLongPress={onDelete}
      style={({ pressed }) => [
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : 1,
        },
      ]}
    >
      <View style={[styles.iconBadge, { backgroundColor: colors.primary + "1c" }]}>
        <Feather name="target" size={16} color={colors.primary} />
      </View>
      <View style={{ flex: 1, gap: 3 }}>
        <Text
          style={[styles.cardTitle, { color: colors.foreground }]}
          numberOfLines={1}
        >
          {strategy.title}
        </Text>
        {strategy.goal ? (
          <Text
            style={[styles.cardGoal, { color: colors.mutedForeground }]}
            numberOfLines={2}
          >
            {strategy.goal}
          </Text>
        ) : null}
        <View style={styles.metaRow}>
          {created ? (
            <Text style={[styles.meta, { color: colors.mutedForeground }]}>
              {created}
            </Text>
          ) : null}
          {strategy.credits_spent > 0 ? (
            <Text style={[styles.meta, { color: colors.mutedForeground }]}>
              · {strategy.credits_spent} coins
            </Text>
          ) : null}
        </View>
      </View>
      <Pressable
        onPress={onDelete}
        hitSlop={10}
        accessibilityLabel={`Delete ${strategy.title}`}
        style={({ pressed }) => [
          styles.deleteBtn,
          { opacity: pressed ? 0.6 : 1 },
        ]}
      >
        <Feather name="trash-2" size={18} color={colors.destructive} />
      </Pressable>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  eyebrow: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 26 },
  subtitle: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
  },
  balanceRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  balance: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  iconBadge: {
    width: 34,
    height: 34,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
  },
  cardTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  cardGoal: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 16,
  },
  metaRow: { flexDirection: "row", gap: 6, marginTop: 2 },
  meta: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  deleteBtn: {
    width: 34,
    height: 34,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
  },
});
