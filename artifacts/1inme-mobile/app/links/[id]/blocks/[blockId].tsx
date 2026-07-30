import AsyncStorage from "@react-native-async-storage/async-storage";
import Slider from "@react-native-community/slider";
import { Feather } from "@expo/vector-icons";
import * as ImagePicker from "expo-image-picker";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Stack,
  useFocusEffect,
  useLocalSearchParams,
  useRouter,
} from "expo-router";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Image,
  PanResponder,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from "react-native";

// Style catalogs mirror the web editor's `block-settings-form.blade.php`
// list/list_numbered/list_pricing blocks so saved blocks roam between
// the two surfaces with identical option keys.
type StyleOption = { key: string; label: string; desc?: string };
const LIST_STYLES: StyleOption[] = [
  { key: "clean", label: "Clean" },
  { key: "boxed", label: "Boxed" },
  { key: "divided", label: "Divided" },
  { key: "checklist", label: "Checklist" },
  { key: "timeline", label: "Timeline" },
];
const LIST_NUMBERED_STYLES: StyleOption[] = [
  { key: "clean", label: "Plain" },
  { key: "boxed", label: "Boxed" },
  { key: "divided", label: "Divided" },
  { key: "pill", label: "Pill Badge" },
  { key: "badge_square", label: "Square Badge" },
  { key: "outlined", label: "Outlined Big" },
];
const PRICING_STYLES: StyleOption[] = [
  { key: "classic", label: "Classic", desc: "Name + price with leader dots" },
  { key: "menu", label: "Menu", desc: "Name, description, price" },
  { key: "cards", label: "Card Grid", desc: "Stacked pricing cards" },
  { key: "comparison", label: "Comparison", desc: "Included / not included" },
  { key: "featured", label: "Featured", desc: "Highlight one plan" },
];

// Border color validity hint (Task #6096): free-text border color fields
// accept anything (value is saved as typed, matching web behavior), but an
// obviously invalid string — a truncated hex like "#fffff" or a stray word —
// shows a subtle inline warning so broken borders don't ship silently.
// The block between the extract markers is lifted verbatim by the
// source-driven test (scripts/test-border-color-validity.mjs); keep it
// self-contained and free of imports.
// [extract:isLikelyCssColor:start]
const CSS_NAMED_COLORS = new Set(
  (
    "aliceblue antiquewhite aqua aquamarine azure beige bisque black blanchedalmond blue " +
    "blueviolet brown burlywood cadetblue chartreuse chocolate coral cornflowerblue cornsilk " +
    "crimson cyan darkblue darkcyan darkgoldenrod darkgray darkgreen darkgrey darkkhaki " +
    "darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon darkseagreen " +
    "darkslateblue darkslategray darkslategrey darkturquoise darkviolet deeppink deepskyblue " +
    "dimgray dimgrey dodgerblue firebrick floralwhite forestgreen fuchsia gainsboro ghostwhite " +
    "gold goldenrod gray green greenyellow grey honeydew hotpink indianred indigo ivory khaki " +
    "lavender lavenderblush lawngreen lemonchiffon lightblue lightcoral lightcyan " +
    "lightgoldenrodyellow lightgray lightgreen lightgrey lightpink lightsalmon lightseagreen " +
    "lightskyblue lightslategray lightslategrey lightsteelblue lightyellow lime limegreen " +
    "linen magenta maroon mediumaquamarine mediumblue mediumorchid mediumpurple " +
    "mediumseagreen mediumslateblue mediumspringgreen mediumturquoise mediumvioletred " +
    "midnightblue mintcream mistyrose moccasin navajowhite navy oldlace olive olivedrab " +
    "orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred papayawhip " +
    "peachpuff peru pink plum powderblue purple rebeccapurple red rosybrown royalblue " +
    "saddlebrown salmon sandybrown seagreen seashell sienna silver skyblue slateblue " +
    "slategray slategrey snow springgreen steelblue tan teal thistle tomato turquoise violet " +
    "wheat white whitesmoke yellow yellowgreen transparent currentcolor inherit"
  ).split(" "),
);
function isLikelyCssColor(raw: string): boolean {
  const v = raw.trim();
  if (v === "") return true; // blank = use the default, never a warning
  if (/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(v)) return true;
  if (/^(?:rgb|rgba|hsl|hsla)\(\s*[^)]+\)$/i.test(v)) return true;
  if (/^var\(\s*--[\w-]+\s*(?:,[^)]*)?\)$/i.test(v)) return true;
  if (/^[a-zA-Z]+$/.test(v)) return CSS_NAMED_COLORS.has(v.toLowerCase());
  return false;
}
// [extract:isLikelyCssColor:end]

// [extract:borderSwatchSelected:start]
function borderSwatchSelected(value: string, swatch: string): boolean {
  return value.trim().toLowerCase() === swatch.trim().toLowerCase();
}
// [extract:borderSwatchSelected:end]

// Quick-pick swatches for border color fields; tapping one writes the hex
// into the paired free-text input (which stays authoritative).
const BORDER_ROW_SWATCHES = [
  "#ffffff",
  "#000000",
  "#7c3aed",
  "#3b82f6",
  "#22c55e",
  "#f59e0b",
  "#ef4444",
  "#ec4899",
];

function BorderColorSwatchRow({
  value,
  onSelect,
  testIDPrefix,
  chipBorderColor,
}: {
  value: string;
  onSelect: (color: string) => void;
  testIDPrefix: string;
  chipBorderColor: string;
}) {
  // Recently used custom colors surface here too, deduped against presets,
  // so a brand hex typed anywhere is one tap away in the border rows.
  const recents = useRecentColors().filter(
    (c) => !BORDER_ROW_SWATCHES.some((p) => p.toLowerCase() === c.toLowerCase()),
  );
  return (
    <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6, alignItems: "center" }}>
      {BORDER_ROW_SWATCHES.map((c) => {
        const sel = borderSwatchSelected(value, c);
        return (
          <Pressable
            {...WEB_FOCUS_RING_PROPS}
            key={c}
            testID={`${testIDPrefix}-${c.slice(1)}`}
            accessibilityLabel={`Border color ${c}`}
            onPress={() => onSelect(c)}
            style={{
              width: 22,
              height: 22,
              borderRadius: 999,
              backgroundColor: c,
              borderWidth: sel ? 2 : 1,
              borderColor: sel ? "#7c3aed" : chipBorderColor,
            }}
          />
        );
      })}
      {recents.length > 0 ? (
        <View style={{ width: 1, height: 16, backgroundColor: chipBorderColor }} />
      ) : null}
      {recents.map((c) => {
        const sel = borderSwatchSelected(value, c);
        return (
          <Pressable
            {...WEB_FOCUS_RING_PROPS}
            key={`recent-${c}`}
            testID={`${testIDPrefix}-recent-${c.replace(/[^a-z0-9]/gi, "")}`}
            accessibilityLabel={`Recent color ${c}`}
            onPress={() => onSelect(c)}
            style={{
              width: 22,
              height: 22,
              borderRadius: 999,
              backgroundColor: c,
              borderWidth: sel ? 2 : 1,
              borderColor: sel ? "#7c3aed" : chipBorderColor,
            }}
          />
        );
      })}
    </View>
  );
}

type ListItem = { text: string; icon: string };
type PricingItem = {
  name: string;
  description: string;
  price: string;
  period: string;
  included: boolean;
  featured: boolean;
  thumbnail: string;
  icon: string;
};

function normalizeListItems(raw: unknown): ListItem[] {
  if (!Array.isArray(raw)) return [{ text: "", icon: "" }];
  const out: ListItem[] = raw.map((i) => {
    if (typeof i === "string") return { text: i, icon: "" };
    if (i && typeof i === "object") {
      const o = i as Record<string, unknown>;
      return {
        text: typeof o.text === "string" ? o.text : "",
        icon: typeof o.icon === "string" ? o.icon : "",
      };
    }
    return { text: "", icon: "" };
  });
  return out.length > 0 ? out : [{ text: "", icon: "" }];
}

function normalizePricingItems(raw: unknown): PricingItem[] {
  if (!Array.isArray(raw)) return [emptyPricingItem()];
  const out: PricingItem[] = raw.map((i) => {
    const o = (i && typeof i === "object" ? i : {}) as Record<string, unknown>;
    return {
      name: typeof o.name === "string" ? o.name : "",
      description: typeof o.description === "string" ? o.description : "",
      price: typeof o.price === "string" ? o.price : "",
      period: typeof o.period === "string" ? o.period : "",
      // Default to true to match the web editor — most items are included
      // by default; a missing key means "no opinion captured yet".
      included: o.included === undefined ? true : !!o.included,
      featured: !!o.featured,
      thumbnail: typeof o.thumbnail === "string" ? o.thumbnail : "",
      icon: typeof o.icon === "string" ? o.icon : "",
    };
  });
  return out.length > 0 ? out : [emptyPricingItem()];
}

// Profile-card socials row. Accepts both the profile-card `{name,url}`
// shape and the legacy socials `{platform,url}` shape on the way in,
// always persisting `{name,url}`.
type ProfileSocial = { name: string; url: string };

function normalizeProfileSocials(raw: unknown): ProfileSocial[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((i) => {
      const o = (i && typeof i === "object" ? i : {}) as Record<string, unknown>;
      const name =
        typeof o.name === "string"
          ? o.name
          : typeof o.platform === "string"
            ? o.platform
            : "";
      const url = typeof o.url === "string" ? o.url : "";
      return { name, url };
    })
    .filter((s) => s.name !== "" || s.url !== "");
}

// Profile-card stat row (used by the "stats" layout). The renderer reads
// `[{label, value}]`; value may arrive as a string or number, so coerce
// to a string for the editable field. The sanitizer drops fully-empty
// rows and caps the list at 6.
type ProfileStat = { label: string; value: string };

function normalizeProfileStats(raw: unknown): ProfileStat[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((i) => {
      const o = (i && typeof i === "object" ? i : {}) as Record<string, unknown>;
      const label = typeof o.label === "string" ? o.label : "";
      const value =
        typeof o.value === "string"
          ? o.value
          : typeof o.value === "number"
            ? String(o.value)
            : "";
      return { label, value };
    })
    .filter((s) => s.label !== "" || s.value !== "");
}

// Profile-card badge row (used by the "badges" layout). The renderer
// accepts either `{label}` objects or bare strings; we always persist
// `{label}` to match the web editor. The sanitizer drops empties and
// caps the list at 12.
type ProfileBadge = { label: string };

function normalizeProfileBadges(raw: unknown): ProfileBadge[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((i) => {
      if (typeof i === "string") return { label: i };
      const o = (i && typeof i === "object" ? i : {}) as Record<string, unknown>;
      return { label: typeof o.label === "string" ? o.label : "" };
    })
    .filter((b) => b.label !== "");
}

// Resolve the profile-card structural layout the same way the public
// renderer does: prefer the `_style._profile_layout` token, falling back
// to a per-type default for older blocks. Drives which bespoke editors
// (stats vs badges) are relevant for this block.
function resolveProfileLayout(block: Block | undefined): string {
  if (!block) return "";
  const style = (block.settings?._style as Record<string, unknown> | undefined) ?? {};
  const tok = typeof style._profile_layout === "string" ? style._profile_layout : "";
  if (tok) return tok;
  switch (block.type) {
    case "profile_card_v2":
      return "cover_hero";
    case "profile_card_v3":
      return "stats";
    case "profile_card_v4":
      return "badges";
    default:
      return "classic_creator";
  }
}

function emptyPricingItem(): PricingItem {
  return {
    name: "",
    description: "",
    price: "",
    period: "",
    included: true,
    featured: false,
    thumbnail: "",
    icon: "",
  };
}

import {
  ListBlockView,
  PricingBlockView,
  visibleListItems,
  visiblePricingItems,
} from "@/components/BlockListPreview";
import {
  AvatarFrame,
  AVATAR_FRAME_KEYS,
  AVATAR_FRAME_LABELS,
  isAvatarFrameKey,
} from "@/components/AvatarFrame";
import { BlockView, StoreCartProvider } from "@/app/biolink/[handle]";
import { Button } from "@/components/Button";
import { ColorSwatchRow } from "@/components/ColorSwatchRow";

// Live block-background preview (Task #5984). The bg-preset section renders
// the block through the same native renderer the design preview uses
// (`BlockView`), read-only, so dragging the transparency slider fades the
// preset layer in real time. Taps are inert (pointerEvents off) and the
// synthetic alias resolves to nothing server-side, so the preview never
// pollutes a real biolink's stats.
const BLOCK_PREVIEW_ALIAS = "__block_preview__";
const NOOP_BLOCK_PREVIEW_EMBED = () => {};

import { DictationMic } from "@/components/DictationMic";
import { DraggableRepeaterRows } from "@/components/DraggableRepeaterRows";
import {
  IconPickerButton,
  IconPickerModal,
} from "@/components/IconPickerModal";
import { MapPickerModal, type PickedPoint } from "@/components/MapPickerModal";
import { StockImageGalleryPicker } from "@/components/StockImageGalleryPicker";
import { TextField } from "@/components/TextField";
import { setVoiceSurface } from "@/components/VoiceAssistant";
import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";
import { getLink, listLinks, type Link } from "@/lib/api/links";
import {
  blockKind,
  fetchOgMeta,
  listBlocks,
  updateBlock,
  type Block,
  type OgMeta,
} from "@/lib/api/blocks";
import { getBaseUrl } from "@/lib/api";
import {
  importPlatformAsset,
  listVaultFiles,
  uploadVaultFile,
  type VaultFile,
} from "@/lib/api/files";
import { getBgPresets } from "@/lib/api/bgPresets";
import { LinearGradient } from "expo-linear-gradient";
import {
  variantsForType,
  findVariant,
  applyRemoteDesignCatalog,
} from "@/lib/blockVariants";
import { getBlockCatalog } from "@/lib/api/blocks";
import { canonicalBlockType } from "@/lib/blockTypeRegistry";
import {
  rememberRecentColorFromTyping,
  rememberRecentColors,
  useRecentColors,
} from "@/lib/recentColors";
import { showAlert } from "@/lib/webAlert";
import { handlePlanLockedError } from "@/lib/upgradePrompt";

// Quick-pick tints for the avatar-frame color row (Task #5910). "Auto"
// (empty string) defers to the layout accent, mirroring the web editor.
const AVATAR_FRAME_COLOR_PRESETS = [
  "#7d9bff",
  "#f59e0b",
  "#ef4444",
  "#10b981",
  "#14b8a6",
  "#ec4899",
  "#f8fafc",
  "#0f172a",
];

// Quick-pick swatches for the Borders section color fields (Task #6089
// presets + Task #6094 recents). Custom hex colors typed into any border
// color field are remembered on-device (AsyncStorage, most recent first,
// capped) and rendered alongside these presets; preset duplicates are
// never re-added to the recents list.
const BORDER_COLOR_SWATCHES = [
  "#ffffff",
  "#0f172a",
  "#7d9bff",
  "#f59e0b",
  "#ef4444",
  "#10b981",
  "#ec4899",
  "#8b5cf6",
];
const RECENT_BORDER_COLORS_KEY = "biolink.editor.recentBorderColors";
const MAX_RECENT_BORDER_COLORS = 5;

// Normalizes a user-typed color to a lowercase #rgb/#rrggbb/#rrggbbaa hex
// string, or null when it isn't a plain hex color (keywords, gradients and
// partial input are not remembered as swatches).
function normalizeHexColor(raw: string): string | null {
  const v = raw.trim().toLowerCase();
  return /^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/.test(v) ? v : null;
}

// Mirrors the catalog-version constant on the PHP side. Bumped whenever a
// variant payload changes in a way clients should re-apply. Stored
// alongside the variant key on each block as `_variant_version`.
const VARIANT_VERSION = 2;

// Special-cased labels for tag keys that don't humanize cleanly (acronyms,
// numerals, etc). Anything not listed here is title-cased from the tag key
// itself, so adding a new tag to `lib/blockVariants.ts` automatically gets
// a sensible chip label without a second code change here. Mirrors the
// web editor's `$variantTags` overrides + auto-derived chip set.
const VARIANT_TAG_LABEL_OVERRIDES: Record<string, string> = {
  y2k: "Y2K",
  three_d: "3D",
};

function variantTagLabel(tag: string): string {
  if (VARIANT_TAG_LABEL_OVERRIDES[tag]) return VARIANT_TAG_LABEL_OVERRIDES[tag];
  return tag
    .split("_")
    .filter(Boolean)
    .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
    .join(" ");
}

// ---------------------------------------------------------------------------
// Photo stickers (image block) — drag-to-place positioning. Mirrors the web
// editor's sticker stage in `image-style-settings.blade.php`: 6 anchor
// presets + a clamped ±80px dx/dy fine offset from the nearest anchor.
// Entries round-trip through the server's `sanitizePhotoStickers`, so we
// always persist `{file_id, url, pos, size, rotate, dx, dy}` with `pos`
// limited to the server's PHOTO_STICKER_POSITIONS set and numeric fields
// clamped to the same bounds the sanitizer enforces (size 24–160, rotate
// ±180, dx/dy ±80, max 4 stickers).
type PhotoSticker = {
  file_id: number;
  url: string;
  pos: string;
  size: number;
  rotate: number;
  dx: number;
  dy: number;
};

const PHOTO_STICKER_POSITIONS = [
  "top_left",
  "top_right",
  "bottom_left",
  "bottom_right",
  "center_left",
  "center_right",
] as const;

const PHOTO_STICKER_POSITION_LABELS: Record<string, string> = {
  top_left: "Top left",
  top_right: "Top right",
  bottom_left: "Bottom left",
  bottom_right: "Bottom right",
  center_left: "Center left",
  center_right: "Center right",
};

function clampNum(v: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, v));
}

function normalizePhotoStickers(raw: unknown): PhotoSticker[] {
  if (!Array.isArray(raw)) return [];
  const out: PhotoSticker[] = [];
  for (const i of raw) {
    const o = (i && typeof i === "object" ? i : {}) as Record<string, unknown>;
    const fileId = Number(o.file_id);
    if (!Number.isFinite(fileId) || fileId <= 0) continue;
    const pos =
      typeof o.pos === "string" &&
      (PHOTO_STICKER_POSITIONS as readonly string[]).includes(o.pos)
        ? o.pos
        : "top_right";
    out.push({
      file_id: Math.round(fileId),
      url: typeof o.url === "string" ? o.url : "",
      pos,
      size: clampNum(Math.round(Number(o.size) || 48), 24, 160),
      rotate: clampNum(Math.round(Number(o.rotate) || 0), -180, 180),
      dx: clampNum(Math.round(Number(o.dx) || 0), -80, 80),
      dy: clampNum(Math.round(Number(o.dy) || 0), -80, 80),
    });
    if (out.length >= 4) break;
  }
  return out;
}

// Anchor base position (top-left px within the stage) for a sticker of
// size S on a W×H stage — identical math to the web editor's anchorBase().
function phAnchorBase(
  pos: string,
  S: number,
  W: number,
  H: number,
): { x: number; y: number } {
  switch (pos) {
    case "top_left":
      return { x: -10, y: -10 };
    case "bottom_left":
      return { x: -10, y: H - S + 10 };
    case "bottom_right":
      return { x: W - S + 10, y: H - S + 10 };
    case "center_left":
      return { x: -12, y: H / 2 - S / 2 };
    case "center_right":
      return { x: W - S + 12, y: H / 2 - S / 2 };
    default:
      // top_right
      return { x: W - S + 10, y: -10 };
  }
}

// Given a dragged top-left (left, top), pick the nearest anchor preset by
// squared distance and express the remainder as a clamped dx/dy — the same
// placement rule the web sticker stage applies on drag.
function phNearestPlacement(
  left: number,
  top: number,
  S: number,
  W: number,
  H: number,
): { pos: string; dx: number; dy: number } {
  let best: { pos: string; d: number; bx: number; by: number } | null = null;
  for (const pos of PHOTO_STICKER_POSITIONS) {
    const b = phAnchorBase(pos, S, W, H);
    const d = (left - b.x) * (left - b.x) + (top - b.y) * (top - b.y);
    if (!best || d < best.d) best = { pos, d, bx: b.x, by: b.y };
  }
  const b = best!;
  return {
    pos: b.pos,
    dx: clampNum(Math.round(left - b.bx), -80, 80),
    dy: clampNum(Math.round(top - b.by), -80, 80),
  };
}

// Absolutize the relative `/f/…` delivery URLs the sanitizer re-derives so
// <Image> can load them on device; absolute URLs pass through untouched.
function photoStickerImageUri(url: string): string {
  if (!url) return "";
  if (/^https?:\/\//i.test(url)) return url;
  return `${getBaseUrl()}${url.startsWith("/") ? "" : "/"}${url}`;
}

// One draggable sticker thumbnail on the stage. The PanResponder is
// created once and reads the latest sticker/stage via refs so a re-render
// mid-gesture (every move commits new dx/dy state) doesn't break the drag.
function DraggableSticker({
  sticker,
  stageW,
  stageH,
  accentColor,
  onPlace,
}: {
  sticker: PhotoSticker;
  stageW: number;
  stageH: number;
  accentColor: string;
  onPlace: (p: { pos: string; dx: number; dy: number }) => void;
}) {
  const stkRef = useRef(sticker);
  stkRef.current = sticker;
  const dimsRef = useRef({ W: stageW, H: stageH });
  dimsRef.current = { W: stageW, H: stageH };
  const onPlaceRef = useRef(onPlace);
  onPlaceRef.current = onPlace;
  const startRef = useRef({ x: 0, y: 0 });
  const [dragging, setDragging] = useState(false);

  const pan = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: () => true,
      onPanResponderGrant: () => {
        const { W, H } = dimsRef.current;
        const s = stkRef.current;
        const b = phAnchorBase(s.pos, s.size, W, H);
        startRef.current = { x: b.x + s.dx, y: b.y + s.dy };
        setDragging(true);
      },
      onPanResponderMove: (_evt, g) => {
        const { W, H } = dimsRef.current;
        const s = stkRef.current;
        onPlaceRef.current(
          phNearestPlacement(
            startRef.current.x + g.dx,
            startRef.current.y + g.dy,
            s.size,
            W,
            H,
          ),
        );
      },
      onPanResponderRelease: () => setDragging(false),
      onPanResponderTerminate: () => setDragging(false),
    }),
  ).current;

  const base = phAnchorBase(sticker.pos, sticker.size, stageW, stageH);
  return (
    <View
      {...pan.panHandlers}
      style={{
        position: "absolute",
        left: base.x + sticker.dx,
        top: base.y + sticker.dy,
        width: sticker.size,
        height: sticker.size,
        zIndex: 20,
        transform: [{ rotate: `${sticker.rotate}deg` }],
        borderWidth: dragging ? 2 : 0,
        borderColor: accentColor,
        borderRadius: 6,
      }}
      accessibilityLabel="Drag to reposition sticker"
    >
      <Image
        source={{ uri: photoStickerImageUri(sticker.url) }}
        style={{ width: "100%", height: "100%" }}
        resizeMode="contain"
      />
    </View>
  );
}

// Inline preview for a pricing item's Thumbnail URL. Renders nothing
// while the URL is empty or doesn't look like an http(s) URL, and hides
// itself if the image fails to load — so a typo'd or 404'ing URL just
// disappears instead of showing a broken-image icon.
function PricingThumbnailPreview({
  uri,
  borderColor,
  mutedColor,
}: {
  uri: string;
  borderColor: string;
  mutedColor: string;
}) {
  const [errored, setErrored] = useState(false);
  useEffect(() => {
    setErrored(false);
  }, [uri]);
  const trimmed = uri.trim();
  const looksLikeUrl = /^https?:\/\//i.test(trimmed);
  if (!trimmed || !looksLikeUrl || errored) return null;
  return (
    <View
      style={{
        width: 56,
        height: 56,
        borderRadius: 8,
        borderWidth: 1,
        borderColor,
        overflow: "hidden",
        backgroundColor: mutedColor,
      }}
    >
      <Image
        source={{ uri: trimmed }}
        style={{ width: "100%", height: "100%" }}
        resizeMode="cover"
        onError={() => setErrored(true)}
        accessibilityLabel="Thumbnail preview"
      />
    </View>
  );
}

