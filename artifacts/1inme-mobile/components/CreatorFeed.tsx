import { Feather } from "@expo/vector-icons";
import {
  type InfiniteData,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { Image } from "expo-image";
import { useRouter } from "expo-router";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { BrandedReactions } from "@/components/BrandedReactions";
import { useColors } from "@/hooks/useColors";
import {
  creatorProfile,
  type CreatorProfilePost,
  type ProfileComment,
  type ReactionKey,
} from "@/lib/api/creatorProfile";
import { showAlert } from "@/lib/webAlert";

// Strongly-typed shape of one infinite-query page returned by
// `creatorProfile.feed`, derived from the function so it stays in sync.
type FeedPage = Awaited<ReturnType<typeof creatorProfile.feed>>;
type FeedData = InfiniteData<FeedPage>;

/**
 * Theme overrides so the same post card can render inside a creator's
 * standard profile (which uses the app's `useColors()` tokens) and inside
 * a bold, per-link Paid Page (which supplies the chosen template's colours).
 * Any field left undefined falls back to the app theme.
 */
export type FeedTheme = {
  cardBg?: string;
  cardText?: string;
  mutedText?: string;
  accent?: string;
  border?: string;
  radius?: number;
  /** Background for inputs / inset surfaces (composer, comment rows). */
  subtleBg?: string;
};

function useFeedTheme(theme?: FeedTheme) {
  const colors = useColors();
  return {
    cardBg: theme?.cardBg ?? colors.card,
    cardText: theme?.cardText ?? colors.foreground,
    mutedText: theme?.mutedText ?? colors.mutedForeground,
    accent: theme?.accent ?? colors.primary,
    border: theme?.border ?? colors.border,
    radius: theme?.radius ?? colors.radius,
    subtleBg: theme?.subtleBg ?? colors.background,
  };
}

export function PostCard({
  post,
  handle,
  reactionsCatalog,
  feedQueryKey,
  theme,
}: {
  post: CreatorProfilePost;
  handle: string;
  reactionsCatalog: { key: string; label: string; emoji: string }[];
  /** Query key of the infinite feed this card belongs to, used for the
   *  optimistic reaction update and comment-count invalidation. */
  feedQueryKey: unknown[];
  theme?: FeedTheme;
}) {
  const c = useFeedTheme(theme);
  const qc = useQueryClient();
  const router = useRouter();
  const [showComments, setShowComments] = useState(false);

  const reactM = useMutation({
    mutationFn: (key: ReactionKey) => creatorProfile.react(handle, post.id, key),
    onMutate: async (key) => {
      // Optimistic update — flip the viewer's reaction immediately,
      // then mirror the change in every cached page of the infinite feed.
      await qc.cancelQueries({ queryKey: feedQueryKey });
      const snap = qc.getQueriesData<FeedData>({ queryKey: feedQueryKey });
      qc.setQueriesData<FeedData>({ queryKey: feedQueryKey }, (old) => {
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
      });
      return { snap };
    },
    onError: (_e, _v, ctx) => {
      ctx?.snap.forEach(([key, data]) => qc.setQueryData(key, data));
    },
    onSettled: () => qc.invalidateQueries({ queryKey: feedQueryKey }),
  });

  return (
    <View
      style={[
        styles.postCard,
        { backgroundColor: c.cardBg, borderColor: c.border, borderRadius: c.radius },
      ]}
    >
      {post.is_pinned ? (
        <View style={styles.pinned}>
          <Feather name="bookmark" size={12} color={c.accent} />
          <Text style={{ color: c.accent, fontSize: 11, fontWeight: "700" }}>
            Pinned
          </Text>
        </View>
      ) : null}
      {post.title ? (
        <Text style={[styles.postTitle, { color: c.cardText }]}>
          {post.title}
        </Text>
      ) : null}

      <PostBody post={post} handle={handle} theme={theme} />

      <View style={styles.metaRow}>
        <Text style={{ color: c.mutedText, fontSize: 12 }}>
          {post.published_at
            ? new Date(post.published_at).toLocaleDateString()
            : ""}
        </Text>
        <View style={{ flex: 1 }} />
        {/*
          Tip CTA: always visible on every post — locked or unlocked,
          free or paid. Tipping is independent of access.
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
          <Feather name="heart" size={14} color={c.mutedText} />
          <Text style={{ color: c.mutedText, fontSize: 12, marginLeft: 4 }}>
            Tip
          </Text>
        </Pressable>
        <Pressable
          onPress={() => setShowComments((v) => !v)}
          style={styles.commentToggle}
        >
          <Feather name="message-circle" size={14} color={c.mutedText} />
          <Text style={{ color: c.mutedText, fontSize: 12, marginLeft: 4 }}>
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
        <CommentsThread
          handle={handle}
          postId={post.id}
          feedQueryKey={feedQueryKey}
          theme={theme}
        />
      ) : null}
    </View>
  );
}

function PostBody({
  post,
  handle,
  theme,
}: {
  post: CreatorProfilePost;
  handle: string;
  theme?: FeedTheme;
}) {
  const c = useFeedTheme(theme);
  const router = useRouter();

  // ── Locked variant ──────────────────────────────────────────────
  // Server omits asset URLs on locked posts and instead emits a
  // sanitized `preview`. We never render the gated asset directly.
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
                backgroundColor: c.accent,
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
        <View
          style={{
            borderRadius: 12,
            aspectRatio: 16 / 10,
            backgroundColor: ["#3d6bff", "#d76dff", "#0ea5e9", "#10b981"][post.id % 4],
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
            backgroundColor: "rgba(61,107,255,0.10)",
            padding: 12,
            alignItems: "center",
            gap: 6,
          }}
        >
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
            <Feather name="lock" size={14} color={c.accent} />
            <Text style={{ color: c.cardText, fontWeight: "800", fontSize: 13 }}>
              {post.access.requires_ppv ? "Pay-per-view" : "Subscribers only"}
            </Text>
          </View>
          {post.teaser_caption ? (
            <Text
              style={{
                color: c.mutedText,
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
                color: c.mutedText,
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
              backgroundColor: c.accent,
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
    <Text style={{ color: c.cardText, marginTop: 6, lineHeight: 20 }}>
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
              onPress={() => post.media?.url && Linking.openURL(post.media.url)}
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
              style={[styles.linkBox, { borderColor: c.border }]}
            >
              <Feather name="film" size={18} color={c.accent} />
              <Text style={{ color: c.cardText, marginLeft: 8 }}>
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
                { borderColor: c.border, backgroundColor: c.subtleBg },
              ]}
            >
              <Feather name="headphones" size={18} color={c.accent} />
              <Text
                style={{ color: c.cardText, marginLeft: 10, fontWeight: "600" }}
              >
                {post.media.title || "Listen"}
              </Text>
              {post.media.duration ? (
                <Text
                  style={{ color: c.mutedText, marginLeft: "auto", fontSize: 12 }}
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
              style={[styles.linkCard, { borderColor: c.border }]}
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
                  style={{ color: c.cardText, fontWeight: "700" }}
                  numberOfLines={2}
                >
                  {post.media.title || post.media.url}
                </Text>
                {post.media.description ? (
                  <Text
                    style={{ color: c.mutedText, fontSize: 12, marginTop: 2 }}
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
  feedQueryKey,
  theme,
}: {
  handle: string;
  postId: number;
  feedQueryKey: unknown[];
  theme?: FeedTheme;
}) {
  const c = useFeedTheme(theme);
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
      qc.invalidateQueries({ queryKey: feedQueryKey });
    },
    onError: (e: Error) =>
      showAlert("Couldn't post comment", e.message || "Try again."),
  });

  const flat = useMemo(() => q.data ?? [], [q.data]);

  return (
    <View style={[styles.thread, { borderTopColor: c.border }]}>
      {q.isLoading ? (
        <ActivityIndicator color={c.accent} />
      ) : flat.length === 0 ? (
        <Text style={{ color: c.mutedText, fontSize: 12 }}>
          No comments yet — be the first.
        </Text>
      ) : (
        flat.map((cm) => (
          <CommentRow
            key={cm.id}
            c={cm}
            depth={0}
            onReply={(id) => setReplyTo(id)}
            theme={theme}
          />
        ))
      )}

      <View
        style={[
          styles.composer,
          { borderColor: c.border, backgroundColor: c.subtleBg },
        ]}
      >
        <TextInput
          value={draft}
          onChangeText={setDraft}
          placeholder={replyTo ? "Write a reply…" : "Add a comment…"}
          placeholderTextColor={c.mutedText}
          style={{ flex: 1, color: c.cardText, paddingHorizontal: 8 }}
          multiline
        />
        {replyTo ? (
          <Pressable onPress={() => setReplyTo(null)}>
            <Feather name="x" size={16} color={c.mutedText} />
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
            color={draft.trim() ? c.accent : c.mutedText}
          />
        </Pressable>
      </View>
    </View>
  );
}

function CommentRow({
  c: comment,
  depth,
  onReply,
  theme,
}: {
  c: ProfileComment;
  depth: number;
  onReply: (id: number) => void;
  theme?: FeedTheme;
}) {
  const c = useFeedTheme(theme);
  return (
    <View style={{ marginLeft: depth * 16, marginTop: 8 }}>
      <View
        style={[
          styles.commentRow,
          { backgroundColor: c.subtleBg, borderColor: c.border },
        ]}
      >
        <Text style={{ color: c.cardText, fontWeight: "700", fontSize: 12 }}>
          {comment.author?.handle
            ? `@${comment.author.handle}`
            : comment.author?.name ?? "Guest"}
        </Text>
        <Text style={{ color: c.cardText, marginTop: 2 }}>{comment.body}</Text>
        {depth === 0 ? (
          <Pressable onPress={() => onReply(comment.id)} style={{ marginTop: 4 }}>
            <Text style={{ color: c.accent, fontSize: 12 }}>Reply</Text>
          </Pressable>
        ) : null}
      </View>
      {comment.replies?.map((r) => (
        <CommentRow
          key={r.id}
          c={r}
          depth={depth + 1}
          onReply={onReply}
          theme={theme}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
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
