import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { router, Stack } from "expo-router";
import { useMemo, useState } from "react";
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

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  Contact,
  contactInitials,
  contactPrimaryPhone,
  listContactTags,
  listContacts,
} from "@/lib/api/contacts";

export default function ContactsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [search, setSearch] = useState("");
  const [activeTag, setActiveTag] = useState<string | null>(null);

  const contactsQ = useQuery({
    queryKey: ["contacts", search, activeTag],
    queryFn: () => listContacts({ q: search || undefined, tag: activeTag ?? undefined }),
    staleTime: 30_000,
  });

  const tagsQ = useQuery({
    queryKey: ["contact-tags"],
    queryFn: listContactTags,
    staleTime: 60_000,
  });

  const tags = tagsQ.data ?? [];

  function toggleTag(t: string) {
    setActiveTag((prev) => (prev === t ? null : t));
  }

  const contacts = contactsQ.data ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Contacts",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
          headerRight: () => (
            <View style={{ flexDirection: "row", gap: 8 }}>
              <Pressable
                onPress={() => router.push("/contacts/follow-ups" as any)}
                style={({ pressed }) => ({ opacity: pressed ? 0.6 : 1, marginRight: 4 })}
              >
                <Feather name="clock" size={20} color={colors.primary} />
              </Pressable>
            </View>
          ),
        }}
      />

      <View style={styles.searchRow}>
        <View style={[styles.searchBox, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Feather name="search" size={15} color={colors.mutedForeground} />
          <TextInput
            value={search}
            onChangeText={setSearch}
            placeholder="Search contacts…"
            placeholderTextColor={colors.mutedForeground}
            style={[styles.searchInput, { color: colors.foreground, fontFamily: "SpaceGrotesk_400Regular" }]}
            autoCapitalize="none"
            autoCorrect={false}
          />
          {search.length > 0 && (
            <Pressable onPress={() => setSearch("")}>
              <Feather name="x" size={14} color={colors.mutedForeground} />
            </Pressable>
          )}
        </View>
      </View>

      {tags.length > 0 && (
        <View style={{ paddingBottom: 8 }}>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ paddingHorizontal: 16, gap: 6, flexDirection: "row" }}
          >
            {tags.map((tag) => {
              const active = activeTag === tag;
              return (
                <Pressable
                  key={tag}
                  onPress={() => toggleTag(tag)}
                  style={[
                    styles.tagChip,
                    {
                      backgroundColor: active ? colors.primary + "20" : colors.card,
                      borderColor: active ? colors.primary + "50" : colors.border,
                    },
                  ]}
                >
                  <Feather name="tag" size={10} color={active ? colors.primary : colors.mutedForeground} />
                  <Text
                    style={{
                      fontFamily: "SpaceGrotesk_500Medium",
                      fontSize: 12,
                      color: active ? colors.primary : colors.mutedForeground,
                      marginLeft: 4,
                    }}
                  >
                    {tag}
                  </Text>
                  {active && (
                    <Feather name="x" size={10} color={colors.primary} style={{ marginLeft: 2 }} />
                  )}
                </Pressable>
              );
            })}
          </ScrollView>
        </View>
      )}

      {contactsQ.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : contacts.length === 0 ? (
        <EmptyState
          icon="users"
          title="No contacts found"
          body={activeTag ? `No contacts tagged "${activeTag}"` : search ? "Try a different search" : "Add contacts to get started"}
        />
      ) : (
        <FlatList
          data={contacts}
          keyExtractor={(c) => String(c.id)}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 32, paddingTop: 4 }}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          refreshControl={
            <RefreshControl
              refreshing={contactsQ.isRefetching}
              onRefresh={() => { qc.invalidateQueries({ queryKey: ["contacts"] }); }}
              tintColor={colors.primary}
            />
          }
          renderItem={({ item }) => <ContactRow c={item} colors={colors} onTagPress={toggleTag} />}
        />
      )}
    </View>
  );
}

function ContactRow({
  c,
  colors,
  onTagPress,
}: {
  c: Contact;
  colors: ReturnType<typeof useColors>;
  onTagPress: (t: string) => void;
}) {
  const initials = contactInitials(c);
  const sub = contactPrimaryPhone(c) ?? c.emails[0]?.value ?? "";

  return (
    <Pressable
      onPress={() => router.push(`/contacts/${c.id}` as any)}
      style={({ pressed }) => [
        styles.row,
        { backgroundColor: colors.card, borderColor: colors.border, opacity: pressed ? 0.75 : 1 },
      ]}
    >
      <View style={[styles.avatar, { backgroundColor: colors.primary + "22" }]}>
        {c.photo_url ? (
          // eslint-disable-next-line @typescript-eslint/no-var-requires
          <View style={styles.avatarImg} />
        ) : (
          <Text style={[styles.avatarText, { color: colors.primary, fontFamily: "SpaceGrotesk_700Bold" }]}>
            {initials}
          </Text>
        )}
      </View>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text
          numberOfLines={1}
          style={{ fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14, color: colors.foreground }}
        >
          {c.display_name}
        </Text>
        {sub ? (
          <Text
            numberOfLines={1}
            style={{ fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, color: colors.mutedForeground, marginTop: 1 }}
          >
            {sub}
          </Text>
        ) : null}
        {(c.tags?.length ?? 0) > 0 && (
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 4, marginTop: 5 }}>
            {(c.tags ?? []).slice(0, 3).map((tag) => (
              <Pressable
                key={tag}
                onPress={(e) => { e.stopPropagation?.(); onTagPress(tag); }}
                style={[styles.tagChip, { backgroundColor: colors.primary + "14", borderColor: colors.primary + "30" }]}
              >
                <Text style={{ fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, color: colors.primary }}>{tag}</Text>
              </Pressable>
            ))}
            {(c.tags?.length ?? 0) > 3 && (
              <Text style={{ fontSize: 11, color: colors.mutedForeground, alignSelf: "center" }}>
                +{(c.tags?.length ?? 0) - 3}
              </Text>
            )}
          </View>
        )}
      </View>
      {c.follow_up_at && (
        <View style={[styles.followUpBadge, { backgroundColor: colors.primary + "18" }]}>
          <Feather name="clock" size={11} color={colors.primary} />
        </View>
      )}
      <Feather name="chevron-right" size={14} color={colors.mutedForeground} style={{ marginLeft: 4 }} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  searchRow: {
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  searchBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderRadius: 12,
    borderWidth: 1,
    paddingHorizontal: 12,
    height: 40,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    paddingVertical: 0,
  },
  tagChip: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 20,
    borderWidth: 1,
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 12,
    borderRadius: 14,
    borderWidth: 1,
  },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
  },
  avatarImg: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: "transparent",
  },
  avatarText: {
    fontSize: 15,
  },
  followUpBadge: {
    width: 24,
    height: 24,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
  },
});
