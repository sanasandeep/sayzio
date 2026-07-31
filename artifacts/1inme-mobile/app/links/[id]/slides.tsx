import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as ImagePicker from "expo-image-picker";
import { Stack, useLocalSearchParams } from "expo-router";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { BlockSettingsEditor } from "@/app/links/[id]/blocks/[blockId]";
import { Button } from "@/components/Button";
import { ColorSwatchRow } from "@/components/ColorSwatchRow";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { createBlock } from "@/lib/api/blocks";
import {
  listVaultFiles,
  uploadVaultFile,
  type VaultFile,
} from "@/lib/api/files";
import {
  getSlideDeck,
  saveSlideDeck,
  type DeckSlide,
  type SlideDeckBackground,
  type SlideDeckEditor,
} from "@/lib/api/slides";

function bgSummary(bg: DeckSlide["background"]): string {
  const t = bg?.type ?? "color";
  if (t === "color") return `Color ${bg?.color ?? "#0f172a"}`;
  if (t === "gradient")
    return `Gradient ${bg?.from_color ?? ""} → ${bg?.to_color ?? ""}`.trim();
  if (t === "image") return "Image";
  if (t === "slideshow") return `Slideshow (${bg?.images?.length ?? 0} images)`;
  if (t === "video") return "Video";
  if (t === "template") return "Template";
  return t;
}

