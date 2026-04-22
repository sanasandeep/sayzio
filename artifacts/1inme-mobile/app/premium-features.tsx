import { Feather } from "@expo/vector-icons";
import { Stack, useRouter } from "expo-router";
import { useMemo } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useQuery } from "@tanstack/react-query";

import { useColors } from "@/hooks/useColors";
import { billing, type PremiumFeature } from "@/lib/api/billing";

export default function PremiumFeaturesScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const plansQuery = useQuery({
    queryKey: ["billing", "plans"],
    queryFn: () => billing.plans(),
  });

  const features: PremiumFeature[] = plansQuery.data?.data?.premium_features ?? [];
  const planNameBySlug = useMemo(() => {
    const map: Record<string, string> = {};
    for (const p of plansQuery.data?.data?.plans ?? []) {
      map[p.slug] = p.name;
    }
    return map;
  }, [plansQuery.data]);

  const grouped = useMemo(() => {
    const map = new Map<string, PremiumFeature[]>();
    for (const f of features) {
      const list = map.get(f.group) ?? [];
      list.push(f);
      map.set(f.group, list);
    }
    return Array.from(map.entries());
  }, [features]);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Premium features", headerShown: true }} />
      <ScrollView
        contentContainerStyle={{
          paddingTop: 16,
          paddingHorizontal: 20,
          paddingBottom: insets.bottom + 32,
          gap: 18,
        }}
      >
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          Plain-English explanations of every premium capability and which plans include it.
        </Text>

        {plansQuery.isLoading ? (
          <ActivityIndicator color={colors.primary} />
        ) : plansQuery.error ? (
          <Text style={{ color: colors.destructive }}>Could not load features.</Text>
        ) : grouped.length === 0 ? (
          <Text style={{ color: colors.mutedForeground }}>
            No premium features are configured yet.
          </Text>
        ) : (
          grouped.map(([group, items]) => (
            <View key={group} style={{ gap: 8 }}>
              <Text style={[styles.groupTitle, { color: colors.primary }]}>
                {group.toUpperCase()}
              </Text>
              <View
                style={[
                  styles.groupCard,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                {items.map((f, i) => (
                  <View
                    key={f.key}
                    style={[
                      styles.featureRow,
                      i === 0 ? null : { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.border },
                    ]}
                  >
                    <Text style={[styles.featureName, { color: colors.foreground }]}>{f.name}</Text>
                    <Text style={[styles.featureDesc, { color: colors.mutedForeground }]}>
                      {f.description}
                    </Text>
                    <View style={styles.badgeRow}>
                      {f.unlocked_by.length === 0 ? (
                        <Text style={[styles.badgeMuted, { color: colors.mutedForeground, borderColor: colors.border }]}>
                          Not unlocked by any plan
                        </Text>
                      ) : (
                        f.unlocked_by.map((slug) => (
                          <Text
                            key={slug}
                            style={[
                              styles.badge,
                              { color: colors.primary, borderColor: colors.primary },
                            ]}
                          >
                            {(planNameBySlug[slug] ?? slug).toUpperCase()}
                          </Text>
                        ))
                      )}
                      {f.unit ? (
                        <Text style={[styles.badgeMuted, { color: colors.mutedForeground, borderColor: colors.border }]}>
                          {f.unit}
                        </Text>
                      ) : null}
                    </View>
                  </View>
                ))}
              </View>
            </View>
          ))
        )}

        <Pressable onPress={() => router.push("/plans" as never)} style={styles.linkRow}>
          <Feather name="tag" size={14} color={colors.primary} />
          <Text style={{ color: colors.primary, fontFamily: "SpaceGrotesk_600SemiBold" }}>
            See all plans
          </Text>
        </Pressable>
        <Pressable onPress={() => router.push("/coin-packages" as never)} style={styles.linkRow}>
          <Feather name="dollar-sign" size={14} color={colors.primary} />
          <Text style={{ color: colors.primary, fontFamily: "SpaceGrotesk_600SemiBold" }}>
            Browse coin packages
          </Text>
        </Pressable>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  intro: { fontSize: 13, lineHeight: 18 },
  groupTitle: { fontSize: 11, letterSpacing: 1.2, fontFamily: "SpaceGrotesk_700Bold" },
  groupCard: { borderWidth: 1 },
  featureRow: { padding: 14, gap: 6 },
  featureName: { fontSize: 15, fontFamily: "SpaceGrotesk_700Bold" },
  featureDesc: { fontSize: 13, lineHeight: 18 },
  badgeRow: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 4 },
  badge: {
    fontSize: 10,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderWidth: 1,
    borderRadius: 999,
    fontFamily: "SpaceGrotesk_700Bold",
    letterSpacing: 0.5,
  },
  badgeMuted: {
    fontSize: 10,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderWidth: 1,
    borderRadius: 999,
    fontFamily: "SpaceGrotesk_400Regular",
  },
  linkRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 10,
  },
});
