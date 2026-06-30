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
import {
  billing,
  type Plan,
  type PremiumFeature,
  type PremiumFeatureCell,
} from "@/lib/api/billing";

const VALUE_GREEN = "#34d399";

/**
 * Render descriptor for one resolved plan cell: either a check/dash `mark`
 * (boolean capabilities + not-included numbers) or a `text` value
 * (numeric allowance, "Unlimited", "Custom", "Advanced"/"Basic").
 */
type CellDisplay =
  | { type: "mark"; on: boolean }
  | { type: "text"; text: string; on: boolean };

/**
 * Turn a resolved per-plan cell into a render descriptor, mirroring the web
 * comparison grid's $renderCell (public/pricing/features.blade.php):
 *  - number:    unlimited → "Unlimited"; not-on → dash mark; else the value
 *               text ("500" / "Custom").
 *  - analytics: the Basic/Advanced text (green when Advanced).
 *  - bool:      a check mark when on, a dash mark when off.
 * A missing cell (older server / unknown plan) renders as an off dash.
 */
export function formatPlanCell(cell: PremiumFeatureCell | undefined): CellDisplay {
  if (!cell) return { type: "mark", on: false };
  if (cell.kind === "number") {
    if (cell.unlimited) return { type: "text", text: "Unlimited", on: true };
    if (!cell.on) return { type: "mark", on: false };
    return { type: "text", text: cell.text, on: true };
  }
  if (cell.kind === "analytics") {
    return { type: "text", text: cell.text, on: cell.on };
  }
  return { type: "mark", on: cell.on };
}

export default function PremiumFeaturesScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const plansQuery = useQuery({
    queryKey: ["billing", "plans"],
    queryFn: () => billing.plans(),
  });

  const features: PremiumFeature[] = plansQuery.data?.data?.premium_features ?? [];
  const plans: Plan[] = useMemo(
    () => plansQuery.data?.data?.plans ?? [],
    [plansQuery.data],
  );

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
          Plain-English explanations of every premium capability — and exactly what each plan
          includes.
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
                    <View style={styles.featureHead}>
                      <Text style={[styles.featureName, { color: colors.foreground }]}>{f.name}</Text>
                      {f.unit ? (
                        <Text style={[styles.unitLabel, { color: colors.mutedForeground, borderColor: colors.border }]}>
                          {f.unit}
                        </Text>
                      ) : null}
                    </View>
                    <Text style={[styles.featureDesc, { color: colors.mutedForeground }]}>
                      {f.description}
                    </Text>
                    {plans.length === 0 ? (
                      <Text style={[styles.cellPlan, { color: colors.mutedForeground }]}>
                        No plans are currently published.
                      </Text>
                    ) : (
                      <View style={styles.cellTable}>
                        {plans.map((p) => {
                          const disp = formatPlanCell(f.cells?.[p.slug]);
                          return (
                            <View key={p.slug} style={styles.cellRow}>
                              <Text
                                style={[styles.cellPlan, { color: colors.mutedForeground }]}
                                numberOfLines={1}
                              >
                                {p.name}
                                {p.is_current ? " · current" : ""}
                              </Text>
                              {disp.type === "mark" ? (
                                <Feather
                                  name={disp.on ? "check" : "minus"}
                                  size={15}
                                  color={disp.on ? VALUE_GREEN : colors.mutedForeground}
                                />
                              ) : (
                                <Text
                                  style={[
                                    styles.cellValue,
                                    { color: disp.on ? VALUE_GREEN : colors.foreground },
                                  ]}
                                >
                                  {disp.text}
                                </Text>
                              )}
                            </View>
                          );
                        })}
                      </View>
                    )}
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
  featureHead: { flexDirection: "row", alignItems: "center", flexWrap: "wrap", gap: 8 },
  featureName: { fontSize: 15, fontFamily: "SpaceGrotesk_700Bold" },
  unitLabel: {
    fontSize: 10,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderWidth: 1,
    borderRadius: 999,
    fontFamily: "SpaceGrotesk_400Regular",
  },
  featureDesc: { fontSize: 13, lineHeight: 18 },
  cellTable: { marginTop: 6, gap: 2 },
  cellRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
    paddingVertical: 5,
  },
  cellPlan: { fontSize: 12, flexShrink: 1, fontFamily: "SpaceGrotesk_400Regular" },
  cellValue: { fontSize: 13, fontFamily: "SpaceGrotesk_700Bold", textAlign: "right" },
  linkRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 10,
  },
});
