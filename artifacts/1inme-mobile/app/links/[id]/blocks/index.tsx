import AsyncStorage from "@react-native-async-storage/async-storage";
import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { LinearGradient } from "expo-linear-gradient";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type ComponentProps,
  type ReactNode,
} from "react";
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
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  blockKind,
  createBlock,
  deleteBlock,
  getBlockCatalog,
  listBlocks,
  reorderBlocks,
  updateBlock,
  type Block,
  type BlockCatalogType,
} from "@/lib/api/blocks";
import {
  appendBlock,
  insertBlockTree,
  moveBlock,
  orderIds,
  removeBlockTree,
  replaceBlock,
} from "@/lib/api/blockCache";
import {
  applyCardTemplate,
  listCardTemplates,
  type CardTemplate,
  type CardTemplateChildSummary,
  type PreviewLayoutCell,
} from "@/lib/api/cardTemplates";
import { listForms, createForm, FORM_TEMPLATES } from "@/lib/api/forms";
import {
  listSocialProofs,
  createSocialProof,
  type ProofType,
} from "@/lib/api/socialProofs";
import {
  listBiolinkCompanions,
  listAiPersonas,
  createBiolinkCompanion,
  createAiPersona,
} from "@/lib/api/aiCompanions";
import { getProfile } from "@/lib/api/profile";

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
  const [paletteSearch, setPaletteSearch] = useState("");
  const [paletteCategory, setPaletteCategory] = useState("all");
  // The "Templates, forms & more" panel — a single inline picker with
  // Cards / Forms / Buzz / AI tabs, mirroring the web editor's special
  // panel. `specialMode` controls which tab it opens on.
  const [specialOpen, setSpecialOpen] = useState(false);
  const [specialMode, setSpecialMode] = useState<SpecialMode>("templates");

  function openSpecial(mode: SpecialMode) {
    setSpecialMode(mode);
    setSpecialOpen(true);
  }

  const catalogQ = useQuery({
    queryKey: ["block-catalog"],
    queryFn: getBlockCatalog,
    staleTime: 5 * 60 * 1000,
  });

  // Whose biolink is this — used to key the persisted palette-collapse
  // state per user (so a shared device doesn't leak one account's
  // collapsed sections to another). Reuses the app-wide cached profile.
  const profileQ = useQuery({
    queryKey: ["profile"],
    queryFn: getProfile,
    staleTime: Infinity,
  });

  // Per-category collapse state for the grouped "Add block" palette,
  // mirroring the web editor. Empty => every section starts expanded.
  // Search or a specific category tab force sections open so matches are
  // never hidden behind a collapse. Persisted to AsyncStorage per
  // user+biolink so the state survives app restarts.
  const [paletteCollapsed, setPaletteCollapsed] = useState<
    Record<string, boolean>
  >({});
  const paletteCollapsedKey = `biolink:paletteCollapsed:${
    profileQ.data?.id ?? "anon"
  }:${id}`;
  const paletteCollapsedReady = profileQ.isSuccess || profileQ.isError;
  useEffect(() => {
    if (!paletteCollapsedReady) return;
    let alive = true;
    AsyncStorage.getItem(paletteCollapsedKey)
      .then((raw) => {
        if (!alive || !raw) return;
        const saved = JSON.parse(raw);
        if (saved && typeof saved === "object") {
          setPaletteCollapsed(saved as Record<string, boolean>);
        }
      })
      .catch(() => {
        /* storage blocked or corrupt — fall back to all-expanded */
      });
    return () => {
      alive = false;
    };
  }, [paletteCollapsedKey, paletteCollapsedReady]);

  function togglePaletteSection(cat: string) {
    setPaletteCollapsed((prev) => {
      const next = { ...prev, [cat]: !prev[cat] };
      AsyncStorage.setItem(paletteCollapsedKey, JSON.stringify(next)).catch(
        () => {
          /* storage blocked / full — non-fatal */
        },
      );
      return next;
    });
  }
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

  // In-place cache helpers — mirror the web editor, which injects the
  // returned block HTML into the list instead of reloading the whole
  // editor. On mobile the equivalent is patching the React Query cache
  // directly rather than invalidating + refetching.
  function appendBlockToCache(b: Block) {
    qc.setQueryData<Block[]>(["blocks", id], (old) => appendBlock(old, b));
  }

  const create = useMutation({
    mutationFn: (type: string) => createBlock(id, { type, settings: {} }),
    onSuccess: (b) => {
      appendBlockToCache(b);
      setPicker(false);
      setPaletteSearch("");
      setPaletteCategory("all");
      setHighlightId(b.id);
      router.push(`/links/${id}/blocks/${b.id}` as any);
    },
  });

  function onPaletteTap(t: BlockCatalogType) {
    if (create.isPending) return;
    if (t.locked) {
      confirm(
        "Upgrade to unlock",
        `"${t.label}" is available on a higher plan. View upgrade options?`,
        () => {
          setPicker(false);
          router.push("/upgrade" as any);
        },
      );
      return;
    }
    create.mutate(t.type);
  }

  const paletteTypes = catalogQ.data?.types ?? [];
  const paletteCategories = catalogQ.data?.categories ?? [];
  const filteredPalette = useMemo(() => {
    const q = paletteSearch.trim().toLowerCase();
    return paletteTypes.filter((t) => {
      if (paletteCategory !== "all" && t.category !== paletteCategory) {
        return false;
      }
      if (q && !t.label.toLowerCase().includes(q)) return false;
      return true;
    });
  }, [paletteTypes, paletteCategory, paletteSearch]);

  // The grouped, collapsible layout used when no search is active and the
  // "All" tab is selected. Categories keep the catalog's order; a section
  // only shows if it actually has types.
  const groupedPalette = useMemo(() => {
    const byCat = new Map<string, BlockCatalogType[]>();
    for (const t of paletteTypes) {
      const arr = byCat.get(t.category);
      if (arr) arr.push(t);
      else byCat.set(t.category, [t]);
    }
    return paletteCategories
      .filter((c) => byCat.has(c.key))
      .map((c) => ({ key: c.key, label: c.label, items: byCat.get(c.key)! }));
  }, [paletteTypes, paletteCategories]);

  // Grouped/collapsible only makes sense in the unfiltered "All" view —
  // a search or a specific category tab force everything open (flat),
  // matching the web editor's force-open behaviour.
  const paletteGrouped =
    paletteCategory === "all" && paletteSearch.trim() === "";

  const toggle = useMutation({
    mutationFn: (b: Block) =>
      updateBlock(id, b.id, { is_active: !b.is_active }),
    onSuccess: (updated) =>
      qc.setQueryData<Block[]>(["blocks", id], (old) =>
        replaceBlock(old, updated),
      ),
    // Server rejected the toggle — pull the truth back so the switch
    // doesn't drift from reality.
    onError: () => qc.invalidateQueries({ queryKey: ["blocks", id] }),
  });

  const remove = useMutation({
    mutationFn: (blockId: number) => deleteBlock(id, blockId),
    onSuccess: (_res, blockId) =>
      qc.setQueryData<Block[]>(["blocks", id], (old) =>
        removeBlockTree(old, blockId),
      ),
    onError: () => qc.invalidateQueries({ queryKey: ["blocks", id] }),
  });

  const persistOrder = useMutation({
    mutationFn: (ids: number[]) => reorderBlocks(id, ids),
    // The optimistic order is already written to the cache in `move()`;
    // only re-sync from the server if the persist failed.
    onError: () => qc.invalidateQueries({ queryKey: ["blocks", id] }),
  });

  function move(idx: number, dir: -1 | 1) {
    const next = moveBlock(order, idx, dir);
    if (!next) return;
    setOrder(next);
    qc.setQueryData<Block[]>(["blocks", id], next);
    persistOrder.mutate(orderIds(next));
  }

  // A single block tile in the "Add block" palette. Shared by the
  // grouped (collapsible) and flat (search / single-category) layouts.
  const renderPaletteTile = (t: BlockCatalogType) => (
    <Pressable
      key={t.type}
      onPress={() => onPaletteTap(t)}
      style={({ pressed }) => [
        styles.paletteTile,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : t.locked ? 0.6 : 1,
        },
      ]}
    >
      <View
        style={[
          styles.paletteIcon,
          {
            backgroundColor: colors.primary + "18",
            borderColor: colors.primary + "33",
          },
        ]}
      >
        <Feather
          name={t.locked ? "lock" : featherFor(t.icon)}
          size={16}
          color={colors.primary}
        />
      </View>
      <Text
        numberOfLines={2}
        style={[styles.paletteLabel, { color: colors.foreground }]}
      >
        {t.label}
      </Text>
      {t.locked ? (
        <Text style={[styles.paletteLocked, { color: colors.mutedForeground }]}>
          Upgrade
        </Text>
      ) : null}
    </Pressable>
  );

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
                  label="Templates, forms & more"
                  onPress={() => openSpecial("templates")}
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
              label="Templates, forms & more"
              variant="ghost"
              onPress={() => openSpecial("templates")}
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

            <View
              style={[
                styles.searchWrap,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <Feather name="search" size={14} color={colors.mutedForeground} />
              <TextInput
                value={paletteSearch}
                onChangeText={setPaletteSearch}
                placeholder="Search blocks…"
                placeholderTextColor={colors.mutedForeground}
                style={[styles.searchInput, { color: colors.foreground }]}
                autoCorrect={false}
                returnKeyType="search"
              />
              {paletteSearch.length > 0 ? (
                <Pressable onPress={() => setPaletteSearch("")} hitSlop={8}>
                  <Feather name="x" size={14} color={colors.mutedForeground} />
                </Pressable>
              ) : null}
            </View>

            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.tabs}
            >
              {[{ key: "all", label: "All" }, ...paletteCategories].map((c) => {
                const active = paletteCategory === c.key;
                return (
                  <Pressable
                    key={c.key}
                    onPress={() => setPaletteCategory(c.key)}
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
                        fontSize: 12,
                        color: active
                          ? colors.primaryForeground
                          : colors.mutedForeground,
                      }}
                    >
                      {c.label}
                    </Text>
                  </Pressable>
                );
              })}
            </ScrollView>

            {catalogQ.isLoading ? (
              <View style={{ paddingVertical: 32 }}>
                <ActivityIndicator color={colors.primary} />
              </View>
            ) : catalogQ.isError ? (
              <View style={{ paddingVertical: 24, gap: 10 }}>
                <Text
                  style={{
                    color: colors.mutedForeground,
                    textAlign: "center",
                    fontSize: 13,
                  }}
                >
                  Couldn&apos;t load the block palette.
                </Text>
                <Button label="Retry" onPress={() => catalogQ.refetch()} />
              </View>
            ) : (
              <ScrollView contentContainerStyle={{ paddingBottom: 20, gap: 8 }}>
                {paletteGrouped ? (
                  <Pressable
                    key="special-panel"
                    onPress={() => {
                      setPicker(false);
                      openSpecial("templates");
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
                        Templates, forms & more
                      </Text>
                    </View>
                    <Text
                      style={[
                        styles.kindBlurb,
                        { color: colors.mutedForeground },
                      ]}
                    >
                      Card templates, forms, Buzz campaigns & AI companions.
                    </Text>
                  </Pressable>
                ) : null}

                {paletteGrouped ? (
                  groupedPalette.map((g) => {
                    const open = !paletteCollapsed[g.key];
                    return (
                      <View key={g.key} style={{ gap: 8 }}>
                        <Pressable
                          onPress={() => togglePaletteSection(g.key)}
                          accessibilityRole="button"
                          accessibilityState={{ expanded: open }}
                          style={[
                            styles.paletteSectionHeader,
                            { borderColor: colors.border },
                          ]}
                        >
                          <Text
                            style={[
                              styles.paletteSectionTitle,
                              { color: colors.foreground },
                            ]}
                          >
                            {g.label}
                          </Text>
                          <View style={styles.paletteSectionMeta}>
                            <Text
                              style={{
                                color: colors.mutedForeground,
                                fontSize: 12,
                              }}
                            >
                              {g.items.length}
                            </Text>
                            <Feather
                              name={open ? "chevron-up" : "chevron-down"}
                              size={16}
                              color={colors.mutedForeground}
                            />
                          </View>
                        </Pressable>
                        {open ? (
                          <View style={styles.paletteGrid}>
                            {g.items.map((t) => renderPaletteTile(t))}
                          </View>
                        ) : null}
                      </View>
                    );
                  })
                ) : (
                  <>
                    <View style={styles.paletteGrid}>
                      {filteredPalette.map((t) => renderPaletteTile(t))}
                    </View>

                    {filteredPalette.length === 0 ? (
                      <Text
                        style={{
                          color: colors.mutedForeground,
                          textAlign: "center",
                          fontSize: 13,
                          paddingVertical: 24,
                        }}
                      >
                        No blocks match &ldquo;{paletteSearch.trim()}&rdquo;.
                      </Text>
                    ) : null}
                  </>
                )}
              </ScrollView>
            )}
          </View>
        </View>
      </Modal>

      <SpecialPanel
        visible={specialOpen}
        mode={specialMode}
        onModeChange={setSpecialMode}
        linkId={id}
        insertAfter={order.length > 0 ? order[order.length - 1].id : null}
        onClose={() => setSpecialOpen(false)}
        onPreview={(t) => setPreviewTpl(t)}
        onApplied={(blocks) => {
          // Card templates create a parent card plus child blocks in one
          // shot. The apply endpoint returns the whole freshly-created
          // sub-tree (parent first, then children), so we patch it into
          // the cache in place — just like the single-block pickers —
          // instead of refetching the entire list.
          const afterId =
            order.length > 0 ? order[order.length - 1].id : null;
          qc.setQueryData<Block[]>(["blocks", id], (old) =>
            insertBlockTree(old, blocks, afterId),
          );
          setSpecialOpen(false);
          setPreviewTpl(null);
          setHighlightId(blocks[0]?.id ?? null);
        }}
        onInserted={(b) => {
          appendBlockToCache(b);
          setSpecialOpen(false);
          setHighlightId(b.id);
        }}
        previewTpl={previewTpl}
        clearPreview={() => setPreviewTpl(null)}
      />
    </View>
  );
}

