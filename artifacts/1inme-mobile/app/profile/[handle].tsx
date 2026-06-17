import { Feather } from "@expo/vector-icons";
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { Image } from "expo-image";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Linking,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { PostCard } from "@/components/CreatorFeed";
import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { creatorProfile } from "@/lib/api/creatorProfile";
import { follow, unfollow } from "@/lib/api/follows";

export default function CreatorProfileScreen() {
  const { handle: rawHandle } = useLocalSearchParams<{ handle: string }>();
  const handle = (Array.isArray(rawHandle) ? rawHandle[0] : rawHandle ?? "")
    .replace(/^@/, "")
    .toLowerCase();
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const profileQ = useQuery({
    queryKey: ["creator-profile", handle],
    queryFn: () => creatorProfile.show(handle),
    enabled: !!handle,
  });

  const feedQ = useInfiniteQuery({
    queryKey: ["creator-profile-feed", handle],
    queryFn: ({ pageParam = 1 }) => creatorProfile.feed(handle, pageParam),
    getNextPageParam: (last) =>
      last.meta.current_page < last.meta.last_page
        ? last.meta.current_page + 1
        : undefined,
    initialPageParam: 1,
    enabled: !!handle && !!profileQ.data,
  });

  const followM = useMutation({
    mutationFn: async (next: boolean) => {
      if (!profileQ.data) return;
      if (next) await follow(profileQ.data.profile.id);
      else await unfollow(profileQ.data.profile.id);
    },
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: ["creator-profile", handle] }),
    onError: (e: Error) =>
      Alert.alert("Couldn't update follow", e.message || "Try again."),
  });

  if (profileQ.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: `@${handle}` }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (profileQ.isError || !profileQ.data) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Not found" }} />
        <Feather name="user-x" size={36} color={colors.mutedForeground} />
        <Text style={{ color: colors.foreground, marginTop: 10 }}>
          That creator profile isn't available.
        </Text>
        <Pressable onPress={() => router.back()} style={{ marginTop: 14 }}>
          <Text style={{ color: colors.primary, fontWeight: "700" }}>
            Go back
          </Text>
        </Pressable>
      </View>
    );
  }

  const { profile, reactions_catalog } = profileQ.data;
  const sections = profile.sections;
  const posts = feedQ.data?.pages.flatMap((p) => p.items) ?? [];

  const initials = profile.name
    .trim()
    .split(/\s+/)
    .map((s) => s[0])
    .filter(Boolean)
    .slice(0, 2)
    .join("")
    .toUpperCase();

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: profile.handle ? `@${profile.handle}` : profile.name,
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
        }}
      />
      <FlatList
        data={posts}
        keyExtractor={(p) => String(p.id)}
        contentContainerStyle={{ paddingBottom: 36 }}
        refreshControl={
          <RefreshControl
            refreshing={profileQ.isFetching || feedQ.isFetching}
            onRefresh={() => {
              profileQ.refetch();
              feedQ.refetch();
            }}
            tintColor={colors.primary}
          />
        }
        onEndReachedThreshold={0.4}
        onEndReached={() => {
          if (feedQ.hasNextPage && !feedQ.isFetchingNextPage)
            feedQ.fetchNextPage();
        }}
        ListHeaderComponent={
          <View>
            {/* Cover */}
            <View style={styles.cover}>
              {profile.cover_image ? (
                <Image
                  source={{ uri: profile.cover_image }}
                  style={StyleSheet.absoluteFill}
                  contentFit="cover"
                />
              ) : (
                <View
                  style={[
                    StyleSheet.absoluteFill,
                    { backgroundColor: colors.primary },
                  ]}
                />
              )}
            </View>

            {/* Hero */}
            <View
              style={[
                styles.heroCard,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <View style={styles.avatarWrap}>
                {profile.avatar ? (
                  <Image
                    source={{ uri: profile.avatar }}
                    style={[
                      styles.avatar,
                      { borderColor: colors.card },
                    ]}
                    contentFit="cover"
                  />
                ) : (
                  <View
                    style={[
                      styles.avatar,
                      styles.avatarFallback,
                      {
                        backgroundColor: colors.primary,
                        borderColor: colors.card,
                      },
                    ]}
                  >
                    <Text style={styles.avatarInitials}>{initials || "?"}</Text>
                  </View>
                )}
              </View>

              <Text style={[styles.name, { color: colors.foreground }]}>
                {profile.name}
              </Text>
              {profile.handle ? (
                <Text style={[styles.handleLine, { color: colors.mutedForeground }]}>
                  @{profile.handle}
                  {profile.location ? `  ·  ${profile.location}` : ""}
                </Text>
              ) : null}
              {profile.tagline ? (
                <Text
                  style={[styles.tagline, { color: colors.foreground }]}
                  numberOfLines={3}
                >
                  {profile.tagline}
                </Text>
              ) : null}

              {sections.stats ? (
                <View style={styles.statRow}>
                  <Stat
                    label="Followers"
                    value={profile.followers_count}
                    color={colors.foreground}
                    muted={colors.mutedForeground}
                  />
                  <View
                    style={[styles.statSep, { backgroundColor: colors.border }]}
                  />
                  <Stat
                    label="Posts"
                    value={profile.posts_count}
                    color={colors.foreground}
                    muted={colors.mutedForeground}
                  />
                </View>
              ) : null}

              {!profile.is_owner ? (
                <View style={{ marginTop: 14, gap: 8 }}>
                  <Button
                    label={profile.is_following ? "Following" : "Follow"}
                    onPress={() => followM.mutate(!profile.is_following)}
                    variant={profile.is_following ? "secondary" : "primary"}
                    loading={followM.isPending}
                    style={{ alignSelf: "stretch" }}
                  />
                  {/* Subscribe & Tip CTAs (Task #1209) — only render
                      when the creator has a payout connection wired
                      up; the Subscribe screen handles the empty
                      tier-list case gracefully. */}
                  <View style={{ flexDirection: "row", gap: 8 }}>
                    <View style={{ flex: 1 }}>
                      <Button
                        label={
                          profileQ.data?.viewer_subscription?.is_current
                            ? `${profileQ.data.viewer_subscription.tier_badge ?? "✓"} Subscribed`
                            : "Subscribe"
                        }
                        variant={
                          profileQ.data?.viewer_subscription?.is_current ? "secondary" : "primary"
                        }
                        onPress={() =>
                          router.push(
                            profileQ.data?.viewer_subscription?.is_current
                              ? { pathname: "/monetization/manage", params: { handle: profile.handle ?? "" } }
                              : { pathname: "/monetization/subscribe", params: { handle: profile.handle ?? "" } },
                          )
                        }
                      />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Button
                        label="💖 Tip"
                        variant="outline"
                        onPress={() =>
                          router.push({
                            pathname: "/monetization/tip",
                            params: { handle: profile.handle ?? "", name: profile.name },
                          })
                        }
                      />
                    </View>
                  </View>
                  {/* Paid DMs (Task #1210): Message button. */}
                  <View style={{ marginTop: 8 }}>
                    <Button
                      label="✉️ Message"
                      variant="secondary"
                      onPress={() =>
                        router.push({
                          pathname: "/dm/[handle]",
                          params: { handle: profile.handle ?? "" },
                        })
                      }
                    />
                  </View>
                </View>
              ) : (
                <Button
                  label="Edit profile"
                  variant="secondary"
                  onPress={() => router.push("/profile-edit")}
                  style={{ marginTop: 14, alignSelf: "stretch" }}
                />
              )}
            </View>

            {/* About */}
            {sections.about && (profile.bio || profile.niche_tags.length) ? (
              <SectionCard title="About" colors={colors}>
                {profile.bio ? (
                  <Text style={{ color: colors.foreground, lineHeight: 20 }}>
                    {profile.bio}
                  </Text>
                ) : null}
                {profile.niche_tags.length ? (
                  <View style={styles.tagRow}>
                    {profile.niche_tags.map((tag) => (
                      <View
                        key={tag}
                        style={[
                          styles.tag,
                          {
                            backgroundColor: colors.background,
                            borderColor: colors.border,
                          },
                        ]}
                      >
                        <Text style={{ color: colors.foreground, fontSize: 12 }}>
                          #{tag}
                        </Text>
                      </View>
                    ))}
                  </View>
                ) : null}
              </SectionCard>
            ) : null}

            {/* Socials */}
            {sections.socials && Object.keys(profile.socials).length ? (
              <SectionCard title="Find me online" colors={colors}>
                <View style={{ gap: 8 }}>
                  {Object.entries(profile.socials).map(([k, v]) => (
                    <Pressable
                      key={k}
                      onPress={() => v && Linking.openURL(v)}
                      style={[
                        styles.socialRow,
                        { borderColor: colors.border },
                      ]}
                    >
                      <Feather name="link-2" size={16} color={colors.primary} />
                      <Text
                        style={{
                          color: colors.foreground,
                          flex: 1,
                          marginLeft: 8,
                        }}
                        numberOfLines={1}
                      >
                        {k}
                      </Text>
                      <Feather
                        name="external-link"
                        size={14}
                        color={colors.mutedForeground}
                      />
                    </Pressable>
                  ))}
                </View>
              </SectionCard>
            ) : null}

            {/* Biolink */}
            {sections.biolink && profile.biolink_url ? (
              <SectionCard title="Biolink" colors={colors}>
                <Button
                  label="Open my biolink"
                  variant="secondary"
                  onPress={() =>
                    profile.biolink_url && Linking.openURL(profile.biolink_url)
                  }
                />
              </SectionCard>
            ) : null}

            {/* Posts header */}
            <Text
              style={[
                styles.postsHeader,
                { color: colors.foreground },
              ]}
            >
              Posts
            </Text>
            {feedQ.isLoading ? (
              <ActivityIndicator
                color={colors.primary}
                style={{ marginTop: 16 }}
              />
            ) : null}
          </View>
        }
        renderItem={({ item }) => (
          <PostCard
            post={item}
            handle={handle}
            reactionsCatalog={reactions_catalog}
            feedQueryKey={["creator-profile-feed", handle]}
          />
        )}
        ListEmptyComponent={
          !feedQ.isLoading ? (
            <View style={{ paddingHorizontal: 16, paddingTop: 8 }}>
              <Text style={{ color: colors.mutedForeground, textAlign: "center" }}>
                No posts yet.
              </Text>
            </View>
          ) : null
        }
        ListFooterComponent={
          feedQ.isFetchingNextPage ? (
            <ActivityIndicator
              color={colors.primary}
              style={{ marginVertical: 16 }}
            />
          ) : null
        }
      />
    </View>
  );
}

