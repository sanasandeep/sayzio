import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { router, Stack, useFocusEffect } from "expo-router";
import { useCallback, useMemo, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Platform,
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
import { useCooldown } from "@/hooks/useCooldown";
import {
  Contact,
  contactInitials,
  contactPrimaryPhone,
  fetchDuplicateCount,
  googleContacts,
  listContactTags,
  listContacts,
} from "@/lib/api/contacts";
import { useAuth } from "@/contexts/AuthContext";
import {
  getStoredContactSyncFingerprint,
  importDeviceContacts,
  setStoredContactSyncFingerprint,
} from "@/lib/deviceContacts";
import { showAlert } from "@/lib/webAlert";

/** Keep the duplicate count fresh-enough without hammering the API on focus. */
const DUPLICATE_COUNT_STALE_MS = 5 * 60 * 1000;

export default function ContactsScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { user } = useAuth();

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

  const duplicatesQ = useQuery({
    queryKey: ["contact-duplicate-count"],
    queryFn: fetchDuplicateCount,
    staleTime: DUPLICATE_COUNT_STALE_MS,
  });

  // Refetch on screen focus, but only once the 5-minute cache has gone stale
  // (react-query's refetch() would otherwise bypass staleTime entirely).
  useFocusEffect(
    useCallback(() => {
      if (duplicatesQ.isStale && !duplicatesQ.isFetching) {
        duplicatesQ.refetch();
      }
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [duplicatesQ.isStale, duplicatesQ.isFetching]),
  );

  const duplicateCount = duplicatesQ.data ?? 0;

  // Google Contacts sync status — only rendered when an account is connected.
  const googleQ = useQuery({
    queryKey: ["google-contacts-status"],
    queryFn: googleContacts.status,
    staleTime: 60_000,
  });
  const googleAccount = googleQ.data ?? null;
  const syncCooldown = useCooldown();

  const syncMutation = useMutation({
    mutationFn: googleContacts.sync,
    onSuccess: (r) => {
      qc.invalidateQueries({ queryKey: ["google-contacts-status"] });
      qc.invalidateQueries({ queryKey: ["contacts"] });
      qc.invalidateQueries({ queryKey: ["contact-duplicate-count"] });

      if (r.status === "in_progress") {
        showAlert(
          "Sync already running",
          "A sync is already in progress. Give it a moment and check back.",
        );
        return;
      }
      if (r.status === "throttled") {
        const secs = Math.max(1, Math.ceil(r.retry_after ?? 0));
        syncCooldown.start(secs);
        showAlert("Already up to date", `You synced very recently. Try again in ${secs}s.`);
        return;
      }
      const s = r.stats;
      if (!s) {
        showAlert("Sync complete", "Your contacts are up to date.");
        return;
      }
      showAlert(
        "Sync complete",
        `Created ${s.created}, updated ${s.updated}, deleted ${s.deleted}, pushed ${s.pushed}` +
          (s.skipped_capped ? `, ${s.skipped_capped} skipped (plan cap)` : "") +
          (s.errors ? `, ${s.errors} error(s)` : "") +
          ".",
      );
    },
    onError: (e: any) => {
      if (e?.code === "google_needs_reauth") {
        // Connection expired — refresh the status card so the reconnect
        // banner appears, and show the server's friendly message.
        qc.invalidateQueries({ queryKey: ["google-contacts-status"] });
        showAlert(
          "Reconnect Google Contacts",
          e?.message ??
            "Your Google Contacts connection expired — reconnect from the web app to resume syncing.",
        );
        return;
      }
      showAlert("Sync failed", e?.message ?? "Try again");
    },
  });

  // Device address-book import (expo-contacts). Hidden on web, where the
  // native contacts module isn't available.
  const importMutation = useMutation({
    mutationFn: async () =>
      importDeviceContacts({
        requestPermission: true,
        // Pass the last-synced fingerprint so an unchanged address book skips
        // the bulk POST entirely (the "unchanged" branch below explains it).
        unchangedFingerprint:
          user?.id != null ? await getStoredContactSyncFingerprint(user.id) : null,
      }),
    onSuccess: (out) => {
      // Remember what we just uploaded so the next silent auto-sync with an
      // unchanged address book skips its bulk POST (same per-user key the
      // auto-sync hook reads on cold start).
      if ("fingerprint" in out && user?.id != null) {
        void setStoredContactSyncFingerprint(user.id, out.fingerprint);
      }
      if (!out.ok) {
        if (out.reason === "unchanged") {
          showAlert(
            "Already up to date",
            "Your phone contacts haven't changed since the last import.",
          );
        } else if (out.reason === "unavailable") {
          showAlert("Not available", "Device contact import isn't available on this build.");
        } else if (out.reason === "denied") {
          showAlert("Permission needed", "Allow access to your contacts to import them.");
        } else {
          showAlert("Nothing to import", "No contacts with emails or phones were found.");
        }
        return;
      }
      const dupes = out.result.duplicates_found ?? 0;
      const summary = `Created ${out.result.created}, updated ${out.result.updated}, skipped ${out.result.skipped}.`;
      if (dupes > 0) {
        const dupeLine =
          dupes === 1
            ? "1 imported contact looks like a duplicate of an existing one."
            : `${dupes} imported contacts look like duplicates of existing ones.`;
        showAlert("Import complete", `${summary}\n\n${dupeLine}`, [
          { text: "Later", style: "cancel" },
          {
            text: "Review duplicates",
            onPress: () => router.push("/contact-duplicates" as any),
          },
        ]);
      } else {
        showAlert("Import complete", summary);
      }
      qc.invalidateQueries({ queryKey: ["contacts"] });
      qc.invalidateQueries({ queryKey: ["contact-duplicate-count"] });
    },
    onError: (e: any) => {
      showAlert("Import failed", e?.message ?? "Try again");
    },
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
            <View style={{ flexDirection: "row", alignItems: "center", gap: 14 }}>
              {Platform.OS !== "web" && (
                <Pressable
                  onPress={() => importMutation.mutate()}
                  disabled={importMutation.isPending}
                  hitSlop={8}
                  style={({ pressed }) => ({ opacity: pressed ? 0.6 : 1 })}
                  accessibilityLabel="Import from phone"
                >
                  {importMutation.isPending ? (
                    <ActivityIndicator color={colors.primary} size="small" />
                  ) : (
                    <Feather name="download" size={20} color={colors.primary} />
                  )}
                </Pressable>
              )}
              <Pressable
                onPress={() => router.push("/contacts/follow-ups" as any)}
                hitSlop={8}
                style={({ pressed }) => ({ opacity: pressed ? 0.6 : 1, marginRight: 4 })}
              >
                <Feather name="clock" size={20} color={colors.primary} />
              </Pressable>
            </View>
          ),
        }}
      />

      {googleAccount && (
        <View
          style={[
            styles.googleCard,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <View style={{ flex: 1, minWidth: 0 }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
              <Feather name="refresh-cw" size={13} color={colors.primary} />
              <Text
                numberOfLines={1}
                style={{
                  flex: 1,
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  fontSize: 13,
                  color: colors.foreground,
                }}
              >
                Google · {googleAccount.account_email ?? "Connected"}
              </Text>
            </View>
            <Text
              numberOfLines={1}
              style={{
                fontFamily: "SpaceGrotesk_400Regular",
                fontSize: 12,
                color:
                  googleAccount.last_sync_status === "error"
                    ? colors.destructive
                    : colors.mutedForeground,
                marginTop: 2,
              }}
            >
              {googleAccount.last_synced_at
                ? `Last synced ${new Date(googleAccount.last_synced_at).toLocaleString()} · ${
                    googleAccount.last_sync_status ?? "ok"
                  }`
                : "Not synced yet"}
            </Text>
            {googleAccount.needs_reauth ? (
              <View
                style={{
                  flexDirection: "row",
                  alignItems: "flex-start",
                  gap: 6,
                  marginTop: 6,
                  paddingVertical: 6,
                  paddingHorizontal: 8,
                  borderRadius: 8,
                  backgroundColor: colors.destructive + "18",
                }}
              >
                <Feather
                  name="alert-triangle"
                  size={13}
                  color={colors.destructive}
                  style={{ marginTop: 1 }}
                />
                <Text
                  style={{
                    flex: 1,
                    fontFamily: "SpaceGrotesk_500Medium",
                    fontSize: 11,
                    lineHeight: 15,
                    color: colors.destructive,
                  }}
                >
                  {googleAccount.reconnect_message ??
                    "Your Google Contacts connection expired — reconnect from the web app to resume syncing."}
                </Text>
              </View>
            ) : null}
            {!googleAccount.needs_reauth && googleAccount.last_sync_error ? (
              <Text
                numberOfLines={2}
                style={{
                  fontFamily: "SpaceGrotesk_400Regular",
                  fontSize: 11,
                  color: colors.destructive,
                  marginTop: 2,
                }}
              >
                {googleAccount.last_sync_error}
              </Text>
            ) : null}
          </View>
          <Pressable
            onPress={() => syncMutation.mutate()}
            disabled={syncMutation.isPending || syncCooldown.active}
            style={({ pressed }) => [
              styles.syncBtn,
              {
                backgroundColor: colors.primary,
                opacity:
                  syncMutation.isPending || syncCooldown.active
                    ? 0.5
                    : pressed
                      ? 0.75
                      : 1,
              },
            ]}
            accessibilityLabel="Sync Google contacts now"
          >
            {syncMutation.isPending ? (
              <ActivityIndicator color="#fff" size="small" />
            ) : (
              <Text
                style={{
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  fontSize: 12,
                  color: "#fff",
                }}
              >
                {syncCooldown.active ? `${syncCooldown.remaining}s` : "Sync now"}
              </Text>
            )}
          </Pressable>
        </View>
      )}

      {duplicateCount > 0 && (
        <Pressable
          onPress={() => router.push("/contact-duplicates" as any)}
          style={({ pressed }) => [
            styles.duplicateBanner,
            {
              backgroundColor: colors.primary + "14",
              borderColor: colors.primary + "40",
              opacity: pressed ? 0.7 : 1,
            },
          ]}
        >
          <View style={[styles.duplicateBadge, { backgroundColor: colors.primary }]}>
            <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 12, color: "#fff" }}>
              {duplicateCount > 99 ? "99+" : duplicateCount}
            </Text>
          </View>
          <Text
            style={{
              flex: 1,
              fontFamily: "SpaceGrotesk_500Medium",
              fontSize: 13,
              color: colors.foreground,
            }}
            numberOfLines={2}
          >
            {duplicateCount === 1
              ? "1 possible duplicate group found. Tap to review"
              : `${duplicateCount} possible duplicate groups found. Tap to review`}
          </Text>
          <Feather name="chevron-right" size={16} color={colors.primary} />
        </Pressable>
      )}

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
  googleCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginHorizontal: 16,
    marginTop: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 12,
    borderWidth: 1,
  },
  syncBtn: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
    minWidth: 76,
  },
  duplicateBanner: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginHorizontal: 16,
    marginTop: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 12,
    borderWidth: 1,
  },
  duplicateBadge: {
    minWidth: 24,
    height: 24,
    borderRadius: 12,
    paddingHorizontal: 6,
    alignItems: "center",
    justifyContent: "center",
  },
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
