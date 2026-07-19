import { Feather } from "@expo/vector-icons";
import { useQueryClient } from "@tanstack/react-query";
import * as ImagePicker from "expo-image-picker";
import { Stack, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { handlePlanLockedError } from "@/lib/upgradePrompt";
import { showAlert } from "@/lib/webAlert";
import {
  MAX_INSTRUCTION_LENGTH,
  rescanCardScan,
  runCardScan,
  saveCardScan,
  type CardScan,
  type CardScanExtracted,
  type DuplicateHint,
} from "@/lib/api/cardScans";

type PickedFile = {
  uri: string;
  name: string;
  type: string;
};

export default function CardScanScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const [files, setFiles] = useState<PickedFile[]>([]);
  const [instruction, setInstruction] = useState("");
  const [scanning, setScanning] = useState(false);
  const [scan, setScan] = useState<CardScan | null>(null);
  const [duplicates, setDuplicates] = useState<DuplicateHint[]>([]);
  const [saving, setSaving] = useState(false);
  const [rescanning, setRescanning] = useState(false);

  async function pickImages() {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ["images"],
      allowsMultipleSelection: true,
      quality: 0.85,
    });
    if (result.canceled) return;

    const picked: PickedFile[] = result.assets.slice(0, 6).map((a) => ({
      uri: a.uri,
      name: a.fileName ?? `card-${Date.now()}.jpg`,
      type: a.mimeType ?? "image/jpeg",
    }));
    setFiles(picked);
    setScan(null);
  }

  async function runScan() {
    if (!files.length) {
      showAlert("No image", "Please pick at least one card or brochure image.");
      return;
    }
    setScanning(true);
    try {
      const result = await runCardScan(files, instruction.trim() || undefined);
      setScan(result.scan);
      setDuplicates(result.duplicates);
    } catch (err: unknown) {
      if (handlePlanLockedError(err)) return;
      const msg =
        err instanceof Error ? err.message : "Scan failed. Please try again.";
      showAlert("Scan failed", msg);
    } finally {
      setScanning(false);
    }
  }

  async function runRescan() {
    if (!scan) return;
    setRescanning(true);
    try {
      const result = await rescanCardScan(
        scan.id,
        instruction.trim() || undefined,
      );
      setScan(result.scan);
      setDuplicates(result.duplicates);
    } catch (err: unknown) {
      if (handlePlanLockedError(err)) return;
      const msg =
        err instanceof Error ? err.message : "Re-scan failed. Please try again.";
      showAlert("Re-scan failed", msg);
    } finally {
      setRescanning(false);
    }
  }

  async function handleSave(opts: {
    createContact: boolean;
    createBiolink: boolean;
  }) {
    if (!scan) return;
    const ex: CardScanExtracted = scan.extracted ?? ({} as CardScanExtracted);

    setSaving(true);
    try {
      const result = await saveCardScan(scan.id, {
        create_contact: opts.createContact,
        create_biolink: opts.createBiolink,
        full_name: ex.full_name ?? undefined,
        first_name: ex.first_name ?? undefined,
        last_name: ex.last_name ?? undefined,
        title: ex.title ?? undefined,
        company: ex.company ?? undefined,
        tagline: ex.tagline ?? undefined,
        description: ex.description ?? undefined,
        website: ex.website ?? undefined,
        address: ex.address ?? undefined,
        emails: ex.emails?.length
          ? ex.emails.map((e) => ({ value: e.value, label: e.label ?? undefined }))
          : undefined,
        phones: ex.phones?.length
          ? ex.phones.map((p) => ({ value: p.value, label: p.label ?? undefined }))
          : undefined,
        socials: ex.socials
          ? {
              instagram: ex.socials.instagram ?? undefined,
              tiktok: ex.socials.tiktok ?? undefined,
              youtube: ex.socials.youtube ?? undefined,
              twitter: ex.socials.twitter ?? undefined,
              linkedin: ex.socials.linkedin ?? undefined,
              facebook: ex.socials.facebook ?? undefined,
            }
          : undefined,
      });

      if (opts.createBiolink && result.biolink) {
        const answersStr = JSON.stringify(result.biolink.answers ?? {});
        router.push(
          `/links/wizard?prefillCategory=${encodeURIComponent(result.biolink.category)}&prefillAnswers=${encodeURIComponent(answersStr)}` as never,
        );
        return;
      }

      if (opts.createContact && result.contact) {
        // Freshly-saved contacts (and any new duplicate they create) should
        // show up immediately on the contacts screen and its banner.
        qc.invalidateQueries({ queryKey: ["contacts"] });
        qc.invalidateQueries({ queryKey: ["contact-duplicate-count"] });

        if (result.contact.has_duplicate) {
          // Same notice the web flash shows: the save made this contact
          // match an existing one — offer a jump to the review screen.
          showAlert(
            "Possible duplicate",
            `"${result.contact.display_name}" was saved, but it looks like a duplicate of an existing contact.`,
            [
              { text: "Not now", style: "cancel", onPress: () => router.back() },
              {
                text: "Review duplicates",
                onPress: () => {
                  router.back();
                  router.push("/contact-duplicates" as never);
                },
              },
            ],
          );
          return;
        }

        showAlert(
          "Contact saved",
          `"${result.contact.display_name}" has been added to your contacts.`,
        );
        router.back();
        return;
      }

      router.back();
    } catch (err: unknown) {
      const msg =
        err instanceof Error ? err.message : "Save failed. Please try again.";
      showAlert("Save failed", msg);
    } finally {
      setSaving(false);
    }
  }

  const ex: CardScanExtracted | null = scan?.extracted ?? null;

  return (
    <>
      <Stack.Screen
        options={{
          title: "Scan a Card or Brochure",
          headerBackTitle: "Back",
        }}
      />
      <ScrollView
        style={[styles.root, { backgroundColor: colors.background }]}
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
      >
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Text style={[styles.sectionLabel, { color: colors.text }]}>
            Card or brochure photos
          </Text>
          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            Pick up to 6 images: front and back of a card, or brochure pages.
          </Text>

          <Pressable
            onPress={pickImages}
            style={[styles.pickBtn, { borderColor: colors.border, backgroundColor: colors.muted }]}
          >
            <Feather name="image" size={18} color={colors.primary} />
            <Text style={[styles.pickBtnText, { color: colors.primary }]}>
              {files.length ? `${files.length} image${files.length > 1 ? "s" : ""} selected` : "Choose images"}
            </Text>
          </Pressable>

          {files.length > 0 && (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.thumbRow}>
              {files.map((f, i) => (
                <Image key={i} source={{ uri: f.uri }} style={styles.thumb} />
              ))}
            </ScrollView>
          )}
        </View>

        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Text style={[styles.sectionLabel, { color: colors.text }]}>
            What should I focus on?{" "}
            <Text style={[styles.optionalTag, { color: colors.mutedForeground }]}>(optional)</Text>
          </Text>
          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            Tell the AI what to prioritise, e.g. "just the logo and phone number"
            or "only brand colors". Leave blank to extract everything.
          </Text>
          <TextInput
            value={instruction}
            onChangeText={(t) =>
              setInstruction(t.slice(0, MAX_INSTRUCTION_LENGTH))
            }
            placeholder="e.g. only grab the logo and phone number"
            placeholderTextColor={colors.mutedForeground}
            multiline
            numberOfLines={2}
            maxLength={MAX_INSTRUCTION_LENGTH}
            style={[
              styles.instructionInput,
              {
                color: colors.text,
                backgroundColor: colors.muted,
                borderColor: colors.border,
              },
            ]}
          />
          {instruction.length > 0 && (
            <Text style={[styles.charCount, { color: colors.mutedForeground }]}>
              {instruction.length}/{MAX_INSTRUCTION_LENGTH}
            </Text>
          )}
        </View>

        <Button
          label={scanning ? "Scanning…" : "Scan with AI"}
          onPress={runScan}
          disabled={scanning || !files.length}
          loading={scanning}
        />

        {scan?.status === "completed" && ex && (
          <>
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.resultHeader}>
                <Feather name="check-circle" size={18} color="#22c55e" />
                <Text style={[styles.sectionLabel, { color: colors.text, marginBottom: 0, marginLeft: 6 }]}>
                  Extraction complete
                </Text>
              </View>

              {scan.logo_url ? (
                <Image source={{ uri: scan.logo_url }} style={styles.logoPreview} />
              ) : null}

              {ex.full_name ? (
                <Field label="Name" value={ex.full_name} colors={colors} />
              ) : null}
              {ex.title ? (
                <Field label="Title" value={ex.title} colors={colors} />
              ) : null}
              {ex.company ? (
                <Field label="Company" value={ex.company} colors={colors} />
              ) : null}
              {ex.emails?.length ? (
                <Field
                  label="Email"
                  value={ex.emails.map((e) => e.value).join(", ")}
                  colors={colors}
                />
              ) : null}
              {ex.phones?.length ? (
                <Field
                  label="Phone"
                  value={ex.phones.map((p) => p.value).join(", ")}
                  colors={colors}
                />
              ) : null}
              {ex.website ? (
                <Field label="Website" value={ex.website} colors={colors} />
              ) : null}
              {ex.address ? (
                <Field label="Address" value={ex.address} colors={colors} />
              ) : null}

              {duplicates.length > 0 && (
                <View style={[styles.dupWarning, { backgroundColor: "rgba(245,158,11,0.10)", borderColor: "rgba(245,158,11,0.3)" }]}>
                  <Feather name="alert-triangle" size={14} color="#f59e0b" />
                  <Text style={[styles.dupText, { color: "#f59e0b" }]}>
                    Possible duplicate contact detected. Review before saving.
                  </Text>
                </View>
              )}
            </View>

            <View style={styles.saveRow}>
              <Button
                label={saving ? "Saving…" : "Save as Contact"}
                onPress={() => handleSave({ createContact: true, createBiolink: false })}
                disabled={saving}
                variant="secondary"
                style={styles.saveBtn}
              />
              <Button
                label={saving ? "Saving…" : "Create Link in Bio"}
                onPress={() => handleSave({ createContact: false, createBiolink: true })}
                disabled={saving}
                style={styles.saveBtn}
              />
            </View>
            <Button
              label={saving ? "Saving…" : "Save Both"}
              onPress={() => handleSave({ createContact: true, createBiolink: true })}
              disabled={saving}
              variant="outline"
            />

            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.sectionLabel, { color: colors.text }]}>
                Not quite right?
              </Text>
              <Text style={[styles.hint, { color: colors.mutedForeground }]}>
                Change the focus above and re-scan the same photos; no need to
                re-upload. Your first scan is kept so you can compare.
              </Text>
              <Button
                label={rescanning ? "Re-scanning…" : "Re-scan with a new focus"}
                onPress={runRescan}
                disabled={rescanning || saving}
                loading={rescanning}
                variant="outline"
              />
            </View>
          </>
        )}

        {scan?.status === "failed" && (
          <>
            <View style={[styles.errorBox, { backgroundColor: "rgba(239,68,68,0.1)", borderColor: "rgba(239,68,68,0.25)" }]}>
              <Feather name="x-circle" size={16} color="#ef4444" />
              <Text style={[styles.errorText, { color: "#ef4444" }]}>
                {scan.error ?? "Scan failed. Please try again with a clearer image."}
              </Text>
            </View>
            <Button
              label={rescanning ? "Re-scanning…" : "Re-scan with a new focus"}
              onPress={runRescan}
              disabled={rescanning}
              loading={rescanning}
              variant="outline"
            />
          </>
        )}
      </ScrollView>
    </>
  );
}

