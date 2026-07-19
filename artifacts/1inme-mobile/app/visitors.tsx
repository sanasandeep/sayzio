import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useCallback, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { StatTile } from "@/components/StatTile";
import { VisitorTrendChart } from "@/components/VisitorTrendChart";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import {
  type VisitorPeriod,
  getAccountVisitors,
} from "@/lib/api/visitors";

// Mobile parity for the web account-wide Visitors page (Task #3812/#3816):
// total visitors + new/returning split, a native new-vs-returning daily
// trend, a visitors-by-link-type breakdown and a source breakdown, all
// filterable by link type and date range. Charts render natively via SVG —
// no dependency on the web Chart.js bundle.

const PERIODS: { key: VisitorPeriod; label: string }[] = [
  { key: "today", label: "Today" },
  { key: "7d", label: "7D" },
  { key: "30d", label: "30D" },
  { key: "90d", label: "90D" },
  { key: "year", label: "1Y" },
  { key: "all", label: "All" },
];

export default function VisitorsScreen() {
  const colors = useColors();
  const [period, setPeriod] = useState<VisitorPeriod>("30d");
  const [type, setType] = useState<string>("all");

  const q = useQuery({
    queryKey: ["account-visitors", period, type],
    queryFn: () => getAccountVisitors({ period, type }),
  });

  const onRefresh = useCallback(() => q.refetch(), [q]);

  const data = q.data;
  const typeChips: { type: string; label: string }[] = [
    { type: "all", label: "All types" },
    ...(data?.available_types ?? []),
  ];

  const maxType = Math.max(1, ...(data?.type_breakdown ?? []).map((r) => r.n));
  const maxSrc = Math.max(1, ...(data?.source_breakdown ?? []).map((r) => r.n));

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Visitors" }} />
      <ScrollView
        contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 40 }}
        refreshControl={
          <RefreshControl
            refreshing={q.isRefetching}
            onRefresh={onRefresh}
            tintColor={colors.primary}
            colors={[colors.primary]}
          />
        }
      >
        <View
          style={[
            styles.rangeBar,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          {PERIODS.map((p) => {
            const active = p.key === period;
            return (
              <Pressable
                key={p.key}
                onPress={() => setPeriod(p.key)}
                style={[
                  styles.rangePill,
                  active && { backgroundColor: colors.primary },
                ]}
              >
                <Text
                  style={[
                    styles.rangePillText,
                    {
                      color: active
                        ? colors.primaryForeground
                        : colors.mutedForeground,
                    },
                  ]}
                >
                  {p.label}
                </Text>
              </Pressable>
            );
          })}
        </View>

        {typeChips.length > 1 ? (
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.typeRow}
          >
            {typeChips.map((c) => {
              const active = c.type === type;
              return (
                <Pressable
                  key={c.type}
                  onPress={() => setType(c.type)}
                  style={[
                    styles.typeChip,
                    {
                      backgroundColor: active ? colors.primary : colors.card,
                      borderColor: active ? colors.primary : colors.border,
                    },
                  ]}
                >
                  <Text
                    style={{
                      color: active
                        ? colors.primaryForeground
                        : colors.foreground,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                      fontSize: 12,
                    }}
                  >
                    {c.label}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>
        ) : null}

        {q.isLoading ? (
          <View style={{ paddingVertical: 60, alignItems: "center" }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : q.isError ? (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Text style={{ color: colors.foreground }}>
              Couldn't load your visitors right now.
            </Text>
            <Text
              style={{
                color: colors.mutedForeground,
                fontSize: 12,
                marginTop: 4,
              }}
            >
              Pull down to refresh, or open the full dashboard in your browser.
            </Text>
          </View>
        ) : data && !data.has_links ? (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Text
              style={{
                color: colors.foreground,
                fontFamily: "SpaceGrotesk_700Bold",
              }}
            >
              No links yet
            </Text>
            <Text
              style={{
                color: colors.mutedForeground,
                fontSize: 12,
                marginTop: 4,
              }}
            >
              Create a link and share it; visitor insights will show up here.
            </Text>
          </View>
        ) : data ? (
          <>
            <Text style={[styles.range, { color: colors.mutedForeground }]}>
              {data.range.from} → {data.range.to}
            </Text>

            <View style={styles.tileRow}>
              <StatTile
                label="Visitors"
                value={data.total_visitors.toLocaleString()}
                icon="users"
              />
              <StatTile
                label="New"
                value={data.new_count.toLocaleString()}
                icon="user-plus"
              />
              <StatTile
                label="Returning"
                value={data.returning_count.toLocaleString()}
                icon="repeat"
              />
            </View>

            <Section title="Visitor trend" colors={colors}>
              <VisitorTrendChart data={data.daily_series} />
            </Section>

            <Section title="Visitors by link type" colors={colors}>
              {data.type_breakdown.length === 0 ? (
                <Text style={{ color: colors.mutedForeground }}>
                  No visitors in this window.
                </Text>
              ) : (
                <View style={{ gap: 6 }}>
                  {data.type_breakdown.map((r) => (
                    <BarRow
                      key={r.type}
                      label={r.label}
                      value={r.n}
                      max={maxType}
                      colors={colors}
                    />
                  ))}
                </View>
              )}
            </Section>

            <Section title="Visitors by source" colors={colors}>
              {data.source_breakdown.length === 0 ? (
                <Text style={{ color: colors.mutedForeground }}>
                  No source data yet.
                </Text>
              ) : (
                <View style={{ gap: 6 }}>
                  {data.source_breakdown.map((r) => (
                    <BarRow
                      key={r.src}
                      label={sourceLabel(r.src)}
                      value={r.n}
                      max={maxSrc}
                      colors={colors}
                    />
                  ))}
                </View>
              )}
            </Section>

            <Button
              label="Open full dashboard"
              variant="outline"
              onPress={() =>
                Linking.openURL(
                  `${getBaseUrl().replace(/\/api\/?$/, "")}/user/visitors?period=${period}${
                    type !== "all" ? `&type=${encodeURIComponent(type)}` : ""
                  }`,
                )
              }
            />
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

function sourceLabel(src: string): string {
  if (src === "web") return "Web";
  if (src === "ar") return "AR card";
  if (src === "mobile_app") return "Mobile app";
  return src;
}

function Section({
  title,
  colors,
  children,
}: {
  title: string;
  colors: ReturnType<typeof useColors>;
  children: React.ReactNode;
}) {
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
      <View style={styles.sectionHead}>
        <Feather name="bar-chart-2" size={14} color={colors.primary} />
        <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
          {title}
        </Text>
      </View>
      {children}
    </View>
  );
}

function BarRow({
  label,
  value,
  max,
  colors,
}: {
  label: string;
  value: number;
  max: number;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={styles.barRow}>
      <Text
        style={[styles.barLabel, { color: colors.foreground }]}
        numberOfLines={1}
      >
        {label}
      </Text>
      <View
        style={[styles.barTrack, { backgroundColor: colors.border + "80" }]}
      >
        <View
          style={{
            height: 8,
            width: `${(value / max) * 100}%`,
            backgroundColor: colors.primary,
            borderRadius: 4,
          }}
        />
      </View>
      <Text style={[styles.barValue, { color: colors.foreground }]}>
        {value.toLocaleString()}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  rangeBar: {
    flexDirection: "row",
    padding: 4,
    borderWidth: 1,
    borderRadius: 12,
    gap: 4,
  },
  rangePill: { flex: 1, paddingVertical: 8, borderRadius: 8, alignItems: "center" },
  rangePillText: {
    fontSize: 12,
    fontFamily: "SpaceGrotesk_700Bold",
    letterSpacing: 0.4,
  },
  typeRow: { gap: 8, paddingVertical: 2 },
  typeChip: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 999,
    borderWidth: 1,
  },
  range: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium" },
  tileRow: { flexDirection: "row", gap: 10 },
  section: { borderWidth: 1, padding: 16, gap: 12 },
  sectionHead: { flexDirection: "row", alignItems: "center", gap: 6 },
  sectionTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  barRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  barLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12, flex: 1.4 },
  barTrack: { flex: 2, height: 8, borderRadius: 4 },
  barValue: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 12,
    minWidth: 44,
    textAlign: "right",
  },
  card: { padding: 16, borderWidth: 1, borderRadius: 12 },
});
