import { Feather } from "@expo/vector-icons";
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
  type InfiniteData,
} from "@tanstack/react-query";
import { Image } from "expo-image";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Linking,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { BrandedReactions } from "@/components/BrandedReactions";
import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import {
  creatorProfile,
  type CreatorProfilePost,
  type ProfileComment,
  type ReactionKey,
} from "@/lib/api/creatorProfile";
import { follow, unfollow } from "@/lib/api/follows";

// Strongly-typed shape of one infinite-query page returned by
// `creatorProfile.feed`. We derive it from the function rather than
// duplicating the shape so it stays in sync with the API contract.
type FeedPage = Awaited<ReturnType<typeof creatorProfile.feed>>;
type FeedData = InfiniteData<FeedPage>;

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

function PostCard({
  post,
  handle,
  reactionsCatalog,
}: {
  post: CreatorProfilePost;
  handle: string;
  reactionsCatalog: { key: string; label: string; emoji: string }[];
}) {
  const colors = useColors();
  const qc = useQueryClient();
  const router = useRouter();
  const [showComments, setShowComments] = useState(false);

  const reactM = useMutation({
    mutationFn: (key: ReactionKey) => creatorProfile.react(handle, post.id, key),
    onMutate: async (key) => {
      // Optimistic update — flip the viewer's reaction immediately,
      // then mirror the change in every cached page of the infinite
      // feed query. Strongly typed against the same FeedPage shape the
      // server returns; no `any` casts.
      await qc.cancelQueries({ queryKey: ["creator-profile-feed", handle] });
      const snap = qc.getQueriesData<FeedData>({
        queryKey: ["creator-profile-feed", handle],
      });
      qc.setQueriesData<FeedData>(
        { queryKey: ["creator-profile-feed", handle] },
        (old) => {
          if (!old) return old;
          return {
            ...old,
            pages: old.pages.map((pg) => ({
              ...pg,
              items: pg.items.map((p) => {
                if (p.id !== post.id) return p;
                const totals: Record<string, number> = {
                  ...(p.reaction_totals ?? {}),
                };
                if (p.my_reaction) {
                  totals[p.my_reaction] = Math.max(
                    0,
                    (totals[p.my_reaction] ?? 1) - 1,
                  );
                }
                let mine: ReactionKey | null = key;
                let count = p.reactions_count;
                if (p.my_reaction === key) {
                  mine = null;
                  count = Math.max(0, count - 1);
                } else {
                  totals[key] = (totals[key] ?? 0) + 1;
                  if (!p.my_reaction) count = count + 1;
                }
                return {
                  ...p,
                  my_reaction: mine,
                  reaction_totals: totals,
                  reactions_count: count,
                };
              }),
            })),
          };
        },
      );
      return { snap };
    },
    onError: (_e, _v, ctx) => {
      ctx?.snap.forEach(([key, data]) => qc.setQueryData(key, data));
    },
    onSettled: () =>
      qc.invalidateQueries({ queryKey: ["creator-profile-feed", handle] }),
  });

  return (
    <View
      style={[
        styles.postCard,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      {post.is_pinned ? (
        <View style={styles.pinned}>
          <Feather name="bookmark" size={12} color={colors.primary} />
          <Text style={{ color: colors.primary, fontSize: 11, fontWeight: "700" }}>
            Pinned
          </Text>
        </View>
      ) : null}
      {post.title ? (
        <Text style={[styles.postTitle, { color: colors.foreground }]}>
          {post.title}
        </Text>
      ) : null}

      <PostBody post={post} handle={handle} />

      <View style={styles.metaRow}>
        <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
          {post.published_at
            ? new Date(post.published_at).toLocaleDateString()
            : ""}
        </Text>
        <View style={{ flex: 1 }} />
        {/*
          Tip CTA (Task #1209): always visible on every post — locked
          or unlocked, free or paid. Tipping is independent of access:
          fans can show appreciation regardless of whether they're
          subscribed or have unlocked the post.
        */}
        <Pressable
          onPress={() =>
            router.push({
              pathname: "/monetization/tip",
              params: { handle, postId: String(post.id) },
            })
          }
          style={styles.commentToggle}
          accessibilityLabel="Send a tip"
        >
          <Feather name="heart" size={14} color={colors.mutedForeground} />
          <Text style={{ color: colors.mutedForeground, fontSize: 12, marginLeft: 4 }}>
            Tip
          </Text>
        </Pressable>
        <Pressable
          onPress={() => setShowComments((v) => !v)}
          style={styles.commentToggle}
        >
          <Feather
            name="message-circle"
            size={14}
            color={colors.mutedForeground}
          />
          <Text style={{ color: colors.mutedForeground, fontSize: 12, marginLeft: 4 }}>
            {post.comments_count}
          </Text>
        </Pressable>
      </View>

      <BrandedReactions
        totals={post.reaction_totals ?? {}}
        myReaction={post.my_reaction}
        onReact={(k) => reactM.mutate(k)}
        disabled={reactM.isPending}
        catalog={reactionsCatalog}
      />

      {showComments ? (
        <CommentsThread handle={handle} postId={post.id} />
      ) : null}
    </View>
  );
}

function PostBody({
  post,
  handle,
}: {
  post: CreatorProfilePost;
  handle: string;
}) {
  const colors = useColors();
  const router = useRouter();

  // ── Locked variant (Task #1209) ─────────────────────────────────
  // Server omits asset URLs (`image`, `media`) on locked posts and
  // instead emits a sanitized `preview` containing ONLY the items /
  // poster the creator opted to share. We never render the gated
  // asset directly — CSS blur is cosmetic, not access control.
  if (post.access && !post.access.can) {
    const ppvDollars =
      post.ppv_price_cents != null ? (post.ppv_price_cents / 100).toFixed(0) : null;
    const ctaLabel = post.access.requires_ppv
      ? `Unlock for $${ppvDollars ?? "?"}`
      : post.access.lowest_tier
        ? `Subscribe — from $${(post.access.lowest_tier.price_monthly_cents / 100).toFixed(0)}/mo`
        : "Subscribe to view";
    const onPress = () =>
      router.push(
        post.access!.requires_ppv
          ? { pathname: "/monetization/unlock", params: { handle, postId: String(post.id) } }
          : { pathname: "/monetization/subscribe", params: { handle } },
      );

    // Render the creator-approved preview surface (gallery items or
    // video poster). When neither is configured, show an opaque
    // gradient placeholder — never the original asset URL.
    const preview = post.preview ?? null;
    const previewSurface =
      preview?.kind === "gallery" && preview.items.length > 0 ? (
        <View style={{ flexDirection: "row", gap: 4 }}>
          {preview.items.map((it, idx) =>
            it.url ? (
              <Image
                key={`${idx}-${it.url}`}
                source={{ uri: it.url }}
                style={{ flex: 1, aspectRatio: 1, borderRadius: 8, backgroundColor: "#0f172a" }}
                contentFit="cover"
              />
            ) : null,
          )}
          {preview.total_items > preview.visible_count ? (
            <View
              style={{
                flex: 1,
                aspectRatio: 1,
                borderRadius: 8,
                backgroundColor: "#7c3aed",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Text style={{ color: "#fff", fontWeight: "800", fontSize: 16 }}>
                +{preview.total_items - preview.visible_count}
              </Text>
            </View>
          ) : null}
        </View>
      ) : preview?.kind === "video" && preview.poster ? (
        <View
          style={{
            position: "relative",
            borderRadius: 12,
            overflow: "hidden",
            aspectRatio: 16 / 10,
            backgroundColor: "#000",
          }}
        >
          <Image
            source={{ uri: preview.poster }}
            style={StyleSheet.absoluteFill}
            contentFit="cover"
          />
          <View
            style={{
              position: "absolute",
              bottom: 8,
              right: 8,
              backgroundColor: "rgba(0,0,0,0.7)",
              paddingHorizontal: 8,
              paddingVertical: 3,
              borderRadius: 999,
            }}
          >
            <Text style={{ color: "#fff", fontSize: 11, fontWeight: "700" }}>
              {preview.seconds}s preview
            </Text>
          </View>
        </View>
      ) : (
        // Gradient placeholder, keyed off post id so it's stable across
        // renders. We do NOT use `post.image` / `post.media` here —
        // those are intentionally null on a locked payload.
        <View
          style={{
            borderRadius: 12,
            aspectRatio: 16 / 10,
            backgroundColor: ["#7c3aed", "#ec4899", "#0ea5e9", "#10b981"][post.id % 4],
            opacity: 0.55,
          }}
        />
      );

    return (
      <View style={{ marginTop: 8, gap: 8 }}>
        {previewSurface}
        <View
          style={{
            borderRadius: 12,
            backgroundColor: "rgba(124,58,237,0.10)",
            padding: 12,
            alignItems: "center",
            gap: 6,
          }}
        >
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
            <Feather name="lock" size={14} color={colors.primary} />
            <Text style={{ color: colors.foreground, fontWeight: "800", fontSize: 13 }}>
              {post.access.requires_ppv ? "Pay-per-view" : "Subscribers only"}
            </Text>
          </View>
          {post.teaser_caption ? (
            <Text
              style={{
                color: colors.mutedForeground,
                fontSize: 12,
                textAlign: "center",
                maxWidth: 280,
              }}
              numberOfLines={2}
            >
              {post.teaser_caption}
            </Text>
          ) : post.body_excerpt ? (
            <Text
              style={{
                color: colors.mutedForeground,
                fontSize: 12,
                textAlign: "center",
                maxWidth: 280,
              }}
              numberOfLines={2}
            >
              {post.body_excerpt}…
            </Text>
          ) : null}
          <Pressable
            onPress={onPress}
            style={{
              marginTop: 4,
              backgroundColor: colors.primary,
              paddingHorizontal: 16,
              paddingVertical: 9,
              borderRadius: 999,
            }}
          >
            <Text style={{ color: "#fff", fontWeight: "700", fontSize: 13 }}>
              {ctaLabel}
            </Text>
          </Pressable>
        </View>
      </View>
    );
  }

  const text = post.body ? (
    <Text style={{ color: colors.foreground, marginTop: 6, lineHeight: 20 }}>
      {post.body}
    </Text>
  ) : null;

  switch (post.post_type) {
    case "image":
      return (
        <View>
          {(post.image || post.media?.url) ? (
            <Image
              source={{ uri: (post.image || post.media?.url) as string }}
              style={styles.media}
              contentFit="cover"
            />
          ) : null}
          {text}
        </View>
      );
    case "gallery": {
      const items = post.media?.items ?? [];
      return (
        <View>
          {items.length ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              {items.map((it, i) => (
                <Image
                  key={`${i}-${it.url}`}
                  source={{ uri: it.url }}
                  style={[styles.galleryItem]}
                  contentFit="cover"
                />
              ))}
            </ScrollView>
          ) : null}
          {text}
        </View>
      );
    }
    case "video":
      return (
        <View>
          {post.media?.poster ? (
            <Pressable
              onPress={() =>
                post.media?.url && Linking.openURL(post.media.url)
              }
            >
              <Image
                source={{ uri: post.media.poster }}
                style={styles.media}
                contentFit="cover"
              />
              <View style={styles.playOverlay}>
                <Feather name="play" size={28} color="#fff" />
              </View>
            </Pressable>
          ) : post.media?.url ? (
            <Pressable
              onPress={() => Linking.openURL(post.media!.url as string)}
              style={[
                styles.linkBox,
                { borderColor: colors.border },
              ]}
            >
              <Feather name="film" size={18} color={colors.primary} />
              <Text style={{ color: colors.foreground, marginLeft: 8 }}>
                Watch video
              </Text>
            </Pressable>
          ) : null}
          {text}
        </View>
      );
    case "audio":
      return (
        <View>
          {post.media?.url ? (
            <Pressable
              onPress={() => Linking.openURL(post.media!.url as string)}
              style={[
                styles.audioBox,
                { borderColor: colors.border, backgroundColor: colors.background },
              ]}
            >
              <Feather name="headphones" size={18} color={colors.primary} />
              <Text
                style={{
                  color: colors.foreground,
                  marginLeft: 10,
                  fontWeight: "600",
                }}
              >
                {post.media.title || "Listen"}
              </Text>
              {post.media.duration ? (
                <Text
                  style={{
                    color: colors.mutedForeground,
                    marginLeft: "auto",
                    fontSize: 12,
                  }}
                >
                  {Math.round(post.media.duration / 60)}m
                </Text>
              ) : null}
            </Pressable>
          ) : null}
          {text}
        </View>
      );
    case "link":
      return (
        <View>
          {post.media?.url ? (
            <Pressable
              onPress={() => Linking.openURL(post.media!.url as string)}
              style={[
                styles.linkCard,
                { borderColor: colors.border },
              ]}
            >
              {post.media.thumbnail ? (
                <Image
                  source={{ uri: post.media.thumbnail }}
                  style={styles.linkThumb}
                  contentFit="cover"
                />
              ) : null}
              <View style={{ flex: 1, padding: 10 }}>
                <Text
                  style={{ color: colors.foreground, fontWeight: "700" }}
                  numberOfLines={2}
                >
                  {post.media.title || post.media.url}
                </Text>
                {post.media.description ? (
                  <Text
                    style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }}
                    numberOfLines={2}
                  >
                    {post.media.description}
                  </Text>
                ) : null}
              </View>
            </Pressable>
          ) : null}
          {text}
        </View>
      );
    default:
      return text ?? null;
  }
}

function CommentsThread({
  handle,
  postId,
}: {
  handle: string;
  postId: number;
}) {
  const colors = useColors();
  const qc = useQueryClient();
  const [draft, setDraft] = useState("");
  const [replyTo, setReplyTo] = useState<number | null>(null);

  const q = useQuery({
    queryKey: ["creator-profile-comments", handle, postId],
    queryFn: () => creatorProfile.comments(handle, postId),
  });

  const send = useMutation({
    mutationFn: () => creatorProfile.comment(handle, postId, draft.trim(), replyTo),
    onSuccess: () => {
      setDraft("");
      setReplyTo(null);
      qc.invalidateQueries({
        queryKey: ["creator-profile-comments", handle, postId],
      });
      qc.invalidateQueries({ queryKey: ["creator-profile-feed", handle] });
    },
    onError: (e: Error) =>
      Alert.alert("Couldn't post comment", e.message || "Try again."),
  });

  const flat = useMemo(() => q.data ?? [], [q.data]);

  return (
    <View
      style={[
        styles.thread,
        { borderTopColor: colors.border },
      ]}
    >
      {q.isLoading ? (
        <ActivityIndicator color={colors.primary} />
      ) : flat.length === 0 ? (
        <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
          No comments yet — be the first.
        </Text>
      ) : (
        flat.map((c) => (
          <CommentRow
            key={c.id}
            c={c}
            depth={0}
            onReply={(id) => setReplyTo(id)}
          />
        ))
      )}

      <View
        style={[
          styles.composer,
          { borderColor: colors.border, backgroundColor: colors.background },
        ]}
      >
        <TextInput
          value={draft}
          onChangeText={setDraft}
          placeholder={
            replyTo ? "Write a reply…" : "Add a comment…"
          }
          placeholderTextColor={colors.mutedForeground}
          style={{ flex: 1, color: colors.foreground, paddingHorizontal: 8 }}
          multiline
        />
        {replyTo ? (
          <Pressable onPress={() => setReplyTo(null)}>
            <Feather name="x" size={16} color={colors.mutedForeground} />
          </Pressable>
        ) : null}
        <Pressable
          disabled={!draft.trim() || send.isPending}
          onPress={() => draft.trim() && send.mutate()}
          style={{ marginLeft: 8 }}
        >
          <Feather
            name="send"
            size={18}
            color={draft.trim() ? colors.primary : colors.mutedForeground}
          />
        </Pressable>
      </View>
    </View>
  );
}

function CommentRow({
  c,
  depth,
  onReply,
}: {
  c: ProfileComment;
  depth: number;
  onReply: (id: number) => void;
}) {
  const colors = useColors();
  return (
    <View style={{ marginLeft: depth * 16, marginTop: 8 }}>
      <View
        style={[
          styles.commentRow,
          { backgroundColor: colors.background, borderColor: colors.border },
        ]}
      >
        <Text
          style={{
            color: colors.foreground,
            fontWeight: "700",
            fontSize: 12,
          }}
        >
          {c.author?.handle ? `@${c.author.handle}` : c.author?.name ?? "Guest"}
        </Text>
        <Text style={{ color: colors.foreground, marginTop: 2 }}>{c.body}</Text>
        {depth === 0 ? (
          <Pressable onPress={() => onReply(c.id)} style={{ marginTop: 4 }}>
            <Text style={{ color: colors.primary, fontSize: 12 }}>Reply</Text>
          </Pressable>
        ) : null}
      </View>
      {c.replies?.map((r) => (
        <CommentRow key={r.id} c={r} depth={depth + 1} onReply={onReply} />
      ))}
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
  postCard: {
    marginHorizontal: 16,
    marginTop: 10,
    padding: 14,
    borderWidth: 1,
  },
  pinned: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    marginBottom: 6,
  },
  postTitle: { fontSize: 16, fontWeight: "700" },
  media: {
    width: "100%",
    aspectRatio: 16 / 10,
    borderRadius: 10,
    marginTop: 8,
  },
  playOverlay: {
    position: "absolute",
    top: 0,
    bottom: 0,
    left: 0,
    right: 0,
    alignItems: "center",
    justifyContent: "center",
  },
  galleryItem: {
    width: 220,
    height: 160,
    borderRadius: 10,
    marginRight: 8,
    marginTop: 8,
  },
  audioBox: {
    flexDirection: "row",
    alignItems: "center",
    padding: 12,
    borderWidth: 1,
    borderRadius: 12,
    marginTop: 8,
  },
  linkBox: {
    flexDirection: "row",
    alignItems: "center",
    padding: 12,
    borderWidth: 1,
    borderRadius: 12,
    marginTop: 8,
  },
  linkCard: {
    flexDirection: "row",
    borderWidth: 1,
    borderRadius: 12,
    overflow: "hidden",
    marginTop: 8,
  },
  linkThumb: { width: 96, height: 96 },
  metaRow: {
    flexDirection: "row",
    alignItems: "center",
    marginTop: 10,
  },
  commentToggle: { flexDirection: "row", alignItems: "center" },
  thread: {
    marginTop: 12,
    paddingTop: 10,
    borderTopWidth: 1,
  },
  commentRow: {
    padding: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
  composer: {
    marginTop: 10,
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderWidth: 1,
    borderRadius: 12,
  },
});
