import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { StatTile } from "@/components/StatTile";
import { useColors } from "@/hooks/useColors";
import { getAnalytics, getNfcCount } from "@/lib/api/analytics";

export default function LinkAnalyticsScreen() {
  const colors = useColors();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const a = useQuery({
    queryKey: ["analytics", id],
    queryFn: () => getAnalytics(id),
    enabled: Number.isFinite(id),
  });
  const n = useQuery({
    queryKey: ["nfc-count", id],
    queryFn: () => getNfcCount(id),
    enabled: Number.isFinite(id),
  });

  if (a.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (a.error || !a.data) {
    return (
      <View style={styles.center}>
        <Text style={{ color: colors.destructive }}>
          Couldn't load analytics.
        </Text>
      </View>
    );
  }

  const data = a.data;
  const maxDay = Math.max(1, ...data.by_day.map((d) => d.clicks));

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Analytics" }} />
      <ScrollView contentContainerStyle={styles.body}>
        <View style={styles.tileRow}>
          <StatTile
            label="Total clicks"
            value={data.total_clicks}
            icon="bar-chart-2"
          />
          <StatTile
            label="Unique"
            value={data.unique_clicks}
            icon="users"
          />
        </View>
        <View style={styles.tileRow}>
          <StatTile
            label="NFC writes"
            value={n.data ?? 0}
            icon="wifi"
            hint="Tag programmings"
          />
        </View>

        <Section title="Clicks by day">
          {data.by_day.length === 0 ? (
            <Text style={{ color: colors.mutedForeground }}>
              No clicks in the selected window.
            </Text>
          ) : (
            <View style={{ gap: 6 }}>
              {data.by_day.map((d) => (
                <View key={d.day} style={styles.barRow}>
                  <Text
                    style={[styles.barLabel, { color: colors.mutedForeground }]}
                  >
                    {d.day}
                  </Text>
                  <View style={styles.barTrack}>
                    <View
                      style={{
                        height: 8,
                        width: `${(d.clicks / maxDay) * 100}%`,
                        backgroundColor: colors.primary,
                        borderRadius: 4,
                      }}
                    />
                  </View>
                  <Text style={[styles.barValue, { color: colors.foreground }]}>
                    {d.clicks}
                  </Text>
                </View>
              ))}
            </View>
          )}
        </Section>

        <Section title="Top countries">
          <Breakdown
            rows={data.by_country.map((r) => ({
              label: r.country || "Unknown",
              clicks: r.clicks,
            }))}
          />
        </Section>

        <Section title="Top referrers">
          <Breakdown
            rows={data.by_referrer.map((r) => ({
              label: r.referrer_host || "Direct",
              clicks: r.clicks,
            }))}
          />
        </Section>

        <Section title="Devices">
          <Breakdown
            rows={data.by_device.map((r) => ({
              label: r.device_type || "Unknown",
              clicks: r.clicks,
            }))}
          />
        </Section>
      </ScrollView>
    </View>
  );
}

function Section({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  const colors = useColors();
  return (
    <View
      style={[
        styles.section,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
        {title}
      </Text>
      {children}
    </View>
  );
}

function Breakdown({ rows }: { rows: { label: string; clicks: number }[] }) {
  const colors = useColors();
  if (rows.length === 0) {
    return <Text style={{ color: colors.mutedForeground }}>No data yet.</Text>;
  }
  const max = Math.max(1, ...rows.map((r) => r.clicks));
  return (
    <View style={{ gap: 6 }}>
      {rows.slice(0, 8).map((r, i) => (
        <View key={`${r.label}-${i}`} style={styles.barRow}>
          <Text
            style={[styles.barLabel, { color: colors.foreground, flex: 1.4 }]}
            numberOfLines={1}
          >
            {r.label}
          </Text>
          <View style={styles.barTrack}>
            <View
              style={{
                height: 8,
                width: `${(r.clicks / max) * 100}%`,
                backgroundColor: colors.primary,
                borderRadius: 4,
              }}
            />
          </View>
          <Text style={[styles.barValue, { color: colors.foreground }]}>
            {r.clicks}
          </Text>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 16, paddingBottom: 40 },
  tileRow: { flexDirection: "row", gap: 12 },
  section: { borderWidth: 1, padding: 16, gap: 12 },
  sectionTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  barRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  barLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    flex: 1,
  },
  barTrack: { flex: 2, height: 8, borderRadius: 4, backgroundColor: "rgba(0,0,0,0.06)" },
  barValue: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 12,
    minWidth: 36,
    textAlign: "right",
  },
});
