import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { errorStatus, type ApiError } from "@/lib/api";
import {
  approveVerificationReview,
  getVerificationReview,
  listVerificationReviews,
  rejectVerificationReview,
  type ReviewQueue,
  type ReviewRequest,
} from "@/lib/api/verificationAdmin";
import { showAlert } from "@/lib/webAlert";

const STATUS_FILTERS = [
  { key: "pending", label: "Pending" },
  { key: "approved", label: "Approved" },
  { key: "rejected", label: "Rejected" },
  { key: "", label: "All" },
];

function formatDate(iso: string | null | undefined): string {
  if (!iso) return "—";
  const d = new Date(iso);
  return isNaN(d.getTime()) ? "—" : d.toLocaleDateString();
}

export default function VerificationReviewScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [queue, setQueue] = useState<ReviewQueue>("new");
  const [status, setStatus] = useState("pending");
  const [page, setPage] = useState(1);
  const [openId, setOpenId] = useState<number | null>(null);
  const [notes, setNotes] = useState("");

  const listQ = useQuery({
    queryKey: ["verification-review", queue, status, page],
    queryFn: () =>
      listVerificationReviews({
        queue,
        status: status || undefined,
        page,
        per_page: 20,
      }),
    retry: false,
  });

  const detailQ = useQuery({
    queryKey: ["verification-review-detail", openId],
    queryFn: () => getVerificationReview(openId as number),
    enabled: openId != null,
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["verification-review"] });
    if (openId != null) {
      qc.invalidateQueries({ queryKey: ["verification-review-detail", openId] });
    }
  };

  const handleReviewError = (e: unknown) => {
    const err = e as ApiError;
    if (errorStatus(e) === 409 || err?.code === "already_reviewed") {
      showAlert(
        "Already reviewed",
        "Another reviewer has already handled this request. The list has been refreshed.",
      );
      invalidate();
      setOpenId(null);
      return;
    }
    showAlert("Action failed", err?.message ?? "Unknown error");
  };

  const approve = useMutation({
    mutationFn: (id: number) =>
      approveVerificationReview(id, {
        admin_notes: notes.trim() || undefined,
      }),
    onSuccess: () => {
      invalidate();
      setOpenId(null);
      setNotes("");
      showAlert("Approved", "The verification request was approved.");
    },
    onError: handleReviewError,
  });

  const reject = useMutation({
    mutationFn: (id: number) => rejectVerificationReview(id, { admin_notes: notes.trim() }),
    onSuccess: () => {
      invalidate();
      setOpenId(null);
      setNotes("");
      showAlert("Rejected", "The verification request was rejected.");
    },
    onError: handleReviewError,
  });

  const busy = approve.isPending || reject.isPending;
  const forbidden = errorStatus(listQ.error) === 403;

  const chip = (active: boolean) => ({
    borderColor: active ? colors.primary : colors.border,
    backgroundColor: active ? colors.primary : colors.card,
  });
  const chipText = (active: boolean) => ({
    color: active ? "#fff" : colors.mutedForeground,
  });

  const detail: ReviewRequest | null | undefined = detailQ.data;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Verification reviews" }} />

      {forbidden ? (
        <View style={styles.center}>
          <EmptyState
            icon="lock"
            title="Reviewer access required"
            body="Your account doesn't have permission to review verification requests."
          />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: 20, gap: 12, paddingBottom: 40 }}>
          {/* Queue tabs */}
          <View style={styles.chipRow}>
            {(
              [
                { key: "new", label: "New", count: listQ.data?.pending_new_count },
                {
                  key: "reverification",
                  label: "Re-verification",
                  count: listQ.data?.pending_reverification_count,
                },
              ] as { key: ReviewQueue; label: string; count?: number }[]
            ).map((t) => {
              const active = queue === t.key;
              return (
                <Pressable
                  key={t.key}
                  onPress={() => {
                    setQueue(t.key);
                    setPage(1);
                  }}
                  accessibilityRole="button"
                  accessibilityState={{ selected: active }}
                  style={[styles.tab, chip(active)]}
                >
                  <Text style={[styles.tabLabel, chipText(active)]}>
                    {t.label}
                    {typeof t.count === "number" ? ` (${t.count})` : ""}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          {/* Status filter */}
          <View style={styles.chipRow}>
            {STATUS_FILTERS.map((f) => {
              const active = status === f.key;
              return (
                <Pressable
                  key={f.key || "all"}
                  onPress={() => {
                    setStatus(f.key);
                    setPage(1);
                  }}
                  accessibilityRole="button"
                  style={[styles.chipSm, chip(active)]}
                >
                  <Text style={[styles.chipSmLabel, chipText(active)]}>{f.label}</Text>
                </Pressable>
              );
            })}
          </View>

          {listQ.isLoading ? (
            <ActivityIndicator color={colors.primary} style={{ marginTop: 30 }} />
          ) : listQ.isError ? (
            <EmptyState
              icon="alert-circle"
              title="Couldn't load requests"
              body={(listQ.error as Partial<ApiError>)?.message ?? "Please try again."}
            />
          ) : (listQ.data?.requests.length ?? 0) === 0 ? (
            <EmptyState
              icon="inbox"
              title="Nothing here"
              body="No verification requests match this filter."
            />
          ) : (
            listQ.data!.requests.map((r) => (
              <Pressable
                key={r.id}
                onPress={() => {
                  setNotes("");
                  setOpenId(r.id);
                }}
                accessibilityRole="button"
                style={[
                  styles.rowCard,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View style={{ flex: 1, gap: 3 }}>
                  <Text style={[styles.rowTitle, { color: colors.foreground }]} numberOfLines={1}>
                    {r.official_name || r.user?.name || `Request #${r.id}`}
                  </Text>
                  <Text style={[styles.rowSub, { color: colors.mutedForeground }]} numberOfLines={1}>
                    {r.user?.handle ? `@${r.user.handle} · ` : ""}
                    {formatDate(r.created_at)}
                  </Text>
                  <View style={styles.chipRow}>
                    <View
                      style={[
                        styles.badge,
                        {
                          backgroundColor:
                            r.status === "approved"
                              ? "#10b98122"
                              : r.status === "rejected"
                                ? "#ef444422"
                                : "#f59e0b22",
                        },
                      ]}
                    >
                      <Text
                        style={[
                          styles.badgeText,
                          {
                            color:
                              r.status === "approved"
                                ? "#10b981"
                                : r.status === "rejected"
                                  ? "#ef4444"
                                  : "#f59e0b",
                          },
                        ]}
                      >
                        {r.status}
                      </Text>
                    </View>
                    {r.tick_type ? (
                      <View style={[styles.badge, { backgroundColor: `${r.tick_type.color}22` }]}>
                        <Text style={[styles.badgeText, { color: r.tick_type.color }]}>
                          {r.tick_type.name}
                        </Text>
                      </View>
                    ) : null}
                  </View>
                </View>
                <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
              </Pressable>
            ))
          )}

          {/* Pagination */}
          {listQ.data && listQ.data.meta.last_page > 1 ? (
            <View style={[styles.chipRow, { justifyContent: "center" }]}>
              <Pressable
                disabled={page <= 1}
                onPress={() => setPage((p) => Math.max(1, p - 1))}
                accessibilityRole="button"
                style={[styles.chipSm, chip(false), { opacity: page <= 1 ? 0.4 : 1 }]}
              >
                <Text style={[styles.chipSmLabel, { color: colors.foreground }]}>Previous</Text>
              </Pressable>
              <Text style={[styles.rowSub, { color: colors.mutedForeground, alignSelf: "center" }]}>
                {page} / {listQ.data.meta.last_page}
              </Text>
              <Pressable
                disabled={page >= listQ.data.meta.last_page}
                onPress={() => setPage((p) => p + 1)}
                accessibilityRole="button"
                style={[
                  styles.chipSm,
                  chip(false),
                  { opacity: page >= listQ.data.meta.last_page ? 0.4 : 1 },
                ]}
              >
                <Text style={[styles.chipSmLabel, { color: colors.foreground }]}>Next</Text>
              </Pressable>
            </View>
          ) : null}
        </ScrollView>
      )}

      {/* Detail modal */}
      <Modal
        visible={openId != null}
        animationType="slide"
        transparent
        onRequestClose={() => setOpenId(null)}
      >
        <View style={styles.modalBackdrop}>
          <View
            style={[
              styles.modalSheet,
              { backgroundColor: colors.background, borderColor: colors.border },
            ]}
          >
            <View style={styles.modalHeader}>
              <Text style={[styles.rowTitle, { color: colors.foreground }]}>
                Request #{openId}
              </Text>
              <Pressable onPress={() => setOpenId(null)} accessibilityRole="button" hitSlop={10}>
                <Feather name="x" size={20} color={colors.mutedForeground} />
              </Pressable>
            </View>
            {detailQ.isLoading || !detail ? (
              <ActivityIndicator color={colors.primary} style={{ marginVertical: 30 }} />
            ) : (
              <ScrollView contentContainerStyle={{ gap: 10, paddingBottom: 20 }}>
                <DetailRow label="Official name" value={detail.official_name} colors={colors} />
                <DetailRow
                  label="User"
                  value={
                    detail.user
                      ? `${detail.user.name ?? ""}${detail.user.handle ? ` (@${detail.user.handle})` : ""}`
                      : "—"
                  }
                  colors={colors}
                />
                <DetailRow label="Purpose" value={detail.purpose || "—"} colors={colors} />
                <DetailRow label="Status" value={detail.status} colors={colors} />
                <DetailRow
                  label="Tick type"
                  value={detail.tick_type?.name ?? "—"}
                  colors={colors}
                />
                <DetailRow
                  label="Proof files"
                  value={String(detail.proof_files?.length ?? 0)}
                  colors={colors}
                />
                <DetailRow label="Submitted" value={formatDate(detail.created_at)} colors={colors} />
                {detail.reviewed_at ? (
                  <DetailRow
                    label="Reviewed"
                    value={`${formatDate(detail.reviewed_at)}${detail.reviewer?.name ? ` by ${detail.reviewer.name}` : ""}`}
                    colors={colors}
                  />
                ) : null}
                {detail.admin_notes ? (
                  <DetailRow label="Reviewer notes" value={detail.admin_notes} colors={colors} />
                ) : null}

                {detail.status === "pending" ? (
                  <>
                    <Text style={[styles.rowSub, { color: colors.mutedForeground, marginTop: 6 }]}>
                      Notes (required to reject)
                    </Text>
                    <TextInput
                      value={notes}
                      onChangeText={setNotes}
                      placeholder="Reviewer notes…"
                      placeholderTextColor={colors.mutedForeground}
                      multiline
                      style={[
                        styles.notesInput,
                        { color: colors.foreground, borderColor: colors.border },
                      ]}
                    />
                    <Button
                      label="Approve"
                      onPress={() => approve.mutate(detail.id)}
                      loading={approve.isPending}
                      disabled={busy}
                    />
                    <Button
                      label="Reject"
                      variant="secondary"
                      onPress={() => {
                        if (!notes.trim()) {
                          showAlert("Notes required", "Add a note explaining the rejection.");
                          return;
                        }
                        reject.mutate(detail.id);
                      }}
                      loading={reject.isPending}
                      disabled={busy}
                    />
                  </>
                ) : null}
              </ScrollView>
            )}
          </View>
        </View>
      </Modal>
    </View>
  );
}

function DetailRow({
  label,
  value,
  colors,
}: {
  label: string;
  value: string;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={{ gap: 2 }}>
      <Text style={[styles.rowSub, { color: colors.mutedForeground }]}>{label}</Text>
      <Text style={[styles.rowTitle, { color: colors.foreground }]}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 20 },
  chipRow: { flexDirection: "row", gap: 8, flexWrap: "wrap" },
  tab: { paddingVertical: 9, paddingHorizontal: 16, borderWidth: 1, borderRadius: 999 },
  tabLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  chipSm: { paddingVertical: 6, paddingHorizontal: 12, borderWidth: 1, borderRadius: 999 },
  chipSmLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  rowCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    borderWidth: 1,
    padding: 14,
  },
  rowTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  rowSub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  badge: { paddingVertical: 3, paddingHorizontal: 8, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    textTransform: "capitalize",
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "flex-end",
  },
  modalSheet: {
    maxHeight: "85%",
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: 1,
    padding: 20,
    gap: 12,
  },
  modalHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  notesInput: {
    borderWidth: 1,
    borderRadius: 10,
    padding: 12,
    minHeight: 70,
    textAlignVertical: "top",
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
  },
});
