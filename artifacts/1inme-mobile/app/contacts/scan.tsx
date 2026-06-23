import { Feather } from "@expo/vector-icons";
import { useMutation } from "@tanstack/react-query";
import { Image } from "expo-image";
import { Stack, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import {
  CARD_SCAN_MAX_FILES,
  CARD_SCAN_MAX_MB,
  CARD_SCAN_MAX_PDF_PAGES,
  scanCards,
  type ScanUpload,
} from "@/lib/api/cardScan";
import { handlePlanLockedError } from "@/lib/upgradePrompt";

const MAX_BYTES = CARD_SCAN_MAX_MB * 1024 * 1024;

export default function ScanCardScreen() {
  const colors = useColors();
  const router = useRouter();
  const [files, setFiles] = useState<ScanUpload[]>([]);

  const scanMut = useMutation({
    mutationFn: (picked: ScanUpload[]) => scanCards(picked),
    onSuccess: (res) => {
      router.replace(`/contacts/scan-review?id=${res.scan.id}` as never);
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert("Scan failed", e?.message ?? "Please try again.");
    },
  });

  function addFiles(next: ScanUpload[]) {
    setFiles((prev) => {
      const merged = [...prev];
      for (const f of next) {
        if (merged.length >= CARD_SCAN_MAX_FILES) break;
        merged.push(f);
      }
      if (prev.length + next.length > CARD_SCAN_MAX_FILES) {
        Alert.alert(
          "File limit",
          `You can scan up to ${CARD_SCAN_MAX_FILES} files at once.`,
        );
      }
      return merged;
    });
  }

  function removeAt(idx: number) {
    setFiles((prev) => prev.filter((_, i) => i !== idx));
  }

  const remaining = CARD_SCAN_MAX_FILES - files.length;

  async function pickFromLibrary() {
    if (remaining <= 0) {
      Alert.alert("File limit", `You've already added ${CARD_SCAN_MAX_FILES} files.`);
      return;
    }
    const ImagePicker = await import("expo-image-picker");
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
        "Photos access needed",
        "Allow access to your photo library in Settings to pick card images.",
      );
      return;
    }
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsMultipleSelection: true,
      selectionLimit: remaining,
      quality: 0.85,
    });
    if (res.canceled) return;
    const picked = (res.assets ?? [])
      .map((a, i) => normaliseAsset(a, i))
      .filter((f): f is ScanUpload => f !== null);
    if (picked.length) addFiles(picked);
  }

  async function takePhoto() {
    if (remaining <= 0) {
      Alert.alert("File limit", `You've already added ${CARD_SCAN_MAX_FILES} files.`);
      return;
    }
    const ImagePicker = await import("expo-image-picker");
    const perm = await ImagePicker.requestCameraPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
        "Camera access needed",
        "Allow camera access in Settings to photograph a card.",
      );
      return;
    }
    const res = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.85,
    });
    if (res.canceled || !res.assets?.[0]) return;
    const f = normaliseAsset(res.assets[0], 0);
    if (f) addFiles([f]);
  }

  async function pickDocument() {
    if (remaining <= 0) {
      Alert.alert("File limit", `You've already added ${CARD_SCAN_MAX_FILES} files.`);
      return;
    }
    const DocumentPicker = await import("expo-document-picker");
    const res = await DocumentPicker.getDocumentAsync({
      type: ["application/pdf", "image/*"],
      multiple: true,
      copyToCacheDirectory: true,
    });
    if (res.canceled) return;
    const picked: ScanUpload[] = [];
    for (const a of res.assets ?? []) {
      if (typeof a.size === "number" && a.size > MAX_BYTES) {
        Alert.alert(
          "File too large",
          `"${a.name}" is over the ${CARD_SCAN_MAX_MB}MB limit.`,
        );
        continue;
      }
      picked.push({
        uri: a.uri,
        name: a.name || guessName(a.mimeType),
        mime: a.mimeType || "application/octet-stream",
      });
    }
    if (picked.length) addFiles(picked);
  }

  function openPicker() {
    Alert.alert("Add a card or brochure", undefined, [
      { text: "Take a photo", onPress: takePhoto },
      { text: "Choose photos", onPress: pickFromLibrary },
      { text: "Pick a PDF / file", onPress: pickDocument },
      { text: "Cancel", style: "cancel" },
    ]);
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Scan a card",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
        }}
      />
      <ScrollView contentContainerStyle={styles.body}>
        <Text style={[styles.heading, { color: colors.foreground }]}>
          Scan a business card or brochure
        </Text>
        <Text style={[styles.sub, { color: colors.mutedForeground }]}>
          Snap a photo or pick files and we&apos;ll read the details with AI — then
          you can save a contact or start a biolink. Up to {CARD_SCAN_MAX_FILES}{" "}
          files, {CARD_SCAN_MAX_MB}MB each (PDFs up to {CARD_SCAN_MAX_PDF_PAGES}{" "}
          pages).
        </Text>

        {files.length === 0 ? (
          <Pressable
            onPress={openPicker}
            style={[
              styles.dropzone,
              {
                borderColor: colors.primary + "55",
                backgroundColor: colors.primary + "0d",
                borderRadius: colors.radius,
              },
            ]}
          >
            <Feather name="camera" size={28} color={colors.primary} />
            <Text style={[styles.dropTitle, { color: colors.foreground }]}>
              Add a card or brochure
            </Text>
            <Text style={[styles.dropHint, { color: colors.mutedForeground }]}>
              Photo, image, or PDF
            </Text>
          </Pressable>
        ) : (
          <View style={{ gap: 10 }}>
            {files.map((f, i) => (
              <View
                key={`${f.uri}-${i}`}
                style={[
                  styles.fileRow,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                {f.mime.startsWith("image/") ? (
                  <Image
                    source={{ uri: f.uri }}
                    style={styles.thumb}
                    contentFit="cover"
                  />
                ) : (
                  <View
                    style={[
                      styles.thumb,
                      {
                        alignItems: "center",
                        justifyContent: "center",
                        backgroundColor: colors.primary + "1c",
                      },
                    ]}
                  >
                    <Feather name="file-text" size={20} color={colors.primary} />
                  </View>
                )}
                <Text
                  numberOfLines={1}
                  style={[styles.fileName, { color: colors.foreground }]}
                >
                  {f.name}
                </Text>
                <Pressable onPress={() => removeAt(i)} hitSlop={8}>
                  <Feather name="x" size={18} color={colors.mutedForeground} />
                </Pressable>
              </View>
            ))}

            {remaining > 0 ? (
              <Pressable onPress={openPicker} style={styles.addMore}>
                <Feather name="plus" size={16} color={colors.primary} />
                <Text style={[styles.addMoreText, { color: colors.primary }]}>
                  Add another ({remaining} left)
                </Text>
              </Pressable>
            ) : null}
          </View>
        )}

        {scanMut.isPending ? (
          <View style={styles.busy}>
            <ActivityIndicator color={colors.primary} />
            <Text style={[styles.busyText, { color: colors.mutedForeground }]}>
              Reading your card with AI…
            </Text>
          </View>
        ) : (
          <Button
            label="Scan with AI"
            onPress={() => scanMut.mutate(files)}
            disabled={files.length === 0}
          />
        )}
      </ScrollView>
    </View>
  );
}

