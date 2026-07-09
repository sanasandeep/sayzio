import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import {
  getLinkTypePairings,
  restoreLinkTypePairingDefaults,
  updateLinkTypePairings,
  type PairingsStatus,
} from "@/lib/api/linkTypePairings";
import { showAlert } from "@/lib/webAlert";

// Mobile parity for the web admin "Perfect Pairings" toggles page. Each
// public link-type page (biolink, resume, reviews, restaurant menu, store,
// event) shows a small set of cross-promo cards; this screen lets a platform
// admin check/uncheck individual cards per page type. Unchecking every card
// for a page hides the whole section on that page type. Server-gated behind
// `settings.manage` (403 otherwise).

// Local checkbox state: pageKey -> set of checked card types.
type ChecksState = Record<string, Set<string>>;

function seedChecks(data: PairingsStatus): ChecksState {
  const next: ChecksState = {};
  for (const section of data.sections) {
    next[section.key] = new Set(
      section.items.filter((i) => i.enabled).map((i) => i.type),
    );
  }
  return next;
}

function toEnabledPayload(checks: ChecksState): Record<string, string[]> {
  const enabled: Record<string, string[]> = {};
  for (const [key, set] of Object.entries(checks)) {
    if (set.size > 0) enabled[key] = Array.from(set);
  }
  return enabled;
}

export default function LinkTypePairingsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [checks, setChecks] = useState<ChecksState | null>(null);
  const seededRef = useRef(false);
  const reseedRef = useRef(false);

  const query = useQuery({
    queryKey: ["admin-link-type-pairings"],
    queryFn: getLinkTypePairings,
  });

  // Seed the checkbox state once from the server, then leave it under the
  // admin's control so a background refetch doesn't clobber in-progress
  // edits. A successful save/restore flips reseedRef so we re-seed from the
  // freshly-returned server truth.
  useEffect(() => {
    if (query.data && (!seededRef.current || reseedRef.current)) {
      seededRef.current = true;
      reseedRef.current = false;
      setChecks(seedChecks(query.data));
    }
  }, [query.data]);

  const applyResult = (data: PairingsStatus) => {
    qc.setQueryData(["admin-link-type-pairings"], data);
    reseedRef.current = true;
    setChecks(seedChecks(data));
  };

  const save = useMutation({
    mutationFn: () => updateLinkTypePairings({ enabled: toEnabledPayload(checks ?? {}) }),
    onSuccess: (data) => {
      applyResult(data);
      showAlert("Saved", "Perfect Pairings settings updated.");
    },
    onError: (e: any) =>
      showAlert(
        "Couldn't save",
        e?.status === 403
          ? "You don't have permission to change these settings."
          : e?.message ?? "Try again.",
      ),
  });

  const restore = useMutation({
    mutationFn: restoreLinkTypePairingDefaults,
    onSuccess: (data) => {
      applyResult(data);
      showAlert("Restored", "All Perfect Pairings cards re-enabled.");
    },
    onError: (e: any) =>
      showAlert(
        "Couldn't restore",
        e?.status === 403
          ? "You don't have permission to change these settings."
          : e?.message ?? "Try again.",
      ),
  });

  const toggle = (pageKey: string, type: string) => {
    setChecks((prev) => {
      if (!prev) return prev;
      const next: ChecksState = { ...prev };
      const set = new Set(next[pageKey] ?? []);
      if (set.has(type)) set.delete(type);
      else set.add(type);
      next[pageKey] = set;
      return next;
    });
  };

  // Alert.alert is a no-op on react-native-web, so the confirm falls back to
  // window.confirm on web (same cross-platform pattern as links/[id]/edit.tsx).
  const confirmRestore = () => {
    const title = "Restore defaults?";
    const msg = "This re-enables every Perfect Pairings card on every page type.";
    if (Platform.OS === "web") {
      if (typeof window !== "undefined" && window.confirm(`${title}\n\n${msg}`)) {
        restore.mutate();
      }
      return;
    }
    showAlert(title, msg, [
      { text: "Cancel", style: "cancel" },
      { text: "Restore", style: "destructive", onPress: () => restore.mutate() },
    ]);
  };

  const data = query.data;
  const card = [styles.card, { backgroundColor: colors.card, borderColor: colors.border }];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: "Perfect Pairings", headerBackTitle: "Back" }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 64 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={card}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You don't have permission to view Perfect Pairings settings."
                : "Couldn't load Perfect Pairings settings."}
            </Text>
          </View>
        ) : data && checks ? (
          <>
            <View style={card}>
              <View style={styles.head}>
                <Feather name="link-2" size={18} color={colors.primary} />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  Cross-promo cards
                </Text>
              </View>
              <Text style={{ color: colors.mutedForeground, marginTop: 8, fontSize: 13 }}>
                Each public page type suggests a few complementary link types.
                Uncheck a card to hide it; unchecking every card hides the
                whole section on that page type.
              </Text>
            </View>

            {data.sections.map((section) => {
              const checked = checks[section.key] ?? new Set<string>();
              return (
                <View key={section.key} style={card}>
                  <View style={styles.head}>
                    <Text style={[styles.title, { color: colors.foreground, flex: 1 }]}>
                      {section.label}
                    </Text>
                    {checked.size === 0 ? (
                      <View style={[styles.badge, { backgroundColor: colors.warning + "1a" }]}>
                        <Text style={{ color: colors.warning, fontSize: 11, fontWeight: "600" }}>
                          Section hidden
                        </Text>
                      </View>
                    ) : null}
                  </View>
                  {section.items.map((item, i) => {
                    const isChecked = checked.has(item.type);
                    return (
                      <Pressable
                        key={item.type}
                        onPress={() => toggle(section.key, item.type)}
                        accessibilityRole="checkbox"
                        accessibilityState={{ checked: isChecked }}
                        aria-checked={isChecked}
                        style={({ pressed }) => [
                          styles.row,
                          {
                            borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                            borderTopColor: colors.border,
                            opacity: pressed ? 0.7 : 1,
                          },
                        ]}
                      >
                        <View
                          style={[
                            styles.checkbox,
                            {
                              borderColor: isChecked ? colors.primary : colors.border,
                              backgroundColor: isChecked ? colors.primary : "transparent",
                            },
                          ]}
                        >
                          {isChecked ? (
                            <Feather name="check" size={14} color={colors.primaryForeground ?? "#fff"} />
                          ) : null}
                        </View>
                        <View style={{ flex: 1, minWidth: 0 }}>
                          <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                            {item.name}
                          </Text>
                          <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }}>
                            {item.benefit}
                          </Text>
                        </View>
                      </Pressable>
                    );
                  })}
                </View>
              );
            })}

            <Button
              label="Save settings"
              onPress={() => save.mutate()}
              loading={save.isPending}
            />
            <Button
              label="Restore defaults"
              variant="outline"
              onPress={confirmRestore}
              loading={restore.isPending}
            />
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 16, padding: 16 },
  head: { flexDirection: "row", alignItems: "center", gap: 8 },
  title: { fontSize: 16, fontWeight: "700" },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 12,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 2,
    alignItems: "center",
    justifyContent: "center",
  },
  badge: {
    paddingHorizontal: 9,
    paddingVertical: 4,
    borderRadius: 999,
  },
});
