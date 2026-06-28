import AsyncStorage from "@react-native-async-storage/async-storage";
import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { LinearGradient } from "expo-linear-gradient";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withTiming,
} from "react-native-reanimated";
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
} from "@/lib/api/cardTemplates";
import {
  applyPageTemplate,
  getPageTemplatePreview,
  listPageTemplates,
  type PageTemplate,
} from "@/lib/api/pageTemplates";
import { BlockView, StoreCartProvider } from "@/app/biolink/[handle]";
import { PreviewBlueprint } from "@/components/PreviewBlueprint";
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
  updateAiPersonaBrandKit,
} from "@/lib/api/aiCompanions";
import { getProfile } from "@/lib/api/profile";

// The design preview renders blocks read-only; embed taps are inert there
// (no WebView modal), so a stable no-op satisfies BlockView's openEmbed prop
// without re-creating the callback on every render.
const NOOP_EMBED = () => {};

// A synthetic alias for the design preview. Block taps inside the preview
// fire best-effort analytics against this handle; it resolves to nothing
// server-side, so previews never pollute a real biolink's stats.
const PREVIEW_ALIAS = "__design_preview__";

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
            body="Add a header, a link button, an image, or any other block to start building your Link in Bio."
            action={
              <View style={{ gap: 8 }}>
                <Button
                  label="Build with AI"
                  onPress={() => router.push(`/links/${id}/ai-builder` as any)}
                />
                <Button
                  label="Start from a design"
                  variant="ghost"
                  onPress={() => openSpecial("designs")}
                />
                <Button
                  label="Add a block"
                  variant="ghost"
                  onPress={() => setPicker(true)}
                />
                <Button
                  label="Templates, forms & more"
                  variant="ghost"
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
              label="Build with AI"
              variant="ghost"
              onPress={() => router.push(`/links/${id}/ai-builder` as any)}
            />
            <Button
              label="Start from a design"
              variant="ghost"
              onPress={() => openSpecial("designs")}
            />
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
        onPageApplied={(blocks) => {
          // A page design replaces the whole page. The apply endpoint
          // returns the full freshly-created tree (parents first, then
          // children by sort order), so swap the cache outright rather than
          // patching in place.
          qc.setQueryData<Block[]>(["blocks", id], () => blocks);
          setSpecialOpen(false);
          setPreviewTpl(null);
          setHighlightId(blocks[0]?.id ?? null);
        }}
        previewTpl={previewTpl}
        clearPreview={() => setPreviewTpl(null)}
      />
    </View>
  );
}

// The four inline-palette tabs the special panel exposes, matching the
// web editor's special panel (Cards / Forms / Buzz / AI).
type SpecialMode = "designs" | "templates" | "forms" | "buzz" | "ai";

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
  // A full-page design REPLACES the link's blocks; the apply endpoint hands
  // back the whole freshly-created tree so the parent can swap the list in
  // place. Page-template preview/apply state lives inside SpecialPanel.
  onPageApplied: (blocks: Block[]) => void;
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

/**
 * Full-screen visual preview for a page design. Tapping a design opens this
 * sheet, which fetches the template's real (sanitized, no-DB-write) block
 * tree and renders it with the *same* native block renderer (`BlockView`)
 * the public biolink page uses — so the user sees a true picture of the
 * finished page before replacing their current one. From here they can
 * apply directly or back out. Card/grid nesting is preserved because the
 * full flattened tree (with parent ids) is handed to every BlockView.
 */
