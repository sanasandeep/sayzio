import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Feather } from "@expo/vector-icons";
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
import { getBgPresets, type BgPreset } from "@/lib/api/bgPresets";
import { getLink, updateLink } from "@/lib/api/links";

// Appearance → "Presets" background gallery (mobile parity for the web
// preset picker). Renders the same catalog of preset swatches the web
// editor shows, browsable by group and searchable by name. Selecting a
// swatch saves `settings.biolink.background_type = 'preset'` +
// `bg_preset_key` right away via the REST link-update endpoint — the
// public renderer resolves the actual CSS server-side from the catalog.

function isRecord(v: unknown): v is Record<string, unknown> {
  return typeof v === "object" && v !== null && !Array.isArray(v);
}

// RN LinearGradient needs at least two stops; solid presets duplicate
// their single color so the swatch still renders.
function swatchColors(p: BgPreset): [string, string, ...string[]] {
  const c = p.colors;
  if (c.length >= 2) return c as [string, string, ...string[]];
  const only = c[0] ?? "#3d3654";
  return [only, only];
}

export function BgPresetPicker({ linkId }: { linkId: number }) {
  const colors = useColors();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [group, setGroup] = useState("gradients");
  const [search, setSearch] = useState("");
  const [pendingKey, setPendingKey] = useState<string | null>(null);

  const linkQ = useQuery({
    queryKey: ["link", linkId],
    queryFn: () => getLink(linkId),
    enabled: Number.isFinite(linkId),
  });

  const catalogQ = useQuery({
    queryKey: ["bg-presets"],
    queryFn: getBgPresets,
    staleTime: 60 * 60 * 1000,
    enabled: open,
  });

  const biolink = isRecord(linkQ.data?.settings)
    ? isRecord((linkQ.data.settings as Record<string, unknown>).biolink)
      ? ((linkQ.data.settings as Record<string, unknown>)
          .biolink as Record<string, unknown>)
      : {}
    : {};
  const presetActive = biolink.background_type === "preset";
  const selectedKey =
    presetActive && typeof biolink.bg_preset_key === "string"
      ? biolink.bg_preset_key
      : "";

  const save = useMutation({
    mutationFn: (key: string) =>
      updateLink(linkId, {
        settings: {
          biolink: { background_type: "preset", bg_preset_key: key },
        },
      }),
    // Optimistically patch the cached link so the Appearance screen's
    // live background preview flips the moment a swatch is tapped; the
    // invalidate below reconciles with the server truth either way.
    onMutate: (key: string) => {
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
              background_type: "preset",
              bg_preset_key: key,
            },
          },
        };
      });
    },
    onSettled: () => {
      setPendingKey(null);
      qc.invalidateQueries({ queryKey: ["link", linkId] });
    },
  });

  const query = search.trim().toLowerCase();
  const visible = useMemo(() => {
    const all = catalogQ.data?.presets ?? [];
    // A name search spans every group (like typing in the web gallery
    // filters the visible grid); otherwise browse the active group tab.
    if (query) {
      return all.filter((p) => p.label.toLowerCase().includes(query));
    }
    return all.filter((p) => p.group === group);
  }, [catalogQ.data, group, query]);

  return (
    <View style={{ gap: 8 }}>
      <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
        Background
      </Text>
      <Pressable
        {...WEB_FOCUS_RING_PROPS}
        testID="bg-presets-toggle"
        onPress={() => setOpen((o) => !o)}
        style={[
          styles.typeRow,
          {
            backgroundColor: colors.card,
            borderColor: presetActive ? colors.primary : colors.border,
            borderRadius: colors.radius,
          },
        ]}
      >
        <LinearGradient
          colors={["#f97316", "#ec4899", "#06b6d4"]}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={styles.typeSwatch}
        />
        <View style={{ flex: 1 }}>
          <Text style={[styles.typeLabel, { color: colors.foreground }]}>
            Presets
          </Text>
          <Text style={[styles.typeHint, { color: colors.mutedForeground }]}>
            {presetActive
              ? "Preset background in use"
              : "Ready-made background gallery"}
          </Text>
        </View>
        {presetActive ? (
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
            label="Search presets"
            value={search}
            onChangeText={setSearch}
            hint="Type a preset name, e.g. Gradient 12"
            autoCapitalize="none"
            testID="bg-presets-search"
          />

          {!query && catalogQ.data ? (
            <View style={styles.chipRow}>
              {catalogQ.data.groups.map((g) => {
                const on = group === g.key;
                return (
                  <Pressable
                    {...WEB_FOCUS_RING_PROPS}
                    key={g.key}
                    onPress={() => setGroup(g.key)}
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
                      {g.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          ) : null}

          {catalogQ.isLoading ? (
            <ActivityIndicator color={colors.primary} />
          ) : catalogQ.isError ? (
            <Text style={[styles.empty, { color: colors.destructive }]}>
              Couldn't load presets. Pull back and try again.
            </Text>
          ) : visible.length === 0 ? (
            <Text style={[styles.empty, { color: colors.mutedForeground }]}>
              No presets match "{search.trim()}".
            </Text>
          ) : (
            <View style={styles.grid} testID="bg-presets-grid">
              {visible.map((p) => {
                const on = selectedKey === p.key;
                const saving = pendingKey === p.key && save.isPending;
                return (
                  <Pressable
                    {...WEB_FOCUS_RING_PROPS}
                    key={p.key}
                    testID={`bg-preset-${p.key}`}
                    accessibilityLabel={p.label}
                    disabled={save.isPending}
                    onPress={() => {
                      if (on) return;
                      setPendingKey(p.key);
                      save.mutate(p.key);
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
                    <LinearGradient
                      colors={swatchColors(p)}
                      start={{ x: 0, y: 0 }}
                      end={{ x: 1, y: 1 }}
                      style={styles.swatch}
                    >
                      {saving ? (
                        <ActivityIndicator size="small" color="#ffffff" />
                      ) : on ? (
                        <View style={styles.checkBadge}>
                          <Feather name="check" size={12} color="#ffffff" />
                        </View>
                      ) : null}
                    </LinearGradient>
                    <Text
                      numberOfLines={1}
                      style={[
                        styles.cellLabel,
                        {
                          color: on ? colors.foreground : colors.mutedForeground,
                        },
                      ]}
                    >
                      {p.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          )}

          {save.isError ? (
            <Text style={[styles.empty, { color: colors.destructive }]}>
              Couldn't apply the preset.{" "}
              {(save.error as Error | null)?.message ?? "Please try again."}
            </Text>
          ) : null}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
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