// The four inline-palette tabs the special panel exposes, matching the
// web editor's special panel (Cards / Forms / Buzz / AI).
type SpecialMode = "templates" | "forms" | "buzz" | "ai";

type SpecialPanelProps = {
  visible: boolean;
  mode: SpecialMode;
  onModeChange: (m: SpecialMode) => void;
  linkId: number;
  insertAfter: number | null;
  onClose: () => void;
  onPreview: (t: CardTemplate) => void;
  // Card templates create a parent + children; the apply endpoint returns
  // the whole freshly-created sub-tree (parent first, then children) so the
  // parent can patch it straight into the cache like the single-block flows.
  onApplied: (blocks: Block[]) => void;
  // Forms / Buzz / AI insert a single block; hand the full block back so
  // the parent can patch it straight into the cache.
  onInserted: (block: Block) => void;
  previewTpl: CardTemplate | null;
  clearPreview: () => void;
};

/**
 * Map a Font Awesome glyph name (the API returns `fa-*` strings) to a
 * Feather icon name we actually have on mobile. Best-effort: anything
 * we don't recognise falls back to `box` so a chip still renders an
 * icon next to its label.
 */
function featherFor(faIcon: string): ComponentProps<typeof Feather>["name"] {
  const map: Record<string, ComponentProps<typeof Feather>["name"]> = {
    "fa-link": "link",
    "fa-external-link-square-alt": "external-link",
    "fa-external-link-alt": "external-link",
    "fa-arrow-right": "arrow-right",
    "fa-heading": "type",
    "fa-paragraph": "align-left",
    "fa-align-left": "align-left",
    "fa-file-alt": "file-text",
    "fa-file-lines": "file-text",
    "fa-list": "list",
    "fa-list-ul": "list",
    "fa-list-ol": "list",
    "fa-tags": "tag",
    "fa-exclamation-triangle": "alert-triangle",
    "fa-circle-info": "info",
    "fa-info-circle": "info",
    "fa-certificate": "award",
    "fa-minus": "minus",
    "fa-arrows-alt-v": "more-vertical",
    "fa-layer-group": "layers",
    "fa-clone": "copy",
    "fa-columns": "columns",
    "fa-id-card": "user",
    "fa-id-card-alt": "user",
    "fa-address-card": "user",
    "fa-user-tag": "user",
    "fa-user-circle": "user",
    "fa-user-check": "user-check",
    "fa-user": "user",
    "fa-users": "users",
    "fa-image": "image",
    "fa-th": "grid",
    "fa-images": "image",
    "fa-photo-video": "image",
    "fa-film": "film",
    "fa-video": "video",
    "fa-play": "play",
    "fa-play-circle": "play-circle",
    "fa-music": "music",
    "fa-headphones": "headphones",
    "fa-file-pdf": "file",
    "fa-file-powerpoint": "file",
    "fa-file-excel": "file",
    "fa-file-download": "download",
    "fa-folder-open": "folder",
    "fa-folder": "folder",
    "fa-question-circle": "help-circle",
    "fa-question": "help-circle",
    "fa-poll": "bar-chart-2",
    "fa-brain": "cpu",
    "fa-quote-right": "message-circle",
    "fa-quote-left": "message-circle",
    "fa-star": "star",
    "fa-stream": "menu",
    "fa-project-diagram": "git-branch",
    "fa-bell": "bell",
    "fa-robot": "cpu",
    "fa-lock": "lock",
    "fa-trophy": "award",
    "fa-route": "map",
    "fa-box": "box",
    "fa-concierge-bell": "bell",
    "fa-book-open": "book-open",
    "fa-store": "shopping-bag",
    "fa-dollar-sign": "dollar-sign",
    "fa-hand-holding-heart": "heart",
    "fa-ticket-alt": "tag",
    "fa-fire": "zap",
    "fa-credit-card": "credit-card",
    "fa-coffee": "coffee",
    "fa-hand-holding-usd": "dollar-sign",
    "fa-mug-hot": "coffee",
    "fa-thumbtack": "bookmark",
    "fa-envelope": "mail",
    "fa-envelope-open-text": "mail",
    "fa-phone": "phone",
    "fa-paper-plane": "send",
    "fa-comments": "message-circle",
    "fa-comment": "message-circle",
    "fa-comment-dots": "message-circle",
    "fa-comment-alt": "message-circle",
    "fa-bullhorn": "volume-2",
    "fa-phone-square": "phone",
    "fa-share-alt": "share-2",
    "fa-share-nodes": "share-2",
    "fa-paint-brush": "edit-3",
    "fa-instagram": "instagram",
    "fa-camera-retro": "camera",
    "fa-rss": "rss",
    "fa-compact-disc": "disc",
    "fa-cloud": "cloud",
    "fa-water": "droplet",
    "fa-podcast": "mic",
    "fa-th-list": "list",
    "fa-youtube": "youtube",
    "fa-gamepad": "tv",
    "fa-bolt": "zap",
    "fa-hand-pointer": "mouse-pointer",
    "fa-clock": "clock",
    "fa-tasks": "check-square",
    "fa-chart-pie": "pie-chart",
    "fa-chart-bar": "bar-chart-2",
    "fa-qrcode": "grid",
    "fa-share-square": "share",
    "fa-bars": "menu",
    "fa-scroll": "file-text",
    "fa-map-marker-alt": "map-pin",
    "fa-map-pin": "map-pin",
    "fa-map": "map",
    "fa-clipboard-list": "clipboard",
    "fa-calendar-check": "calendar",
    "fa-calendar-alt": "calendar",
    "fa-calendar-day": "calendar",
    "fa-hashtag": "hash",
    "fa-thumbs-up": "thumbs-up",
    "fa-window-maximize": "maximize",
    "fa-code": "code",
    "fa-address-book": "book",
    "fa-check-circle": "check-circle",
    "fa-utensils": "coffee",
    "fa-cube": "box",
    "fa-square": "square",
    "fa-square-poll-vertical": "bar-chart-2",
    "fa-heart": "heart",
  };
  // Strip any prefix like "fab "/"far "/"fas " that the API sometimes
  // sends through verbatim from settings.
  const cleaned = faIcon.replace(/^(fab|far|fas|fa-solid|fa-regular|fa-brands)\s+/, "");
  return map[cleaned] ?? "box";
}

