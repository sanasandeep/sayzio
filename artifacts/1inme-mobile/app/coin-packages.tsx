import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
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
import { type CoinPackage, wallet as walletApi } from "@/lib/api";
import { showAlert } from "@/lib/webAlert";

const GATEWAYS: { slug: string; label: string }[] = [
  { slug: "stripe", label: "Card (Stripe)" },
  { slug: "razorpay", label: "Razorpay" },
  { slug: "paypal", label: "PayPal" },
  { slug: "cashfree", label: "Cashfree" },
  { slug: "offline", label: "Manual / Offline" },
];

export default function CoinPackagesScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [packages, setPackages] = useState<CoinPackage[]>([]);
  const [busyPkgId, setBusyPkgId] = useState<number | null>(null);

  const load = useCallback(async () => {
    try {
      const pkgs = await walletApi.packages();
      setPackages(pkgs.items);
    } catch (e: unknown) {
      const err = e as { status?: number; message?: string } | undefined;
      if (err?.status === 404) {
        showAlert("Coins unavailable", "The wallet feature is currently disabled.");
        router.back();
      } else {
        showAlert("Couldn't load coin packages", err?.message ?? "Try again in a moment.");
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [router]);

  useEffect(() => {
    void load();
  }, [load]);

  const buy = async (pkg: CoinPackage, gateway: string) => {
    setBusyPkgId(pkg.id);
    try {
      const res = await walletApi.purchase(pkg.id, gateway);
      const handoff = res.handoff;
      if (
        handoff &&
        typeof handoff === "object" &&
        "kind" in handoff &&
        handoff.kind === "redirect" &&
        "url" in handoff &&
        typeof handoff.url === "string"
      ) {
        await WebBrowser.openBrowserAsync(handoff.url);
        await load();
      } else {
        showAlert("Almost there", "Continue checkout in your web browser to complete payment.");
      }
    } catch (e: unknown) {
      const err = e as { message?: string } | undefined;
      showAlert("Purchase failed", err?.message ?? "Please try again.");
    } finally {
      setBusyPkgId(null);
    }
  };

  const promptGateway = (pkg: CoinPackage) => {
    showAlert("Pay with", pkg.name, [
      ...GATEWAYS.map((g) => ({ text: g.label, onPress: () => buy(pkg, g.slug) })),
      { text: "Cancel", style: "cancel" as const },
    ]);
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
      <Stack.Screen options={{ title: "Coin packages", headerShown: true }} />
      <ScrollView
        contentContainerStyle={{
          paddingTop: 16,
          paddingHorizontal: 20,
          paddingBottom: insets.bottom + 32,
          gap: 16,
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
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          Top up coins to unlock paid add-ons on demand without changing your subscription plan.
        </Text>

        {packages.length === 0 ? (
          <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
            No coin packages are available right now.
          </Text>
        ) : (
          <View style={{ gap: 10 }}>
            {packages.map((pkg) => (
              <Pressable
                key={pkg.id}
                onPress={() => promptGateway(pkg)}
                disabled={busyPkgId === pkg.id}
                style={({ pressed }) => [
                  styles.packageCard,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                    opacity: pressed || busyPkgId === pkg.id ? 0.6 : 1,
                  },
                ]}
              >
                <View style={{ flex: 1 }}>
                  <Text style={[styles.pkgName, { color: colors.foreground }]}>{pkg.name}</Text>
                  <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
                    {pkg.coin_amount.toLocaleString()} coins
                    {pkg.bonus_coins > 0 ? ` + ${pkg.bonus_coins.toLocaleString()} bonus` : ""}
                  </Text>
                  {pkg.description ? (
                    <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
                      {pkg.description}
                    </Text>
                  ) : null}
                </View>
                <View style={{ alignItems: "flex-end" }}>
                  <Text style={[styles.price, { color: colors.foreground }]}>
                    {pkg.formatted ?? `${pkg.currency} ${(pkg.amount_minor / 100).toFixed(2)}`}
                  </Text>
                  {busyPkgId === pkg.id ? (
                    <ActivityIndicator size="small" color={colors.primary} />
                  ) : (
                    <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
                  )}
                </View>
              </Pressable>
            ))}
          </View>
        )}

        <Pressable onPress={() => router.push("/wallet" as never)} style={styles.linkRow}>
          <Feather name="credit-card" size={14} color={colors.primary} />
          <Text style={{ color: colors.primary, fontFamily: "SpaceGrotesk_600SemiBold" }}>
            View wallet balance & transactions
          </Text>
        </Pressable>
        <Pressable onPress={() => router.push("/plans" as never)} style={styles.linkRow}>
          <Feather name="tag" size={14} color={colors.primary} />
          <Text style={{ color: colors.primary, fontFamily: "SpaceGrotesk_600SemiBold" }}>
            Compare subscription plans
          </Text>
        </Pressable>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  intro: { fontSize: 13, lineHeight: 18 },
  subtle: { fontSize: 12 },
  packageCard: {
    flexDirection: "row",
    alignItems: "center",
    padding: 14,
    borderWidth: 1,
    gap: 12,
  },
  pkgName: { fontSize: 15, fontWeight: "600" },
  price: { fontSize: 16, fontWeight: "700" },
  linkRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 10,
  },
});