// The block-settings editor, extracted so it can render both as the
// full-screen route (default export below) and inline, expanded in place
// beneath a block's row in the blocks list — mirroring the web editor's
// inline/expand pattern. In inline mode the screen chrome (Stack header,
// own ScrollView) is skipped and a successful save calls `onDone`
// instead of popping the navigation stack.
export function BlockSettingsEditor({
  linkId,
  blockId,
  inline = false,
  onDone,
}: {
  linkId: number;
  blockId: number;
  inline?: boolean;
  onDone?: () => void;
}) {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const id = linkId;

  const q = useQuery({
    queryKey: ["blocks", id],
    queryFn: () => listBlocks(id),
    enabled: Number.isFinite(id),
  });
  const block: Block | undefined = useMemo(
    () => q.data?.find((b) => b.id === blockId),
    [q.data, blockId],
  );

  const [active, setActive] = useState(true);
  const [values, setValues] = useState<Record<string, string>>({});
  // Per-block targeting (mirrors the web editor's Display Settings card —
  // `settings._visibility.{countries,countries_exclude,devices,devices_exclude}`).
  // Country lists stay as comma-separated strings to match the web's CSV
  // input UX; device lists are sets for cheap toggling.
  const [visDevices, setVisDevices] = useState<Set<string>>(new Set());
  const [visDevicesExclude, setVisDevicesExclude] = useState<Set<string>>(new Set());
  const [visCountries, setVisCountries] = useState<string>("");
  const [visCountriesExclude, setVisCountriesExclude] = useState<string>("");
  // Per-block trackable-link settings live under `block.settings._link`. We
  // hold them in a dedicated state bucket because `values` is string-only
  // and the auto-UTM payload includes nested overrides + an enum toggle.
  const [linkUrl, setLinkUrl] = useState<string>("");
  type AutoUtmEnabled = "inherit" | "on" | "off";
  const UTM_KEYS = ["utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content"] as const;
  const [autoUtmEnabled, setAutoUtmEnabled] = useState<AutoUtmEnabled>("inherit");
  const [autoUtmOverrides, setAutoUtmOverrides] = useState<Record<string, string>>({});

  // "Pick from my links" + "Fetch details" — mobile parity for the web
  // link-block editor's picker/OG-fetch shortcuts. Only rendered for the
  // link-button family (link / link_big / featured_pin).
  const isLinkPickerType = ["link", "link_big", "featured_pin"].includes(
    block?.type ?? "",
  );
  const [pickerOpen, setPickerOpen] = useState(false);
  const [pickerQ, setPickerQ] = useState("");
  const pickerQuery = useQuery({
    queryKey: ["blockLinkPicker", pickerQ],
    queryFn: () => listLinks({ q: pickerQ || undefined, per_page: 25 }),
    enabled: pickerOpen,
  });
  const [ogFetching, setOgFetching] = useState(false);
  const [ogError, setOgError] = useState("");
  const [ogSuccess, setOgSuccess] = useState(false);
  // Fetched-but-not-yet-applied OG meta. Instead of silently filling the
  // form, the fetch stages the result here and a preview card asks the
  // creator to confirm ("Apply") or discard ("Dismiss") — matching the
  // web editor's confirm-before-apply preview card.
  const [ogPreview, setOgPreview] = useState<OgMeta | null>(null);

  // Fetch stages the meta into `ogPreview` for the preview card; nothing
  // is written into the form until the creator taps Apply.
  const runOgFetch = useCallback(async (overrideUrl?: string) => {
    const url = (overrideUrl ?? linkUrl).trim();
    if (!url) {
      setOgError("Please enter a URL first.");
      setOgSuccess(false);
      return;
    }
    setOgFetching(true);
    setOgError("");
    setOgSuccess(false);
    setOgPreview(null);
    try {
      const m = await fetchOgMeta(url);
      if (!m.title && !m.description && !m.image_url && !m.favicon_url) {
        setOgError("No details found for that page.");
        return;
      }
      setOgPreview(m);
    } catch (e) {
      setOgError(
        (e as { message?: string })?.message || "Could not fetch page details.",
      );
    } finally {
      setOgFetching(false);
    }
  }, [linkUrl]);

  // Mirrors the web editor: title/description only fill EMPTY fields,
  // thumbnail falls back to the favicon. The title lands in whichever
  // title-ish key this block kind actually uses ("text" for the featured
  // variants, "label" for the plain link button).
  const applyOgPreview = useCallback(() => {
    const m = ogPreview;
    if (!m) return;
    setValues((p) => {
      const next = { ...p };
      const titleKey = ["text", "label", "title"].find(
        (k) => (p[k] ?? "").trim() !== "",
      )
        ? null
        : block?.type === "link"
          ? "label"
          : "text";
      if (m.title && titleKey) next[titleKey] = m.title;
      if (m.description && !(p.description ?? "").trim())
        next.description = m.description;
      const img = m.image_url || m.favicon_url;
      if (img && block?.type !== "featured_pin" && !(p.thumbnail ?? "").trim())
        next.thumbnail = img;
      return next;
    });
    setOgPreview(null);
    setOgSuccess(true);
  }, [ogPreview, block?.type]);

  // Pull the parent biolink so the resolved-URL preview can read the
  // biolink-wide auto_utm defaults and slug. Cached by the same key the
  // settings screens use.
  const linkQ = useQuery({
    queryKey: ["link", id],
    queryFn: () => getLink(id),
    enabled: Number.isFinite(id),
  });

  // Design-locked pages follow their template's styling: the per-block
  // Designs gallery is hidden (content editing stays fully available).
  // Mirrors the web editor, which hides the Block Styling section.
  const designLocked = !!linkQ.data?.design_locked;

  // Task #1094 — per-block scarcity. `maxClicks` 0/empty = unlimited.
  // `endDate` is a `YYYY-MM-DDTHH:mm` string (locale-naive, just like
  // the web editor's <input type="datetime-local">). The presentation
  // toggles, near-percent slider and expired-action settings live
  // under `settings._limits` to mirror the Blade form's name shape.
  const [maxClicks, setMaxClicks] = useState<string>("");
  const [endDate, setEndDate] = useState<string>("");
  const [showCountdown, setShowCountdown] = useState<boolean>(false);
  const [showRemaining, setShowRemaining] = useState<boolean>(false);
  const [nearPercent, setNearPercent] = useState<number>(20);
  const [expiredAction, setExpiredAction] = useState<"hide" | "show">("hide");
  const [expiredLabel, setExpiredLabel] = useState<string>("Sold out");
  const [expiredEmoji, setExpiredEmoji] = useState<string>("");
  const [previewState, setPreviewState] = useState<"active" | "near" | "expired">("active");
  // List/pricing block state. These block types persist a `style` string,
  // an `items` array, and (for `list`) a default bullet `icon`. They are
  // edited via a bespoke UI rather than the generic field renderer.
  const [listStyle, setListStyle] = useState<string>("");
  const [defaultBulletIcon, setDefaultBulletIcon] = useState<string>("fa-check");
  const [listItems, setListItems] = useState<ListItem[]>([]);
  const [pricingItems, setPricingItems] = useState<PricingItem[]>([]);
  const isList = block?.type === "list";
  const isListNumbered = block?.type === "list_numbered";
  const isPricing = block?.type === "list_pricing";
  const isAnyList = isList || isListNumbered || isPricing;
  // Profile-card identity blocks (profile_card_v1..v4). Their text content
  // (name/title/bio/avatar/cover/location/website/cta_*) lives in the
  // generic string `values` map, but `verified` (boolean) and `socials`
  // (array) need their own buckets — edited via a bespoke section below.
  const isProfileCard = canonicalBlockType(block?.type ?? "") === "profile_card";
  // Map location block. Address/lat/lng/label/zoom already round-trip
  // through the generic string `values` map (no bespoke bucket needed for
  // those), but `show_directions` is boolean and the "pick on map"
  // affordance needs its own modal-open flag — those get dedicated state,
  // mirroring the web `mapPinPicker` editor.
  const isMapLocation = block?.type === "map_location";
  // Image block — photo stickers (drag-to-place). The sticker array lives
  // in `_style._photo_stickers`; the drag stage needs the measured stage
  // width plus the photo's aspect ratio to mirror the web stage's math.
  const isImageBlock = block?.type === "image";
  // Gallery/grid image blocks (Task #6016) — their `images` array
  // ([{url, alt}]) is edited via a bespoke repeater below, with rows
  // fillable from the curated stock gallery.
  const isGalleryBlock = ["image_grid", "image_slider", "image_slider_v2"].includes(
    block?.type ?? "",
  );
  const [galleryImages, setGalleryImages] = useState<
    { url: string; alt: string }[]
  >([]);
  // Importing a curated stock image into the vault before appending it
  // as a sticker (stickers must reference an owned vault file).
  const [stockStickerBusy, setStockStickerBusy] = useState(false);
  const [photoStickers, setPhotoStickers] = useState<PhotoSticker[]>([]);
  const [stickerStageW, setStickerStageW] = useState(0);
  const [stickerStageRatio, setStickerStageRatio] = useState(4 / 3);
  // Add-sticker flow (Task #5956): upload from device via expo-image-picker
  // or pick an existing image from the Sayzio Files vault. New entries get
  // the server defaults (pos top_right, size 48) up to the 4-sticker cap.
  const [stickerUploading, setStickerUploading] = useState(false);
  const [stickerPickerOpen, setStickerPickerOpen] = useState(false);
  const [stickerVaultFiles, setStickerVaultFiles] = useState<VaultFile[] | null>(
    null,
  );
  const [stickerVaultLoading, setStickerVaultLoading] = useState(false);
  // Vault picker pagination + name search (Task #5967). `page`/`lastPage`
  // mirror the API's pagination envelope so "Load more" knows when to
  // stop; `query` is debounced before refetching page 1.
  const [stickerVaultPage, setStickerVaultPage] = useState(1);
  const [stickerVaultLastPage, setStickerVaultLastPage] = useState(1);
  const [stickerVaultLoadingMore, setStickerVaultLoadingMore] = useState(false);
  const [stickerVaultQuery, setStickerVaultQuery] = useState("");

  // Fetch a page of image vault files. `append` keeps earlier pages in the
  // grid (load-more); a fresh search/open replaces the list. The API also
  // filters by type, but the client re-filters defensively (matches #5956).
  // Monotonic request id — stale-response guard so an older in-flight
  // fetch that resolves late can't overwrite a newer query's results.
  const stickerVaultReq = useRef(0);
  const fetchStickerVaultPage = useCallback(
    async (page: number, q: string, append: boolean) => {
      const reqId = ++stickerVaultReq.current;
      if (append) setStickerVaultLoadingMore(true);
      else setStickerVaultLoading(true);
      try {
        const res = await listVaultFiles({
          type: "image",
          perPage: 60,
          page,
          q: q.trim() || undefined,
        });
        if (reqId !== stickerVaultReq.current) return;
        const images = res.files.filter((f) => f.type === "image");
        setStickerVaultFiles((prev) =>
          append && prev ? [...prev, ...images] : images,
        );
        setStickerVaultPage(res.pagination.current_page);
        setStickerVaultLastPage(res.pagination.last_page);
      } catch {
        if (reqId !== stickerVaultReq.current) return;
        if (!append) {
          setStickerVaultFiles([]);
          setStickerVaultPage(1);
          setStickerVaultLastPage(1);
        }
      } finally {
        if (reqId === stickerVaultReq.current) {
          if (append) setStickerVaultLoadingMore(false);
          else setStickerVaultLoading(false);
        }
      }
    },
    [],
  );

  // Debounced name search — refetch page 1 whenever the query settles
  // while the picker is open. Skips the initial "" run on open because
  // openStickerVaultPicker already fetched page 1.
  const stickerVaultQueryRan = useRef(false);
  useEffect(() => {
    if (!stickerPickerOpen) {
      stickerVaultQueryRan.current = false;
      return;
    }
    if (!stickerVaultQueryRan.current) {
      stickerVaultQueryRan.current = true;
      if (stickerVaultQuery === "") return;
    }
    const t = setTimeout(() => {
      void fetchStickerVaultPage(1, stickerVaultQuery, false);
    }, 350);
    return () => clearTimeout(t);
  }, [stickerVaultQuery, stickerPickerOpen, fetchStickerVaultPage]);

  // Append a vault file as a new sticker with the server defaults
  // (pos top_right, size 48); duplicates are allowed (matches web), the
  // 4-sticker cap is enforced here and re-checked server-side.
  const appendSticker = useCallback((file: VaultFile) => {
    if (file.type !== "image") {
      showAlert(
        "Images only",
        "Stickers must be image files (PNG, WebP or SVG with transparency work best).",
      );
      return;
    }
    setPhotoStickers((prev) => {
      if (prev.length >= 4) return prev;
      return [
        ...prev,
        {
          file_id: file.id,
          url: file.url_path || file.url,
          pos: "top_right",
          size: 48,
          rotate: 0,
          dx: 0,
          dy: 0,
        },
      ];
    });
    setStickerPickerOpen(false);
  }, []);

  const addStickerFromDevice = useCallback(async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      showAlert(
        "Photos access needed",
        "Allow access to your photo library in Settings to pick an image.",
      );
      return;
    }
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.9,
    });
    if (res.canceled || !res.assets?.[0]) return;
    const asset = res.assets[0];
    setStickerUploading(true);
    try {
      const file = await uploadVaultFile({
        uri: asset.uri,
        name: asset.fileName ?? undefined,
        mime: asset.mimeType ?? undefined,
      });
      appendSticker(file);
    } catch (e) {
      // Storage-quota (and other plan-gated) rejections get the upgrade
      // prompt with the recommended-plan hint instead of a raw error.
      if (handlePlanLockedError(e, "Your storage is full on your current plan.")) {
        return;
      }
      const msg =
        e && typeof e === "object" && "message" in e
          ? String((e as { message: unknown }).message)
          : "Upload failed.";
      showAlert("Upload failed", msg);
    } finally {
      setStickerUploading(false);
    }
  }, [appendSticker]);

  // Curated stock sticker (Task #6016): stickers must reference an owned
  // vault file (the server sanitizer fails closed on foreign URLs), so a
  // stock pick first imports the asset into the vault, then appends it.
  // Task #6028: import happens SERVER-side by asset key (the asset CDN
  // has no CORS headers, so the web build can't fetch the blob itself).
  const addStickerFromStock = useCallback(
    async (assetKey: string) => {
      if (stockStickerBusy) return;
      setStockStickerBusy(true);
      try {
        const file = await importPlatformAsset({ key: assetKey });
        appendSticker(file);
      } catch (e) {
        if (handlePlanLockedError(e, "Your storage is full on your current plan.")) {
          return;
        }
        const msg =
          e && typeof e === "object" && "message" in e
            ? String((e as { message: unknown }).message)
            : "Could not add that sticker.";
        showAlert("Could not add sticker", msg);
      } finally {
        setStockStickerBusy(false);
      }
    },
    [appendSticker, stockStickerBusy],
  );

  const openStickerVaultPicker = useCallback(async () => {
    setStickerPickerOpen((open) => !open);
    if (stickerVaultFiles !== null) return;
    await fetchStickerVaultPage(1, stickerVaultQuery, false);
  }, [stickerVaultFiles, stickerVaultQuery, fetchStickerVaultPage]);

  const loadMoreStickerVault = useCallback(() => {
    if (stickerVaultLoadingMore || stickerVaultLoading) return;
    if (stickerVaultPage >= stickerVaultLastPage) return;
    void fetchStickerVaultPage(stickerVaultPage + 1, stickerVaultQuery, true);
  }, [
    stickerVaultLoadingMore,
    stickerVaultLoading,
    stickerVaultPage,
    stickerVaultLastPage,
    stickerVaultQuery,
    fetchStickerVaultPage,
  ]);

  const [mapPickerOpen, setMapPickerOpen] = useState(false);
  const [mapShowDirections, setMapShowDirections] = useState(true);
  const [profileVerified, setProfileVerified] = useState<boolean>(false);
  const [profileSocials, setProfileSocials] = useState<ProfileSocial[]>([]);
  // Decorative avatar frame (Task #5910) — mirrors _style._avatar_frame
  // (+ optional _avatar_frame_color tint). "" = none / auto accent.
  const [avatarFrame, setAvatarFrame] = useState<string>("");
  // Block background preset (Task #5970): `_style.bg_preset_key` +
  // `_style.bg_preset_opacity` (0–100). Available on every block type.
  const [bgPresetKey, setBgPresetKey] = useState<string>("");
  const [bgPresetOpacity, setBgPresetOpacity] = useState<number>(100);
  const [bgPresetOpen, setBgPresetOpen] = useState(false);
  const [bgPresetGroup, setBgPresetGroup] = useState<string>("all");
  // Unified block background (Task #6044): None / Color / Gradient /
  // Preset / Image, mirroring the web editor's Style-tab picker. Color
  // and Gradient both persist into `_style.bg_color` (gradient as a CSS
  // gradient string); Image into `_style.bg_image` (http(s) or /f/ vault
  // path); Preset keeps the existing `_style.bg_preset_key` fields.
  const [bgMode, setBgMode] = useState<"none" | "color" | "gradient" | "preset" | "image">("none");
  const [bgColorVal, setBgColorVal] = useState<string>("");
  const [gradType, setGradType] = useState<"linear" | "radial" | "conic">("linear");
  const [gradAngle, setGradAngle] = useState<number>(135);
  const [gradStops, setGradStops] = useState<string[]>(["#7c3aed", "#22d3ee"]);
  const [bgImageVal, setBgImageVal] = useState<string>("");
  const [bgImgUploading, setBgImgUploading] = useState(false);
  // Per-device block width (Task #6119 web parity): base `_style.grid_span`
  // (mobile, 12-column grid) plus the optional desktop-only override
  // `_style.grid_span_md` ("" = same as mobile, cleared on save).
  const [widthDevice, setWidthDevice] = useState<"mobile" | "desktop">("mobile");
  const [gridSpan, setGridSpan] = useState<string>("12");
  const [gridSpanMd, setGridSpanMd] = useState<string>("");
  // Per-device block height / row span (Task #6123 web parity): base
  // `_style.grid_row_span` ("" = auto height) plus the desktop-only
  // override `_style.grid_row_span_md` ("" = same as mobile).
  const [heightDevice, setHeightDevice] = useState<"mobile" | "desktop">("mobile");
  const [gridRowSpan, setGridRowSpan] = useState<string>("");
  const [gridRowSpanMd, setGridRowSpanMd] = useState<string>("");
  // Borders (Task #6038 web parity): shorthand style/width/color/radius
  // plus advanced per-corner radii and per-side style/width/color behind
  // an expander. Blank advanced fields fall back to the shorthand
  // field-by-field at render time (server + renderers own the semantics).
  const [bdStyle, setBdStyle] = useState<string>("none");
  const [bdWidth, setBdWidth] = useState<string>("");
  const [bdColor, setBdColor] = useState<string>("");
  const [bdRadius, setBdRadius] = useState<string>("");
  const [bdAdvOpen, setBdAdvOpen] = useState(false);
  const [bdCorners, setBdCorners] = useState<Record<"tl" | "tr" | "bl" | "br", string>>({
    tl: "",
    tr: "",
    bl: "",
    br: "",
  });
  const [bdSides, setBdSides] = useState<
    Record<"top" | "right" | "bottom" | "left", { style: string; width: string; color: string }>
  >({
    top: { style: "", width: "", color: "" },
    right: { style: "", width: "", color: "" },
    bottom: { style: "", width: "", color: "" },
    left: { style: "", width: "", color: "" },
  });
  // Recently used custom border colors (Task #6094): hydrated once from
  // AsyncStorage, appended to the preset swatch row, updated whenever a
  // valid custom hex is committed (blur) in any border color field.
  const [recentBorderColors, setRecentBorderColors] = useState<string[]>([]);
  useEffect(() => {
    AsyncStorage.getItem(RECENT_BORDER_COLORS_KEY)
      .then((raw) => {
        if (!raw) return;
        try {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) {
            setRecentBorderColors(
              parsed
                .filter((c): c is string => typeof c === "string" && normalizeHexColor(c) !== null)
                .filter((c) => !BORDER_COLOR_SWATCHES.includes(c.toLowerCase()))
                .slice(0, MAX_RECENT_BORDER_COLORS),
            );
          }
        } catch {}
      })
      .catch(() => {});
  }, []);
  const rememberBorderColor = useCallback((raw: string) => {
    const hex = normalizeHexColor(raw);
    if (!hex || BORDER_COLOR_SWATCHES.includes(hex)) return;
    setRecentBorderColors((prev) => {
      const next = [hex, ...prev.filter((c) => c !== hex)].slice(0, MAX_RECENT_BORDER_COLORS);
      if (next.length === prev.length && next.every((c, i) => c === prev[i])) return prev;
      AsyncStorage.setItem(RECENT_BORDER_COLORS_KEY, JSON.stringify(next)).catch(() => {});
      return next;
    });
  }, []);
  // Long-press a RECENT swatch to remove it (Task #6103); presets are fixed.
  const removeRecentBorderColor = useCallback((raw: string) => {
    const hex = raw.trim().toLowerCase();
    setRecentBorderColors((prev) => {
      const next = prev.filter((c) => c !== hex);
      if (next.length === prev.length) return prev;
      AsyncStorage.setItem(RECENT_BORDER_COLORS_KEY, JSON.stringify(next)).catch(() => {});
      return next;
    });
  }, []);
  const confirmRemoveRecentBorderColor = useCallback(
    (hex: string) => {
      if (Platform.OS === "web") {
        if (typeof window !== "undefined" && window.confirm(`Remove ${hex} from your recent border colors?`)) {
          removeRecentBorderColor(hex);
        }
        return;
      }
      showAlert(`Remove recent color`, `Remove ${hex} from your recent border colors?`, [
        { text: "Cancel", style: "cancel" },
        { text: "Remove", style: "destructive", onPress: () => removeRecentBorderColor(hex) },
      ]);
    },
    [removeRecentBorderColor],
  );
  // Instant borders live preview (Task #6074): true when any border field
  // is set, so the preview only appears once borders are in play.
  const borderFieldsDirty = useMemo(
    () =>
      bdStyle !== "none" ||
      [bdWidth, bdColor, bdRadius, bdCorners.tl, bdCorners.tr, bdCorners.bl, bdCorners.br].some(
        (v) => v.trim() !== "",
      ) ||
      (["top", "right", "bottom", "left"] as const).some(
        (s) =>
          bdSides[s].style.trim() !== "" ||
          bdSides[s].width.trim() !== "" ||
          bdSides[s].color.trim() !== "",
      ),
    [bdStyle, bdWidth, bdColor, bdRadius, bdCorners, bdSides],
  );
  // In-progress `_style` for the borders live preview: the saved _style
  // with the editing border fields patched in — the EXACT merge the save
  // path performs (non-empty set, blank deleted), so the preview and the
  // eventual save can never disagree. Rendering is pure state, so edits
  // show instantly with no re-fetch/flash.
  const borderPreviewStyle = useMemo(() => {
    const base =
      (block?.settings?._style as Record<string, unknown> | undefined) ?? {};
    const out: Record<string, unknown> = { ...base };
    const put = (key: string, val: string) => {
      if (val.trim() !== "") out[key] = val.trim();
      else delete out[key];
    };
    put("border_style", bdStyle === "none" ? "" : bdStyle);
    put("border_width", bdWidth);
    put("border_color", bdColor);
    put("border_radius", bdRadius);
    put("border_radius_tl", bdCorners.tl);
    put("border_radius_tr", bdCorners.tr);
    put("border_radius_bl", bdCorners.bl);
    put("border_radius_br", bdCorners.br);
    (["top", "right", "bottom", "left"] as const).forEach((side) => {
      put(`border_${side}_style`, bdSides[side].style);
      put(`border_${side}_width`, bdSides[side].width);
      put(`border_${side}_color`, bdSides[side].color);
    });
    return out;
  }, [block, bdStyle, bdWidth, bdColor, bdRadius, bdCorners, bdSides]);
  // Task #5987 — when a preset swatch is tapped in the grid, the live
  // preview (which sits above the grid) may be scrolled off-screen.
  // These refs let us bring it back into view: on web via the DOM's
  // scrollIntoView; on native screen-mode via measureLayout against the
  // editor ScrollView. Inline mode on native is best-effort (no parent
  // scroll handle), which is fine — web is the primary editor surface.
  const bgPresetPreviewRef = useRef<View | null>(null);
  const editorScrollRef = useRef<ScrollView | null>(null);
  const scrollBgPresetPreviewIntoView = useCallback(() => {
    // Defer a frame so the preview has (re)rendered with the new preset
    // before we measure/scroll to it.
    requestAnimationFrame(() => {
      const node = bgPresetPreviewRef.current as
        | (View & { scrollIntoView?: (opts?: unknown) => void })
        | null;
      if (!node) return;
      if (typeof node.scrollIntoView === "function") {
        // react-native-web: the ref is a DOM element.
        node.scrollIntoView({ behavior: "smooth", block: "nearest" });
        return;
      }
      const scroller = editorScrollRef.current;
      if (!scroller) return;
      try {
        node.measureLayout(
          scroller.getInnerViewNode(),
          (_x: number, y: number) => {
            scroller.scrollTo({ y: Math.max(0, y - 12), animated: true });
          },
          () => {},
        );
      } catch {
        // Best-effort — never let a measurement failure break selection.
      }
    });
  }, []);
  // Block background preset catalog — only fetched once the picker is
  // opened (or a preset is already applied, so its swatch can render).
  // Query key/staleTime match the Appearance pickers' so caches share.
  const bgPresetCatalogQ = useQuery({
    queryKey: ["bg-presets"],
    queryFn: getBgPresets,
    staleTime: 60 * 60 * 1000,
    enabled: bgPresetOpen || bgPresetKey !== "",
  });
  // Admin-managed Designs catalog additions (Task #6045): merge remote
  // customs/hidden into the hardcoded variant mirror before the gallery
  // renders. Shares the blocks screen's cache key.
  const blockCatalogQ = useQuery({
    queryKey: ["block-catalog"],
    queryFn: getBlockCatalog,
    staleTime: 5 * 60 * 1000,
  });
  const designCatalog = blockCatalogQ.data?.design_catalog;
  applyRemoteDesignCatalog(designCatalog ?? null);
  const [avatarFrameColor, setAvatarFrameColor] = useState<string>("");
  // Stats (`[{label,value}]`, "stats" layout) and badges (`[{label}]`,
  // "badges" layout) repeaters. Edited via bespoke sections below, gated
  // by the block's resolved profile layout so they only show where the
  // public renderer actually paints them.
  const [profileStats, setProfileStats] = useState<ProfileStat[]>([]);
  const [profileBadges, setProfileBadges] = useState<ProfileBadge[]>([]);
  const profileCardLayout = useMemo(() => resolveProfileLayout(block), [block]);
  // Selected design variant for this block. Mirrors `_style._variant` from
  // the web editor — empty string means "no variant chosen" (treated as
  // Custom in the gallery).
  const [variantKey, setVariantKey] = useState<string>("");
  // Designs gallery state (mirrors the web editor's parity feature set).
  const [activeFilter, setActiveFilter] = useState<string>("all");
  const [favorites, setFavorites] = useState<string[]>([]);
  // Visual icon picker target. `kind` says which icon slot we're editing
  // and (for items) `index` is the row. Closing the modal resets to null.
  // Mirrors the web editor's `icon-picker.blade.php` modal — always
  // reachable via "Browse icons" so creators don't need to know FA classes.
  const [iconPickerTarget, setIconPickerTarget] = useState<
    | { kind: "default" }
    | { kind: "list"; index: number }
    | { kind: "pricing"; index: number }
    | null
  >(null);
  const favoritesKey = block ? `biolink:variantFavorites:${block.type}` : "";

  useEffect(() => {
    if (!block) return;
    setActive(block.is_active);
    const init: Record<string, string> = {};
    Object.entries(block.settings ?? {}).forEach(([k, v]) => {
      if (typeof v === "string") init[k] = v;
      else if (v != null && typeof v !== "object") init[k] = String(v);
    });
    setValues(init);
    // Hydrate per-block trackable-link state. Supports both the new
    // structured `_link.auto_utm` shape and the legacy flat `_link.utm_*`
    // overrides — flat values are surfaced as overrides so editing in
    // the new UI carries them forward seamlessly.
    const lk = (block.settings?._link as Record<string, unknown> | undefined) ?? {};
    // Prefer the trackable `_link.url`; fall back to the legacy generic
    // `settings.url` so existing link/image/video blocks load with their
    // current destination already in the new editor.
    const legacyUrl =
      typeof block.settings?.url === "string" ? (block.settings.url as string) : "";
    setLinkUrl(typeof lk.url === "string" ? lk.url : legacyUrl);
    const au = (lk.auto_utm as Record<string, unknown> | undefined) ?? {};
    const en = au.enabled;
    setAutoUtmEnabled(en === "on" || en === "off" ? en : "inherit");
    const ov: Record<string, string> = {};
    const auOv = (au.overrides as Record<string, unknown> | undefined) ?? {};
    UTM_KEYS.forEach((k) => {
      if (typeof auOv[k] === "string") ov[k] = auOv[k] as string;
      else if (typeof lk[k] === "string") ov[k] = lk[k] as string;
      else ov[k] = "";
    });
    setAutoUtmOverrides(ov);

    // Hydrate scarcity controls. We feed the <TextField> for max_clicks
    // a string ("" when unlimited) so empty input round-trips cleanly.
    setMaxClicks(
      typeof block.max_clicks === "number" && block.max_clicks > 0
        ? String(block.max_clicks)
        : "",
    );
    setEndDate(
      typeof block.end_date === "string" && block.end_date
        ? // Trim seconds + tz for the YYYY-MM-DDTHH:mm shape the web
          // editor uses; the backend accepts either form via Carbon.
          block.end_date.slice(0, 16)
        : "",
    );
    const lim = (block.settings?._limits as Record<string, unknown> | undefined) ?? {};
    setShowCountdown(!!lim.show_countdown);
    setShowRemaining(!!lim.show_remaining);
    const npRaw = Number(lim.near_threshold_percent);
    setNearPercent(Number.isFinite(npRaw) ? Math.max(0, Math.min(100, npRaw)) : 20);
    setExpiredAction(lim.expired_action === "show" ? "show" : "hide");
    setExpiredLabel(typeof lim.expired_label === "string" ? lim.expired_label : "Sold out");
    setExpiredEmoji(typeof lim.expired_emoji === "string" ? lim.expired_emoji : "");
    const style = (block.settings?._style as Record<string, unknown> | undefined) ?? {};
    setVariantKey(typeof style._variant === "string" ? style._variant : "");
    // Hydrate visibility/targeting from `_visibility` (everything missing
    // means "show everywhere"). Country codes are upper-cased on the way in
    // so the chip below the input never disagrees with what the saved CSV
    // actually contains.
    const vis = (block.settings?._visibility as Record<string, unknown> | undefined) ?? {};
    const toCsv = (v: unknown): string =>
      Array.isArray(v) ? v.map((x) => String(x).trim().toUpperCase()).filter(Boolean).join(", ") : "";
    const toDeviceSet = (v: unknown): Set<string> => {
      const allowed = new Set(["mobile", "tablet", "desktop"]);
      const out = new Set<string>();
      if (Array.isArray(v)) v.forEach((x) => { const s = String(x); if (allowed.has(s)) out.add(s); });
      return out;
    };
    setVisDevices(toDeviceSet(vis.devices));
    setVisDevicesExclude(toDeviceSet(vis.devices_exclude));
    setVisCountries(toCsv(vis.countries));
    setVisCountriesExclude(toCsv(vis.countries_exclude));
    // Hydrate list/pricing-specific state from the saved settings. We
    // keep these in their own state buckets so the generic `values`
    // map (string-only) doesn't trip over the array/boolean fields.
    if (block.type === "list" || block.type === "list_numbered") {
      const savedStyle = block.settings?.style;
      setListStyle(typeof savedStyle === "string" && savedStyle ? savedStyle : "clean");
      setListItems(normalizeListItems(block.settings?.items));
      const di = block.settings?.icon;
      setDefaultBulletIcon(typeof di === "string" && di ? di : "fa-check");
    } else if (block.type === "list_pricing") {
      const savedStyle = block.settings?.style;
      setListStyle(typeof savedStyle === "string" && savedStyle ? savedStyle : "classic");
      setPricingItems(normalizePricingItems(block.settings?.items));
    }
    // Hydrate profile-card-specific buckets (verified flag + socials list).
    // The string fields ride along in the generic `values` map above.
    if (canonicalBlockType(block.type) === "profile_card") {
      const v = block.settings?.verified;
      setProfileVerified(v === true || v === 1 || v === "1" || v === "true");
      setProfileSocials(normalizeProfileSocials(block.settings?.socials));
      setProfileStats(normalizeProfileStats(block.settings?.stats));
      setProfileBadges(normalizeProfileBadges(block.settings?.badges));
      const st = (block.settings?._style as Record<string, unknown> | undefined) ?? {};
      setAvatarFrame(isAvatarFrameKey(st._avatar_frame) ? st._avatar_frame : "");
      setAvatarFrameColor(
        typeof st._avatar_frame_color === "string" ? st._avatar_frame_color : "",
      );
    }
    // Hydrate photo stickers for image blocks from `_style._photo_stickers`
    // (added on web via upload/vault; repositionable here by drag).
    if (block.type === "image") {
      const st = (block.settings?._style as Record<string, unknown> | undefined) ?? {};
      setPhotoStickers(normalizePhotoStickers(st._photo_stickers));
    }
    // Hydrate the gallery/grid images repeater ([{url, alt}]). Entries
    // may be plain strings on very old blocks — normalize both shapes.
    if (["image_grid", "image_slider", "image_slider_v2"].includes(block.type)) {
      const raw = block.settings?.images;
      const rows = Array.isArray(raw)
        ? raw.map((i) => {
            if (typeof i === "string") return { url: i, alt: "" };
            const o = (i && typeof i === "object" ? i : {}) as Record<string, unknown>;
            return {
              url: typeof o.url === "string" ? o.url : "",
              alt: typeof o.alt === "string" ? o.alt : "",
            };
          })
        : [];
      setGalleryImages(rows);
    }
    // Hydrate the map-location boolean toggle. Mirrors the web default
    // (`$s['show_directions'] ?? true`) so blocks saved before this field
    // existed still show the "Directions" button by default.
    if (block.type === "map_location") {
      const sd = block.settings?.show_directions;
      setMapShowDirections(!(sd === false || sd === 0 || sd === "0" || sd === "false"));
    }
    // Hydrate the block background preset (any block type).
    {
      const st = (block.settings?._style as Record<string, unknown> | undefined) ?? {};
      setBgPresetKey(typeof st.bg_preset_key === "string" ? st.bg_preset_key : "");
      const rawOp = Number(st.bg_preset_opacity);
      setBgPresetOpacity(
        Number.isFinite(rawOp) ? Math.max(0, Math.min(100, Math.round(rawOp))) : 100,
      );
      // Unified background mode hydrate (Task #6044): mode is derived
      // from whichever field is populated — preset wins, then image,
      // then gradient-vs-color (both live in bg_color).
      const bgc = typeof st.bg_color === "string" ? st.bg_color.trim() : "";
      const bgi = typeof st.bg_image === "string" ? st.bg_image.trim() : "";
      const gradMatch = /^(linear|radial|conic)-gradient\(/i.exec(bgc);
      if (typeof st.bg_preset_key === "string" && st.bg_preset_key !== "") {
        setBgMode("preset");
      } else if (bgi !== "") {
        setBgMode("image");
      } else if (gradMatch) {
        setBgMode("gradient");
      } else if (bgc !== "" && bgc !== "transparent") {
        setBgMode("color");
      } else {
        setBgMode("none");
      }
      setBgImageVal(bgi);
      if (gradMatch) {
        setGradType(gradMatch[1].toLowerCase() as "linear" | "radial" | "conic");
        const ang = /(?:\(|from\s)\s*(-?\d+(?:\.\d+)?)deg/i.exec(bgc);
        if (ang) setGradAngle(Math.round(Number(ang[1])));
        const cols = bgc.match(/#[0-9a-fA-F]{3,8}|rgba?\([^)]*\)/g) ?? [];
        if (cols.length >= 2) setGradStops(cols.slice(0, 4));
        setBgColorVal("");
      } else {
        setBgColorVal(bgc === "transparent" ? "" : bgc);
      }
      // Hydrate borders (Task #6038): shorthand + per-corner + per-side.
      const bstr = (v: unknown): string =>
        typeof v === "string" ? v.trim() : typeof v === "number" ? String(v) : "";
      // Hydrate per-device block width (Task #6119). Spans may round-trip
      // as numbers (sanitizer casts) — normalize to the chip value strings.
      const spanNum = (v: unknown): number => {
        const n = parseInt(bstr(v), 10);
        return Number.isFinite(n) && n >= 1 && n <= 12 ? n : 0;
      };
      setGridSpan(String(spanNum(st.grid_span) || 12));
      const mdSpan = spanNum(st.grid_span_md);
      setGridSpanMd(mdSpan ? String(mdSpan) : "");
      setWidthDevice("mobile");
      // Hydrate per-device row span (Task #6123) — bounded 1..6, "" = unset.
      const rowSpanNum = (v: unknown): number => {
        const n = parseInt(bstr(v), 10);
        return Number.isFinite(n) && n >= 1 && n <= 6 ? n : 0;
      };
      const baseRowSpan = rowSpanNum(st.grid_row_span);
      setGridRowSpan(baseRowSpan ? String(baseRowSpan) : "");
      const mdRowSpan = rowSpanNum(st.grid_row_span_md);
      setGridRowSpanMd(mdRowSpan ? String(mdRowSpan) : "");
      setHeightDevice("mobile");
      setBdStyle(bstr(st.border_style) || "none");
      setBdWidth(bstr(st.border_width));
      setBdColor(bstr(st.border_color));
      setBdRadius(bstr(st.border_radius));
      const corners = {
        tl: bstr(st.border_radius_tl),
        tr: bstr(st.border_radius_tr),
        bl: bstr(st.border_radius_bl),
        br: bstr(st.border_radius_br),
      };
      setBdCorners(corners);
      const sidesNext = {
        top: { style: bstr(st.border_top_style), width: bstr(st.border_top_width), color: bstr(st.border_top_color) },
        right: { style: bstr(st.border_right_style), width: bstr(st.border_right_width), color: bstr(st.border_right_color) },
        bottom: { style: bstr(st.border_bottom_style), width: bstr(st.border_bottom_width), color: bstr(st.border_bottom_color) },
        left: { style: bstr(st.border_left_style), width: bstr(st.border_left_width), color: bstr(st.border_left_color) },
      };
      setBdSides(sidesNext);
      // Auto-expand the advanced panel when any advanced value is set,
      // mirroring the web expander's initial state.
      setBdAdvOpen(
        Object.values(corners).some((v) => v !== "") ||
          Object.values(sidesNext).some((s) => s.style !== "" || s.width !== "" || s.color !== ""),
      );
    }
  }, [block]);

  // Measure the block photo's natural aspect ratio so the sticker stage
  // matches the web editor's stage proportions (falls back to 4:3 while
  // loading or when the URL can't be measured).
  const stickerImageUri = isImageBlock
    ? photoStickerImageUri((values.url ?? "").trim())
    : "";
  useEffect(() => {
    if (!stickerImageUri) return;
    let cancelled = false;
    Image.getSize(
      stickerImageUri,
      (w, h) => {
        if (!cancelled && w > 0 && h > 0) setStickerStageRatio(w / h);
      },
      () => {},
    );
    return () => {
      cancelled = true;
    };
  }, [stickerImageUri]);

  // Hydrate favorites from AsyncStorage once we know the block's type
  // (favorites are scoped per-type to match the web editor's localStorage
  // key shape so picks roam across surfaces).
  useEffect(() => {
    if (!favoritesKey) return;
    AsyncStorage.getItem(favoritesKey)
      .then((raw) => {
        if (!raw) return;
        try {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) setFavorites(parsed.filter((k): k is string => typeof k === "string"));
        } catch {}
      })
      .catch(() => {});
  }, [favoritesKey]);

  const persistFavorites = useCallback(
    (next: string[]) => {
      setFavorites(next);
      if (favoritesKey) AsyncStorage.setItem(favoritesKey, JSON.stringify(next)).catch(() => {});
    },
    [favoritesKey],
  );

  const toggleFavorite = useCallback(
    (key: string) => {
      const i = favorites.indexOf(key);
      const next = i === -1 ? [...favorites, key] : favorites.filter((k) => k !== key);
      persistFavorites(next);
    },
    [favorites, persistFavorites],
  );

  const visibleVariants = useMemo(() => {
    if (!block) return [];
    const all = variantsForType(block.type);
    if (activeFilter === "all") return all;
    if (activeFilter === "favorites") return all.filter((v) => favorites.indexOf(v.key) !== -1);
    return all.filter((v) => v.tags.indexOf(activeFilter) !== -1);
  }, [block, activeFilter, favorites, designCatalog]);

  // Build the next block.settings payload for a given variant key. We do
  // a FULL `_style` REPLACE — never a merge — so swapping from variant A
  // to variant B can't leak any of A's keys into the new block style.
  // The first time a variant skins handcrafted styling we snapshot the
  // original `_style` into `_style_custom_snapshot` (matching the web
  // editor's restore-custom path).
  const buildVariantSettings = useCallback(
    (currentSettings: Record<string, unknown> | null, key: string) => {
      const settings: Record<string, unknown> = { ...(currentSettings ?? {}) };
      const existingStyle =
        (settings._style as Record<string, unknown> | undefined) ?? {};
      if (key === "") {
        const snap = settings._style_custom_snapshot as
          | Record<string, unknown>
          | undefined;
        settings._style = snap
          ? { ...snap, _variant: "", _variant_version: 0 }
          : { _variant: "", _variant_version: 0 };
        return settings;
      }
      const variant = findVariant(block?.type ?? "", key);
      const oldVariant = (existingStyle._variant as string) || "";
      const hasHandcrafted = Object.keys(existingStyle).some(
        (k) => k !== "_variant" && k !== "_variant_version",
      );
      if (
        oldVariant === "" &&
        hasHandcrafted &&
        !settings._style_custom_snapshot
      ) {
        const snap: Record<string, unknown> = { ...existingStyle };
        delete snap._variant;
        delete snap._variant_version;
        settings._style_custom_snapshot = snap;
      }
      // Variant payload comes from the catalog's preview hints (mobile's
      // smaller surface) — bg/text/border/radius. Backend validators will
      // sanitize anything weird through the same pipeline as web.
      const p = variant?.preview;
      const replaced: Record<string, unknown> = {
        _variant: key,
        _variant_version: VARIANT_VERSION,
      };
      if (p?.bg) replaced.bg_color = p.bg;
      if (p?.text) replaced.text_color = p.text;
      if (p?.border) {
        replaced.border_color = p.border;
        replaced.border_width = "1";
        replaced.border_style = p.dashed ? "dashed" : "solid";
      } else {
        replaced.border_style = "none";
      }
      if (typeof p?.radius === "number") replaced.border_radius = String(p.radius);
      // Profile-card identity designs carry a structural layout token. Stamp
      // it into `_style._profile_layout` so the public renderer dispatches on
      // the chosen layout (mirrors the web `profile_identity` payload).
      if (variant?.profileLayout) replaced._profile_layout = variant.profileLayout;
      settings._style = replaced;
      return settings;
    },
    [block],
  );

  const applyVariantMutation = useMutation({
    mutationFn: (key: string) =>
      updateBlock(id, blockId, {
        settings: buildVariantSettings(block?.settings ?? null, key),
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["blocks", id] });
    },
  });

  const applyToAllMutation = useMutation({
    mutationFn: async (key: string) => {
      // Iterate sibling blocks of the same type and PATCH each with the
      // same full-replace style payload. We hit the per-block endpoint
      // rather than a single bulk endpoint so each block goes through
      // the standard sanitize + snapshot path.
      const siblings = (q.data ?? []).filter((b) => b.type === block?.type);
      let count = 0;
      for (const sib of siblings) {
        await updateBlock(id, sib.id, {
          settings: buildVariantSettings(sib.settings ?? null, key),
        });
        count += 1;
      }
      return count;
    },
    onSuccess: (count) => {
      qc.invalidateQueries({ queryKey: ["blocks", id] });
      showAlert("Applied", `Design applied to ${count} block(s).`);
    },
  });

  const handleApplyVariant = useCallback(
    (key: string) => {
      const next = variantKey === key ? "" : key;
      setVariantKey(next);
      applyVariantMutation.mutate(next);
    },
    [variantKey, applyVariantMutation],
  );

  const surpriseMe = useCallback(() => {
    if (!block) return;
    const pool = variantsForType(block.type).filter((v) => v.key !== variantKey);
    const pick = pool[Math.floor(Math.random() * pool.length)];
    if (pick) handleApplyVariant(pick.key);
  }, [block, variantKey, handleApplyVariant]);

  const handleApplyToAll = useCallback(() => {
    if (!variantKey || !block) return;
    showAlert(
      "Apply to all",
      `Apply this design to every ${block.type} block on this page?`,
      [
        { text: "Cancel", style: "cancel" },
        { text: "Apply", onPress: () => applyToAllMutation.mutate(variantKey) },
      ],
    );
  }, [variantKey, block, applyToAllMutation]);

  const restoreCustom = useCallback(() => {
    if (!block?.settings?._style_custom_snapshot) return;
    setVariantKey("");
    applyVariantMutation.mutate("");
  }, [block, applyVariantMutation]);

  const save = useMutation({
    mutationFn: () => {
      // Field saves are content-only — the API PATCH replaces `settings`
      // wholesale, so we start from the block's current settings and only
      // overlay the keys this editor actually surfaces. This preserves
      // object-shaped keys the mobile UI doesn't edit (e.g. `_image_style`
      // mask/border/shadow config, `_style` for every block type, and the
      // pre-variant `_style_custom_snapshot`) so a mobile content edit
      // never silently wipes web-configured styling. Variant changes flow
      // through the dedicated apply path above, so carrying the persisted
      // `_style` forward here is a no-op for variants.
      const prevSettings =
        (block?.settings as Record<string, unknown> | undefined) ?? {};
      const nextSettings: Record<string, unknown> = {
        ...prevSettings,
        ...values,
      };
      // A save from mobile is a real content edit: clear the seeded
      // placeholder flag just like a fresh payload used to (before this
      // merge, the flag was dropped implicitly by the wholesale replace).
      delete nextSettings._placeholder;
      delete nextSettings._placeholder_seed;
      // Merge per-block targeting back into `_visibility`. We preserve any
      // pre-existing keys (continents/cities/os/browsers/languages/time_slots)
      // that the mobile UI doesn't surface yet so saving from mobile never
      // wipes settings configured from the web editor.
      const csvToCodes = (s: string): string[] =>
        s.split(",").map((x) => x.trim().toUpperCase()).filter((x) => /^[A-Z]{2}$/.test(x));
      const prevVis = (block?.settings?._visibility as Record<string, unknown> | undefined) ?? {};
      nextSettings._visibility = {
        ...prevVis,
        countries: csvToCodes(visCountries),
        countries_exclude: csvToCodes(visCountriesExclude),
        devices: Array.from(visDevices),
        devices_exclude: Array.from(visDevicesExclude),
      };

      // Persist trackable-link settings under their structured `_link`
      // sub-object. Preserve any pre-existing `_link` fields the editor
      // doesn't surface (target, rel, title) so we don't blow them away.
      const prevLink =
        (block?.settings?._link as Record<string, unknown> | undefined) ?? {};
      const cleanOverrides: Record<string, string> = {};
      UTM_KEYS.forEach((k) => {
        const v = (autoUtmOverrides[k] || "").trim();
        if (v !== "") cleanOverrides[k] = v;
      });
      const trimmedUrl = linkUrl.trim();
      const linkOut: Record<string, unknown> = { ...prevLink };
      // Drop legacy flat utm_* overrides — they've been promoted into
      // auto_utm.overrides above so we don't want stale duplicates.
      UTM_KEYS.forEach((k) => delete linkOut[k]);
      if (trimmedUrl) linkOut.url = trimmedUrl;
      else delete linkOut.url;
      // Keep the legacy generic `settings.url` in lockstep with the
      // trackable destination so renderers that still read it (and the
      // redirect's `$s['url']` fallback) never disagree with `_link.url`.
      if (trimmedUrl) nextSettings.url = trimmedUrl;
      else delete nextSettings.url;
      linkOut.auto_utm = {
        enabled: autoUtmEnabled,
        overrides: cleanOverrides,
      };
      nextSettings._link = linkOut;
      // For list/pricing blocks, replace the primitive `style`/`icon`
      // strings copied into `values` with the structured editor state
      // (style + items + per-item icons). Empty trailing rows are
      // dropped so a tap-and-leave doesn't persist blank entries.
      if (isList || isListNumbered) {
        nextSettings.style = listStyle;
        if (isList) nextSettings.icon = defaultBulletIcon;
        nextSettings.items = listItems
          .filter((it) => it.text.trim() !== "" || it.icon)
          .map((it) => (isList ? { text: it.text, icon: it.icon } : { text: it.text }));
      } else if (isPricing) {
        nextSettings.style = listStyle;
        // Keep any row that has *anything* meaningful filled in. Earlier
        // we only kept rows with name/price/description, but that dropped
        // rows where a creator only set, say, a thumbnail + featured
        // flag and hadn't typed a name yet. We treat the row as empty
        // only if every textual field is blank AND no flag is set.
        nextSettings.items = pricingItems
          .filter(
            (it) =>
              it.name.trim() !== "" ||
              it.price.trim() !== "" ||
              it.period.trim() !== "" ||
              it.description.trim() !== "" ||
              it.thumbnail.trim() !== "" ||
              it.icon.trim() !== "" ||
              it.featured ||
              !it.included,
          )
          .map((it) => ({
            name: it.name,
            description: it.description,
            price: it.price,
            period: it.period,
            included: it.included,
            featured: it.featured,
            thumbnail: it.thumbnail,
            icon: it.icon,
          }));
      }
      // Profile-card identity blocks: persist the boolean `verified` flag
      // and the `socials` repeater (dropping fully-empty rows). The string
      // fields (name/title/bio/avatar/cover/location/website/cta_*) already
      // round-trip through the generic `values` spread above.
      if (isProfileCard) {
        nextSettings.verified = profileVerified;
        nextSettings.socials = profileSocials
          .map((s) => ({ name: s.name.trim(), url: s.url.trim() }))
          .filter((s) => s.name !== "" || s.url !== "");
        // Avatar frame (Task #5910): merge the two frame keys into the
        // block's current _style (the generic save otherwise strips
        // _style so variants stay server-owned). Empty selections drop
        // the keys, matching the web editor's clear-on-empty semantics.
        const prevStyle =
          (block?.settings?._style as Record<string, unknown> | undefined) ?? {};
        const styleOut: Record<string, unknown> = { ...prevStyle };
        if (avatarFrame) styleOut._avatar_frame = avatarFrame;
        else delete styleOut._avatar_frame;
        if (avatarFrameColor) styleOut._avatar_frame_color = avatarFrameColor;
        else delete styleOut._avatar_frame_color;
        nextSettings._style = styleOut;
      }
      // Stats + badges round-trip in the same shapes the web editor and the
      // public renderer expect: `[{label,value}]` and `[{label}]`. They ride
      // only on the layout that actually paints them (stats / badges), and
      // the caps mirror the backend sanitizer (6 stats, 12 badges).
      if (profileCardLayout === "stats") {
        nextSettings.stats = profileStats
          .map((s) => ({ label: s.label.trim(), value: s.value.trim() }))
          .filter((s) => s.label !== "" || s.value !== "")
          .slice(0, 6);
      }
      if (profileCardLayout === "badges") {
        nextSettings.badges = profileBadges
          .map((b) => ({ label: b.label.trim() }))
          .filter((b) => b.label !== "")
          .slice(0, 12);
      }
      // Gallery/grid blocks: persist the images repeater ([{url, alt}]),
      // dropping rows without a URL so tap-and-leave never saves blanks.
      if (isGalleryBlock) {
        nextSettings.images = galleryImages
          .map((i) => ({ url: i.url.trim(), alt: i.alt.trim() }))
          .filter((i) => i.url !== "");
      }
      // Image block: merge the drag-positioned photo stickers back into
      // the block's current `_style` (the API replaces `settings` wholesale,
      // so without this merge the whole _style — stickers included — would
      // be wiped on every mobile save). Empty array drops the key; entries
      // are already normalized to the server sanitizer's shape/bounds.
      if (isImageBlock) {
        const prevStyle =
          (block?.settings?._style as Record<string, unknown> | undefined) ?? {};
        const styleOut: Record<string, unknown> = { ...prevStyle };
        if (photoStickers.length > 0) {
          styleOut._photo_stickers = photoStickers.slice(0, 4).map((s) => ({
            file_id: s.file_id,
            url: s.url,
            pos: s.pos,
            size: clampNum(Math.round(s.size), 24, 160),
            rotate: clampNum(Math.round(s.rotate), -180, 180),
            dx: clampNum(Math.round(s.dx), -80, 80),
            dy: clampNum(Math.round(s.dy), -80, 80),
          }));
        } else {
          delete styleOut._photo_stickers;
        }
        if (Object.keys(styleOut).length > 0) nextSettings._style = styleOut;
      }
      // Block background preset (Task #5970): merge the preset key +
      // opacity into whatever `_style` has been assembled so far (profile
      // avatar frame / image stickers may already have populated it).
      // Empty key deletes both so clearing round-trips.
      {
        const baseStyle =
          (nextSettings._style as Record<string, unknown> | undefined) ??
          (block?.settings?._style as Record<string, unknown> | undefined) ??
          {};
        const styleOut: Record<string, unknown> = { ...baseStyle };
        if (bgMode === "preset" && bgPresetKey) {
          styleOut.bg_preset_key = bgPresetKey;
          styleOut.bg_preset_opacity = clampNum(Math.round(bgPresetOpacity), 0, 100);
        } else {
          delete styleOut.bg_preset_key;
          delete styleOut.bg_preset_opacity;
        }
        // Unified background modes (Task #6044): each mode owns its
        // field(s); the others are dropped so switching modes round-trips
        // cleanly (server merge semantics treat a missing key as removal
        // only when an empty value is sent — we delete + rely on the
        // full-_style replace the mobile save already performs).
        if (bgMode === "color" && bgColorVal.trim() !== "") {
          styleOut.bg_color = bgColorVal.trim();
        } else if (bgMode === "gradient") {
          const stops = gradStops.filter((c) => c.trim() !== "");
          if (stops.length >= 2) {
            const stopList = stops
              .map((c, i) => `${c.trim()} ${Math.round((i / (stops.length - 1)) * 100)}%`)
              .join(", ");
            styleOut.bg_color =
              gradType === "linear"
                ? `linear-gradient(${gradAngle}deg, ${stopList})`
                : gradType === "radial"
                  ? `radial-gradient(circle at center, ${stopList})`
                  : `conic-gradient(from ${gradAngle}deg at center, ${stopList})`;
          } else {
            delete styleOut.bg_color;
          }
        } else {
          delete styleOut.bg_color;
        }
        if (bgMode === "image" && bgImageVal.trim() !== "") {
          styleOut.bg_image = bgImageVal.trim();
        } else {
          delete styleOut.bg_image;
        }
        // Borders (Task #6038): persist shorthand + per-corner + per-side
        // values; blank fields are deleted so clearing round-trips and the
        // renderers fall back to the shorthand field-by-field.
        const putStyle = (key: string, val: string) => {
          if (val.trim() !== "") styleOut[key] = val.trim();
          else delete styleOut[key];
        };
        // Per-device block width (Task #6119): the base span always
        // persists (12 = full width); the desktop override only when set —
        // deleting it means "same as mobile" on the public page.
        putStyle("grid_span", gridSpan);
        putStyle("grid_span_md", gridSpanMd);
        // Per-device row span (Task #6123): both keys only persist when
        // set — deleting them means "auto height" / "same as mobile".
        putStyle("grid_row_span", gridRowSpan);
        putStyle("grid_row_span_md", gridRowSpanMd);
        putStyle("border_style", bdStyle === "none" ? "" : bdStyle);
        putStyle("border_width", bdWidth);
        putStyle("border_color", bdColor);
        putStyle("border_radius", bdRadius);
        putStyle("border_radius_tl", bdCorners.tl);
        putStyle("border_radius_tr", bdCorners.tr);
        putStyle("border_radius_bl", bdCorners.bl);
        putStyle("border_radius_br", bdCorners.br);
        (["top", "right", "bottom", "left"] as const).forEach((side) => {
          putStyle(`border_${side}_style`, bdSides[side].style);
          putStyle(`border_${side}_width`, bdSides[side].width);
          putStyle(`border_${side}_color`, bdSides[side].color);
        });
        if (Object.keys(styleOut).length > 0) nextSettings._style = styleOut;
        else delete nextSettings._style;
      }
      // Map-location block: the boolean toggle round-trips through its own
      // state (the generic `values` map would otherwise stringify it).
      // address/lat/lng/label/zoom already ride along in `nextSettings`
      // via the `...values` spread above.
      if (isMapLocation) {
        nextSettings.show_directions = mapShowDirections;
      }
      // Stamp the limits config alongside any existing settings — this
      // is a merge by the time the backend sanitizer sees it (the web
      // controller preserves _style etc. via $request->validate +
      // sanitizeSettings), but we send the structured _limits sub-array
      // so the editor preview and the public renderer agree on shape.
      nextSettings._limits = {
        show_countdown: showCountdown,
        show_remaining: showRemaining,
        near_threshold_percent: nearPercent,
        expired_action: expiredAction,
        expired_label: expiredLabel.slice(0, 40),
        expired_emoji: expiredEmoji.slice(0, 4),
      };
      // Trim the local datetime string to ISO for the API. Empty string
      // → null clears the expiry; the backend treats null as "no expiry".
      const endDateIso = endDate ? endDate : null;
      const mcParsed = parseInt((maxClicks || "").trim(), 10);
      const maxClicksOut = Number.isFinite(mcParsed) && mcParsed > 0 ? mcParsed : null;
      // Remember any applied custom colors as extra swatches for every
      // ColorSwatchRow (persists across restarts via AsyncStorage).
      rememberRecentColors([
        bgMode === "color" ? bgColorVal : "",
        ...(bgMode === "gradient" ? gradStops : []),
        bdColor,
        bdSides.top.color,
        bdSides.right.color,
        bdSides.bottom.color,
        bdSides.left.color,
        avatarFrameColor,
      ]);
      return updateBlock(id, blockId, {
        is_active: active,
        settings: nextSettings,
        end_date: endDateIso,
        max_clicks: maxClicksOut,
      });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["blocks", id] });
      if (onDone) onDone();
      else router.back();
    },
  });

  // Visible filter chips: only tags actually used by this type's catalog.
  const tagChips = useMemo(() => {
    if (!block) return [] as string[];
    const present = new Set<string>();
    variantsForType(block.type).forEach((v) => v.tags.forEach((t) => present.add(t)));
    return Array.from(present);
  }, [block, designCatalog]);

  // Voice turns started while this editor is open prefer the general
  // in-app tools; dictation works via the per-field mics regardless.
  useFocusEffect(
    useCallback(() => {
      setVoiceSurface("app");
      return () => setVoiceSurface(null);
    }, []),
  );

  // Append a dictated chunk to whichever field's setter is passed in,
  // mirroring the create form's per-field dictation factory.
  const dictateInto =
    (setter: React.Dispatch<React.SetStateAction<string>>) => (t: string) =>
      setter((v) => (v ? v.trim() + " " : "") + t);

  if (q.isLoading) {
    return (
      <View style={inline ? styles.centerInline : styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (!block) {
    return (
      <View style={inline ? styles.centerInline : styles.center}>
        <Text style={{ color: colors.destructive }}>Block not found.</Text>
      </View>
    );
  }

  const meta = blockKind(block.type);

  const body = (
    <>
        {meta ? (
          <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
            {meta.blurb}
          </Text>
        ) : null}

        {/* First-paint placeholder banner + sample pill. Cleared by
            backend update() once the creator edits a seeded field. */}
        {block.settings?._placeholder ? (
          <>
            <View
              style={{
                flexDirection: "row",
                alignItems: "flex-start",
                gap: 10,
                padding: 12,
                borderRadius: 12,
                backgroundColor: "rgba(61,107,255,0.16)",
                borderWidth: 1,
                borderColor: "rgba(167,139,250,0.35)",
              }}
            >
              <Feather name="zap" size={16} color="#fbbf24" style={{ marginTop: 2 }} />
              <Text
                style={{
                  flex: 1,
                  color: colors.foreground,
                  fontSize: 12.5,
                  lineHeight: 18,
                }}
              >
                <Text style={{ fontWeight: "700" }}>We dropped in placeholder content </Text>
                so this block looks great right away. Tap any text or media field below to replace it; this notice will disappear once you edit a seeded field.
              </Text>
            </View>
            {/* Compact "Sample" pill row — visual reminder kept right
                above the dynamic field UI so creators understand the
                values they see in inputs are placeholders. */}
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                gap: 6,
                alignSelf: "flex-start",
                paddingHorizontal: 8,
                paddingVertical: 3,
                borderRadius: 999,
                backgroundColor: "rgba(251,191,36,0.18)",
                borderWidth: 1,
                borderColor: "rgba(251,191,36,0.35)",
              }}
            >
              <Feather name="edit-3" size={11} color="#fbbf24" />
              <Text style={{ color: "#fbbf24", fontSize: 10, fontWeight: "700", letterSpacing: 0.4, textTransform: "uppercase" }}>
                Sample: tap to replace
              </Text>
            </View>
          </>
        ) : null}

        {/* Designs gallery — full mobile parity with the web editor:
            filter chips (incl. Favorites), Surprise me, Apply to all of
            this type, and a Custom snapshot restore card when the block
            has handcrafted styling captured. Variant keys are identical
            to the web catalog so picks roam across surfaces. Hidden
            entirely while the page is design-locked to a template. */}
        {designLocked ? (
          <View
            style={{
              padding: 12,
              borderRadius: 12,
              borderWidth: 1,
              borderColor: "rgba(245,158,11,0.4)",
              backgroundColor: colors.card,
              flexDirection: "row",
              alignItems: "center",
              gap: 8,
            }}
            testID="block-design-locked-note"
          >
            <Text style={{ fontSize: 14 }}>🔒</Text>
            <Text style={{ color: colors.mutedForeground, fontSize: 12, flex: 1 }}>
              Styling follows this page's template design. Detach from the
              template in page settings to unlock block designs.
            </Text>
          </View>
        ) : (
        <View style={{ gap: 8 }}>
          <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
            <Text style={[styles.rowLabel, { color: colors.foreground }]}>Design</Text>
            <Pressable {...WEB_FOCUS_RING_PROPS}
              onPress={surpriseMe}
              style={{
                paddingHorizontal: 10,
                paddingVertical: 6,
                borderRadius: 999,
                backgroundColor: colors.primary,
              }}
            >
              <Text style={{ color: "#fff", fontWeight: "700", fontSize: 11 }}>🎲 Surprise me</Text>
            </Pressable>
          </View>

          {/* Filter chips */}
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 6 }}>
            {(["all", "favorites", ...tagChips] as const).map((f) => {
              const selected = activeFilter === f;
              const label =
                f === "all" ? "All" : f === "favorites" ? "★ Favorites" : variantTagLabel(f as string);
              return (
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  key={f}
                  onPress={() => setActiveFilter(f as string)}
                  style={{
                    paddingHorizontal: 10,
                    paddingVertical: 5,
                    borderRadius: 999,
                    backgroundColor: selected ? colors.primary : colors.card,
                    borderWidth: 1,
                    borderColor: selected ? colors.primary : colors.border,
                  }}
                >
                  <Text style={{ color: selected ? "#fff" : colors.foreground, fontWeight: "600", fontSize: 11 }}>
                    {label}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>

          {/* Custom restore card */}
          {block.settings?._style_custom_snapshot ? (
            <Pressable {...WEB_FOCUS_RING_PROPS}
              onPress={restoreCustom}
              style={{
                padding: 10,
                borderRadius: 12,
                borderWidth: 1,
                borderStyle: "dashed",
                borderColor: colors.primary,
                backgroundColor: colors.card,
                flexDirection: "row",
                alignItems: "center",
                gap: 8,
              }}
            >
              <Text style={{ fontSize: 16 }}>🎨</Text>
              <View style={{ flex: 1 }}>
                <Text style={{ color: colors.foreground, fontWeight: "700", fontSize: 12 }}>
                  Custom (your tweaks)
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 10 }}>
                  Restore your handcrafted styling.
                </Text>
              </View>
              <Text style={{ color: colors.primary, fontWeight: "700", fontSize: 11 }}>↺</Text>
            </Pressable>
          ) : null}

          {/* Variant grid */}
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
            {visibleVariants.map((v) => {
              const selected = variantKey === v.key;
              const fav = favorites.indexOf(v.key) !== -1;
              return (
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  key={v.key}
                  onPress={() => handleApplyVariant(v.key)}
                  style={{
                    width: 92,
                    padding: 8,
                    borderRadius: 12,
                    backgroundColor: colors.card,
                    borderWidth: selected ? 2 : 1,
                    borderColor: selected ? colors.primary : colors.border,
                  }}
                >
                  <Pressable {...WEB_FOCUS_RING_PROPS}
                    onPress={() => toggleFavorite(v.key)}
                    hitSlop={8}
                    style={{ position: "absolute", top: 4, right: 4, zIndex: 1, padding: 2 }}
                  >
                    <Text style={{ color: fav ? colors.primary : colors.mutedForeground, fontSize: 12 }}>
                      {fav ? "★" : "☆"}
                    </Text>
                  </Pressable>
                  <View
                    style={{
                      height: 44,
                      borderRadius: Math.min(v.preview.radius, 16),
                      backgroundColor: v.preview.bg === "transparent" ? "transparent" : v.preview.bg,
                      borderWidth: v.preview.border ? 1 : 0,
                      borderColor: v.preview.border ?? "transparent",
                      borderStyle: v.preview.dashed ? "dashed" : "solid",
                      alignItems: "center",
                      justifyContent: "center",
                      marginTop: 12,
                      marginBottom: 6,
                    }}
                  >
                    <Text style={{ color: v.preview.text, fontWeight: "700", fontSize: 11 }} numberOfLines={1}>
                      {v.name.slice(0, 8)}
                    </Text>
                  </View>
                  <Text numberOfLines={1} style={{ color: colors.foreground, fontSize: 11, fontWeight: "600" }}>
                    {v.name}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>

          {visibleVariants.length === 0 ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 11, textAlign: "center", paddingVertical: 8 }}>
              No designs match this filter yet.
            </Text>
          ) : null}

          {/* Apply to all */}
          {variantKey ? (
            <Pressable {...WEB_FOCUS_RING_PROPS}
              onPress={handleApplyToAll}
              disabled={applyToAllMutation.isPending}
              style={{
                padding: 10,
                borderRadius: 10,
                borderWidth: 1,
                borderStyle: "dashed",
                borderColor: colors.border,
                alignItems: "center",
                opacity: applyToAllMutation.isPending ? 0.6 : 1,
              }}
            >
              <Text style={{ color: colors.foreground, fontSize: 11, fontWeight: "700" }}>
                {applyToAllMutation.isPending
                  ? "Applying…"
                  : `Apply this design to all ${block.type} blocks`}
              </Text>
            </Pressable>
          ) : null}
        </View>
        )}

        {/* Block background preset (Task #5970) — mirrors the web editor's
            Look-tab picker: catalog presets (torn-paper excluded server-side
            via `paper`) painted behind THIS block with a 0–100 transparency.
            Saved into `_style.bg_preset_key` / `_style.bg_preset_opacity`
            on the normal save path. */}
        {/* Unified block background (Task #6044) — None / Color / Gradient /
            Preset / Image mode picker mirroring the web Style tab. */}
        <View style={{ gap: 8 }} testID="block-bg-section">
          <Text style={[styles.rowLabel, { color: colors.foreground }]}>Background</Text>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
            {(
              [
                { key: "none", label: "None" },
                { key: "color", label: "Color" },
                { key: "gradient", label: "Gradient" },
                { key: "preset", label: "Preset" },
                { key: "image", label: "Image" },
              ] as const
            ).map((m) => {
              const sel = bgMode === m.key;
              return (
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  key={m.key}
                  testID={`block-bg-mode-${m.key}`}
                  onPress={() => {
                    setBgMode(m.key);
                    if (m.key === "preset") setBgPresetOpen(true);
                  }}
                  style={{
                    paddingHorizontal: 12,
                    paddingVertical: 6,
                    borderRadius: 999,
                    backgroundColor: sel ? colors.primary : colors.card,
                    borderWidth: 1,
                    borderColor: sel ? colors.primary : colors.border,
                  }}
                >
                  <Text style={{ color: sel ? "#fff" : colors.foreground, fontWeight: "600", fontSize: 11 }}>
                    {m.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          {bgMode === "color" ? (
            <View style={{ gap: 6 }}>
              <ColorSwatchRow
                prefix="block-bg-color-swatch"
                value={bgColorVal}
                onPick={setBgColorVal}
                palette={["#7c3aed", "#2563eb", "#059669", "#dc2626", "#f59e0b", "#0f172a", "#ffffff", "rgba(255,255,255,0.12)"]}
              />
              <TextInput
                testID="block-bg-color-input"
                value={bgColorVal}
                onChangeText={(v) => {
                  setBgColorVal(v);
                  // Valid typed colors join the shared recent set (debounced).
                  rememberRecentColorFromTyping("block-bg-color", v);
                }}
                placeholder="#7c3aed or rgba(...)"
                placeholderTextColor={colors.mutedForeground}
                autoCapitalize="none"
                autoCorrect={false}
                style={[{ borderWidth: 1, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 8, fontSize: 13, color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card }]}
              />
            </View>
          ) : null}

          {bgMode === "gradient" ? (
            <View style={{ gap: 8 }}>
              <View style={{ flexDirection: "row", gap: 6 }}>
                {(["linear", "radial", "conic"] as const).map((g) => {
                  const sel = gradType === g;
                  return (
                    <Pressable {...WEB_FOCUS_RING_PROPS}
                      key={g}
                      testID={`block-bg-grad-${g}`}
                      onPress={() => setGradType(g)}
                      style={{
                        paddingHorizontal: 10,
                        paddingVertical: 5,
                        borderRadius: 999,
                        backgroundColor: sel ? colors.primary : colors.card,
                        borderWidth: 1,
                        borderColor: sel ? colors.primary : colors.border,
                      }}
                    >
                      <Text style={{ color: sel ? "#fff" : colors.foreground, fontWeight: "600", fontSize: 11 }}>
                        {g.charAt(0).toUpperCase() + g.slice(1)}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
              {gradType !== "radial" ? (
                <View style={{ gap: 4 }}>
                  <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>Angle · {gradAngle}°</Text>
                  <Slider
                    style={{ width: "100%", height: 32 }}
                    minimumValue={0}
                    maximumValue={360}
                    step={1}
                    value={gradAngle}
                    minimumTrackTintColor={colors.primary}
                    maximumTrackTintColor={colors.border}
                    thumbTintColor={colors.primary}
                    onValueChange={(v) => setGradAngle(Math.round(v))}
                  />
                </View>
              ) : null}
              {gradStops.map((c, i) => (
                <View key={i} style={{ gap: 6 }}>
                <ColorSwatchRow
                  prefix={`block-bg-grad-stop-${i}-swatch`}
                  value={c}
                  onPick={(v) =>
                    setGradStops((prev) => prev.map((p, j) => (j === i ? v : p)))
                  }
                  size={22}
                />
                <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                  <View style={{ width: 24, height: 24, borderRadius: 6, backgroundColor: c || colors.muted, borderWidth: 1, borderColor: colors.border }} />
                  <TextInput
                    testID={`block-bg-grad-stop-${i}`}
                    value={c}
                    onChangeText={(v) => {
                      setGradStops((prev) => prev.map((p, j) => (j === i ? v : p)));
                      rememberRecentColorFromTyping(`block-bg-grad-stop-${i}`, v);
                    }}
                    placeholder="#7c3aed"
                    placeholderTextColor={colors.mutedForeground}
                    autoCapitalize="none"
                    autoCorrect={false}
                    style={[{ borderWidth: 1, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 8, fontSize: 13, flex: 1, color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card }]}
                  />
                  {gradStops.length > 2 ? (
                    <Pressable {...WEB_FOCUS_RING_PROPS}
                      onPress={() => setGradStops((prev) => prev.filter((_, j) => j !== i))}
                      style={{ paddingHorizontal: 8, paddingVertical: 6 }}
                    >
                      <Text style={{ color: colors.destructive, fontWeight: "700", fontSize: 12 }}>✕</Text>
                    </Pressable>
                  ) : null}
                </View>
                </View>
              ))}
              {gradStops.length < 4 ? (
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  testID="block-bg-grad-add-stop"
                  onPress={() => setGradStops((prev) => [...prev, "#f59e0b"])}
                  style={{
                    alignSelf: "flex-start",
                    paddingHorizontal: 10,
                    paddingVertical: 6,
                    borderRadius: 999,
                    borderWidth: 1,
                    borderColor: colors.border,
                  }}
                >
                  <Text style={{ color: colors.foreground, fontWeight: "600", fontSize: 11 }}>+ Add color</Text>
                </Pressable>
              ) : null}
            </View>
          ) : null}

          {bgMode === "image" ? (
            <View style={{ gap: 8 }}>
              <TextInput
                testID="block-bg-image-input"
                value={bgImageVal}
                onChangeText={setBgImageVal}
                placeholder="https://… or /f/… vault path"
                placeholderTextColor={colors.mutedForeground}
                autoCapitalize="none"
                autoCorrect={false}
                style={[{ borderWidth: 1, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 8, fontSize: 13, color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card }]}
              />
              <Pressable {...WEB_FOCUS_RING_PROPS}
                testID="block-bg-image-upload"
                disabled={bgImgUploading}
                onPress={async () => {
                  const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
                  if (!perm.granted) {
                    showAlert(
                      "Photos access needed",
                      "Allow access to your photo library in Settings to pick an image.",
                    );
                    return;
                  }
                  const res = await ImagePicker.launchImageLibraryAsync({
                    mediaTypes: ImagePicker.MediaTypeOptions.Images,
                    quality: 0.9,
                  });
                  if (res.canceled || !res.assets?.[0]) return;
                  const asset = res.assets[0];
                  setBgImgUploading(true);
                  try {
                    const file = await uploadVaultFile({
                      uri: asset.uri,
                      name: asset.fileName ?? undefined,
                      mime: asset.mimeType ?? undefined,
                    });
                    setBgImageVal(file.url_path || file.url);
                  } catch (e) {
                    if (!handlePlanLockedError(e, "Your storage is full on your current plan.")) {
                      const msg =
                        e && typeof e === "object" && "message" in e
                          ? String((e as { message: unknown }).message)
                          : "Upload failed.";
                      showAlert("Upload failed", msg);
                    }
                  } finally {
                    setBgImgUploading(false);
                  }
                }}
                style={{
                  alignSelf: "flex-start",
                  paddingHorizontal: 12,
                  paddingVertical: 7,
                  borderRadius: 999,
                  borderWidth: 1,
                  borderColor: colors.border,
                  backgroundColor: colors.card,
                  opacity: bgImgUploading ? 0.6 : 1,
                }}
              >
                <Text style={{ color: colors.foreground, fontWeight: "600", fontSize: 11 }}>
                  {bgImgUploading ? "Uploading…" : "Upload from device"}
                </Text>
              </Pressable>
              {bgImageVal ? (
                <Image
                  source={{
                    uri: /^https?:\/\//i.test(bgImageVal)
                      ? bgImageVal
                      : `${getBaseUrl()}${bgImageVal}`,
                  }}
                  style={{ width: 96, height: 64, borderRadius: 8, borderWidth: 1, borderColor: colors.border }}
                  resizeMode="cover"
                />
              ) : null}
            </View>
          ) : null}
        </View>

        {bgMode === "preset" ? (
        <View style={{ gap: 8 }} testID="block-bg-preset-section">
          <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
            <Text style={[styles.rowLabel, { color: colors.foreground }]}>Background preset</Text>
            <Pressable {...WEB_FOCUS_RING_PROPS}
              testID="block-bg-preset-toggle"
              onPress={() => setBgPresetOpen((v) => !v)}
              style={{
                paddingHorizontal: 10,
                paddingVertical: 6,
                borderRadius: 999,
                borderWidth: 1,
                borderColor: colors.border,
                backgroundColor: colors.card,
              }}
            >
              <Text style={{ color: colors.foreground, fontWeight: "600", fontSize: 11 }}>
                {bgPresetOpen ? "Hide presets" : bgPresetKey ? "Change preset" : "Pick a preset"}
              </Text>
            </Pressable>
          </View>

          {bgPresetKey ? (() => {
            const cur = (bgPresetCatalogQ.data?.presets ?? []).find(
              (p) => p.key === bgPresetKey && !p.paper,
            );
            return (
              <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                <View style={{ width: 44, height: 44, borderRadius: 10, overflow: "hidden", borderWidth: 1, borderColor: colors.border }}>
                  {cur ? (
                    <LinearGradient
                      colors={
                        cur.colors.length >= 2
                          ? (cur.colors as [string, string, ...string[]])
                          : ([cur.colors[0] ?? "#3d3654", cur.colors[0] ?? "#3d3654"] as [string, string])
                      }
                      start={{ x: 0, y: 0 }}
                      end={{ x: 1, y: 1 }}
                      style={StyleSheet.absoluteFill}
                    />
                  ) : (
                    <View style={[StyleSheet.absoluteFill, { backgroundColor: colors.muted }]} />
                  )}
                  {cur?.swatch ? (
                    <Image
                      source={{ uri: `${getBaseUrl()}${cur.swatch}` }}
                      style={StyleSheet.absoluteFill}
                      resizeMode="cover"
                    />
                  ) : null}
                </View>
                <Text style={{ color: colors.mutedForeground, fontSize: 12, flex: 1 }} numberOfLines={1}>
                  {cur?.label ?? bgPresetKey}
                </Text>
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  testID="block-bg-preset-clear"
                  onPress={() => setBgPresetKey("")}
                  style={{
                    paddingHorizontal: 10,
                    paddingVertical: 6,
                    borderRadius: 999,
                    borderWidth: 1,
                    borderColor: colors.border,
                  }}
                >
                  <Text style={{ color: colors.destructive, fontWeight: "600", fontSize: 11 }}>Remove</Text>
                </Pressable>
              </View>
            );
          })() : null}

          {bgPresetKey ? (
            <View style={{ gap: 6 }}>
              <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                Transparency · {bgPresetOpacity}%
              </Text>
              <Slider
                testID="block-bg-preset-opacity-slider"
                style={{ width: "100%", height: 32 }}
                minimumValue={0}
                maximumValue={100}
                step={1}
                value={bgPresetOpacity}
                minimumTrackTintColor={colors.primary}
                maximumTrackTintColor={colors.border}
                thumbTintColor={colors.primary}
                onValueChange={(v) => setBgPresetOpacity(Math.round(v))}
              />
            </View>
          ) : null}

          {/* Live preview (Task #5984) — the block rendered through the same
              native renderer the public page uses, with the in-progress
              preset key + dragged opacity patched into `_style`. Because it
              reads the slider state directly, the background fades in real
              time while dragging; the saved value still lands in
              `_style.bg_preset_opacity` via the normal save path. */}
          {bgPresetKey && block ? (
            <View style={{ gap: 6 }}>
              <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                Live preview
              </Text>
              <View
                ref={bgPresetPreviewRef}
                testID="block-bg-preset-live-preview"
                pointerEvents="none"
                style={{
                  padding: 12,
                  borderRadius: 12,
                  borderWidth: 1,
                  borderStyle: "dashed",
                  borderColor: colors.border,
                }}
              >
                <StoreCartProvider alias={BLOCK_PREVIEW_ALIAS}>
                  <BlockView
                    block={{
                      ...block,
                      settings: {
                        ...(block.settings ?? {}),
                        _style: {
                          ...((block.settings?._style as Record<string, unknown> | undefined) ?? {}),
                          bg_preset_key: bgPresetKey,
                          bg_preset_opacity: clampNum(Math.round(bgPresetOpacity), 0, 100),
                        },
                      },
                    }}
                    alias={BLOCK_PREVIEW_ALIAS}
                    allBlocks={q.data ?? []}
                    openEmbed={NOOP_BLOCK_PREVIEW_EMBED}
                  />
                </StoreCartProvider>
              </View>
            </View>
          ) : null}

          {bgPresetOpen ? (
            bgPresetCatalogQ.isLoading ? (
              <ActivityIndicator color={colors.primary} />
            ) : (
              <View style={{ gap: 8 }}>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 6 }}>
                  {[{ key: "all", label: "All" }, ...(bgPresetCatalogQ.data?.groups ?? [])].map((g) => {
                    const sel = bgPresetGroup === g.key;
                    return (
                      <Pressable {...WEB_FOCUS_RING_PROPS}
                        key={g.key}
                        onPress={() => setBgPresetGroup(g.key)}
                        style={{
                          paddingHorizontal: 10,
                          paddingVertical: 5,
                          borderRadius: 999,
                          backgroundColor: sel ? colors.primary : colors.card,
                          borderWidth: 1,
                          borderColor: sel ? colors.primary : colors.border,
                        }}
                      >
                        <Text style={{ color: sel ? "#fff" : colors.foreground, fontWeight: "600", fontSize: 11 }}>
                          {g.label}
                        </Text>
                      </Pressable>
                    );
                  })}
                </ScrollView>
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
                  {(bgPresetCatalogQ.data?.presets ?? [])
                    .filter((p) => !p.paper)
                    .filter((p) => bgPresetGroup === "all" || p.group === bgPresetGroup)
                    .map((p) => {
                      const sel = bgPresetKey === p.key;
                      return (
                        <Pressable {...WEB_FOCUS_RING_PROPS}
                          key={p.key}
                          testID={`block-bg-preset-${p.key}`}
                          onPress={() => {
                            setBgPresetKey(sel ? "" : p.key);
                            // Task #5987 — make the change immediately
                            // visible: bring the live preview back into
                            // view when picking (not clearing) a preset.
                            if (!sel) scrollBgPresetPreviewIntoView();
                          }}
                          style={{
                            width: 56,
                            height: 56,
                            borderRadius: 12,
                            overflow: "hidden",
                            borderWidth: sel ? 3 : 1,
                            borderColor: sel ? colors.primary : colors.border,
                          }}
                        >
                          <LinearGradient
                            colors={
                              p.colors.length >= 2
                                ? (p.colors as [string, string, ...string[]])
                                : ([p.colors[0] ?? "#3d3654", p.colors[0] ?? "#3d3654"] as [string, string])
                            }
                            start={{ x: 0, y: 0 }}
                            end={{ x: 1, y: 1 }}
                            style={StyleSheet.absoluteFill}
                          />
                          {p.swatch ? (
                            <Image
                              source={{ uri: `${getBaseUrl()}${p.swatch}` }}
                              style={StyleSheet.absoluteFill}
                              resizeMode="cover"
                            />
                          ) : null}
                        </Pressable>
                      );
                    })}
                </View>
              </View>
            )
          ) : null}
        </View>
        ) : null}

        {/* Block Width (Task #6119) — per-device span chips mirroring the
            web Style tab: Mobile edits the base `grid_span`; Desktop edits
            the `grid_span_md` override, where "Same" clears it so large
            screens follow the mobile width. */}
        <View style={{ gap: 8 }} testID="block-width-section">
          <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
            <Text style={[styles.rowLabel, { color: colors.foreground }]}>Block Width</Text>
            <View style={{ flexDirection: "row", borderRadius: 999, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.card, padding: 2 }}>
              {(["mobile", "desktop"] as const).map((dev) => {
                const sel = widthDevice === dev;
                return (
                  <Pressable {...WEB_FOCUS_RING_PROPS}
                    key={dev}
                    testID={`block-width-device-${dev}`}
                    onPress={() => setWidthDevice(dev)}
                    style={{
                      paddingHorizontal: 10,
                      paddingVertical: 4,
                      borderRadius: 999,
                      backgroundColor: sel ? colors.primary : "transparent",
                    }}
                  >
                    <Text style={{ color: sel ? "#fff" : colors.mutedForeground, fontWeight: "600", fontSize: 11 }}>
                      {dev === "mobile" ? "Mobile" : "Desktop"}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </View>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
            {(widthDevice === "desktop"
              ? ([["", "Same"], ["3", "¼"], ["4", "⅓"], ["6", "½"], ["8", "⅔"], ["9", "¾"], ["12", "Full"]] as const)
              : ([["3", "¼"], ["4", "⅓"], ["6", "½"], ["8", "⅔"], ["9", "¾"], ["12", "Full"]] as const)
            ).map(([val, label]) => {
              const sel = widthDevice === "desktop" ? gridSpanMd === val : gridSpan === val;
              return (
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  key={`${widthDevice}-${val || "same"}`}
                  testID={`block-width-${widthDevice}-${val || "same"}`}
                  onPress={() =>
                    widthDevice === "desktop" ? setGridSpanMd(val) : setGridSpan(val)
                  }
                  style={{
                    paddingHorizontal: 14,
                    paddingVertical: 7,
                    borderRadius: 999,
                    backgroundColor: sel ? colors.primary : colors.card,
                    borderWidth: 1,
                    borderColor: sel ? colors.primary : colors.border,
                  }}
                >
                  <Text style={{ color: sel ? "#fff" : colors.foreground, fontWeight: "600", fontSize: 12 }}>
                    {label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
          <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
            {widthDevice === "mobile"
              ? "Width on phones — smaller widths place blocks side-by-side"
              : "Width on large screens — \u201cSame\u201d keeps the mobile width"}
          </Text>
        </View>

        {/* Block Height (Task #6123) — per-device row-span chips mirroring
            the web Style tab: Mobile edits the base `grid_row_span`
            ("Auto" clears it, natural height); Desktop edits the
            `grid_row_span_md` override, where "Same" clears it so large
            screens follow the mobile setting. */}
        <View style={{ gap: 8 }} testID="block-height-section">
          <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
            <Text style={[styles.rowLabel, { color: colors.foreground }]}>Block Height (Rows)</Text>
            <View style={{ flexDirection: "row", borderRadius: 999, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.card, padding: 2 }}>
              {(["mobile", "desktop"] as const).map((dev) => {
                const sel = heightDevice === dev;
                return (
                  <Pressable {...WEB_FOCUS_RING_PROPS}
                    key={dev}
                    testID={`block-height-device-${dev}`}
                    onPress={() => setHeightDevice(dev)}
                    style={{
                      paddingHorizontal: 10,
                      paddingVertical: 4,
                      borderRadius: 999,
                      backgroundColor: sel ? colors.primary : "transparent",
                    }}
                  >
                    <Text style={{ color: sel ? "#fff" : colors.mutedForeground, fontWeight: "600", fontSize: 11 }}>
                      {dev === "mobile" ? "Mobile" : "Desktop"}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </View>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
            {(heightDevice === "desktop"
              ? ([["", "Same"], ["1", "1"], ["2", "2"], ["3", "3"], ["4", "4"], ["5", "5"], ["6", "6"]] as const)
              : ([["", "Auto"], ["1", "1"], ["2", "2"], ["3", "3"], ["4", "4"], ["5", "5"], ["6", "6"]] as const)
            ).map(([val, label]) => {
              const sel = heightDevice === "desktop" ? gridRowSpanMd === val : gridRowSpan === val;
              return (
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  key={`${heightDevice}-${val || "unset"}`}
                  testID={`block-height-${heightDevice}-${val || "unset"}`}
                  onPress={() =>
                    heightDevice === "desktop" ? setGridRowSpanMd(val) : setGridRowSpan(val)
                  }
                  style={{
                    paddingHorizontal: 14,
                    paddingVertical: 7,
                    borderRadius: 999,
                    backgroundColor: sel ? colors.primary : colors.card,
                    borderWidth: 1,
                    borderColor: sel ? colors.primary : colors.border,
                  }}
                >
                  <Text style={{ color: sel ? "#fff" : colors.foreground, fontWeight: "600", fontSize: 12 }}>
                    {label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
          <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
            {heightDevice === "mobile"
              ? "Rows the block stretches across next to side-by-side blocks — \u201cAuto\u201d keeps natural height"
              : "Rows on large screens — \u201cSame\u201d keeps the mobile setting"}
          </Text>
        </View>

        {/* Borders (Task #6038) — shorthand border + corner radius with an
            Advanced expander exposing per-corner radii and per-side
            style/width/color, mirroring the web Style tab. Blank advanced
            fields fall back to the shorthand field-by-field. */}
        <View style={{ gap: 8 }} testID="block-borders-section">
          <Text style={[styles.rowLabel, { color: colors.foreground }]}>Borders</Text>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
            {(["none", "solid", "dashed", "dotted", "double"] as const).map((bs) => {
              const sel = bdStyle === bs;
              return (
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  key={bs}
                  testID={`block-border-style-${bs}`}
                  onPress={() => setBdStyle(bs)}
                  style={{
                    paddingHorizontal: 12,
                    paddingVertical: 6,
                    borderRadius: 999,
                    backgroundColor: sel ? colors.primary : colors.card,
                    borderWidth: 1,
                    borderColor: sel ? colors.primary : colors.border,
                  }}
                >
                  <Text style={{ color: sel ? "#fff" : colors.foreground, fontWeight: "600", fontSize: 11 }}>
                    {bs.charAt(0).toUpperCase() + bs.slice(1)}
                  </Text>
                </Pressable>
              );
            })}
          </View>
          <View style={{ flexDirection: "row", gap: 8 }}>
            <View style={{ flex: 1, gap: 4 }}>
              <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>Width (px)</Text>
              <TextInput
                testID="block-border-width-input"
                value={bdWidth}
                onChangeText={setBdWidth}
                placeholder="1"
                placeholderTextColor={colors.mutedForeground}
                keyboardType="numeric"
                style={[{ borderWidth: 1, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 8, fontSize: 13, color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card }]}
              />
            </View>
            <View style={{ flex: 1.4, gap: 4 }}>
              <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>Color</Text>
              <ColorSwatchRow
                prefix="block-border-color-swatch"
                value={bdColor}
                onPick={setBdColor}
              />
              <TextInput
                testID="block-border-color-input"
                value={bdColor}
                onChangeText={(v) => {
                  setBdColor(v);
                  rememberRecentColorFromTyping("block-border-color", v);
                }}
                onBlur={() => rememberBorderColor(bdColor)}
                placeholder="#ffffff"
                placeholderTextColor={colors.mutedForeground}
                autoCapitalize="none"
                autoCorrect={false}
                style={[{ borderWidth: 1, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 8, fontSize: 13, color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card }]}
              />
              <BorderColorSwatchRow
                value={bdColor}
                onSelect={setBdColor}
                testIDPrefix="block-border-color-swatch"
                chipBorderColor={colors.border}
              />
              {!isLikelyCssColor(bdColor) ? (
                <Text
                  testID="block-border-color-invalid"
                  style={{ color: "#f59e0b", fontSize: 10 }}
                >
                  This doesn't look like a valid color; check for typos (e.g. #ffffff).
                </Text>
              ) : null}
            </View>
            <View style={{ flex: 1, gap: 4 }}>
              <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>Radius (px)</Text>
              <TextInput
                testID="block-border-radius-input"
                value={bdRadius}
                onChangeText={setBdRadius}
                placeholder="12"
                placeholderTextColor={colors.mutedForeground}
                keyboardType="numeric"
                style={[{ borderWidth: 1, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 8, fontSize: 13, color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card }]}
              />
            </View>
          </View>
          <ColorSwatchRow
            prefix="block-border-color"
            value={bdColor}
            onPick={setBdColor}
            size={24}
          />

          {/* Border color quick-pick swatches: fixed presets plus the
              creator's recently used custom colors (Task #6094). Tapping a
              swatch fills the shorthand Color field above. */}
          <View
            testID="block-border-color-swatches"
            style={{ flexDirection: "row", flexWrap: "wrap", gap: 8, alignItems: "center" }}
          >
            {[
              ...BORDER_COLOR_SWATCHES.map((c) => ({ color: c, recent: false })),
              ...recentBorderColors
                .filter((c) => !BORDER_COLOR_SWATCHES.includes(c))
                .map((c) => ({ color: c, recent: true })),
            ].map(({ color: sw, recent }) => {
              const sel = bdColor.trim().toLowerCase() === sw;
              return (
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  key={`${recent ? "recent" : "preset"}-${sw}`}
                  testID={`block-border-color-quick-${sw.replace("#", "")}`}
                  accessibilityLabel={`${recent ? "Recent" : "Preset"} border color ${sw}`}
                  onPress={() => {
                    setBdColor(sw);
                    if (recent) rememberBorderColor(sw);
                  }}
                  onLongPress={recent ? () => confirmRemoveRecentBorderColor(sw) : undefined}
                  style={{
                    width: 26,
                    height: 26,
                    borderRadius: 13,
                    backgroundColor: sw,
                    borderWidth: sel ? 2 : 1,
                    borderColor: sel ? colors.primary : colors.border,
                  }}
                />
              );
            })}
          </View>
          {recentBorderColors.length > 0 ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 10 }}>
              Your recent custom colors appear at the end of the row.
            </Text>
          ) : null}

          <Pressable {...WEB_FOCUS_RING_PROPS}
            testID="block-borders-advanced-toggle"
            onPress={() => setBdAdvOpen((v) => !v)}
            style={{
              alignSelf: "flex-start",
              paddingHorizontal: 12,
              paddingVertical: 7,
              borderRadius: 999,
              borderWidth: 1,
              borderColor: colors.border,
              backgroundColor: colors.card,
            }}
          >
            <Text style={{ color: colors.foreground, fontWeight: "600", fontSize: 11 }}>
              {bdAdvOpen ? "Hide advanced" : "Advanced settings"}
            </Text>
          </Pressable>

          {bdAdvOpen ? (
            <View
              testID="block-borders-advanced-panel"
              style={{
                gap: 10,
                padding: 10,
                borderRadius: 12,
                borderWidth: 1,
                borderStyle: "dashed",
                borderColor: colors.border,
              }}
            >
              <Text style={{ color: colors.foreground, fontWeight: "700", fontSize: 12 }}>
                Corner radius
              </Text>
              <View style={{ flexDirection: "row", gap: 8 }}>
                {(
                  [
                    { key: "tl", label: "T-L" },
                    { key: "tr", label: "T-R" },
                    { key: "bl", label: "B-L" },
                    { key: "br", label: "B-R" },
                  ] as const
                ).map((c) => (
                  <View key={c.key} style={{ flex: 1, gap: 4 }}>
                    <Text style={{ color: colors.mutedForeground, fontSize: 10, fontWeight: "700" }}>
                      {c.label}
                    </Text>
                    <TextInput
                      testID={`block-border-radius-${c.key}`}
                      value={bdCorners[c.key]}
                      onChangeText={(v) => setBdCorners((prev) => ({ ...prev, [c.key]: v }))}
                      placeholder="-"
                      placeholderTextColor={colors.mutedForeground}
                      keyboardType="numeric"
                      style={[{ borderWidth: 1, borderRadius: 10, paddingHorizontal: 10, paddingVertical: 8, fontSize: 13, color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card }]}
                    />
                  </View>
                ))}
              </View>
              <Text style={{ color: colors.mutedForeground, fontSize: 10 }}>
                Blank corners use the radius above.
              </Text>

              <Text style={{ color: colors.foreground, fontWeight: "700", fontSize: 12 }}>
                Per-side borders
              </Text>
              {(
                [
                  { key: "top", label: "Top" },
                  { key: "right", label: "Right" },
                  { key: "bottom", label: "Bottom" },
                  { key: "left", label: "Left" },
                ] as const
              ).map((sd) => (
                <View key={sd.key} style={{ gap: 6 }}>
                  <Text style={{ color: colors.mutedForeground, fontSize: 10, fontWeight: "700" }}>
                    {sd.label}
                  </Text>
                  <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 4 }}>
                    {(["", "none", "solid", "dashed", "dotted", "double"] as const).map((bs) => {
                      const sel = bdSides[sd.key].style === bs;
                      return (
                        <Pressable {...WEB_FOCUS_RING_PROPS}
                          key={bs || "default"}
                          testID={`block-border-${sd.key}-style-${bs || "default"}`}
                          onPress={() =>
                            setBdSides((prev) => ({
                              ...prev,
                              [sd.key]: { ...prev[sd.key], style: bs },
                            }))
                          }
                          style={{
                            paddingHorizontal: 9,
                            paddingVertical: 4,
                            borderRadius: 999,
                            backgroundColor: sel ? colors.primary : colors.card,
                            borderWidth: 1,
                            borderColor: sel ? colors.primary : colors.border,
                          }}
                        >
                          <Text style={{ color: sel ? "#fff" : colors.foreground, fontWeight: "600", fontSize: 10 }}>
                            {bs === "" ? "Default" : bs.charAt(0).toUpperCase() + bs.slice(1)}
                          </Text>
                        </Pressable>
                      );
                    })}
                  </View>
                  <ColorSwatchRow
                    prefix={`block-border-${sd.key}-color-swatch`}
                    value={bdSides[sd.key].color}
                    onPick={(v) =>
                      setBdSides((prev) => ({
                        ...prev,
                        [sd.key]: { ...prev[sd.key], color: v },
                      }))
                    }
                  />
                  <View style={{ flexDirection: "row", gap: 8 }}>
                    <TextInput
                      testID={`block-border-${sd.key}-width`}
                      value={bdSides[sd.key].width}
                      onChangeText={(v) =>
                        setBdSides((prev) => ({
                          ...prev,
                          [sd.key]: { ...prev[sd.key], width: v },
                        }))
                      }
                      placeholder="Width"
                      placeholderTextColor={colors.mutedForeground}
                      keyboardType="numeric"
                      style={[{ flex: 1, borderWidth: 1, borderRadius: 10, paddingHorizontal: 10, paddingVertical: 8, fontSize: 13, color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card }]}
                    />
                    <TextInput
                      testID={`block-border-${sd.key}-color`}
                      value={bdSides[sd.key].color}
                      onChangeText={(v) => {
                        setBdSides((prev) => ({
                          ...prev,
                          [sd.key]: { ...prev[sd.key], color: v },
                        }));
                        rememberRecentColorFromTyping(`block-border-${sd.key}-color`, v);
                      }}
                      onBlur={() => rememberBorderColor(bdSides[sd.key].color)}
                      placeholder="#ffffff"
                      placeholderTextColor={colors.mutedForeground}
                      autoCapitalize="none"
                      autoCorrect={false}
                      style={[{ flex: 1.4, borderWidth: 1, borderRadius: 10, paddingHorizontal: 10, paddingVertical: 8, fontSize: 13, color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card }]}
                    />
                  </View>
                  <BorderColorSwatchRow
                    value={bdSides[sd.key].color}
                    onSelect={(c) =>
                      setBdSides((prev) => ({
                        ...prev,
                        [sd.key]: { ...prev[sd.key], color: c },
                      }))
                    }
                    testIDPrefix={`block-border-${sd.key}-color-swatch`}
                    chipBorderColor={colors.border}
                  />
                  {!isLikelyCssColor(bdSides[sd.key].color) ? (
                    <Text
                      testID={`block-border-${sd.key}-color-invalid`}
                      style={{ color: "#f59e0b", fontSize: 10 }}
                    >
                      This doesn't look like a valid color; check for typos (e.g. #ffffff).
                    </Text>
                  ) : null}
                </View>
              ))}
              <Text style={{ color: colors.mutedForeground, fontSize: 10 }}>
                Blank fields use the border settings above. Pick "None" to remove one side.
              </Text>
            </View>
          ) : null}
          {/* Instant live preview (Task #6074) — the same native renderer
              as the public page, with the in-progress border fields
              patched into `_style`. State-driven, so it updates the
              moment a field changes (no network round-trip / flash). */}
          {block && borderFieldsDirty ? (
            <View style={{ gap: 6 }}>
              <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>Live preview</Text>
              <View
                testID="block-borders-live-preview"
                pointerEvents="none"
                style={{
                  padding: 12,
                  borderRadius: 12,
                  borderWidth: 1,
                  borderStyle: "dashed",
                  borderColor: colors.border,
                }}
              >
                <StoreCartProvider alias={BLOCK_PREVIEW_ALIAS}>
                  <BlockView
                    block={{
                      ...block,
                      settings: {
                        ...(block.settings ?? {}),
                        _style: borderPreviewStyle,
                      },
                    }}
                    alias={BLOCK_PREVIEW_ALIAS}
                    allBlocks={q.data ?? []}
                    openEmbed={NOOP_BLOCK_PREVIEW_EMBED}
                  />
                </StoreCartProvider>
              </View>
            </View>
          ) : null}
        </View>

        {isAnyList ? (
          <View style={{ gap: 12 }}>
            {/* Live preview — reflects the current style + items + icons
                so creators can confirm how the block will look on the
                public page before saving. Mirrors the public renderer's
                structural treatment for each variant. */}
            <View style={{ gap: 6 }}>
              <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                Preview
              </Text>
              <View
                style={{
                  padding: 12,
                  borderRadius: 12,
                  borderWidth: 1,
                  borderStyle: "dashed",
                  borderColor: colors.border,
                  backgroundColor: colors.muted,
                }}
              >
                {isPricing ? (
                  visiblePricingItems(pricingItems).length === 0 ? (
                    <Text style={{ color: colors.mutedForeground, fontSize: 11, fontStyle: "italic" }}>
                      Add a pricing row to see the preview.
                    </Text>
                  ) : (
                    <PricingBlockView
                      styleKey={listStyle}
                      items={pricingItems}
                      colors={colors}
                    />
                  )
                ) : visibleListItems(listItems).length === 0 ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 11, fontStyle: "italic" }}>
                    Add an item to see the preview.
                  </Text>
                ) : (
                  <ListBlockView
                    kind={isListNumbered ? "numbered" : "list"}
                    styleKey={listStyle}
                    defaultIcon={defaultBulletIcon}
                    items={listItems}
                    colors={colors}
                  />
                )}
              </View>
            </View>

            {/* Style picker — radio cards mirroring the web editor's
                style grid. We render labels (and a one-line description
                for pricing) rather than icons because the mobile bundle
                doesn't ship Font Awesome glyphs. */}
            <View style={{ gap: 8 }}>
              <Text style={[styles.rowLabel, { color: colors.foreground }]}>Style</Text>
              <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
                {(isList
                  ? LIST_STYLES
                  : isListNumbered
                    ? LIST_NUMBERED_STYLES
                    : PRICING_STYLES
                ).map((s) => {
                  const selected = listStyle === s.key;
                  return (
                    <Pressable {...WEB_FOCUS_RING_PROPS}
                      key={s.key}
                      onPress={() => setListStyle(s.key)}
                      style={{
                        flexBasis: isPricing ? "48%" : "31%",
                        flexGrow: 1,
                        padding: 10,
                        borderRadius: 12,
                        backgroundColor: selected ? colors.primary + "22" : colors.card,
                        borderWidth: selected ? 2 : 1,
                        borderColor: selected ? colors.primary : colors.border,
                      }}
                    >
                      <Text
                        style={{
                          color: colors.foreground,
                          fontWeight: "700",
                          fontSize: 12,
                        }}
                      >
                        {s.label}
                      </Text>
                      {s.desc ? (
                        <Text
                          style={{
                            color: colors.mutedForeground,
                            fontSize: 10,
                            marginTop: 2,
                          }}
                        >
                          {s.desc}
                        </Text>
                      ) : null}
                    </Pressable>
                  );
                })}
              </View>
            </View>

            {isList ? (
              <View style={{ gap: 6 }}>
                <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                  Default bullet icon
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                  Used when an item below has no icon picked.
                </Text>
                <IconPickerButton
                  value={defaultBulletIcon}
                  onPress={() => setIconPickerTarget({ kind: "default" })}
                  placeholder="Browse icons..."
                />
              </View>
            ) : null}

            <View style={{ gap: 8 }}>
              <Text style={[styles.rowLabel, { color: colors.foreground }]}>Items</Text>

              {(isList || isListNumbered) && (
                <DraggableRepeaterRows
                  items={listItems}
                  gap={8}
                  handleColor={colors.mutedForeground}
                  onReorder={(perm) =>
                    setListItems((prev) => perm.map((i) => prev[i]))
                  }
                  renderRow={(it, idx, dragHandle) => (
                  <View
                    style={{
                      padding: 10,
                      borderRadius: 12,
                      backgroundColor: colors.card,
                      borderWidth: 1,
                      borderColor: colors.border,
                      gap: 8,
                    }}
                  >
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                      {dragHandle}
                      <Text style={{ color: colors.mutedForeground, fontSize: 12, width: 22 }}>
                        {isListNumbered ? `${idx + 1}.` : "•"}
                      </Text>
                      <View style={{ flex: 1 }}>
                        <TextField
                          value={it.text}
                          placeholder="Item text"
                          onChangeText={(t) =>
                            setListItems((prev) =>
                              prev.map((p, i) => (i === idx ? { ...p, text: t } : p)),
                            )
                          }
                          trailing={
                            <DictationMic
                              onText={(t) =>
                                setListItems((prev) =>
                                  prev.map((p, i) =>
                                    i === idx
                                      ? {
                                          ...p,
                                          text: p.text ? p.text.trim() + " " + t : t,
                                        }
                                      : p,
                                  ),
                                )
                              }
                            />
                          }
                        />
                      </View>
                      <Pressable
                        {...WEB_FOCUS_RING_PROPS}
                        disabled={idx === 0}
                        onPress={() =>
                          setListItems((prev) => {
                            if (idx <= 0) return prev;
                            const next = [...prev];
                            [next[idx - 1], next[idx]] = [next[idx], next[idx - 1]];
                            return next;
                          })
                        }
                        accessibilityRole="button"
                        accessibilityLabel={`Move item ${idx + 1} up`}
                        hitSlop={8}
                        style={{ padding: 4, opacity: idx === 0 ? 0.3 : 1 }}
                        testID={`list-item-up-${idx}`}
                      >
                        <Feather
                          name="arrow-up"
                          size={15}
                          color={idx === 0 ? colors.mutedForeground : colors.primary}
                        />
                      </Pressable>
                      <Pressable
                        {...WEB_FOCUS_RING_PROPS}
                        disabled={idx === listItems.length - 1}
                        onPress={() =>
                          setListItems((prev) => {
                            if (idx >= prev.length - 1) return prev;
                            const next = [...prev];
                            [next[idx], next[idx + 1]] = [next[idx + 1], next[idx]];
                            return next;
                          })
                        }
                        accessibilityRole="button"
                        accessibilityLabel={`Move item ${idx + 1} down`}
                        hitSlop={8}
                        style={{
                          padding: 4,
                          opacity: idx === listItems.length - 1 ? 0.3 : 1,
                        }}
                        testID={`list-item-down-${idx}`}
                      >
                        <Feather
                          name="arrow-down"
                          size={15}
                          color={
                            idx === listItems.length - 1
                              ? colors.mutedForeground
                              : colors.primary
                          }
                        />
                      </Pressable>
                      <Pressable {...WEB_FOCUS_RING_PROPS}
                        onPress={() =>
                          setListItems((prev) => prev.filter((_, i) => i !== idx))
                        }
                        hitSlop={8}
                        style={{ padding: 4 }}
                      >
                        <Feather name="trash-2" size={16} color={colors.destructive} />
                      </Pressable>
                    </View>

                    {isList ? (
                      <IconPickerButton
                        value={it.icon}
                        onPress={() =>
                          setIconPickerTarget({ kind: "list", index: idx })
                        }
                        placeholder="Browse icons (uses default if empty)"
                      />
                    ) : null}
                  </View>
                  )}
                />
              )}

              {isPricing && (
                <DraggableRepeaterRows
                  items={pricingItems}
                  gap={8}
                  handleColor={colors.mutedForeground}
                  onReorder={(perm) =>
                    setPricingItems((prev) => perm.map((i) => prev[i]))
                  }
                  renderRow={(it, idx, dragHandle) => (
                  <View
                    style={{
                      padding: 10,
                      borderRadius: 12,
                      backgroundColor: colors.card,
                      borderWidth: 1,
                      borderColor: colors.border,
                      gap: 8,
                    }}
                  >
                    <TextField
                      label="Name"
                      value={it.name}
                      placeholder="Plan / item name"
                      onChangeText={(t) =>
                        setPricingItems((prev) =>
                          prev.map((p, i) => (i === idx ? { ...p, name: t } : p)),
                        )
                      }
                      trailing={
                        <DictationMic
                          onText={(t) =>
                            setPricingItems((prev) =>
                              prev.map((p, i) =>
                                i === idx
                                  ? {
                                      ...p,
                                      name: p.name ? p.name.trim() + " " + t : t,
                                    }
                                  : p,
                              ),
                            )
                          }
                        />
                      }
                    />
                    <View style={{ flexDirection: "row", gap: 8 }}>
                      <View style={{ flex: 1 }}>
                        <TextField
                          label="Price"
                          value={it.price}
                          placeholder="$29"
                          onChangeText={(t) =>
                            setPricingItems((prev) =>
                              prev.map((p, i) => (i === idx ? { ...p, price: t } : p)),
                            )
                          }
                        />
                      </View>
                      <View style={{ width: 110 }}>
                        <TextField
                          label="Period"
                          value={it.period}
                          placeholder="/mo"
                          onChangeText={(t) =>
                            setPricingItems((prev) =>
                              prev.map((p, i) => (i === idx ? { ...p, period: t } : p)),
                            )
                          }
                        />
                      </View>
                    </View>
                    <TextField
                      label="Description"
                      hint="Used by Menu, Cards, Featured styles"
                      value={it.description}
                      multiline
                      numberOfLines={2}
                      onChangeText={(t) =>
                        setPricingItems((prev) =>
                          prev.map((p, i) => (i === idx ? { ...p, description: t } : p)),
                        )
                      }
                      style={{ minHeight: 60, paddingTop: 12, textAlignVertical: "top" }}
                      trailing={
                        <DictationMic
                          onText={(t) =>
                            setPricingItems((prev) =>
                              prev.map((p, i) =>
                                i === idx
                                  ? {
                                      ...p,
                                      description: p.description
                                        ? p.description.trim() + " " + t
                                        : t,
                                    }
                                  : p,
                              ),
                            )
                          }
                        />
                      }
                    />
                    <View style={{ flexDirection: "row", gap: 8 }}>
                      <View style={{ flex: 1, flexDirection: "row", gap: 8, alignItems: "flex-end" }}>
                        <View style={{ flex: 1 }}>
                          <TextField
                            label="Thumbnail URL"
                            value={it.thumbnail}
                            autoCapitalize="none"
                            keyboardType="url"
                            onChangeText={(t) =>
                              setPricingItems((prev) =>
                                prev.map((p, i) => (i === idx ? { ...p, thumbnail: t } : p)),
                              )
                            }
                          />
                        </View>
                        <PricingThumbnailPreview
                          uri={it.thumbnail}
                          borderColor={colors.border}
                          mutedColor={colors.muted}
                        />
                      </View>
                      <View style={{ flex: 1, gap: 4 }}>
                        <Text
                          style={{
                            color: colors.mutedForeground,
                            fontSize: 11,
                            fontFamily: "SpaceGrotesk_600SemiBold",
                          }}
                        >
                          Icon
                        </Text>
                        <IconPickerButton
                          value={it.icon}
                          onPress={() =>
                            setIconPickerTarget({ kind: "pricing", index: idx })
                          }
                          placeholder="Browse icons..."
                        />
                      </View>
                    </View>
                    <View
                      style={{
                        flexDirection: "row",
                        alignItems: "center",
                        justifyContent: "space-between",
                        paddingVertical: 4,
                      }}
                    >
                      <Text style={{ color: colors.foreground, fontSize: 12, fontWeight: "600" }}>
                        Included
                      </Text>
                      <Switch
                        value={it.included}
                        onValueChange={(v) =>
                          setPricingItems((prev) =>
                            prev.map((p, i) => (i === idx ? { ...p, included: v } : p)),
                          )
                        }
                        trackColor={{ true: colors.primary, false: colors.border }}
                      />
                    </View>
                    <View
                      style={{
                        flexDirection: "row",
                        alignItems: "center",
                        justifyContent: "space-between",
                        paddingVertical: 4,
                      }}
                    >
                      <Text style={{ color: colors.foreground, fontSize: 12, fontWeight: "600" }}>
                        ★ Featured
                      </Text>
                      <Switch
                        value={it.featured}
                        onValueChange={(v) =>
                          setPricingItems((prev) =>
                            prev.map((p, i) => (i === idx ? { ...p, featured: v } : p)),
                          )
                        }
                        trackColor={{ true: colors.primary, false: colors.border }}
                      />
                    </View>
                    <View
                      style={{
                        flexDirection: "row",
                        alignItems: "center",
                        justifyContent: "flex-end",
                        gap: 4,
                      }}
                    >
                      {dragHandle}
                      <View style={{ flex: 1 }} />
                      <Pressable
                        {...WEB_FOCUS_RING_PROPS}
                        disabled={idx === 0}
                        onPress={() =>
                          setPricingItems((prev) => {
                            if (idx <= 0) return prev;
                            const next = [...prev];
                            [next[idx - 1], next[idx]] = [next[idx], next[idx - 1]];
                            return next;
                          })
                        }
                        accessibilityRole="button"
                        accessibilityLabel={`Move row ${idx + 1} up`}
                        style={{ padding: 6, opacity: idx === 0 ? 0.3 : 1 }}
                        testID={`pricing-item-up-${idx}`}
                      >
                        <Feather
                          name="arrow-up"
                          size={15}
                          color={idx === 0 ? colors.mutedForeground : colors.primary}
                        />
                      </Pressable>
                      <Pressable
                        {...WEB_FOCUS_RING_PROPS}
                        disabled={idx === pricingItems.length - 1}
                        onPress={() =>
                          setPricingItems((prev) => {
                            if (idx >= prev.length - 1) return prev;
                            const next = [...prev];
                            [next[idx], next[idx + 1]] = [next[idx + 1], next[idx]];
                            return next;
                          })
                        }
                        accessibilityRole="button"
                        accessibilityLabel={`Move row ${idx + 1} down`}
                        style={{
                          padding: 6,
                          opacity: idx === pricingItems.length - 1 ? 0.3 : 1,
                        }}
                        testID={`pricing-item-down-${idx}`}
                      >
                        <Feather
                          name="arrow-down"
                          size={15}
                          color={
                            idx === pricingItems.length - 1
                              ? colors.mutedForeground
                              : colors.primary
                          }
                        />
                      </Pressable>
                      <Pressable {...WEB_FOCUS_RING_PROPS}
                        onPress={() =>
                          setPricingItems((prev) => prev.filter((_, i) => i !== idx))
                        }
                        style={{
                          paddingHorizontal: 10,
                          paddingVertical: 6,
                          borderRadius: 8,
                          flexDirection: "row",
                          alignItems: "center",
                          gap: 6,
                        }}
                      >
                        <Feather name="trash-2" size={14} color={colors.destructive} />
                        <Text style={{ color: colors.destructive, fontSize: 12, fontWeight: "600" }}>
                          Remove
                        </Text>
                      </Pressable>
                    </View>
                  </View>
                  )}
                />
              )}

              <Pressable {...WEB_FOCUS_RING_PROPS}
                onPress={() => {
                  if (isPricing) {
                    setPricingItems((prev) => [...prev, emptyPricingItem()]);
                  } else {
                    setListItems((prev) => [...prev, { text: "", icon: "" }]);
                  }
                }}
                style={{
                  padding: 10,
                  borderRadius: 10,
                  borderWidth: 1,
                  borderStyle: "dashed",
                  borderColor: colors.primary,
                  alignItems: "center",
                  flexDirection: "row",
                  justifyContent: "center",
                  gap: 6,
                }}
              >
                <Feather name="plus" size={14} color={colors.primary} />
                <Text style={{ color: colors.primary, fontSize: 12, fontWeight: "700" }}>
                  Add item
                </Text>
              </Pressable>
            </View>
          </View>
        ) : null}

        {isMapLocation ? (
          <View style={{ gap: 12 }}>
            <TextField
              label="Address"
              value={values.address ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, address: t }))}
              placeholder="123 Main St, City"
            />
            <View style={{ gap: 6 }}>
              <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                Pin location
              </Text>
              <Pressable {...WEB_FOCUS_RING_PROPS}
                onPress={() => setMapPickerOpen(true)}
                style={[styles.mapPickerBtn, { borderColor: colors.border, borderRadius: colors.radius }]}
              >
                <Feather name="map-pin" size={15} color={colors.primary} />
                <Text style={[styles.mapPickerBtnText, { color: colors.primary }]}>
                  {values.lat && values.lng ? "Change point on map" : "Pick a point on the map"}
                </Text>
              </Pressable>
              {values.lat && values.lng ? (
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  onPress={() =>
                    setValues((p) => ({ ...p, lat: "", lng: "" }))
                  }
                  hitSlop={8}
                >
                  <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                    Clear pinned coordinates
                  </Text>
                </Pressable>
              ) : null}
            </View>
            <View style={{ flexDirection: "row", gap: 10 }}>
              <View style={{ flex: 1 }}>
                <TextField
                  label="Latitude (optional)"
                  value={values.lat ?? ""}
                  onChangeText={(t) => setValues((p) => ({ ...p, lat: t }))}
                  placeholder="37.7749"
                  keyboardType="numeric"
                />
              </View>
              <View style={{ flex: 1 }}>
                <TextField
                  label="Longitude (optional)"
                  value={values.lng ?? ""}
                  onChangeText={(t) => setValues((p) => ({ ...p, lng: t }))}
                  placeholder="-122.4194"
                  keyboardType="numeric"
                />
              </View>
            </View>
            <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: -6 }}>
              If both latitude and longitude are set they take precedence over the address.
            </Text>
            <TextField
              label="Display label (optional)"
              value={values.label ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, label: t }))}
            />
            <TextField
              label="Zoom"
              value={values.zoom ?? "15"}
              onChangeText={(t) => setValues((p) => ({ ...p, zoom: t }))}
              keyboardType="numeric"
            />
            <View style={styles.row}>
              <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                Show "Directions" button
              </Text>
              <Switch
                value={mapShowDirections}
                onValueChange={setMapShowDirections}
                trackColor={{ true: colors.primary }}
              />
            </View>
          </View>
        ) : null}

        {isImageBlock ? (
          <StockImageGalleryPicker
            label="Stock images"
            hint="Use a curated photo or hand-drawn graphic"
            selectedUrl={linkUrl.trim()}
            onSelect={(url) => setLinkUrl(url)}
            testIDPrefix="image-stock-gallery"
          />
        ) : null}

        {isImageBlock ? (
          <View style={{ gap: 12 }}>
            <Text style={[styles.rowLabel, { color: colors.foreground }]}>
              Photo stickers
            </Text>
            <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
              {photoStickers.length > 0
                ? "Drag a sticker to reposition it: it snaps to the nearest corner or edge with a fine offset, exactly like the web editor."
                : "Layer up to 4 of your own sticker images (PNG or WebP with transparency work best) over the photo."}
            </Text>
            {photoStickers.length > 0 ? (
            <View
              onLayout={(e) => setStickerStageW(e.nativeEvent.layout.width)}
              style={{
                width: "100%",
                aspectRatio: stickerStageRatio,
                borderRadius: 12,
                borderWidth: 1,
                borderColor: colors.border,
                backgroundColor: colors.muted,
                overflow: "hidden",
              }}
            >
              {stickerImageUri ? (
                <Image
                  source={{ uri: stickerImageUri }}
                  style={{ width: "100%", height: "100%" }}
                  resizeMode="cover"
                />
              ) : null}
              {stickerStageW > 0
                ? photoStickers.map((stk, idx) => (
                    <DraggableSticker
                      key={`${stk.file_id}-${idx}`}
                      sticker={stk}
                      stageW={stickerStageW}
                      stageH={stickerStageW / stickerStageRatio}
                      accentColor={colors.primary}
                      onPlace={(p) =>
                        setPhotoStickers((prev) =>
                          prev.map((s, i) =>
                            i === idx
                              ? { ...s, pos: p.pos, dx: p.dx, dy: p.dy }
                              : s,
                          ),
                        )
                      }
                    />
                  ))
                : null}
            </View>
            ) : null}
            {photoStickers.map((stk, idx) => (
              <View
                key={`sticker-row-${stk.file_id}-${idx}`}
                style={{
                  gap: 8,
                  padding: 10,
                  borderRadius: 10,
                  borderWidth: 1,
                  borderColor: colors.border,
                }}
              >
                <View
                  style={{
                    flexDirection: "row",
                    alignItems: "center",
                    justifyContent: "space-between",
                  }}
                >
                  <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                    <Image
                      source={{ uri: photoStickerImageUri(stk.url) }}
                      style={{ width: 28, height: 28 }}
                      resizeMode="contain"
                    />
                    <Text style={{ color: colors.foreground, fontSize: 12, fontWeight: "600" }}>
                      {PHOTO_STICKER_POSITION_LABELS[stk.pos] ?? stk.pos}
                      {stk.dx !== 0 || stk.dy !== 0
                        ? `  (${stk.dx >= 0 ? "+" : ""}${stk.dx}, ${stk.dy >= 0 ? "+" : ""}${stk.dy})`
                        : ""}
                    </Text>
                  </View>
                  <Pressable
                    {...WEB_FOCUS_RING_PROPS}
                    onPress={() =>
                      setPhotoStickers((prev) => prev.filter((_, i) => i !== idx))
                    }
                    hitSlop={8}
                    style={{ flexDirection: "row", alignItems: "center", gap: 4 }}
                  >
                    <Feather name="trash-2" size={13} color={colors.destructive} />
                    <Text style={{ color: colors.destructive, fontSize: 11, fontWeight: "600" }}>
                      Remove
                    </Text>
                  </Pressable>
                </View>
                <View style={{ flexDirection: "row", gap: 10 }}>
                  <View style={{ flex: 1 }}>
                    <TextField
                      label="Size (24–160)"
                      value={String(stk.size)}
                      onChangeText={(t) => {
                        const n = parseInt(t.trim(), 10);
                        setPhotoStickers((prev) =>
                          prev.map((s, i) =>
                            i === idx
                              ? {
                                  ...s,
                                  size: Number.isFinite(n)
                                    ? clampNum(n, 24, 160)
                                    : 48,
                                }
                              : s,
                          ),
                        );
                      }}
                      keyboardType="numeric"
                    />
                  </View>
                  <View style={{ flex: 1 }}>
                    <TextField
                      label="Rotate (±180°)"
                      value={String(stk.rotate)}
                      onChangeText={(t) => {
                        const n = parseInt(t.trim(), 10);
                        setPhotoStickers((prev) =>
                          prev.map((s, i) =>
                            i === idx
                              ? {
                                  ...s,
                                  rotate: Number.isFinite(n)
                                    ? clampNum(n, -180, 180)
                                    : 0,
                                }
                              : s,
                          ),
                        );
                      }}
                      keyboardType="numeric"
                    />
                  </View>
                </View>
              </View>
            ))}
            {photoStickers.length < 4 ? (
              <View style={{ gap: 8 }}>
                <View style={{ flexDirection: "row", gap: 8 }}>
                  <Pressable
                    {...WEB_FOCUS_RING_PROPS}
                    onPress={addStickerFromDevice}
                    disabled={stickerUploading}
                    style={{
                      flex: 1,
                      flexDirection: "row",
                      alignItems: "center",
                      justifyContent: "center",
                      gap: 6,
                      paddingVertical: 10,
                      borderRadius: 10,
                      borderWidth: 1,
                      borderColor: colors.border,
                      opacity: stickerUploading ? 0.6 : 1,
                    }}
                    accessibilityRole="button"
                    accessibilityLabel="Add sticker from device"
                  >
                    {stickerUploading ? (
                      <ActivityIndicator size="small" color={colors.primary} />
                    ) : (
                      <Feather name="upload" size={14} color={colors.primary} />
                    )}
                    <Text
                      style={{
                        color: colors.primary,
                        fontSize: 12,
                        fontWeight: "600",
                      }}
                    >
                      {stickerUploading ? "Uploading…" : "Add sticker"}
                    </Text>
                  </Pressable>
                  <Pressable
                    {...WEB_FOCUS_RING_PROPS}
                    onPress={openStickerVaultPicker}
                    style={{
                      flex: 1,
                      flexDirection: "row",
                      alignItems: "center",
                      justifyContent: "center",
                      gap: 6,
                      paddingVertical: 10,
                      borderRadius: 10,
                      borderWidth: 1,
                      borderColor: stickerPickerOpen
                        ? colors.primary
                        : colors.border,
                    }}
                    accessibilityRole="button"
                    accessibilityLabel="Pick sticker from my files"
                  >
                    <Feather name="folder" size={14} color={colors.primary} />
                    <Text
                      style={{
                        color: colors.primary,
                        fontSize: 12,
                        fontWeight: "600",
                      }}
                    >
                      From my files
                    </Text>
                  </Pressable>
                </View>
                {stickerPickerOpen ? (
                  <View
                    style={{
                      borderRadius: 10,
                      borderWidth: 1,
                      borderColor: colors.border,
                      padding: 10,
                      gap: 8,
                    }}
                  >
                    <TextInput
                      value={stickerVaultQuery}
                      onChangeText={setStickerVaultQuery}
                      placeholder="Search your files by name…"
                      placeholderTextColor={colors.mutedForeground}
                      autoCapitalize="none"
                      autoCorrect={false}
                      style={{
                        borderWidth: 1,
                        borderColor: colors.border,
                        borderRadius: 8,
                        paddingHorizontal: 10,
                        paddingVertical: 8,
                        fontSize: 12,
                        color: colors.foreground,
                      }}
                      accessibilityLabel="Search your files by name"
                    />
                    {stickerVaultLoading ? (
                      <ActivityIndicator size="small" color={colors.primary} />
                    ) : stickerVaultFiles && stickerVaultFiles.length > 0 ? (
                      <View
                        style={{
                          flexDirection: "row",
                          flexWrap: "wrap",
                          gap: 8,
                        }}
                      >
                        {stickerVaultFiles.map((f) => (
                          <Pressable
                            key={`vault-file-${f.id}`}
                            {...WEB_FOCUS_RING_PROPS}
                            onPress={() => appendSticker(f)}
                            style={{
                              width: 56,
                              height: 56,
                              borderRadius: 8,
                              borderWidth: 1,
                              borderColor: colors.border,
                              backgroundColor: colors.muted,
                              overflow: "hidden",
                            }}
                            accessibilityRole="button"
                            accessibilityLabel={`Use ${f.original_name} as sticker`}
                          >
                            <Image
                              source={{ uri: photoStickerImageUri(f.url_path || f.url) }}
                              style={{ width: "100%", height: "100%" }}
                              resizeMode="contain"
                            />
                          </Pressable>
                        ))}
                      </View>
                    ) : (
                      <Text
                        style={{ color: colors.mutedForeground, fontSize: 11 }}
                      >
                        {stickerVaultQuery.trim()
                          ? "No images match that name."
                          : "No images in your files yet: upload one from your device instead."}
                      </Text>
                    )}
                    {!stickerVaultLoading &&
                    stickerVaultFiles &&
                    stickerVaultFiles.length > 0 &&
                    stickerVaultPage < stickerVaultLastPage ? (
                      <Pressable
                        {...WEB_FOCUS_RING_PROPS}
                        onPress={loadMoreStickerVault}
                        disabled={stickerVaultLoadingMore}
                        style={{
                          flexDirection: "row",
                          alignItems: "center",
                          justifyContent: "center",
                          gap: 6,
                          paddingVertical: 8,
                          borderRadius: 8,
                          borderWidth: 1,
                          borderColor: colors.border,
                          opacity: stickerVaultLoadingMore ? 0.6 : 1,
                        }}
                        accessibilityRole="button"
                        accessibilityLabel="Load more files"
                      >
                        {stickerVaultLoadingMore ? (
                          <ActivityIndicator
                            size="small"
                            color={colors.primary}
                          />
                        ) : (
                          <Feather
                            name="chevron-down"
                            size={14}
                            color={colors.primary}
                          />
                        )}
                        <Text
                          style={{
                            color: colors.primary,
                            fontSize: 12,
                            fontWeight: "600",
                          }}
                        >
                          {stickerVaultLoadingMore ? "Loading…" : "Load more"}
                        </Text>
                      </Pressable>
                    ) : null}
                  </View>
                ) : null}
                <StockImageGalleryPicker
                  label={stockStickerBusy ? "Adding sticker…" : "Stock stickers"}
                  hint="Pick a curated hand-drawn graphic"
                  folders={[{ folder: "hand-drawn", label: "Hand-drawn" }]}
                  busy={stockStickerBusy}
                  onSelect={(_url, asset) => void addStickerFromStock(asset.key)}
                  testIDPrefix="sticker-stock-gallery"
                />
              </View>
            ) : (
              <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                Sticker limit reached (4 max): remove one to add another.
              </Text>
            )}
          </View>
        ) : null}

        {isGalleryBlock ? (
          <View style={{ gap: 12 }}>
            <Text style={[styles.rowLabel, { color: colors.foreground }]}>
              Images
            </Text>
            <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
              Add image URLs or pick from the curated stock gallery below.
              Rows without a URL are dropped on save.
            </Text>
            <DraggableRepeaterRows
              items={galleryImages}
              gap={12}
              handleColor={colors.mutedForeground}
              onReorder={(perm) =>
                setGalleryImages((prev) => perm.map((i) => prev[i]))
              }
              renderRow={(img, idx, dragHandle) => (
              <View
                style={{
                  gap: 8,
                  padding: 10,
                  borderWidth: 1,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                }}
              >
                <View
                  style={{ flexDirection: "row", alignItems: "center", gap: 8 }}
                >
                  {dragHandle}
                  {img.url.trim() ? (
                    <Image
                      source={{ uri: img.url.trim() }}
                      style={{
                        width: 44,
                        height: 44,
                        borderRadius: 8,
                        backgroundColor: colors.muted,
                      }}
                      resizeMode="cover"
                    />
                  ) : (
                    <View
                      style={{
                        width: 44,
                        height: 44,
                        borderRadius: 8,
                        backgroundColor: colors.muted,
                        alignItems: "center",
                        justifyContent: "center",
                      }}
                    >
                      <Feather
                        name="image"
                        size={16}
                        color={colors.mutedForeground}
                      />
                    </View>
                  )}
                  <Text
                    style={{
                      flex: 1,
                      color: colors.mutedForeground,
                      fontSize: 11,
                    }}
                    numberOfLines={1}
                  >
                    {img.url.trim() || "No image yet"}
                  </Text>
                  <Pressable
                    {...WEB_FOCUS_RING_PROPS}
                    disabled={idx === 0}
                    onPress={() =>
                      setGalleryImages((prev) => {
                        if (idx <= 0) return prev;
                        const next = [...prev];
                        [next[idx - 1], next[idx]] = [next[idx], next[idx - 1]];
                        return next;
                      })
                    }
                    accessibilityRole="button"
                    accessibilityLabel={`Move image ${idx + 1} up`}
                    style={{ padding: 6, opacity: idx === 0 ? 0.3 : 1 }}
                    testID={`gallery-img-up-${idx}`}
                  >
                    <Feather
                      name="arrow-up"
                      size={15}
                      color={idx === 0 ? colors.mutedForeground : colors.primary}
                    />
                  </Pressable>
                  <Pressable
                    {...WEB_FOCUS_RING_PROPS}
                    disabled={idx === galleryImages.length - 1}
                    onPress={() =>
                      setGalleryImages((prev) => {
                        if (idx >= prev.length - 1) return prev;
                        const next = [...prev];
                        [next[idx], next[idx + 1]] = [next[idx + 1], next[idx]];
                        return next;
                      })
                    }
                    accessibilityRole="button"
                    accessibilityLabel={`Move image ${idx + 1} down`}
                    style={{
                      padding: 6,
                      opacity: idx === galleryImages.length - 1 ? 0.3 : 1,
                    }}
                    testID={`gallery-img-down-${idx}`}
                  >
                    <Feather
                      name="arrow-down"
                      size={15}
                      color={
                        idx === galleryImages.length - 1
                          ? colors.mutedForeground
                          : colors.primary
                      }
                    />
                  </Pressable>
                  <Pressable
                    {...WEB_FOCUS_RING_PROPS}
                    onPress={() =>
                      setGalleryImages((prev) =>
                        prev.filter((_, i) => i !== idx),
                      )
                    }
                    accessibilityRole="button"
                    accessibilityLabel={`Remove image ${idx + 1}`}
                    style={{ padding: 6 }}
                  >
                    <Feather name="trash-2" size={15} color={colors.destructive} />
                  </Pressable>
                </View>
                <TextField
                  label="Image URL"
                  value={img.url}
                  onChangeText={(t) =>
                    setGalleryImages((prev) =>
                      prev.map((r, i) => (i === idx ? { ...r, url: t } : r)),
                    )
                  }
                  keyboardType="url"
                  autoCapitalize="none"
                />
                <TextField
                  label="Alt text"
                  value={img.alt}
                  onChangeText={(t) =>
                    setGalleryImages((prev) =>
                      prev.map((r, i) => (i === idx ? { ...r, alt: t } : r)),
                    )
                  }
                />
              </View>
              )}
            />
            <Button
              label="Add image"
              variant="ghost"
              onPress={() =>
                setGalleryImages((prev) => [...prev, { url: "", alt: "" }])
              }
            />
            <StockImageGalleryPicker
              label="Stock images"
              hint="Tap a curated image to add it to this gallery"
              onSelect={(url, asset) =>
                setGalleryImages((prev) => {
                  // Fill the first empty row if one exists, else append.
                  const emptyIdx = prev.findIndex((r) => r.url.trim() === "");
                  if (emptyIdx !== -1) {
                    return prev.map((r, i) =>
                      i === emptyIdx ? { ...r, url } : r,
                    );
                  }
                  return [...prev, { url, alt: asset.label || "" }];
                })
              }
              testIDPrefix="gallery-stock-gallery"
            />
          </View>
        ) : null}

        {isProfileCard ? (
          <View style={{ gap: 12 }}>
            <Text style={[styles.rowLabel, { color: colors.foreground }]}>
              Profile
            </Text>
            <TextField
              label="Name"
              value={values.name ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, name: t }))}
              trailing={
                <DictationMic
                  onText={(t) =>
                    setValues((p) => ({
                      ...p,
                      name: p.name ? p.name.trim() + " " + t : t,
                    }))
                  }
                />
              }
            />
            <TextField
              label="Title / tagline"
              value={values.title ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, title: t }))}
              trailing={
                <DictationMic
                  onText={(t) =>
                    setValues((p) => ({
                      ...p,
                      title: p.title ? p.title.trim() + " " + t : t,
                    }))
                  }
                />
              }
            />
            <TextField
              label="Bio"
              value={values.bio ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, bio: t }))}
              multiline
              numberOfLines={4}
              style={{ height: 110, textAlignVertical: "top", paddingTop: 12 }}
              trailing={
                <DictationMic
                  onText={(t) =>
                    setValues((p) => ({
                      ...p,
                      bio: p.bio ? p.bio.trim() + " " + t : t,
                    }))
                  }
                />
              }
            />
            <TextField
              label="Avatar image URL"
              hint="Square image works best."
              value={values.avatar ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, avatar: t }))}
              keyboardType="url"
              autoCapitalize="none"
            />
            <TextField
              label="Cover image URL"
              hint="Used by cover / hero / floating layouts."
              value={values.cover ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, cover: t }))}
              keyboardType="url"
              autoCapitalize="none"
            />

            {/* Verified toggle — shown next to the name on founder/social
                layouts (mirrors the web editor's verified checkbox). */}
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                justifyContent: "space-between",
                gap: 12,
              }}
            >
              <View style={{ flex: 1 }}>
                <Text style={{ color: colors.foreground, fontWeight: "600", fontSize: 13 }}>
                  Verified badge
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                  Shows a check next to the name.
                </Text>
              </View>
              <Switch
                value={profileVerified}
                onValueChange={setProfileVerified}
                trackColor={{ false: colors.border, true: colors.primary }}
              />
            </View>

            {/* Decorative avatar frame (Task #5910) — mirrors the web
                editor's swatch picker. Renders behind circular avatars on
                the public page; "None" clears it. */}
            <View>
              <Text style={{ color: colors.foreground, fontWeight: "600", fontSize: 13 }}>
                Avatar frame
              </Text>
              <Text style={{ color: colors.mutedForeground, fontSize: 11, marginBottom: 8 }}>
                A decorative shape drawn behind the avatar.
              </Text>
              <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
                <Pressable
                  {...WEB_FOCUS_RING_PROPS}
                  onPress={() => setAvatarFrame("")}
                  style={{
                    alignItems: "center",
                    borderWidth: 1,
                    borderColor: avatarFrame === "" ? colors.primary : colors.border,
                    backgroundColor: avatarFrame === "" ? colors.primary + "22" : "transparent",
                    borderRadius: 10,
                    paddingVertical: 8,
                    paddingHorizontal: 10,
                    width: 76,
                  }}
                >
                  <View
                    style={{
                      width: 34,
                      height: 34,
                      alignItems: "center",
                      justifyContent: "center",
                    }}
                  >
                    <View
                      style={{
                        width: 24,
                        height: 24,
                        borderRadius: 12,
                        borderWidth: 1,
                        borderStyle: "dashed",
                        borderColor: colors.mutedForeground,
                      }}
                    />
                  </View>
                  <Text style={{ color: colors.foreground, fontSize: 10, marginTop: 4 }}>
                    None
                  </Text>
                </Pressable>
                {AVATAR_FRAME_KEYS.map((fk) => {
                  const selected = avatarFrame === fk;
                  return (
                    <Pressable
                      key={fk}
                      {...WEB_FOCUS_RING_PROPS}
                      onPress={() => setAvatarFrame(fk)}
                      style={{
                        alignItems: "center",
                        borderWidth: 1,
                        borderColor: selected ? colors.primary : colors.border,
                        backgroundColor: selected ? colors.primary + "22" : "transparent",
                        borderRadius: 10,
                        paddingVertical: 8,
                        paddingHorizontal: 10,
                        width: 76,
                      }}
                    >
                      <View
                        style={{
                          width: 34,
                          height: 34,
                          alignItems: "center",
                          justifyContent: "center",
                        }}
                      >
                        <View style={{ position: "absolute", width: 34, height: 34 }}>
                          <AvatarFrame shape={fk} color={colors.primary} size={34} />
                        </View>
                        <View
                          style={{
                            width: 18,
                            height: 18,
                            borderRadius: 9,
                            backgroundColor: colors.primary + "66",
                          }}
                        />
                      </View>
                      <Text
                        style={{ color: colors.foreground, fontSize: 10, marginTop: 4 }}
                        numberOfLines={1}
                      >
                        {AVATAR_FRAME_LABELS[fk]}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
              {avatarFrame !== "" ? (
                <View style={{ marginTop: 10 }}>
                  <Text style={{ color: colors.mutedForeground, fontSize: 11, marginBottom: 6 }}>
                    Frame color. Auto uses the layout accent.
                  </Text>
                  <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
                    <Pressable
                      {...WEB_FOCUS_RING_PROPS}
                      onPress={() => setAvatarFrameColor("")}
                      style={{
                        borderWidth: 1,
                        borderColor: avatarFrameColor === "" ? colors.primary : colors.border,
                        borderRadius: 8,
                        paddingVertical: 6,
                        paddingHorizontal: 10,
                      }}
                    >
                      <Text style={{ color: colors.foreground, fontSize: 11 }}>Auto</Text>
                    </Pressable>
                    <ColorSwatchRow
                      prefix="avatar-frame-color"
                      value={avatarFrameColor}
                      onPick={setAvatarFrameColor}
                      palette={AVATAR_FRAME_COLOR_PRESETS}
                      size={30}
                    />
                  </View>
                </View>
              ) : null}
            </View>

            <TextField
              label="Location"
              value={values.location ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, location: t }))}
              trailing={
                <DictationMic
                  onText={(t) =>
                    setValues((p) => ({
                      ...p,
                      location: p.location ? p.location.trim() + " " + t : t,
                    }))
                  }
                />
              }
            />
            <TextField
              label="Website"
              value={values.website ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, website: t }))}
              keyboardType="url"
              autoCapitalize="none"
            />
            <TextField
              label="Button label"
              hint="Shown on the founder layout."
              value={values.cta_label ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, cta_label: t }))}
              trailing={
                <DictationMic
                  onText={(t) =>
                    setValues((p) => ({
                      ...p,
                      cta_label: p.cta_label
                        ? p.cta_label.trim() + " " + t
                        : t,
                    }))
                  }
                />
              }
            />
            <TextField
              label="Button URL"
              value={values.cta_url ?? ""}
              onChangeText={(t) => setValues((p) => ({ ...p, cta_url: t }))}
              keyboardType="url"
              autoCapitalize="none"
            />

            {/* Socials repeater — name + URL rows. Renders as icon chips on
                the glass/gradient/minimal-dark/social layouts. */}
            <View style={{ gap: 8 }}>
              <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                Social links
              </Text>
              <DraggableRepeaterRows
                items={profileSocials}
                gap={8}
                handleColor={colors.mutedForeground}
                onReorder={(perm) =>
                  setProfileSocials((prev) => perm.map((i) => prev[i]))
                }
                renderRow={(soc, idx, dragHandle) => (
                <View
                  style={{
                    gap: 8,
                    padding: 10,
                    borderRadius: 12,
                    borderWidth: 1,
                    borderColor: colors.border,
                    backgroundColor: colors.card,
                  }}
                >
                  <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                    <View style={{ marginTop: 18 }}>{dragHandle}</View>
                    <View style={{ flex: 1 }}>
                      <TextField
                        label="Platform"
                        hint="e.g. instagram, twitter, github"
                        value={soc.name}
                        onChangeText={(t) =>
                          setProfileSocials((p) =>
                            p.map((s, i) => (i === idx ? { ...s, name: t } : s)),
                          )
                        }
                        autoCapitalize="none"
                      />
                    </View>
                    <Pressable
                      {...WEB_FOCUS_RING_PROPS}
                      disabled={idx === 0}
                      onPress={() =>
                        setProfileSocials((prev) => {
                          if (idx <= 0) return prev;
                          const next = [...prev];
                          [next[idx - 1], next[idx]] = [next[idx], next[idx - 1]];
                          return next;
                        })
                      }
                      accessibilityRole="button"
                      accessibilityLabel={`Move social link ${idx + 1} up`}
                      hitSlop={8}
                      style={{
                        padding: 6,
                        marginTop: 18,
                        opacity: idx === 0 ? 0.3 : 1,
                      }}
                      testID={`profile-social-up-${idx}`}
                    >
                      <Feather
                        name="arrow-up"
                        size={16}
                        color={idx === 0 ? colors.mutedForeground : colors.primary}
                      />
                    </Pressable>
                    <Pressable
                      {...WEB_FOCUS_RING_PROPS}
                      disabled={idx === profileSocials.length - 1}
                      onPress={() =>
                        setProfileSocials((prev) => {
                          if (idx >= prev.length - 1) return prev;
                          const next = [...prev];
                          [next[idx], next[idx + 1]] = [next[idx + 1], next[idx]];
                          return next;
                        })
                      }
                      accessibilityRole="button"
                      accessibilityLabel={`Move social link ${idx + 1} down`}
                      hitSlop={8}
                      style={{
                        padding: 6,
                        marginTop: 18,
                        opacity: idx === profileSocials.length - 1 ? 0.3 : 1,
                      }}
                      testID={`profile-social-down-${idx}`}
                    >
                      <Feather
                        name="arrow-down"
                        size={16}
                        color={
                          idx === profileSocials.length - 1
                            ? colors.mutedForeground
                            : colors.primary
                        }
                      />
                    </Pressable>
                    <Pressable {...WEB_FOCUS_RING_PROPS}
                      onPress={() =>
                        setProfileSocials((p) => p.filter((_, i) => i !== idx))
                      }
                      hitSlop={8}
                      style={{ padding: 6, marginTop: 18 }}
                    >
                      <Feather name="trash-2" size={18} color={colors.destructive} />
                    </Pressable>
                  </View>
                  <TextField
                    label="URL"
                    value={soc.url}
                    onChangeText={(t) =>
                      setProfileSocials((p) =>
                        p.map((s, i) => (i === idx ? { ...s, url: t } : s)),
                      )
                    }
                    keyboardType="url"
                    autoCapitalize="none"
                  />
                </View>
                )}
              />
              <Pressable {...WEB_FOCUS_RING_PROPS}
                onPress={() =>
                  setProfileSocials((p) => [...p, { name: "", url: "" }])
                }
                style={{
                  padding: 12,
                  borderRadius: 12,
                  borderWidth: 1,
                  borderStyle: "dashed",
                  borderColor: colors.primary,
                  alignItems: "center",
                  flexDirection: "row",
                  justifyContent: "center",
                  gap: 6,
                }}
              >
                <Feather name="plus" size={14} color={colors.primary} />
                <Text style={{ color: colors.primary, fontSize: 12, fontWeight: "700" }}>
                  Add social link
                </Text>
              </Pressable>
            </View>

            {/* Stats repeater — value + label rows, reorderable with
                up/down. Only the "stats" layout paints these, so gate the
                editor to it (mirrors the web editor + public renderer).
                Capped at 6 by the backend sanitizer. */}
            {profileCardLayout === "stats" ? (
              <View style={{ gap: 8 }}>
                <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                  Stats
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: -4 }}>
                  Shown as a row of figures under the bio.
                </Text>
                {profileStats.map((stat, idx) => (
                  <View
                    key={idx}
                    style={{
                      gap: 8,
                      padding: 10,
                      borderRadius: 12,
                      borderWidth: 1,
                      borderColor: colors.border,
                      backgroundColor: colors.card,
                    }}
                  >
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                      <View style={{ flex: 1 }}>
                        <TextField
                          label="Value"
                          hint="e.g. 12K, 4.9, 250+"
                          value={stat.value}
                          onChangeText={(t) =>
                            setProfileStats((p) =>
                              p.map((s, i) => (i === idx ? { ...s, value: t } : s)),
                            )
                          }
                        />
                      </View>
                      <Pressable {...WEB_FOCUS_RING_PROPS}
                        disabled={idx === 0}
                        onPress={() =>
                          setProfileStats((p) => {
                            if (idx === 0) return p;
                            const next = [...p];
                            [next[idx - 1], next[idx]] = [next[idx], next[idx - 1]];
                            return next;
                          })
                        }
                        hitSlop={8}
                        style={{ padding: 6, marginTop: 18, opacity: idx === 0 ? 0.25 : 1 }}
                      >
                        <Feather name="arrow-up" size={18} color={colors.foreground} />
                      </Pressable>
                      <Pressable {...WEB_FOCUS_RING_PROPS}
                        disabled={idx === profileStats.length - 1}
                        onPress={() =>
                          setProfileStats((p) => {
                            if (idx === p.length - 1) return p;
                            const next = [...p];
                            [next[idx + 1], next[idx]] = [next[idx], next[idx + 1]];
                            return next;
                          })
                        }
                        hitSlop={8}
                        style={{
                          padding: 6,
                          marginTop: 18,
                          opacity: idx === profileStats.length - 1 ? 0.25 : 1,
                        }}
                      >
                        <Feather name="arrow-down" size={18} color={colors.foreground} />
                      </Pressable>
                      <Pressable {...WEB_FOCUS_RING_PROPS}
                        onPress={() =>
                          setProfileStats((p) => p.filter((_, i) => i !== idx))
                        }
                        hitSlop={8}
                        style={{ padding: 6, marginTop: 18 }}
                      >
                        <Feather name="trash-2" size={18} color={colors.destructive} />
                      </Pressable>
                    </View>
                    <TextField
                      label="Label"
                      hint="e.g. Followers, Rating, Projects"
                      value={stat.label}
                      onChangeText={(t) =>
                        setProfileStats((p) =>
                          p.map((s, i) => (i === idx ? { ...s, label: t } : s)),
                        )
                      }
                      trailing={
                        <DictationMic
                          onText={(t) =>
                            setProfileStats((p) =>
                              p.map((s, i) =>
                                i === idx
                                  ? {
                                      ...s,
                                      label: s.label
                                        ? s.label.trim() + " " + t
                                        : t,
                                    }
                                  : s,
                              ),
                            )
                          }
                        />
                      }
                    />
                  </View>
                ))}
                {profileStats.length < 6 ? (
                  <Pressable {...WEB_FOCUS_RING_PROPS}
                    onPress={() =>
                      setProfileStats((p) => [...p, { label: "", value: "" }])
                    }
                    style={{
                      padding: 12,
                      borderRadius: 12,
                      borderWidth: 1,
                      borderStyle: "dashed",
                      borderColor: colors.primary,
                      alignItems: "center",
                      flexDirection: "row",
                      justifyContent: "center",
                      gap: 6,
                    }}
                  >
                    <Feather name="plus" size={14} color={colors.primary} />
                    <Text style={{ color: colors.primary, fontSize: 12, fontWeight: "700" }}>
                      Add stat
                    </Text>
                  </Pressable>
                ) : (
                  <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                    Up to 6 stats.
                  </Text>
                )}
              </View>
            ) : null}

            {/* Badges repeater — label-only chips. Only the "badges" layout
                paints these, so gate the editor to it. Capped at 12 by the
                backend sanitizer. */}
            {profileCardLayout === "badges" ? (
              <View style={{ gap: 8 }}>
                <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                  Badges
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: -4 }}>
                  Shown as a row of pill chips under the bio.
                </Text>
                {profileBadges.map((badge, idx) => (
                  <View
                    key={idx}
                    style={{
                      flexDirection: "row",
                      alignItems: "center",
                      gap: 8,
                      padding: 10,
                      borderRadius: 12,
                      borderWidth: 1,
                      borderColor: colors.border,
                      backgroundColor: colors.card,
                    }}
                  >
                    <View style={{ flex: 1 }}>
                      <TextField
                        label="Label"
                        hint="e.g. Top Creator, Verified, Pro"
                        value={badge.label}
                        onChangeText={(t) =>
                          setProfileBadges((p) =>
                            p.map((b, i) => (i === idx ? { ...b, label: t } : b)),
                          )
                        }
                        trailing={
                          <DictationMic
                            onText={(t) =>
                              setProfileBadges((p) =>
                                p.map((b, i) =>
                                  i === idx
                                    ? {
                                        ...b,
                                        label: b.label
                                          ? b.label.trim() + " " + t
                                          : t,
                                      }
                                    : b,
                                ),
                              )
                            }
                          />
                        }
                      />
                    </View>
                    <Pressable {...WEB_FOCUS_RING_PROPS}
                      onPress={() =>
                        setProfileBadges((p) => p.filter((_, i) => i !== idx))
                      }
                      hitSlop={8}
                      style={{ padding: 6, marginTop: 18 }}
                    >
                      <Feather name="trash-2" size={18} color={colors.destructive} />
                    </Pressable>
                  </View>
                ))}
                {profileBadges.length < 12 ? (
                  <Pressable {...WEB_FOCUS_RING_PROPS}
                    onPress={() => setProfileBadges((p) => [...p, { label: "" }])}
                    style={{
                      padding: 12,
                      borderRadius: 12,
                      borderWidth: 1,
                      borderStyle: "dashed",
                      borderColor: colors.primary,
                      alignItems: "center",
                      flexDirection: "row",
                      justifyContent: "center",
                      gap: 6,
                    }}
                  >
                    <Feather name="plus" size={14} color={colors.primary} />
                    <Text style={{ color: colors.primary, fontSize: 12, fontWeight: "700" }}>
                      Add badge
                    </Text>
                  </Pressable>
                ) : (
                  <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                    Up to 12 badges.
                  </Text>
                )}
              </View>
            ) : null}
          </View>
        ) : null}

        {/* "Pick from my links" + "Fetch details" — mobile parity for the
            web link-block editor shortcuts. Picking a link drops its short
            URL into the trackable destination; Fetch pulls OG metadata for
            the current destination URL and pre-fills empty text/description/
            thumbnail fields. */}
        {isLinkPickerType ? (
          <View
            style={{
              borderWidth: 1,
              borderColor: colors.border,
              borderRadius: colors.radius,
              backgroundColor: colors.card,
              overflow: "hidden",
            }}
          >
            <Pressable
              {...WEB_FOCUS_RING_PROPS}
              onPress={() => setPickerOpen((o) => !o)}
              style={{
                flexDirection: "row",
                alignItems: "center",
                justifyContent: "space-between",
                paddingHorizontal: 12,
                paddingVertical: 10,
              }}
            >
              <Text style={{ color: colors.foreground, fontSize: 13, fontWeight: "600" }}>
                Pick from my links
              </Text>
              <Feather
                name={pickerOpen ? "chevron-up" : "chevron-down"}
                size={16}
                color={colors.mutedForeground}
              />
            </Pressable>
            {pickerOpen ? (
              <View style={{ borderTopWidth: 1, borderTopColor: colors.border, padding: 8, gap: 8 }}>
                <TextField
                  label=""
                  value={pickerQ}
                  onChangeText={setPickerQ}
                  placeholder="Search by title or alias…"
                  autoCapitalize="none"
                />
                {pickerQuery.isLoading ? (
                  <ActivityIndicator color={colors.primary} style={{ paddingVertical: 12 }} />
                ) : (pickerQuery.data?.items ?? []).length === 0 ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12, textAlign: "center", paddingVertical: 12 }}>
                    No links found
                  </Text>
                ) : (
                  (pickerQuery.data?.items ?? [])
                    .filter((l: Link) => l.id !== id)
                    .map((l: Link) => (
                      <Pressable
                        {...WEB_FOCUS_RING_PROPS}
                        key={l.id}
                        onPress={() => {
                          setLinkUrl(l.short_url);
                          setPickerOpen(false);
                          // Auto-fetch OG details for the picked link so the
                          // preview card appears immediately — `linkUrl` state
                          // hasn't committed yet, so pass the URL explicitly.
                          void runOgFetch(l.short_url);
                        }}
                        style={{
                          flexDirection: "row",
                          alignItems: "center",
                          gap: 10,
                          paddingHorizontal: 8,
                          paddingVertical: 8,
                        }}
                      >
                        <Feather name="link" size={14} color={colors.primary} />
                        <View style={{ flex: 1, minWidth: 0 }}>
                          <Text
                            numberOfLines={1}
                            style={{ color: colors.foreground, fontSize: 13, fontWeight: "600" }}
                          >
                            {l.title || l.alias}
                          </Text>
                          <Text
                            numberOfLines={1}
                            style={{ color: colors.mutedForeground, fontSize: 11 }}
                          >
                            /{l.alias} · {l.type}
                          </Text>
                        </View>
                      </Pressable>
                    ))
                )}
              </View>
            ) : null}
          </View>
        ) : null}

        {isLinkPickerType ? (
          <View style={{ gap: 4 }}>
            <Button
              label={ogFetching ? "Fetching…" : "Fetch details from URL"}
              variant="secondary"
              onPress={runOgFetch}
              disabled={ogFetching}
            />
            {ogError ? (
              <Text style={{ color: colors.destructive, fontSize: 11 }}>{ogError}</Text>
            ) : null}
            {ogSuccess && !ogError ? (
              <Text style={{ color: "#4ade80", fontSize: 11 }}>
                Details pre-filled below.
              </Text>
            ) : null}
            {ogPreview ? (
              <View
                style={{
                  marginTop: 6,
                  padding: 10,
                  gap: 10,
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderWidth: 1,
                  borderRadius: colors.radius,
                }}
              >
                <View style={{ flexDirection: "row", gap: 10, alignItems: "center" }}>
                  {ogPreview.image_url || ogPreview.favicon_url ? (
                    <Image
                      source={{ uri: ogPreview.image_url || ogPreview.favicon_url || undefined }}
                      style={{
                        width: 48,
                        height: 48,
                        borderRadius: 8,
                        backgroundColor: colors.muted,
                      }}
                      resizeMode="cover"
                    />
                  ) : (
                    <View
                      style={{
                        width: 48,
                        height: 48,
                        borderRadius: 8,
                        backgroundColor: colors.muted,
                        alignItems: "center",
                        justifyContent: "center",
                      }}
                    >
                      <Feather name="globe" size={18} color={colors.mutedForeground} />
                    </View>
                  )}
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text
                      numberOfLines={2}
                      style={{ color: colors.foreground, fontSize: 13, fontWeight: "600" }}
                    >
                      {ogPreview.title || "Untitled page"}
                    </Text>
                    {ogPreview.description ? (
                      <Text
                        numberOfLines={2}
                        style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}
                      >
                        {ogPreview.description}
                      </Text>
                    ) : null}
                  </View>
                </View>
                <View style={{ flexDirection: "row", gap: 8 }}>
                  <View style={{ flex: 1 }}>
                    <Button label="Apply" onPress={applyOgPreview} />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Button
                      label="Dismiss"
                      variant="secondary"
                      onPress={() => setOgPreview(null)}
                    />
                  </View>
                </View>
                <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                  Apply fills only the empty fields below.
                </Text>
              </View>
            ) : null}
            <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
              Uses the Destination URL below to pre-fill empty fields.
            </Text>
          </View>
        ) : null}

        {(meta?.fields ?? [])
          // The trackable-link section below owns the destination URL
          // (it writes settings._link.url, which the redirect controller
          // prefers over settings.url). Hide the generic `url` field for
          // any block where the trackable-link section will render so
          // creators don't end up with two URL inputs that disagree.
          .filter((f) => !(f.key === "url"))
          .map((f) => (
          <TextField
            key={f.key}
            label={f.label}
            hint={f.hint}
            value={values[f.key] ?? ""}
            onChangeText={(t) => setValues((p) => ({ ...p, [f.key]: t }))}
            keyboardType={f.kind === "url" ? "url" : "default"}
            autoCapitalize={f.kind === "url" ? "none" : "sentences"}
            multiline={f.kind === "multiline"}
            numberOfLines={f.kind === "multiline" ? 4 : 1}
            style={
              f.kind === "multiline"
                ? { height: 120, textAlignVertical: "top", paddingTop: 12 }
                : undefined
            }
          />
        ))}

        {/* Targeting — per-block geo + device visibility rules. Mirrors the
            web editor's "Display Settings → Audience/Device" cards but
            scoped to the controls a creator is most likely to want on the
            go (countries include/exclude, devices include/exclude). Other
            visibility keys (continents, cities, OS, browser, time slots)
            are preserved on save so this never wipes web-only settings. */}
        <View
          style={[
            styles.row,
            {
              flexDirection: "column",
              alignItems: "stretch",
              gap: 12,
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <Text style={[styles.rowLabel, { color: colors.foreground }]}>
            Targeting
          </Text>

          <View style={{ gap: 6 }}>
            <Text style={{ fontSize: 12, color: colors.mutedForeground }}>
              Devices · Show only on (leave empty for all)
            </Text>
            <View style={{ flexDirection: "row", gap: 8 }}>
              {(["mobile", "tablet", "desktop"] as const).map((d) => {
                const on = visDevices.has(d);
                return (
                  <Pressable {...WEB_FOCUS_RING_PROPS}
                    key={d}
                    onPress={() => {
                      const next = new Set(visDevices);
                      if (on) next.delete(d); else next.add(d);
                      setVisDevices(next);
                    }}
                    style={{
                      paddingHorizontal: 10,
                      paddingVertical: 6,
                      borderRadius: 999,
                      borderWidth: 1,
                      borderColor: on ? colors.primary : colors.border,
                      backgroundColor: on ? colors.primary : "transparent",
                    }}
                  >
                    <Text style={{ fontSize: 12, color: on ? "#fff" : colors.mutedForeground, textTransform: "capitalize" }}>{d}</Text>
                  </Pressable>
                );
              })}
            </View>
          </View>

          <View style={{ gap: 6 }}>
            <Text style={{ fontSize: 12, color: colors.mutedForeground }}>Devices · Hide on</Text>
            <View style={{ flexDirection: "row", gap: 8 }}>
              {(["mobile", "tablet", "desktop"] as const).map((d) => {
                const on = visDevicesExclude.has(d);
                return (
                  <Pressable {...WEB_FOCUS_RING_PROPS}
                    key={d}
                    onPress={() => {
                      const next = new Set(visDevicesExclude);
                      if (on) next.delete(d); else next.add(d);
                      setVisDevicesExclude(next);
                    }}
                    style={{
                      paddingHorizontal: 10,
                      paddingVertical: 6,
                      borderRadius: 999,
                      borderWidth: 1,
                      borderColor: on ? colors.destructive : colors.border,
                      backgroundColor: on ? colors.destructive : "transparent",
                    }}
                  >
                    <Text style={{ fontSize: 12, color: on ? "#fff" : colors.mutedForeground, textTransform: "capitalize" }}>{d}</Text>
                  </Pressable>
                );
              })}
            </View>
          </View>

          <TextField
            label="Countries · Show only in"
            hint="ISO codes, comma-separated. e.g. US, IN, GB. Leave empty for all."
            value={visCountries}
            onChangeText={setVisCountries}
            autoCapitalize="characters"
          />
          <TextField
            label="Countries · Hide in"
            hint="ISO codes, comma-separated. e.g. RU, KP."
            value={visCountriesExclude}
            onChangeText={setVisCountriesExclude}
            autoCapitalize="characters"
          />
        </View>

        {(() => {
          // Auto-UTM editor: shown for any block kind that ships with a
          // `url` field (link/image/video/embed) since those are the ones
          // whose outbound clicks our redirect controller decorates.
          const hasUrl = (meta?.fields ?? []).some((f) => f.key === "url");
          if (!hasUrl) return null;
          const biolinkAutoUtm =
            ((linkQ.data?.settings ?? {}) as Record<string, any>).biolink
              ?.auto_utm ?? {};
          const biolinkOn = !!biolinkAutoUtm.enabled;
          const biolinkDefaults: Record<string, string> =
            biolinkAutoUtm.defaults ?? {};
          // Mirror AutoUtmBuilder::buildTokens — same field fallback +
          // slugify so the preview matches the URL the redirect emits.
          const slugify = (s: string) =>
            s.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
          const settingsRecord = (block?.settings ?? {}) as Record<string, unknown>;
          let blockNameRaw = "";
          for (const k of ["label", "title", "heading", "text", "name"]) {
            const v = settingsRecord[k];
            if (typeof v === "string" && v) {
              blockNameRaw = v;
              break;
            }
          }
          if (!blockNameRaw) blockNameRaw = `block-${block?.id ?? ""}`;
          const tokens: Record<string, string> = {
            slug: linkQ.data?.alias ?? "",
            alias: linkQ.data?.alias ?? "",
            block: slugify(blockNameRaw),
            block_id: String(block?.id ?? ""),
            link_id: String(id),
          };
          const HARD_DEFAULTS: Record<string, string> = {
            utm_source: "1inme",
            utm_medium: "biolink",
            utm_campaign: "{slug}",
            utm_content: "{block}",
          };
          const effectiveOn =
            autoUtmEnabled === "on" ||
            (autoUtmEnabled === "inherit" && biolinkOn);
          const resolveTokens = (v: string) =>
            v.replace(/\{([a-z_]+)\}/g, (_, name) => tokens[name] ?? "");
          const buildPreview = () => {
            const raw = linkUrl.trim();
            if (!raw) return "(set a destination URL above)";
            // Mirror PHP split: preserve fragment, treat any existing
            // destination key as creator-set (do not overwrite).
            const hashAt = raw.indexOf("#");
            const fragment = hashAt !== -1 ? raw.slice(hashAt) : "";
            const base = hashAt !== -1 ? raw.slice(0, hashAt) : raw;
            const queryAt = base.indexOf("?");
            const pathPart = queryAt !== -1 ? base.slice(0, queryAt) : base;
            const qsRaw = queryAt !== -1 ? base.slice(queryAt + 1) : "";
            const params = new URLSearchParams(qsRaw);
            if (effectiveOn) {
              UTM_KEYS.forEach((k) => {
                let v = (autoUtmOverrides[k] || "").trim();
                if (!v) {
                  const tpl =
                    (biolinkDefaults[k] || "").trim() || HARD_DEFAULTS[k] || "";
                  if (tpl) v = resolveTokens(tpl).trim();
                } else {
                  v = resolveTokens(v).trim();
                }
                if (!v || params.has(k)) return;
                params.set(k, v);
              });
            }
            const qs = params.toString();
            return pathPart + (qs ? "?" + qs : "") + fragment;
          };
          return (
            <View style={{ gap: 10 }}>
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 12,
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  letterSpacing: 0.6,
                  textTransform: "uppercase",
                  marginTop: 8,
                }}
              >
                Trackable link
              </Text>
              <TextField
                label="Destination URL"
                value={linkUrl}
                onChangeText={setLinkUrl}
                keyboardType="url"
                autoCapitalize="none"
                hint="Tracked clicks land in your analytics."
              />
              <View style={{ gap: 8 }}>
                <Text
                  style={[
                    styles.choiceLabel,
                    { color: colors.mutedForeground },
                  ]}
                >
                  Auto-UTM for this block
                </Text>
                <View
                  style={[
                    styles.segment,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  {(["inherit", "on", "off"] as AutoUtmEnabled[]).map(
                    (opt) => {
                      const on = autoUtmEnabled === opt;
                      const lbl =
                        opt === "inherit"
                          ? `Inherit (${biolinkOn ? "on" : "off"})`
                          : opt === "on"
                            ? "Always on"
                            : "Off";
                      return (
                        <Pressable {...WEB_FOCUS_RING_PROPS}
                          key={opt}
                          onPress={() => setAutoUtmEnabled(opt)}
                          style={[
                            styles.segmentItem,
                            {
                              backgroundColor: on
                                ? colors.background
                                : "transparent",
                              borderRadius: colors.radius - 4,
                            },
                          ]}
                        >
                          <Text
                            style={[
                              styles.segmentText,
                              {
                                color: on
                                  ? colors.primary
                                  : colors.mutedForeground,
                              },
                            ]}
                          >
                            {lbl}
                          </Text>
                        </Pressable>
                      );
                    },
                  )}
                </View>
                <Text
                  style={{
                    color: colors.mutedForeground,
                    fontSize: 11,
                    fontFamily: "SpaceGrotesk_400Regular",
                  }}
                >
                  Existing UTM params on the destination URL are always
                  preserved.
                </Text>
              </View>

              {effectiveOn ? (
                <View style={{ gap: 8 }}>
                  {UTM_KEYS.map((k) => (
                    <TextField
                      key={k}
                      label={k}
                      value={autoUtmOverrides[k] ?? ""}
                      onChangeText={(t) =>
                        setAutoUtmOverrides((p) => ({ ...p, [k]: t }))
                      }
                      autoCapitalize="none"
                      hint={
                        biolinkDefaults[k]
                          ? `default: ${biolinkDefaults[k]}`
                          : HARD_DEFAULTS[k]
                            ? `default: ${HARD_DEFAULTS[k]}`
                            : "optional"
                      }
                    />
                  ))}
                </View>
              ) : null}

              <View
                style={{
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderWidth: 1,
                  borderRadius: colors.radius,
                  padding: 12,
                  gap: 4,
                }}
              >
                <Text
                  style={{
                    color: colors.mutedForeground,
                    fontSize: 11,
                    fontFamily: "SpaceGrotesk_600SemiBold",
                    letterSpacing: 0.5,
                    textTransform: "uppercase",
                  }}
                >
                  Resolved URL preview
                </Text>
                <Text
                  selectable
                  style={{
                    color: colors.foreground,
                    fontSize: 12,
                    fontFamily: "SpaceGrotesk_400Regular",
                  }}
                >
                  {buildPreview()}
                </Text>
              </View>
            </View>
          );
        })()}

        <View
          style={[
            styles.row,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <Text style={[styles.rowLabel, { color: colors.foreground }]}>
            Visible on Link in Bio
          </Text>
          <Switch
            value={active}
            onValueChange={setActive}
            trackColor={{ true: colors.primary, false: colors.border }}
          />
        </View>

        {/* Task #1094 — Limits & Scarcity card. Editing the cap or
            expiry from mobile mirrors the web editor exactly: empty
            max_clicks = unlimited, blank end_date = no time-based
            expiry. The preview row below lets the creator eyeball
            active / near / expired states without burning real clicks
            or waiting for the real expiry to land. */}
        <View
          style={{
            padding: 14,
            borderRadius: 12,
            borderWidth: 1,
            borderColor: colors.border,
            backgroundColor: colors.card,
            gap: 10,
          }}
        >
          <Text style={[styles.rowLabel, { color: colors.foreground }]}>
            Limits &amp; Scarcity
          </Text>
          <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
            Set a click cap or expiry, then choose how the block reacts
            once the limit is reached. Bots never count toward the cap.
          </Text>

          <TextField
            label="Max clicks (blank = unlimited)"
            value={maxClicks}
            placeholder="0"
            keyboardType="number-pad"
            onChangeText={(t) => setMaxClicks(t.replace(/[^\d]/g, ""))}
          />
          {typeof block.click_count === "number" && block.click_count > 0 ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
              Already counted: {block.click_count}
            </Text>
          ) : null}

          <TextField
            label="Expires (YYYY-MM-DDTHH:mm, blank = never)"
            value={endDate}
            placeholder="2026-12-31T23:59"
            autoCapitalize="none"
            onChangeText={setEndDate}
          />

          <View style={[styles.row, { borderColor: colors.border, borderRadius: 10, padding: 10 }]}>
            <Text style={{ color: colors.foreground, fontSize: 13 }}>
              Show live countdown
            </Text>
            <Switch
              value={showCountdown}
              onValueChange={setShowCountdown}
              trackColor={{ true: colors.primary, false: colors.border }}
            />
          </View>
          <View style={[styles.row, { borderColor: colors.border, borderRadius: 10, padding: 10 }]}>
            <Text style={{ color: colors.foreground, fontSize: 13 }}>
              Show remaining count
            </Text>
            <Switch
              value={showRemaining}
              onValueChange={setShowRemaining}
              trackColor={{ true: colors.primary, false: colors.border }}
            />
          </View>

          {/* "Almost gone" threshold — mobile uses a simple ±5 stepper
              instead of a slider since RN core doesn't ship one and we
              don't want a new dependency just for this one control. */}
          <View>
            <Text style={{ color: colors.foreground, fontSize: 12, fontWeight: "600", marginBottom: 4 }}>
              "Almost gone" threshold: {nearPercent}%
            </Text>
            <View style={{ flexDirection: "row", gap: 8 }}>
              <Pressable {...WEB_FOCUS_RING_PROPS}
                onPress={() => setNearPercent((p) => Math.max(0, p - 5))}
                style={{
                  paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8,
                  backgroundColor: colors.muted, borderWidth: 1, borderColor: colors.border,
                }}
              >
                <Text style={{ color: colors.foreground, fontWeight: "700" }}>−5%</Text>
              </Pressable>
              <Pressable {...WEB_FOCUS_RING_PROPS}
                onPress={() => setNearPercent((p) => Math.min(100, p + 5))}
                style={{
                  paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8,
                  backgroundColor: colors.muted, borderWidth: 1, borderColor: colors.border,
                }}
              >
                <Text style={{ color: colors.foreground, fontWeight: "700" }}>+5%</Text>
              </Pressable>
            </View>
          </View>

          {/* Expired behavior */}
          <View style={{ flexDirection: "row", gap: 8 }}>
            {(["hide", "show"] as const).map((opt) => {
              const sel = expiredAction === opt;
              return (
                <Pressable {...WEB_FOCUS_RING_PROPS}
                  key={opt}
                  onPress={() => setExpiredAction(opt)}
                  style={{
                    flex: 1, padding: 10, borderRadius: 10,
                    backgroundColor: sel ? colors.primary + "22" : colors.muted,
                    borderWidth: sel ? 2 : 1,
                    borderColor: sel ? colors.primary : colors.border,
                  }}
                >
                  <Text style={{ color: colors.foreground, fontWeight: "700", fontSize: 12 }}>
                    {opt === "hide" ? "Hide when expired" : "Keep showing it"}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          {expiredAction === "show" ? (
            <View style={{ flexDirection: "row", gap: 8 }}>
              <View style={{ flex: 2 }}>
                <TextField
                  label="Expired label"
                  value={expiredLabel}
                  onChangeText={(t) => setExpiredLabel(t.slice(0, 40))}
                  placeholder="Sold out"
                  trailing={<DictationMic onText={dictateInto(setExpiredLabel)} />}
                />
              </View>
              <View style={{ flex: 1 }}>
                <TextField
                  label="Emoji"
                  value={expiredEmoji}
                  onChangeText={(t) => setExpiredEmoji(t.slice(0, 4))}
                  placeholder="🔒"
                />
              </View>
            </View>
          ) : null}

          {/* Editor preview switcher — pure local state, never persisted. */}
          <View
            style={{
              padding: 10, borderRadius: 10, borderWidth: 1, borderStyle: "dashed",
              borderColor: colors.border, backgroundColor: colors.muted, gap: 6,
            }}
          >
            <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center" }}>
              <Text style={{ color: colors.foreground, fontSize: 12, fontWeight: "600" }}>
                Preview state
              </Text>
              <View style={{ flexDirection: "row", gap: 4 }}>
                {(["active", "near", "expired"] as const).map((s) => {
                  const sel = previewState === s;
                  return (
                    <Pressable {...WEB_FOCUS_RING_PROPS}
                      key={s}
                      onPress={() => setPreviewState(s)}
                      style={{
                        paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8,
                        backgroundColor: sel ? colors.primary : "transparent",
                        borderWidth: 1, borderColor: sel ? colors.primary : colors.border,
                      }}
                    >
                      <Text style={{ color: sel ? "#fff" : colors.foreground, fontSize: 11, fontWeight: "700" }}>
                        {s}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            </View>
            <View
              style={{
                alignSelf: "flex-start",
                paddingHorizontal: 10, paddingVertical: 5, borderRadius: 8,
                backgroundColor:
                  previewState === "expired" ? "rgba(120,113,108,0.25)"
                  : previewState === "near"  ? "rgba(245,158,11,0.22)"
                                              : colors.success + "2e",
              }}
            >
              <Text style={{ color: colors.foreground, fontSize: 11, fontWeight: "700" }}>
                {previewState === "expired"
                  ? `${expiredEmoji ? expiredEmoji + " " : ""}${expiredLabel || "Sold out"}`
                  : previewState === "near"
                    ? "🔥 Only 3 left"
                    : "⏳ Ends in 02:14:33"}
              </Text>
            </View>
          </View>
        </View>

        <Button
          label="Save block"
          onPress={() => save.mutate()}
          loading={save.isPending}
        />
    </>
  );

  return (
    <View
      style={
        inline
          ? styles.inlineWrap
          : { flex: 1, backgroundColor: colors.background }
      }
    >
      {inline ? null : (
        <Stack.Screen
          options={{ headerShown: true, title: meta?.label || block.type }}
        />
      )}
      {inline ? (
        // Inline mode lives inside the blocks list's own ScrollView, so a
        // nested ScrollView would break scrolling — render a plain View.
        <View style={styles.bodyInline}>{body}</View>
      ) : (
        <ScrollView ref={editorScrollRef} contentContainerStyle={styles.body}>{body}</ScrollView>
      )}

      <IconPickerModal
        visible={iconPickerTarget !== null}
        onClose={() => setIconPickerTarget(null)}
        // For per-item slots we resolve the value from the right array;
        // for "default" we read the block-level default bullet icon.
        value={
          iconPickerTarget?.kind === "list"
            ? listItems[iconPickerTarget.index]?.icon ?? ""
            : iconPickerTarget?.kind === "pricing"
              ? pricingItems[iconPickerTarget.index]?.icon ?? ""
              : iconPickerTarget?.kind === "default"
                ? defaultBulletIcon
                : ""
        }
        title={
          iconPickerTarget?.kind === "default"
            ? "Default bullet icon"
            : "Pick an icon"
        }
        // Per-item list bullets fall back to the block default when
        // cleared. The block-default and pricing rows have no fallback,
        // so we don't expose the "Use default" affordance there.
        allowClear={iconPickerTarget?.kind === "list"}
        onChange={(next) => {
          if (!iconPickerTarget) return;
          if (iconPickerTarget.kind === "default") {
            setDefaultBulletIcon(next || "fas fa-check");
          } else if (iconPickerTarget.kind === "list") {
            const i = iconPickerTarget.index;
            setListItems((prev) =>
              prev.map((p, idx) => (idx === i ? { ...p, icon: next } : p)),
            );
          } else {
            const i = iconPickerTarget.index;
            setPricingItems((prev) =>
              prev.map((p, idx) => (idx === i ? { ...p, icon: next } : p)),
            );
          }
        }}
      />

      <MapPickerModal
        visible={mapPickerOpen}
        initialLat={values.lat ? parseFloat(values.lat) : null}
        initialLng={values.lng ? parseFloat(values.lng) : null}
        onClose={() => setMapPickerOpen(false)}
        onPick={(p: PickedPoint) => {
          setValues((prev) => ({
            ...prev,
            lat: String(p.lat),
            lng: String(p.lng),
            address: p.address && !prev.address?.trim() ? p.address : prev.address,
          }));
          setMapPickerOpen(false);
        }}
      />
    </View>
  );
}

// Full-screen route wrapper: reads the link + block ids from the route
// params and renders the shared editor in screen mode.
export default function EditBlockScreen() {
  const { id: idParam, blockId: blockIdParam } = useLocalSearchParams<{
    id: string;
    blockId: string;
  }>();
  return (
    <BlockSettingsEditor
      linkId={Number(idParam)}
      blockId={Number(blockIdParam)}
    />
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  centerInline: {
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 24,
  },
  inlineWrap: {},
  body: { padding: 20, gap: 14, paddingBottom: 40 },
  bodyInline: { gap: 14 },
  blurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    padding: 14,
    borderWidth: 1,
  },
  mapPickerBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderWidth: 1,
  },
  mapPickerBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  rowLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  choiceLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  segment: { flexDirection: "row", padding: 4, borderWidth: 1 },
  segmentItem: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 10,
  },
  segmentText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
  },
});