/**
 * Renders the same shape-aware mini-blueprint the web gallery shows for
 * a card template's thumbnail when no static `thumbnail_url` is set.
 * Each row is a flex row whose cells flex-grow proportional to their
 * grid_span, and each cell's `shape` hint drives a small mock (avatar
 * circle, pill button, stacked input lines, social dot rows, etc.) so
 * the tile communicates the card's actual contents at a glance.
 */
function PreviewBlueprint({
  rows,
  height,
}: {
  rows: PreviewLayoutCell[][];
  height: number;
}) {
  if (!rows.length) return null;
  return (
    <View
      style={{
        width: "100%",
        height,
        paddingHorizontal: 6,
        paddingVertical: 5,
        gap: 3,
        justifyContent: "center",
      }}
    >
      {rows.map((row, ri) => (
        <View
          key={ri}
          style={{
            flexDirection: "row",
            gap: 3,
            alignItems: "center",
            width: "100%",
          }}
        >
          {row.map((cell, ci) => (
            <View
              key={ci}
              style={{
                flex: cell.span,
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <BlueprintCell cell={cell} />
            </View>
          ))}
        </View>
      ))}
    </View>
  );
}

/**
 * The PHP `TemplatePreviewLayoutBuilder` mirrors web styling and emits
 * CSS `linear-gradient(...)` strings for media/map-style cells. React
 * Native's `backgroundColor` cannot accept gradient strings — assigning
 * one would either be silently ignored or throw — so we parse those
 * here. Returns either a solid colour string (safe to drop into any
 * `backgroundColor` prop) or a structured gradient we can hand to
 * `expo-linear-gradient`. Plain rgba/hex/named colours pass through
 * untouched.
 */
type ParsedBg =
  | { kind: "solid"; color: string }
  | { kind: "gradient"; colors: string[]; angle: number };

function parsePreviewBg(bg: string): ParsedBg {
  const trimmed = (bg ?? "").trim();
  if (!trimmed.toLowerCase().startsWith("linear-gradient")) {
    return { kind: "solid", color: trimmed || "rgba(255,255,255,0.10)" };
  }
  const open = trimmed.indexOf("(");
  const close = trimmed.lastIndexOf(")");
  if (open < 0 || close <= open) {
    return { kind: "solid", color: "rgba(255,255,255,0.10)" };
  }
  const body = trimmed.slice(open + 1, close);
  // Split on commas at depth 0 so commas inside `rgba(...)` stops are kept.
  const parts: string[] = [];
  let depth = 0;
  let cur = "";
  for (let i = 0; i < body.length; i++) {
    const ch = body[i];
    if (ch === "(") depth++;
    else if (ch === ")") depth--;
    if (ch === "," && depth === 0) {
      parts.push(cur.trim());
      cur = "";
    } else {
      cur += ch;
    }
  }
  if (cur.trim()) parts.push(cur.trim());
  let angle = 180;
  let stops = parts;
  if (parts.length > 0 && /^-?\d+(?:\.\d+)?\s*deg$/i.test(parts[0])) {
    angle = parseFloat(parts[0]);
    stops = parts.slice(1);
  }
  // A stop may be `<color> <position?>` — keep just the colour token.
  const colors = stops
    .map((s) => s.split(/\s+/)[0])
    .filter((c) => c.length > 0);
  if (colors.length < 2) {
    return { kind: "solid", color: colors[0] || "rgba(255,255,255,0.10)" };
  }
  return { kind: "gradient", colors, angle };
}

/**
 * Convert a CSS gradient angle (clockwise from "north", 0deg = bottom→
 * top, 90deg = left→right) into the start/end unit-square points
 * `expo-linear-gradient` expects.
 */
function gradientPoints(angle: number) {
  const rad = (angle * Math.PI) / 180;
  const x = Math.sin(rad);
  const y = -Math.cos(rad);
  return {
    start: { x: 0.5 - x / 2, y: 0.5 - y / 2 },
    end: { x: 0.5 + x / 2, y: 0.5 + y / 2 },
  };
}

function BlueprintCell({ cell }: { cell: PreviewLayoutCell }) {
  const h = cell.h;
  const parsed = parsePreviewBg(cell.bg);
  // Always have a safe solid fallback to drop into RN `backgroundColor`
  // — even gradient cells use this for tiny inner elements (dots, lines)
  // where rendering a full `LinearGradient` wouldn't add value.
  const bg = parsed.kind === "solid" ? parsed.color : parsed.colors[0];
  switch (cell.shape) {
    case "heading":
      return (
        <View style={{ width: "100%", alignItems: "center", gap: 1 }}>
          <View
            style={{
              backgroundColor: bg,
              height: h,
              width: "100%",
              borderRadius: 2,
            }}
          />
          {cell.sub ? (
            <View
              style={{
                backgroundColor: bg,
                height: Math.max(h - 6, 4),
                width: "55%",
                borderRadius: 2,
              }}
            />
          ) : null}
        </View>
      );
    case "text_lines": {
      const lines = Math.max(cell.lines ?? 2, 1);
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            justifyContent: "center",
            gap: 2,
          }}
        >
          {Array.from({ length: lines }).map((_, i) => (
            <View
              key={i}
              style={{
                backgroundColor: bg,
                height: 2.5,
                width: i === lines - 1 ? "60%" : "100%",
                borderRadius: 2,
              }}
            />
          ))}
        </View>
      );
    }
    case "pill":
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            backgroundColor: bg,
            borderRadius: 999,
            flexDirection: "row",
            alignItems: "center",
            justifyContent: "flex-end",
            paddingHorizontal: 4,
          }}
        >
          {cell.icon ? (
            <View
              style={{
                width: 4,
                height: 4,
                borderRadius: 2,
                backgroundColor: "rgba(255,255,255,0.85)",
              }}
            />
          ) : null}
        </View>
      );
    case "avatar": {
      const size = Math.max(h - 8, 14);
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            flexDirection: "row",
            alignItems: "center",
            gap: 5,
          }}
        >
          <View
            style={{
              width: size,
              height: size,
              borderRadius: size / 2,
              backgroundColor: bg,
            }}
          />
          <View style={{ flex: 1, gap: 2 }}>
            <View
              style={{
                backgroundColor: "rgba(255,255,255,0.55)",
                height: 4,
                width: "70%",
                borderRadius: 2,
              }}
            />
            <View
              style={{
                backgroundColor: "rgba(255,255,255,0.30)",
                height: 3,
                width: "50%",
                borderRadius: 2,
              }}
            />
          </View>
        </View>
      );
    }
    case "media":
      // The builder emits CSS `linear-gradient(...)` strings for image/
      // video/audio/pdf cells (matches web). Render them via
      // `expo-linear-gradient` here so the mobile thumbnail shows the
      // same colourful media block instead of a flat patch.
      if (parsed.kind === "gradient") {
        const { start, end } = gradientPoints(parsed.angle);
        return (
          <LinearGradient
            colors={parsed.colors as [string, string, ...string[]]}
            start={start}
            end={end}
            style={{ width: "100%", minHeight: h, borderRadius: 3 }}
          />
        );
      }
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            backgroundColor: bg,
            borderRadius: 3,
          }}
        />
      );
    case "dot_row": {
      const dots = Math.max(cell.dots ?? 5, 1);
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            flexDirection: "row",
            alignItems: "center",
            justifyContent: "center",
            gap: 3,
          }}
        >
          {Array.from({ length: dots }).map((_, i) => (
            <View
              key={i}
              style={{
                width: 4,
                height: 4,
                borderRadius: 2,
                backgroundColor: bg,
              }}
            />
          ))}
        </View>
      );
    }
    case "form": {
      const lines = Math.max(cell.lines ?? 1, 1);
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            justifyContent: "center",
            gap: 3,
          }}
        >
          {Array.from({ length: lines }).map((_, i) => (
            <View
              key={i}
              style={{
                backgroundColor: bg,
                height: 4,
                width: "100%",
                borderRadius: 2,
              }}
            />
          ))}
          <View
            style={{
              alignSelf: "center",
              backgroundColor: cell.btn_bg ?? "rgba(139,92,246,0.85)",
              height: 5,
              width: "60%",
              borderRadius: 999,
            }}
          />
        </View>
      );
    }
    case "list_rows": {
      const lines = Math.max(cell.lines ?? 3, 1);
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            justifyContent: "center",
            gap: 3,
          }}
        >
          {Array.from({ length: lines }).map((_, i) => (
            <View
              key={i}
              style={{
                flexDirection: "row",
                alignItems: "center",
                gap: 3,
              }}
            >
              <View
                style={{
                  width: 3,
                  height: 3,
                  borderRadius: 2,
                  backgroundColor: bg,
                }}
              />
              <View
                style={{
                  flex: 1,
                  backgroundColor: bg,
                  height: 2.5,
                  borderRadius: 2,
                }}
              />
            </View>
          ))}
        </View>
      );
    }
    case "hairline":
      return (
        <View
          style={{
            width: "100%",
            backgroundColor: bg,
            height: h,
            borderRadius: 2,
          }}
        />
      );
    case "spacer":
      return <View style={{ width: "100%", minHeight: h }} />;
    case "badge":
      return (
        <View
          style={{
            alignSelf: "center",
            backgroundColor: bg,
            height: h,
            width: "50%",
            borderRadius: 999,
          }}
        />
      );
    case "tile":
    default:
      // The builder uses gradients for map/map_location cells too, so
      // honour them here as well for visual parity with web.
      if (parsed.kind === "gradient") {
        const { start, end } = gradientPoints(parsed.angle);
        return (
          <LinearGradient
            colors={parsed.colors as [string, string, ...string[]]}
            start={start}
            end={end}
            style={{ width: "100%", minHeight: h, borderRadius: 3 }}
          />
        );
      }
      return (
        <View
          style={{
            width: "100%",
            minHeight: h,
            backgroundColor: bg,
            borderRadius: 3,
          }}
        />
      );
  }
}

