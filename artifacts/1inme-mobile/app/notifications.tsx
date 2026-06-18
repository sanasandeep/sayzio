import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, router } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Animated,
  FlatList,
  Linking,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import {
  deleteNotification,
  listNotifications,
  markAllRead,
  markRead,
  restoreNotification,
  type Notification,
} from "@/lib/api/notifications";

// Translate a notification's web target into a native Expo Router route when an
// equivalent screen exists. Returns null when there's no native counterpart, so
// the caller falls back to the in-app browser. Matching is done on the path
// (host/query/hash stripped) since the URL is the canonical target.
function nativeRouteFor(target: string): string | null {
  let path = target;
  try {
    path = /^https?:\/\//i.test(target)
      ? new URL(target).pathname
      : target.split(/[?#]/)[0];
  } catch {
    path = target.split(/[?#]/)[0];
  }
  if (!path.startsWith("/")) path = `/${path}`;

  // Public creator profile: /@handle (may carry post/roadmap hashes).
  const profile = path.match(/^\/@([A-Za-z0-9_]+)/);
  if (profile) return `/profile/${profile[1]}`;

  // In-app dashboard areas that have a native screen.
  if (path.startsWith("/user/team")) return "/team";
  if (path.startsWith("/user/posts")) return "/posts";
  if (path.startsWith("/user/social-accounts")) return "/social";
  if (path.startsWith("/user/domains")) return "/domains";

  return null;
}

export default function NotificationsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["notifications"],
    queryFn: listNotifications,
  });

  const markAll = useMutation({
    mutationFn: markAllRead,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["notifications"] }),
  });

  const markOne = useMutation({
    mutationFn: (id: number) => markRead(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["notifications"] }),
  });

  const [undoId, setUndoId] = useState<number | null>(null);
  const toastOpacity = useRef(new Animated.Value(0)).current;
  const hideTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const showUndoToast = (id: number) => {
    if (hideTimer.current) clearTimeout(hideTimer.current);
    setUndoId(id);
    Animated.timing(toastOpacity, {
      toValue: 1,
      duration: 180,
      useNativeDriver: true,
    }).start();
    hideTimer.current = setTimeout(() => hideUndoToast(), 5000);
  };

  const hideUndoToast = () => {
    if (hideTimer.current) clearTimeout(hideTimer.current);
    Animated.timing(toastOpacity, {
      toValue: 0,
      duration: 180,
      useNativeDriver: true,
    }).start(() => setUndoId(null));
  };

  useEffect(() => {
    return () => {
      if (hideTimer.current) clearTimeout(hideTimer.current);
    };
  }, []);

  // Tapping a row opens the thing it's about (when it carries a target URL)
  // and marks it read in the same gesture — mirroring the web feed. When the
  // target maps to a real native screen we route there for a polished, native
  // feel; otherwise we resolve the URL against the API host and hand off to the
  // in-app browser (falling back to the OS handler).
  const openNotification = (item: Notification) => {
    if (!item.read_at) markOne.mutate(item.id);
    const target = item.url;
    if (!target) return;

    const native = nativeRouteFor(target);
    if (native) {
      router.push(native as never);
      return;
    }

    const absolute = /^https?:\/\//i.test(target)
      ? target
      : `${getBaseUrl()}${target.startsWith("/") ? "" : "/"}${target}`;
    WebBrowser.openBrowserAsync(absolute).catch(() => {
      Linking.openURL(absolute).catch(() => {});
    });
  };

  const removeOne = useMutation({
    mutationFn: (id: number) => deleteNotification(id),
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: ["notifications"] });
      showUndoToast(id);
    },
  });

  const restoreOne = useMutation({
    mutationFn: (id: number) => restoreNotification(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["notifications"] });
      hideUndoToast();
    },
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Notifications",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
          headerRight: () => (
            <View style={{ flexDirection: "row", alignItems: "center", gap: 14, paddingRight: 12 }}>
              {(q.data?.unreadCount ?? 0) > 0 ? (
                <Pressable onPress={() => markAll.mutate()} hitSlop={8}>
                  <Text
                    style={{
                      color: colors.primary,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                      fontSize: 13,
                    }}
                  >
                    Mark all
                  </Text>
                </Pressable>
              ) : null}
              <Pressable
                onPress={() => router.push("/api-usage")}
                hitSlop={8}
              >
                <Feather name="activity" size={18} color={colors.primary} />
              </Pressable>
              <Pressable
                onPress={() => router.push("/notification-preferences")}
                hitSlop={8}
              >
                <Feather name="settings" size={18} color={colors.primary} />
              </Pressable>
            </View>
          ),
        }}
      />

      {q.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={q.data?.items ?? []}
          keyExtractor={(n) => String(n.id)}
          contentContainerStyle={{ padding: 20, gap: 8 }}
          renderItem={({ item }) => {
            const unread = !item.read_at;
            return (
              <Pressable
                onPress={() => openNotification(item)}
                style={[
                  styles.row,
                  {
                    backgroundColor: colors.card,
                    borderColor: unread ? colors.primary : colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View
                  style={[
                    styles.iconWrap,
                    { backgroundColor: colors.primary + "1c" },
                  ]}
                >
                  <Feather name="bell" size={16} color={colors.primary} />
                </View>
                <View style={{ flex: 1, gap: 2 }}>
                  <Text
                    numberOfLines={1}
                    style={[styles.rowTitle, { color: colors.foreground }]}
                  >
                    {item.title || item.type || "Notification"}
                  </Text>
                  {item.body ? (
                    <Text
                      numberOfLines={2}
                      style={[
                        styles.rowBody,
                        { color: colors.mutedForeground },
                      ]}
                    >
                      {item.body}
                    </Text>
                  ) : null}
                </View>
                {unread ? (
                  <View
                    style={[
                      styles.unreadDot,
                      { backgroundColor: colors.primary },
                    ]}
                  />
                ) : null}
                <Pressable
                  onPress={() => removeOne.mutate(item.id)}
                  hitSlop={8}
                  style={styles.dismissBtn}
                >
                  <Feather name="x" size={16} color={colors.mutedForeground} />
                </Pressable>
              </Pressable>
            );
          }}
          ListEmptyComponent={
            <EmptyState
              icon="bell"
              title="No notifications yet"
              body="Updates about your links and followers appear here."
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

      {undoId !== null ? (
        <Animated.View
          pointerEvents="box-none"
          style={[styles.toastWrap, { opacity: toastOpacity }]}
        >
          <View style={[styles.toast, { backgroundColor: colors.foreground }]}>
            <Text
              style={[styles.toastText, { color: colors.background }]}
              numberOfLines={1}
            >
              Notification removed
            </Text>
            <Pressable
              onPress={() => undoId !== null && restoreOne.mutate(undoId)}
              hitSlop={8}
              disabled={restoreOne.isPending}
            >
              <Text style={[styles.toastAction, { color: colors.primary }]}>
                {restoreOne.isPending ? "…" : "Undo"}
              </Text>
            </Pressable>
          </View>
        </Animated.View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  iconWrap: {
    width: 36,
    height: 36,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  rowTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  rowBody: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, lineHeight: 16 },
  unreadDot: { width: 8, height: 8, borderRadius: 999 },
  dismissBtn: { padding: 4 },
  toastWrap: {
    position: "absolute",
    left: 16,
    right: 16,
    bottom: 24,
  },
  toast: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 12,
    shadowColor: "#000",
    shadowOpacity: 0.2,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 6,
  },
  toastText: { flex: 1, fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  toastAction: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
});
