import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { apiFetch } from "@/lib/api";

type Invoice = {
  id: number | string;
  number?: string | null;
  status?: string | null;
  amount?: number | string | null;
  amount_minor?: number | null;
  currency?: string | null;
  formatted?: string | null;
  created_at?: string | null;
  due_at?: string | null;
};

async function listInvoices(): Promise<Invoice[]> {
  // The backend wraps list payloads as either { data: { items } } or
  // { data: [...] }. Normalize either shape.
  const res = await apiFetch<{
    data?: { items?: Invoice[] } | Invoice[];
    items?: Invoice[];
  }>("/billing/invoices");
  if (Array.isArray(res?.data)) return res.data as Invoice[];
  if (Array.isArray((res as { data?: { items?: Invoice[] } })?.data?.items)) {
    return (res as { data: { items: Invoice[] } }).data.items;
  }
  if (Array.isArray(res?.items)) return res.items;
  return [];
}

const statusTint = (
  colors: ReturnType<typeof useColors>,
): Record<string, string> => ({
  paid: colors.success,
  sent: "#0ea5e9",
  draft: "#7d9bff",
  overdue: colors.destructive,
  void: "#9ca3af",
});

function formatAmount(inv: Invoice): string {
  if (inv.formatted) return inv.formatted;
  if (typeof inv.amount_minor === "number") {
    const major = inv.amount_minor / 100;
    return `${inv.currency ?? ""} ${major.toFixed(2)}`.trim();
  }
  if (inv.amount != null) return `${inv.currency ?? ""} ${inv.amount}`.trim();
  return "—";
}

export default function InvoicesScreen() {
  const colors = useColors();
  const router = useRouter();

  const q = useQuery({
    queryKey: ["billing-invoices"],
    queryFn: listInvoices,
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Invoices" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load invoices"
          body={
            (q.error as { message?: string })?.message ??
            "Check your connection and try again."
          }
        />
      ) : (
        <FlatList<Invoice>
          data={q.data ?? []}
          keyExtractor={(inv) => String(inv.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => {
            const tint = statusTint(colors)[String(item.status ?? "").toLowerCase()] ?? colors.primary;
            return (
              <Pressable
                onPress={() => router.push(`/invoices/${item.id}` as never)}
                style={({ pressed }) => [
                  styles.row,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <View style={[styles.iconWrap, { backgroundColor: tint + "1c" }]}>
                  <Feather name="file-text" size={18} color={tint} />
                </View>
                <View style={{ flex: 1, gap: 2 }}>
                  <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                    {item.number ?? `Invoice #${item.id}`}
                  </Text>
                  <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                    {item.status ? String(item.status).toUpperCase() : "—"}
                    {item.due_at ? ` · due ${item.due_at.slice(0, 10)}` : ""}
                  </Text>
                </View>
                <Text style={[styles.amount, { color: colors.foreground }]} numberOfLines={1}>
                  {formatAmount(item)}
                </Text>
                <Feather name="chevron-right" size={16} color={colors.mutedForeground} />
              </Pressable>
            );
          }}
          ListEmptyComponent={
            <EmptyState
              icon="file-text"
              title="No invoices yet"
              body="When you bill a client or your subscription renews, the invoices will land here."
            />
          }
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  iconWrap: {
    width: 38,
    height: 38,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, letterSpacing: 0.4 },
  amount: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15 },
});
