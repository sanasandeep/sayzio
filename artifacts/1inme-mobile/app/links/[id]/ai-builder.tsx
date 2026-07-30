import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery } from "@tanstack/react-query";
import {
  Stack,
  useFocusEffect,
  useLocalSearchParams,
  useRouter,
} from "expo-router";
import * as ImagePicker from "expo-image-picker";
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

import { Button } from "@/components/Button";
import { CoinCostHint, insufficientCoins } from "@/components/CoinCostHint";
import { DictationMic } from "@/components/DictationMic";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { handlePlanLockedError } from "@/lib/upgradePrompt";
import {
  estimateAiBuilder,
  generateAiBuilder,
  getAiBuilderIntake,
  importAiBuilderImages,
  previewAiBuilderImages,
  searchAiBuilderImages,
  type AiBuilderImagePreview,
  type AiBuilderImageResult,
  type AiBuilderIntake,
  type AiBuilderPayload,
} from "@/lib/api/aiBuilder";
import { uploadWizardImage } from "@/lib/api/wizard";
import { showAlert } from "@/lib/webAlert";

/**
 * "Build my Link in Bio with AI" — mobile parity for the web
 * links/{link}/ai-builder flow. The creator describes the page they want,
 * optionally attaches images (uploaded to their vault) and destination links,
 * and the server's AiBiolinkBuilderService assembles a full page and REPLACES
 * this biolink's blocks. The coin charge (auto-refunded on parse failure)
 * and the curated, plan-allowed block subset live server-side. When the
 * creator's plan unlocks On-Brand AI, a toggle injects their Brand Kit voice.
 */
