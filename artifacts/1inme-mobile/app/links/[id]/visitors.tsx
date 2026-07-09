import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
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
import { NfcWriteTrigger } from "@/components/NfcWriteTrigger";
import { StatTile } from "@/components/StatTile";
import { VisitorTrendChart } from "@/components/VisitorTrendChart";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import {
  type VisitorPeriod,
  getLinkVisitors,
} from "@/lib/api/visitors";

// Mobile parity for the web per-link Visitor Insights page (Task #3816):
// totals + new/returning, a native daily trend, identified (logged-in)
// visitors, NFC write count + recent writes, and the AR business-card
// breakdown. Reached from the link detail screen's "Visitor Insights"
// action. Charts render natively (no web Chart.js dependency).

const PERIODS: { key: VisitorPeriod; label: string }[] = [
  { key: "today", label: "Today" },
  { key: "7d", label: "7D" },
  { key: "30d", label: "30D" },
  { key: "90d", label: "90D" },
  { key: "year", label: "1Y" },
  { key: "all", label: "All" },
];

export default function LinkVisitorsScreen() {
  const colors = useColors();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);
  const [period, setPeriod] = useState<VisitorPeriod>("30d");

  const q = useQuery({
    queryKey: ["link-visitors", id, period],
    queryFn: () => getLinkVisitors(id, { period }),
    enabled: Number.isFinite(id),
  });

  const onRefresh = useCallback(() => q.refetch(), [q]);
  const data = q.data;
  const maxSrc = Math.max(1, ...(data?.source_breakdown ?? []).map((r) => r.n));

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Visitor Insights" }} />
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

        {q.isLoading ? (
          <View style={{ paddingVertical: 60, alignItems: "center" }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : q.isError || !data ? (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Text style={{ color: colors.foreground }}>
              Couldn't load visitor insights.
            </Text>
          </View>
        ) : (
          <>
            <Text style={[styles.range, { color: colors.mutedForeground }]}>
              {data.link.title || `/${data.link.alias}`} · {data.range.from} →{" "}
              {data.range.to}
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

            {data.nfc_count > 0 ? (
              <Section title="NFC writes" colors={colors} icon="wifi">
                <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: 8 }}>
                  <Text style={{ color: colors.foreground, fontSize: 13, flex: 1 }}>
                    {data.nfc_count.toLocaleString()} tag
                    {data.nfc_count === 1 ? "" : "s"} programmed with this link.
                  </Text>
                  <NfcWriteTrigger
                    linkId={id}
                    url={`${getBaseUrl().replace(/\/api\/?$/, "")}/${data.link.alias}`}
                    variant="button"
                  />
                </View>
                {data.nfc_recent.length > 0 ? (
                  <View style={{ gap: 4, marginTop: 4 }}>
                    {data.nfc_recent.map((w) => (
                      <Text
                        key={w.id}
                        style={{
                          color: colors.mutedForeground,
                          fontSize: 12,
                          fontFamily: "SpaceGrotesk_500Medium",
                        }}
                      >
                        {w.created_at
                          ? new Date(w.created_at).toLocaleString()
                          : "—"}
                      </Text>
                    ))}
                  </View>
                ) : null}
              </Section>
            ) : null}

            {data.ar_sessions > 0 || data.ar_clicks > 0 ? (
              <Section title="AR business card" colors={colors} icon="camera">
                <View style={styles.tileRow}>
                  <StatTile
                    label="AR scans"
                    value={data.ar_sessions.toLocaleString()}
                    icon="camera"
                  />
                  <StatTile
                    label="AR taps"
                    value={data.ar_clicks.toLocaleString()}
                    icon="mouse-pointer"
                  />
                </View>
              </Section>
            ) : null}

            <Section title="Identified visitors" colors={colors} icon="user-check">
              {data.identified.length === 0 ? (
                <Text style={{ color: colors.mutedForeground }}>
                  No logged-in visitors in this window.
                </Text>
              ) : (
                <View style={{ gap: 8 }}>
                  {data.identified.map((v) => (
                    <View
                      key={v.id}
                      style={[
                        styles.visitorRow,
                        { borderColor: colors.border },
                      ]}
                    >
                      <View style={{ flex: 1, minWidth: 0 }}>
                        <Text
                          style={{
                            color: colors.foreground,
                            fontFamily: "SpaceGrotesk_600SemiBold",
                            fontSize: 13,
                          }}
                          numberOfLines={1}
                        >
                          {v.name || v.email || `User #${v.id}`}
                        </Text>
                        {v.email ? (
                          <Text
                            style={{
                              color: colors.mutedForeground,
                              fontSize: 11,
                              fontFamily: "SpaceGrotesk_500Medium",
                            }}
                            numberOfLines={1}
                          >
                            {v.email}
                          </Text>
                        ) : null}
                      </View>
                      <View style={{ alignItems: "flex-end", gap: 3 }}>
                        <Text
                          style={{
                            color: colors.foreground,
                            fontFamily: "SpaceGrotesk_700Bold",
                            fontSize: 13,
                          }}
                        >
                          {v.visit_count} visit{v.visit_count === 1 ? "" : "s"}
                        </Text>
                        <View
                          style={[
                            styles.badge,
                            {
                              backgroundColor: v.is_follower
                                ? colors.primary + "22"
                                : colors.border + "60",
                            },
                          ]}
                        >
                          <Text
                            style={{
                              color: v.is_follower
                                ? colors.primary
                                : colors.mutedForeground,
                              fontSize: 10,
                              fontFamily: "SpaceGrotesk_600SemiBold",
                            }}
                          >
                            {v.is_follower ? "Follower" : "Visitor"}
                          </Text>
                        </View>
                      </View>
                    </View>
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
                    <View key={r.src} style={styles.barRow}>
                      <Text
                        style={[styles.barLabel, { color: colors.foreground }]}
                        numberOfLines={1}
                      >
                        {sourceLabel(r.src)}
                      </Text>
                      <View
                        style={[
                          styles.barTrack,
                          { backgroundColor: colors.border + "80" },
                        ]}
                      >
                        <View
                          style={{
                            height: 8,
                            width: `${(r.n / maxSrc) * 100}%`,
                            backgroundColor: colors.primary,
                            borderRadius: 4,
                          }}
                        />
                      </View>
                      <Text
                        style={[styles.barValue, { color: colors.foreground }]}
                      >
                        {r.n.toLocaleString()}
                      </Text>
                    </View>
                  ))}
                </View>
              )}
            </Section>

            <Button
              label="Open full dashboard"
              variant="outline"
              onPress={() =>
                Linking.openURL(
                  `${getBaseUrl().replace(/\/api\/?$/, "")}/user/links/${id}/visitors?period=${period}`,
                )
              }
            />
          </>
        )}
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
  icon = "bar-chart-2",
  children,
}: {
  title: string;
  colors: ReturnType<typeof useColors>;
  icon?: keyof typeof Feather.glyphMap;
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
        <Feather name={icon} size={14} color={colors.primary} />
        <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
          {title}
        </Text>
      </View>
      {children}
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
  range: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium" },
  tileRow: { flexDirection: "row", gap: 10 },
  section: { borderWidth: 1, padding: 16, gap: 12 },
  sectionHead: { flexDirection: "row", alignItems: "center", gap: 6 },
  sectionTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  visitorRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 999,
  },
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