function Field({
  label,
  value,
  colors,
}: {
  label: string;
  value: string;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={styles.fieldRow}>
      <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>{label}</Text>
      <Text style={[styles.fieldValue, { color: colors.text }]}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  content: { padding: 16, gap: 14, paddingBottom: 40 },
  card: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 16,
    gap: 8,
  },
  sectionLabel: {
    fontSize: 14,
    fontWeight: "600",
    marginBottom: 2,
  },
  optionalTag: {
    fontSize: 12,
    fontWeight: "400",
  },
  hint: {
    fontSize: 12,
    lineHeight: 17,
  },
  pickBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderStyle: "dashed",
  },
  pickBtnText: {
    fontSize: 14,
    fontWeight: "500",
  },
  thumbRow: {
    marginTop: 4,
  },
  thumb: {
    width: 64,
    height: 64,
    borderRadius: 8,
    marginRight: 8,
  },
  instructionInput: {
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    fontSize: 14,
    minHeight: 64,
    textAlignVertical: "top",
  },
  charCount: {
    fontSize: 11,
    textAlign: "right",
    marginTop: -4,
  },
  resultHeader: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 8,
  },
  logoPreview: {
    width: 72,
    height: 72,
    borderRadius: 10,
    marginBottom: 4,
  },
  fieldRow: {
    flexDirection: "row",
    gap: 8,
    paddingVertical: 3,
  },
  fieldLabel: {
    fontSize: 12,
    width: 72,
    paddingTop: 1,
  },
  fieldValue: {
    fontSize: 13,
    flex: 1,
  },
  dupWarning: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 6,
    borderWidth: 1,
    borderRadius: 8,
    padding: 10,
    marginTop: 4,
  },
  dupText: {
    fontSize: 12,
    flex: 1,
    lineHeight: 17,
  },
  saveRow: {
    flexDirection: "row",
    gap: 10,
  },
  saveBtn: {
    flex: 1,
  },
  errorBox: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
    borderWidth: 1,
    borderRadius: 10,
    padding: 12,
  },
  errorText: {
    fontSize: 13,
    flex: 1,
    lineHeight: 18,
  },
});
