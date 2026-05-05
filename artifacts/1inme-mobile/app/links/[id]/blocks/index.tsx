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
  View,
} from "react-native";

import { BlockPickerPreview } from "@/components/BlockPickerPreview";
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
  type CardTemplateChildSummary,
  type PreviewLayoutCell,
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
                  <BlockPickerPreview type={k.type} />
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