export default function SlidesEditorScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useQuery({
    queryKey: ["slides-deck", id],
    queryFn: () => getSlideDeck(id),
    enabled: Number.isFinite(id),
  });

  const [slides, setSlides] = useState<DeckSlide[]>([]);
  // Auto-play: deck settings.auto_advance is milliseconds; 0 = off. The UI
  // exposes a toggle + a seconds field, mirroring the web slides editor.
  const [autoOn, setAutoOn] = useState(false);
  // In-place block editing (Task #6391): tapping an attached block row
  // expands the shared BlockSettingsEditor inline beneath it.
  const [expandedBlockId, setExpandedBlockId] = useState<number | null>(null);
  const [autoSeconds, setAutoSeconds] = useState("5");
  const [loop, setLoop] = useState(false);
  const [published, setPublished] = useState(false);
  const [saved, setSaved] = useState(false);
  const [pickerFor, setPickerFor] = useState<
    { slide: number; kind: "attach" | "create" } | null
  >(null);
  const [creatingType, setCreatingType] = useState<string | null>(null);
  // Which slide's background is open in the visual background editor.
  const [bgEditorFor, setBgEditorFor] = useState<number | null>(null);

  // Hydrate local editor state from the server only when explicitly allowed
  // (initial load and right after a successful save). Background refetches
  // must never clobber unsaved local edits.
  const hydratedRef = useRef(false);

  useEffect(() => {
    const d = q.data;
    if (!d || hydratedRef.current) return;
    hydratedRef.current = true;
    setSlides(
      d.deck.slides.map((s) => ({
        ...s,
        block_ids: [...(s.block_ids ?? [])],
        background: { ...(s.background ?? { type: "color", color: "#0f172a" }) },
      })),
    );
    const adv = Number(d.deck.settings?.auto_advance ?? 0);
    setAutoOn(adv > 0);
    setAutoSeconds(String(adv > 0 ? Math.round(adv / 100) / 10 : 5));
    setLoop(!!d.deck.settings?.loop);
    setPublished(!!d.deck.is_published);
  }, [q.data]);

  const meta = q.data?.meta;
  const blockById = useMemo(() => {
    const m = new Map<number, { type: string; label: string | null }>();
    (meta?.blocks ?? []).forEach((b) => m.set(b.id, b));
    return m;
  }, [meta?.blocks]);

  const save = useMutation({
    mutationFn: () => {
      if (!slides.length) throw new Error("Add at least one slide.");
      const secs = Math.max(0, Math.min(60, Number(autoSeconds) || 0));
      return saveSlideDeck(id, {
        settings: {
          ...(q.data?.deck.settings ?? {}),
          auto_advance: autoOn ? Math.round(secs * 1000) : 0,
          loop,
        },
        is_published: published,
        slides,
      });
    },
    onSuccess: (data: SlideDeckEditor) => {
      // Rehydrate from the saved payload (slides now carry server ids).
      hydratedRef.current = false;
      qc.setQueryData(["slides-deck", id], data);
      qc.invalidateQueries({ queryKey: ["link", id] });
      setSaved(true);
      setTimeout(() => setSaved(false), 2500);
    },
  });

  const updateSlideAt = (i: number, fn: (s: DeckSlide) => DeckSlide) =>
    setSlides((prev) => prev.map((s, idx) => (idx === i ? fn(s) : s)));

  const addSlide = () =>
    setSlides((prev) => [
      ...prev,
      {
        title: `Slide ${prev.length + 1}`,
        block_ids: [],
        block_settings: {},
        background: { type: "color", color: "#0f172a" },
        animation: { enter: "fade", duration_ms: 400 },
        transition: "slide",
        settings: {},
      },
    ]);

  const removeSlideAt = (i: number) =>
    setSlides((prev) => prev.filter((_, idx) => idx !== i));

  const moveSlide = (i: number, dir: -1 | 1) =>
    setSlides((prev) => {
      const j = i + dir;
      if (j < 0 || j >= prev.length) return prev;
      const next = [...prev];
      [next[i], next[j]] = [next[j], next[i]];
      return next;
    });

  // Background parity actions (mirror the web editor's copy-from-previous
  // and apply-to-all buttons): deep-clone so slides never share one object.
  const copyBgFromPrevious = (i: number) => {
    if (i <= 0) return;
    const prevBg = slides[i - 1]?.background ?? {};
    updateSlideAt(i, (s) => ({
      ...s,
      background: JSON.parse(JSON.stringify(prevBg)),
    }));
  };

  const applyBgToAll = (i: number) => {
    const bg = slides[i]?.background ?? {};
    setSlides((prev) =>
      prev.map((s, idx) =>
        idx === i ? s : { ...s, background: JSON.parse(JSON.stringify(bg)) },
      ),
    );
  };

  const attachBlock = (slideIdx: number, blockId: number) => {
    updateSlideAt(slideIdx, (s) =>
      s.block_ids.includes(blockId) || s.block_ids.length >= 10
        ? s
        : { ...s, block_ids: [...s.block_ids, blockId] },
    );
    setPickerFor(null);
  };

  const detachBlock = (slideIdx: number, blockId: number) =>
    updateSlideAt(slideIdx, (s) => ({
      ...s,
      block_ids: s.block_ids.filter((b) => b !== blockId),
    }));

  // In-slide block creation: POST /links/{id}/blocks (same endpoint + plan
  // gating as the biolink editor) then attach the new block to the slide.
  const createAndAttach = async (slideIdx: number, type: string) => {
    if (creatingType) return;
    setCreatingType(type);
    try {
      const block = await createBlock(id, { type });
      updateSlideAt(slideIdx, (s) =>
        s.block_ids.length >= 10
          ? s
          : { ...s, block_ids: [...s.block_ids, block.id] },
      );
      // Patch the cached meta so the new block shows in the attach list and
      // labels resolve — WITHOUT refetching, which would discard local edits.
      qc.setQueryData<SlideDeckEditor>(["slides-deck", id], (old) =>
        old
          ? {
              ...old,
              meta: {
                ...old.meta,
                blocks: [
                  ...old.meta.blocks,
                  { id: block.id, type: block.type, label: null },
                ],
              },
            }
          : old,
      );
      setPickerFor(null);
    } finally {
      setCreatingType(null);
    }
  };

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Slides" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (q.error || !q.data) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Slides" }} />
        <Text style={{ color: colors.destructive }}>
          Couldn't load the slide deck.
        </Text>
      </View>
    );
  }

  const creatable = meta?.creatable_types ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Edit slides" }} />
      <ScrollView contentContainerStyle={styles.body}>
        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Playback
          </Text>
          <View
            style={[
              styles.card,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <View style={styles.rowBetween}>
              <View style={{ flex: 1, paddingRight: 12 }}>
                <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                  Auto-play
                </Text>
                <Text style={[styles.hint, { color: colors.mutedForeground }]}>
                  Advance slides automatically.
                </Text>
              </View>
              <Switch
                value={autoOn}
                onValueChange={setAutoOn}
                trackColor={{ true: colors.primary, false: colors.border }}
              />
            </View>
            {autoOn ? (
              <TextField
                label="Seconds per slide"
                value={autoSeconds}
                onChangeText={setAutoSeconds}
                keyboardType="numeric"
                placeholder="5"
              />
            ) : null}
            <View style={styles.rowBetween}>
              <View style={{ flex: 1, paddingRight: 12 }}>
                <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                  Loop
                </Text>
                <Text style={[styles.hint, { color: colors.mutedForeground }]}>
                  Restart from the first slide at the end.
                </Text>
              </View>
              <Switch
                value={loop}
                onValueChange={setLoop}
                trackColor={{ true: colors.primary, false: colors.border }}
              />
            </View>
            <View style={styles.rowBetween}>
              <View style={{ flex: 1, paddingRight: 12 }}>
                <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                  Published
                </Text>
                <Text style={[styles.hint, { color: colors.mutedForeground }]}>
                  Visitors see the latest published version.
                </Text>
              </View>
              <Switch
                value={published}
                onValueChange={setPublished}
                trackColor={{ true: colors.primary, false: colors.border }}
              />
            </View>
          </View>
        </View>

        <View style={styles.section}>
          <View style={styles.rowBetween}>
            <Text
              style={[styles.sectionLabel, { color: colors.mutedForeground }]}
            >
              Slides ({slides.length})
            </Text>
            <Pressable onPress={addSlide} hitSlop={8} style={styles.addLink}>
              <Feather name="plus" size={14} color={colors.primary} />
              <Text style={[styles.addLinkText, { color: colors.primary }]}>
                Add slide
              </Text>
            </Pressable>
          </View>

          {slides.map((s, i) => (
            <View
              key={s.id ?? `new-${i}`}
              style={[
                styles.card,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <View style={styles.rowBetween}>
                <Text style={[styles.cardTag, { color: colors.primary }]}>
                  Slide {i + 1}
                </Text>
                <View style={styles.rowCenter}>
                  <Pressable
                    onPress={() => moveSlide(i, -1)}
                    hitSlop={8}
                    disabled={i === 0}
                  >
                    <Feather
                      name="arrow-up"
                      size={16}
                      color={i === 0 ? colors.border : colors.mutedForeground}
                    />
                  </Pressable>
                  <Pressable
                    onPress={() => moveSlide(i, 1)}
                    hitSlop={8}
                    disabled={i === slides.length - 1}
                  >
                    <Feather
                      name="arrow-down"
                      size={16}
                      color={
                        i === slides.length - 1
                          ? colors.border
                          : colors.mutedForeground
                      }
                    />
                  </Pressable>
                  {slides.length > 1 ? (
                    <Pressable onPress={() => removeSlideAt(i)} hitSlop={8}>
                      <Feather
                        name="trash-2"
                        size={16}
                        color={colors.destructive}
                      />
                    </Pressable>
                  ) : null}
                </View>
              </View>

              <TextField
                label="Title"
                value={s.title ?? ""}
                onChangeText={(v) =>
                  updateSlideAt(i, (sl) => ({ ...sl, title: v }))
                }
                placeholder={`Slide ${i + 1}`}
              />

              <View style={styles.subSection}>
                <Text style={[styles.subTitle, { color: colors.foreground }]}>
                  Background
                </Text>
                <Pressable
                  testID={`slide-${i}-bg-open`}
                  accessibilityRole="button"
                  accessibilityLabel={`Edit background for slide ${i + 1}`}
                  onPress={() => setBgEditorFor(i)}
                  style={[
                    styles.bgRow,
                    { borderColor: colors.border, borderRadius: colors.radius },
                  ]}
                >
                  <BgPreviewSwatch background={s.background} />
                  <Text
                    style={[
                      styles.hint,
                      { color: colors.foreground, flex: 1 },
                    ]}
                    numberOfLines={1}
                  >
                    {bgSummary(s.background)}
                  </Text>
                  <Feather
                    name="edit-2"
                    size={14}
                    color={colors.mutedForeground}
                  />
                </Pressable>
                <View style={styles.row2}>
                  {i > 0 ? (
                    <Pressable
                      onPress={() => copyBgFromPrevious(i)}
                      style={[
                        styles.smallBtn,
                        { borderColor: colors.border, borderRadius: 999 },
                      ]}
                    >
                      <Feather
                        name="copy"
                        size={13}
                        color={colors.foreground}
                      />
                      <Text
                        style={[styles.smallBtnText, { color: colors.foreground }]}
                      >
                        Copy previous
                      </Text>
                    </Pressable>
                  ) : null}
                  {slides.length > 1 ? (
                    <Pressable
                      onPress={() => applyBgToAll(i)}
                      style={[
                        styles.smallBtn,
                        { borderColor: colors.border, borderRadius: 999 },
                      ]}
                    >
                      <Feather
                        name="layers"
                        size={13}
                        color={colors.foreground}
                      />
                      <Text
                        style={[styles.smallBtnText, { color: colors.foreground }]}
                      >
                        Apply to all
                      </Text>
                    </Pressable>
                  ) : null}
                </View>
              </View>

              <View style={styles.subSection}>
                <Text style={[styles.subTitle, { color: colors.foreground }]}>
                  Blocks ({s.block_ids.length}/10)
                </Text>
                {s.block_ids.length === 0 ? (
                  <Text
                    style={[styles.hint, { color: colors.mutedForeground }]}
                  >
                    No blocks on this slide yet.
                  </Text>
                ) : (
                  s.block_ids.map((bid) => {
                    const b = blockById.get(bid);
                    const expanded = expandedBlockId === bid;
                    return (
                      <View key={bid}>
                        <View
                          style={[
                            styles.blockRow,
                            {
                              borderColor: expanded
                                ? colors.primary
                                : colors.border,
                              borderRadius: colors.radius,
                            },
                          ]}
                        >
                          <Pressable
                            style={{ flex: 1 }}
                            onPress={() =>
                              setExpandedBlockId(expanded ? null : bid)
                            }
                          >
                            <Text
                              style={[
                                styles.blockType,
                                { color: colors.foreground },
                              ]}
                            >
                              {b?.type ?? `Block #${bid}`}
                            </Text>
                            {b?.label ? (
                              <Text
                                numberOfLines={1}
                                style={[
                                  styles.hint,
                                  { color: colors.mutedForeground },
                                ]}
                              >
                                {b.label}
                              </Text>
                            ) : null}
                          </Pressable>
                          <Pressable
                            onPress={() =>
                              setExpandedBlockId(expanded ? null : bid)
                            }
                            hitSlop={8}
                            style={{ marginRight: 12 }}
                          >
                            <Feather
                              name={expanded ? "chevron-up" : "edit-2"}
                              size={15}
                              color={expanded ? colors.primary : colors.mutedForeground}
                            />
                          </Pressable>
                          <Pressable onPress={() => detachBlock(i, bid)} hitSlop={8}>
                            <Feather name="x" size={16} color={colors.destructive} />
                          </Pressable>
                        </View>
                        {expanded ? (
                          <View
                            style={[
                              styles.inlineEditor,
                              {
                                borderColor: colors.border,
                                borderBottomLeftRadius: colors.radius,
                                borderBottomRightRadius: colors.radius,
                              },
                            ]}
                          >
                            <BlockSettingsEditor
                              inline
                              linkId={id}
                              blockId={bid}
                              onDone={() => {
                                setExpandedBlockId(null);
                                qc.invalidateQueries({
                                  queryKey: ["slides-deck", id],
                                });
                              }}
                            />
                          </View>
                        ) : null}
                      </View>
                    );
                  })
                )}
                <View style={styles.row2}>
                  <Pressable
                    onPress={() => setPickerFor({ slide: i, kind: "attach" })}
                    style={[
                      styles.smallBtn,
                      { borderColor: colors.border, borderRadius: 999 },
                    ]}
                  >
                    <Feather name="link" size={13} color={colors.foreground} />
                    <Text
                      style={[styles.smallBtnText, { color: colors.foreground }]}
                    >
                      Attach block
                    </Text>
                  </Pressable>
                  {creatable.length ? (
                    <Pressable
                      onPress={() => setPickerFor({ slide: i, kind: "create" })}
                      style={[
                        styles.smallBtn,
                        { borderColor: colors.primary, borderRadius: 999 },
                      ]}
                    >
                      <Feather name="plus" size={13} color={colors.primary} />
                      <Text
                        style={[styles.smallBtnText, { color: colors.primary }]}
                      >
                        New block
                      </Text>
                    </Pressable>
                  ) : null}
                </View>
              </View>
            </View>
          ))}
        </View>

        <Button
          label={save.isPending ? "Saving…" : saved ? "Saved" : "Save deck"}
          onPress={() => save.mutate()}
          disabled={save.isPending}
        />
        {save.error ? (
          <Text style={{ color: colors.destructive }}>
            {(save.error as Error).message || "Couldn't save the deck."}
          </Text>
        ) : null}
      </ScrollView>

      <Modal
        visible={pickerFor != null}
        transparent
        animationType="fade"
        onRequestClose={() => setPickerFor(null)}
      >
        <Pressable
          style={styles.modalBackdrop}
          onPress={() => setPickerFor(null)}
        >
          <Pressable
            style={[
              styles.modalSheet,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
            onPress={() => {}}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              {pickerFor?.kind === "create" ? "New block" : "Attach a block"}
            </Text>
            <ScrollView style={{ maxHeight: 420 }}>
              {pickerFor?.kind === "create"
                ? creatable.map((t) => (
                    <Pressable
                      key={t.type}
                      style={styles.optionRow}
                      disabled={creatingType != null}
                      onPress={() => createAndAttach(pickerFor.slide, t.type)}
                    >
                      <Text
                        style={[styles.optionText, { color: colors.foreground }]}
                      >
                        {t.label}
                      </Text>
                      {creatingType === t.type ? (
                        <ActivityIndicator size="small" color={colors.primary} />
                      ) : (
                        <Feather
                          name="plus"
                          size={16}
                          color={colors.mutedForeground}
                        />
                      )}
                    </Pressable>
                  ))
                : (meta?.blocks ?? [])
                    .filter(
                      (b) =>
                        pickerFor == null ||
                        !slides[pickerFor.slide]?.block_ids.includes(b.id),
                    )
                    .map((b) => (
                      <Pressable
                        key={b.id}
                        style={styles.optionRow}
                        onPress={() =>
                          pickerFor && attachBlock(pickerFor.slide, b.id)
                        }
                      >
                        <View style={{ flex: 1, paddingRight: 10 }}>
                          <Text
                            style={[
                              styles.optionText,
                              { color: colors.foreground },
                            ]}
                          >
                            {b.type}
                          </Text>
                          {b.label ? (
                            <Text
                              numberOfLines={1}
                              style={[
                                styles.hint,
                                { color: colors.mutedForeground },
                              ]}
                            >
                              {b.label}
                            </Text>
                          ) : null}
                        </View>
                        <Feather
                          name="link"
                          size={16}
                          color={colors.mutedForeground}
                        />
                      </Pressable>
                    ))}
              {pickerFor?.kind === "attach" && !(meta?.blocks ?? []).length ? (
                <Text style={[styles.hint, { color: colors.mutedForeground }]}>
                  This page has no blocks yet — create one instead.
                </Text>
              ) : null}
            </ScrollView>
          </Pressable>
        </Pressable>
      </Modal>

      <BackgroundEditorModal
        visible={bgEditorFor != null}
        slideNumber={(bgEditorFor ?? 0) + 1}
        background={
          (bgEditorFor != null ? slides[bgEditorFor]?.background : null) ?? {}
        }
        onClose={() => setBgEditorFor(null)}
        onChange={(bg) => {
          if (bgEditorFor == null) return;
          updateSlideAt(bgEditorFor, (s) => ({ ...s, background: bg }));
        }}
      />
    </View>
  );
}

/** Small visual preview of a slide background (color / gradient / image). */
function BgPreviewSwatch({ background }: { background: SlideDeckBackground }) {
  const colors = useColors();
  const t = background?.type ?? "color";
  if (t === "image" && background?.image_url) {
    return (
      <Image
        source={{ uri: String(background.image_url) }}
        style={[styles.bgSwatch, { borderColor: colors.border }]}
      />
    );
  }
  if (t === "gradient") {
    return (
      <View style={[styles.bgSwatch, { borderColor: colors.border }]}>
        <View
          style={{
            flex: 1,
            backgroundColor: background?.from_color ?? "#0f172a",
          }}
        />
        <View
          style={{
            flex: 1,
            backgroundColor: background?.to_color ?? "#3d6bff",
          }}
        />
      </View>
    );
  }
  return (
    <View
      style={[
        styles.bgSwatch,
        {
          borderColor: colors.border,
          backgroundColor:
            t === "color" ? (background?.color ?? "#0f172a") : colors.muted,
        },
      ]}
    />
  );
}

const EDITABLE_BG_TYPES = [
  { type: "color", label: "Color" },
  { type: "gradient", label: "Gradient" },
  { type: "image", label: "Image" },
] as const;

/**
 * Visual per-slide background editor: switch type (color / gradient /
 * image) and pick values in place. Changes apply live to the local slide
 * state and persist through the existing "Save deck" PUT
 * /links/{id}/slides call — the server validates types/colors and
 * sanitizes media URLs (SlideDeckController::saveRules /
 * sanitizeSaveData), matching the web editor exactly.
 */
function BackgroundEditorModal({
  visible,
  slideNumber,
  background,
  onClose,
  onChange,
}: {
  visible: boolean;
  slideNumber: number;
  background: SlideDeckBackground;
  onClose: () => void;
  onChange: (bg: SlideDeckBackground) => void;
}) {
  const colors = useColors();
  const t = background.type ?? "color";
  const editable = t === "color" || t === "gradient" || t === "image";

  // Vault images for the image tab: fetched lazily the first time the
  // image tab is visible, then cached for the modal's lifetime.
  const [vault, setVault] = useState<VaultFile[] | null>(null);
  const [vaultLoading, setVaultLoading] = useState(false);
  const [vaultError, setVaultError] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  useEffect(() => {
    if (!visible || t !== "image" || vault !== null || vaultLoading) return;
    let cancelled = false;
    setVaultLoading(true);
    setVaultError(false);
    listVaultFiles({ type: "image", perPage: 24 })
      .then((res) => {
        if (!cancelled) setVault(res.files);
      })
      .catch(() => {
        if (!cancelled) setVaultError(true);
      })
      .finally(() => {
        if (!cancelled) setVaultLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [visible, t, vault, vaultLoading]);

  const set = (patch: Partial<SlideDeckBackground>) =>
    onChange({ ...background, ...patch });

  const switchType = (next: "color" | "gradient" | "image") => {
    // Seed sensible defaults for the target type without discarding the
    // other keys, so toggling back and forth never loses picked values.
    const patch: Partial<SlideDeckBackground> = { type: next };
    if (next === "color" && !background.color) patch.color = "#0f172a";
    if (next === "gradient") {
      if (!background.from_color) patch.from_color = "#0f172a";
      if (!background.to_color) patch.to_color = "#3d6bff";
    }
    set(patch);
  };

  const uploadFromDevice = async () => {
    if (uploading) return;
    setUploadError(null);
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      setUploadError("Photo library permission is needed to upload.");
      return;
    }
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.9,
    });
    if (res.canceled || !res.assets?.length) return;
    setUploading(true);
    try {
      const asset = res.assets[0];
      const file = await uploadVaultFile({
        uri: asset.uri,
        name: asset.fileName ?? undefined,
        mime: asset.mimeType ?? undefined,
      });
      setVault((prev) => [file, ...(prev ?? [])]);
      set({ type: "image", image_url: file.url });
    } catch (e) {
      setUploadError(
        (e as { message?: string })?.message || "Upload failed.",
      );
    } finally {
      setUploading(false);
    }
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
    >
      <Pressable style={styles.modalBackdrop} onPress={onClose}>
        <Pressable
          style={[
            styles.modalSheet,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
          onPress={() => {}}
        >
          <View style={styles.rowBetween}>
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              Slide {slideNumber} background
            </Text>
            <Pressable onPress={onClose} hitSlop={8} testID="bg-editor-close">
              <Feather name="x" size={18} color={colors.mutedForeground} />
            </Pressable>
          </View>

          <ScrollView style={{ maxHeight: 460 }} contentContainerStyle={{ gap: 12 }}>
            <View style={styles.row2}>
              {EDITABLE_BG_TYPES.map((opt) => {
                const active = t === opt.type;
                return (
                  <Pressable
                    key={opt.type}
                    testID={`bg-type-${opt.type}`}
                    onPress={() => switchType(opt.type)}
                    style={[
                      styles.smallBtn,
                      {
                        borderColor: active ? colors.primary : colors.border,
                        backgroundColor: active
                          ? `${colors.primary}22`
                          : "transparent",
                        borderRadius: 999,
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.smallBtnText,
                        {
                          color: active ? colors.primary : colors.foreground,
                        },
                      ]}
                    >
                      {opt.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            {!editable ? (
              <Text style={[styles.hint, { color: colors.mutedForeground }]}>
                This slide uses a {t} background, which is set up in the web
                editor. Picking a type above replaces it.
              </Text>
            ) : null}

            {t === "color" ? (
              <View style={{ gap: 8 }}>
                <ColorSwatchRow
                  prefix="slide-bg-color-swatch"
                  value={String(background.color ?? "")}
                  onPick={(c) => set({ color: c })}
                />
                <TextField
                  label="Color"
                  value={String(background.color ?? "")}
                  onChangeText={(v) => set({ color: v })}
                  placeholder="#0f172a"
                  autoCapitalize="none"
                />
              </View>
            ) : null}

            {t === "gradient" ? (
              <View style={{ gap: 8 }}>
                <ColorSwatchRow
                  prefix="slide-bg-from-swatch"
                  value={String(background.from_color ?? "")}
                  onPick={(c) => set({ from_color: c })}
                />
                <TextField
                  label="From color"
                  value={String(background.from_color ?? "")}
                  onChangeText={(v) => set({ from_color: v })}
                  placeholder="#0f172a"
                  autoCapitalize="none"
                />
                <ColorSwatchRow
                  prefix="slide-bg-to-swatch"
                  value={String(background.to_color ?? "")}
                  onPick={(c) => set({ to_color: c })}
                />
                <TextField
                  label="To color"
                  value={String(background.to_color ?? "")}
                  onChangeText={(v) => set({ to_color: v })}
                  placeholder="#3d6bff"
                  autoCapitalize="none"
                />
              </View>
            ) : null}

            {t === "image" ? (
              <View style={{ gap: 10 }}>
                <TextField
                  label="Image URL"
                  value={String(background.image_url ?? "")}
                  onChangeText={(v) => set({ image_url: v })}
                  placeholder="https://…"
                  autoCapitalize="none"
                />
                <View style={styles.row2}>
                  <Pressable
                    testID="bg-image-upload"
                    onPress={uploadFromDevice}
                    disabled={uploading}
                    style={[
                      styles.smallBtn,
                      { borderColor: colors.primary, borderRadius: 999 },
                    ]}
                  >
                    {uploading ? (
                      <ActivityIndicator size="small" color={colors.primary} />
                    ) : (
                      <Feather name="upload" size={13} color={colors.primary} />
                    )}
                    <Text
                      style={[styles.smallBtnText, { color: colors.primary }]}
                    >
                      {uploading ? "Uploading…" : "Upload image"}
                    </Text>
                  </Pressable>
                </View>
                {uploadError ? (
                  <Text style={[styles.hint, { color: colors.destructive }]}>
                    {uploadError}
                  </Text>
                ) : null}
                <Text
                  style={[styles.subTitle, { color: colors.foreground }]}
                >
                  From your files
                </Text>
                {vaultLoading ? (
                  <ActivityIndicator size="small" color={colors.primary} />
                ) : vaultError ? (
                  <Text
                    style={[styles.hint, { color: colors.mutedForeground }]}
                  >
                    Couldn't load your files.
                  </Text>
                ) : vault && vault.length > 0 ? (
                  <View style={styles.vaultGrid}>
                    {vault.map((f) => {
                      const sel = background.image_url === f.url;
                      return (
                        <Pressable
                          key={f.id}
                          testID={`bg-vault-${f.id}`}
                          accessibilityRole="button"
                          accessibilityLabel={`Use image ${f.original_name}`}
                          onPress={() =>
                            set({ type: "image", image_url: f.url })
                          }
                          style={[
                            styles.vaultThumbWrap,
                            {
                              borderColor: sel
                                ? colors.primary
                                : colors.border,
                              borderWidth: sel ? 2 : 1,
                            },
                          ]}
                        >
                          <Image
                            source={{ uri: f.url }}
                            style={styles.vaultThumb}
                          />
                        </Pressable>
                      );
                    })}
                  </View>
                ) : (
                  <Text
                    style={[styles.hint, { color: colors.mutedForeground }]}
                  >
                    No images in your files yet — upload one above.
                  </Text>
                )}
              </View>
            ) : null}
          </ScrollView>

          <Button label="Done" onPress={onClose} />
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 18, paddingBottom: 64 },
  section: { gap: 10 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  card: { padding: 14, borderWidth: 1, gap: 10 },
  cardTag: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  rowBetween: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  rowCenter: { flexDirection: "row", alignItems: "center", gap: 14 },
  rowLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  addLink: { flexDirection: "row", alignItems: "center", gap: 4 },
  addLinkText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  subSection: {
    gap: 8,
    marginTop: 6,
    paddingTop: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "rgba(127,127,127,0.25)",
  },
  subTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  hint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11,
    lineHeight: 16,
  },
  row2: { flexDirection: "row", gap: 10, flexWrap: "wrap" },
  smallBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 7,
  },
  smallBtnText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  blockRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  blockType: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  modalBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "center",
    padding: 24,
  },
  modalSheet: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
    gap: 8,
  },
  modalTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    marginBottom: 4,
  },
  optionRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingVertical: 12,
    paddingHorizontal: 4,
  },
  optionText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14 },
  bgRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  bgSwatch: {
    width: 28,
    height: 28,
    borderRadius: 8,
    borderWidth: 1,
    overflow: "hidden",
    flexDirection: "row",
  },
  vaultGrid: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  vaultThumbWrap: { borderRadius: 8, overflow: "hidden" },
  vaultThumb: { width: 64, height: 64 },
  inlineEditor: {
    borderWidth: 1,
    borderTopWidth: 0,
    padding: 12,
  },
});
