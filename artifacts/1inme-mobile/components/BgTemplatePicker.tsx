import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Feather } from "@expo/vector-icons";
import { Image as ExpoImage } from "expo-image";
import { LinearGradient } from "expo-linear-gradient";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";
import { getBaseUrl } from "@/lib/api";
import { getBgTemplates, type BgTemplate } from "@/lib/api/bgTemplates";
import { getLink, updateLink } from "@/lib/api/links";

// Appearance → "Templates" background gallery (mobile parity for the web
// template picker). Renders the admin-managed bg_templates catalog,
// browsable by category and searchable by name. Selecting a template saves
// `settings.biolink.background_type = 'template'` + `bg_template_id` right
// away via the REST link-update endpoint — the public renderer resolves
// the actual CSS/JS server-side from the bg_templates table.
//
// Swatches show the REAL rendered texture via pre-rendered PNG thumbnails
// (generate-bg-template-swatches.mjs) layered over an instant-paint
// LinearGradient approximation; templates whose CSS changed since the last
// generator run fall back to the gradient tint (md5-gated manifest).

function isRecord(v: unknown): v is Record<string, unknown> {
  return typeof v === "object" && v !== null && !Array.isArray(v);
}

// RN LinearGradient needs at least two stops; near-solid templates
// duplicate their single color so the swatch still renders.
function swatchColors(t: BgTemplate): [string, string, ...string[]] {
  const c = t.colors;
  if (c.length >= 2) return c as [string, string, ...string[]];
  const only = c[0] ?? "#3d3654";
  return [only, only];
}

