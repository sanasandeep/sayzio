import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Modal,
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
import {
  approveReview,
  deleteReview,
  getMyReviews,
  hideReview,
  pinReview,
  replyReview,
  type OwnerReview,
  type OwnerReviewCounts,
  type ReviewStatus,
} from "@/lib/api/reviews";

type Colors = ReturnType<typeof useColors>;

const STAR = "★";
const STAR_EMPTY = "☆";

type TabKey = "pending" | "approved" | "hidden";
const TABS: { key: TabKey; label: string }[] = [
  { key: "pending", label: "Pending" },
  { key: "approved", label: "Published" },
  { key: "hidden", label: "Hidden" },
];

function Stars({ rating, colors }: { rating: number; colors: Colors }) {
  const full = Math.round(rating);
  return (
    <Text style={{ fontSize: 13, letterSpacing: 1 }}>
      {[1, 2, 3, 4, 5].map((i) => (
        <Text key={i} style={{ color: i <= full ? "#f59e0b" : colors.border }}>
          {i <= full ? STAR : STAR_EMPTY}
        </Text>
      ))}
    </Text>
  );
}

function ActionButton({
  icon,
  label,
  color,
  onPress,
  disabled,
}: {
  icon: keyof typeof Feather.glyphMap;
  label: string;
  color: string;
  onPress: () => void;
  disabled?: boolean;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      style={[styles.actionBtn, { borderColor: color, opacity: disabled ? 0.4 : 1 }]}
      hitSlop={4}
    >
      <Feather name={icon} size={13} color={color} />
      <Text style={[styles.actionBtnText, { color }]}>{label}</Text>
    </Pressable>
  );
}

