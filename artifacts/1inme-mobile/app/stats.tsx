import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, router } from "expo-router";
import { useCallback, useState } from "react";
import { ActivityIndicator, Linking, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from "react-native";
import Svg, { Polyline } from "react-native-svg";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { apiFetch, getBaseUrl } from "@/lib/api";
import { getProfile } from "@/lib/api/profile";

// Mobile parity for the unified Stats home (web route: /user/stats).
// The web view ships the chart-heavy "how am I doing this week?"
// dashboard; the phone surfaces the same KPI tiles plus daily growth
// sparklines, and defers full chart drill-downs / CSV export to the
// browser via a "Open full dashboard" button.

type TrendPoint = { date: string; value: number };

type StatsResponse = {
  range: { from: string; to: string };
  audience: { followers: number; followers_delta: number; subscribers: number };
  content: { posts: number; views: number; comments: number };
  engagement: { reactions: number; tips: number };
  earnings: { tips_total: number; subs_total: number; payouts_total: number; currency: string };
  trends?: { followers: TrendPoint[]; posts: TrendPoint[] };
  capabilities?: { analytics_export?: boolean };
};

const RANGES = [
  { key: "7d", label: "7D" },
  { key: "30d", label: "30D" },
  { key: "90d", label: "90D" },
  { key: "1y", label: "1Y" },
] as const;

type RangeKey = (typeof RANGES)[number]["key"];

async function fetchStats(range: RangeKey): Promise<StatsResponse> {
  const res = await apiFetch<{ data: StatsResponse }>(`/stats?range=${range}`);
  return res.data;
}

// Lightweight zero-dependency sparkline (uses the SVG lib already
// bundled for QR/link art). Falls back to a flat baseline when the
// series is empty or all-zero so the card never collapses.
function Sparkline({ data, color, width = 280, height = 48 }: { data: number[]; color: string; width?: number; height?: number }) {
  const pad = 4;
  const max = Math.max(1, ...data);
  const n = data.length;
  const points =
    n <= 1
      ? `${pad},${height - pad} ${width - pad},${height - pad}`
      : data
          .map((v, i) => {
            const x = pad + (i * (width - pad * 2)) / (n - 1);
            const y = height - pad - (v / max) * (height - pad * 2);
            return `${x.toFixed(1)},${y.toFixed(1)}`;
          })
          .join(" ");
  return (
    <Svg width="100%" height={height} viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none">
      <Polyline points={points} fill="none" stroke={color} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
    </Svg>
  );
}

export default function StatsScreen() {
  const colors = useColors();
  const [range, setRange] = useState<RangeKey>("30d");
  const q = useQuery({ queryKey: ["creator-stats", range], queryFn: () => fetchStats(range) });
  const profileQ = useQuery({ queryKey: ["profile"], queryFn: getProfile });

  const onRefresh = useCallback(() => {
    q.refetch();
    profileQ.refetch();
  }, [q, profileQ]);
  // Mirror the web `analytics_export` ("Stats CSV export") paid gate.
  // Prefer the capability the stats payload now carries; fall back to
  // the profile (and finally default-true, matching the server helper's
  // fallback) so the control only hides once we know the plan lacks it.
  const canExport =
    q.data?.capabilities?.analytics_export ?? profileQ.data?.capabilities?.analytics_export ?? true;

  const tile = (icon: any, label: string, value: string, sub?: string) => (
    <View style={[styles.tile, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}>
      <View style={[styles.iconWrap, { backgroundColor: colors.primary + "15" }]}>
        <Feather name={icon} size={16} color={colors.primary} />
      </View>
      <Text style={[styles.tileLabel, { color: colors.mutedForeground }]}>{label}</Text>
      <Text style={[styles.tileValue, { color: colors.foreground }]}>{value}</Text>
      {sub ? <Text style={[styles.tileSub, { color: colors.mutedForeground }]}>{sub}</Text> : null}
    </View>
  );

  const fmt = (n: number | undefined) =>
    typeof n === "number" ? n.toLocaleString() : "—";
  const money = (n: number | undefined, cur = "USD") =>
    typeof n === "number" ? `${cur} ${n.toFixed(2)}` : "—";

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Stats", headerBackTitle: "Back" }} />
      <ScrollView
        contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 40 }}
        refreshControl={
          <RefreshControl
            refreshing={q.isRefetching}
            onRefresh={onRefresh}
            tintColor={colors.primary}
            colors={[colors.primary]}
          />
        }
      >
        <View style={[styles.rangeBar, { backgroundColor: colors.card, borderColor: colors.border }]}>
          {RANGES.map((r) => {
            const active = r.key === range;
            return (
              <Pressable
                key={r.key}
                onPress={() => setRange(r.key)}
                style={[styles.rangePill, active && { backgroundColor: colors.primary }]}
              >
                <Text
                  style={[
                    styles.rangePillText,
                    { color: active ? colors.primaryForeground : colors.mutedForeground },
                  ]}
                >
                  {r.label}
                </Text>
              </Pressable>
            );
          })}
        </View>

        {q.isLoading ? (
          <View style={{ paddingVertical: 60, alignItems: "center" }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : q.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Text style={{ color: colors.foreground }}>Couldn't load your stats right now.</Text>
            <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 4 }}>
              Pull down to refresh, or open the full dashboard in your browser.
            </Text>
          </View>
        ) : (
          <>
            <Text style={[styles.range, { color: colors.mutedForeground }]}>
              {q.data?.range?.from} → {q.data?.range?.to}
            </Text>

            <Text style={[styles.section, { color: colors.foreground }]}>Audience</Text>
            <View style={styles.row}>
              {tile("users", "Followers", fmt(q.data?.audience?.followers),
                q.data?.audience?.followers_delta != null ? `${q.data.audience.followers_delta >= 0 ? "+" : ""}${q.data.audience.followers_delta} this week` : undefined)}
              {tile("star", "Subscribers", fmt(q.data?.audience?.subscribers))}
            </View>

            {q.data?.trends ? (
              <>
                <Text style={[styles.section, { color: colors.foreground }]}>Trends</Text>
                <View style={[styles.chartCard, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}>
                  <View style={styles.chartHead}>
                    <Feather name="trending-up" size={14} color={colors.primary} />
                    <Text style={[styles.chartLabel, { color: colors.mutedForeground }]}>New followers</Text>
                  </View>
                  <Sparkline data={(q.data.trends.followers ?? []).map((p) => p.value)} color={colors.primary} />
                </View>
                <View style={[styles.chartCard, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}>
                  <View style={styles.chartHead}>
                    <Feather name="file-text" size={14} color={colors.primary} />
                    <Text style={[styles.chartLabel, { color: colors.mutedForeground }]}>Posts published</Text>
                  </View>
                  <Sparkline data={(q.data.trends.posts ?? []).map((p) => p.value)} color={colors.primary} />
                </View>
              </>
            ) : null}

            <Text style={[styles.section, { color: colors.foreground }]}>Content & engagement</Text>
            <View style={styles.row}>
              {tile("file-text", "Posts", fmt(q.data?.content?.posts))}
              {tile("eye", "Views", fmt(q.data?.content?.views))}
            </View>
            <View style={styles.row}>
              {tile("message-circle", "Comments", fmt(q.data?.content?.comments))}
              {tile("heart", "Reactions", fmt(q.data?.engagement?.reactions))}
            </View>

            <Text style={[styles.section, { color: colors.foreground }]}>Earnings</Text>
            <View style={styles.row}>
              {tile("gift", "Tips", money(q.data?.earnings?.tips_total, q.data?.earnings?.currency))}
              {tile("credit-card", "Subscriptions", money(q.data?.earnings?.subs_total, q.data?.earnings?.currency))}
            </View>
            {tile("download", "Payouts", money(q.data?.earnings?.payouts_total, q.data?.earnings?.currency), "Lifetime, after fees")}

            <Button
              label="Open full dashboard"
              onPress={() => Linking.openURL(`${getBaseUrl().replace(/\/api\/?$/, "")}/user/stats?range=${range}`)}
            />
            {canExport ? (
              <Button
                label="Export CSV"
                variant="outline"
                onPress={() => Linking.openURL(`${getBaseUrl().replace(/\/api\/?$/, "")}/user/stats/export?range=${range}`)}
              />
            ) : (
              <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_700Bold" }}>
                  Stats CSV export is a paid feature
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 4, marginBottom: 12 }}>
                  Upgrade your plan to download CSV exports.
                </Text>
                <Button label="Upgrade plan" onPress={() => router.push("/upgrade")} />
              </View>
            )}
          </>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  rangeBar: { flexDirection: "row", padding: 4, borderWidth: 1, borderRadius: 12, gap: 4 },
  rangePill: { flex: 1, paddingVertical: 8, borderRadius: 8, alignItems: "center" },
  rangePillText: { fontSize: 12, fontFamily: "SpaceGrotesk_700Bold", letterSpacing: 0.4 },
  range: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium" },
  section: { fontSize: 13, fontFamily: "SpaceGrotesk_700Bold", textTransform: "uppercase", letterSpacing: 0.6, marginTop: 8 },
  row: { flexDirection: "row", gap: 10 },
  tile: { flex: 1, padding: 14, borderWidth: 1, gap: 4 },
  iconWrap: { width: 28, height: 28, borderRadius: 8, alignItems: "center", justifyContent: "center" },
  tileLabel: { fontSize: 11, fontFamily: "SpaceGrotesk_500Medium", textTransform: "uppercase", letterSpacing: 0.4 },
  tileValue: { fontSize: 20, fontFamily: "SpaceGrotesk_700Bold" },
  tileSub: { fontSize: 11, fontFamily: "SpaceGrotesk_400Regular" },
  card: { padding: 16, borderWidth: 1, borderRadius: 12 },
  chartCard: { padding: 14, borderWidth: 1, gap: 8 },
  chartHead: { flexDirection: "row", alignItems: "center", gap: 6 },
  chartLabel: { fontSize: 11, fontFamily: "SpaceGrotesk_500Medium", textTransform: "uppercase", letterSpacing: 0.4 },
});
