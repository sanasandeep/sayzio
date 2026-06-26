import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import {
  getSchemaHealth,
  repairSchema,
  type SchemaRepairResult,
} from "@/lib/api/schemaHealth";

// Super-admin parity for the web dashboard schema-health banner. Shows whether
// the live database has drifted from its recorded migrations (missing tables /
// columns) and offers the one-click "Repair columns" action — so an out-of-date
// schema can be spotted and fixed on the go. Column drift is added in place;
// whole-missing tables can't be recreated here and are surfaced as still
// needing `php artisan migrate --force`.

export default function SchemaHealthScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [repairResult, setRepairResult] = useState<SchemaRepairResult | null>(
    null,
  );
  const [repairError, setRepairError] = useState<string | null>(null);

  const query = useQuery({
    queryKey: ["schema-health"],
    queryFn: getSchemaHealth,
  });

  const repair = useMutation({
    mutationFn: repairSchema,
    onSuccess: (r) => {
      setRepairResult(r);
      setRepairError(null);
      qc.invalidateQueries({ queryKey: ["schema-health"] });
    },
    onError: (e: any) => {
      setRepairResult(null);
      setRepairError(e?.message ?? "Couldn't repair the schema.");
    },
  });

  const data = query.data;

  // Only column drift can be auto-repaired in place; whole-missing tables need
  // a real migration, so the action is hidden when nothing is column-fixable.
  const columnDrift = (data?.missing ?? []).filter((m) => !m.table_missing);
  const wholeMissing = (data?.missing ?? []).filter((m) => m.table_missing);
  const canRepair = columnDrift.length > 0 && !repair.isPending;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Schema health", headerBackTitle: "Back" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 48 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You need admin access to view schema health."
                : "Couldn't load schema health."}
            </Text>
          </View>
        ) : data ? (
          <>
            {/* Status badge */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.cardHead}>
                <Text style={[styles.cardTitle, { color: colors.foreground }]}>Database status</Text>
                {!data.available ? (
                  <View style={[styles.badge, { backgroundColor: colors.mutedForeground + "22" }]}>
                    <Text style={[styles.badgeText, { color: colors.mutedForeground }]}>Unavailable</Text>
                  </View>
                ) : data.healthy ? (
                  <View style={[styles.badge, { backgroundColor: colors.success + "22" }]}>
                    <Text style={[styles.badgeText, { color: colors.success }]}>In sync</Text>
                  </View>
                ) : (
                  <View style={[styles.badge, { backgroundColor: colors.warning + "22" }]}>
                    <Text style={[styles.badgeText, { color: colors.warning }]}>Out of date</Text>
                  </View>
                )}
              </View>

              {!data.available ? (
                <Text style={[styles.note, { color: colors.mutedForeground }]}>
                  {data.error
                    ? data.error
                    : "Couldn't reach the database to check the schema. Try again shortly."}
                </Text>
              ) : data.healthy ? (
                <Text style={[styles.note, { color: colors.mutedForeground }]}>
                  All {data.scanned} expected table(s) and columns are present. The
                  live database matches its recorded migrations.
                </Text>
              ) : (
                <Text style={[styles.note, { color: colors.mutedForeground }]}>
                  {data.missing_count} expected table(s) have drift — present in the
                  migrations but missing from the live database. Scanned {data.scanned} table(s).
                </Text>
              )}
            </View>

            {/* Drift report */}
            {data.available && !data.healthy ? (
              <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <Text style={[styles.cardTitle, { color: colors.foreground }]}>What's missing</Text>

                {columnDrift.length > 0 ? (
                  <View style={{ gap: 6 }}>
                    <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
                      Missing columns (repairable)
                    </Text>
                    {columnDrift.map((m) => (
                      <View key={m.table} style={styles.driftRow}>
                        <Feather name="columns" size={14} color={colors.primary} />
                        <Text style={{ color: colors.foreground, flex: 1 }}>
                          <Text style={styles.mono}>{m.table}</Text>
                          {" — "}
                          {m.columns.join(", ")}
                        </Text>
                      </View>
                    ))}
                  </View>
                ) : null}

                {wholeMissing.length > 0 ? (
                  <View style={{ gap: 6, marginTop: columnDrift.length > 0 ? 10 : 0 }}>
                    <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
                      Whole tables missing (needs migrate)
                    </Text>
                    {wholeMissing.map((m) => (
                      <View key={m.table} style={styles.driftRow}>
                        <Feather name="alert-triangle" size={14} color={colors.warning} />
                        <Text style={{ color: colors.foreground, flex: 1 }}>
                          <Text style={styles.mono}>{m.table}</Text>
                          {" — entire table missing"}
                        </Text>
                      </View>
                    ))}
                  </View>
                ) : null}

                <Button
                  label={
                    columnDrift.length > 0
                      ? "Repair columns"
                      : "Nothing to repair in place"
                  }
                  onPress={() => repair.mutate()}
                  loading={repair.isPending}
                  disabled={!canRepair}
                />

                {wholeMissing.length > 0 ? (
                  <Text style={[styles.note, { color: colors.mutedForeground }]}>
                    Whole-missing tables can't be recreated here — run
                    {" "}
                    <Text style={styles.mono}>php artisan migrate --force</Text> on the server.
                  </Text>
                ) : null}
              </View>
            ) : null}

            {/* Repair outcome */}
            {repairResult ? (
              <View
                style={[
                  styles.resultBox,
                  {
                    backgroundColor: repairResult.healthy ? colors.success + "15" : colors.warning + "15",
                    borderColor: repairResult.healthy ? colors.success : colors.warning,
                  },
                ]}
              >
                <Feather
                  name={repairResult.healthy ? "check-circle" : "info"}
                  size={16}
                  color={repairResult.healthy ? colors.success : colors.warning}
                />
                <View style={{ flex: 1, gap: 4 }}>
                  <Text style={{ color: colors.foreground }}>
                    {repairResult.added_columns_count > 0
                      ? `Added ${repairResult.added_columns_count} column(s) across ${repairResult.added_tables_count} table(s).`
                      : "No columns needed adding."}
                  </Text>
                  {repairResult.unrepairable_count > 0 ? (
                    <Text style={{ color: colors.foreground }}>
                      {repairResult.unrepairable_count} whole table(s) still missing — run{" "}
                      <Text style={styles.mono}>php artisan migrate --force</Text>:{" "}
                      {repairResult.unrepairable.join(", ")}
                    </Text>
                  ) : null}
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    {repairResult.healthy
                      ? "The schema is now in sync."
                      : `${repairResult.still_missing} item(s) still need attention.`}
                  </Text>
                </View>
              </View>
            ) : null}

            {repairError ? (
              <View
                style={[
                  styles.resultBox,
                  { backgroundColor: colors.destructive + "15", borderColor: colors.destructive },
                ]}
              >
                <Feather name="alert-circle" size={16} color={colors.destructive} />
                <Text style={{ color: colors.foreground, flex: 1 }}>{repairError}</Text>
              </View>
            ) : null}
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 8 },
  cardHead: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  cardTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15 },
  note: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium", lineHeight: 17 },
  badge: { paddingHorizontal: 10, paddingVertical: 3, borderRadius: 999 },
  badgeText: { fontSize: 11, fontFamily: "SpaceGrotesk_700Bold" },
  fieldLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
  driftRow: { flexDirection: "row", alignItems: "flex-start", gap: 8 },
  mono: { fontFamily: "SpaceGrotesk_600SemiBold" },
  resultBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 12,
    borderWidth: 1,
    borderRadius: 10,
  },
});
