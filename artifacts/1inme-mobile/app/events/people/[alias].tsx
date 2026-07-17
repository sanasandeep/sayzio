import { Feather } from "@expo/vector-icons";
import { Image } from "expo-image";
import * as Location from "expo-location";
import { router, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { showAlert } from "@/lib/webAlert";
import {
  type EventAttendee,
  type AttendeesResponse,
  type DiscoverabilityState,
  acceptContactExchange,
  getDiscoverability,
  listEventAttendees,
  requestContactExchange,
  toggleDiscoverability,
} from "@/lib/api/events";

/**
 * Task #5008 — "People at this event" screen. Shows opted-in attendees,
 * lets the viewer toggle their own discoverability, and send/accept exchange
 * requests. Mutual contacts are created server-side on acceptance.
 */
export default function PeopleAtEventScreen() {
  const { alias, title } = useLocalSearchParams<{
    alias: string;
    title?: string;
  }>();
  const colors = useColors();

  const [state, setState] = useState<DiscoverabilityState | null>(null);
  const [data, setData] = useState<AttendeesResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [toggling, setToggling] = useState(false);
  const [actionBusy, setActionBusy] = useState<number | null>(null);

  const loadAll = useCallback(async (silent = false) => {
    if (!alias) return;
    if (!silent) setLoading(true);
    try {
      const disc = await getDiscoverability(alias);
      setState(disc);
      if (disc.is_attendee && disc.event_live) {
        const attendees = await listEventAttendees(alias);
        setData(attendees);
      } else {
        setData(null);
      }
    } catch {
      // Keep whatever we already had; errors surface via empty state.
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [alias]);

  useEffect(() => {
    loadAll();
  }, [loadAll]);

  const handleToggle = useCallback(
    async (value: boolean) => {
      if (!alias || !state) return;
      setToggling(true);
      try {
        let coords: { lat: number; lng: number } | undefined;
        try {
          const { status } = await Location.requestForegroundPermissionsAsync();
          if (status === "granted") {
            const pos = await Location.getCurrentPositionAsync({});
            coords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
          }
        } catch {
          // coords are optional — just continue without them
        }
        await toggleDiscoverability(alias, value, coords);
        setState((prev) => (prev ? { ...prev, discoverable: value } : prev));
        if (value) {
          // Reload the list now that we're opted in.
          const attendees = await listEventAttendees(alias);
          setData(attendees);
        }
      } catch (e: unknown) {
        const msg =
          e instanceof Error ? e.message : "Could not update your status.";
        showAlert("Error", msg);
      } finally {
        setToggling(false);
      }
    },
    [alias, state],
  );

  const handleRequest = useCallback(
    async (attendee: EventAttendee) => {
      if (!alias) return;
      setActionBusy(attendee.user.id);
      try {
        const result = await requestContactExchange(alias, attendee.user.id);
        setData((prev) => {
          if (!prev) return prev;
          return {
            ...prev,
            items: prev.items.map((a) =>
              a.user.id === attendee.user.id
                ? {
                    ...a,
                    exchange_status: result.status,
                    exchange_id: result.exchange_id,
                    sent_by_me: true,
                  }
                : a,
            ),
          };
        });
        if (result.status === "accepted") {
          showAlert(
            "Contacts exchanged!",
            `You and ${attendee.user.name ?? "this person"} are now in each other's contacts.`,
          );
        }
      } catch (e: unknown) {
        const msg =
          e instanceof Error ? e.message : "Could not send request.";
        showAlert("Error", msg);
      } finally {
        setActionBusy(null);
      }
    },
    [alias],
  );

  const handleAccept = useCallback(async (attendee: EventAttendee) => {
    if (!attendee.exchange_id) return;
    setActionBusy(attendee.user.id);
    try {
      await acceptContactExchange(attendee.exchange_id);
      setData((prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          items: prev.items.map((a) =>
            a.user.id === attendee.user.id
              ? { ...a, exchange_status: "accepted" }
              : a,
          ),
        };
      });
      showAlert(
        "Contacts exchanged!",
        `You and ${attendee.user.name ?? "this person"} are now in each other's contacts.`,
      );
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : "Could not accept.";
      showAlert("Error", msg);
    } finally {
      setActionBusy(null);
    }
  }, []);

  const renderAttendee = useCallback(
    ({ item }: { item: EventAttendee }) => {
      const busy = actionBusy === item.user.id;
      const status = item.exchange_status;
      const accepted = status === "accepted";
      const pendingInbound =
        status === "pending" && item.sent_by_me === false;
      const pendingOutbound =
        status === "pending" && item.sent_by_me === true;

      return (
        <View
          style={[
            styles.attendeeRow,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <View style={styles.avatar}>
            {item.user.avatar_url ? (
              <Image
                source={{ uri: item.user.avatar_url }}
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
                  {(item.user.name ?? "?")[0].toUpperCase()}
                </Text>
              </View>
            )}
          </View>

          <View style={styles.attendeeInfo}>
            <Text
              style={[styles.attendeeName, { color: colors.foreground }]}
              numberOfLines={1}
            >
              {item.user.name ?? "Attendee"}
            </Text>
            {item.user.handle ? (
              <Text
                style={[
                  styles.attendeeHandle,
                  { color: colors.mutedForeground },
                ]}
                numberOfLines={1}
              >
                @{item.user.handle}
              </Text>
            ) : null}
            {item.user.bio ? (
              <Text
                style={[styles.attendeeBio, { color: colors.mutedForeground }]}
                numberOfLines={2}
              >
                {item.user.bio}
              </Text>
            ) : null}
          </View>

          <View style={styles.action}>
            {busy ? (
              <ActivityIndicator size="small" color={colors.primary} />
            ) : accepted ? (
              <View
                style={[
                  styles.badge,
                  { backgroundColor: colors.muted },
                ]}
              >
                <Feather
                  name="check"
                  size={12}
                  color={colors.primary}
                />
                <Text
                  style={[styles.badgeText, { color: colors.primary }]}
                >
                  Connected
                </Text>
              </View>
            ) : pendingInbound ? (
              <Pressable
                style={[
                  styles.actionBtn,
                  { backgroundColor: colors.primary },
                ]}
                onPress={() => handleAccept(item)}
              >
                <Text style={styles.actionBtnText}>Accept</Text>
              </Pressable>
            ) : pendingOutbound ? (
              <View
                style={[
                  styles.badge,
                  { backgroundColor: colors.muted },
                ]}
              >
                <Text
                  style={[
                    styles.badgeText,
                    { color: colors.mutedForeground },
                  ]}
                >
                  Sent
                </Text>
              </View>
            ) : (
              <Pressable
                style={[
                  styles.actionBtn,
                  { backgroundColor: colors.primary },
                ]}
                onPress={() => handleRequest(item)}
              >
                <Text style={styles.actionBtnText}>Exchange</Text>
              </Pressable>
            )}
          </View>
        </View>
      );
    },
    [colors, actionBusy, handleRequest, handleAccept],
  );

  const isAttendee = state?.is_attendee ?? false;
  const isLive = state?.event_live ?? false;
  const myDiscoverable = data?.my_discoverable ?? state?.discoverable ?? false;

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
          {title ? `People at ${title}` : "People at this event"}
        </Text>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} size="large" />
        </View>
      ) : !isLive ? (
        <View style={styles.center}>
          <Feather name="clock" size={40} color={colors.mutedForeground} />
          <Text
            style={[styles.emptyTitle, { color: colors.foreground }]}
          >
            Event not live yet
          </Text>
          <Text
            style={[styles.emptyBody, { color: colors.mutedForeground }]}
          >
            The "People at this event" feature is only available while the
            event is happening.
          </Text>
        </View>
      ) : !isAttendee ? (
        <View style={styles.center}>
          <Feather name="user-x" size={40} color={colors.mutedForeground} />
          <Text
            style={[styles.emptyTitle, { color: colors.foreground }]}
          >
            RSVP required
          </Text>
          <Text
            style={[styles.emptyBody, { color: colors.mutedForeground }]}
          >
            You need an RSVP or ticket for this event to see other attendees.
          </Text>
        </View>
      ) : (
        <>
          {/* Discoverability toggle */}
          <View
            style={[
              styles.toggleCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
            ]}
          >
            <View style={styles.toggleLeft}>
              <Text
                style={[styles.toggleLabel, { color: colors.foreground }]}
              >
                Make me discoverable
              </Text>
              <Text
                style={[
                  styles.toggleSub,
                  { color: colors.mutedForeground },
                ]}
              >
                {myDiscoverable
                  ? "Other attendees can see you and send exchange requests."
                  : "Hidden from other attendees. Enable to connect with people here."}
              </Text>
            </View>
            <Switch
              value={myDiscoverable}
              onValueChange={handleToggle}
              disabled={toggling}
              trackColor={{ true: colors.primary, false: colors.border }}
            />
          </View>

          <FlatList
            data={data?.items ?? []}
            keyExtractor={(a) => String(a.user.id)}
            renderItem={renderAttendee}
            refreshControl={
              <RefreshControl
                refreshing={refreshing}
                onRefresh={() => {
                  setRefreshing(true);
                  loadAll(true);
                }}
                tintColor={colors.primary}
              />
            }
            contentContainerStyle={styles.list}
            ListEmptyComponent={
              <View style={styles.center}>
                <Feather
                  name="users"
                  size={40}
                  color={colors.mutedForeground}
                />
                <Text
                  style={[styles.emptyTitle, { color: colors.foreground }]}
                >
                  No one here yet
                </Text>
                <Text
                  style={[
                    styles.emptyBody,
                    { color: colors.mutedForeground },
                  ]}
                >
                  Enable discoverability above — when other attendees do the
                  same, they&apos;ll appear here.
                </Text>
              </View>
            }
          />
        </>
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
  toggleCard: {
    flexDirection: "row",
    alignItems: "center",
    marginHorizontal: 16,
    marginBottom: 12,
    padding: 16,
    borderRadius: 12,
    borderWidth: 1,
    gap: 12,
  },
  toggleLeft: {
    flex: 1,
    gap: 4,
  },
  toggleLabel: {
    fontSize: 15,
    fontWeight: "600",
  },
  toggleSub: {
    fontSize: 13,
    lineHeight: 18,
  },
  list: {
    paddingHorizontal: 16,
    paddingBottom: 32,
    gap: 10,
  },
  attendeeRow: {
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
  attendeeInfo: {
    flex: 1,
    gap: 2,
  },
  attendeeName: {
    fontSize: 15,
    fontWeight: "600",
  },
  attendeeHandle: {
    fontSize: 13,
  },
  attendeeBio: {
    fontSize: 12,
    lineHeight: 16,
    marginTop: 2,
  },
  action: {
    alignItems: "center",
    justifyContent: "center",
    minWidth: 80,
  },
  actionBtn: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 8,
  },
  actionBtnText: {
    color: "#fff",
    fontSize: 13,
    fontWeight: "600",
  },
  badge: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 8,
    gap: 4,
  },
  badgeText: {
    fontSize: 12,
    fontWeight: "600",
  },
});
