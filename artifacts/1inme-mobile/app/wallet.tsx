import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import {
  type WalletBalance,
  type WalletLedgerDay,
  type WalletLedgerSummary,
  wallet as walletApi,
} from "@/lib/api";
import { showAlert } from "@/lib/webAlert";

export default function WalletScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [balance, setBalance] = useState<WalletBalance | null>(null);
  const [days, setDays] = useState<WalletLedgerDay[]>([]);
  const [summary, setSummary] = useState<WalletLedgerSummary | null>(null);

  const load = useCallback(async () => {
    try {
      // Buying coins lives on its own /coin-packages screen now, so the
      // wallet view focuses purely on balance + the day-by-day ledger.
      const [bal, tx] = await Promise.all([
        walletApi.balance(),
        walletApi.transactions({ limit: 100 }),
      ]);
      setBalance(bal);
      setDays(tx.days ?? []);
      setSummary(tx.summary ?? null);
    } catch (e: unknown) {
      const err = e as { status?: number; message?: string } | undefined;
      if (err?.status === 404) {
        showAlert("Wallet unavailable", "The wallet feature is currently disabled.");
        router.back();
      } else {
        showAlert("Couldn't load wallet", err?.message ?? "Try again in a moment.");
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [router]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  const lowBalance =
    balance && balance.low_balance_threshold > 0 && balance.balance < balance.low_balance_threshold;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Wallet", headerShown: true }} />
      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top,
          paddingHorizontal: 20,
          paddingBottom: 40,
          gap: 20,
        }}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              void load();
            }}
          />
        }
      >
        <View
          style={[
            styles.balanceCard,
            { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          <Text style={[styles.label, { color: colors.mutedForeground }]}>Coin balance</Text>
          <Text style={[styles.balance, { color: colors.primary }]}>
            {(balance?.balance ?? 0).toLocaleString()} 🪙
          </Text>
          {balance && balance.rate_coins_per_unit > 0 && (
            <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
              ≈ {balance.currency}{" "}
              {(balance.balance / balance.rate_coins_per_unit).toFixed(2)} (
              {balance.rate_coins_per_unit} coins per 1 {balance.currency})
            </Text>
          )}
          {lowBalance && (
            <Text style={[styles.warn, { color: colors.warning }]}>
              <Feather name="alert-triangle" size={12} /> Low balance: top up to keep using coin add-ons.
            </Text>
          )}
        </View>

        <Pressable
          onPress={() => router.push("/coin-packages" as never)}
          style={({ pressed }) => [
            styles.packageCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.primary,
              borderRadius: colors.radius,
              opacity: pressed ? 0.7 : 1,
            },
          ]}
        >
          <View style={{ flex: 1 }}>
            <Text style={[styles.pkgName, { color: colors.foreground }]}>Top up coins</Text>
            <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
              Browse coin packages and pick a gateway.
            </Text>
          </View>
          <Feather name="chevron-right" size={18} color={colors.primary} />
        </Pressable>

        {summary && (
          <View style={styles.tilesRow}>
            <View
              style={[
                styles.tile,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <Text style={[styles.tileLabel, { color: colors.mutedForeground }]}>Purchased</Text>
              <Text style={[styles.tileValue, { color: colors.success }]}>
                +{summary.coins_in.toLocaleString()}
              </Text>
            </View>
            <View
              style={[
                styles.tile,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <Text style={[styles.tileLabel, { color: colors.mutedForeground }]}>Spent</Text>
              <Text style={[styles.tileValue, { color: colors.destructive }]}>
                −{summary.coins_out.toLocaleString()}
              </Text>
            </View>
            <View
              style={[
                styles.tile,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <Text style={[styles.tileLabel, { color: colors.mutedForeground }]}>Net</Text>
              <Text
                style={[
                  styles.tileValue,
                  { color: summary.net >= 0 ? colors.success : colors.destructive },
                ]}
              >
                {summary.net >= 0 ? "+" : ""}
                {summary.net.toLocaleString()}
              </Text>
            </View>
          </View>
        )}

        <View>
          <Text style={[styles.section, { color: colors.foreground }]}>Coin ledger</Text>
          {days.length === 0 ? (
            <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
              No transactions yet.
            </Text>
          ) : (
            <View style={{ gap: 14 }}>
              {days.map((day) => (
                <View key={day.date}>
                  <View style={styles.dayHeader}>
                    <Text style={[styles.dayTitle, { color: colors.foreground }]}>
                      {formatDay(day.date)}
                    </Text>
                    <Text
                      style={[
                        styles.dayNet,
                        { color: day.net >= 0 ? colors.success : colors.destructive },
                      ]}
                    >
                      {day.net >= 0 ? "+" : ""}
                      {day.net.toLocaleString()}
                    </Text>
                  </View>
                  <Text style={[styles.subtle, { color: colors.mutedForeground, marginBottom: 6 }]}>
                    In +{day.coins_in.toLocaleString()} · Out −{day.coins_out.toLocaleString()}
                  </Text>
                  <View
                    style={[
                      styles.txList,
                      {
                        backgroundColor: colors.card,
                        borderColor: colors.border,
                        borderRadius: colors.radius,
                      },
                    ]}
                  >
                    {day.items.map((tx, i) => (
                      <View
                        key={tx.id}
                        style={[
                          styles.txRow,
                          {
                            borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                            borderTopColor: colors.border,
                          },
                        ]}
                      >
                        <View style={{ flex: 1 }}>
                          <Text style={[styles.txType, { color: colors.foreground }]}>
                            {tx.reason ?? tx.type}
                          </Text>
                          <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
                            {formatTime(tx.created_at)} · {tx.type}
                          </Text>
                        </View>
                        <View style={{ alignItems: "flex-end" }}>
                          <Text
                            style={[
                              styles.txDelta,
                              { color: tx.delta_coins >= 0 ? colors.success : colors.destructive },
                            ]}
                          >
                            {tx.delta_coins >= 0 ? "+" : ""}
                            {tx.delta_coins.toLocaleString()}
                          </Text>
                          <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
                            bal {tx.balance_after.toLocaleString()}
                          </Text>
                        </View>
                      </View>
                    ))}
                  </View>
                </View>
              ))}
            </View>
          )}
        </View>
      </ScrollView>
    </View>
  );
}

function formatDay(iso: string): string {
  const d = new Date(`${iso}T00:00:00`);
  if (Number.isNaN(d.getTime())) return iso;
  const today = new Date();
  const sameDay =
    d.getFullYear() === today.getFullYear() &&
    d.getMonth() === today.getMonth() &&
    d.getDate() === today.getDate();
  const label = d.toLocaleDateString(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });
  return sameDay ? `Today · ${label}` : label;
}

function formatTime(iso: string | null): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" });
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  balanceCard: { padding: 20, borderWidth: 1, gap: 6 },
  label: { fontSize: 12, textTransform: "uppercase", letterSpacing: 1 },
  balance: { fontSize: 36, fontWeight: "700", marginTop: 4 },
  subtle: { fontSize: 12 },
  warn: { fontSize: 12, marginTop: 6 },
  section: { fontSize: 14, fontWeight: "600", marginBottom: 8 },
  packageCard: {
    flexDirection: "row",
    alignItems: "center",
    padding: 14,
    borderWidth: 1,
    gap: 12,
  },
  pkgName: { fontSize: 15, fontWeight: "600" },
  price: { fontSize: 16, fontWeight: "700" },
  tilesRow: { flexDirection: "row", gap: 10 },
  tile: { flex: 1, padding: 12, borderWidth: 1, gap: 2 },
  tileLabel: { fontSize: 10, textTransform: "uppercase", letterSpacing: 0.8 },
  tileValue: { fontSize: 16, fontWeight: "700" },
  dayHeader: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  dayTitle: { fontSize: 13, fontWeight: "700" },
  dayNet: { fontSize: 13, fontWeight: "700" },
  txList: { borderWidth: 1, paddingVertical: 4 },
  txRow: { flexDirection: "row", alignItems: "center", padding: 14, gap: 10 },
  txType: { fontSize: 13, fontWeight: "600", textTransform: "capitalize" },
  txDelta: { fontSize: 14, fontWeight: "700" },
});
