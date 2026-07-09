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
  type WalletTransaction,
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
  const [transactions, setTransactions] = useState<WalletTransaction[]>([]);

  const load = useCallback(async () => {
    try {
      // Buying coins lives on its own /coin-packages screen now, so the
      // wallet view focuses purely on balance + history.
      const [bal, tx] = await Promise.all([
        walletApi.balance(),
        walletApi.transactions({ limit: 15 }),
      ]);
      setBalance(bal);
      setTransactions(tx.items);
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
              <Feather name="alert-triangle" size={12} /> Low balance — top up to keep using coin add-ons.
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

        <View>
          <Text style={[styles.section, { color: colors.foreground }]}>Recent transactions</Text>
          {transactions.length === 0 ? (
            <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
              No transactions yet.
            </Text>
          ) : (
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
              {transactions.map((tx, i) => (
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
                    <Text style={[styles.txType, { color: colors.foreground }]}>{tx.type}</Text>
                    {tx.reason ? (
                      <Text
                        style={[styles.subtle, { color: colors.mutedForeground }]}
                        numberOfLines={1}
                      >
                        {tx.reason}
                      </Text>
                    ) : null}
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
          )}
        </View>
      </ScrollView>
    </View>
  );
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
  txList: { borderWidth: 1, paddingVertical: 4 },
  txRow: { flexDirection: "row", alignItems: "center", padding: 14, gap: 10 },
  txType: { fontSize: 13, fontWeight: "600", textTransform: "capitalize" },
  txDelta: { fontSize: 14, fontWeight: "700" },
});
