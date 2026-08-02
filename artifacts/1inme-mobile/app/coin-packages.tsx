import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import { type CoinPackage, wallet as walletApi } from "@/lib/api";
import { showAlert } from "@/lib/webAlert";

function openPricingPage(): void {
  const url = `${getBaseUrl()}/pricing`;
  if (Platform.OS === "web") {
    if (typeof window !== "undefined") {
      window.open(url, "_blank");
    }
    return;
  }
  Linking.openURL(url).catch(() => {});
}

export default function CoinPackagesScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [packages, setPackages] = useState<CoinPackage[]>([]);

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

        <View
          style={[
            styles.webBanner,
            {
              backgroundColor: colors.primary + "14",
              borderColor: colors.primary + "44",
              borderRadius: colors.radius,
            },
          ]}
        >
          <Feather name="external-link" size={15} color={colors.primary} />
          <Text style={[styles.webBannerText, { color: colors.mutedForeground }]}>
            Coin purchases are completed on the website.{" "}
            <Text style={{ color: colors.primary, fontFamily: "SpaceGrotesk_600SemiBold" }}>
              Open the pricing page
            </Text>{" "}
            in your browser to buy coins.
          </Text>
        </View>

        {packages.length === 0 ? (
          <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
            No coin packages are available right now.
          </Text>
        ) : (
          <View style={{ gap: 10 }}>
            {packages.map((pkg) => (
              <View
                key={pkg.id}
                style={[
                  styles.packageCard,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View style={{ flex: 1 }}>
                  <Text style={[styles.pkgName, { color: colors.foreground }]}>{pkg.name}</Text>
                  <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
                    {pkg.coin_amount.toLocaleString()} coins
                    {pkg.bonus_coins > 0 ? ` + ${pkg.bonus_coins.toLocaleString()} bonus` : ""}
                  </Text>
                  {pkg.plan_bonus_pct > 0 ? (
                    <Text style={[styles.subtle, { color: colors.primary }]}>
                      +{pkg.plan_bonus_coins.toLocaleString()} coins ({pkg.plan_bonus_pct}%{" "}
                      {pkg.plan_bonus_plan_name ?? "plan"} plan bonus) ={" "}
                      {pkg.total_with_plan_bonus.toLocaleString()} total
                    </Text>
                  ) : null}
                  {pkg.description ? (
                    <Text style={[styles.subtle, { color: colors.mutedForeground }]}>
                      {pkg.description}
                    </Text>
                  ) : null}
                </View>
                <View style={{ alignItems: "flex-end", gap: 8 }}>
                  <Text style={[styles.price, { color: colors.foreground }]}>
                    {pkg.formatted ?? `${pkg.currency} ${(pkg.amount_minor / 100).toFixed(2)}`}
                  </Text>
                  <Pressable
                    onPress={openPricingPage}
                    style={({ pressed }) => [
                      styles.buyBtn,
                      {
                        backgroundColor: colors.primary,
                        borderRadius: colors.radius,
                        opacity: pressed ? 0.7 : 1,
                      },
                    ]}
                  >
                    <Feather name="external-link" size={13} color={colors.primaryForeground} />
                    <Text style={[styles.buyBtnText, { color: colors.primaryForeground }]}>
                      Buy on web
                    </Text>
                  </Pressable>
                </View>
              </View>
            ))}
          </View>
        )}

        <Button
          label="Open pricing page to buy coins"
          onPress={openPricingPage}
          style={{ marginTop: 4 }}
        />

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
  webBanner: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
    padding: 12,
    borderWidth: 1,
  },
  webBannerText: {
    flex: 1,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
  },
  packageCard: {
    flexDirection: "row",
    alignItems: "center",
    padding: 14,
    borderWidth: 1,
    gap: 12,
  },
  pkgName: { fontSize: 15, fontWeight: "600" },
  price: { fontSize: 16, fontWeight: "700" },
  buyBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  buyBtnText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
  },
  linkRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 10,
  },
});
