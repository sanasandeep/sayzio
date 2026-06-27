import { useQuery } from "@tanstack/react-query";
import { Stack, router } from "expo-router";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import type { ApiError } from "@/lib/api";
import {
  getInsuranceDashboard,
  type InsuranceDashboardItem,
  type InsuranceState,
} from "@/lib/api/insurance";

function stateColor(
  state: InsuranceState,
  colors: ReturnType<typeof useColors>,
): string {
  if (state === "primary") return colors.success;
  if (state === "failover") return colors.warning;
  return colors.destructive;
}

function timeAgo(iso: string | null): string {
  if (!iso) return "Never";
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return "Never";
  const diff = Date.now() - then;
  const m = Math.round(diff / 60000);
  if (m < 1) return "just now";
  if (m < 60) return `${m}m ago`;
  const h = Math.round(m / 60);
  if (h < 24) return `${h}h ago`;
  const d = Math.round(h / 24);
  return `${d}d ago`;
}

function Row({ item }: { item: InsuranceDashboardItem }) {
  const colors = useColors();
  return (
    <Pressable
      onPress={() =>
        router.push(`/links/${item.id}/settings/insurance` as any)
      }
      style={[styles.row, { borderColor: colors.border, backgroundColor: colors.card }]}
    >
      <View style={{ flex: 1 }}>
        <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 }} numberOfLines={1}>
          {item.title || `/${item.alias}`}
        </Text>
        <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }} numberOfLines={1}>
          /{item.alias}
          {item.long_url ? ` → ${item.long_url}` : ""}
        </Text>
        <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 4 }}>
          {item.uptime_ratio != null
            ? `${(item.uptime_ratio * 100).toFixed(2)}% uptime (${item.uptime_samples})`
            : "No probes yet"}
          {" · "}
          checked {timeAgo(item.last_checked_at)}
        </Text>
      </View>
      <View
        style={[
          styles.badge,
          { backgroundColor: stateColor(item.state, colors) + "22" },
        ]}
      >
        <Text style={{ color: stateColor(item.state, colors), fontSize: 11, fontFamily: "SpaceGrotesk_600SemiBold" }}>
          {item.state.charAt(0).toUpperCase() + item.state.slice(1)}
        </Text>
      </View>
    </Pressable>
  );
}

export default function InsuranceDashboardScreen() {
  const colors = useColors();
  const q = useQuery({
    queryKey: ["insurance-dashboard"],
    queryFn: () => getInsuranceDashboard(),
  });

  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Link Health" }} />
      <ScrollView contentContainerStyle={styles.body}>
        <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
          All links with Link Insurance enabled. Failed-over links are listed
          first.
        </Text>

        {q.isLoading ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : q.isError ? (
          <View style={styles.center}>
            <Text style={{ color: colors.mutedForeground }}>
              {(q.error as unknown as ApiError)?.message ?? "Could not load Link Health."}
            </Text>
          </View>
        ) : !q.data || q.data.items.length === 0 ? (
          <View style={[styles.empty, { borderColor: colors.border, backgroundColor: colors.card }]}>
            <Text style={{ color: colors.mutedForeground, textAlign: "center" }}>
              No links have Link Insurance enabled yet. Open a link's settings to
              add backup destinations.
            </Text>
          </View>
        ) : (
          q.data.items.map((item) => <Row key={item.id} item={item} />)
        )}
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  body: { padding: 20, paddingBottom: 40 },
  center: { padding: 40, alignItems: "center", justifyContent: "center" },
  blurb: {
    fontSize: 13,
    fontFamily: "SpaceGrotesk_400Regular",
    lineHeight: 19,
    marginBottom: 16,
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginBottom: 10,
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
  },
  empty: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 24,
  },
});
