import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as ImagePicker from "expo-image-picker";
import * as Linking from "expo-linking";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  getReviews,
  submitReview,
  submitReviewWithMedia,
  type Review,
  type ReviewMediaUpload,
  type ReviewSort,
  type ReviewSummary,
} from "@/lib/api/reviews";

const MAX_MEDIA = 6;

type Colors = ReturnType<typeof useColors>;

const STAR = "★";
const STAR_EMPTY = "☆";

// Render a row of filled/empty stars for a given (possibly fractional)
// rating. `size` controls the font size of each glyph.
function Stars({
  rating,
  size = 14,
  color = "#f59e0b",
  muted = "#9ca3af",
}: {
  rating: number;
  size?: number;
  color?: string;
  muted?: string;
}) {
  const full = Math.round(rating);
  return (
    <Text style={{ fontSize: size, letterSpacing: 1 }}>
      {[1, 2, 3, 4, 5].map((i) => (
        <Text key={i} style={{ color: i <= full ? color : muted }}>
          {i <= full ? STAR : STAR_EMPTY}
        </Text>
      ))}
    </Text>
  );
}

function SummaryHeader({
  summary,
  colors,
}: {
  summary: ReviewSummary;
  colors: Colors;
}) {
  const total = summary.total ?? 0;
  return (
    <View
      style={[
        styles.summaryCard,
        { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
      ]}
    >
      <View style={styles.summaryLeft}>
        <Text style={[styles.average, { color: colors.foreground }]}>
          {summary.rated > 0 ? summary.average.toFixed(1) : "—"}
        </Text>
        <Stars rating={summary.average} size={16} muted={colors.border} />
        <Text style={[styles.summaryCount, { color: colors.mutedForeground }]}>
          {total} {total === 1 ? "review" : "reviews"}
        </Text>
      </View>
      <View style={styles.summaryRight}>
        {[5, 4, 3, 2, 1].map((star) => {
          const pct = summary.percent?.[String(star)] ?? 0;
          const count = summary.breakdown?.[String(star)] ?? 0;
          return (
            <View key={star} style={styles.breakdownRow}>
              <Text style={[styles.breakdownStar, { color: colors.mutedForeground }]}>{star}</Text>
              <Feather name="star" size={10} color="#f59e0b" />
              <View style={[styles.breakdownTrack, { backgroundColor: colors.border }]}>
                <View
                  style={[styles.breakdownFill, { width: `${pct}%`, backgroundColor: "#f59e0b" }]}
                />
              </View>
              <Text style={[styles.breakdownCount, { color: colors.mutedForeground }]}>{count}</Text>
            </View>
          );
        })}
      </View>
    </View>
  );
}

function MediaStrip({ media, colors }: { media: Review["media"]; colors: Colors }) {
  if (!media || media.length === 0) return null;
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={{ gap: 8, marginTop: 8 }}
    >
      {media.map((m, i) => {
        if (m.type === "image") {
          return <Image key={i} source={{ uri: m.url }} style={styles.mediaThumb} />;
        }
        const icon = m.type === "audio" ? "music" : "video";
        const label = m.type === "audio" ? "Audio" : "Video";
        return (
          <Pressable
            key={i}
            onPress={() => Linking.openURL(m.url)}
            style={[
              styles.mediaThumb,
              styles.mediaPlaceholder,
              { backgroundColor: colors.primary + "1c", borderColor: colors.border },
            ]}
          >
            <Feather name={icon as keyof typeof Feather.glyphMap} size={20} color={colors.primary} />
            <Text style={[styles.mediaLabel, { color: colors.primary }]}>{label}</Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

function ReviewCard({ review, colors }: { review: Review; colors: Colors }) {
  const initial = (review.author_name || "?").trim().charAt(0).toUpperCase() || "?";
  return (
    <View
      style={[
        styles.reviewCard,
        { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
      ]}
    >
      <View style={styles.reviewHead}>
        {review.author_avatar ? (
          <Image source={{ uri: review.author_avatar }} style={styles.avatar} />
        ) : (
          <View style={[styles.avatar, styles.avatarFallback, { backgroundColor: colors.primary + "22" }]}>
            <Text style={[styles.avatarInitial, { color: colors.primary }]}>{initial}</Text>
          </View>
        )}
        <View style={{ flex: 1 }}>
          <View style={styles.reviewNameRow}>
            <Text style={[styles.reviewName, { color: colors.foreground }]} numberOfLines={1}>
              {review.author_name}
            </Text>
            {review.pinned ? (
              <View style={[styles.pinTag, { backgroundColor: colors.primary + "22" }]}>
                <Feather name="bookmark" size={9} color={colors.primary} />
                <Text style={[styles.pinTagText, { color: colors.primary }]}>Pinned</Text>
              </View>
            ) : null}
          </View>
          <View style={styles.reviewMetaRow}>
            {typeof review.rating === "number" ? (
              <Stars rating={review.rating} size={12} muted={colors.border} />
            ) : null}
            <Text style={[styles.sourceLabel, { color: colors.mutedForeground }]}>
              {review.source_label}
            </Text>
          </View>
        </View>
      </View>

      {review.body ? (
        <Text style={[styles.reviewBody, { color: colors.foreground }]}>{review.body}</Text>
      ) : null}

      {review.answers?.map((a, i) => (
        <View key={i} style={styles.answerRow}>
          <Text style={[styles.answerPrompt, { color: colors.mutedForeground }]}>{a.prompt}</Text>
          <Text style={[styles.answerText, { color: colors.foreground }]}>{a.answer}</Text>
        </View>
      ))}

      <MediaStrip media={review.media} colors={colors} />

      {review.reply ? (
        <View style={[styles.replyBox, { backgroundColor: colors.background, borderColor: colors.border }]}>
          <Text style={[styles.replyLabel, { color: colors.primary }]}>Reply from owner</Text>
          <Text style={[styles.replyBody, { color: colors.foreground }]}>{review.reply}</Text>
        </View>
      ) : null}

      {review.source_url ? (
        <Pressable onPress={() => Linking.openURL(review.source_url!)} hitSlop={6}>
          <Text style={[styles.sourceLink, { color: colors.primary }]}>View on {review.source_label}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

function SubmitModal({
  alias,
  visible,
  onClose,
  colors,
  onSubmitted,
}: {
  alias: string;
  visible: boolean;
  onClose: () => void;
  colors: Colors;
  onSubmitted: () => void;
}) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [rating, setRating] = useState(0);
  const [body, setBody] = useState("");
  const [media, setMedia] = useState<ReviewMediaUpload[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState<string | null>(null);

  const reset = () => {
    setName("");
    setEmail("");
    setRating(0);
    setBody("");
    setMedia([]);
    setError(null);
    setDone(null);
  };

  const appendAssets = (assets: ImagePicker.ImagePickerAsset[]) => {
    if (!assets.length) return;
    setError(null);
    setMedia((prev) => {
      const picked = assets.map((a) => ({
        uri: a.uri,
        mimeType: a.mimeType ?? null,
        fileName: a.fileName ?? null,
      }));
      return [...prev, ...picked].slice(0, MAX_MEDIA);
    });
  };

  const pickFromLibrary = async () => {
    if (media.length >= MAX_MEDIA) return;
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
        "Photos access needed",
        "Allow access to your photo library in Settings to attach photos or videos.",
      );
      return;
    }
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.All,
      allowsMultipleSelection: true,
      selectionLimit: MAX_MEDIA - media.length,
      quality: 0.85,
    });
    if (res.canceled || !res.assets?.length) return;
    appendAssets(res.assets);
  };

  const takePhoto = async () => {
    if (media.length >= MAX_MEDIA) return;
    const perm = await ImagePicker.requestCameraPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
        "Camera access needed",
        "Allow camera access in Settings to take a photo or video for your review.",
      );
      return;
    }
    const res = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.All,
      quality: 0.85,
    });
    if (res.canceled || !res.assets?.length) return;
    appendAssets(res.assets);
  };

  const addMedia = () => {
    if (media.length >= MAX_MEDIA) return;
    Alert.alert("Add media", undefined, [
      { text: "Choose from library", onPress: pickFromLibrary },
      { text: "Take photo or video", onPress: takePhoto },
      { text: "Cancel", style: "cancel" },
    ]);
  };

  const removeMedia = (index: number) => {
    setMedia((prev) => prev.filter((_, i) => i !== index));
  };

  const mutation = useMutation({
    mutationFn: () => {
      const fields = {
        author_name: name.trim() || undefined,
        author_email: email.trim() || undefined,
        rating: rating > 0 ? rating : undefined,
        body: body.trim() || undefined,
      };
      return media.length > 0
        ? submitReviewWithMedia(alias, fields, media)
        : submitReview(alias, fields);
    },
    onSuccess: (res) => {
      setDone(res.message);
      onSubmitted();
    },
    onError: (e: unknown) => {
      const msg =
        e && typeof e === "object" && "message" in e
          ? String((e as { message: unknown }).message)
          : "Could not submit your review";
      setError(msg);
    },
  });

  const close = () => {
    reset();
    onClose();
  };

  const canSubmit = rating > 0 || body.trim().length > 0;

  const inputStyle = {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: colors.radius,
    paddingHorizontal: 12,
    paddingVertical: 10,
    color: colors.foreground,
    backgroundColor: colors.card,
    fontSize: 15,
  };

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={close}>
      <View style={styles.modalBackdrop}>
        <View
          style={[
            styles.modalCard,
            { backgroundColor: colors.background, borderColor: colors.border },
          ]}
        >
          {done ? (
            <View style={{ gap: 12, alignItems: "center", paddingVertical: 12 }}>
              <Feather name="check-circle" size={40} color="#16a34a" />
              <Text style={[styles.modalTitle, { color: colors.foreground, textAlign: "center" }]}>
                Thank you!
              </Text>
              <Text style={[styles.helper, { color: colors.mutedForeground, textAlign: "center" }]}>
                {done}
              </Text>
              <Pressable
                onPress={close}
                style={[styles.submitBtn, { backgroundColor: colors.primary, marginTop: 4 }]}
              >
                <Text style={[styles.submitBtnText, { color: colors.primaryForeground }]}>Done</Text>
              </Pressable>
            </View>
          ) : (
            <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={{ gap: 12 }}>
              <View style={styles.modalHeadRow}>
                <Text style={[styles.modalTitle, { color: colors.foreground }]}>Write a review</Text>
                <Pressable onPress={close} hitSlop={8}>
                  <Feather name="x" size={22} color={colors.mutedForeground} />
                </Pressable>
              </View>

              <View style={styles.starPicker}>
                {[1, 2, 3, 4, 5].map((i) => (
                  <Pressable key={i} onPress={() => setRating(i)} hitSlop={4}>
                    <Text style={{ fontSize: 34, color: i <= rating ? "#f59e0b" : colors.border }}>
                      {i <= rating ? STAR : STAR_EMPTY}
                    </Text>
                  </Pressable>
                ))}
              </View>

              <TextInput
                value={name}
                onChangeText={setName}
                placeholder="Your name (optional)"
                placeholderTextColor={colors.mutedForeground}
                style={inputStyle}
                autoCapitalize="words"
                editable={!mutation.isPending}
              />
              <TextInput
                value={email}
                onChangeText={setEmail}
                placeholder="Email (optional)"
                placeholderTextColor={colors.mutedForeground}
                style={inputStyle}
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                editable={!mutation.isPending}
              />
              <TextInput
                value={body}
                onChangeText={setBody}
                placeholder="Share your experience…"
                placeholderTextColor={colors.mutedForeground}
                style={[inputStyle, { minHeight: 100, textAlignVertical: "top" }]}
                multiline
                editable={!mutation.isPending}
              />

              <View style={styles.mediaSection}>
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={{ gap: 8 }}
                >
                  {media.map((m, i) => {
                    const isVideo = (m.mimeType || "").startsWith("video");
                    return (
                      <View key={`${m.uri}-${i}`} style={styles.mediaPreviewWrap}>
                        <Image source={{ uri: m.uri }} style={styles.mediaThumb} />
                        {isVideo ? (
                          <View style={styles.mediaVideoBadge}>
                            <Feather name="video" size={12} color="#fff" />
                          </View>
                        ) : null}
                        <Pressable
                          onPress={() => removeMedia(i)}
                          disabled={mutation.isPending}
                          hitSlop={6}
                          style={styles.mediaRemoveBtn}
                        >
                          <Feather name="x" size={12} color="#fff" />
                        </Pressable>
                      </View>
                    );
                  })}
                  {media.length < MAX_MEDIA ? (
                    <Pressable
                      onPress={addMedia}
                      disabled={mutation.isPending}
                      style={[
                        styles.mediaThumb,
                        styles.mediaAddBtn,
                        { borderColor: colors.border, backgroundColor: colors.card },
                      ]}
                    >
                      <Feather name="camera" size={20} color={colors.primary} />
                      <Text style={[styles.mediaAddLabel, { color: colors.mutedForeground }]}>
                        Add media
                      </Text>
                    </Pressable>
                  ) : null}
                </ScrollView>
                <Text style={[styles.mediaHint, { color: colors.mutedForeground }]}>
                  Add up to {MAX_MEDIA} photos or videos (optional)
                </Text>
              </View>

              {error ? <Text style={styles.errorText}>{error}</Text> : null}

              <Pressable
                onPress={() => {
                  setError(null);
                  mutation.mutate();
                }}
                disabled={!canSubmit || mutation.isPending}
                style={[
                  styles.submitBtn,
                  { backgroundColor: colors.primary, opacity: !canSubmit || mutation.isPending ? 0.5 : 1 },
                ]}
              >
                {mutation.isPending ? (
                  <ActivityIndicator size="small" color={colors.primaryForeground} />
                ) : (
                  <Text style={[styles.submitBtnText, { color: colors.primaryForeground }]}>
                    Submit review
                  </Text>
                )}
              </Pressable>
            </ScrollView>
          )}
        </View>
      </View>
    </Modal>
  );
}

/**
 * Reusable reviews surface used both inline (the reviews_wall biolink block)
 * and as a full standalone screen. Renders a rating summary, the unified
 * review feed, a recent/top sort toggle, and a no-login submission form.
 */
export function ReviewsWall({
  alias,
  colors,
  title,
  scroll = false,
}: {
  alias: string;
  colors: Colors;
  title?: string | null;
  scroll?: boolean;
}) {
  const qc = useQueryClient();
  const [sort, setSort] = useState<ReviewSort>("recent");
  const [showForm, setShowForm] = useState(false);

  const q = useQuery({
    queryKey: ["reviews", alias, sort],
    queryFn: () => getReviews(alias, { sort, limit: 50 }),
    enabled: !!alias,
  });

  const content = (
    <>
      {title ? <Text style={[styles.wallTitle, { color: colors.foreground }]}>{title}</Text> : null}

      {q.isLoading ? (
        <View style={{ paddingVertical: 24, alignItems: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError ? (
        <Text style={[styles.helper, { color: colors.mutedForeground, textAlign: "center", paddingVertical: 16 }]}>
          Reviews couldn&apos;t be loaded right now.
        </Text>
      ) : q.data ? (
        <>
          <SummaryHeader summary={q.data.summary} colors={colors} />

          <View style={styles.controlsRow}>
            <View style={styles.sortToggle}>
              {(["recent", "rating"] as ReviewSort[]).map((s) => {
                const active = sort === s;
                return (
                  <Pressable
                    key={s}
                    onPress={() => setSort(s)}
                    style={[
                      styles.sortPill,
                      {
                        backgroundColor: active ? colors.primary : colors.card,
                        borderColor: active ? colors.primary : colors.border,
                      },
                    ]}
                  >
                    <Text
                      style={{
                        fontFamily: "SpaceGrotesk_600SemiBold",
                        fontSize: 12,
                        color: active ? colors.primaryForeground : colors.mutedForeground,
                      }}
                    >
                      {s === "recent" ? "Most recent" : "Top rated"}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            <Pressable
              onPress={() => setShowForm(true)}
              style={[styles.writeBtn, { borderColor: colors.primary }]}
            >
              <Feather name="edit-3" size={13} color={colors.primary} />
              <Text style={[styles.writeBtnText, { color: colors.primary }]}>Write a review</Text>
            </Pressable>
          </View>

          {q.data.reviews.length === 0 ? (
            <View style={{ paddingVertical: 24, alignItems: "center", gap: 6 }}>
              <Feather name="message-square" size={28} color={colors.mutedForeground} />
              <Text style={[styles.helper, { color: colors.mutedForeground }]}>
                No reviews yet — be the first to leave one.
              </Text>
            </View>
          ) : (
            <View style={{ gap: 10 }}>
              {q.data.reviews.map((r) => (
                <ReviewCard key={r.id} review={r} colors={colors} />
              ))}
            </View>
          )}
        </>
      ) : null}

      <SubmitModal
        alias={alias}
        visible={showForm}
        onClose={() => setShowForm(false)}
        colors={colors}
        onSubmitted={() => qc.invalidateQueries({ queryKey: ["reviews", alias] })}
      />
    </>
  );

  if (scroll) {
    return (
      <ScrollView
        style={{ flex: 1, backgroundColor: colors.background }}
        contentContainerStyle={{ padding: 16, gap: 12 }}
      >
        {content}
      </ScrollView>
    );
  }
  return <View style={{ gap: 12 }}>{content}</View>;
}

const styles = StyleSheet.create({
  wallTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 20 },
  helper: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },

  summaryCard: {
    flexDirection: "row",
    gap: 16,
    padding: 16,
    borderWidth: 1,
  },
  summaryLeft: { alignItems: "center", justifyContent: "center", gap: 4, minWidth: 84 },
  average: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 40, lineHeight: 44 },
  summaryCount: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  summaryRight: { flex: 1, justifyContent: "center", gap: 4 },
  breakdownRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  breakdownStar: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, width: 8, textAlign: "right" },
  breakdownTrack: { flex: 1, height: 6, borderRadius: 3, overflow: "hidden" },
  breakdownFill: { height: 6, borderRadius: 3 },
  breakdownCount: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11, width: 22, textAlign: "right" },

  controlsRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
    flexWrap: "wrap",
  },
  sortToggle: { flexDirection: "row", gap: 6 },
  sortPill: { paddingVertical: 6, paddingHorizontal: 12, borderRadius: 999, borderWidth: 1 },
  writeBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
  },
  writeBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },

  reviewCard: { padding: 14, borderWidth: 1, gap: 6 },
  reviewHead: { flexDirection: "row", gap: 10, alignItems: "center" },
  avatar: { width: 38, height: 38, borderRadius: 19 },
  avatarFallback: { alignItems: "center", justifyContent: "center" },
  avatarInitial: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  reviewNameRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  reviewName: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15, flexShrink: 1 },
  reviewMetaRow: { flexDirection: "row", alignItems: "center", gap: 8, marginTop: 2 },
  sourceLabel: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  pinTag: {
    flexDirection: "row",
    alignItems: "center",
    gap: 3,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 999,
  },
  pinTagText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 9 },
  reviewBody: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20, marginTop: 2 },
  answerRow: { marginTop: 4 },
  answerPrompt: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11 },
  answerText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  mediaThumb: { width: 72, height: 72, borderRadius: 10 },
  mediaPlaceholder: { alignItems: "center", justifyContent: "center", borderWidth: 1, gap: 2 },
  mediaLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 10 },
  replyBox: { marginTop: 6, padding: 10, borderRadius: 10, borderWidth: 1, gap: 2 },
  replyLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 11 },
  replyBody: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 18 },
  sourceLink: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12, marginTop: 4 },

  modalBackdrop: { flex: 1, backgroundColor: "rgba(0,0,0,0.5)", justifyContent: "flex-end" },
  modalCard: {
    padding: 20,
    borderTopWidth: 1,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    maxHeight: "88%",
  },
  modalHeadRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 20 },
  starPicker: { flexDirection: "row", gap: 6, justifyContent: "center", paddingVertical: 4 },
  mediaSection: { gap: 6 },
  mediaPreviewWrap: { position: "relative" },
  mediaAddBtn: {
    borderWidth: 1,
    borderStyle: "dashed",
    alignItems: "center",
    justifyContent: "center",
    gap: 2,
  },
  mediaAddLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 9, textAlign: "center" },
  mediaRemoveBtn: {
    position: "absolute",
    top: -6,
    right: -6,
    width: 20,
    height: 20,
    borderRadius: 10,
    backgroundColor: "rgba(0,0,0,0.7)",
    alignItems: "center",
    justifyContent: "center",
  },
  mediaVideoBadge: {
    position: "absolute",
    bottom: 4,
    left: 4,
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: "rgba(0,0,0,0.6)",
    alignItems: "center",
    justifyContent: "center",
  },
  mediaHint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  submitBtn: { minHeight: 50, borderRadius: 12, alignItems: "center", justifyContent: "center", paddingHorizontal: 20 },
  submitBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  errorText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, color: "#dc2626" },
});