export default function AiBuilderScreen() {
  const colors = useColors();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const linkId = Number(id);

  const [description, setDescription] = useState("");
  const [images, setImages] = useState<string[]>([]);
  const [linksText, setLinksText] = useState("");
  const [useBrandKit, setUseBrandKit] = useState(true);
  const [uploading, setUploading] = useState(false);
  // Inline upload failure message (quota exceeded, oversized file, network…).
  // Rendered next to the upload control instead of a transient alert so the
  // creator is never stranded on a silent failure — cleared on the next
  // attempt and on success, mirroring the web intake's uploadError.
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [estimate, setEstimate] = useState<number | null>(null);
  // Auto-sourced image preview (Task #5722): the creator can review the
  // images the builder would use (extracted from links, or AI-generated)
  // and deselect any before the paid build runs.
  const [preview, setPreview] = useState<AiBuilderImagePreview | null>(null);
  const [previewing, setPreviewing] = useState(false);
  const [removedExtracted, setRemovedExtracted] = useState<string[]>([]);
  const [skippedSlots, setSkippedSlots] = useState<string[]>([]);

  // Google image search picker (only rendered when the server reports the
  // admin has configured search keys — preview mode hides it entirely).
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const [searchResults, setSearchResults] = useState<AiBuilderImageResult[]>(
    [],
  );
  const [searchDisclaimer, setSearchDisclaimer] = useState("");
  const [selectedCandidates, setSelectedCandidates] = useState<string[]>([]);
  // Set when a search fails because the admin removed/disabled the Google CSE
  // keys mid-session (`image_search_unavailable`): the picker collapses
  // instead of staying retryable forever, and the intake is refetched so the
  // server's fresh `image_search_enabled` flag takes over.
  const [searchUnavailable, setSearchUnavailable] = useState(false);

  const intakeQ = useQuery<AiBuilderIntake>({
    queryKey: ["ai-builder-intake", linkId],
    queryFn: () => getAiBuilderIntake(linkId),
    enabled: Number.isFinite(linkId),
  });

  const intake = intakeQ.data;

  // Inverse of the mid-session collapse: when a refetched intake reports the
  // admin re-added the search keys (`image_search_enabled: true` again), the
  // picker reappears without a remount.
  useEffect(() => {
    if (intake?.image_search_enabled) setSearchUnavailable(false);
  }, [intake?.image_search_enabled]);

  // While the picker is collapsed as unavailable, a light refetch on screen
  // focus picks up the admin re-enabling search without a remount.
  useFocusEffect(
    useCallback(() => {
      if (searchUnavailable) intakeQ.refetch();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchUnavailable]),
  );

  // Default the On-Brand toggle to on whenever it's available.
  useEffect(() => {
    if (intake?.on_brand_allowed && intake.brand_kit) {
      setUseBrandKit(true);
    }
  }, [intake?.on_brand_allowed, intake?.brand_kit]);

  const links = useMemo(
    () =>
      linksText
        .split(/[\n,]+/)
        .map((s) => s.trim())
        .filter((s) => s.length > 0)
        .slice(0, intake?.max_links ?? 25),
    [linksText, intake?.max_links],
  );

  const showBrandToggle = !!intake?.on_brand_allowed && !!intake?.brand_kit;

  const keptImages = useMemo(
    () =>
      (preview?.extracted ?? []).filter((u) => !removedExtracted.includes(u)),
    [preview?.extracted, removedExtracted],
  );

  const buildPayload = (): AiBuilderPayload => ({
    description: description.trim(),
    links,
    images,
    use_brand_kit: showBrandToggle ? useBrandKit : undefined,
    // Only meaningful once the creator has previewed and attached no uploads
    // (uploads win outright server-side). Presence of kept_images means
    // "use my reviewed list verbatim — don't re-extract".
    ...(preview && images.length === 0
      ? { kept_images: keptImages, skip_generated_slots: skippedSlots }
      : {}),
  });

  async function runPreview() {
    if (previewing) return;
    setPreviewing(true);
    try {
      const res = await previewAiBuilderImages(linkId, links, description.trim());
      setPreview(res);
      setRemovedExtracted([]);
      setSkippedSlots([]);
      setEstimate(null);
    } catch (e: any) {
      showAlert(
        "Couldn't preview images",
        e?.message ?? "Please try again in a moment.",
      );
    } finally {
      setPreviewing(false);
    }
  }

  function toggleExtracted(url: string) {
    setRemovedExtracted((prev) =>
      prev.includes(url) ? prev.filter((u) => u !== url) : [...prev, url],
    );
    setEstimate(null);
  }

  function toggleSlot(slot: string) {
    setSkippedSlots((prev) =>
      prev.includes(slot) ? prev.filter((s) => s !== slot) : [...prev, slot],
    );
    setEstimate(null);
  }

  const descTooShort = description.trim().length < 10;

  const estimateM = useMutation({
    mutationFn: () => estimateAiBuilder(linkId, buildPayload()),
    onSuccess: (res) => setEstimate(res.estimated_credits),
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      showAlert(
        "Couldn't estimate",
        e?.message ?? "Please try again in a moment.",
      );
    },
  });

  const generateM = useMutation({
    mutationFn: () => generateAiBuilder(linkId, buildPayload()),
    onSuccess: (res) => {
      showAlert(
        "Page built",
        `Created ${res.blocks} block${res.blocks === 1 ? "" : "s"}.${
          res.credits_spent > 0 ? ` Used ${res.credits_spent} coins.` : ""
        }`,
      );
      // Land in the standard block editor on the freshly-built page, mirroring
      // the web flow's redirect to the editor.
      router.replace(`/links/${linkId}/blocks` as any);
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      showAlert(
        "Couldn't build the page",
        e?.message ??
          "The assistant couldn't build a page from that. Add more detail and try again.",
      );
    },
  });

  const searchM = useMutation({
    mutationFn: () => searchAiBuilderImages(linkId, searchQuery.trim()),
    onSuccess: (res) => {
      setSearchResults(res.results);
      setSearchDisclaimer(res.disclaimer);
      setSelectedCandidates([]);
      if (res.results.length === 0) {
        showAlert("No images found", "Try a different search.");
      }
    },
    onError: (e: any) => {
      // The admin removed/disabled the Google CSE keys mid-session: collapse
      // the picker (no point retrying a dead feature) and refetch the intake
      // so `image_search_enabled` reflects the fresh server state.
      if (e?.code === "image_search_unavailable") {
        setSearchUnavailable(true);
        setSearchOpen(false);
        setSearchResults([]);
        setSelectedCandidates([]);
        intakeQ.refetch();
        showAlert(
          "Image search unavailable",
          "Web image search was turned off. You can still upload your own images.",
        );
        return;
      }
      showAlert("Search failed", e?.message ?? "Please try again.");
    },
  });

  const importM = useMutation({
    mutationFn: () => importAiBuilderImages(linkId, selectedCandidates),
    onSuccess: (imported) => {
      setImages((prev) => {
        const next = [...prev];
        for (const img of imported) {
          if (next.length >= (intake?.max_images ?? 25)) break;
          if (!next.includes(img.url)) next.push(img.url);
        }
        return next;
      });
      setSelectedCandidates([]);
      setEstimate(null);
    },
    onError: (e: any) => {
      showAlert(
        "Couldn't add those images",
        e?.message ?? "They may be blocked or too large.",
      );
    },
  });

  function toggleCandidate(url: string) {
    setSelectedCandidates((prev) => {
      if (prev.includes(url)) return prev.filter((u) => u !== url);
      if (prev.length >= 6) return prev;
      return [...prev, url];
    });
  }

  async function addImage() {
    if ((images.length ?? 0) >= (intake?.max_images ?? 25)) {
      showAlert("Limit reached", "You've added the maximum number of images.");
      return;
    }
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      showAlert(
        "Photos access needed",
        "Allow access to your photo library in Settings to add an image.",
      );
      return;
    }
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.85,
    });
    if (res.canceled || !res.assets?.[0]) return;
    const asset = res.assets[0];
    setUploading(true);
    setUploadError(null);
    try {
      const url = await uploadWizardImage({
        uri: asset.uri,
        mime: asset.mimeType ?? undefined,
        name: asset.fileName ?? undefined,
      });
      setImages((prev) => [...prev, url]);
      setEstimate(null);
    } catch (e: any) {
      // Inline, persistent error next to the upload control (a transient
      // alert can be missed, stranding the creator with no feedback). The
      // finally below resets `uploading`, so the button flips back from
      // "Uploading…" and a retry is immediately possible.
      setUploadError(
        typeof e?.message === "string" && e.message.length > 0
          ? e.message
          : "Couldn't upload the image. Please try again.",
      );
    } finally {
      setUploading(false);
    }
  }

  function removeImage(url: string) {
    setImages((prev) => prev.filter((u) => u !== url));
    setEstimate(null);
  }

  if (intakeQ.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "AI builder" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (intakeQ.isError || !intake) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "AI builder" }} />
        <Text style={{ color: colors.mutedForeground, marginBottom: 12 }}>
          Couldn't load the AI builder.
        </Text>
        <Button label="Retry" onPress={() => intakeQ.refetch()} />
      </View>
    );
  }

  if (!intake.ai_enabled) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "AI builder" }} />
        <Feather name="zap-off" size={28} color={colors.mutedForeground} />
        <Text
          style={{
            color: colors.foreground,
            fontWeight: "600",
            marginTop: 12,
          }}
        >
          AI builder is unavailable
        </Text>
        <Text
          style={{
            color: colors.mutedForeground,
            textAlign: "center",
            marginTop: 6,
          }}
        >
          AI generation is currently turned off. You can still build your page
          with blocks, designs, and templates.
        </Text>
      </View>
    );
  }

  const busy = generateM.isPending;
  // Prefer the input-specific estimate once the user asked for one;
  // otherwise fall back to the intake's baseline worst-case cost.
  const shortOnCoins = insufficientCoins(
    estimate ?? intake.estimated_cost,
    intake.balance,
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "AI builder" }} />
      <ScrollView contentContainerStyle={styles.body}>
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          Describe the page you want and AI will assemble it for you. This
          replaces the blocks currently on this Link in Bio.
        </Text>

        <TextField
          label="What should your page be about?"
          placeholder="e.g. A page for my coffee roastery with my shop link, menu, story, and Instagram."
          value={description}
          onChangeText={(t) => {
            setDescription(t);
            setEstimate(null);
          }}
          multiline
          numberOfLines={5}
          style={{ minHeight: 120, textAlignVertical: "top" }}
          hint={`${intake.balance} coins available`}
          trailing={
            <DictationMic
              onText={(t) =>
                setDescription((prev) => (prev ? `${prev} ${t}` : t))
              }
            />
          }
        />

        <View style={{ gap: 8 }}>
          <Text style={[styles.label, { color: colors.mutedForeground }]}>
            Images (optional)
          </Text>
          <Text style={{ fontSize: 12, color: colors.mutedForeground }}>
            No uploads? We'll pull images from your links automatically, and
            if none are found, AI can generate a matching avatar and cover
            (extra coins, included in the estimate).
          </Text>
          {images.length > 0 ? (
            <View style={styles.imageRow}>
              {images.map((url, index) => (
                <View key={url} style={styles.thumbWrap}>
                  <Image source={{ uri: url }} style={styles.thumb} />
                  <Pressable
                    onPress={() => removeImage(url)}
                    testID={`ai-builder-remove-upload-${index}`}
                    style={[
                      styles.thumbRemove,
                      { backgroundColor: colors.destructive },
                    ]}
                    hitSlop={6}
                  >
                    <Feather name="x" size={12} color="#fff" />
                  </Pressable>
                </View>
              ))}
            </View>
          ) : null}
          <Button
            label={uploading ? "Uploading…" : "Add an image"}
            variant="ghost"
            loading={uploading}
            onPress={addImage}
          />
          {uploadError ? (
            <Text style={{ fontSize: 12, color: colors.destructive }}>
              {uploadError}
            </Text>
          ) : null}

          {intake.image_search_enabled ? (searchUnavailable ? null : (
            <View
              style={[
                styles.searchBox,
                { borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <Pressable
                onPress={() => setSearchOpen((v) => !v)}
                style={styles.searchToggle}
              >
                <Feather name="search" size={14} color={colors.primary} />
                <Text
                  style={{
                    color: colors.foreground,
                    fontWeight: "600",
                    fontSize: 13,
                    flex: 1,
                  }}
                >
                  Search the web for images (free)
                </Text>
                <Feather
                  name={searchOpen ? "chevron-up" : "chevron-down"}
                  size={16}
                  color={colors.mutedForeground}
                />
              </Pressable>

              {searchOpen ? (
                <View style={{ gap: 10 }}>
                  <TextField
                    placeholder="e.g. minimalist fitness logo"
                    value={searchQuery}
                    onChangeText={setSearchQuery}
                    autoCapitalize="none"
                    returnKeyType="search"
                    onSubmitEditing={() => {
                      if (searchQuery.trim().length >= 2) searchM.mutate();
                    }}
                  />
                  <Button
                    label={searchM.isPending ? "Searching…" : "Search"}
                    variant="ghost"
                    loading={searchM.isPending}
                    disabled={searchQuery.trim().length < 2}
                    onPress={() => searchM.mutate()}
                  />
                  {searchResults.length > 0 ? (
                    <>
                      <Text
                        style={{ color: colors.mutedForeground, fontSize: 11 }}
                      >
                        {searchDisclaimer}
                      </Text>
                      <View style={styles.imageRow}>
                        {searchResults.map((r) => {
                          const selected = selectedCandidates.includes(r.url);
                          return (
                            <Pressable
                              key={r.url}
                              onPress={() => toggleCandidate(r.url)}
                              style={[
                                styles.thumbWrap,
                                selected && {
                                  borderWidth: 2,
                                  borderColor: colors.primary,
                                  borderRadius: 10,
                                },
                              ]}
                            >
                              <Image
                                source={{ uri: r.thumbnail ?? r.url }}
                                style={styles.thumb}
                              />
                              {selected ? (
                                <View
                                  style={[
                                    styles.thumbRemove,
                                    { backgroundColor: colors.primary },
                                  ]}
                                >
                                  <Feather name="check" size={12} color="#fff" />
                                </View>
                              ) : null}
                            </Pressable>
                          );
                        })}
                      </View>
                      <Button
                        label={
                          importM.isPending
                            ? "Adding…"
                            : `Add selected (${selectedCandidates.length})`
                        }
                        variant="ghost"
                        loading={importM.isPending}
                        disabled={selectedCandidates.length === 0}
                        onPress={() => importM.mutate()}
                      />
                    </>
                  ) : null}
                </View>
              ) : null}
            </View>
          )) : null}
        </View>

        <TextField
          label="Links to include (optional)"
          placeholder="One URL per line"
          value={linksText}
          onChangeText={(t) => {
            setLinksText(t);
            setEstimate(null);
          }}
          multiline
          numberOfLines={3}
          autoCapitalize="none"
          autoCorrect={false}
          style={{ minHeight: 72, textAlignVertical: "top" }}
          hint={links.length > 0 ? `${links.length} link(s)` : undefined}
        />

        {images.length === 0 ? (
          <View
            style={[
              styles.previewBox,
              { borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <View style={styles.previewHeader}>
              <Text
                style={{
                  flex: 1,
                  fontSize: 12,
                  color: colors.mutedForeground,
                }}
              >
                No uploads: preview the images we'd use so you can pick which
                to keep.
              </Text>
              <Pressable onPress={runPreview} disabled={previewing} hitSlop={6}>
                <Text
                  style={{
                    color: colors.primary,
                    fontSize: 12,
                    fontWeight: "600",
                    opacity: previewing ? 0.5 : 1,
                  }}
                >
                  {previewing
                    ? "Checking…"
                    : preview
                      ? "Refresh preview"
                      : "Preview images"}
                </Text>
              </Pressable>
            </View>

            {preview && preview.extracted.length > 0 ? (
              <View style={{ gap: 8 }}>
                <Text style={{ fontSize: 12, color: colors.mutedForeground }}>
                  Found on your links: tap to keep or remove (free):
                </Text>
                <View style={styles.imageRow}>
                  {preview.extracted.map((url) => {
                    const kept = !removedExtracted.includes(url);
                    return (
                      <Pressable
                        key={url}
                        onPress={() => toggleExtracted(url)}
                        style={[
                          styles.previewThumbWrap,
                          kept
                            ? { borderColor: colors.primary }
                            : { borderColor: "transparent", opacity: 0.35 },
                        ]}
                      >
                        <Image source={{ uri: url }} style={styles.thumb} />
                        <View
                          style={[
                            styles.previewBadge,
                            {
                              backgroundColor: kept
                                ? colors.primary
                                : colors.muted,
                            },
                          ]}
                        >
                          <Feather
                            name={kept ? "check" : "x"}
                            size={10}
                            color={kept ? "#fff" : colors.mutedForeground}
                          />
                        </View>
                      </Pressable>
                    );
                  })}
                </View>
              </View>
            ) : null}

            {preview &&
            keptImages.length === 0 &&
            preview.generation.enabled ? (
              <View style={{ gap: 8 }}>
                <Text style={{ fontSize: 12, color: colors.mutedForeground }}>
                  {preview.extracted.length > 0
                    ? "Nothing kept. "
                    : "Nothing found on your links. "}
                  AI can generate these instead (
                  {preview.generation.cost_per_image} coins each). Untick any
                  you don't want:
                </Text>
                <View style={{ flexDirection: "row", gap: 8 }}>
                  {preview.generation.slots.map((slot) => {
                    const on = !skippedSlots.includes(slot);
                    return (
                      <Pressable
                        key={slot}
                        onPress={() => toggleSlot(slot)}
                        style={[
                          styles.slotChip,
                          {
                            borderColor: on ? colors.primary : colors.border,
                            backgroundColor: on
                              ? colors.primary + "1A"
                              : "transparent",
                          },
                        ]}
                      >
                        <Feather
                          name={on ? "check" : "x"}
                          size={12}
                          color={on ? colors.primary : colors.mutedForeground}
                        />
                        <Text
                          style={{
                            fontSize: 12,
                            textTransform: "capitalize",
                            color: on
                              ? colors.primary
                              : colors.mutedForeground,
                          }}
                        >
                          {slot}
                        </Text>
                      </Pressable>
                    );
                  })}
                </View>
              </View>
            ) : null}

            {preview &&
            preview.extracted.length === 0 &&
            !preview.generation.enabled ? (
              <Text style={{ fontSize: 12, color: colors.mutedForeground }}>
                No images found on your links: your page will be built without
                images.
              </Text>
            ) : null}

            {/* Inline upload (Task #5735): replace the auto-sourced flow right here */}
            <View
              style={{
                borderTopWidth: StyleSheet.hairlineWidth,
                borderTopColor: colors.border,
                paddingTop: 10,
                gap: 8,
              }}
            >
              <Text style={{ fontSize: 12, color: colors.mutedForeground }}>
                Don't like these? Upload your own instead: uploads replace the
                extracted and generated images.
              </Text>
              <Button
                label={uploading ? "Uploading…" : "Upload instead"}
                variant="ghost"
                loading={uploading}
                onPress={addImage}
              />
              {uploadError ? (
                <Text style={{ fontSize: 12, color: colors.destructive }}>
                  {uploadError}
                </Text>
              ) : null}
            </View>
          </View>
        ) : null}

        {showBrandToggle ? (
          <View
            style={[
              styles.brandRow,
              { borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <View style={{ flex: 1, gap: 2 }}>
              <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                On-Brand AI
              </Text>
              <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                Use your “{intake.brand_kit?.name}” AI Brand Kit voice and colors.
              </Text>
            </View>
            <Switch
              value={useBrandKit}
              onValueChange={(v) => {
                setUseBrandKit(v);
                setEstimate(null);
              }}
              trackColor={{ true: colors.primary, false: colors.border }}
            />
          </View>
        ) : null}

        {estimate !== null ? (
          <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
            Estimated cost: ~{estimate} coins ({intake.balance} available)
          </Text>
        ) : null}

        <CoinCostHint
          cost={estimate ?? intake.estimated_cost}
          balance={intake.balance}
          actionLabel="this build"
        />

        <View style={{ gap: 8, marginTop: 4 }}>
          <Button
            label={estimateM.isPending ? "Estimating…" : "Estimate cost"}
            variant="ghost"
            loading={estimateM.isPending}
            disabled={descTooShort || busy}
            onPress={() => estimateM.mutate()}
          />
          <Button
            label={busy ? "Building your page…" : "Build my page with AI"}
            loading={busy}
            disabled={descTooShort || uploading || shortOnCoins}
            onPress={() => generateM.mutate()}
          />
          {descTooShort ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
              Add a little more detail (at least 10 characters) to get started.
            </Text>
          ) : null}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  body: { padding: 16, gap: 16 },
  intro: { fontSize: 13, lineHeight: 18 },
  label: { fontSize: 13, fontWeight: "600" },
  imageRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  thumbWrap: { position: "relative" },
  thumb: { width: 72, height: 72, borderRadius: 8 },
  thumbRemove: {
    position: "absolute",
    top: -6,
    right: -6,
    width: 20,
    height: 20,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
  },
  searchBox: { borderWidth: 1, padding: 12, gap: 10 },
  searchToggle: { flexDirection: "row", alignItems: "center", gap: 8 },
  brandRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    borderWidth: 1,
    padding: 12,
  },
  previewBox: { borderWidth: 1, padding: 12, gap: 10 },
  previewHeader: { flexDirection: "row", alignItems: "center", gap: 8 },
  previewThumbWrap: {
    position: "relative",
    borderWidth: 2,
    borderRadius: 10,
    padding: 1,
  },
  previewBadge: {
    position: "absolute",
    top: 4,
    right: 4,
    width: 18,
    height: 18,
    borderRadius: 9,
    alignItems: "center",
    justifyContent: "center",
  },
  slotChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
});
