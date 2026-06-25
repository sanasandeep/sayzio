import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  getStatsStorage,
  updateStatsStorage,
  type StatsStorageStatus,
} from "@/lib/api/statsStorage";

// Mobile parity for the web admin "Analytics Storage" panel. Read the growth
// of the high-volume analytics tables, the retention window the nightly sweep
// applies, the last sweep outcome, and set/clear the hard cap + growth-alert
// threshold. Server-gated behind `settings.manage` (403 otherwise).

function fmt(n: number): string {
  return n.toLocaleString();
}

function retentionLabel(days: number | null): string {
  if (days === null) return "No automatic pruning";
  return `${days} ${days === 1 ? "day" : "days"}`;
}

function planLabel(days: number): string {
  if (days === -1) return "Unlimited (keep forever)";
  return `${days} ${days === 1 ? "day" : "days"}`;
}

export default function StatsStorageScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [hardMax, setHardMax] = useState("");
  const [threshold, setThreshold] = useState("");
  const [clearHardMax, setClearHardMax] = useState(false);
  const [clearThreshold, setClearThreshold] = useState(false);

  const query = useQuery({
    queryKey: ["admin-stats-storage"],
    queryFn: getStatsStorage,
  });

  const save = useMutation({
    mutationFn: () =>
      updateStatsStorage({
        hard_max_days:
          !clearHardMax && hardMax.trim() ? Number(hardMax.trim()) : undefined,
        clear_hard_max_days: clearHardMax || undefined,
        alert_row_threshold:
          !clearThreshold && threshold.trim()
            ? Number(threshold.trim())
            : undefined,
        clear_alert_row_threshold: clearThreshold || undefined,
      }),
    onSuccess: (data: StatsStorageStatus) => {
      qc.setQueryData(["admin-stats-storage"], data);
      setHardMax("");
      setThreshold("");
      setClearHardMax(false);
      setClearThreshold(false);
      Alert.alert("Saved", "Analytics storage settings updated.");
    },
    onError: (e: any) =>
      Alert.alert(
        "Couldn't save",
        e?.status === 403
          ? "You don't have permission to change these settings."
          : e?.message ?? "Try again.",
      ),
  });

  const data = query.data;

  const card = [styles.card, { backgroundColor: colors.card, borderColor: colors.border }];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: "Analytics storage", headerBackTitle: "Back" }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 64 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={card}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You don't have permission to view analytics storage."
                : "Couldn't load analytics storage."}
            </Text>
          </View>
        ) : data ? (
          <>
            {/* Unbounded-growth warning */}
            {data.growth_unbounded ? (
              <View
                style={[
                  styles.card,
                  { backgroundColor: "#f59e0b1a", borderColor: "#f59e0b59" },
                ]}
              >
                <View style={styles.head}>
                  <Feather name="alert-triangle" size={18} color="#f59e0b" />
                  <Text style={[styles.title, { color: "#f59e0b" }]}>
                    Growing unbounded
                  </Text>
                </View>
                <Text style={{ color: colors.foreground, marginTop: 8, fontSize: 13 }}>
                  A table has crossed {fmt(data.alert_threshold)} rows and nothing
                  will prune it — {data.reason}. Set a hard cap below to bound
                  storage.
                </Text>
              </View>
            ) : null}

            {/* Retention summary */}
            <View style={card}>
              <View style={styles.head}>
                <Feather name="clock" size={18} color={colors.primary} />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  Retention
                </Text>
              </View>
              <View style={{ marginTop: 10, gap: 8 }}>
                <Stat label="Effective window" value={retentionLabel(data.effective_days)} colors={colors} />
                <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: -4 }}>
                  {data.reason}
                </Text>
                <Stat label="Plan retention" value={planLabel(data.plan_retention)} colors={colors} />
                <Stat
                  label="Hard cap"
                  value={data.hard_max_days === null ? "Not set" : retentionLabel(data.hard_max_days)}
                  colors={colors}
                />
                <Stat label="Alert threshold" value={`${fmt(data.alert_threshold)} rows`} colors={colors} />
              </View>
            </View>

            {/* Table sizes */}
            <View style={card}>
              <View style={styles.head}>
                <Feather name="database" size={18} color={colors.primary} />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  Table sizes (estimated)
                </Text>
              </View>
              {data.tables.length === 0 ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13, marginTop: 8 }}>
                  No analytics tables found.
                </Text>
              ) : (
                data.tables.map((t, i) => (
                  <View
                    key={t.table}
                    style={[
                      styles.row,
                      {
                        borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                        borderTopColor: colors.border,
                      },
                    ]}
                  >
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text numberOfLines={1} style={{ color: colors.foreground, fontWeight: "600" }}>
                        {t.table}
                      </Text>
                      <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                        ~{fmt(t.estimated_rows)} rows
                      </Text>
                    </View>
                    <View
                      style={[
                        styles.badge,
                        {
                          backgroundColor: t.over_threshold ? "#f59e0b1a" : colors.primary + "14",
                        },
                      ]}
                    >
                      <Text
                        style={{
                          color: t.over_threshold ? "#f59e0b" : colors.primary,
                          fontSize: 11,
                          fontWeight: "600",
                        }}
                      >
                        {t.over_threshold ? "Over threshold" : "OK"}
                      </Text>
                    </View>
                  </View>
                ))
              )}
            </View>

            {/* Last sweep */}
            <View style={card}>
              <View style={styles.head}>
                <Feather name="trash-2" size={18} color={colors.primary} />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  Last cleanup sweep
                </Text>
              </View>
              {data.last_run === null ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13, marginTop: 8 }}>
                  No sweep has run yet. The cleanup runs daily.
                </Text>
              ) : (
                <View style={{ marginTop: 8, gap: 4 }}>
                  <Text style={{ color: colors.foreground, fontSize: 13 }}>
                    Outcome:{" "}
                    {data.last_run.action === "pruned"
                      ? "Pruned"
                      : data.last_run.action === "noop"
                        ? "No deletions"
                        : data.last_run.action ?? "unknown"}
                    {data.last_run.dry_run ? " (dry run)" : ""}
                  </Text>
                  {data.last_run.ran_at ? (
                    <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                      Ran {data.last_run.ran_at}
                    </Text>
                  ) : null}
                  {data.last_run.reason ? (
                    <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                      {data.last_run.reason}
                    </Text>
                  ) : null}
                </View>
              )}
            </View>

            {/* Settings */}
            <View style={card}>
              <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
                Storage limits
              </Text>
              <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 4 }}>
                Leave a field blank to keep its current value, or toggle "Clear"
                to remove it.
              </Text>

              <View style={{ gap: 14, marginTop: 12 }}>
                <View>
                  <TextField
                    label="Hard cap (days)"
                    placeholder={data.hard_max_days === null ? "not set" : String(data.hard_max_days)}
                    keyboardType="number-pad"
                    value={hardMax}
                    editable={!clearHardMax}
                    onChangeText={setHardMax}
                  />
                  {data.hard_max_days !== null ? (
                    <ClearToggle
                      label="Clear the hard cap"
                      value={clearHardMax}
                      onChange={setClearHardMax}
                      colors={colors}
                    />
                  ) : null}
                </View>

                <View>
                  <TextField
                    label="Growth alert threshold (rows)"
                    placeholder={fmt(data.alert_threshold)}
                    keyboardType="number-pad"
                    value={threshold}
                    editable={!clearThreshold}
                    onChangeText={setThreshold}
                  />
                  <ClearToggle
                    label={`Reset to default (${fmt(data.default_threshold)})`}
                    value={clearThreshold}
                    onChange={setClearThreshold}
                    colors={colors}
                  />
                </View>

                <Button
                  label="Save settings"
                  onPress={() => save.mutate()}
                  loading={save.isPending}
                />
              </View>
            </View>
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

function Stat({
  label,
  value,
  colors,
}: {
  label: string;
  value: string;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={styles.statRow}>
      <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>{label}</Text>
      <Text style={{ color: colors.foreground, fontSize: 13, fontWeight: "600" }}>
        {value}
      </Text>
    </View>
  );
}

function ClearToggle({
  label,
  value,
  onChange,
  colors,
}: {
  label: string;
  value: boolean;
  onChange: (v: boolean) => void;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={styles.toggleRow}>
      <Switch value={value} onValueChange={onChange} />
      <Text style={{ color: colors.mutedForeground, fontSize: 13, flex: 1 }}>
        {label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 16, padding: 16 },
  head: { flexDirection: "row", alignItems: "center", gap: 8 },
  title: { fontSize: 16, fontWeight: "700" },
  sectionTitle: { fontSize: 15, fontWeight: "700" },
  statRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 12,
  },
  badge: {
    paddingHorizontal: 9,
    paddingVertical: 4,
    borderRadius: 999,
  },
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginTop: 8,
  },
});
