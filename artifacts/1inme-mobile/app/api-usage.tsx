import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { router } from "expo-router";
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { getApiUsage, type ApiUsage } from "@/lib/api/usage";

function Row({
  label,
  value,
  color,
  muted,
}: {
  label: string;
  value: string;
  color: string;
  muted: string;
}) {
  return (
    <View style={styles.row}>
      <Text style={[styles.rowLabel, { color: muted }]}>{label}</Text>
      <Text style={[styles.rowValue, { color }]}>{value}</Text>
    </View>
  );
}

export default function ApiUsageScreen() {
  const colors = useColors();

  const q = useQuery({
    queryKey: ["api-usage"],
    queryFn: getApiUsage,
  });

  const usage = q.data;

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={{ padding: 16, gap: 16 }}
      refreshControl={
        <RefreshControl
          refreshing={q.isRefetching}
          onRefresh={() => q.refetch()}
          tintColor={colors.primary}
        />
      }
    >
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError || !usage ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load usage"
          body="Pull to refresh to try again."
        />
      ) : !usage.api_access ? (
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Feather name="zap" size={24} color={colors.primary} />
          <Text style={[styles.cardTitle, { color: colors.foreground }]}>
            API access is not on your plan
          </Text>
          <Text style={[styles.cardBody, { color: colors.mutedForeground }]}>
            Upgrade to unlock developer API keys and start making programmatic
            calls. You'll see your usage and limits here.
          </Text>
          <Pressable
            onPress={() => router.push("/upgrade")}
            style={[styles.cta, { backgroundColor: colors.primary }]}
          >
            <Text style={[styles.ctaText, { color: colors.primaryForeground }]}>
              View plans
            </Text>
          </Pressable>
        </View>
      ) : (
        <UsageBody usage={usage} colors={colors} />
      )}
    </ScrollView>
  );
}

function UsageBody({
  usage,
  colors,
}: {
  usage: ApiUsage;
  colors: ReturnType<typeof useColors>;
}) {
  const pct = usage.unlimited ? 0 : Math.min(100, Math.max(0, usage.percent_used));
  const overLimit = !usage.unlimited && usage.calls_used >= usage.allowance;
  const barColor = overLimit
    ? colors.destructive
    : pct >= 80
      ? colors.accent
      : colors.primary;

  const allowanceLabel = usage.unlimited
    ? "Unlimited"
    : usage.allowance.toLocaleString();

  return (
    <>
      {/* Headline meter */}
      <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
        <Text style={[styles.eyebrow, { color: colors.mutedForeground }]}>
          THIS PERIOD · {usage.period}
        </Text>
        <View style={styles.meterHead}>
          <Text style={[styles.bigNumber, { color: colors.foreground }]}>
            {usage.calls_used.toLocaleString()}
          </Text>
          <Text style={[styles.bigUnit, { color: colors.mutedForeground }]}>
            / {allowanceLabel} calls
          </Text>
        </View>

        {!usage.unlimited ? (
          <>
            <View style={[styles.track, { backgroundColor: colors.border }]}>
              <View
                style={[
                  styles.fill,
                  { width: `${pct}%`, backgroundColor: barColor },
                ]}
              />
            </View>
            <Text style={[styles.meterCaption, { color: colors.mutedForeground }]}>
              {overLimit
                ? "You've used your full monthly allowance."
                : `${pct}% of your monthly allowance used` +
                  (usage.remaining != null
                    ? ` · ${usage.remaining.toLocaleString()} left`
                    : "")}
            </Text>
          </>
        ) : (
          <Text style={[styles.meterCaption, { color: colors.mutedForeground }]}>
            Your plan includes unlimited API calls.
          </Text>
        )}
      </View>

      {/* Overage / coins */}
      {!usage.unlimited ? (
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Text style={[styles.cardTitle, { color: colors.foreground }]}>
            Overage
          </Text>
          <Row
            label="Overage calls this period"
            value={usage.overage_calls.toLocaleString()}
            color={colors.foreground}
            muted={colors.mutedForeground}
          />
          <Row
            label="Coins spent on overage"
            value={usage.coins_spent.toLocaleString()}
            color={colors.foreground}
            muted={colors.mutedForeground}
          />
          <Row
            label="Coin balance"
            value={usage.coin_balance.toLocaleString()}
            color={colors.foreground}
            muted={colors.mutedForeground}
          />
          <Text style={[styles.cardBody, { color: colors.mutedForeground }]}>
            {usage.wallet_enabled
              ? `Beyond your allowance, 1 coin covers ${usage.calls_per_coin.toLocaleString()} extra calls.`
              : "Overage billing from coins is currently disabled, so calls beyond your allowance are rejected."}
          </Text>
        </View>
      ) : null}

      {/* Rate limit */}
      <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
        <Text style={[styles.cardTitle, { color: colors.foreground }]}>
          Limits
        </Text>
        <Row
          label="Rate limit"
          value={
            usage.rate_per_min > 0
              ? `${usage.rate_per_min.toLocaleString()} / min`
              : "—"
          }
          color={colors.foreground}
          muted={colors.mutedForeground}
        />
        <Row
          label="Monthly allowance"
          value={allowanceLabel}
          color={colors.foreground}
          muted={colors.mutedForeground}
        />
      </View>

      <Text style={[styles.footnote, { color: colors.mutedForeground }]}>
        We'll send a notification when you reach 80% and 100% of your allowance.
        Manage how you're notified in notification settings.
      </Text>
    </>
  );
}

const styles = StyleSheet.create({
  center: { paddingVertical: 64, alignItems: "center", justifyContent: "center" },
  card: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
    gap: 10,
  },
  eyebrow: {
    fontSize: 11,
    letterSpacing: 1,
    fontFamily: "SpaceGrotesk_600SemiBold",
  },
  meterHead: { flexDirection: "row", alignItems: "flex-end", gap: 6 },
  bigNumber: { fontSize: 34, fontFamily: "SpaceGrotesk_700Bold" },
  bigUnit: { fontSize: 14, fontFamily: "SpaceGrotesk_500Medium", paddingBottom: 6 },
  track: { height: 10, borderRadius: 999, overflow: "hidden" },
  fill: { height: "100%", borderRadius: 999 },
  meterCaption: { fontSize: 13, fontFamily: "SpaceGrotesk_400Regular" },
  cardTitle: { fontSize: 16, fontFamily: "SpaceGrotesk_600SemiBold" },
  cardBody: { fontSize: 13, fontFamily: "SpaceGrotesk_400Regular", lineHeight: 19 },
  row: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 4,
  },
  rowLabel: { fontSize: 13, fontFamily: "SpaceGrotesk_400Regular" },
  rowValue: { fontSize: 15, fontFamily: "SpaceGrotesk_600SemiBold" },
  cta: {
    marginTop: 4,
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: "center",
  },
  ctaText: { fontSize: 15, fontFamily: "SpaceGrotesk_600SemiBold" },
  footnote: {
    fontSize: 12,
    textAlign: "center",
    lineHeight: 18,
    paddingHorizontal: 8,
  },
});
