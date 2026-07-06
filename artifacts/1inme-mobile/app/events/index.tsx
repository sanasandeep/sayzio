import { Feather } from "@expo/vector-icons";
import * as Location from "expo-location";
import { Image } from "expo-image";
import { router } from "expo-router";
import { useCallback, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { AppIcon } from "@/components/AppIcon";
import { EmptyState } from "@/components/EmptyState";
import { EventConnectionTips } from "@/components/EventConnectionTips";
import { useColors } from "@/hooks/useColors";
import {
  type EventConnectionTip,
  type EventCategoryOption,
  type EventItem,
  listEvents,
} from "@/lib/api/events";

export default function EventsDirectoryScreen() {
  const colors = useColors();
  const [q, setQ] = useState("");
  const [nearMe, setNearMe] = useState(false);
  const [category, setCategory] = useState<string | null>(null);
  const [categories, setCategories] = useState<EventCategoryOption[]>([]);
  const [connectionTips, setConnectionTips] = useState<EventConnectionTip[]>([]);
  const [events, setEvents] = useState<EventItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(
    async (opts: { near?: boolean; query?: string; category?: string | null } = {}) => {
      const near = opts.near ?? nearMe;
      const query = opts.query ?? q;
      const cat = opts.category !== undefined ? opts.category : category;
      let lat: number | undefined;
      let lng: number | undefined;
      if (near) {
        try {
          const { status } = await Location.requestForegroundPermissionsAsync();
          if (status === "granted") {
            const pos = await Location.getCurrentPositionAsync({});
            lat = pos.coords.latitude;
            lng = pos.coords.longitude;
          }
        } catch {
          // ignore — falls back to unfiltered directory
        }
      }
      const res = await listEvents({
        q: query || undefined,
        category: cat || undefined,
        lat,
        lng,
      });
      setEvents(res.items);
      // The curated category catalogue is stable, so only seed it once from
      // the first response — later filtered calls return the same list.
      setCategories((prev) => (prev.length > 0 ? prev : res.categories));
      // Same for the connection tips — static coaching copy, seed once.
      setConnectionTips((prev) =>
        prev.length > 0 ? prev : res.connection_tips ?? [],
      );
    },
    [nearMe, q, category],
  );

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    try {
      await load();
    } finally {
      setRefreshing(false);
    }
  }, [load]);

  const toggleNearMe = useCallback(async () => {
    const next = !nearMe;
    setNearMe(next);
    setLoading(true);
    try {
      await load({ near: next });
    } finally {
      setLoading(false);
    }
  }, [nearMe, load]);

  const runSearch = useCallback(async () => {
    setLoading(true);
    try {
      await load({ query: q });
    } finally {
      setLoading(false);
    }
  }, [load, q]);

  const toggleCategory = useCallback(
    async (slug: string) => {
      // Tapping the active chip clears the filter (parity with web).
      const next = category === slug ? null : slug;
      setCategory(next);
      setLoading(true);
      try {
        await load({ category: next });
      } finally {
        setLoading(false);
      }
    },
    [category, load],
  );

  useState(() => {
    load().finally(() => setLoading(false));
  });

  return (
    <View style={[styles.wrap, { backgroundColor: colors.background }]}>
      <View style={styles.searchRow}>
        <View
          style={[
            styles.searchBox,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <Feather name="search" size={16} color={colors.mutedForeground} />
          <TextInput
            value={q}
            onChangeText={setQ}
            onSubmitEditing={runSearch}
            placeholder="Search events, venues..."
            placeholderTextColor={colors.mutedForeground}
            style={[styles.searchInput, { color: colors.foreground }]}
            returnKeyType="search"
          />
        </View>
        <Pressable
          onPress={toggleNearMe}
          style={[
            styles.nearBtn,
            {
              backgroundColor: nearMe ? colors.primary : colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <Feather
            name="map-pin"
            size={16}
            color={nearMe ? colors.primaryForeground : colors.foreground}
          />
        </Pressable>
      </View>

      {categories.length > 0 ? (
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chipRow}
        >
          {categories.map((c) => {
            const active = category === c.slug;
            return (
              <Pressable
                key={c.slug}
                onPress={() => toggleCategory(c.slug)}
                style={[
                  styles.chip,
                  {
                    backgroundColor: active ? colors.primary : colors.card,
                    borderColor: active ? colors.primary : colors.border,
                  },
                ]}
              >
                <AppIcon
                  name={c.icon}
                  size={13}
                  color={active ? colors.primaryForeground : colors.foreground}
                />
                <Text
                  style={[
                    styles.chipLabel,
                    {
                      color: active ? colors.primaryForeground : colors.foreground,
                    },
                  ]}
                >
                  {c.label}
                </Text>
              </Pressable>
            );
          })}
        </ScrollView>
      ) : null}

      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={colors.primary} />
      ) : events.length === 0 ? (
        <EmptyState
          icon="calendar"
          title="No events found"
          body={
            category
              ? "No events in this category yet. Tap the category again to clear it."
              : "Try a different search or turn off the near-me filter."
          }
        />
      ) : (
        <FlatList
          data={events}
          keyExtractor={(e) => String(e.id)}
          contentContainerStyle={{ padding: 16, gap: 12 }}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }
          ListFooterComponent={
            connectionTips.length > 0 ? (
              <EventConnectionTips tips={connectionTips} />
            ) : null
          }
          renderItem={({ item }) => (
            <Pressable
              onPress={() => router.push(`/events/${item.alias}`)}
              style={[
                styles.card,
                { backgroundColor: colors.card, borderColor: colors.border },
              ]}
            >
              {item.cover_image_url ? (
                <Image
                  source={{ uri: item.cover_image_url }}
                  style={styles.cover}
                  contentFit="cover"
                />
              ) : null}
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                {item.title}
              </Text>
              {item.category_label ? (
                <View style={styles.categoryRow}>
                  <AppIcon
                    name={item.category_icon ?? "calendar"}
                    size={13}
                    color={colors.primary}
                  />
                  <Text
                    style={{ color: colors.primary, fontSize: 12, fontWeight: "600" }}
                  >
                    {item.category_label}
                  </Text>
                </View>
              ) : null}
              {item.start_date ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                  {new Date(item.start_date).toLocaleString()}
                </Text>
              ) : null}
              {item.location ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                  📍 {item.location}
                </Text>
              ) : null}
              {item.hashtags.length > 0 ? (
                <View style={styles.tagRow}>
                  {item.hashtags.slice(0, 4).map((tag) => (
                    <View
                      key={tag}
                      style={[styles.tagChip, { borderColor: colors.border }]}
                    >
                      <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                        #{tag}
                      </Text>
                    </View>
                  ))}
                </View>
              ) : null}
              <View style={styles.cardFooterRow}>
                {item.ticketing_enabled && item.tiers.length > 0 ? (
                  <Text style={{ color: colors.primary, fontWeight: "600" }}>
                    From {item.tiers[0]?.price_label}
                  </Text>
                ) : (
                  <Text style={{ color: colors.primary, fontWeight: "600" }}>
                    Free RSVP
                  </Text>
                )}
                {item.interested_count > 0 ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                    {item.interested_count} interested
                  </Text>
                ) : null}
              </View>
            </Pressable>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1 },
  searchRow: {
    flexDirection: "row",
    gap: 8,
    padding: 16,
    paddingBottom: 8,
  },
  searchBox: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    height: 44,
  },
  searchInput: { flex: 1, fontSize: 15 },
  chipRow: {
    flexDirection: "row",
    gap: 8,
    paddingHorizontal: 16,
    paddingBottom: 8,
  },
  chip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  chipLabel: { fontSize: 13, fontWeight: "600" },
  nearBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  card: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
    gap: 4,
    overflow: "hidden",
  },
  cover: {
    width: "100%",
    height: 120,
    borderRadius: 10,
    marginBottom: 6,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: "700",
  },
  categoryRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    marginTop: 2,
  },
  tagRow: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 4 },
  tagChip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 8,
    paddingVertical: 2,
  },
  cardFooterRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginTop: 4,
  },
});
