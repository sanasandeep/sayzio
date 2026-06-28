import { useQuery } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
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
import { getLedger } from "@/lib/api/accounting";

type RangeKey = "ytd" | "this_month" | "last_30";

function rangeFor(key: RangeKey): { from: string; to: string } {
  const now = new Date();
  const to = now.toISOString().slice(0, 10);
  if (key === "this_month") {
    const from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    return { from, to };
  }
  if (key === "last_30") {
    const d = new Date(now);
    d.setDate(d.getDate() - 30);
    return { from: d.toISOString().slice(0, 10), to };
  }
  const from = new Date(now.getFullYear(), 0, 1).toISOString().slice(0, 10);
  return { from, to };
}

const RANGES: { key: RangeKey; label: string }[] = [
  { key: "ytd", label: "Year to date" },
  { key: "this_month", label: "This month" },
  { key: "last_30", label: "Last 30 days" },
];

export default function LedgerScreen() {
  const colors = useColors();
  const [range, setRange] = useState<RangeKey>("ytd");

  const q = useQuery({
    queryKey: ["billing-ledger", range],
    queryFn: () => getLedger(rangeFor(range)),
  });

  const money = (minor: number, currency: string) =>
    `${currency} ${((minor ?? 0) / 100).toFixed(2)}`;

  const report = q.data;
  const cur = report?.currency ?? "USD";

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Ledger report" }} />
      <ScrollView
        contentContainerStyle={{ padding: 20, gap: 16 }}
        refreshControl={
          <RefreshControl refreshing={q.isFetching && !q.isLoading} onRefresh={() => q.refetch()} tintColor={colors.primary} />
        }
      >
        <View style={{ flexDirection: "row", gap: 8 }}>
          {RANGES.map((r) => (
            <Pressable
              key={r.key}
              onPress={() => setRange(r.key)}
              style={[
                styles.chip,
                {
                  borderColor: range === r.key ? colors.primary : colors.border,
                  backgroundColor: range === r.key ? colors.primary + "1c" : colors.card,
                },
              ]}
            >
              <Text style={{ color: range === r.key ? colors.primary : colors.mutedForeground, fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 }}>
                {r.label}
              </Text>
            </Pressable>
          ))}
        </View>

        {q.isLoading ? (
          <View style={{ paddingVertical: 40, alignItems: "center" }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : q.isError ? (
          <EmptyState icon="alert-circle" title="Couldn't load report" body={(q.error as { message?: string })?.message ?? "Try again."} />
        ) : report ? (
          <>
            <View style={{ gap: 10 }}>
              {[
                ["Income", report.totals.income_minor, colors.success],
                ["Refunded", -report.totals.refunded_minor, colors.destructive],
                ["Tax collected", report.totals.tax_collected_minor, colors.foreground],
                ["Expenses", -report.totals.expense_minor, colors.destructive],
                ["Profit", report.totals.profit_minor, colors.primary],
              ].map(([label, val, tint]) => (
                <View key={label as string} style={[styles.statRow, { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius }]}>
                  <Text style={[styles.statLabel, { color: colors.mutedForeground }]}>{label as string}</Text>
                  <Text style={[styles.statValue, { color: tint as string }]}>{money(val as number, cur)}</Text>
                </View>
              ))}
            </View>

            {report.by_month.length > 0 && (
              <View style={{ gap: 8 }}>
                <Text style={[styles.section, { color: colors.foreground }]}>By month</Text>
                {report.by_month.map((m) => (
                  <View key={m.month} style={[styles.monthRow, { borderColor: colors.border }]}>
                    <Text style={[styles.monthLabel, { color: colors.foreground }]}>{m.month}</Text>
                    <Text style={[styles.sub, { color: colors.success }]}>{money(m.income_minor, cur)}</Text>
                    <Text style={[styles.sub, { color: colors.destructive }]}>-{money(m.expense_minor, cur)}</Text>
                    <Text style={[styles.sub, { color: colors.primary }]}>{money(m.profit_minor, cur)}</Text>
                  </View>
                ))}
              </View>
            )}

            <Text style={[styles.footnote, { color: colors.mutedForeground }]}>
              {report.totals.invoice_count} invoices · {report.totals.expense_count} expenses
            </Text>
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  chip: { paddingHorizontal: 12, paddingVertical: 8, borderWidth: 1, borderRadius: 999 },
  statRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", padding: 16, borderWidth: 1 },
  statLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  statValue: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 17 },
  section: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15, marginTop: 8 },
  monthRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", paddingVertical: 8, borderBottomWidth: 1, gap: 8 },
  monthLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13, flex: 1 },
  sub: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  footnote: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12, textAlign: "center", marginTop: 8 },
});
