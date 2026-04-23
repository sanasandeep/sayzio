import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import {
  type AiCreditBalance,
  type AiCreditPack,
  type AiCreditTransaction,
  aiCredits as aiCreditsApi,
  wallet as walletApi,
} from "@/lib/api";

/**
 * Server stores raw feature codes (e.g. `voice_stt`); friendly labels
 * make the transactions feed readable. Falls back to the raw code so
 * new features still render without an app update.
 */
function prettyFeature(code: string | null | undefined): string | null {
  if (!code) return null;
  const map: Record<string, string> = {
    voice_stt: "Voice transcription",
    voice_llm: "Voice thinking",
    voice_tts: "Voice speech",
  };
  return map[code] ?? code;
}

export default function AiCreditsScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [balance, setBalance] = useState<AiCreditBalance | null>(null);
  const [transactions, setTransactions] = useState<AiCreditTransaction[]>([]);
  const [packs, setPacks] = useState<AiCreditPack[]>([]);
  const [walletCoins, setWalletCoins] = useState<number>(0);
  const [buying, setBuying] = useState<string | null>(null);
  const [customCredits, setCustomCredits] = useState<string>("1000");
  const [packAttempt, setPackAttempt] = useState<string>(() =>
    `pk-${Math.random().toString(36).slice(2)}-${Date.now()}`,
  );
  const [customAttempt, setCustomAttempt] = useState<string>(() =>
    `cu-${Math.random().toString(36).slice(2)}-${Date.now()}`,
  );

  const load = useCallback(async () => {
    try {
      const [bal, tx, pk, wb] = await Promise.all([
        aiCreditsApi.balance(),
        aiCreditsApi.transactions(15),
        aiCreditsApi.packs(),
        walletApi.balance().catch(() => null),
      ]);
      setBalance(bal);
      setTransactions(tx.items);
      setPacks(pk.items);
      setWalletCoins(wb?.balance ?? 0);
    } catch (e: unknown) {
      const err = e as { status?: number; message?: string } | undefined;
      if (err?.status === 404) {
        Alert.alert("AI engine unavailable", "The AI engine is currently disabled.");
        router.back();
      } else {
        Alert.alert("Couldn't load AI credits", err?.message ?? "Try again in a moment.");
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [router]);

  useEffect(() => {
    void load();
  }, [load]);

  const buy = async (pack: AiCreditPack) => {
    if (walletCoins < pack.wallet_cost) {
      Alert.alert("Not enough coins", "Top up your wallet first.");
      return;
    }
    setBuying(pack.id);
    try {
      const r = await aiCreditsApi.purchase({
        pack_id: pack.id,
        idempotency_key: `mob-pack-${pack.id}-${packAttempt}`,
      });
      Alert.alert(
        "Credits added",
        `+${r.credits_added.toLocaleString()} ✦ — new balance ${r.balance.toLocaleString()}`,
      );
      // Rotate the attempt token only after a confirmed success so that
      // any retry mid-purchase reuses the same idempotency key.
      setPackAttempt(`pk-${Math.random().toString(36).slice(2)}-${Date.now()}`);
      await load();
    } catch (e: unknown) {
      const err = e as { message?: string } | undefined;
      Alert.alert("Purchase failed", err?.message ?? "Try again.");
    } finally {
      setBuying(null);
    }
  };

  const buyCustom = async () => {
    const credits = parseInt(customCredits, 10);
    if (!Number.isFinite(credits) || credits < 100) {
      Alert.alert("Invalid amount", "Enter at least 100 credits.");
      return;
    }
    const rate = balance?.wallet_to_credits_rate ?? 0;
    const cost = rate > 0 ? Math.ceil(credits / rate) : Number.POSITIVE_INFINITY;
    if (walletCoins < cost) {
      Alert.alert("Not enough coins", `Need ${cost.toLocaleString()} coins.`);
      return;
    }
    setBuying("__custom__");
    try {
      const r = await aiCreditsApi.purchase({
        credits,
        idempotency_key: `mob-custom-${credits}-${customAttempt}`,
      });
      Alert.alert(
        "Credits added",
        `+${r.credits_added.toLocaleString()} ✦ — new balance ${r.balance.toLocaleString()}`,
      );
      setCustomAttempt(`cu-${Math.random().toString(36).slice(2)}-${Date.now()}`);
      await load();
    } catch (e: unknown) {
      const err = e as { message?: string } | undefined;
      Alert.alert("Purchase failed", err?.message ?? "Try again.");
    } finally {
      setBuying(null);
    }
  };

  if (loading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "AI credits", headerShown: true }} />
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
          <Text style={[styles.label, { color: colors.mutedForeground }]}>AI credit balance</Text>
          <Text style={[styles.balance, { color: colors.primary }]}>
            {(balance?.balance ?? 0).toLocaleString()} ✦
          </Text>
          <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
            Spent {(balance?.lifetime_spent ?? 0).toLocaleString()} · purchased{" "}
            {(balance?.lifetime_purchased ?? 0).toLocaleString()}
          </Text>
          {balance && balance.wallet_to_credits_rate > 0 && (
            <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
              1 wallet coin = {balance.wallet_to_credits_rate} credits
            </Text>
          )}
        </View>

        <View>
          <Text style={[styles.section, { color: colors.foreground }]}>
            Top up with coins (wallet: {walletCoins.toLocaleString()} 🪙)
          </Text>
          {packs.length === 0 ? (
            <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
              No credit packs are configured yet.
            </Text>
          ) : (
            <View style={{ gap: 10 }}>
              {packs.map((p) => {
                const disabled = walletCoins < p.wallet_cost || buying === p.id;
                return (
                  <Pressable
                    key={p.id}
                    onPress={() => buy(p)}
                    disabled={disabled}
                    style={({ pressed }) => [
                      styles.packageCard,
                      {
                        backgroundColor: colors.card,
                        borderColor: colors.primary,
                        borderRadius: colors.radius,
                        opacity: disabled ? 0.45 : pressed ? 0.7 : 1,
                      },
                    ]}
                  >
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.pkgName, { color: colors.foreground }]}>{p.label}</Text>
                      <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
                        {p.credits.toLocaleString()} ✦ · costs {p.wallet_cost.toLocaleString()} 🪙
                      </Text>
                    </View>
                    {buying === p.id ? (
                      <ActivityIndicator color={colors.primary} />
                    ) : (
                      <Feather name="chevron-right" size={18} color={colors.primary} />
                    )}
                  </Pressable>
                );
              })}
            </View>
          )}
        </View>

        <View
          style={[
            styles.balanceCard,
            { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          <Text style={[styles.label, { color: colors.mutedForeground }]}>Custom amount</Text>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 10, marginTop: 6 }}>
            <TextInput
              value={customCredits}
              onChangeText={setCustomCredits}
              keyboardType="number-pad"
              style={[
                styles.input,
                { color: colors.foreground, borderColor: colors.border, backgroundColor: colors.background },
              ]}
              placeholder="1000"
              placeholderTextColor={colors.mutedForeground}
            />
            <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
              ≈ {(() => {
                const c = parseInt(customCredits, 10);
                const rate = balance?.wallet_to_credits_rate ?? 0;
                if (!Number.isFinite(c) || c <= 0 || rate <= 0) return "—";
                return Math.ceil(c / rate).toLocaleString();
              })()} 🪙
            </Text>
            <Pressable
              onPress={buyCustom}
              disabled={buying === "__custom__"}
              style={({ pressed }) => [
                {
                  paddingVertical: 10,
                  paddingHorizontal: 14,
                  borderRadius: colors.radius - 4,
                  backgroundColor: colors.primary,
                  opacity: buying === "__custom__" ? 0.5 : pressed ? 0.7 : 1,
                },
              ]}
            >
              <Text style={{ color: "#fff", fontWeight: "600", fontSize: 13 }}>Buy</Text>
            </Pressable>
          </View>
        </View>

        <View>
          <Text style={[styles.section, { color: colors.foreground }]}>Recent transactions</Text>
          {transactions.length === 0 ? (
            <Text style={[styles.subtle, { color: colors.mutedForeground }]}>No transactions yet.</Text>
          ) : (
            <View
              style={[
                styles.txList,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
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
                    <Text style={[styles.subtle, { color: colors.mutedForeground }]} numberOfLines={1}>
                      {prettyFeature(tx.feature) ?? tx.reason ?? ""}
                      {tx.model ? ` · ${tx.model}` : ""}
                    </Text>
                  </View>
                  <View style={{ alignItems: "flex-end" }}>
                    <Text
                      style={[
                        styles.txDelta,
                        { color: tx.delta_credits >= 0 ? "#10b981" : "#ef4444" },
                      ]}
                    >
                      {tx.delta_credits >= 0 ? "+" : ""}
                      {tx.delta_credits.toLocaleString()}
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
  section: { fontSize: 14, fontWeight: "600", marginBottom: 8 },
  packageCard: {
    flexDirection: "row",
    alignItems: "center",
    padding: 14,
    borderWidth: 1,
    gap: 12,
  },
  pkgName: { fontSize: 15, fontWeight: "600" },
  txList: { borderWidth: 1, paddingVertical: 4 },
  txRow: { flexDirection: "row", alignItems: "center", padding: 14, gap: 10 },
  txType: { fontSize: 13, fontWeight: "600", textTransform: "capitalize" },
  txDelta: { fontSize: 14, fontWeight: "700" },
  input: {
    flex: 1,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderWidth: 1,
    borderRadius: 8,
    fontSize: 14,
  },
});
