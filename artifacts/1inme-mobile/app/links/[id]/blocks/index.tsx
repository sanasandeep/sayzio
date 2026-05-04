import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  BLOCK_KINDS,
  blockKind,
  createBlock,
  deleteBlock,
  listBlocks,
  reorderBlocks,
  updateBlock,
  type Block,
} from "@/lib/api/blocks";
import {
  applyCardTemplate,
  listCardTemplates,
  type CardTemplate,
} from "@/lib/api/cardTemplates";

function confirm(title: string, msg: string, onYes: () => void) {
  if (Platform.OS === "web") {
    if (typeof window !== "undefined" && window.confirm(`${title}\n\n${msg}`)) {
      onYes();
    }
    return;
  }
  Alert.alert(title, msg, [
    { text: "Cancel", style: "cancel" },
    { text: "OK", style: "destructive", onPress: onYes },
  ]);
}

export default function BlocksScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useQuery({
    queryKey: ["blocks", id],
    queryFn: () => listBlocks(id),
    enabled: Number.isFinite(id),
  });

  const [order, setOrder] = useState<Block[]>([]);
  useEffect(() => {
    if (q.data) setOrder(q.data);
  }, [q.data]);

  const [picker, setPicker] = useState(false);
  const [cardGallery, setCardGallery] = useState(false);
  const [previewTpl, setPreviewTpl] = useState<CardTemplate | null>(null);
  // After applying a card template (or any other insert) we want to
  // jump the editor list to the new block and pulse-highlight it so the
  // user actually sees what was added — refresh alone leaves them at
  // the top of a long list with no feedback.
  const [highlightId, setHighlightId] = useState<number | null>(null);
  const scrollRef = useRef<ScrollView>(null);
  const rowOffsets = useRef<Record<number, number>>({});

  useEffect(() => {
    if (highlightId == null) return;
    const target = order.find((b) => b.id === highlightId);
    if (!target) return; // wait for refetch to bring it in
    const y = rowOffsets.current[highlightId];
    if (y != null) {
      scrollRef.current?.scrollTo({ y: Math.max(0, y - 24), animated: true });
    }
    const t = setTimeout(() => setHighlightId(null), 1800);
    return () => clearTimeout(t);
  }, [highlightId, order]);

  const create = useMutation({
    mutationFn: (type: string) => createBlock(id, { type, settings: {} }),
    onSuccess: (b) => {
      qc.invalidateQueries({ queryKey: ["blocks", id] });
      setPicker(false);
      router.push(`/links/${id}/blocks/${b.id}` as any);
    },
  });

  const toggle = useMutation({
    mutationFn: (b: Block) =>
      updateBlock(id, b.id, { is_active: !b.is_active }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["blocks", id] }),
  });

  const remove = useMutation({
    mutationFn: (blockId: number) => deleteBlock(id, blockId),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["blocks", id] }),
  });

  const persistOrder = useMutation({
    mutationFn: (ids: number[]) => reorderBlocks(id, ids),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["blocks", id] }),
  });

  function move(idx: number, dir: -1 | 1) {
    const next = order.slice();
    const j = idx + dir;
    if (j < 0 || j >= next.length) return;
    [next[idx], next[j]] = [next[j], next[idx]];
    setOrder(next);
    persistOrder.mutate(next.map((b) => b.id));
  }

  if (q.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Blocks" }} />
      <ScrollView ref={scrollRef} contentContainerStyle={styles.body}>
        {order.length === 0 ? (
          <EmptyState
            icon="grid"
            title="No blocks yet"
            body="Add a header, a link button, an image, or any other block to start building your biolink."
            action={
              <View style={{ gap: 8 }}>
                <Button label="Add a block" onPress={() => setPicker(true)} />
                <Button
                  label="Browse card templates"
                  onPress={() => setCardGallery(true)}
                />
              </View>
            }
          />
        ) : (
          <View style={{ gap: 10 }}>
            {order.map((b, i) => {
              const meta = blockKind(b.type);
              const label =
                (b.settings?.label as string) ||
                (b.settings?.title as string) ||
                meta?.label ||
                b.type;
              const isHighlight = highlightId === b.id;
              return (
                <View
                  key={b.id}
                  onLayout={(e) => {
                    rowOffsets.current[b.id] = e.nativeEvent.layout.y;
                  }}
                  style={[
                    styles.row,
                    {
                      backgroundColor: isHighlight
                        ? colors.primary + "22"
                        : colors.card,
                      borderColor: isHighlight ? colors.primary : colors.border,
                      borderWidth: isHighlight ? 2 : 1,
                      borderRadius: colors.radius,
                      opacity: b.is_active ? 1 : 0.5,
                    },
                  ]}
                >
                  <View style={styles.handle}>
                    <Pressable onPress={() => move(i, -1)} hitSlop={6}>
                      <Feather
                        name="chevron-up"
                        size={18}
                        color={i === 0 ? colors.border : colors.foreground}
                      />
                    </Pressable>
                    <Pressable onPress={() => move(i, 1)} hitSlop={6}>
                      <Feather
                        name="chevron-down"
                        size={18}
                        color={
                          i === order.length - 1
                            ? colors.border
                            : colors.foreground
                        }
                      />
                    </Pressable>
                  </View>
                  <Pressable
                    style={{ flex: 1, gap: 2 }}
                    onPress={() => router.push(`/links/${id}/blocks/${b.id}` as any)}
                  >
                    <Text
                      numberOfLines={1}
                      style={[styles.rowTitle, { color: colors.foreground }]}
                    >
                      {label}
                    </Text>
                    <Text
                      style={[styles.rowSub, { color: colors.mutedForeground }]}
                    >
                      {meta?.label || b.type}
                    </Text>
                  </Pressable>
                  <Switch
                    value={b.is_active}
                    onValueChange={() => toggle.mutate(b)}
                    trackColor={{ true: colors.primary, false: colors.border }}
                  />
                  <Pressable
                    onPress={() =>
                      confirm("Delete block?", "Remove this block?", () =>
                        remove.mutate(b.id),
                      )
                    }
                    hitSlop={8}
                  >
                    <Feather
                      name="trash-2"
                      size={16}
                      color={colors.destructive}
                    />
                  </Pressable>
                </View>
              );
            })}
            <Button label="Add a block" onPress={() => setPicker(true)} />
            <Button
              label="Browse card templates"
              variant="ghost"
              onPress={() => setCardGallery(true)}
            />
          </View>
        )}
      </ScrollView>

      <Modal
        visible={picker}
        animationType="slide"
        transparent
        onRequestClose={() => setPicker(false)}
      >
        <View style={styles.modalBackdrop}>
          <View
            style={[
              styles.modalCard,
              { backgroundColor: colors.background, borderColor: colors.border },
            ]}
          >
            <View style={styles.modalHeader}>
              <Text style={[styles.modalTitle, { color: colors.foreground }]}>
                Add block
              </Text>
              <Pressable onPress={() => setPicker(false)} hitSlop={8}>
                <Feather name="x" size={20} color={colors.mutedForeground} />
              </Pressable>
            </View>
            <ScrollView contentContainerStyle={{ gap: 8, paddingBottom: 20 }}>
              <Pressable
                key="card-templates"
                onPress={() => {
                  setPicker(false);
                  setCardGallery(true);
                }}
                style={({ pressed }) => [
                  styles.kindRow,
                  {
                    backgroundColor: colors.primary + "22",
                    borderColor: colors.primary,
                    borderRadius: colors.radius,
                    opacity: pressed ? 0.85 : 1,
                  },
                ]}
              >
                <View style={styles.kindRowHead}>
                  <Feather name="layers" size={16} color={colors.primary} />
                  <Text
                    style={[styles.kindLabel, { color: colors.foreground }]}
                  >
                    Card templates
                  </Text>
                </View>
                <Text
                  style={[styles.kindBlurb, { color: colors.mutedForeground }]}
                >
                  Pre-designed card layouts you can drop in and edit.
                </Text>
              </Pressable>

              {BLOCK_KINDS.map((k) => (
                <Pressable
                  key={k.type}
                  onPress={() => create.mutate(k.type)}
                  style={({ pressed }) => [
                    styles.kindRow,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                      opacity: pressed ? 0.85 : 1,
                    },
                  ]}
                >
                  <Text style={[styles.kindLabel, { color: colors.foreground }]}>
                    {k.label}
                  </Text>
                  <Text
                    style={[styles.kindBlurb, { color: colors.mutedForeground }]}
                  >
                    {k.blurb}
                  </Text>
                </Pressable>
              ))}
            </ScrollView>
          </View>
        </View>
      </Modal>

      <CardTemplatesGallery
        visible={cardGallery}
        linkId={id}
        insertAfter={order.length > 0 ? order[order.length - 1].id : null}
        onClose={() => setCardGallery(false)}
        onPreview={(t) => setPreviewTpl(t)}
        onApplied={(blockId) => {
          qc.invalidateQueries({ queryKey: ["blocks", id] });
          setCardGallery(false);
          setPreviewTpl(null);
          setHighlightId(blockId);
        }}
        previewTpl={previewTpl}
        clearPreview={() => setPreviewTpl(null)}
      />
    </View>
  );
}