/**
 * Group a flat children-summary list into icon-tagged chips (one chip
 * per distinct block type, with a count when repeated). Mirrors the
 * web gallery's "what's inside" peek so creators can tell at a glance
 * what blocks the card holds.
 */
function chipsFromChildren(
  children: CardTemplateChildSummary[],
): Array<{ icon: string; label: string; count: number }> {
  const groups = new Map<
    string,
    { icon: string; label: string; count: number }
  >();
  for (const c of children) {
    const existing = groups.get(c.type);
    if (existing) {
      existing.count += 1;
    } else {
      groups.set(c.type, {
        icon: c.icon || "fa-cube",
        label: c.label,
        count: 1,
      });
    }
  }
  return Array.from(groups.values());
}

function SpecialPanel(props: SpecialPanelProps) {
  const colors = useColors();
  const {
    visible,
    mode,
    onModeChange,
    linkId,
    insertAfter,
    onClose,
    onPreview,
    onApplied,
    onInserted,
    previewTpl,
    clearPreview,
  } = props;

  const qc = useQueryClient();
  const [activeCat, setActiveCat] = useState<string>("all");
  const [search, setSearch] = useState("");
  const [createOpen, setCreateOpen] = useState(false);

  // Reset the search box (and any open create sheet) whenever the user
  // switches tabs so a query typed on one tab doesn't silently hide
  // everything on the next.
  useEffect(() => {
    setSearch("");
    setCreateOpen(false);
  }, [mode]);

  const q = useQuery({
    queryKey: ["card-templates", linkId],
    queryFn: () => listCardTemplates(linkId),
    enabled: visible && mode === "templates" && Number.isFinite(linkId),
    staleTime: 60_000,
  });

  const formsQ = useQuery({
    queryKey: ["special-forms"],
    queryFn: listForms,
    enabled: visible && mode === "forms",
    staleTime: 60_000,
  });

  const buzzQ = useQuery({
    queryKey: ["special-buzz"],
    queryFn: listSocialProofs,
    enabled: visible && mode === "buzz",
    staleTime: 60_000,
  });

  const aiQ = useQuery({
    queryKey: ["special-ai"],
    queryFn: listBiolinkCompanions,
    enabled: visible && mode === "ai",
    staleTime: 60_000,
  });

  const apply = useMutation({
    mutationFn: (templateId: number) =>
      applyCardTemplate(linkId, {
        template_id: templateId,
        insert_after: insertAfter,
      }),
    onSuccess: (res) => onApplied(res.blocks),
  });

  // Forms / Buzz / AI all resolve to a single block; the settings payload
  // is passed verbatim to the API, matching the web special panel.
  const insert = useMutation({
    mutationFn: (payload: { type: string; settings: Record<string, unknown> }) =>
      createBlock(linkId, payload),
    onSuccess: (b) => onInserted(b),
  });

  const items = q.data?.items ?? [];
  const cats = q.data?.categories ?? {};

  const visibleItems = useMemo(() => {
    let list = activeCat === "all" ? items : items.filter((t) => t.category === activeCat);
    const term = search.trim().toLowerCase();
    if (term) {
      list = list.filter(
        (t) =>
          t.name.toLowerCase().includes(term) ||
          (t.description ?? "").toLowerCase().includes(term),
      );
    }
    return list;
  }, [items, activeCat, search]);

  const catOptions = useMemo(() => {
    const used = new Set(items.map((t) => t.category));
    return [
      { key: "all", label: "All" },
      ...Object.entries(cats)
        .filter(([key]) => used.has(key))
        .map(([key, label]) => ({ key, label })),
    ];
  }, [items, cats]);

  const formItems = useMemo(() => {
    const term = search.trim().toLowerCase();
    const list = formsQ.data?.items ?? [];
    if (!term) return list;
    return list.filter((f) => f.title.toLowerCase().includes(term));
  }, [formsQ.data, search]);

  const buzzItems = useMemo(() => {
    const term = search.trim().toLowerCase();
    const list = buzzQ.data?.items ?? [];
    if (!term) return list;
    return list.filter(
      (s) =>
        s.name.toLowerCase().includes(term) ||
        s.type_label.toLowerCase().includes(term),
    );
  }, [buzzQ.data, search]);

  const aiItems = useMemo(() => {
    const term = search.trim().toLowerCase();
    const list = aiQ.data?.items ?? [];
    if (!term) return list;
    return list.filter((c) => c.name.toLowerCase().includes(term));
  }, [aiQ.data, search]);

  const MODE_TABS: Array<{
    key: SpecialMode;
    label: string;
    icon: ComponentProps<typeof Feather>["name"];
  }> = [
    { key: "templates", label: "Cards", icon: "layers" },
    { key: "forms", label: "Forms", icon: "file-text" },
    { key: "buzz", label: "Buzz", icon: "volume-2" },
    { key: "ai", label: "AI", icon: "cpu" },
  ];

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
              Templates, forms & more
            </Text>
            <Pressable onPress={onClose} hitSlop={8}>
              <Feather name="x" size={20} color={colors.mutedForeground} />
            </Pressable>
          </View>

          {/* Mode tab bar — Cards / Forms / Buzz / AI, mirroring the web
              special panel's pill tabs. */}
          <View style={styles.modeTabs}>
            {MODE_TABS.map((t) => {
              const active = t.key === mode;
              return (
                <Pressable
                  key={t.key}
                  onPress={() => onModeChange(t.key)}
                  style={[
                    styles.modeTab,
                    {
                      backgroundColor: active ? colors.primary : colors.card,
                      borderColor: active ? colors.primary : colors.border,
                    },
                  ]}
                >
                  <Feather
                    name={t.icon}
                    size={13}
                    color={active ? "#fff" : colors.foreground}
                  />
                  <Text
                    style={{
                      color: active ? "#fff" : colors.foreground,
                      fontSize: 12,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                    }}
                  >
                    {t.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          {/* Shared search box across all tabs. */}
          <View
            style={[
              styles.searchBox,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Feather name="search" size={14} color={colors.mutedForeground} />
            <TextInput
              value={search}
              onChangeText={setSearch}
              placeholder={
                mode === "templates"
                  ? "Search templates"
                  : mode === "forms"
                    ? "Search forms"
                    : mode === "buzz"
                      ? "Search Buzz campaigns"
                      : "Search AI companions"
              }
              placeholderTextColor={colors.mutedForeground}
              style={{
                flex: 1,
                color: colors.foreground,
                fontFamily: "SpaceGrotesk_400Regular",
                fontSize: 13,
                padding: 0,
              }}
            />
            {search ? (
              <Pressable onPress={() => setSearch("")} hitSlop={8}>
                <Feather name="x" size={14} color={colors.mutedForeground} />
              </Pressable>
            ) : null}
          </View>

          {mode === "forms" ? (
            <SpecialList
              query={formsQ}
              empty="You don't have any forms yet. Create your first one to drop it in here."
              filteredCount={formItems.length}
              inserting={insert.isPending}
              createLabel="Create new form"
              onCreate={() => setCreateOpen(true)}
            >
              {formItems.map((f) => (
                <SpecialRow
                  key={f.id}
                  icon="file-text"
                  title={f.title}
                  subtitle={`${f.fields.length} field${f.fields.length === 1 ? "" : "s"}${f.is_active ? "" : " · inactive"}`}
                  disabled={insert.isPending}
                  onPress={() =>
                    insert.mutate({
                      type: "form",
                      settings: { form_id: f.id, height: 600 },
                    })
                  }
                />
              ))}
            </SpecialList>
          ) : mode === "buzz" ? (
            <SpecialList
              query={buzzQ}
              empty="You don't have any Buzz campaigns yet. Create your first one to drop it in here."
              filteredCount={buzzItems.length}
              inserting={insert.isPending}
              createLabel="Create new campaign"
              onCreate={() => setCreateOpen(true)}
            >
              {buzzItems.map((s) => (
                <SpecialRow
                  key={s.id}
                  icon="volume-2"
                  title={s.name}
                  subtitle={`${s.type_label}${s.is_active ? "" : " · inactive"}`}
                  disabled={insert.isPending}
                  onPress={() =>
                    insert.mutate({
                      type: "social_proof",
                      settings: { social_proof_id: s.id },
                    })
                  }
                />
              ))}
            </SpecialList>
          ) : mode === "ai" ? (
            <SpecialList
              query={aiQ}
              empty="You don't have any AI companions for biolink placement yet. Create your first one to drop it in here."
              filteredCount={aiItems.length}
              inserting={insert.isPending}
              createLabel="Create new companion"
              onCreate={() => setCreateOpen(true)}
            >
              {aiItems.map((c) => (
                <SpecialRow
                  key={c.id}
                  icon="cpu"
                  title={c.name}
                  subtitle={c.is_disabled ? "Disabled" : "AI companion"}
                  disabled={insert.isPending}
                  onPress={() =>
                    insert.mutate({
                      type: "ai_companion",
                      settings: { companion_id: c.id },
                    })
                  }
                />
              ))}
            </SpecialList>
          ) : q.isLoading ? (
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
                  visibleItems.map((t) => {
                    const previewRows = t.preview_layout ?? [];
                    const chips = chipsFromChildren(t.children ?? []);
                    const shownChips = chips.slice(0, 3);
                    const extraChips = Math.max(0, chips.length - 3);
                    return (
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
                        {/* Full-width thumbnail strip. When the API returns
                            a static thumbnail_url we use that; otherwise
                            we render the same shape-aware mini-blueprint
                            the web gallery shows so the tile communicates
                            the card's actual contents at a glance. */}
                        <View
                          style={[
                            styles.tplThumbStrip,
                            { backgroundColor: colors.primary + "12" },
                          ]}
                        >
                          {t.thumbnail_url ? (
                            <Image
                              source={{ uri: t.thumbnail_url }}
                              style={styles.tplThumbStripImage}
                              resizeMode="cover"
                            />
                          ) : previewRows.length ? (
                            <PreviewBlueprint rows={previewRows} height={96} />
                          ) : (
                            <View
                              style={{
                                width: "100%",
                                height: 96,
                                alignItems: "center",
                                justifyContent: "center",
                              }}
                            >
                              <Feather
                                name="layers"
                                size={24}
                                color={colors.primary}
                              />
                            </View>
                          )}
                          {t.locked ? (
                            <View
                              style={[
                                styles.tplLockBadgeFloating,
                                { backgroundColor: colors.primary },
                              ]}
                            >
                              <Feather name="lock" size={10} color="#fff" />
                              <Text
                                style={{
                                  color: "#fff",
                                  fontSize: 10,
                                  fontFamily: "SpaceGrotesk_600SemiBold",
                                }}
                              >
                                {t.plan_tier?.toUpperCase() || "PRO"}
                              </Text>
                            </View>
                          ) : null}
                        </View>
                        {/* Body: title on the left, small subtle category
                            pill on the right, then a flex-wrap row of
                            icon-tagged "what's inside" chips so creators
                            can tell at a glance what blocks the card
                            holds (Avatar, 2 Buttons, Heading, …). */}
                        <View style={{ padding: 12, gap: 8 }}>
                          <View style={styles.tplTitleRow}>
                            <Text
                              numberOfLines={1}
                              style={[
                                styles.tplName,
                                { color: colors.foreground, flex: 1 },
                              ]}
                            >
                              {t.name}
                            </Text>
                            <View
                              style={[
                                styles.categoryPill,
                                { borderColor: colors.border },
                              ]}
                            >
                              <Text
                                style={{
                                  color: colors.mutedForeground,
                                  fontSize: 9,
                                  letterSpacing: 0.5,
                                  fontFamily: "SpaceGrotesk_600SemiBold",
                                }}
                              >
                                {(t.category_label || t.category).toUpperCase()}
                              </Text>
                            </View>
                          </View>
                          {chips.length ? (
                            <View
                              style={{
                                flexDirection: "row",
                                flexWrap: "wrap",
                                gap: 4,
                              }}
                            >
                              {shownChips.map((chip, i) => (
                                <View
                                  key={i}
                                  style={[
                                    styles.contentChip,
                                    {
                                      backgroundColor: colors.primary + "15",
                                      borderColor: colors.primary + "33",
                                    },
                                  ]}
                                >
                                  <Feather
                                    name={featherFor(chip.icon)}
                                    size={10}
                                    color={colors.primary}
                                  />
                                  <Text
                                    style={{
                                      color: colors.foreground,
                                      fontSize: 11,
                                      fontFamily: "SpaceGrotesk_500Medium",
                                    }}
                                  >
                                    {chip.count > 1
                                      ? `${chip.count} ${chip.label}s`
                                      : chip.label}
                                  </Text>
                                </View>
                              ))}
                              {extraChips > 0 ? (
                                <View
                                  style={[
                                    styles.contentChip,
                                    {
                                      backgroundColor: "transparent",
                                      borderColor: "transparent",
                                    },
                                  ]}
                                >
                                  <Text
                                    style={{
                                      color: colors.primary,
                                      fontSize: 11,
                                      fontFamily: "SpaceGrotesk_600SemiBold",
                                    }}
                                  >
                                    +{extraChips} more
                                  </Text>
                                </View>
                              ) : null}
                            </View>
                          ) : (
                            <Text
                              style={{
                                color: colors.mutedForeground,
                                fontSize: 11,
                                fontFamily: "SpaceGrotesk_500Medium",
                              }}
                            >
                              {t.children_count} blocks
                            </Text>
                          )}
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
                    );
                  })
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

      {mode === "forms" || mode === "buzz" || mode === "ai" ? (
        <SpecialCreateModal
          visible={createOpen}
          mode={mode}
          buzzTypes={buzzQ.data?.types ?? []}
          onClose={() => setCreateOpen(false)}
          onCreated={() => {
            setCreateOpen(false);
            const key =
              mode === "forms"
                ? "special-forms"
                : mode === "buzz"
                  ? "special-buzz"
                  : "special-ai";
            qc.invalidateQueries({ queryKey: [key] });
          }}
        />
      ) : null}
    </Modal>
  );
}

// Inline "create on the spot" sheet for the Forms / Buzz / AI tabs. Lets
// the user mint a new form / Buzz campaign / AI companion without leaving
// the block editor; on success the parent refetches the picker so the new
// item appears and is immediately selectable.
function SpecialCreateModal(props: {
  visible: boolean;
  mode: "forms" | "buzz" | "ai";
  buzzTypes: ProofType[];
  onClose: () => void;
  onCreated: () => void;
}) {
  const colors = useColors();
  const { visible, mode, buzzTypes, onClose, onCreated } = props;

  const [name, setName] = useState("");
  const [template, setTemplate] = useState("contact");
  const [buzzType, setBuzzType] = useState("");
  const [personaId, setPersonaId] = useState<number | null>(null);
  const [showPersonaForm, setShowPersonaForm] = useState(false);
  const [personaName, setPersonaName] = useState("");
  const [personaPrompt, setPersonaPrompt] = useState("");

  useEffect(() => {
    if (visible) {
      setName("");
      setTemplate("contact");
      setBuzzType(buzzTypes[0]?.type ?? "");
      setPersonaId(null);
      setShowPersonaForm(false);
      setPersonaName("");
      setPersonaPrompt("");
    }
  }, [visible, mode]);

  const personasQ = useQuery({
    queryKey: ["ai-personas"],
    queryFn: listAiPersonas,
    enabled: visible && mode === "ai",
    staleTime: 60_000,
  });

  // Default the persona selection to the first available one once loaded.
  useEffect(() => {
    if (mode === "ai" && personaId == null) {
      const first = personasQ.data?.items?.[0];
      if (first) setPersonaId(first.id);
    }
  }, [mode, personaId, personasQ.data]);

  // Open the inline create form automatically when the user has no
  // personas yet, so the "AI" tab is never a dead-end on mobile.
  useEffect(() => {
    if (
      mode === "ai" &&
      !personasQ.isLoading &&
      (personasQ.data?.items?.length ?? 0) === 0
    ) {
      setShowPersonaForm(true);
    }
  }, [mode, personasQ.isLoading, personasQ.data]);

  const createPersona = useMutation({
    mutationFn: async () =>
      createAiPersona({
        name: personaName.trim(),
        system_prompt: personaPrompt.trim() || undefined,
      }),
    onSuccess: async (persona) => {
      await personasQ.refetch();
      setPersonaId(persona.id);
      setShowPersonaForm(false);
      setPersonaName("");
      setPersonaPrompt("");
    },
  });

  const create = useMutation({
    mutationFn: async () => {
      const trimmed = name.trim();
      if (mode === "forms") return createForm({ title: trimmed, template });
      if (mode === "buzz") return createSocialProof({ name: trimmed, type: buzzType });
      return createBiolinkCompanion({ name: trimmed, persona_id: personaId! });
    },
    onSuccess: () => onCreated(),
  });

  const title =
    mode === "forms"
      ? "New form"
      : mode === "buzz"
        ? "New Buzz campaign"
        : "New AI companion";
  const nameLabel = mode === "forms" ? "Form title" : "Name";
  const personas = personasQ.data?.items ?? [];
  const canSubmit =
    name.trim().length > 0 &&
    !create.isPending &&
    (mode === "forms"
      ? true
      : mode === "buzz"
        ? !!buzzType
        : personaId != null);
  const canCreatePersona =
    personaName.trim().length > 0 && !createPersona.isPending;

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      onRequestClose={onClose}
    >
      <Pressable style={styles.createBackdrop} onPress={onClose} />
      <View
        style={[
          styles.createSheet,
          { backgroundColor: colors.background, borderColor: colors.border },
        ]}
      >
        <View style={styles.createHeader}>
          <Text
            style={{
              color: colors.foreground,
              fontFamily: "SpaceGrotesk_600SemiBold",
              fontSize: 16,
            }}
          >
            {title}
          </Text>
          <Pressable onPress={onClose} hitSlop={8}>
            <Feather name="x" size={20} color={colors.mutedForeground} />
          </Pressable>
        </View>

        <ScrollView
          contentContainerStyle={{ gap: 14, paddingBottom: 8 }}
          keyboardShouldPersistTaps="handled"
        >
          <View style={{ gap: 6 }}>
            <Text style={[styles.createFieldLabel, { color: colors.mutedForeground }]}>
              {nameLabel}
            </Text>
            <TextInput
              value={name}
              onChangeText={setName}
              placeholder={
                mode === "forms"
                  ? "e.g. Contact us"
                  : mode === "buzz"
                    ? "e.g. Recent signups"
                    : "e.g. Support bot"
              }
              placeholderTextColor={colors.mutedForeground}
              style={[
                styles.createInput,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  color: colors.foreground,
                  borderRadius: colors.radius,
                },
              ]}
            />
          </View>

          {mode === "forms" ? (
            <View style={{ gap: 6 }}>
              <Text style={[styles.createFieldLabel, { color: colors.mutedForeground }]}>
                Starting template
              </Text>
              <View style={styles.createChips}>
                {FORM_TEMPLATES.map((t) => {
                  const active = template === t.value;
                  return (
                    <Pressable
                      key={t.value}
                      onPress={() => setTemplate(t.value)}
                      style={[
                        styles.createChip,
                        {
                          backgroundColor: active ? colors.primary : colors.card,
                          borderColor: active ? colors.primary : colors.border,
                        },
                      ]}
                    >
                      <Text
                        style={{
                          color: active ? colors.primaryForeground : colors.foreground,
                          fontFamily: "SpaceGrotesk_600SemiBold",
                          fontSize: 12,
                        }}
                      >
                        {t.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            </View>
          ) : null}

          {mode === "buzz" ? (
            <View style={{ gap: 6 }}>
              <Text style={[styles.createFieldLabel, { color: colors.mutedForeground }]}>
                Campaign type
              </Text>
              <View style={styles.createChips}>
                {buzzTypes.map((t) => {
                  const active = buzzType === t.type;
                  return (
                    <Pressable
                      key={t.type}
                      onPress={() => setBuzzType(t.type)}
                      style={[
                        styles.createChip,
                        {
                          backgroundColor: active ? colors.primary : colors.card,
                          borderColor: active ? colors.primary : colors.border,
                        },
                      ]}
                    >
                      <Text
                        style={{
                          color: active ? colors.primaryForeground : colors.foreground,
                          fontFamily: "SpaceGrotesk_600SemiBold",
                          fontSize: 12,
                        }}
                      >
                        {t.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            </View>
          ) : null}

          {mode === "ai" ? (
            <View style={{ gap: 6 }}>
              <Text style={[styles.createFieldLabel, { color: colors.mutedForeground }]}>
                Persona
              </Text>
              {personasQ.isLoading ? (
                <ActivityIndicator color={colors.primary} />
              ) : personas.length === 0 && !showPersonaForm ? (
                <Text
                  style={{
                    color: colors.mutedForeground,
                    fontSize: 13,
                    fontFamily: "SpaceGrotesk_400Regular",
                  }}
                >
                  You don&apos;t have an AI persona yet. Create one below to wire
                  it into your companion.
                </Text>
              ) : personas.length > 0 ? (
                <View style={styles.createChips}>
                  {personas.map((p) => {
                    const active = personaId === p.id;
                    return (
                      <Pressable
                        key={p.id}
                        onPress={() => setPersonaId(p.id)}
                        style={[
                          styles.createChip,
                          {
                            backgroundColor: active ? colors.primary : colors.card,
                            borderColor: active ? colors.primary : colors.border,
                          },
                        ]}
                      >
                        <Text
                          style={{
                            color: active
                              ? colors.primaryForeground
                              : colors.foreground,
                            fontFamily: "SpaceGrotesk_600SemiBold",
                            fontSize: 12,
                          }}
                        >
                          {p.name}
                        </Text>
                      </Pressable>
                    );
                  })}
                </View>
              ) : null}

              {!showPersonaForm ? (
                <Pressable
                  onPress={() => setShowPersonaForm(true)}
                  style={{ paddingVertical: 4 }}
                  hitSlop={6}
                >
                  <Text
                    style={{
                      color: colors.primary,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                      fontSize: 13,
                    }}
                  >
                    + New persona
                  </Text>
                </Pressable>
              ) : (
                <View
                  style={{
                    gap: 10,
                    marginTop: 4,
                    padding: 12,
                    borderWidth: 1,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                    backgroundColor: colors.card,
                  }}
                >
                  <View style={{ gap: 6 }}>
                    <Text
                      style={[
                        styles.createFieldLabel,
                        { color: colors.mutedForeground },
                      ]}
                    >
                      Persona name
                    </Text>
                    <TextInput
                      value={personaName}
                      onChangeText={setPersonaName}
                      placeholder="e.g. Friendly support agent"
                      placeholderTextColor={colors.mutedForeground}
                      style={[
                        styles.createInput,
                        {
                          backgroundColor: colors.background,
                          borderColor: colors.border,
                          color: colors.foreground,
                          borderRadius: colors.radius,
                        },
                      ]}
                    />
                  </View>

                  <View style={{ gap: 6 }}>
                    <Text
                      style={[
                        styles.createFieldLabel,
                        { color: colors.mutedForeground },
                      ]}
                    >
                      Base instructions (optional)
                    </Text>
                    <TextInput
                      value={personaPrompt}
                      onChangeText={setPersonaPrompt}
                      placeholder="e.g. You are a helpful assistant for my biolink visitors. Keep replies short and friendly."
                      placeholderTextColor={colors.mutedForeground}
                      multiline
                      numberOfLines={4}
                      style={[
                        styles.createInput,
                        {
                          backgroundColor: colors.background,
                          borderColor: colors.border,
                          color: colors.foreground,
                          borderRadius: colors.radius,
                          minHeight: 88,
                          textAlignVertical: "top",
                        },
                      ]}
                    />
                  </View>

                  {createPersona.isError ? (
                    <Text style={{ color: colors.destructive, fontSize: 12 }}>
                      {(createPersona.error as { message?: string })?.message ||
                        "Couldn't create persona. Try again."}
                    </Text>
                  ) : null}

                  <View style={{ flexDirection: "row", gap: 8 }}>
                    <View style={{ flex: 1 }}>
                      <Button
                        label={
                          createPersona.isPending
                            ? "Creating…"
                            : "Create persona"
                        }
                        onPress={() => createPersona.mutate()}
                        disabled={!canCreatePersona}
                      />
                    </View>
                    {personas.length > 0 ? (
                      <View style={{ flex: 1 }}>
                        <Button
                          label="Cancel"
                          variant="ghost"
                          onPress={() => {
                            setShowPersonaForm(false);
                            setPersonaName("");
                            setPersonaPrompt("");
                          }}
                        />
                      </View>
                    ) : null}
                  </View>
                </View>
              )}
            </View>
          ) : null}

          {create.isError ? (
            <Text style={{ color: colors.destructive, fontSize: 12 }}>
              {(create.error as { message?: string })?.message ||
                "Couldn't create. Try again."}
            </Text>
          ) : null}
        </ScrollView>

        <View style={{ gap: 8, paddingTop: 8 }}>
          <Button
            label={create.isPending ? "Creating…" : "Create"}
            onPress={() => create.mutate()}
            disabled={!canSubmit}
          />
          <Button label="Cancel" variant="ghost" onPress={onClose} />
        </View>
      </View>
    </Modal>
  );
}

// Shared loading/error/empty wrapper for the Forms / Buzz / AI tabs so
// each picker shares the same states without repeating boilerplate.
function SpecialList(props: {
  query: { isLoading: boolean; isError: boolean; refetch: () => void };
  empty: string;
  filteredCount: number;
  inserting: boolean;
  createLabel?: string;
  onCreate?: () => void;
  children: ReactNode;
}) {
  const colors = useColors();
  const { query, empty, filteredCount, inserting, createLabel, onCreate, children } =
    props;

  if (query.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (query.isError) {
    return (
      <View style={[styles.center, { gap: 10 }]}>
        <Text style={{ color: colors.destructive, paddingHorizontal: 16 }}>
          Couldn't load this list.
        </Text>
        <Button label="Retry" onPress={() => query.refetch()} />
      </View>
    );
  }
  return (
    <ScrollView contentContainerStyle={{ gap: 8, paddingBottom: 24 }}>
      {inserting ? (
        <View style={styles.insertingHint}>
          <ActivityIndicator color={colors.primary} size="small" />
          <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
            Adding block…
          </Text>
        </View>
      ) : null}
      {onCreate ? (
        <Pressable
          onPress={onCreate}
          disabled={inserting}
          style={({ pressed }) => [
            styles.specialCreateRow,
            {
              borderColor: colors.primary + "55",
              backgroundColor: colors.primary + "0F",
              borderRadius: colors.radius,
              opacity: pressed ? 0.85 : inserting ? 0.6 : 1,
            },
          ]}
        >
          <View
            style={[
              styles.specialRowIcon,
              {
                backgroundColor: colors.primary + "18",
                borderColor: colors.primary + "33",
              },
            ]}
          >
            <Feather name="plus" size={16} color={colors.primary} />
          </View>
          <Text
            style={{
              flex: 1,
              color: colors.primary,
              fontFamily: "SpaceGrotesk_600SemiBold",
              fontSize: 14,
            }}
          >
            {createLabel ?? "Create new"}
          </Text>
        </Pressable>
      ) : null}
      {filteredCount === 0 ? (
        <Text
          style={{
            color: colors.mutedForeground,
            textAlign: "center",
            padding: 24,
            fontSize: 13,
            fontFamily: "SpaceGrotesk_400Regular",
          }}
        >
          {empty}
        </Text>
      ) : (
        children
      )}
    </ScrollView>
  );
}

// A single tappable row in the Forms / Buzz / AI pickers.
function SpecialRow(props: {
  icon: ComponentProps<typeof Feather>["name"];
  title: string;
  subtitle: string;
  disabled: boolean;
  onPress: () => void;
}) {
  const colors = useColors();
  const { icon, title, subtitle, disabled, onPress } = props;
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      style={({ pressed }) => [
        styles.specialRow,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : disabled ? 0.6 : 1,
        },
      ]}
    >
      <View
        style={[
          styles.specialRowIcon,
          {
            backgroundColor: colors.primary + "18",
            borderColor: colors.primary + "33",
          },
        ]}
      >
        <Feather name={icon} size={16} color={colors.primary} />
      </View>
      <View style={{ flex: 1, gap: 2 }}>
        <Text
          numberOfLines={1}
          style={[styles.rowTitle, { color: colors.foreground }]}
        >
          {title}
        </Text>
        <Text
          numberOfLines={1}
          style={[styles.rowSub, { color: colors.mutedForeground }]}
        >
          {subtitle}
        </Text>
      </View>
      <Feather name="plus" size={18} color={colors.primary} />
    </Pressable>
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
  searchWrap: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: Platform.OS === "ios" ? 10 : 4,
    borderWidth: 1,
  },
  searchInput: {
    flex: 1,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    padding: 0,
  },
  paletteGrid: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
  },
  paletteSectionHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingVertical: 8,
    paddingHorizontal: 2,
    borderBottomWidth: 1,
  },
  paletteSectionTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    textTransform: "uppercase",
    letterSpacing: 0.5,
  },
  paletteSectionMeta: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  paletteTile: {
    width: "31.5%",
    minHeight: 92,
    padding: 10,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
  },
  paletteIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  paletteLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    textAlign: "center",
    lineHeight: 14,
  },
  paletteLocked: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 9,
    textTransform: "uppercase",
    letterSpacing: 0.5,
  },
  tabs: { gap: 6, paddingVertical: 4, paddingRight: 8 },
  tab: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
  },
  modeTabs: { flexDirection: "row", gap: 6 },
  modeTab: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 5,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
  },
  searchBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 9,
    borderRadius: 999,
    borderWidth: 1,
  },
  specialRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 12,
    borderWidth: 1,
  },
  specialRowIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  insertingHint: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 6,
  },
  specialCreateRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 12,
    borderWidth: 1,
    borderStyle: "dashed",
  },
  createBackdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(0,0,0,0.5)",
  },
  createSheet: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    maxHeight: "85%",
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: 1,
    padding: 18,
    gap: 12,
  },
  createHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  createFieldLabel: {
    fontSize: 12,
    fontFamily: "SpaceGrotesk_600SemiBold",
    textTransform: "uppercase",
    letterSpacing: 0.5,
  },
  createInput: {
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    fontFamily: "SpaceGrotesk_400Regular",
  },
  createChips: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
  },
  createChip: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
  },
  tplCard: {
    borderWidth: 1,
    overflow: "hidden",
  },
  tplThumbStrip: {
    width: "100%",
    height: 96,
    position: "relative",
    overflow: "hidden",
  },
  tplThumbStripImage: { width: "100%", height: 96 },
  tplLockBadgeFloating: {
    position: "absolute",
    top: 6,
    right: 6,
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 999,
  },
  tplTitleRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  categoryPill: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 999,
    borderWidth: 1,
  },
  contentChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 6,
    paddingVertical: 3,
    borderRadius: 999,
    borderWidth: 1,
  },
  tplName: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 14,
    flexShrink: 1,
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
