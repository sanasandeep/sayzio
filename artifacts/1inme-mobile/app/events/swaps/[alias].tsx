import { Feather } from "@expo/vector-icons";
import { Image } from "expo-image";
import { router, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { showAlert } from "@/lib/webAlert";
import {
  type MyEventSwap,
  cancelContactExchange,
  listMyEventSwaps,
} from "@/lib/api/events";

/**
 * Task #5052 — "My swaps" screen: the viewer's own pending/accepted
 * contact-swap requests at an event. Pending requests the viewer sent can
 * be withdrawn; accepted swaps show when they were accepted. Mirrors the
 * web event page's "My swaps" panel.
 */
export default function MyEventSwapsScreen() {
  const { alias, title } = useLocalSearchParams<{
    alias: string;
    title?: string;
  }>();
  const colors = useColors();

  const [items, setItems] = useState<MyEventSwap[] | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(
    async (silent = false) => {
      if (!alias) return;
      if (!silent) setLoading(true);
      try {
        const res = await listMyEventSwaps(alias);
        setItems(res.items);
      } catch {
        // Keep whatever we already had; errors surface via empty state.
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [alias],
  );

  useEffect(() => {
    load();
  }, [load]);

  const handleWithdraw = useCallback(
    async (swap: MyEventSwap) => {
      setBusyId(swap.exchange_id);
      try {
        await cancelContactExchange(swap.exchange_id);
        setItems((prev) =>
          prev ? prev.filter((s) => s.exchange_id !== swap.exchange_id) : prev,
        );
      } catch (e: unknown) {
        const msg =
          e instanceof Error ? e.message : "Could not withdraw the request.";
        showAlert("Error", msg);
      } finally {
        setBusyId(null);
      }
    },
    [],
  );

  const renderSwap = useCallback(
    ({ item }: { item: MyEventSwap }) => {
      const busy = busyId === item.exchange_id;
      const other = item.other;
      const accepted = item.status === "accepted";
      const acceptedLabel =
        accepted && item.accepted_at
          ? `Accepted ${new Date(item.accepted_at).toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" })}`
          : accepted
            ? "Accepted"
            : item.sent_by_me
              ? "Pending: sent by you"
              : "Pending: awaiting you";

      return (
        <View
          style={[
            styles.row,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <View style={styles.avatar}>
            {other?.avatar_url ? (
              <Image
                source={{ uri: other.avatar_url }}
                style={styles.avatarImg}
                contentFit="cover"
              />
            ) : (
              <View
                style={[
                  styles.avatarPlaceholder,
                  { backgroundColor: colors.muted },
                ]}
              >
                <Text
                  style={[
                    styles.avatarInitial,
                    { color: colors.mutedForeground },
                  ]}
                >
                  {(other?.name ?? "?")[0].toUpperCase()}
                </Text>
              </View>
            )}
          </View>

          <View style={styles.info}>
            <Text
              style={[styles.name, { color: colors.foreground }]}
              numberOfLines={1}
            >
              {other?.name ?? "Attendee"}
              {other?.handle ? (
                <Text style={{ color: colors.mutedForeground }}>
                  {"  "}@{other.handle}
                </Text>
              ) : null}
            </Text>
            <View style={styles.statusRow}>
              {accepted ? (
                <Feather name="check" size={12} color={colors.primary} />
              ) : (
                <Feather
                  name="clock"
                  size={12}
                  color={colors.mutedForeground}
                />
              )}
              <Text
                style={[
                  styles.statusText,
                  {
                    color: accepted ? colors.primary : colors.mutedForeground,
                  },
                ]}
              >
                {acceptedLabel}
              </Text>
            </View>
          </View>

          {busy ? (
            <ActivityIndicator size="small" color={colors.primary} />
          ) : item.can_cancel ? (
            <Pressable
              style={[
                styles.withdrawBtn,
                { borderColor: colors.border, backgroundColor: colors.muted },
              ]}
              onPress={() => handleWithdraw(item)}
            >
              <Text
                style={[styles.withdrawText, { color: colors.foreground }]}
              >
                Withdraw
              </Text>
            </Pressable>
          ) : null}
        </View>
      );
    },
    [colors, busyId, handleWithdraw],
  );

  return (
    <View style={[styles.container, { backgroundColor: colors.background }]}>
      <View style={styles.header}>
        <Pressable style={styles.backBtn} onPress={() => router.back()}>
          <Feather name="arrow-left" size={22} color={colors.foreground} />
        </Pressable>
        <Text
          style={[styles.headerTitle, { color: colors.foreground }]}
          numberOfLines={1}
        >
          {title ? `My swaps at ${title}` : "My swaps"}
        </Text>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} size="large" />
        </View>
      ) : (
        <FlatList
          data={items ?? []}
          keyExtractor={(s) => String(s.exchange_id)}
          renderItem={renderSwap}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true);
                load(true);
              }}
              tintColor={colors.primary}
            />
          }
          contentContainerStyle={styles.list}
          ListEmptyComponent={
            <View style={styles.center}>
              <Feather
                name="repeat"
                size={40}
                color={colors.mutedForeground}
              />
              <Text style={[styles.emptyTitle, { color: colors.foreground }]}>
                No swaps yet
              </Text>
              <Text
                style={[styles.emptyBody, { color: colors.mutedForeground }]}
              >
                Contact-swap requests you send or receive at this event will
                appear here so you can review or withdraw them.
              </Text>
            </View>
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 16,
    paddingTop: 52,
    paddingBottom: 12,
    gap: 8,
  },
  backBtn: {
    padding: 6,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: "700",
    flex: 1,
  },
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 32,
    paddingVertical: 48,
    gap: 12,
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: "600",
    textAlign: "center",
    marginTop: 8,
  },
  emptyBody: {
    fontSize: 14,
    textAlign: "center",
    lineHeight: 20,
  },
  list: {
    paddingHorizontal: 16,
    paddingBottom: 32,
    gap: 10,
    flexGrow: 1,
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
    gap: 12,
  },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    overflow: "hidden",
  },
  avatarImg: {
    width: 44,
    height: 44,
  },
  avatarPlaceholder: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: "center",
    justifyContent: "center",
  },
  avatarInitial: {
    fontSize: 18,
    fontWeight: "700",
  },
  info: {
    flex: 1,
    gap: 4,
  },
  name: {
    fontSize: 15,
    fontWeight: "600",
  },
  statusRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
  },
  statusText: {
    fontSize: 12,
    fontWeight: "500",
  },
  withdrawBtn: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 8,
    borderWidth: 1,
  },
  withdrawText: {
    fontSize: 13,
    fontWeight: "600",
  },
});