function Stat({
  label,
  value,
  color,
  muted,
}: {
  label: string;
  value: number;
  color: string;
  muted: string;
}) {
  return (
    <View style={styles.stat}>
      <Text style={[styles.statValue, { color }]}>
        {Intl.NumberFormat().format(value)}
      </Text>
      <Text style={[styles.statLabel, { color: muted }]}>{label}</Text>
    </View>
  );
}

function SectionCard({
  title,
  children,
  colors,
}: {
  title: string;
  children: React.ReactNode;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View
      style={[
        styles.section,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
        {title}
      </Text>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  cover: { height: 140, width: "100%" },
  heroCard: {
    marginHorizontal: 16,
    marginTop: -40,
    padding: 16,
    borderWidth: 1,
    alignItems: "center",
  },
  avatarWrap: { marginTop: -48 },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    borderWidth: 4,
  },
  avatarFallback: { alignItems: "center", justifyContent: "center" },
  avatarInitials: { color: "#fff", fontSize: 28, fontWeight: "800" },
  name: {
    marginTop: 10,
    fontSize: 22,
    fontFamily: "SpaceGrotesk_700Bold",
  },
  handleLine: { marginTop: 2, fontSize: 13 },
  tagline: { marginTop: 8, fontSize: 14, textAlign: "center" },
  statRow: {
    marginTop: 14,
    flexDirection: "row",
    alignItems: "center",
    alignSelf: "stretch",
    justifyContent: "center",
    paddingVertical: 10,
    gap: 10,
  },
  stat: { alignItems: "center", paddingHorizontal: 14 },
  statValue: { fontSize: 18, fontWeight: "800" },
  statLabel: { fontSize: 11, marginTop: 2 },
  statSep: { width: 1, height: 24 },
  section: {
    marginHorizontal: 16,
    marginTop: 14,
    padding: 14,
    borderWidth: 1,
  },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
    marginBottom: 10,
  },
  tagRow: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 10 },
  tag: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    borderWidth: 1,
  },
  socialRow: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderRadius: 10,
  },
  postsHeader: {
    marginHorizontal: 16,
    marginTop: 22,
    marginBottom: 6,
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 18,
  },
});
