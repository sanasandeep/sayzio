import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Feather } from "@expo/vector-icons";
import { Stack, useLocalSearchParams } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { DesignLockGate } from "@/components/DesignLockGate";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { type PageSticker } from "@/lib/api/biolinks";
import { getLink, updateLink } from "@/lib/api/links";

const MAX_STICKERS = 10;
const PALETTE = ["😀", "😍", "🤩", "🔥", "✨", "⭐", "💖", "🌈", "🎉", "🚀", "👑", "💎"];

// Basic page-sticker management for mobile. Mirrors the web appearance
// card: add emoji/image stickers, tweak position/tilt/size/layer with
// steppers, reorder and delete. Saves the whole list into
// settings.biolink.stickers (the API replaces the list wholesale and
// re-sanitizes server-side).
export default function StickerSettings() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const linkId = Number(id);
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["link", linkId],
    queryFn: () => getLink(linkId),
    enabled: Number.isFinite(linkId),
  });

  const [stickers, setStickers] = useState<PageSticker[]>([]);
  const [selected, setSelected] = useState<number | null>(null);
  const [customEmoji, setCustomEmoji] = useState("");
  const [imageUrl, setImageUrl] = useState("");
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    if (!q.data || loaded) return;
    const bio = ((q.data.settings as Record<string, any>) ?? {}).biolink ?? {};
    const list = Array.isArray(bio.stickers) ? (bio.stickers as PageSticker[]) : [];
    setStickers(list);
    setSelected(list.length ? 0 : null);
    setLoaded(true);
  }, [q.data, loaded]);

  const save = useMutation({
    mutationFn: () =>
      updateLink(linkId, { settings: { biolink: { stickers } } as any }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["link", linkId] });
    },
  });

  const add = (st: PageSticker) => {
    if (stickers.length >= MAX_STICKERS) return;
    setStickers((prev) => [...prev, st]);
    setSelected(stickers.length);
  };
  const addEmoji = (value: string) => {
    const v = value.trim();
    if (!v) return;
    add({ kind: "emoji", value: v.slice(0, 16), x: 20 + ((stickers.length * 15) % 60), y: 15 + ((stickers.length * 18) % 65), rotation: -12, scale: 1, layer: "front" });
  };
  const addImage = () => {
    const v = imageUrl.trim();
    if (!v || (!/^https?:\/\//i.test(v) && !v.startsWith("/f/"))) return;
    add({ kind: "image", value: v, x: 50, y: 30, rotation: -8, scale: 1, layer: "front" });
    setImageUrl("");
  };
  const patchSelected = (patch: Partial<PageSticker>) => {
    if (selected === null) return;
    setStickers((prev) => prev.map((s, i) => (i === selected ? { ...s, ...patch } : s)));
  };
  const remove = (i: number) => {
    setStickers((prev) => prev.filter((_, j) => j !== i));
    setSelected((cur) => {
      if (cur === null) return null;
      const n = stickers.length - 1;
      if (!n) return null;
      return Math.min(cur > i ? cur - 1 : cur, n - 1);
    });
  };
  const move = (i: number, dir: -1 | 1) => {
    const j = i + dir;
    if (j < 0 || j >= stickers.length) return;
    setStickers((prev) => {
      const copy = [...prev];
      const [item] = copy.splice(i, 1);
      copy.splice(j, 0, item);
      return copy;
    });
    setSelected(j);
  };

  const clamp = (v: number, lo: number, hi: number) => Math.max(lo, Math.min(hi, v));
  const sel = selected !== null ? stickers[selected] : null;

  const stepper = (
    label: string,
    value: string,
    onDec: () => void,
    onInc: () => void,
  ) => (
    <View style={styles.stepperRow}>
      <Text style={[styles.stepperLabel, { color: colors.mutedForeground }]}>{label}</Text>
      <View style={styles.stepperControls}>
        <Pressable style={[styles.stepBtn, { borderColor: colors.border }]} onPress={onDec}>
          <Feather name="minus" size={14} color={colors.foreground} />
        </Pressable>
        <Text style={[styles.stepValue, { color: colors.foreground }]}>{value}</Text>
        <Pressable style={[styles.stepBtn, { borderColor: colors.border }]} onPress={onInc}>
          <Feather name="plus" size={14} color={colors.foreground} />
        </Pressable>
      </View>
    </View>
  );

  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Page stickers" }} />
      <DesignLockGate linkId={linkId}>
        {q.isLoading ? (
          <View style={styles.center}>
            <ActivityIndicator />
          </View>
        ) : (
          <ScrollView contentContainerStyle={styles.content}>
            <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
              Decorate your page with tilted emojis or small images. They float
              over the page and never block taps. Up to {MAX_STICKERS} stickers.
            </Text>

            {stickers.length < MAX_STICKERS ? (
              <>
                <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>Add a sticker</Text>
                <View style={styles.palette}>
                  {PALETTE.map((em) => (
                    <Pressable
                      key={em}
                      style={[styles.paletteBtn, { borderColor: colors.border, backgroundColor: colors.card }]}
                      onPress={() => addEmoji(em)}
                      testID={`sticker-emoji-${em}`}
                    >
                      <Text style={{ fontSize: 20 }}>{em}</Text>
                    </Pressable>
                  ))}
                </View>
                <View style={styles.addRow}>
                  <View style={{ flex: 1 }}>
                    <TextField value={customEmoji} onChangeText={setCustomEmoji} placeholder="Any emoji" />
                  </View>
                  <Button
                    label="Add"
                    variant="secondary"
                    onPress={() => {
                      addEmoji(customEmoji);
                      setCustomEmoji("");
                    }}
                  />
                </View>
                <View style={styles.addRow}>
                  <View style={{ flex: 1 }}>
                    <TextField
                      value={imageUrl}
                      onChangeText={setImageUrl}
                      placeholder="Image URL (https://… or /f/…)"
                      autoCapitalize="none"
                    />
                  </View>
                  <Button label="Add" variant="secondary" onPress={addImage} />
                </View>
              </>
            ) : (
              <Text style={[styles.blurb, { color: "#fbbf24" }]}>
                Sticker limit reached — remove one to add another.
              </Text>
            )}

            {stickers.length ? (
              <>
                <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
                  Stickers ({stickers.length}/{MAX_STICKERS})
                </Text>
                {stickers.map((s, i) => (
                  <Pressable
                    key={i}
                    style={[
                      styles.row,
                      {
                        borderColor: i === selected ? "#3d6bff" : colors.border,
                        backgroundColor: colors.card,
                      },
                    ]}
                    onPress={() => setSelected(i)}
                  >
                    {s.kind === "emoji" ? (
                      <Text style={{ fontSize: 22 }}>{s.value}</Text>
                    ) : (
                      <Image source={{ uri: s.value }} style={{ width: 24, height: 24 }} resizeMode="contain" />
                    )}
                    <Text style={[styles.rowLabel, { color: colors.mutedForeground }]} numberOfLines={1}>
                      {s.kind === "image" ? s.value : s.layer === "back" ? "Behind content" : "Above content"}
                    </Text>
                    <Pressable hitSlop={8} onPress={() => move(i, -1)} disabled={i === 0}>
                      <Feather name="arrow-up" size={14} color={i === 0 ? colors.border : colors.mutedForeground} />
                    </Pressable>
                    <Pressable hitSlop={8} onPress={() => move(i, 1)} disabled={i === stickers.length - 1}>
                      <Feather name="arrow-down" size={14} color={i === stickers.length - 1 ? colors.border : colors.mutedForeground} />
                    </Pressable>
                    <Pressable hitSlop={8} onPress={() => remove(i)} testID={`sticker-remove-${i}`}>
                      <Feather name="trash-2" size={14} color="#f87171" />
                    </Pressable>
                  </Pressable>
                ))}
              </>
            ) : null}

            {sel ? (
              <>
                <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>Adjust selected</Text>
                {stepper("Horizontal", `${Math.round(sel.x)}%`, () => patchSelected({ x: clamp(sel.x - 5, 0, 100) }), () => patchSelected({ x: clamp(sel.x + 5, 0, 100) }))}
                {stepper("Vertical", `${Math.round(sel.y)}%`, () => patchSelected({ y: clamp(sel.y - 5, 0, 100) }), () => patchSelected({ y: clamp(sel.y + 5, 0, 100) }))}
                {stepper("Tilt", `${sel.rotation}°`, () => patchSelected({ rotation: clamp(sel.rotation - 15, -180, 180) }), () => patchSelected({ rotation: clamp(sel.rotation + 15, -180, 180) }))}
                {stepper("Size", `${Math.round(sel.scale * 100)}%`, () => patchSelected({ scale: clamp(Math.round((sel.scale - 0.2) * 100) / 100, 0.4, 3) }), () => patchSelected({ scale: clamp(Math.round((sel.scale + 0.2) * 100) / 100, 0.4, 3) }))}
                <View style={styles.layerRow}>
                  {(["front", "back"] as const).map((layer) => (
                    <Pressable
                      key={layer}
                      style={[
                        styles.layerBtn,
                        {
                          borderColor: sel.layer === layer ? "#3d6bff" : colors.border,
                          backgroundColor: sel.layer === layer ? "rgba(61,107,255,0.15)" : colors.card,
                        },
                      ]}
                      onPress={() => patchSelected({ layer })}
                    >
                      <Text style={{ fontSize: 12, color: sel.layer === layer ? "#7d9bff" : colors.mutedForeground }}>
                        {layer === "front" ? "Above content" : "Behind content"}
                      </Text>
                    </Pressable>
                  ))}
                </View>
              </>
            ) : null}

            <Button
              label={save.isPending ? "Saving…" : "Save stickers"}
              onPress={() => save.mutate()}
              disabled={save.isPending}
              testID="stickers-save"
            />
            {save.isSuccess ? (
              <Text style={{ color: "#34d399", fontSize: 12, textAlign: "center" }}>Saved</Text>
            ) : null}
            {save.isError ? (
              <Text style={{ color: "#f87171", fontSize: 12, textAlign: "center" }}>
                Could not save — please try again.
              </Text>
            ) : null}
          </ScrollView>
        )}
      </DesignLockGate>
    </>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  content: { padding: 16, gap: 10 },
  blurb: { fontSize: 13, lineHeight: 18 },
  sectionLabel: { fontSize: 12, fontWeight: "700", textTransform: "uppercase", marginTop: 8 },
  palette: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  paletteBtn: {
    width: 40,
    height: 40,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  addRow: { flexDirection: "row", gap: 8, alignItems: "center" },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  rowLabel: { flex: 1, fontSize: 12 },
  stepperRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  stepperLabel: { fontSize: 13 },
  stepperControls: { flexDirection: "row", alignItems: "center", gap: 10 },
  stepBtn: {
    width: 30,
    height: 30,
    borderRadius: 8,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  stepValue: { fontSize: 13, fontVariant: ["tabular-nums"], minWidth: 48, textAlign: "center" },
  layerRow: { flexDirection: "row", gap: 8 },
  layerBtn: { flex: 1, borderWidth: 1, borderRadius: 10, paddingVertical: 10, alignItems: "center" },
});
