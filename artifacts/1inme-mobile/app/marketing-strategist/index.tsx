import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useFocusEffect, useRouter } from "expo-router";
import { useCallback } from "react";
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { AiDisabledNotice } from "@/components/AiDisabledNotice";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  marketingStrategist,
  type MsSummary,
} from "@/lib/api/marketingStrategist";

/**
 * Marketing Strategist (mobile) — the list of saved strategies and the
 * entry point into the builder. Mirrors the web index: a balance-aware
 * header, a "New strategy" CTA, and a card per saved strategy showing its
 * goal summary and the data sources it grounded on.
 *
 * Gating mirrors Ask Coach: the index loader degrades gracefully — a 200
 * with `ai_enabled:false` renders the "AI is off" explainer, while a 403
 * (feature not on plan) renders the plan-locked explainer — so the user is
 * never bounced out with a raw error.
 */
export default function MarketingStrategistScreen() {
  const colors = useColors();
  const router = useRouter();

  const q = useQuery({
    queryKey: ["marketing-strategist", "list"],
    queryFn: marketingStrategist.index,
  });

  // Refetch whenever the screen regains focus so a strategy created in the
  // builder (or a suggestion applied in the detail view) shows up here.
  useFocusEffect(
    useCallback(() => {
      q.refetch();
    }, [q]),
  );

  const status = (q.error as { status?: number } | null)?.status;
  const disabled: "engine" | "plan" | null =
    q.data?.ai_enabled === false
      ? "engine"
      : status === 404
        ? "engine"
        : status === 403
          ? "plan"
          : null;

  const strategies = q.data?.strategies ?? [];
  const balance = q.data?.balance;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Marketing Strategist",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
          headerRight: () =>
            disabled ? null : (
              <Pressable
                onPress={() => router.push("/marketing-strategist/new" as never)}
                hitSlop={8}
                style={{ paddingRight: 12 }}
              >
                <Feather name="plus" size={22} color={colors.primary} />
              </Pressable>
            ),
        }}
      />

      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : disabled ? (
        <AiDisabledNotice feature="Marketing Strategist" variant={disabled} />
      ) : (
        <ScrollView
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        >
          <View style={styles.heroWrap}>
            <Text style={[styles.kicker, { color: colors.primary }]}>
              AI · DIGITAL PERFORMER
            </Text>
            <Text style={[styles.heroTitle, { color: colors.foreground }]}>
              Marketing Strategist
            </Text>
            <Text style={[styles.heroBlurb, { color: colors.mutedForeground }]}>
              Feed in your own Sayzio data and get an organic + paid plan built
              around real features — then refine and act on it with one tap.
            </Text>
            {typeof balance === "number" ? (
              <View
                style={[
                  styles.balancePill,
                  { backgroundColor: colors.primary + "1c" },
                ]}
              >
                <Feather name="circle" size={12} color={colors.primary} />
                <Text style={[styles.balanceText, { color: colors.primary }]}>
                  {balance.toLocaleString()} coins
                </Text>
              </View>
            ) : null}
          </View>

          {strategies.length === 0 ? (
            <EmptyState
              icon="target"
              title="No strategies yet"
              body="Choose which of your data to share, set a goal, and the strategist will draft a marketing plan you can refine and act on with one tap."
              action={
                <Pressable
                  onPress={() =>
                    router.push("/marketing-strategist/new" as never)
                  }
                  style={[styles.cta, { backgroundColor: colors.primary }]}
                >
                  <Feather name="zap" size={15} color="#fff" />
                  <Text style={styles.ctaText}>Build your first strategy</Text>
                </Pressable>
              }
            />
          ) : (
            <View style={{ gap: 12, marginTop: 8 }}>
              {strategies.map((s) => (
                <StrategyCard
                  key={s.id}
                  strategy={s}
                  colors={colors}
                  onPress={() =>
                    router.push(`/marketing-strategist/${s.id}` as never)
                  }
                />
              ))}
            </View>
          )}
        </ScrollView>
      )}
    </View>
  );
}

function StrategyCard({
  strategy,
  colors,
  onPress,
}: {
  strategy: MsSummary;
  colors: ReturnType<typeof useColors>;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.8 : 1,
        },
      ]}
    >
      <View style={styles.cardHead}>
        <Text
          style={[styles.cardTitle, { color: colors.foreground }]}
          numberOfLines={1}
        >
          {strategy.title}
        </Text>
        {strategy.credits_spent > 0 ? (
          <Text style={[styles.cardCoins, { color: colors.primary }]}>
            {strategy.credits_spent.toLocaleString()} coins
          </Text>
        ) : null}
      </View>
      {strategy.goal ? (
        <Text
          style={[styles.cardGoal, { color: colors.mutedForeground }]}
          numberOfLines={2}
        >
          {strategy.goal}
        </Text>
      ) : null}
      {strategy.sources.length > 0 ? (
        <View style={styles.tagRow}>
          {strategy.sources.map((src) => (
            <View
              key={src}
              style={[styles.tag, { backgroundColor: colors.muted }]}
            >
              <Text style={[styles.tagText, { color: colors.mutedForeground }]}>
                {sourceLabel(src)}
              </Text>
            </View>
          ))}
        </View>
      ) : null}
    </Pressable>
  );
}

/** Humanize a source key for the summary chips (matches the web labels). */
function sourceLabel(key: string): string {
  const map: Record<string, string> = {
    links: "Links & types",
    analytics: "Analytics",
    audience: "Followers & subscribers",
    pixels: "Tracking pixels",
    minds: "AI Minds",
    brand_kits: "Brand Kits",
    personas: "AI Personas",
    companions: "AI Companions",
  };
  return map[key] ?? key;
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  heroWrap: { marginBottom: 16, gap: 6 },
  kicker: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 1,
  },
  heroTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 24,
  },
  heroBlurb: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13.5,
    lineHeight: 20,
  },
  balancePill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    alignSelf: "flex-start",
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 999,
    marginTop: 4,
  },
  balanceText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
  },
  cta: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 16,
    paddingVertical: 11,
    borderRadius: 12,
  },
  ctaText: {
    color: "#fff",
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
  },
  card: {
    borderWidth: 1,
    padding: 16,
    gap: 8,
  },
  cardHead: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
  },
  cardTitle: {
    flex: 1,
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 16,
  },
  cardCoins: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
  },
  cardGoal: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
  },
  tagRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 6,
    marginTop: 2,
  },
  tag: {
    paddingHorizontal: 9,
    paddingVertical: 3,
    borderRadius: 999,
  },
  tagText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
  },
});