function normaliseAsset(a: any, idx: number): ScanUpload | null {
  if (!a?.uri) return null;
  if (typeof a.fileSize === "number" && a.fileSize > MAX_BYTES) {
    Alert.alert(
      "Image too large",
      `"${a.fileName ?? "Image"}" is over the ${CARD_SCAN_MAX_MB}MB limit.`,
    );
    return null;
  }
  const mime = a.mimeType || "image/jpeg";
  return {
    uri: a.uri,
    name: a.fileName || guessName(mime, idx),
    mime,
  };
}

function guessName(mime?: string | null, idx = 0): string {
  if (mime === "application/pdf") return `brochure-${idx + 1}.pdf`;
  if (mime?.includes("png")) return `card-${idx + 1}.png`;
  return `card-${idx + 1}.jpg`;
}

const styles = StyleSheet.create({
  body: { padding: 20, gap: 16, paddingBottom: 48 },
  heading: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 19 },
  dropzone: {
    borderWidth: 1.5,
    borderStyle: "dashed",
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 40,
    gap: 8,
  },
  dropTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
  dropHint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  fileRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 10,
    borderWidth: 1,
  },
  thumb: { width: 44, height: 44, borderRadius: 8 },
  fileName: { flex: 1, fontFamily: "SpaceGrotesk_500Medium", fontSize: 14 },
  addMore: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    paddingVertical: 10,
  },
  addMoreText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  busy: { alignItems: "center", gap: 10, paddingVertical: 16 },
  busyText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
});
