import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import { ActivityIndicator, Linking, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { apiFetch, getBaseUrl } from "@/lib/api";

// Task #1211 — mobile parity stub for the admin Reports & DMCA queue.
// Mirrors the web /admin/moderation-queue tabs (user reports + DMCA
// takedowns). Phone layout keeps it list-only — destructive moderator
// actions (warn / suspend / remove) are surfaced as a "review on web"
// CTA that opens the full admin queue in the browser to keep the
// mobile permission surface tiny.

type ReportRow = {
  id: number;
  target_type: string;
  target_label: string;
  reporter_name: string;
  reason: string;
  count: number;
  created_at: string;
};

type DmcaRow = {
  id: number;
  reporter_name: string;
  reporter_email: string;
  target_label: string;
  status: string;
  created_at: string;
};

async function fetchReports(): Promise<ReportRow[]> {
  const res = await apiFetch<{ data: ReportRow[] }>("/admin/moderation/reports");
  return res.data ?? [];
}
async function fetchDmca(): Promise<DmcaRow[]> {
  const res = await apiFetch<{ data: DmcaRow[] }>("/admin/moderation/dmca");
  return res.data ?? [];
}

export default function ModerationScreen() {
  const colors = useColors();
  const [tab, setTab] = useState<"reports" | "dmca">("reports");

  const reports = useQuery({ queryKey: ["mod-reports"], queryFn: fetchReports, enabled: tab === "reports" });
  const dmca = useQuery({ queryKey: ["mod-dmca"], queryFn: fetchDmca, enabled: tab === "dmca" });

  const tabBtn = (key: "reports" | "dmca", label: string, icon: any) => (
    <Pressable
      onPress={() => setTab(key)}
      style={[
        styles.tab,
        {
          backgroundColor: tab === key ? colors.primary : "transparent",
          borderColor: tab === key ? colors.primary : colors.border,
        },
      ]}
    >
      <Feather name={icon} size={14} color={tab === key ? "#fff" : colors.foreground} />
      <Text
        style={[
          styles.tabLabel,
          { color: tab === key ? "#fff" : colors.foreground },
        ]}
      >
        {label}
      </Text>
    </Pressable>
  );

  const renderEmpty = (msg: string) => (
    <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
      <Feather name="check-circle" size={20} color={colors.mutedForeground} />
      <Text style={{ color: colors.foreground, marginTop: 6 }}>{msg}</Text>
    </View>
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Moderation", headerBackTitle: "Back" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 40 }}>
        <View style={styles.tabs}>
          {tabBtn("reports", "User reports", "flag")}
          {tabBtn("dmca", "DMCA", "shield")}
        </View>

        {tab === "reports" ? (
          reports.isLoading ? (
            <ActivityIndicator color={colors.primary} />
          ) : reports.isError ? (
            renderEmpty("Couldn't load reports.")
          ) : (reports.data ?? []).length === 0 ? (
            renderEmpty("No open user reports — you're all caught up.")
          ) : (
            (reports.data ?? []).map((r) => (
              <View key={r.id} style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <View style={styles.cardHead}>
                  <Text style={[styles.target, { color: colors.foreground }]}>{r.target_label}</Text>
                  <View style={[styles.badge, { backgroundColor: colors.primary + "15" }]}>
                    <Text style={[styles.badgeText, { color: colors.primary }]}>×{r.count}</Text>
                  </View>
                </View>
                <Text style={[styles.meta, { color: colors.mutedForeground }]}>
                  {r.target_type} • reported by {r.reporter_name}
                </Text>
                <Text style={{ color: colors.foreground, marginTop: 6 }} numberOfLines={4}>
                  {r.reason || "(no reason given)"}
                </Text>
              </View>
            ))
          )
        ) : dmca.isLoading ? (
          <ActivityIndicator color={colors.primary} />
        ) : dmca.isError ? (
          renderEmpty("Couldn't load DMCA queue.")
        ) : (dmca.data ?? []).length === 0 ? (
          renderEmpty("No pending DMCA takedowns.")
        ) : (
          (dmca.data ?? []).map((d) => (
            <View key={d.id} style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.cardHead}>
                <Text style={[styles.target, { color: colors.foreground }]}>{d.target_label}</Text>
                <Text style={[styles.meta, { color: colors.mutedForeground }]}>{d.status}</Text>
              </View>
              <Text style={[styles.meta, { color: colors.mutedForeground }]}>
                from {d.reporter_name} ({d.reporter_email})
              </Text>
            </View>
          ))
        )}

        <Button
          label="Review on web"
          variant="outline"
          onPress={() => Linking.openURL(`${getBaseUrl().replace(/\/api\/?$/, "")}/admin/moderation-queue`)}
        />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  tabs: { flexDirection: "row", gap: 8 },
  tab: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
  },
  tabLabel: { fontSize: 12, fontFamily: "SpaceGrotesk_600SemiBold" },
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 4 },
  cardHead: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  target: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 14, flex: 1 },
  meta: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium" },
  badge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 999 },
  badgeText: { fontSize: 11, fontFamily: "SpaceGrotesk_700Bold" },
});
