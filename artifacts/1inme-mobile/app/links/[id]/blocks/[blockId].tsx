import AsyncStorage from "@react-native-async-storage/async-storage";
import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
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
import {
  IconPickerButton,
  IconPickerModal,
} from "@/components/IconPickerModal";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  blockKind,
  listBlocks,
  updateBlock,
  type Block,
} from "@/lib/api/blocks";
import { variantsForType, findVariant } from "@/lib/blockVariants";

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

export default function EditBlockScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const { id: idParam, blockId: blockIdParam } = useLocalSearchParams<{
    id: string;
    blockId: string;
  }>();
  const id = Number(idParam);
  const blockId = Number(blockIdParam);

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
      Alert.alert("Applied", `Design applied to ${count} block(s).`);
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
    Alert.alert(
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
      return updateBlock(id, blockId, {
        is_active: active,
        settings: nextSettings,
      });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["blocks", id] });
      router.back();
    },
  });

  // Visible filter chips: only tags actually used by this type's catalog.
  const tagChips = useMemo(() => {
    if (!block) return [] as string[];
    const present = new Set<string>();
    variantsForType(block.type).forEach((v) => v.tags.forEach((t) => present.add(t)));
    return Array.from(present);
  }, [block]);

  if (q.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (!block) {
    return (
      <View style={styles.center}>
        <Text style={{ color: colors.destructive }}>Block not found.</Text>
      </View>
    );
  }

  const meta = blockKind(block.type);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ headerShown: true, title: meta?.label || block.type }}
      />
      <ScrollView contentContainerStyle={styles.body}>
        {meta ? (
          <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
            {meta.blurb}
          </Text>
        ) : null}

        {/* Designs gallery — full mobile parity with the web editor:
            filter chips (incl. Favorites), Surprise me, Apply to all of
            this type, and a Custom snapshot restore card when the block
            has handcrafted styling captured. Variant keys are identical
            to the web catalog so picks roam across surfaces. */}
        <View style={{ gap: 8 }}>
          <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
            <Text style={[styles.rowLabel, { color: colors.foreground }]}>Design</Text>
            <Pressable
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
                <Pressable
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
            <Pressable
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
                <Pressable
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
                  <Pressable
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
            <Pressable
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
                    <Pressable
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
                        />
                      </View>
                      <Pressable
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
                    <Pressable
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

              <Pressable
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

        {(meta?.fields ?? []).map((f) => (
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
                  <Pressable
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
                  <Pressable
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
            Visible on biolink
          </Text>
          <Switch
            value={active}
            onValueChange={setActive}
            trackColor={{ true: colors.primary, false: colors.border }}
          />
        </View>

        <Button
          label="Save block"
          onPress={() => save.mutate()}
          loading={save.isPending}
        />
      </ScrollView>

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
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 14, paddingBottom: 40 },
  blurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    padding: 14,
    borderWidth: 1,
  },
  rowLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
});
