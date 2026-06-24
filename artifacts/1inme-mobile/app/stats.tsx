import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, router } from "expo-router";
import { ActivityIndicator, Linking, ScrollView, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { apiFetch, getBaseUrl } from "@/lib/api";
import { getProfile } from "@/lib/api/profile";

// Task #1211 — mobile parity stub for the unified Stats home (web
// route: /user/stats). The web view ships the chart-heavy "how am I
// doing this week?" dashboard; the phone surfaces the same KPI tiles
// and defers chart drill-downs / CSV export to the browser via a
// "Open full dashboard" button.

type StatsResponse = {
  range: { from: string; to: string };
  audience: { followers: number; followers_delta: number; subscribers: number };
  content: { posts: number; views: number; comments: number };
  engagement: { reactions: number; tips: number };
  earnings: { tips_total: number; subs_total: number; payouts_total: number; currency: string };
};

async function fetchStats(): Promise<StatsResponse> {
  const res = await apiFetch<{ data: StatsResponse }>("/stats");
  return res.data;
}

export default function StatsScreen() {
  const colors = useColors();
  const q = useQuery({ queryKey: ["creator-stats"], queryFn: fetchStats });
  const profileQ = useQuery({ queryKey: ["profile"], queryFn: getProfile });
  // Mirror the web `analytics_export` ("Stats CSV export") paid gate.
  // Default-true matches the server helper's fallback, so the control
  // only hides once we know the plan lacks the feature.
  const canExport = profileQ.data?.capabilities?.analytics_export ?? true;

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
      <ScrollView contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 40 }}>
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
              onPress={() => Linking.openURL(`${getBaseUrl().replace(/\/api\/?$/, "")}/user/stats`)}
            />
            {canExport ? (
              <Button
                label="Export CSV"
                variant="outline"
                onPress={() => Linking.openURL(`${getBaseUrl().replace(/\/api\/?$/, "")}/user/stats/export`)}
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
  range: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium" },
  section: { fontSize: 13, fontFamily: "SpaceGrotesk_700Bold", textTransform: "uppercase", letterSpacing: 0.6, marginTop: 8 },
  row: { flexDirection: "row", gap: 10 },
  tile: { flex: 1, padding: 14, borderWidth: 1, gap: 4 },
  iconWrap: { width: 28, height: 28, borderRadius: 8, alignItems: "center", justifyContent: "center" },
  tileLabel: { fontSize: 11, fontFamily: "SpaceGrotesk_500Medium", textTransform: "uppercase", letterSpacing: 0.4 },
  tileValue: { fontSize: 20, fontFamily: "SpaceGrotesk_700Bold" },
  tileSub: { fontSize: 11, fontFamily: "SpaceGrotesk_400Regular" },
  card: { padding: 16, borderWidth: 1, borderRadius: 12 },
});
