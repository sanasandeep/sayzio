import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Platform,
  Pressable,
  RefreshControl,
  Share,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { fetchSubmissionsCsv, getForm, listSubmissions } from "@/lib/api/forms";

export default function FormDetailScreen() {
  const colors = useColors();
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);

  const fq = useQuery({
    queryKey: ["form", id],
    queryFn: () => getForm(id),
    enabled: !!id,
  });
  const sq = useQuery({
    queryKey: ["form", id, "submissions"],
    queryFn: () => listSubmissions(id),
    enabled: !!id,
  });

  const fields = fq.data?.fields ?? [];
  const fieldLabel = (fid: string) =>
    fields.find((f) => f.id === fid)?.label || fid;

  const fmtCents = (cents: number, currency?: string | null) =>
    `${(cents / 100).toFixed(2)} ${(currency || "USD").toUpperCase()}`;

  const [exporting, setExporting] = useState(false);
  const exportCsv = async () => {
    setExporting(true);
    try {
      const csv = await fetchSubmissionsCsv(id);
      if (Platform.OS === "web" && typeof window !== "undefined") {
        const blob = new Blob([csv], { type: "text/csv" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `form-${id}-submissions.csv`;
        a.click();
        URL.revokeObjectURL(url);
      } else {
        await Share.share({
          message: csv,
          title: `Form ${id} submissions`,
        });
      }
    } catch (e: any) {
      Alert.alert("Export failed", e?.message ?? "Try again");
    } finally {
      setExporting(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: fq.data?.title || "Form",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
          headerRight: () => (
            <Pressable
              onPress={exportCsv}
              disabled={exporting}
              hitSlop={8}
              style={{ paddingRight: 12, opacity: exporting ? 0.5 : 1 }}
            >
              {exporting ? (
                <ActivityIndicator color={colors.primary} size="small" />
              ) : (
                <Feather name="download" size={20} color={colors.primary} />
              )}
            </Pressable>
          ),
        }}
      />
      {sq.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={sq.data?.items ?? []}
          keyExtractor={(s) => String(s.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          refreshControl={
            <RefreshControl
              refreshing={sq.isFetching && !sq.isLoading}
              onRefresh={() => sq.refetch()}
              tintColor={colors.primary}
            />
          }
          ListHeaderComponent={
            <View style={{ paddingBottom: 8 }}>
              <Text style={[styles.summary, { color: colors.mutedForeground }]}>
                {sq.data?.total ?? 0} submissions
              </Text>
            </View>
          }
          renderItem={({ item }) => (
            <View
              style={[
                styles.card,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <Text style={[styles.when, { color: colors.mutedForeground }]}>
                #{item.id} ·{" "}
                {item.created_at
                  ? new Date(item.created_at).toLocaleString()
                  : ""}
              </Text>
              {Object.entries(item.data || {})
                .slice(0, 8)
                .map(([k, v]) => (
                  <View key={k} style={styles.kv}>
                    <Text
                      style={[styles.k, { color: colors.mutedForeground }]}
                    >
                      {fieldLabel(k)}
                    </Text>
                    <Text
                      style={[styles.v, { color: colors.foreground }]}
                    >
                      {Array.isArray(v) ? v.join(", ") : String(v ?? "")}
                    </Text>
                  </View>
                ))}
              {((item.line_items && item.line_items.length > 0) ||
                (item.amount_cents ?? 0) > 0) && (
                <View
                  style={[styles.payment, { borderTopColor: colors.border }]}
                >
                  <View style={styles.paymentHeader}>
                    <View style={styles.paymentHeaderLeft}>
                      <Feather
                        name="credit-card"
                        size={12}
                        color={colors.mutedForeground}
                      />
                      <Text
                        style={[
                          styles.k,
                          { color: colors.mutedForeground },
                        ]}
                      >
                        Payment
                      </Text>
                    </View>
                    {item.payment_status &&
                      item.payment_status !== "none" && (
                        <View
                          style={[
                            styles.badge,
                            {
                              backgroundColor:
                                item.payment_status === "paid"
                                  ? colors.success + "26"
                                  : item.payment_status === "pending"
                                    ? colors.warning + "26"
                                    : colors.destructive + "26",
                            },
                          ]}
                        >
                          <Text
                            style={[
                              styles.badgeText,
                              {
                                color:
                                  item.payment_status === "paid"
                                    ? colors.success
                                    : item.payment_status === "pending"
                                      ? colors.warning
                                      : colors.destructive,
                              },
                            ]}
                          >
                            {item.payment_status}
                          </Text>
                        </View>
                      )}
                  </View>
                  {(item.line_items ?? []).map((li, i) => (
                    <View key={i} style={styles.lineItem}>
                      <View style={{ flex: 1, paddingRight: 8 }}>
                        <Text
                          style={[styles.liLabel, { color: colors.foreground }]}
                        >
                          {li.field === "__base__"
                            ? "Base fee"
                            : li.label || li.field || "Item"}
                        </Text>
                        {!!li.detail && (
                          <Text
                            style={[
                              styles.liDetail,
                              { color: colors.mutedForeground },
                            ]}
                          >
                            {li.detail}
                          </Text>
                        )}
                      </View>
                      <Text
                        style={[styles.liAmount, { color: colors.mutedForeground }]}
                      >
                        {fmtCents(li.amount_cents, item.currency)}
                      </Text>
                    </View>
                  ))}
                  <View
                    style={[styles.lineItem, { marginTop: 2 }]}
                  >
                    <Text
                      style={[styles.totalLabel, { color: colors.foreground }]}
                    >
                      Total
                    </Text>
                    <Text
                      style={[styles.totalAmount, { color: colors.foreground }]}
                    >
                      {fmtCents(item.amount_cents ?? 0, item.currency)}
                    </Text>
                  </View>
                </View>
              )}
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="inbox"
              title="No submissions yet"
              body="Share your form to start collecting responses."
            />
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  summary: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  card: { padding: 16, borderWidth: 1, gap: 6 },
  when: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11 },
  kv: { gap: 2, marginTop: 4 },
  k: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  v: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14 },
  payment: { marginTop: 10, paddingTop: 10, borderTopWidth: 1, gap: 4 },
  paymentHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 4,
  },
  paymentHeaderLeft: { flexDirection: "row", alignItems: "center", gap: 5 },
  badge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.5,
    textTransform: "uppercase",
  },
  lineItem: {
    flexDirection: "row",
    alignItems: "flex-start",
    justifyContent: "space-between",
  },
  liLabel: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  liDetail: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11, marginTop: 1 },
  liAmount: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  totalLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  totalAmount: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
});
