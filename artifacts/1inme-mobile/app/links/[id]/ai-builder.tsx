import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import * as ImagePicker from "expo-image-picker";
import { useEffect, useMemo, useState } from "react";
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
  type AiBuilderIntake,
  type AiBuilderPayload,
} from "@/lib/api/aiBuilder";
import { uploadWizardImage } from "@/lib/api/wizard";

/**
 * "Build my Link in Bio with AI" — mobile parity for the web
 * links/{link}/ai-builder flow. The creator describes the page they want,
 * optionally attaches images (uploaded to their vault) and destination links,
 * and the server's AiBiolinkBuilderService assembles a full page and REPLACES
 * this biolink's blocks. The AI credit charge (auto-refunded on parse failure)
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
  const [estimate, setEstimate] = useState<number | null>(null);

  const intakeQ = useQuery<AiBuilderIntake>({
    queryKey: ["ai-builder-intake", linkId],
    queryFn: () => getAiBuilderIntake(linkId),
    enabled: Number.isFinite(linkId),
  });

  const intake = intakeQ.data;

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

  const buildPayload = (): AiBuilderPayload => ({
    description: description.trim(),
    links,
    images,
    use_brand_kit: showBrandToggle ? useBrandKit : undefined,
  });

  const descTooShort = description.trim().length < 10;

  const estimateM = useMutation({
    mutationFn: () => estimateAiBuilder(linkId, buildPayload()),
    onSuccess: (res) => setEstimate(res.estimated_credits),
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert(
        "Couldn't estimate",
        e?.message ?? "Please try again in a moment.",
      );
    },
  });

  const generateM = useMutation({
    mutationFn: () => generateAiBuilder(linkId, buildPayload()),
    onSuccess: (res) => {
      Alert.alert(
        "Page built",
        `Created ${res.blocks} block${res.blocks === 1 ? "" : "s"}.${
          res.credits_spent > 0 ? ` Used ${res.credits_spent} credits.` : ""
        }`,
      );
      // Land in the standard block editor on the freshly-built page, mirroring
      // the web flow's redirect to the editor.
      router.replace(`/links/${linkId}/blocks` as any);
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert(
        "Couldn't build the page",
        e?.message ??
          "The assistant couldn't build a page from that. Add more detail and try again.",
      );
    },
  });

  async function addImage() {
    if ((images.length ?? 0) >= (intake?.max_images ?? 25)) {
      Alert.alert("Limit reached", "You've added the maximum number of images.");
      return;
    }
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
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
    try {
      const url = await uploadWizardImage({
        uri: asset.uri,
        mime: asset.mimeType ?? undefined,
        name: asset.fileName ?? undefined,
      });
      setImages((prev) => [...prev, url]);
      setEstimate(null);
    } catch (e: any) {
      Alert.alert(
        "Couldn't upload image",
        e?.message ?? "Please try again.",
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
          hint={`${intake.balance} AI credits available`}
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
          {images.length > 0 ? (
            <View style={styles.imageRow}>
              {images.map((url) => (
                <View key={url} style={styles.thumbWrap}>
                  <Image source={{ uri: url }} style={styles.thumb} />
                  <Pressable
                    onPress={() => removeImage(url)}
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
            Estimated cost: ~{estimate} credits ({intake.balance} available)
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
  brandRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    borderWidth: 1,
    padding: 12,
  },
});