function ReviewItem({
  review,
  colors,
  busy,
  onApprove,
  onHide,
  onPin,
  onReply,
  onDelete,
}: {
  review: OwnerReview;
  colors: Colors;
  busy: boolean;
  onApprove: () => void;
  onHide: () => void;
  onPin: () => void;
  onReply: () => void;
  onDelete: () => void;
}) {
  const initial =
    (review.author_name || "?").trim().charAt(0).toUpperCase() || "?";

  const statusMeta: Record<string, { label: string; color: string }> = {
    pending: { label: "Pending", color: colors.warning },
    approved: { label: "Published", color: colors.success },
    hidden: { label: "Hidden", color: colors.mutedForeground },
    unverified: { label: "Unverified", color: "#6366f1" },
  };
  const sm = statusMeta[review.status] ?? {
    label: review.status,
    color: colors.mutedForeground,
  };

  return (
    <View
      style={[
        styles.card,
        { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
      ]}
    >
      <View style={styles.head}>
        {review.author_avatar ? (
          <Image source={{ uri: review.author_avatar }} style={styles.avatar} />
        ) : (
          <View
            style={[
              styles.avatar,
              styles.avatarFallback,
              { backgroundColor: colors.primary + "22" },
            ]}
          >
            <Text style={[styles.avatarInitial, { color: colors.primary }]}>{initial}</Text>
          </View>
        )}
        <View style={{ flex: 1 }}>
          <View style={styles.nameRow}>
            <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
              {review.author_name || "Anonymous"}
            </Text>
            {review.pinned ? (
              <Feather name="bookmark" size={12} color={colors.primary} />
            ) : null}
          </View>
          {review.author_email ? (
            <Text style={[styles.email, { color: colors.mutedForeground }]} numberOfLines={1}>
              {review.author_email}
            </Text>
          ) : null}
        </View>
        <View style={[styles.statusTag, { backgroundColor: sm.color + "22" }]}>
          <Text style={[styles.statusTagText, { color: sm.color }]}>{sm.label}</Text>
        </View>
      </View>

      <View style={styles.metaRow}>
        {typeof review.rating === "number" ? (
          <Stars rating={review.rating} colors={colors} />
        ) : null}
        {review.is_spam ? (
          <View style={[styles.spamTag, { backgroundColor: colors.destructive + "22" }]}>
            <Feather name="alert-triangle" size={10} color={colors.destructive} />
            <Text style={[styles.spamTagText, { color: colors.destructive }]}>Spam</Text>
          </View>
        ) : null}
        {review.link ? (
          <Text style={[styles.linkLabel, { color: colors.mutedForeground }]} numberOfLines={1}>
            {review.link.title || review.link.alias}
          </Text>
        ) : null}
      </View>

      {review.body ? (
        <Text style={[styles.body, { color: colors.foreground }]}>{review.body}</Text>
      ) : null}

      {review.answers?.map((a, i) => (
        <View key={i} style={styles.answerRow}>
          <Text style={[styles.answerPrompt, { color: colors.mutedForeground }]}>{a.prompt}</Text>
          <Text style={[styles.answerText, { color: colors.foreground }]}>{a.answer}</Text>
        </View>
      ))}

      {review.reply ? (
        <View
          style={[
            styles.replyBox,
            { backgroundColor: colors.background, borderColor: colors.border },
          ]}
        >
          <Text style={[styles.replyLabel, { color: colors.primary }]}>Your reply</Text>
          <Text style={[styles.replyBody, { color: colors.foreground }]}>{review.reply}</Text>
        </View>
      ) : null}

      <View style={styles.actions}>
        {review.status !== "approved" ? (
          <ActionButton icon="check" label="Approve" color={colors.success} onPress={onApprove} disabled={busy} />
        ) : null}
        {review.status !== "hidden" ? (
          <ActionButton icon="eye-off" label="Hide" color={colors.mutedForeground} onPress={onHide} disabled={busy} />
        ) : null}
        <ActionButton
          icon="bookmark"
          label={review.pinned ? "Unpin" : "Pin"}
          color={colors.primary}
          onPress={onPin}
          disabled={busy}
        />
        <ActionButton
          icon="corner-up-left"
          label={review.reply ? "Edit reply" : "Reply"}
          color={colors.foreground}
          onPress={onReply}
          disabled={busy}
        />
        <ActionButton icon="trash-2" label="Delete" color={colors.destructive} onPress={onDelete} disabled={busy} />
      </View>
    </View>
  );
}

function ReplyModal({
  review,
  colors,
  onClose,
  onSave,
  saving,
}: {
  review: OwnerReview | null;
  colors: Colors;
  onClose: () => void;
  onSave: (reply: string) => void;
  saving: boolean;
}) {
  const [text, setText] = useState("");

  // Re-seed the field whenever a different review is opened.
  useEffect(() => {
    setText(review?.reply ?? "");
  }, [review?.id, review?.reply]);

  return (
    <Modal
      visible={!!review}
      transparent
      animationType="slide"
      onRequestClose={onClose}
    >
      <View style={styles.modalBackdrop}>
        <View
          style={[
            styles.modalCard,
            { backgroundColor: colors.background, borderColor: colors.border },
          ]}
        >
          <View style={styles.modalHeadRow}>
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              Reply to review
            </Text>
            <Pressable onPress={onClose} hitSlop={8}>
              <Feather name="x" size={22} color={colors.mutedForeground} />
            </Pressable>
          </View>
          <TextInput
            value={text}
            onChangeText={setText}
            placeholder="Write a public reply…"
            placeholderTextColor={colors.mutedForeground}
            multiline
            editable={!saving}
            style={{
              borderWidth: 1,
              borderColor: colors.border,
              borderRadius: colors.radius,
              padding: 12,
              minHeight: 110,
              textAlignVertical: "top",
              color: colors.foreground,
              backgroundColor: colors.card,
              fontSize: 15,
              fontFamily: "SpaceGrotesk_400Regular",
            }}
          />
          <Text style={[styles.helper, { color: colors.mutedForeground }]}>
            Leave it empty and save to remove an existing reply.
          </Text>
          <Pressable
            onPress={() => onSave(text)}
            disabled={saving}
            style={[styles.saveBtn, { backgroundColor: colors.primary, opacity: saving ? 0.6 : 1 }]}
          >
            {saving ? (
              <ActivityIndicator size="small" color={colors.primaryForeground} />
            ) : (
              <Text style={[styles.saveBtnText, { color: colors.primaryForeground }]}>
                Save reply
              </Text>
            )}
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}

export default function ManageReviewsScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [tab, setTab] = useState<TabKey>("pending");
  const [replyTarget, setReplyTarget] = useState<OwnerReview | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  const q = useQuery({
    queryKey: ["my-reviews", tab],
    queryFn: () => getMyReviews({ status: tab as ReviewStatus, per_page: 50 }),
  });

  const counts: OwnerReviewCounts = q.data?.counts ?? {
    pending: 0,
    approved: 0,
    hidden: 0,
    unverified: 0,
  };

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["my-reviews"] });
  };

  const act = useMutation({
    mutationFn: async (args: {
      id: string;
      kind: "approve" | "hide" | "pin" | "delete";
    }) => {
      switch (args.kind) {
        case "approve":
          return approveReview(args.id);
        case "hide":
          return hideReview(args.id);
        case "pin":
          return pinReview(args.id);
        case "delete":
          return deleteReview(args.id);
      }
    },
    onMutate: (args) => setBusyId(args.id),
    onSettled: () => setBusyId(null),
    onSuccess: () => invalidate(),
    onError: (e: unknown) => {
      const msg =
        e && typeof e === "object" && "message" in e
          ? String((e as { message: unknown }).message)
          : "Something went wrong";
      if (Platform.OS === "web") alert(msg);
      else Alert.alert("Couldn't update review", msg);
    },
  });

  const reply = useMutation({
    mutationFn: (args: { id: string; reply: string }) =>
      replyReview(args.id, args.reply),
    onSuccess: () => {
      setReplyTarget(null);
      invalidate();
    },
    onError: (e: unknown) => {
      const msg =
        e && typeof e === "object" && "message" in e
          ? String((e as { message: unknown }).message)
          : "Could not save reply";
      if (Platform.OS === "web") alert(msg);
      else Alert.alert("Couldn't save reply", msg);
    },
  });

  const confirmDelete = (review: OwnerReview) => {
    const go = () => act.mutate({ id: review.id, kind: "delete" });
    const label = review.author_name || "this review";
    if (Platform.OS === "web") {
      if (confirm(`Delete ${label}? This cannot be undone.`)) go();
    } else {
      Alert.alert(
        "Delete review?",
        "This permanently removes the review and cannot be undone.",
        [
          { text: "Cancel", style: "cancel" },
          { text: "Delete", style: "destructive", onPress: go },
        ],
      );
    }
  };

  const reviews = q.data?.reviews ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Manage reviews" }} />

      <View style={{ paddingHorizontal: 16, paddingTop: 12 }}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={{ gap: 8 }}
        >
          {TABS.map((t) => {
            const active = tab === t.key;
            const count = counts[t.key];
            return (
              <Pressable
                key={t.key}
                onPress={() => setTab(t.key)}
                style={[
                  styles.tab,
                  {
                    backgroundColor: active ? colors.primary : colors.card,
                    borderColor: active ? colors.primary : colors.border,
                  },
                ]}
              >
                <Text
                  style={{
                    fontFamily: "SpaceGrotesk_600SemiBold",
                    fontSize: 13,
                    color: active ? colors.primaryForeground : colors.mutedForeground,
                  }}
                >
                  {t.label}
                  {count > 0 ? ` · ${count}` : ""}
                </Text>
              </Pressable>
            );
          })}
        </ScrollView>
      </View>

      {q.isLoading ? (
        <View style={{ paddingVertical: 48, alignItems: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError ? (
        <View style={{ paddingVertical: 40 }}>
          <EmptyState
            icon="alert-circle"
            title="Couldn't load reviews"
            body="Pull to refresh and try again."
          />
        </View>
      ) : reviews.length === 0 ? (
        <View style={{ paddingVertical: 40 }}>
          <EmptyState
            icon="message-square"
            title={
              tab === "pending"
                ? "No reviews waiting"
                : tab === "approved"
                  ? "No published reviews"
                  : "Nothing hidden"
            }
            body={
              tab === "pending"
                ? "New reviews that need approval will show up here."
                : undefined
            }
          />
        </View>
      ) : (
        <FlatList
          data={reviews}
          keyExtractor={(r) => r.id}
          contentContainerStyle={{ padding: 16, gap: 12 }}
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
          renderItem={({ item }) => (
            <ReviewItem
              review={item}
              colors={colors}
              busy={busyId === item.id || (reply.isPending && replyTarget?.id === item.id)}
              onApprove={() => act.mutate({ id: item.id, kind: "approve" })}
              onHide={() => act.mutate({ id: item.id, kind: "hide" })}
              onPin={() => act.mutate({ id: item.id, kind: "pin" })}
              onReply={() => setReplyTarget(item)}
              onDelete={() => confirmDelete(item)}
            />
          )}
        />
      )}

      <ReplyModal
        review={replyTarget}
        colors={colors}
        saving={reply.isPending}
        onClose={() => setReplyTarget(null)}
        onSave={(text) => {
          if (replyTarget) reply.mutate({ id: replyTarget.id, reply: text });
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  tab: {
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: 999,
    borderWidth: 1,
  },

  card: { padding: 14, borderWidth: 1, gap: 8 },
  head: { flexDirection: "row", gap: 10, alignItems: "center" },
  avatar: { width: 38, height: 38, borderRadius: 19 },
  avatarFallback: { alignItems: "center", justifyContent: "center" },
  avatarInitial: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  nameRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15, flexShrink: 1 },
  email: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 1 },
  statusTag: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  statusTagText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 10 },

  metaRow: { flexDirection: "row", alignItems: "center", gap: 8, flexWrap: "wrap" },
  spamTag: {
    flexDirection: "row",
    alignItems: "center",
    gap: 3,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 999,
  },
  spamTagText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 9 },
  linkLabel: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11, flexShrink: 1 },

  body: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  answerRow: { marginTop: 2 },
  answerPrompt: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11 },
  answerText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },

  replyBox: { padding: 10, borderRadius: 10, borderWidth: 1, gap: 2 },
  replyLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 11 },
  replyBody: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 18 },

  actions: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 2 },
  actionBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 999,
    borderWidth: 1,
  },
  actionBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },

  modalBackdrop: { flex: 1, backgroundColor: "rgba(0,0,0,0.5)", justifyContent: "flex-end" },
  modalCard: {
    padding: 20,
    borderTopWidth: 1,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    gap: 12,
  },
  modalHeadRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 20 },
  helper: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  saveBtn: { minHeight: 50, borderRadius: 12, alignItems: "center", justifyContent: "center" },
  saveBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
});