export function BgTemplatePicker({ linkId }: { linkId: number }) {
  const colors = useColors();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [category, setCategory] = useState("all");
  const [search, setSearch] = useState("");
  const [pendingId, setPendingId] = useState<number | null>(null);

  const linkQ = useQuery({
    queryKey: ["link", linkId],
    queryFn: () => getLink(linkId),
    enabled: Number.isFinite(linkId),
  });

  const catalogQ = useQuery({
    queryKey: ["bg-templates"],
    queryFn: getBgTemplates,
    staleTime: 60 * 60 * 1000,
    enabled: open,
  });

  const biolink = isRecord(linkQ.data?.settings)
    ? isRecord((linkQ.data.settings as Record<string, unknown>).biolink)
      ? ((linkQ.data.settings as Record<string, unknown>)
          .biolink as Record<string, unknown>)
      : {}
    : {};
  const templateActive = biolink.background_type === "template";
  const selectedId =
    templateActive && typeof biolink.bg_template_id === "number"
      ? biolink.bg_template_id
      : null;

  const save = useMutation({
    mutationFn: (id: number) =>
      updateLink(linkId, {
        settings: {
          biolink: { background_type: "template", bg_template_id: id },
        },
      }),
    // Optimistically patch the cached link so the Appearance screen's
    // live background preview flips the moment a swatch is tapped; the
    // invalidate below reconciles with the server truth either way.
    onMutate: (id: number) => {
      qc.setQueryData(["link", linkId], (prev: unknown) => {
        if (!isRecord(prev)) return prev;
        const settings = isRecord(prev.settings) ? prev.settings : {};
        const biolink = isRecord(settings.biolink) ? settings.biolink : {};
        return {
          ...prev,
          settings: {
            ...settings,
            biolink: {
              ...biolink,
              background_type: "template",
              bg_template_id: id,
            },
          },
        };
      });
    },
    onSettled: () => {
      setPendingId(null);
      qc.invalidateQueries({ queryKey: ["link", linkId] });
    },
  });

  const query = search.trim().toLowerCase();
  const visible = useMemo(() => {
    const all = catalogQ.data?.templates ?? [];
    // A name search spans every category (like typing in the web gallery
    // filters the visible grid); otherwise browse the active category tab.
    if (query) {
      return all.filter((t) => t.name.toLowerCase().includes(query));
    }
    if (category === "all") return all;
    return all.filter((t) => t.category === category);
  }, [catalogQ.data, category, query]);

  return (
    <View style={{ gap: 8 }}>
      <Pressable
        {...WEB_FOCUS_RING_PROPS}
        testID="bg-templates-toggle"
        onPress={() => setOpen((o) => !o)}
        style={[
          styles.typeRow,
          {
            backgroundColor: colors.card,
            borderColor: templateActive ? colors.primary : colors.border,
            borderRadius: colors.radius,
          },
        ]}
      >
        <LinearGradient
          colors={["#0ea5e9", "#3d6bff", "#22c55e"]}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={styles.typeSwatch}
        />
        <View style={{ flex: 1 }}>
          <Text style={[styles.typeLabel, { color: colors.foreground }]}>
            Templates
          </Text>
          <Text style={[styles.typeHint, { color: colors.mutedForeground }]}>
            {templateActive
              ? "Template background in use"
              : "Animated & patterned backgrounds"}
          </Text>
        </View>
        {templateActive ? (
          <Feather name="check-circle" size={16} color={colors.primary} />
        ) : null}
        <Feather
          name={open ? "chevron-up" : "chevron-down"}
          size={18}
          color={colors.mutedForeground}
        />
      </Pressable>

      {open ? (
        <View style={{ gap: 10 }}>
          <TextField
            label="Search templates"
            value={search}
            onChangeText={setSearch}
            hint="Type a template name, e.g. Aurora"
            autoCapitalize="none"
            testID="bg-templates-search"
          />

          {!query && catalogQ.data ? (
            <View style={styles.chipRow}>
              {[{ key: "all", label: "All" }, ...catalogQ.data.categories].map(
                (c) => {
                  const on = category === c.key;
                  return (
                    <Pressable
                      {...WEB_FOCUS_RING_PROPS}
                      key={c.key}
                      onPress={() => setCategory(c.key)}
                      style={[
                        styles.chip,
                        {
                          backgroundColor: on ? colors.primary : colors.card,
                          borderColor: on ? colors.primary : colors.border,
                        },
                      ]}
                    >
                      <Text
                        style={[
                          styles.chipText,
                          {
                            color: on
                              ? colors.primaryForeground
                              : colors.mutedForeground,
                          },
                        ]}
                      >
                        {c.label}
                      </Text>
                    </Pressable>
                  );
                },
              )}
            </View>
          ) : null}

          {catalogQ.isLoading ? (
            <ActivityIndicator color={colors.primary} />
          ) : catalogQ.isError ? (
            <Text style={[styles.empty, { color: colors.destructive }]}>
              Couldn't load templates. Pull back and try again.
            </Text>
          ) : visible.length === 0 ? (
            <Text style={[styles.empty, { color: colors.mutedForeground }]}>
              No templates match "{search.trim()}".
            </Text>
          ) : (
            <View style={styles.grid} testID="bg-templates-grid">
              {visible.map((t) => {
                const on = selectedId === t.id;
                const saving = pendingId === t.id && save.isPending;
                return (
                  <Pressable
                    {...WEB_FOCUS_RING_PROPS}
                    key={t.id}
                    testID={`bg-template-${t.slug}`}
                    accessibilityLabel={t.name}
                    disabled={save.isPending}
                    onPress={() => {
                      if (on) return;
                      setPendingId(t.id);
                      save.mutate(t.id);
                    }}
                    style={[
                      styles.cell,
                      {
                        borderColor: on ? colors.primary : colors.border,
                        borderWidth: on ? 2 : 1,
                        borderRadius: colors.radius,
                      },
                    ]}
                  >
                    <View testID={`bg-tpl-swatch-${t.slug}`} style={styles.swatch}>
                      {/* Gradient approximation stays underneath as the
                          instant paint + fallback; the pre-rendered PNG of
                          the REAL CSS texture (animated auroras, meshes,
                          patterns) covers it once loaded. */}
                      <LinearGradient
                        colors={swatchColors(t)}
                        start={{ x: 0, y: 0 }}
                        end={{ x: 1, y: 1 }}
                        style={StyleSheet.absoluteFill}
                      />
                      {t.swatch ? (
                        <ExpoImage
                          source={{ uri: `${getBaseUrl()}${t.swatch}` }}
                          style={StyleSheet.absoluteFill}
                          contentFit="cover"
                          transition={0}
                          cachePolicy="memory-disk"
                        />
                      ) : null}
                      {saving ? (
                        <ActivityIndicator size="small" color="#ffffff" />
                      ) : on ? (
                        <View style={styles.checkBadge}>
                          <Feather name="check" size={12} color="#ffffff" />
                        </View>
                      ) : null}
                    </View>
                    <Text
                      numberOfLines={1}
                      style={[
                        styles.cellLabel,
                        {
                          color: on ? colors.foreground : colors.mutedForeground,
                        },
                      ]}
                    >
                      {t.name}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          )}

          {save.isError ? (
            <Text style={[styles.empty, { color: colors.destructive }]}>
              Couldn't apply the template.{" "}
              {(save.error as Error | null)?.message ?? "Please try again."}
            </Text>
          ) : null}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  typeRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  typeSwatch: { width: 34, height: 34, borderRadius: 8 },
  typeLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  typeHint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    marginTop: 2,
  },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 999,
    borderWidth: 1,
  },
  chipText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  grid: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
  },
  cell: {
    width: "30.5%",
    overflow: "hidden",
  },
  swatch: {
    width: "100%",
    aspectRatio: 9 / 14,
    alignItems: "center",
    justifyContent: "center",
  },
  checkBadge: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: "rgba(0,0,0,0.45)",
    alignItems: "center",
    justifyContent: "center",
  },
  cellLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    textAlign: "center",
    paddingVertical: 5,
    paddingHorizontal: 4,
  },
  empty: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    textAlign: "center",
    paddingVertical: 8,
  },
});