function PageDesignPreview({
  linkId,
  template,
  applying,
  onApply,
  onClose,
}: {
  linkId: number;
  template: PageTemplate | null;
  applying: boolean;
  onApply: () => void;
  onClose: () => void;
}) {
  const colors = useColors();

  const previewQ = useQuery({
    queryKey: ["page-template-preview", linkId, template?.id],
    queryFn: () => getPageTemplatePreview(linkId, template!.id),
    enabled: !!template && Number.isFinite(linkId),
    staleTime: 60_000,
  });

  const blocks = previewQ.data?.blocks ?? [];
  const rootBlocks = useMemo(
    () => blocks.filter((b) => !b.parent_id),
    [blocks],
  );

  return (
    <Modal
      visible={!!template}
      animationType="slide"
      onRequestClose={onClose}
    >
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        {/* Header: back out + template name */}
        <View
          style={[
            styles.previewBar,
            { borderColor: colors.border, backgroundColor: colors.background },
          ]}
        >
          <Pressable
            onPress={onClose}
            hitSlop={8}
            style={{ flexDirection: "row", alignItems: "center", gap: 4 }}
          >
            <Feather name="chevron-left" size={22} color={colors.foreground} />
            <Text
              style={{
                color: colors.foreground,
                fontSize: 15,
                fontFamily: "SpaceGrotesk_600SemiBold",
              }}
            >
              Back
            </Text>
          </Pressable>
          <Text
            numberOfLines={1}
            style={{
              flex: 1,
              textAlign: "center",
              color: colors.foreground,
              fontSize: 15,
              fontFamily: "SpaceGrotesk_700Bold",
            }}
          >
            {template?.name ?? "Preview"}
          </Text>
          <Pressable onPress={onClose} hitSlop={8}>
            <Feather name="x" size={22} color={colors.mutedForeground} />
          </Pressable>
        </View>

        {/* Subtitle: category / recommended / lock */}
        {template ? (
          <Text
            style={{
              color: colors.mutedForeground,
              fontSize: 12,
              fontFamily: "SpaceGrotesk_500Medium",
              paddingHorizontal: 16,
              paddingTop: 8,
              textAlign: "center",
            }}
          >
            {template.category_label}
            {template.recommended ? " · Recommended for you" : ""}
            {template.locked
              ? ` · ${template.plan_tier?.toUpperCase() || "PRO"} only`
              : ""}
          </Text>
        ) : null}

        {/* Body: rendered blocks */}
        {previewQ.isLoading ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.primary} />
            <Text
              style={{
                color: colors.mutedForeground,
                marginTop: 8,
                fontFamily: "SpaceGrotesk_400Regular",
                fontSize: 13,
              }}
            >
              Building preview…
            </Text>
          </View>
        ) : previewQ.isError ? (
          <View style={styles.center}>
            <Feather
              name="alert-circle"
              size={32}
              color={colors.mutedForeground}
            />
            <Text
              style={{
                color: colors.foreground,
                textAlign: "center",
                marginTop: 8,
                fontFamily: "SpaceGrotesk_500Medium",
              }}
            >
              Couldn&apos;t load this preview.
            </Text>
            <View style={{ marginTop: 12 }}>
              <Button label="Retry" onPress={() => previewQ.refetch()} />
            </View>
          </View>
        ) : rootBlocks.length === 0 ? (
          <View style={styles.center}>
            <Feather name="layout" size={32} color={colors.mutedForeground} />
            <Text
              style={{
                color: colors.mutedForeground,
                textAlign: "center",
                marginTop: 8,
                fontFamily: "SpaceGrotesk_400Regular",
              }}
            >
              This design has no visible blocks to preview.
            </Text>
          </View>
        ) : (
          <StoreCartProvider alias={PREVIEW_ALIAS}>
            <ScrollView
              contentContainerStyle={{
                paddingHorizontal: 20,
                paddingTop: 14,
                paddingBottom: 32,
                gap: 10,
                alignItems: "center",
              }}
            >
              <View style={{ width: "100%", maxWidth: 480, gap: 10 }}>
                {rootBlocks.map((b) => (
                  <BlockView
                    key={b.id}
                    block={b}
                    alias={PREVIEW_ALIAS}
                    allBlocks={blocks}
                    openEmbed={NOOP_EMBED}
                  />
                ))}
              </View>
            </ScrollView>
          </StoreCartProvider>
        )}

        {/* Footer: apply / lock hint / cancel */}
        <View
          style={[
            styles.previewFooter,
            { borderColor: colors.border, backgroundColor: colors.background },
          ]}
        >
          {template?.locked ? (
            <View
              style={[styles.lockHint, { borderColor: colors.primary + "55" }]}
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
                Upgrade to {template.plan_tier?.toUpperCase() || "PRO"} to use
                this design.
              </Text>
            </View>
          ) : (
            <Button
              label={applying ? "Applying…" : "Use this design"}
              onPress={onApply}
              disabled={applying}
            />
          )}
        </View>
      </View>
    </Modal>
  );
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
    onPageApplied,
    onInserted,
    previewTpl,
    clearPreview,
  } = props;

  const qc = useQueryClient();
  const [activeCat, setActiveCat] = useState<string>("all");
  const [search, setSearch] = useState("");
  const [createOpen, setCreateOpen] = useState(false);
  // Page-design preview is kept local to the panel (unlike the card preview,
  // which the parent owns) — applying a page design is self-contained here.
  const [previewPage, setPreviewPage] = useState<PageTemplate | null>(null);

  // Reset the search box, category filter and any open sheets whenever the
  // user switches tabs so a query/category set on one tab doesn't silently
  // hide everything on the next (card and page categories differ).
  useEffect(() => {
    setSearch("");
    setCreateOpen(false);
    setActiveCat("all");
    setPreviewPage(null);
  }, [mode]);

  const q = useQuery({
    queryKey: ["card-templates", linkId],
    queryFn: () => listCardTemplates(linkId),
    enabled: visible && mode === "templates" && Number.isFinite(linkId),
    staleTime: 60_000,
  });

  const pageQ = useQuery({
    queryKey: ["page-templates", linkId],
    queryFn: () => listPageTemplates(linkId),
    enabled: visible && mode === "designs" && Number.isFinite(linkId),
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

  // Applying a page design replaces the link's blocks. The server returns
  // HTTP 409 (`confirm_overwrite`) when the link already has blocks; we
  // re-issue with confirm_overwrite after the user accepts the alert.
  const applyPage = useMutation({
    mutationFn: (vars: { templateId: number; confirm: boolean }) =>
      applyPageTemplate(linkId, {
        template_id: vars.templateId,
        confirm_overwrite: vars.confirm,
      }),
    onSuccess: (res) => {
      setPreviewPage(null);
      onPageApplied(res.blocks);
    },
    onError: (err: unknown, vars) => {
      const e = err as { status?: number; message?: string };
      if (e?.status === 409 && !vars.confirm) {
        Alert.alert(
          "Replace your page?",
          "Applying this design will remove your current blocks and replace them with the template. This can't be undone.",
          [
            { text: "Cancel", style: "cancel" },
            {
              text: "Replace",
              style: "destructive",
              onPress: () =>
                applyPage.mutate({ templateId: vars.templateId, confirm: true }),
            },
          ],
        );
        return;
      }
      Alert.alert(
        "Couldn't apply design",
        e?.message || "Something went wrong. Please try again.",
      );
    },
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

  // The same handful of placeholder assets (avatars, covers, documents)
  // repeat across every template's mini-blueprint. Collect their unique
  // URLs once so we can warm Expo's image cache when the gallery opens —
  // otherwise each tile triggers its own network fetch and media/avatar
  // cells flash blank while the picker is scrolled.
  const previewImageUrls = useMemo(() => {
    const urls = new Set<string>();
    for (const t of items) {
      for (const row of t.preview_layout ?? []) {
        for (const cell of row) {
          if (cell.img) urls.add(cell.img);
        }
      }
    }
    return Array.from(urls);
  }, [items]);

  // Track which URLs we've already asked Expo to prefetch so re-renders
  // (search, tab switches) don't re-issue the same requests.
  const prefetchedRef = useRef<Set<string>>(new Set());
  useEffect(() => {
    if (!visible || mode !== "templates") return;
    for (const url of previewImageUrls) {
      if (prefetchedRef.current.has(url)) continue;
      prefetchedRef.current.add(url);
      // Fire-and-forget: a failed prefetch just falls back to lazy load.
      Image.prefetch(url).catch(() => {
        prefetchedRef.current.delete(url);
      });
    }
  }, [visible, mode, previewImageUrls]);

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

  const pageItems = pageQ.data?.items ?? [];
  const pageCats = pageQ.data?.categories ?? {};

  const visiblePageItems = useMemo(() => {
    let list =
      activeCat === "all"
        ? pageItems
        : pageItems.filter((t) => t.category === activeCat);
    const term = search.trim().toLowerCase();
    if (term) {
      list = list.filter(
        (t) =>
          t.name.toLowerCase().includes(term) ||
          (t.description ?? "").toLowerCase().includes(term),
      );
    }
    return list;
  }, [pageItems, activeCat, search]);

  const pageCatOptions = useMemo(() => {
    const used = new Set(pageItems.map((t) => t.category));
    return [
      { key: "all", label: "All" },
      ...Object.entries(pageCats)
        .filter(([key]) => used.has(key))
        .map(([key, label]) => ({ key, label })),
    ];
  }, [pageItems, pageCats]);

  // Warm the page-design thumbnails' placeholder assets, same as the card
  // gallery does, so blueprint media cells don't flash blank on scroll.
  const pagePreviewImageUrls = useMemo(() => {
    const urls = new Set<string>();
    for (const t of pageItems) {
      for (const row of t.preview_layout ?? []) {
        for (const cell of row) {
          if (cell.img) urls.add(cell.img);
        }
      }
    }
    return Array.from(urls);
  }, [pageItems]);
  useEffect(() => {
    if (!visible || mode !== "designs") return;
    for (const url of pagePreviewImageUrls) {
      if (prefetchedRef.current.has(url)) continue;
      prefetchedRef.current.add(url);
      Image.prefetch(url).catch(() => {
        prefetchedRef.current.delete(url);
      });
    }
  }, [visible, mode, pagePreviewImageUrls]);

  const MODE_TABS: Array<{
    key: SpecialMode;
    label: string;
    icon: ComponentProps<typeof Feather>["name"];
  }> = [
    { key: "designs", label: "Designs", icon: "grid" },
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

          {mode === "designs" ? (
            pageQ.isLoading ? (
              <View style={styles.center}>
                <ActivityIndicator color={colors.primary} />
              </View>
            ) : pageQ.isError ? (
              <View style={styles.center}>
                <Text style={{ color: colors.destructive, padding: 16 }}>
                  Couldn't load designs. Pull down to retry.
                </Text>
              </View>
            ) : (
              <>
                <Text
                  style={{
                    color: colors.mutedForeground,
                    fontSize: 12,
                    fontFamily: "SpaceGrotesk_400Regular",
                    paddingHorizontal: 2,
                    paddingBottom: 8,
                  }}
                >
                  Pick a ready-made page design. Applying one replaces your
                  current blocks.
                </Text>
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={styles.tabs}
                >
                  {pageCatOptions.map((c) => {
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
                            borderColor: active
                              ? colors.primary
                              : colors.border,
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
                  {visiblePageItems.length === 0 ? (
                    <Text
                      style={{
                        color: colors.mutedForeground,
                        textAlign: "center",
                        padding: 24,
                      }}
                    >
                      No designs in this category yet.
                    </Text>
                  ) : (
                    visiblePageItems.map((t) => {
                      const previewRows = t.preview_layout ?? [];
                      return (
                        <Pressable
                          key={t.id}
                          onPress={() => setPreviewPage(t)}
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
                              <PreviewBlueprint rows={previewRows} height={120} />
                            ) : (
                              <View
                                style={{
                                  width: "100%",
                                  height: 120,
                                  alignItems: "center",
                                  justifyContent: "center",
                                }}
                              >
                                <Feather
                                  name="grid"
                                  size={24}
                                  color={colors.primary}
                                />
                              </View>
                            )}
                            {t.recommended ? (
                              <View
                                style={[
                                  styles.tplLockBadgeFloating,
                                  {
                                    backgroundColor: colors.primary,
                                    left: 8,
                                    right: undefined as unknown as number,
                                  },
                                ]}
                              >
                                <Feather name="star" size={10} color="#fff" />
                                <Text
                                  style={{
                                    color: "#fff",
                                    fontSize: 10,
                                    fontFamily: "SpaceGrotesk_600SemiBold",
                                  }}
                                >
                                  FOR YOU
                                </Text>
                              </View>
                            ) : null}
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
                                  {(
                                    t.category_label || t.category
                                  ).toUpperCase()}
                                </Text>
                              </View>
                            </View>
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
                            <Text
                              style={{
                                color: colors.primary,
                                fontSize: 11,
                                fontFamily: "SpaceGrotesk_600SemiBold",
                              }}
                            >
                              {t.blocks_count} block
                              {t.blocks_count === 1 ? "" : "s"}
                            </Text>
                          </View>
                        </Pressable>
                      );
                    })
                  )}
                </ScrollView>
              </>
            )
          ) : mode === "forms" ? (
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
              empty="You don't have any AI companions for Link in Bio placement yet. Create your first one to drop it in here."
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
                    label={apply.isPending ? "Adding…" : "Add to my Link in Bio"}
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

      <PageDesignPreview
        linkId={linkId}
        template={previewPage}
        applying={applyPage.isPending}
        onApply={() => {
          if (previewPage) {
            applyPage.mutate({ templateId: previewPage.id, confirm: false });
          }
        }}
        onClose={() => setPreviewPage(null)}
      />

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
  // On-Brand AI (Task #2664): default-on; injects the owner's Brand Kit voice.
  const [personaUseBrandKit, setPersonaUseBrandKit] = useState(true);

  useEffect(() => {
    if (visible) {
      setName("");
      setTemplate("contact");
      setBuzzType(buzzTypes[0]?.type ?? "");
      setPersonaId(null);
      setShowPersonaForm(false);
      setPersonaName("");
      setPersonaPrompt("");
      setPersonaUseBrandKit(true);
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
        use_brand_kit: personaUseBrandKit,
      }),
    onSuccess: async (persona) => {
      await personasQ.refetch();
      setPersonaId(persona.id);
      setShowPersonaForm(false);
      setPersonaName("");
      setPersonaPrompt("");
    },
  });

  // On-Brand AI (Task #2679): flip `use_brand_kit` on the already-selected
  // agent. Web only exposes this inside a full persona save; mobile makes it
  // a real, reversible setting by patching just this field and refetching.
  const updateBrandKit = useMutation({
    mutationFn: async (next: boolean) =>
      updateAiPersonaBrandKit(personaId!, next),
    onSuccess: () => personasQ.refetch(),
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
  const selectedPersona = personas.find((p) => p.id === personaId) ?? null;
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
                Agent
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
                  You don&apos;t have an AI agent yet. Create one below to wire
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

              {selectedPersona && !showPersonaForm ? (
                <View
                  style={{
                    flexDirection: "row",
                    alignItems: "center",
                    gap: 12,
                    marginTop: 4,
                    padding: 12,
                    borderWidth: 1,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                    backgroundColor: colors.card,
                  }}
                >
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text
                      style={[
                        styles.createFieldLabel,
                        { color: colors.foreground },
                      ]}
                    >
                      On-Brand AI
                    </Text>
                    <Text
                      style={{ color: colors.mutedForeground, fontSize: 12 }}
                    >
                      {updateBrandKit.isError
                        ? (updateBrandKit.error as { message?: string })
                            ?.message || "Couldn't update. Try again."
                        : `Use your Brand Kit voice and tone in ${selectedPersona.name}'s replies.`}
                    </Text>
                  </View>
                  {updateBrandKit.isPending ? (
                    <ActivityIndicator color={colors.primary} />
                  ) : (
                    <Switch
                      value={selectedPersona.use_brand_kit}
                      onValueChange={(v) => updateBrandKit.mutate(v)}
                      trackColor={{ true: colors.primary, false: colors.border }}
                    />
                  )}
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
                    + New agent
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
                      Agent name
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
                      placeholder="e.g. You are a helpful assistant for my Link in Bio visitors. Keep replies short and friendly."
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

                  <View
                    style={{
                      flexDirection: "row",
                      alignItems: "center",
                      gap: 12,
                    }}
                  >
                    <View style={{ flex: 1, gap: 2 }}>
                      <Text
                        style={[
                          styles.createFieldLabel,
                          { color: colors.foreground },
                        ]}
                      >
                        On-Brand AI
                      </Text>
                      <Text
                        style={{ color: colors.mutedForeground, fontSize: 12 }}
                      >
                        Use your Brand Kit voice and tone in replies.
                      </Text>
                    </View>
                    <Switch
                      value={personaUseBrandKit}
                      onValueChange={setPersonaUseBrandKit}
                      trackColor={{ true: colors.primary, false: colors.border }}
                    />
                  </View>

                  {createPersona.isError ? (
                    <Text style={{ color: colors.destructive, fontSize: 12 }}>
                      {(createPersona.error as { message?: string })?.message ||
                        "Couldn't create agent. Try again."}
                    </Text>
                  ) : null}

                  <View style={{ flexDirection: "row", gap: 8 }}>
                    <View style={{ flex: 1 }}>
                      <Button
                        label={
                          createPersona.isPending
                            ? "Creating…"
                            : "Create agent"
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
  previewBar: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    paddingTop: 52,
    paddingBottom: 12,
    borderBottomWidth: 1,
  },
  previewFooter: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 28,
    borderTopWidth: 1,
  },
});
