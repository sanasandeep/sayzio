import { Feather } from "@expo/vector-icons";
import { useInfiniteQuery } from "@tanstack/react-query";
import { Stack } from "expo-router";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  getSchemaRepairAudits,
  type SchemaRepairAudit,
} from "@/lib/api/schemaHealth";

// Read-only parity for the web admin "Schema repair audit log". Lists past
// one-click schema repair runs — who ran each, when, which columns were
// added/backfilled per table, and any whole-missing tables it could not
// recreate. Gated server-side behind `settings.manage` (403 otherwise).
// Only schema metadata is shown, never row data.

function formatWhen(iso: string | null): string {
  if (!iso) return "Unknown time";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function AuditCard({ audit }: { audit: SchemaRepairAudit }) {
  const colors = useColors();
  const addedTables = Object.entries(audit.added);

  return (
    <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
      {/* When + actor */}
      <View style={styles.cardHead}>
        <View style={{ flex: 1 }}>
          <Text style={[styles.who, { color: colors.foreground }]}>
            {audit.actor_label}
          </Text>
          {audit.actor_email ? (
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              {audit.actor_email}
            </Text>
          ) : null}
        </View>
        <View style={{ alignItems: "flex-end" }}>
          <Text style={[styles.when, { color: colors.foreground }]}>
            {formatWhen(audit.created_at)}
          </Text>
          {audit.ip ? (
            <Text style={[styles.ip, { color: colors.mutedForeground }]}>
              {audit.ip}
            </Text>
          ) : null}
        </View>
      </View>

      {/* Columns added */}
      {audit.changed_schema ? (
        <View style={{ gap: 6 }}>
          <View style={[styles.badge, { backgroundColor: "#10b98122" }]}>
            <Text style={[styles.badgeText, { color: "#10b981" }]}>
              {audit.added_columns_count}{" "}
              {audit.added_columns_count === 1 ? "column" : "columns"} across{" "}
              {audit.added_tables_count}{" "}
              {audit.added_tables_count === 1 ? "table" : "tables"}
            </Text>
          </View>
          {addedTables.map(([table, cols]) => (
            <Text key={table} style={[styles.mono, { color: colors.mutedForeground }]}>
              {table} — {cols.join(", ")}
            </Text>
          ))}
        </View>
      ) : (
        <Text style={[styles.sub, { color: colors.mutedForeground }]}>
          No changes (already up to date)
        </Text>
      )}

      {/* Could not repair */}
      {audit.unrepairable_count > 0 ? (
        <View style={{ gap: 6 }}>
          <View style={[styles.badge, { backgroundColor: "#ef444422" }]}>
            <Text style={[styles.badgeText, { color: "#ef4444" }]}>
              Could not repair {audit.unrepairable_count}{" "}
              {audit.unrepairable_count === 1 ? "table" : "tables"}
            </Text>
          </View>
          {audit.unrepairable.map((table) => (
            <Text key={table} style={[styles.mono, { color: colors.mutedForeground }]}>
              {table}
            </Text>
          ))}
        </View>
      ) : null}
    </View>
  );
}

export default function RepairAuditsScreen() {
  const colors = useColors();

  const query = useInfiniteQuery({
    queryKey: ["schema-repair-audits"],
    queryFn: ({ pageParam }) => getSchemaRepairAudits(pageParam, 30),
    initialPageParam: 1,
    getNextPageParam: (last) =>
      last.meta.current_page < last.meta.last_page
        ? last.meta.current_page + 1
        : undefined,
  });

  const audits = query.data?.pages.flatMap((p) => p.audits) ?? [];
  const forbidden = (query.error as any)?.status === 403;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: "Schema repair log", headerBackTitle: "Back" }}
      />
      {query.isLoading ? (
        <ActivityIndicator color={colors.primary} style={{ marginTop: 32 }} />
      ) : query.isError ? (
        <View style={[styles.center]}>
          <Feather
            name={forbidden ? "lock" : "alert-triangle"}
            size={22}
            color={forbidden ? colors.mutedForeground : colors.destructive}
          />
          <Text style={{ color: colors.foreground, marginTop: 8, textAlign: "center" }}>
            {forbidden
              ? "You need admin access to view the schema repair log."
              : "Couldn't load the schema repair log."}
          </Text>
        </View>
      ) : (
        <FlatList
          data={audits}
          keyExtractor={(a) => String(a.id)}
          contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 48 }}
          ListHeaderComponent={
            <Text style={[styles.intro, { color: colors.mutedForeground }]}>
              Every run of the dashboard's one-click "Fix now" schema repair —
              who ran it, when, and exactly which columns were added per table.
              Only schema metadata is recorded, never row data.
            </Text>
          }
          renderItem={({ item }) => <AuditCard audit={item} />}
          ListEmptyComponent={
            <View style={[styles.center, { marginTop: 24 }]}>
              <Feather name="tool" size={24} color={colors.mutedForeground} />
              <Text style={{ color: colors.mutedForeground, marginTop: 8 }}>
                No schema repairs have been run yet.
              </Text>
            </View>
          }
          onEndReachedThreshold={0.4}
          onEndReached={() => {
            if (query.hasNextPage && !query.isFetchingNextPage) {
              query.fetchNextPage();
            }
          }}
          ListFooterComponent={
            query.isFetchingNextPage ? (
              <ActivityIndicator color={colors.primary} style={{ marginVertical: 16 }} />
            ) : null
          }
          refreshing={query.isRefetching && !query.isFetchingNextPage}
          onRefresh={() => query.refetch()}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  intro: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium", lineHeight: 18, marginBottom: 4 },
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 10 },
  cardHead: { flexDirection: "row", alignItems: "flex-start", gap: 10 },
  who: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 14 },
  sub: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium", marginTop: 2 },
  when: { fontSize: 12, fontFamily: "SpaceGrotesk_600SemiBold" },
  ip: { fontSize: 11, fontFamily: "SpaceGrotesk_500Medium", marginTop: 2 },
  badge: {
    alignSelf: "flex-start",
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 999,
  },
  badgeText: { fontSize: 11, fontFamily: "SpaceGrotesk_700Bold" },
  mono: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium" },
});
