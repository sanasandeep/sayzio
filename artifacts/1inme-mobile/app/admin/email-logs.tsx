import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { getEmailLogs, type EmailLogStatus } from "@/lib/api/email";

// Super-admin parity for the web "Email Log" page. Browse, search (recipient /
// subject) and filter (category, status) every outbound email, paginate, and
// tap a row to view it in full and resend it. Gated server-side behind
// `settings.manage`.

const STATUS_FILTERS: { value: string; label: string }[] = [
  { value: "", label: "All" },
  { value: "sent", label: "Sent" },
  { value: "failed", label: "Failed" },
  { value: "pending", label: "Pending" },
];

export function statusColor(status: EmailLogStatus, colors: ReturnType<typeof useColors>) {
  switch (status) {
    case "sent":
      return colors.success;
    case "failed":
      return colors.destructive;
    case "pending":
      return colors.warning;
    default:
      return colors.mutedForeground;
  }
}

export default function EmailLogsScreen() {
  const colors = useColors();
  const router = useRouter();

  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [category, setCategory] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);

  const query = useQuery({
    queryKey: ["admin-email-logs", q, category, status, page],
    queryFn: () => getEmailLogs({ q, category, status, page }),
  });

  const data = query.data;
  const categoryOptions = useMemo(() => {
    const entries = data ? Object.entries(data.categories) : [];
    return [{ value: "", label: "All categories" }, ...entries.map(([value, label]) => ({ value, label }))];
  }, [data]);

  const applySearch = () => {
    setQ(search.trim());
    setPage(1);
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Email log", headerBackTitle: "Back" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 48 }}>
        {/* Search + filters */}
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <TextField
            label="Search"
            value={search}
            onChangeText={setSearch}
            onSubmitEditing={applySearch}
            placeholder="Recipient or subject"
            autoCapitalize="none"
            autoCorrect={false}
            returnKeyType="search"
          />

          <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>Status</Text>
          <View style={styles.chipRow}>
            {STATUS_FILTERS.map((s) => {
              const on = status === s.value;
              return (
                <Pressable
                  key={s.value || "all"}
                  onPress={() => {
                    setStatus(s.value);
                    setPage(1);
                  }}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: on ? colors.primary : colors.background,
                      borderColor: on ? colors.primary : colors.border,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.chipText,
                      { color: on ? colors.primaryForeground : colors.mutedForeground },
                    ]}
                  >
                    {s.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          {categoryOptions.length > 1 ? (
            <>
              <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>Category</Text>
              <View style={styles.chipRow}>
                {categoryOptions.map((c) => {
                  const on = category === c.value;
                  return (
                    <Pressable
                      key={c.value || "all-cats"}
                      onPress={() => {
                        setCategory(c.value);
                        setPage(1);
                      }}
                      style={[
                        styles.chip,
                        {
                          backgroundColor: on ? colors.primary : colors.background,
                          borderColor: on ? colors.primary : colors.border,
                        },
                      ]}
                    >
                      <Text
                        style={[
                          styles.chipText,
                          { color: on ? colors.primaryForeground : colors.mutedForeground },
                        ]}
                      >
                        {c.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            </>
          ) : null}
        </View>

        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You need admin access to view the email log."
                : "Couldn't load the email log."}
            </Text>
          </View>
        ) : data && data.logs.length > 0 ? (
          <>
            <Text style={[styles.metaLine, { color: colors.mutedForeground }]}>
              {data.meta.total} {data.meta.total === 1 ? "email" : "emails"} · page{" "}
              {data.meta.current_page} of {data.meta.last_page}
            </Text>
            <View
              style={[
                styles.list,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              {data.logs.map((l, i) => (
                <Pressable
                  key={l.id}
                  onPress={() => router.push(`/admin/email-logs/${l.id}` as never)}
                  style={({ pressed }) => [
                    styles.listItem,
                    {
                      borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                      borderTopColor: colors.border,
                      opacity: pressed ? 0.7 : 1,
                    },
                  ]}
                >
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text style={[styles.subject, { color: colors.foreground }]} numberOfLines={1}>
                      {l.subject || "(no subject)"}
                    </Text>
                    <Text style={{ color: colors.mutedForeground, fontSize: 12 }} numberOfLines={1}>
                      {l.recipient}
                    </Text>
                    <View style={styles.metaRow}>
                      <View style={[styles.statusDot, { backgroundColor: statusColor(l.status, colors) }]} />
                      <Text style={{ color: statusColor(l.status, colors), fontSize: 11, fontFamily: "SpaceGrotesk_600SemiBold" }}>
                        {l.status}
                      </Text>
                      {l.is_resend ? (
                        <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>· resent</Text>
                      ) : null}
                      {l.created_at ? (
                        <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                          · {new Date(l.created_at).toLocaleDateString()}
                        </Text>
                      ) : null}
                    </View>
                  </View>
                  <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
                </Pressable>
              ))}
            </View>

            {/* Pagination */}
            <View style={styles.pager}>
              <Pressable
                disabled={data.meta.current_page <= 1}
                onPress={() => setPage((p) => Math.max(1, p - 1))}
                style={({ pressed }) => [
                  styles.pagerBtn,
                  {
                    borderColor: colors.border,
                    opacity: data.meta.current_page <= 1 ? 0.4 : pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name="chevron-left" size={16} color={colors.foreground} />
                <Text style={{ color: colors.foreground }}>Prev</Text>
              </Pressable>
              <Pressable
                disabled={data.meta.current_page >= data.meta.last_page}
                onPress={() => setPage((p) => p + 1)}
                style={({ pressed }) => [
                  styles.pagerBtn,
                  {
                    borderColor: colors.border,
                    opacity: data.meta.current_page >= data.meta.last_page ? 0.4 : pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Text style={{ color: colors.foreground }}>Next</Text>
                <Feather name="chevron-right" size={16} color={colors.foreground} />
              </Pressable>
            </View>
          </>
        ) : (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="inbox" size={20} color={colors.mutedForeground} />
            <Text style={{ color: colors.mutedForeground, marginTop: 6 }}>
              No emails match your filters.
            </Text>
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 8 },
  fieldLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: { paddingHorizontal: 12, paddingVertical: 7, borderRadius: 999, borderWidth: 1 },
  chipText: { fontSize: 12, fontFamily: "SpaceGrotesk_600SemiBold" },
  metaLine: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium" },
  list: { borderWidth: StyleSheet.hairlineWidth, overflow: "hidden" },
  listItem: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14 },
  subject: { fontSize: 14, fontFamily: "SpaceGrotesk_600SemiBold" },
  metaRow: { flexDirection: "row", alignItems: "center", gap: 6, marginTop: 2 },
  statusDot: { width: 7, height: 7, borderRadius: 999 },
  pager: { flexDirection: "row", justifyContent: "space-between", gap: 12 },
  pagerBtn: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    paddingVertical: 12,
    borderWidth: 1,
    borderRadius: 10,
  },
});