type GalleryProps = {
  visible: boolean;
  linkId: number;
  insertAfter: number | null;
  onClose: () => void;
  onPreview: (t: CardTemplate) => void;
  onApplied: (blockId: number) => void;
  previewTpl: CardTemplate | null;
  clearPreview: () => void;
};

function CardTemplatesGallery(props: GalleryProps) {
  const colors = useColors();
  const {
    visible,
    linkId,
    insertAfter,
    onClose,
    onPreview,
    onApplied,
    previewTpl,
    clearPreview,
  } = props;

  const [activeCat, setActiveCat] = useState<string>("all");

  const q = useQuery({
    queryKey: ["card-templates", linkId],
    queryFn: () => listCardTemplates(linkId),
    enabled: visible && Number.isFinite(linkId),
    staleTime: 60_000,
  });

  const apply = useMutation({
    mutationFn: (templateId: number) =>
      applyCardTemplate(linkId, {
        template_id: templateId,
        insert_after: insertAfter,
      }),
    onSuccess: (res) => onApplied(res.block_id),
  });

  const items = q.data?.items ?? [];
  const cats = q.data?.categories ?? {};

  const visibleItems = useMemo(() => {
    if (activeCat === "all") return items;
    return items.filter((t) => t.category === activeCat);
  }, [items, activeCat]);

  const catOptions = useMemo(() => {
    const used = new Set(items.map((t) => t.category));
    return [
      { key: "all", label: "All" },
      ...Object.entries(cats)
        .filter(([key]) => used.has(key))
        .map(([key, label]) => ({ key, label })),
    ];
  }, [items, cats]);

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      onRequestClose={onClose}
    >
      <View style={styles.modalBackdrop}>
        <View
          style={[
            styles.galleryCard,
            { backgroundColor: colors.background, borderColor: colors.border },
          ]}
        >
          <View style={styles.modalHeader}>
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              Card templates
            </Text>
            <Pressable onPress={onClose} hitSlop={8}>
              <Feather name="x" size={20} color={colors.mutedForeground} />
            </Pressable>
          </View>

          {q.isLoading ? (
            <View style={styles.center}>
              <ActivityIndicator color={colors.primary} />
            </View>
          ) : q.isError ? (
            <View style={styles.center}>
              <Text style={{ color: colors.destructive, padding: 16 }}>
                Couldn't load templates. Pull down to retry.
              </Text>
            </View>
          ) : (
            <>
              <ScrollView
                horizontal
                showsHorizontalScrollIndicator={false}
                contentContainerStyle={styles.tabs}
              >
                {catOptions.map((c) => {
                  const active = c.key === activeCat;
                  return (
                    <Pressable
                      key={c.key}
                      onPress={() => setActiveCat(c.key)}
                      style={[
                        styles.tab,
                        {
                          backgroundColor: active
                            ? colors.primary
                            : colors.card,
                          borderColor: active ? colors.primary : colors.border,
                        },
                      ]}
                    >
                      <Text
                        style={{
                          color: active ? "#fff" : colors.foreground,
                          fontSize: 12,
                          fontFamily: "SpaceGrotesk_600SemiBold",
                        }}
                      >
                        {c.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </ScrollView>

              <ScrollView
                contentContainerStyle={{ gap: 10, paddingBottom: 24 }}
              >
                {visibleItems.length === 0 ? (
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      textAlign: "center",
                      padding: 24,
                    }}
                  >
                    No templates in this category yet.
                  </Text>
                ) : (
                  visibleItems.map((t) => (
                    <Pressable
                      key={t.id}
                      onPress={() => onPreview(t)}
                      style={({ pressed }) => [
                        styles.tplCard,
                        {
                          backgroundColor: colors.card,
                          borderColor: colors.border,
                          borderRadius: colors.radius,
                          opacity: pressed ? 0.85 : 1,
                        },
                      ]}
                    >
                      <View style={styles.tplThumbWrap}>
                        {t.thumbnail_url ? (
                          <Image
                            source={{ uri: t.thumbnail_url }}
                            style={styles.tplThumb}
                            resizeMode="cover"
                          />
                        ) : (
                          <View
                            style={[
                              styles.tplThumb,
                              {
                                backgroundColor: colors.primary + "22",
                                alignItems: "center",
                                justifyContent: "center",
                              },
                            ]}
                          >
                            <Feather
                              name="layers"
                              size={20}
                              color={colors.primary}
                            />
                          </View>
                        )}
                      </View>
                      <View style={{ flex: 1, gap: 4 }}>
                        <View style={styles.tplTitleRow}>
                          <Text
                            numberOfLines={1}
                            style={[
                              styles.tplName,
                              { color: colors.foreground },
                            ]}
                          >
                            {t.name}
                          </Text>
                          {t.locked ? (
                            <View
                              style={[
                                styles.lockBadge,
                                { backgroundColor: colors.primary + "22" },
                              ]}
                            >
                              <Feather
                                name="lock"
                                size={10}
                                color={colors.primary}
                              />
                              <Text
                                style={{
                                  color: colors.primary,
                                  fontSize: 10,
                                  fontFamily: "SpaceGrotesk_600SemiBold",
                                }}
                              >
                                {t.plan_tier?.toUpperCase() || "PRO"}
                              </Text>
                            </View>
                          ) : null}
                        </View>
                        <Text
                          style={{
                            color: colors.mutedForeground,
                            fontSize: 11,
                            fontFamily: "SpaceGrotesk_500Medium",
                          }}
                        >
                          {t.category_label} · {t.children_count} blocks
                        </Text>
                        {t.description ? (
                          <Text
                            numberOfLines={2}
                            style={{
                              color: colors.mutedForeground,
                              fontSize: 12,
                              fontFamily: "SpaceGrotesk_400Regular",
                            }}
                          >
                            {t.description}
                          </Text>
                        ) : null}
                      </View>
                    </Pressable>
                  ))
                )}
              </ScrollView>
            </>
          )}
        </View>
      </View>

      <Modal
        visible={!!previewTpl}
        animationType="fade"
        transparent
        onRequestClose={clearPreview}
      >
        {previewTpl ? (
          <View style={styles.modalBackdrop}>
            <View
              style={[
                styles.modalCard,
                {
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                },
              ]}
            >
              <View style={styles.modalHeader}>
                <Text
                  style={[styles.modalTitle, { color: colors.foreground }]}
                  numberOfLines={1}
                >
                  {previewTpl.name}
                </Text>
                <Pressable onPress={clearPreview} hitSlop={8}>
                  <Feather name="x" size={20} color={colors.mutedForeground} />
                </Pressable>
              </View>

              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 12,
                  fontFamily: "SpaceGrotesk_500Medium",
                  marginBottom: 4,
                }}
              >
                {previewTpl.category_label}
                {previewTpl.locked
                  ? ` · ${previewTpl.plan_tier?.toUpperCase() || "PRO"} only`
                  : ""}
              </Text>
              {previewTpl.description ? (
                <Text
                  style={{
                    color: colors.foreground,
                    fontSize: 13,
                    fontFamily: "SpaceGrotesk_400Regular",
                  }}
                >
                  {previewTpl.description}
                </Text>
              ) : null}

              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 11,
                  marginTop: 12,
                  marginBottom: 6,
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  textTransform: "uppercase",
                  letterSpacing: 0.6,
                }}
              >
                What's inside
              </Text>
              <ScrollView
                style={{ maxHeight: 260 }}
                contentContainerStyle={{ gap: 6, paddingBottom: 8 }}
              >
                {previewTpl.children.map((c, i) => (
                  <View
                    key={i}
                    style={[
                      styles.childRow,
                      {
                        backgroundColor: colors.card,
                        borderColor: colors.border,
                        borderRadius: colors.radius,
                      },
                    ]}
                  >
                    <Text
                      style={{
                        color: colors.foreground,
                        fontSize: 12,
                        fontFamily: "SpaceGrotesk_600SemiBold",
                      }}
                    >
                      {c.label}
                    </Text>
                    {c.preview ? (
                      <Text
                        numberOfLines={2}
                        style={{
                          color: colors.mutedForeground,
                          fontSize: 11,
                          fontFamily: "SpaceGrotesk_400Regular",
                        }}
                      >
                        {c.preview}
                      </Text>
                    ) : null}
                  </View>
                ))}
              </ScrollView>

              <View style={{ gap: 8, marginTop: 14 }}>
                {previewTpl.locked ? (
                  <View
                    style={[
                      styles.lockHint,
                      { borderColor: colors.primary + "55" },
                    ]}
                  >
                    <Feather name="lock" size={14} color={colors.primary} />
                    <Text
                      style={{
                        color: colors.primary,
                        fontSize: 12,
                        flex: 1,
                        fontFamily: "SpaceGrotesk_500Medium",
                      }}
                    >
                      Upgrade to {previewTpl.plan_tier?.toUpperCase() || "PRO"}{" "}
                      to use this template.
                    </Text>
                  </View>
                ) : (
                  <Button
                    label={apply.isPending ? "Adding…" : "Add to my biolink"}
                    onPress={() => apply.mutate(previewTpl.id)}
                    disabled={apply.isPending}
                  />
                )}
                {apply.isError ? (
                  <Text
                    style={{
                      color: colors.destructive,
                      fontSize: 12,
                      textAlign: "center",
                    }}
                  >
                    {(apply.error as { message?: string })?.message ||
                      "Couldn't add template. Try again."}
                  </Text>
                ) : null}
                <Button
                  label="Cancel"
                  variant="ghost"
                  onPress={clearPreview}
                />
              </View>
            </View>
          </View>
        ) : (
          <View />
        )}
      </Modal>
    </Modal>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 14, paddingBottom: 40 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 12,
    borderWidth: 1,
  },
  handle: { gap: 2 },
  rowTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  rowSub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  modalBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
    justifyContent: "flex-end",
  },
  modalCard: {
    maxHeight: "80%",
    padding: 20,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderTopWidth: 1,
    borderLeftWidth: 1,
    borderRightWidth: 1,
    gap: 12,
  },
  galleryCard: {
    height: "88%",
    padding: 16,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderTopWidth: 1,
    borderLeftWidth: 1,
    borderRightWidth: 1,
    gap: 10,
  },
  modalHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 20, flex: 1 },
  kindRow: { padding: 14, borderWidth: 1, gap: 4 },
  kindRowHead: { flexDirection: "row", alignItems: "center", gap: 8 },
  kindLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  kindBlurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  tabs: { gap: 6, paddingVertical: 4, paddingRight: 8 },
  tab: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
  },
  tplCard: {
    flexDirection: "row",
    gap: 12,
    padding: 12,
    borderWidth: 1,
    alignItems: "center",
  },
  tplThumbWrap: { width: 72, height: 72 },
  tplThumb: { width: 72, height: 72, borderRadius: 12 },
  tplTitleRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  tplName: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 14,
    flexShrink: 1,
  },
  lockBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 999,
  },
  childRow: { padding: 10, borderWidth: 1, gap: 2 },
  lockHint: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 10,
    borderWidth: 1,
    borderRadius: 12,
  },
});
