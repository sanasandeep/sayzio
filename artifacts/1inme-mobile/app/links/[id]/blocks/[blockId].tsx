import AsyncStorage from "@react-native-async-storage/async-storage";
import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Stack,
  useFocusEffect,
  useLocalSearchParams,
  useRouter,
} from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
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
import { Button } from "@/components/Button";
import { DictationMic } from "@/components/DictationMic";
import {
  IconPickerButton,
  IconPickerModal,
} from "@/components/IconPickerModal";
import { MapPickerModal, type PickedPoint } from "@/components/MapPickerModal";
import { TextField } from "@/components/TextField";
import { setVoiceSurface } from "@/components/VoiceAssistant";
import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";
import { getLink } from "@/lib/api/links";
import {
  blockKind,
  listBlocks,
  updateBlock,
  type Block,
} from "@/lib/api/blocks";
import { variantsForType, findVariant } from "@/lib/blockVariants";
import { canonicalBlockType } from "@/lib/blockTypeRegistry";
import { showAlert } from "@/lib/webAlert";

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

  // Pull the parent biolink so the resolved-URL preview can read the
  // biolink-wide auto_utm defaults and slug. Cached by the same key the
  // settings screens use.
  const linkQ = useQuery({
    queryKey: ["link", id],
    queryFn: () => getLink(id),
    enabled: Number.isFinite(id),
  });

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
  const [mapPickerOpen, setMapPickerOpen] = useState(false);
  const [mapShowDirections, setMapShowDirections] = useState(true);
  const [profileVerified, setProfileVerified] = useState<boolean>(false);
  const [profileSocials, setProfileSocials] = useState<ProfileSocial[]>([]);
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
    }
    // Hydrate the map-location boolean toggle. Mirrors the web default
    // (`$s['show_directions'] ?? true`) so blocks saved before this field
    // existed still show the "Directions" button by default.
    if (block.type === "map_location") {
      const sd = block.settings?.show_directions;
      setMapShowDirections(!(sd === false || sd === 0 || sd === "0" || sd === "false"));
    }
  }, [block]);

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
  }, [block, activeFilter, favorites]);

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
      // Field saves are content-only — variant changes flow through the
      // dedicated apply path above so we never re-merge a stale _style
      // here. We strip _style entirely from the values payload so the
      // backend keeps whatever variant/snapshot is currently persisted.
      const nextSettings: Record<string, unknown> = { ...values };
      delete nextSettings._style;
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
  }, [block]);

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
            to the web catalog so picks roam across surfaces. */}
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

              {(isList || isListNumbered) &&
                listItems.map((it, idx) => (
                  <View
                    key={idx}
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
                ))}

              {isPricing &&
                pricingItems.map((it, idx) => (
                  <View
                    key={idx}
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
                    <Pressable {...WEB_FOCUS_RING_PROPS}
                      onPress={() =>
                        setPricingItems((prev) => prev.filter((_, i) => i !== idx))
                      }
                      style={{
                        alignSelf: "flex-end",
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
                ))}

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
              {profileSocials.map((soc, idx) => (
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
              ))}
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
        <ScrollView contentContainerStyle={styles.body}>{body}</ScrollView>
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
