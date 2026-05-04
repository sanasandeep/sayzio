import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as Haptics from "expo-haptics";
import { Stack } from "expo-router";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  Linking,
  Modal,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";
import * as ImagePicker from "expo-image-picker";
import { Gesture, GestureDetector } from "react-native-gesture-handler";
import Animated, {
  runOnJS,
  useAnimatedStyle,
  useSharedValue,
  withTiming,
} from "react-native-reanimated";

import { Button } from "@/components/Button";
import { Card } from "@/components/Card";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  createResumeItem,
  deleteResumeItem,
  getResume,
  removeResumeHeaderPhoto,
  reorderResumeItems,
  updateResumeColorTheme,
  updateResumeHeader,
  updateResumeItem,
  updateResumePublicPdf,
  updateResumePublishing,
  revokeResumeShare,
  getResumeViews,
  updateResumeSummary,
  updateResumeTemplate,
  uploadResumeHeaderPhoto,
  type PublishingPayload,
  type Resume,
  type ResumeBundle,
  type ResumeItem,
  type ResumeSectionType,
  type ResumeVisibility,
  type ResumeViewLogEntry,
} from "@/lib/api/resume";

const VISIBILITY_OPTIONS: { value: ResumeVisibility; label: string; hint: string }[] = [
  { value: "public",      label: "Public",       hint: "Anyone with the link" },
  { value: "registered",  label: "Members",      hint: "Signed-in 1INME users" },
  { value: "followers",   label: "Followers",    hint: "People who follow you" },
  { value: "subscribers", label: "Subscribers",  hint: "Paying subscribers only" },
  { value: "password",    label: "Password",     hint: "Anyone with the password" },
];

/**
 * Native resume editor + live preview.
 *
 * Talks to the new `/api/v1/resume*` endpoints (which the web editor
 * also reads through, via the shared ResumePresenter), so the same data
 * model backs both clients. Header + summary autosave with a short
 * debounce; section items are managed through dedicated CRUD calls and
 * a single inline editor card.
 */
export default function ResumeScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({ queryKey: ["resume"], queryFn: getResume });
  const resume = q.data?.resume;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Resume" }} />
      {q.isLoading || !resume ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <ResumeEditor resume={resume} bundle={q.data!} qc={qc} />
      )}
    </View>
  );
}

function ResumeEditor({
  resume,
  bundle,
  qc,
}: {
  resume: Resume;
  bundle: ResumeBundle;
  qc: ReturnType<typeof useQueryClient>;
}) {
  const colors = useColors();

  // ── Header autosave ────────────────────────────────────────────
  const [header, setHeader] = useState(resume.sections.header);
  const [summary, setSummary] = useState(resume.sections.summary);
  const headerInit = useRef(false);
  // Last server snapshot we accepted into local state. We adopt new
  // server values only for fields the user hasn't touched since — i.e.
  // local still equals what the server last gave us. This lets remote
  // changes (template switches, second-device edits) flow in without
  // clobbering an in-flight edit the user hasn't sent yet.
  const lastServerHeader = useRef(resume.sections.header);
  const lastServerSummary = useRef(resume.sections.summary);
  useEffect(() => {
    if (!headerInit.current) {
      setHeader(resume.sections.header);
      setSummary(resume.sections.summary);
      lastServerHeader.current = resume.sections.header;
      lastServerSummary.current = resume.sections.summary;
      headerInit.current = true;
      return;
    }
    const remoteHeader = resume.sections.header;
    const remoteSummary = resume.sections.summary;
    setHeader((current) => {
      const next = { ...current };
      let changed = false;
      (Object.keys(remoteHeader) as (keyof typeof remoteHeader)[]).forEach((k) => {
        const remoteVal = remoteHeader[k];
        const lastVal = lastServerHeader.current[k];
        // Only adopt the server value if the user hasn't typed past
        // what we last hydrated for this field.
        if (remoteVal !== lastVal && current[k] === lastVal) {
          (next as Record<string, unknown>)[k as string] = remoteVal;
          changed = true;
        }
      });
      return changed ? next : current;
    });
    setSummary((current) =>
      remoteSummary !== lastServerSummary.current && current === lastServerSummary.current
        ? remoteSummary
        : current,
    );
    lastServerHeader.current = remoteHeader;
    lastServerSummary.current = remoteSummary;
  }, [resume]);

  const headerMut = useMutation({
    mutationFn: (h: typeof header) =>
      updateResumeHeader({
        name: h.name,
        headline: h.headline,
        location: h.location,
        email: h.email || undefined,
        phone: h.phone,
        website: h.website || undefined,
      }),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't save header", e?.message ?? "Try again."),
  });

  const summaryMut = useMutation({
    mutationFn: (s: string) => updateResumeSummary(s),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
  });

  useDebouncedEffect(() => {
    if (!headerInit.current) return;
    headerMut.mutate(header);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [header.name, header.headline, header.location, header.email, header.phone, header.website], 600);

  useDebouncedEffect(() => {
    if (!headerInit.current) return;
    summaryMut.mutate(summary);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [summary], 600);

  // ── Section CRUD ───────────────────────────────────────────────
  const refresh = useCallback(() => qc.invalidateQueries({ queryKey: ["resume"] }), [qc]);

  const itemDelete = useMutation({
    mutationFn: (id: number) => deleteResumeItem(id),
    onSuccess: refresh,
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't delete", e?.message ?? "Try again."),
  });

  const itemReorder = useMutation({
    mutationFn: ({ type, ids }: { type: ResumeSectionType; ids: number[] }) =>
      reorderResumeItems(type, ids),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
  });

  const templateMut = useMutation({
    mutationFn: (id: string) => updateResumeTemplate(id),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
    onError: (e: { message?: string }) =>
      Alert.alert("Template locked", e?.message ?? "Upgrade to use this template."),
  });

  const themeMut = useMutation({
    mutationFn: (id: string) => updateResumeColorTheme(id),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
  });

  const photoUploadMut = useMutation({
    mutationFn: (a: { uri: string; mime?: string; name?: string }) =>
      uploadResumeHeaderPhoto(a),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't upload photo", e?.message ?? "Try again."),
  });

  const photoRemoveMut = useMutation({
    mutationFn: () => removeResumeHeaderPhoto(),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't remove photo", e?.message ?? "Try again."),
  });

  const pickFromLibrary = useCallback(async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
        "Photos access needed",
        "Allow access to your photo library in Settings to pick a header photo.",
      );
      return;
    }
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.85,
    });
    if (res.canceled || !res.assets?.[0]) return;
    const a = res.assets[0];
    photoUploadMut.mutate({
      uri: a.uri,
      mime: a.mimeType ?? undefined,
      name: a.fileName ?? undefined,
    });
  }, [photoUploadMut]);

  const takePhoto = useCallback(async () => {
    const perm = await ImagePicker.requestCameraPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(
        "Camera access needed",
        "Allow camera access in Settings to take a header photo.",
      );
      return;
    }
    const res = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.85,
    });
    if (res.canceled || !res.assets?.[0]) return;
    const a = res.assets[0];
    photoUploadMut.mutate({
      uri: a.uri,
      mime: a.mimeType ?? undefined,
      name: a.fileName ?? undefined,
    });
  }, [photoUploadMut]);

  const openSourceMenu = useCallback(() => {
    // Stays at 3 buttons so Android's Alert (which truncates beyond 3)
    // renders cleanly on every platform.
    Alert.alert("Header photo", undefined, [
      { text: "Choose from library", onPress: pickFromLibrary },
      { text: "Take photo", onPress: takePhoto },
      { text: "Cancel", style: "cancel" },
    ]);
  }, [pickFromLibrary, takePhoto]);

  const openPhotoMenu = useCallback(() => {
    const hasPhoto = !!resume.sections.header.photo_url;
    if (!hasPhoto) {
      openSourceMenu();
      return;
    }
    // Existing photo: offer replace/remove. Replace opens a second
    // 3-button source menu so the top-level alert stays at 3 actions
    // (Android's Alert silently drops anything past 3).
    Alert.alert("Header photo", undefined, [
      { text: "Replace photo", onPress: openSourceMenu },
      {
        text: "Remove photo",
        style: "destructive",
        onPress: () =>
          Alert.alert("Remove header photo?", undefined, [
            { text: "Cancel", style: "cancel" },
            {
              text: "Remove",
              style: "destructive",
              onPress: () => photoRemoveMut.mutate(),
            },
          ]),
      },
      { text: "Cancel", style: "cancel" },
    ]);
  }, [resume.sections.header.photo_url, openSourceMenu, photoRemoveMut]);

  const [showStyle, setShowStyle] = useState(false);
  const [showPublish, setShowPublish] = useState(false);
  const [publishedUrl, setPublishedUrl] = useState<string | null>(null);
  const saving = headerMut.isPending || summaryMut.isPending;

  const publishingMut = useMutation({
    mutationFn: (payload: PublishingPayload) => updateResumePublishing(payload),
    onSuccess: ({ resume: r, public_url }) => {
      qc.setQueryData(["resume"], { ...bundle, resume: r });
      setPublishedUrl(public_url);
      setShowPublish(false);
    },
    onError: (e: { message?: string; errors?: Record<string, string[]> }) => {
      const detail = e.errors
        ? Object.values(e.errors).flat().join("\n")
        : e.message ?? "Try again.";
      Alert.alert("Couldn't update sharing", detail);
    },
  });

  const publicPdfMut = useMutation({
    mutationFn: (v: boolean) => updateResumePublicPdf(v),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't update PDF sharing", e?.message ?? "Try again."),
  });

  return (
    <ScrollView
      contentContainerStyle={styles.body}
      keyboardShouldPersistTaps="handled"
    >
      {/* Status strip */}
      <View style={styles.statusRow}>
        <View
          style={[
            styles.statusDot,
            { backgroundColor: saving ? "#f59e0b" : "#10b981" },
          ]}
        />
        <Text style={[styles.statusText, { color: colors.mutedForeground }]}>
          {saving ? "Saving…" : "All changes saved"}
        </Text>
        <Pressable
          onPress={() => setShowStyle((s) => !s)}
          hitSlop={8}
          style={{ marginLeft: "auto" }}
        >
          <Text style={[styles.linkText, { color: colors.primary }]}>
            {showStyle ? "Hide style" : "Style"}
          </Text>
        </Pressable>
        <Pressable
          onPress={() => setShowPublish(true)}
          hitSlop={8}
          style={{ marginLeft: 14 }}
        >
          <Text style={[styles.linkText, { color: colors.primary }]}>
            Publish
          </Text>
        </Pressable>
      </View>

      {/* Publishing summary card */}
      <PublishingSummaryCard
        resume={resume}
        publishedUrl={publishedUrl}
        onOpenSheet={() => setShowPublish(true)}
        onTogglePublicPdf={(v) => publicPdfMut.mutate(v)}
        publicPdfBusy={publicPdfMut.isPending}
      />

      {showStyle ? (
        <StylePanel
          resume={resume}
          templates={bundle.registries.templates}
          themes={bundle.registries.color_themes}
          onPickTemplate={(id) => templateMut.mutate(id)}
          onPickTheme={(id) => themeMut.mutate(id)}
        />
      ) : null}

      {/* Header section */}
      <SectionTitle text="Header" />
      <Card>
        <HeaderPhotoSlot
          photoUrl={resume.sections.header.photo_url}
          busy={photoUploadMut.isPending || photoRemoveMut.isPending}
          onPress={openPhotoMenu}
        />
        <TextField
          label="Display name"
          value={header.name}
          onChangeText={(v) => setHeader((h) => ({ ...h, name: v }))}
          placeholder="Your name"
        />
        <TextField
          label="Headline"
          value={header.headline}
          onChangeText={(v) => setHeader((h) => ({ ...h, headline: v }))}
          placeholder="What you do, in one line"
        />
        <TextField
          label="Location"
          value={header.location}
          onChangeText={(v) => setHeader((h) => ({ ...h, location: v }))}
          placeholder="City, Country"
        />
        <TextField
          label="Email"
          value={header.email}
          onChangeText={(v) => setHeader((h) => ({ ...h, email: v }))}
          autoCapitalize="none"
          keyboardType="email-address"
          placeholder="you@example.com"
        />
        <TextField
          label="Phone"
          value={header.phone}
          onChangeText={(v) => setHeader((h) => ({ ...h, phone: v }))}
          keyboardType="phone-pad"
          placeholder="+1 555 123 4567"
        />
        <TextField
          label="Website"
          value={header.website}
          onChangeText={(v) => setHeader((h) => ({ ...h, website: v }))}
          autoCapitalize="none"
          keyboardType="url"
          placeholder="https://yourdomain.com"
        />
      </Card>

      {/* Summary */}
      <SectionTitle text="Summary" />
      <Card>
        <TextField
          label="Professional summary"
          value={summary}
          onChangeText={setSummary}
          multiline
          numberOfLines={5}
          placeholder="A short paragraph that introduces who you are."
        />
      </Card>

      {/* Section editors */}
      {SECTION_DEFS.map((def) => (
        <SectionEditor
          key={def.type}
          def={def}
          items={resume.items[def.type] ?? []}
          onAdd={(data) => createResumeItem(def.type, data).then(refresh)}
          onUpdate={(id, data) => updateResumeItem(id, data).then(refresh)}
          onDelete={(id) => itemDelete.mutate(id)}
          onMove={(type, ids) => itemReorder.mutate({ type, ids })}
        />
      ))}

      {/* Live preview */}
      <SectionTitle text="Preview" />
      <PreviewCard resume={{ ...resume, sections: { ...resume.sections, header, summary } }} />

      <Text style={[styles.hint, { color: colors.mutedForeground }]}>
        Header photos and custom sections are still managed on the web editor.
      </Text>

      <PublishSheet
        visible={showPublish}
        resume={resume}
        onClose={() => setShowPublish(false)}
        onSubmit={(p) => publishingMut.mutate(p)}
        onTogglePublicPdf={(v) => publicPdfMut.mutate(v)}
        publicPdfBusy={publicPdfMut.isPending}
        busy={publishingMut.isPending}
      />
    </ScrollView>
  );
}

// ── Publishing UI ───────────────────────────────────────────────

function PublishingSummaryCard({
  resume,
  publishedUrl,
  onOpenSheet,
  onTogglePublicPdf,
  publicPdfBusy,
}: {
  resume: Resume;
  publishedUrl: string | null;
  onOpenSheet: () => void;
  onTogglePublicPdf: (v: boolean) => void;
  publicPdfBusy: boolean;
}) {
  const colors = useColors();
  const handle = resume.handle;
  // Prefer the URL the publishing endpoint just handed back; otherwise
  // fall back to a synthesized one so the link stays visible across
  // navigations even before the user re-publishes.
  const pageUrl = publishedUrl ?? (handle ? `https://1inme.com/${handle}/resume` : null);
  const pdfUrl = resume.public_pdf_url ?? (handle ? `https://1inme.com/${handle}/resume.pdf` : null);

  const visMeta = VISIBILITY_OPTIONS.find((o) => o.value === resume.visibility);

  const copyOrShare = async (url: string, title: string) => {
    try {
      await Share.share({ message: url, url, title });
    } catch {
      // Share sheet dismissed — fall back to opening the link directly.
      Linking.openURL(url).catch(() => {});
    }
  };

  return (
    <Card style={{ gap: 10 }}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
        <View
          style={[
            styles.statusDot,
            { backgroundColor: resume.is_public ? "#10b981" : "#9ca3af" },
          ]}
        />
        <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 }}>
          {resume.is_public ? "Published" : "Unpublished"}
        </Text>
        {resume.is_public && visMeta ? (
          <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
            · {visMeta.label}
          </Text>
        ) : null}
        <Pressable onPress={onOpenSheet} hitSlop={8} style={{ marginLeft: "auto" }}>
          <Text style={[styles.linkText, { color: colors.primary }]}>Manage</Text>
        </Pressable>
      </View>

      {resume.is_public && pageUrl ? (
        <View style={{ gap: 6 }}>
          <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>Public page</Text>
          <Pressable onPress={() => copyOrShare(pageUrl, "My resume")}>
            <Text
              style={{ color: colors.primary, fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 }}
              numberOfLines={1}
            >
              {pageUrl}
            </Text>
          </Pressable>
        </View>
      ) : null}

      <View style={styles.switchRow}>
        <View style={{ flex: 1 }}>
          <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 }}>
            Allow public PDF download
          </Text>
          <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
            {resume.is_public_pdf ? "Anyone with the link can download it." : "Only you can download the PDF."}
          </Text>
        </View>
        <Switch
          value={resume.is_public_pdf}
          onValueChange={onTogglePublicPdf}
          disabled={publicPdfBusy}
          trackColor={{ true: colors.primary }}
        />
      </View>

      {resume.is_public_pdf && pdfUrl ? (
        <Pressable onPress={() => copyOrShare(pdfUrl, "My resume (PDF)")}>
          <Text
            style={{ color: colors.primary, fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 }}
            numberOfLines={1}
          >
            {pdfUrl}
          </Text>
        </Pressable>
      ) : null}
    </Card>
  );
}

function PublishSheet({
  visible,
  resume,
  onClose,
  onSubmit,
  onTogglePublicPdf,
  publicPdfBusy,
  busy,
}: {
  visible: boolean;
  resume: Resume;
  onClose: () => void;
  onSubmit: (p: PublishingPayload) => void;
  onTogglePublicPdf: (v: boolean) => void;
  publicPdfBusy: boolean;
  busy: boolean;
}) {
  const colors = useColors();
  const qc = useQueryClient();

  // Local form state — re-seeded each time the sheet opens so an in-
  // flight edit on the server can't get clobbered while the sheet is
  // closed and so opening the sheet always reflects the latest server
  // state rather than stale local edits.
  const [isPublic, setIsPublic] = useState(resume.is_public);
  const [visibility, setVisibility] = useState<ResumeVisibility>(
    (resume.visibility as ResumeVisibility) ?? "public",
  );
  const [allowIndexing, setAllowIndexing] = useState(resume.allow_indexing);
  const [metaDescription, setMetaDescription] = useState(resume.meta_description ?? "");
  const [password, setPassword] = useState("");
  const [touchedPassword, setTouchedPassword] = useState(false);
  // Expiration is held as a YYYY-MM-DD string for a simple cross-
  // platform input. We send it up as ISO at submit time. Empty string
  // = "never expires" (clears any stored expiration).
  const [expiresDate, setExpiresDate] = useState(toLocalDate(resume.expires_at));
  const [touchedExpiry, setTouchedExpiry] = useState(false);
  const [showViewLog, setShowViewLog] = useState(false);

  useEffect(() => {
    if (!visible) return;
    setIsPublic(resume.is_public);
    setVisibility((resume.visibility as ResumeVisibility) ?? "public");
    setAllowIndexing(resume.allow_indexing);
    setMetaDescription(resume.meta_description ?? "");
    setPassword("");
    setTouchedPassword(false);
    setExpiresDate(toLocalDate(resume.expires_at));
    setTouchedExpiry(false);
  }, [visible, resume]);

  const revokeMut = useMutation({
    mutationFn: () => revokeResumeShare({}),
    onSuccess: (r) => {
      // Push the new share_revision into the cache so the rest of the
      // page reflects "no longer expired / sessions invalidated".
      qc.setQueryData<ResumeBundle | undefined>(["resume"], (prev) =>
        prev ? { ...prev, resume: r } : prev,
      );
      Alert.alert("Share revoked", "Anyone who already typed the password will be prompted again next time.");
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't revoke share", e?.message ?? "Try again."),
  });

  const submit = () => {
    const payload: PublishingPayload = {
      is_public: isPublic,
      visibility,
      allow_indexing: allowIndexing,
      meta_description: metaDescription.trim() === "" ? null : metaDescription.trim(),
    };
    if (visibility === "password" && touchedPassword) {
      payload.password = password;
    }
    if (touchedExpiry) {
      // Empty string clears the deadline server-side. A bare YYYY-MM-DD
      // is fine — Carbon parses it as midnight in the server's TZ.
      payload.expires_at = expiresDate.trim();
    }
    onSubmit(payload);
  };

  const confirmRevoke = () => {
    Alert.alert(
      "Revoke active sessions?",
      "Everyone who already typed the password will be prompted again on their next visit. The link itself does not change.",
      [
        { text: "Cancel", style: "cancel" },
        { text: "Revoke", style: "destructive", onPress: () => revokeMut.mutate() },
      ],
    );
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      onRequestClose={onClose}
    >
      <View style={styles.sheetBackdrop}>
        <View style={[styles.sheet, { backgroundColor: colors.background }]}>
          <View style={styles.sheetHandle} />
          <View style={styles.sheetHeader}>
            <Text style={[styles.sectionTitle, { color: colors.foreground, marginTop: 0, marginBottom: 0 }]}>
              Publish & sharing
            </Text>
            <Pressable onPress={onClose} hitSlop={8}>
              <Feather name="x" size={20} color={colors.mutedForeground} />
            </Pressable>
          </View>

          <ScrollView
            contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 32 }}
            keyboardShouldPersistTaps="handled"
          >
            <Card>
              <View style={styles.switchRow}>
                <View style={{ flex: 1 }}>
                  <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 }}>
                    Publish resume
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
                    Turn off to take the public page offline.
                  </Text>
                </View>
                <Switch
                  value={isPublic}
                  onValueChange={setIsPublic}
                  trackColor={{ true: colors.primary }}
                />
              </View>
            </Card>

            <View>
              <Text style={[styles.subhead, { color: colors.mutedForeground, marginBottom: 8 }]}>
                Who can view it
              </Text>
              <Card style={{ gap: 4 }}>
                {VISIBILITY_OPTIONS.map((opt) => {
                  const active = visibility === opt.value;
                  return (
                    <Pressable
                      key={opt.value}
                      onPress={() => setVisibility(opt.value)}
                      style={[
                        styles.visRow,
                        { borderColor: active ? colors.primary : "transparent" },
                      ]}
                    >
                      <View style={{ flex: 1 }}>
                        <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 }}>
                          {opt.label}
                        </Text>
                        <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
                          {opt.hint}
                        </Text>
                      </View>
                      <Feather
                        name={active ? "check-circle" : "circle"}
                        size={18}
                        color={active ? colors.primary : colors.border}
                      />
                    </Pressable>
                  );
                })}
              </Card>
            </View>

            {visibility === "password" ? (
              <Card style={{ gap: 10 }}>
                <TextField
                  label={resume.has_password ? "New password (leave blank to keep current)" : "Password"}
                  value={password}
                  onChangeText={(v) => {
                    setPassword(v);
                    setTouchedPassword(true);
                  }}
                  placeholder="Enter a password"
                  autoCapitalize="none"
                  secureTextEntry
                />
                {resume.has_password && touchedPassword && password === "" ? (
                  <Text style={{ color: colors.destructive, fontSize: 11, marginTop: 4 }}>
                    Saving with an empty password will clear the existing one.
                  </Text>
                ) : null}

                <TextField
                  label="Expires (YYYY-MM-DD, optional)"
                  value={expiresDate}
                  onChangeText={(v) => {
                    setExpiresDate(v);
                    setTouchedExpiry(true);
                  }}
                  placeholder="2026-12-31"
                  autoCapitalize="none"
                  keyboardType="numbers-and-punctuation"
                />
                {resume.is_share_expired ? (
                  <Text style={{ color: colors.destructive, fontSize: 11 }}>
                    This share has expired — visitors are seeing an expiry message.
                  </Text>
                ) : expiresDate ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                    Visitors will be blocked after this date.
                  </Text>
                ) : (
                  <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                    Leave blank to share until you turn it off.
                  </Text>
                )}

                <Pressable
                  onPress={confirmRevoke}
                  disabled={revokeMut.isPending}
                  style={{
                    flexDirection: "row",
                    alignItems: "center",
                    gap: 8,
                    paddingVertical: 8,
                    opacity: revokeMut.isPending ? 0.6 : 1,
                  }}
                >
                  <Feather name="rotate-ccw" size={14} color={colors.destructive} />
                  <Text style={{ color: colors.destructive, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 }}>
                    {revokeMut.isPending ? "Revoking…" : "Revoke active sessions"}
                  </Text>
                </Pressable>
                <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                  Forces everyone who already typed the password back to the prompt. The URL stays the same.
                </Text>
              </Card>
            ) : null}

            {/* Audit log entry-point — visible whenever the resume is
                public, regardless of visibility tier, since views are
                logged for every non-owner visit that gets through. */}
            {isPublic ? (
              <Card>
                <Pressable
                  onPress={() => setShowViewLog(true)}
                  style={{ flexDirection: "row", alignItems: "center", gap: 10 }}
                >
                  <Feather name="eye" size={16} color={colors.primary} />
                  <View style={{ flex: 1 }}>
                    <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 }}>
                      View log ({resume.view_count.toLocaleString()})
                    </Text>
                    <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
                      See who's been visiting your public resume page.
                    </Text>
                  </View>
                  <Feather name="chevron-right" size={16} color={colors.mutedForeground} />
                </Pressable>
              </Card>
            ) : null}

            <Card>
              <View style={styles.switchRow}>
                <View style={{ flex: 1 }}>
                  <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 }}>
                    Allow search engines to index
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
                    Lets Google show your resume in results.
                  </Text>
                </View>
                <Switch
                  value={allowIndexing}
                  onValueChange={setAllowIndexing}
                  trackColor={{ true: colors.primary }}
                />
              </View>
            </Card>

            <Card>
              <TextField
                label="Meta description"
                value={metaDescription}
                onChangeText={setMetaDescription}
                placeholder="A short blurb shown in search and social previews."
                multiline
                numberOfLines={3}
              />
              <Text style={{ color: colors.mutedForeground, fontSize: 10, textAlign: "right" }}>
                {metaDescription.length}/240
              </Text>
            </Card>

            <Card>
              <View style={styles.switchRow}>
                <View style={{ flex: 1 }}>
                  <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 }}>
                    Allow public PDF download
                  </Text>
                  <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
                    {resume.is_public_pdf
                      ? "Anyone with the link can download the PDF."
                      : "Only you can download the PDF."}
                  </Text>
                </View>
                {/* Saved immediately — separate endpoint from the rest of the
                    publishing form so toggling it doesn't require hitting Save. */}
                <Switch
                  value={resume.is_public_pdf}
                  onValueChange={onTogglePublicPdf}
                  disabled={publicPdfBusy}
                  trackColor={{ true: colors.primary }}
                />
              </View>
            </Card>

            <View style={styles.rowGap}>
              <Button label="Cancel" variant="outline" onPress={onClose} style={{ flex: 1 }} />
              <Button label={busy ? "Saving…" : "Save"} onPress={submit} loading={busy} style={{ flex: 1 }} />
            </View>
          </ScrollView>
        </View>
      </View>

      <ViewLogModal visible={showViewLog} onClose={() => setShowViewLog(false)} />
    </Modal>
  );
}

/**
 * Convert an ISO8601 datetime to "YYYY-MM-DD" for the simple
 * date input on mobile. Returns "" for null/invalid values.
 */
function toLocalDate(iso: string | null | undefined): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (isNaN(d.getTime())) return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Owner-facing audit log of who viewed the public resume page. Pulls
 * paginated rows from /api/v1/resume/views.
 */
function ViewLogModal({
  visible,
  onClose,
}: {
  visible: boolean;
  onClose: () => void;
}) {
  const colors = useColors();
  const [page, setPage] = useState(1);

  const q = useQuery({
    queryKey: ["resume-views", page],
    queryFn: () => getResumeViews(page, 25),
    enabled: visible,
    staleTime: 30_000,
  });

  useEffect(() => {
    if (visible) setPage(1);
  }, [visible]);

  const formatWhen = (iso: string | null) => {
    if (!iso) return "—";
    const d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleString();
  };

  const formatReferrer = (r: string | null) => {
    if (!r) return "Direct";
    try {
      return new URL(r).hostname;
    } catch {
      return r;
    }
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.sheetBackdrop}>
        <View style={[styles.sheet, { backgroundColor: colors.background }]}>
          <View style={styles.sheetHandle} />
          <View style={styles.sheetHeader}>
            <Text style={[styles.sectionTitle, { color: colors.foreground, marginTop: 0, marginBottom: 0 }]}>
              Resume views
            </Text>
            <Pressable onPress={onClose} hitSlop={8}>
              <Feather name="x" size={20} color={colors.mutedForeground} />
            </Pressable>
          </View>

          <ScrollView contentContainerStyle={{ padding: 16, gap: 10, paddingBottom: 32 }}>
            <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
              One row per unique visitor per day. Bots and your own visits aren't logged.
            </Text>

            {q.isLoading ? (
              <ActivityIndicator color={colors.primary} style={{ marginTop: 20 }} />
            ) : q.isError ? (
              <Text style={{ color: colors.destructive, fontSize: 12 }}>
                Couldn't load views. Pull down and try again.
              </Text>
            ) : (q.data?.views.length ?? 0) === 0 ? (
              <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 12 }}>
                No views yet.
              </Text>
            ) : (
              <View style={{ gap: 8 }}>
                {q.data!.views.map((row: ResumeViewLogEntry) => (
                  <Card key={row.id} style={{ gap: 4 }}>
                    <View style={{ flexDirection: "row", justifyContent: "space-between" }}>
                      <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 }}>
                        {formatWhen(row.viewed_at)}
                      </Text>
                      <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                        {row.country_code ?? "—"}
                      </Text>
                    </View>
                    <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
                      {row.viewer_handle ? `@${row.viewer_handle}` : "Anonymous visitor"}
                      {"  ·  "}
                      {formatReferrer(row.referrer)}
                    </Text>
                  </Card>
                ))}
              </View>
            )}

            {q.data && q.data.meta.last_page > 1 ? (
              <View style={[styles.rowGap, { marginTop: 8 }]}>
                <Button
                  label="Previous"
                  variant="outline"
                  onPress={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1}
                  style={{ flex: 1 }}
                />
                <Text
                  style={{
                    color: colors.mutedForeground,
                    fontSize: 12,
                    alignSelf: "center",
                    marginHorizontal: 8,
                  }}
                >
                  {page} / {q.data.meta.last_page}
                </Text>
                <Button
                  label="Next"
                  variant="outline"
                  onPress={() => setPage((p) => Math.min(q.data!.meta.last_page, p + 1))}
                  disabled={page >= q.data.meta.last_page}
                  style={{ flex: 1 }}
                />
              </View>
            ) : null}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

// ── Reusable bits ────────────────────────────────────────────────

function SectionTitle({ text }: { text: string }) {
  const colors = useColors();
  return (
    <Text style={[styles.sectionTitle, { color: colors.foreground }]}>{text}</Text>
  );
}

function HeaderPhotoSlot({
  photoUrl,
  busy,
  onPress,
}: {
  photoUrl: string | null;
  busy: boolean;
  onPress: () => void;
}) {
  const colors = useColors();
  return (
    <Pressable
      onPress={busy ? undefined : onPress}
      accessibilityRole="button"
      accessibilityLabel={photoUrl ? "Change header photo" : "Add header photo"}
      style={({ pressed }) => [
        styles.photoRow,
        { opacity: pressed && !busy ? 0.85 : 1 },
      ]}
    >
      <View
        style={[
          styles.photoThumb,
          { borderColor: colors.border, backgroundColor: colors.muted },
        ]}
      >
        {busy ? (
          <ActivityIndicator color={colors.primary} />
        ) : photoUrl ? (
          <Image source={{ uri: photoUrl }} style={styles.photoImg} />
        ) : (
          <Feather name="user" size={28} color={colors.mutedForeground} />
        )}
      </View>
      <View style={{ flex: 1 }}>
        <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 }}>
          Header photo
        </Text>
        <Text style={{ color: colors.mutedForeground, fontSize: 11, marginTop: 2 }}>
          {photoUrl
            ? "Tap to change or remove."
            : "Tap to pick from your library or take a new one."}
        </Text>
      </View>
      <Feather
        name={photoUrl ? "edit-2" : "plus"}
        size={18}
        color={colors.primary}
      />
    </Pressable>
  );
}

function StylePanel({
  resume,
  templates,
  themes,
  onPickTemplate,
  onPickTheme,
}: {
  resume: Resume;
  templates: Resume["template"][];
  themes: Resume["color_theme"][];
  onPickTemplate: (id: string) => void;
  onPickTheme: (id: string) => void;
}) {
  const colors = useColors();
  return (
    <Card style={{ gap: 14 }}>
      <Text style={[styles.subhead, { color: colors.mutedForeground }]}>Template</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
        {templates.map((t) => {
          const active = t.id === resume.template_id;
          return (
            <Pressable
              key={t.id}
              onPress={() => !active && onPickTemplate(t.id)}
              style={[
                styles.chip,
                {
                  borderColor: active ? colors.primary : colors.border,
                  backgroundColor: active ? colors.primary + "22" : "transparent",
                },
              ]}
            >
              <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 }}>
                {t.name ?? t.id}
              </Text>
              {t.premium ? (
                <Text style={{ color: colors.primary, marginLeft: 6, fontSize: 10 }}>PRO</Text>
              ) : null}
            </Pressable>
          );
        })}
      </ScrollView>

      <Text style={[styles.subhead, { color: colors.mutedForeground }]}>Color theme</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
        {themes.map((th) => {
          const active = th.id === resume.color_theme_id;
          return (
            <Pressable
              key={th.id}
              onPress={() => !active && onPickTheme(th.id)}
              style={[
                styles.swatch,
                {
                  backgroundColor: th.accent ?? "#7c3aed",
                  borderColor: active ? colors.foreground : "transparent",
                },
              ]}
              accessibilityLabel={th.name ?? th.id}
            />
          );
        })}
      </ScrollView>
    </Card>
  );
}

// ── Section item editor ─────────────────────────────────────────

type FieldKind = "text" | "multiline" | "url" | "email" | "month" | "switch" | "number";
type FieldDef = {
  key: string;
  label: string;
  kind?: FieldKind;
  required?: boolean;
  placeholder?: string;
};
type SectionDef = {
  type: ResumeSectionType;
  title: string;
  emptyHint: string;
  fields: FieldDef[];
  /** Render a one-line label for the saved item in the list. */
  summarize: (data: Record<string, unknown>) => string;
};

const SECTION_DEFS: SectionDef[] = [
  {
    type: "experience",
    title: "Experience",
    emptyHint: "Add your roles, most recent first.",
    fields: [
      { key: "company", label: "Company", required: true },
      { key: "role", label: "Role", required: true },
      { key: "location", label: "Location" },
      { key: "start_date", label: "Start (YYYY-MM)", kind: "month", placeholder: "2024-01" },
      { key: "end_date", label: "End (YYYY-MM)", kind: "month", placeholder: "Leave blank if current" },
      { key: "is_current", label: "I currently work here", kind: "switch" },
      { key: "description", label: "Description", kind: "multiline" },
    ],
    summarize: (d) => `${d.role || "Role"} · ${d.company || "Company"}`,
  },
  {
    type: "education",
    title: "Education",
    emptyHint: "Add schools, certifications, courses.",
    fields: [
      { key: "school", label: "School", required: true },
      { key: "degree", label: "Degree" },
      { key: "field", label: "Field of study" },
      { key: "start_date", label: "Start (YYYY-MM)", kind: "month" },
      { key: "end_date", label: "End (YYYY-MM)", kind: "month" },
      { key: "description", label: "Notes", kind: "multiline" },
    ],
    summarize: (d) => `${d.school || "School"}${d.degree ? ` · ${d.degree}` : ""}`,
  },
  {
    type: "skills",
    title: "Skills",
    emptyHint: "Tag the skills you want to highlight.",
    fields: [
      { key: "name", label: "Skill", required: true },
      { key: "group", label: "Group (e.g. Languages, Tools)" },
      { key: "level", label: "Level (1–5)", kind: "number" },
    ],
    summarize: (d) => `${d.name || "Skill"}${d.group ? ` (${d.group})` : ""}`,
  },
  {
    type: "projects",
    title: "Projects",
    emptyHint: "Showcase work you're proud of.",
    fields: [
      { key: "name", label: "Project", required: true },
      { key: "role", label: "Your role" },
      { key: "url", label: "URL", kind: "url" },
      { key: "start_date", label: "Start (YYYY-MM)", kind: "month" },
      { key: "end_date", label: "End (YYYY-MM)", kind: "month" },
      { key: "description", label: "Description", kind: "multiline" },
    ],
    summarize: (d) => `${d.name || "Project"}`,
  },
  {
    type: "certifications",
    title: "Certifications",
    emptyHint: "Add credentials and certifications.",
    fields: [
      { key: "name", label: "Name", required: true },
      { key: "issuer", label: "Issuer" },
      { key: "issued_on", label: "Issued (YYYY-MM)", kind: "month" },
      { key: "expires_on", label: "Expires (YYYY-MM)", kind: "month" },
      { key: "credential_url", label: "Credential URL", kind: "url" },
    ],
    summarize: (d) => `${d.name || "Certification"}${d.issuer ? ` · ${d.issuer}` : ""}`,
  },
  {
    type: "awards",
    title: "Awards",
    emptyHint: "Recognition and achievements.",
    fields: [
      { key: "title", label: "Title", required: true },
      { key: "issuer", label: "Issuer" },
      { key: "date", label: "Date (YYYY-MM)", kind: "month" },
      { key: "description", label: "Description", kind: "multiline" },
    ],
    summarize: (d) => `${d.title || "Award"}`,
  },
  {
    type: "languages",
    title: "Languages",
    emptyHint: "Languages you speak.",
    fields: [
      { key: "name", label: "Language", required: true },
      { key: "proficiency", label: "Proficiency (basic/conversational/professional/fluent/native)" },
    ],
    summarize: (d) => `${d.name || "Language"}${d.proficiency ? ` · ${d.proficiency}` : ""}`,
  },
  {
    type: "links",
    title: "Links",
    emptyHint: "GitHub, LinkedIn, portfolio…",
    fields: [
      { key: "label", label: "Label", required: true },
      { key: "url", label: "URL", required: true, kind: "url" },
    ],
    summarize: (d) => `${d.label || "Link"}`,
  },
];

function SectionEditor({
  def,
  items,
  onAdd,
  onUpdate,
  onDelete,
  onMove,
}: {
  def: SectionDef;
  items: ResumeItem[];
  onAdd: (data: Record<string, unknown>) => Promise<unknown>;
  onUpdate: (id: number, data: Record<string, unknown>) => Promise<unknown>;
  onDelete: (id: number) => void;
  onMove: (type: ResumeSectionType, ids: number[]) => void;
}) {
  const colors = useColors();
  const [editing, setEditing] = useState<{ id: number | null; data: Record<string, unknown> } | null>(null);
  const [busy, setBusy] = useState(false);

  const startNew = () => {
    const blank: Record<string, unknown> = {};
    def.fields.forEach((f) => {
      blank[f.key] = f.kind === "switch" ? false : "";
    });
    setEditing({ id: null, data: blank });
  };

  const move = (id: number, dir: -1 | 1) => {
    const ids = items.map((i) => i.id);
    const idx = ids.indexOf(id);
    const target = idx + dir;
    if (idx < 0 || target < 0 || target >= ids.length) return;
    [ids[idx], ids[target]] = [ids[target], ids[idx]];
    onMove(def.type, ids);
  };

  const save = async () => {
    if (!editing) return;
    setBusy(true);
    try {
      const cleaned: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(editing.data)) {
        if (v === "" || v === null || v === undefined) continue;
        const f = def.fields.find((x) => x.key === k);
        if (f?.kind === "number") {
          const n = Number(v);
          if (Number.isFinite(n)) cleaned[k] = n;
        } else if (f?.kind === "switch") {
          if (v) cleaned[k] = true;
        } else {
          cleaned[k] = v;
        }
      }
      if (editing.id == null) await onAdd(cleaned);
      else await onUpdate(editing.id, cleaned);
      setEditing(null);
    } catch (e) {
      const err = e as { message?: string; errors?: Record<string, string[]> };
      const detail = err.errors
        ? Object.values(err.errors).flat().join("\n")
        : err.message ?? "Try again.";
      Alert.alert("Couldn't save", detail);
    } finally {
      setBusy(false);
    }
  };

  return (
    <View>
      <View style={styles.sectionHeadRow}>
        <SectionTitle text={def.title} />
        <Pressable onPress={startNew} hitSlop={8} style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
          <Feather name="plus" size={16} color={colors.primary} />
          <Text style={[styles.linkText, { color: colors.primary }]}>Add</Text>
        </Pressable>
      </View>

      {items.length === 0 && !editing ? (
        <Card>
          <Text style={[styles.hint, { color: colors.mutedForeground, textAlign: "left", marginTop: 0 }]}>
            {def.emptyHint}
          </Text>
        </Card>
      ) : null}

      {(() => {
        const isAnyEditing = editing != null && editing.id != null;
        const renderRow = (it: ResumeItem, idx: number) => (
          <Card style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
            <Feather
              name="menu"
              size={16}
              color={colors.mutedForeground}
              accessibilityLabel="Drag handle. Long-press and drag to reorder."
            />
            <View style={{ flex: 1 }}>
              <Text
                numberOfLines={1}
                ellipsizeMode="tail"
                style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 }}
              >
                {def.summarize(it.data)}
              </Text>
            </View>
            <Pressable
              hitSlop={6}
              onPress={() => move(it.id, -1)}
              disabled={idx === 0}
              accessibilityLabel="Move up"
            >
              <Feather name="chevron-up" size={18} color={idx === 0 ? colors.border : colors.mutedForeground} />
            </Pressable>
            <Pressable
              hitSlop={6}
              onPress={() => move(it.id, 1)}
              disabled={idx === items.length - 1}
              accessibilityLabel="Move down"
            >
              <Feather name="chevron-down" size={18} color={idx === items.length - 1 ? colors.border : colors.mutedForeground} />
            </Pressable>
            <Pressable hitSlop={6} onPress={() => setEditing({ id: it.id, data: { ...it.data } })}>
              <Feather name="edit-2" size={16} color={colors.primary} />
            </Pressable>
            <Pressable
              hitSlop={6}
              onPress={() =>
                Alert.alert("Delete entry?", def.summarize(it.data), [
                  { text: "Cancel", style: "cancel" },
                  { text: "Delete", style: "destructive", onPress: () => onDelete(it.id) },
                ])
              }
            >
              <Feather name="trash-2" size={16} color={colors.destructive} />
            </Pressable>
          </Card>
        );

        if (isAnyEditing) {
          // Drag is disabled while an item is being edited inline; rows
          // would have wildly different heights and a half-typed edit
          // shouldn't fight the gesture. Chevrons remain available.
          return items.map((it, idx) => {
            if (editing!.id === it.id) {
              return (
                <Card key={it.id} style={{ gap: 8 }}>
                  <FieldStack
                    def={def}
                    data={editing!.data}
                    onChange={(k, v) => setEditing({ ...editing!, data: { ...editing!.data, [k]: v } })}
                  />
                  <View style={styles.rowGap}>
                    <Button label="Cancel" variant="outline" onPress={() => setEditing(null)} style={{ flex: 1 }} />
                    <Button label={busy ? "Saving…" : "Save"} onPress={save} loading={busy} style={{ flex: 1 }} />
                  </View>
                </Card>
              );
            }
            return <View key={it.id}>{renderRow(it, idx)}</View>;
          });
        }

        return (
          <DraggableItemList
            items={items}
            renderRow={renderRow}
            onReorder={(ids) => onMove(def.type, ids)}
          />
        );
      })()}

      {editing && editing.id == null ? (
        <Card style={{ gap: 8 }}>
          <FieldStack
            def={def}
            data={editing.data}
            onChange={(k, v) => setEditing({ ...editing, data: { ...editing.data, [k]: v } })}
          />
          <View style={styles.rowGap}>
            <Button label="Cancel" variant="outline" onPress={() => setEditing(null)} style={{ flex: 1 }} />
            <Button label={busy ? "Saving…" : "Add"} onPress={save} loading={busy} style={{ flex: 1 }} />
          </View>
        </Card>
      ) : null}
    </View>
  );
}

// ── Drag-to-reorder list ────────────────────────────────────────
//
// Long-press on any row to "pick it up", then drag up/down to
// reorder. Other rows slide out of the way as the dragged row
// crosses their midpoint. On release we call `onReorder` with the
// new id order, which the parent persists via the existing
// /resume/items/reorder endpoint.
//
// Implementation notes:
//  * Rows render in their original DOM order. Each row's translateY
//    is `(currentSlot - originalIndex) * rowHeight`, animated.
//  * The dragged row ignores its slot offset and follows the finger
//    directly, so movement feels 1:1.
//  * We measure the first row's height once and assume all rows in
//    the same section are uniform (they all show one summary line +
//    icons inside the same Card).
function DraggableItemList({
  items,
  renderRow,
  onReorder,
}: {
  items: ResumeItem[];
  renderRow: (item: ResumeItem, idx: number) => React.ReactNode;
  onReorder: (ids: number[]) => void;
}) {
  const [rowH, setRowH] = useState(0);
  const idsKey = items.map((i) => i.id).join(",");
  const lastCommittedKey = useRef(idsKey);

  // Mutable map: id -> current visual slot. Lives outside React
  // because it's read/written from the UI thread by gesture
  // handlers, and we don't want to re-render on every frame.
  const slots = useSharedValue<Record<string, number>>(
    Object.fromEntries(items.map((it, idx) => [String(it.id), idx])),
  );

  useEffect(() => {
    slots.value = Object.fromEntries(items.map((it, idx) => [String(it.id), idx]));
    lastCommittedKey.current = idsKey;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [idsKey]);

  const commit = useCallback(() => {
    const entries = Object.entries(slots.value).sort((a, b) => a[1] - b[1]);
    const newIds = entries.map(([id]) => Number(id));
    const newKey = newIds.join(",");
    if (newKey !== lastCommittedKey.current) {
      lastCommittedKey.current = newKey;
      onReorder(newIds);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [onReorder]);

  if (items.length <= 1) {
    return (
      <>
        {items.map((it, idx) => (
          <View key={it.id}>{renderRow(it, idx)}</View>
        ))}
      </>
    );
  }

  return (
    <View>
      {items.map((it, idx) => (
        <DraggableRow
          key={it.id}
          id={it.id}
          origIdx={idx}
          totalItems={items.length}
          rowH={rowH}
          slots={slots}
          onMeasureRow={idx === 0 ? setRowH : undefined}
          onCommit={commit}
        >
          {renderRow(it, idx)}
        </DraggableRow>
      ))}
    </View>
  );
}

function DraggableRow({
  id,
  origIdx,
  totalItems,
  rowH,
  slots,
  children,
  onMeasureRow,
  onCommit,
}: {
  id: number;
  origIdx: number;
  totalItems: number;
  rowH: number;
  slots: ReturnType<typeof useSharedValue<Record<string, number>>>;
  children: React.ReactNode;
  onMeasureRow?: (h: number) => void;
  onCommit: () => void;
}) {
  const isDragging = useSharedValue(false);
  const panY = useSharedValue(0);

  const triggerHaptic = useCallback(() => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
  }, []);

  const gesture = useMemo(() => {
    return Gesture.Pan()
      .activateAfterLongPress(350)
      .onStart(() => {
        "worklet";
        isDragging.value = true;
        panY.value = 0;
        runOnJS(triggerHaptic)();
      })
      .onUpdate((e) => {
        "worklet";
        if (rowH <= 0) return;
        panY.value = e.translationY;
        const myCurrent = slots.value[String(id)] ?? origIdx;
        const target = Math.max(
          0,
          Math.min(totalItems - 1, origIdx + Math.round(e.translationY / rowH)),
        );
        if (target === myCurrent) return;
        const next: Record<string, number> = { ...slots.value };
        if (target > myCurrent) {
          for (const k of Object.keys(next)) {
            const v = next[k];
            if (Number(k) === id) continue;
            if (v > myCurrent && v <= target) next[k] = v - 1;
          }
        } else {
          for (const k of Object.keys(next)) {
            const v = next[k];
            if (Number(k) === id) continue;
            if (v >= target && v < myCurrent) next[k] = v + 1;
          }
        }
        next[String(id)] = target;
        slots.value = next;
      })
      .onEnd(() => {
        "worklet";
        isDragging.value = false;
        panY.value = 0;
        runOnJS(onCommit)();
      })
      .onFinalize(() => {
        "worklet";
        isDragging.value = false;
        panY.value = 0;
      });
  }, [id, origIdx, totalItems, rowH, slots, isDragging, panY, onCommit, triggerHaptic]);

  const animStyle = useAnimatedStyle(() => {
    const mySlot = slots.value[String(id)] ?? origIdx;
    if (isDragging.value) {
      return {
        transform: [{ translateY: panY.value }, { scale: 1.03 }],
        zIndex: 100,
        elevation: 10,
        shadowColor: "#000",
        shadowOpacity: 0.18,
        shadowRadius: 8,
        shadowOffset: { width: 0, height: 4 },
        opacity: 0.97,
      };
    }
    const target = (mySlot - origIdx) * rowH;
    return {
      transform: [{ translateY: withTiming(target, { duration: 180 }) }],
      zIndex: 1,
    };
  });

  return (
    <GestureDetector gesture={gesture}>
      <Animated.View
        style={animStyle}
        onLayout={
          onMeasureRow
            ? (e) => {
                const h = e.nativeEvent.layout.height;
                if (h > 0) onMeasureRow(h);
              }
            : undefined
        }
      >
        {children}
      </Animated.View>
    </GestureDetector>
  );
}

function FieldStack({
  def,
  data,
  onChange,
}: {
  def: SectionDef;
  data: Record<string, unknown>;
  onChange: (k: string, v: unknown) => void;
}) {
  const colors = useColors();
  return (
    <>
      {def.fields.map((f) => {
        if (f.kind === "switch") {
          return (
            <View key={f.key} style={styles.switchRow}>
              <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium", fontSize: 13, flex: 1 }}>
                {f.label}
              </Text>
              <Switch
                value={!!data[f.key]}
                onValueChange={(v) => onChange(f.key, v)}
                trackColor={{ true: colors.primary }}
              />
            </View>
          );
        }
        return (
          <TextField
            key={f.key}
            label={f.label + (f.required ? " *" : "")}
            value={String(data[f.key] ?? "")}
            onChangeText={(v) => onChange(f.key, v)}
            placeholder={f.placeholder}
            multiline={f.kind === "multiline"}
            numberOfLines={f.kind === "multiline" ? 4 : undefined}
            keyboardType={
              f.kind === "url" ? "url" : f.kind === "email" ? "email-address" : f.kind === "number" ? "number-pad" : "default"
            }
            autoCapitalize={f.kind === "url" || f.kind === "email" ? "none" : undefined}
          />
        );
      })}
    </>
  );
}

// ── Live preview ────────────────────────────────────────────────

function PreviewCard({ resume }: { resume: Resume }) {
  const colors = useColors();
  const accent = resume.color_theme?.accent ?? colors.primary;
  const h = resume.sections.header;
  const items = resume.items;

  const contact = useMemo(
    () => [h.location, h.email, h.phone, h.website].filter(Boolean).join(" · "),
    [h.location, h.email, h.phone, h.website],
  );

  const renderGroup = (title: string, type: ResumeSectionType, render: (it: ResumeItem) => React.ReactNode) => {
    const list = items[type] ?? [];
    if (list.length === 0) return null;
    return (
      <View style={{ marginTop: 12 }}>
        <Text style={[styles.pvSectionTitle, { color: accent, borderColor: accent }]}>{title.toUpperCase()}</Text>
        {list.map((it) => (
          <View key={it.id} style={{ marginTop: 6 }}>
            {render(it)}
          </View>
        ))}
      </View>
    );
  };

  return (
    <Card style={{ backgroundColor: "#fff", padding: 18 }}>
      <View style={{ flexDirection: "row", gap: 12, alignItems: "center" }}>
        {h.photo_url ? (
          <Image
            source={{ uri: h.photo_url }}
            style={{ width: 56, height: 56, borderRadius: 28, backgroundColor: "#eee" }}
          />
        ) : null}
        <View style={{ flex: 1 }}>
          <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 22, color: "#111" }}>
            {h.name || "Your name"}
          </Text>
          {h.headline ? (
            <Text style={{ fontSize: 13, color: "#444", marginTop: 2 }}>{h.headline}</Text>
          ) : null}
          {contact ? (
            <Text style={{ fontSize: 11, color: "#666", marginTop: 4 }}>{contact}</Text>
          ) : null}
        </View>
      </View>

      {resume.sections.summary ? (
        <View style={{ marginTop: 12 }}>
          <Text style={[styles.pvSectionTitle, { color: accent, borderColor: accent }]}>SUMMARY</Text>
          <Text style={{ fontSize: 12, color: "#222", lineHeight: 18, marginTop: 4 }}>
            {resume.sections.summary}
          </Text>
        </View>
      ) : null}

      {renderGroup("Experience", "experience", (it) => (
        <>
          <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 13, color: "#111" }}>
            {String(it.data.role || "Role")} · {String(it.data.company || "")}
          </Text>
          {(it.data.start_date || it.data.end_date) ? (
            <Text style={{ fontSize: 10, color: "#666" }}>
              {String(it.data.start_date || "")} – {it.data.is_current ? "Present" : String(it.data.end_date || "")}
            </Text>
          ) : null}
          {it.data.description ? (
            <Text style={{ fontSize: 11, color: "#222", marginTop: 2 }}>{String(it.data.description)}</Text>
          ) : null}
        </>
      ))}

      {renderGroup("Education", "education", (it) => (
        <>
          <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 13, color: "#111" }}>
            {String(it.data.school || "School")}
          </Text>
          {(it.data.degree || it.data.field) ? (
            <Text style={{ fontSize: 11, color: "#444" }}>
              {[it.data.degree, it.data.field].filter(Boolean).join(", ") as string}
            </Text>
          ) : null}
        </>
      ))}

      {renderGroup("Projects", "projects", (it) => (
        <>
          <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 13, color: "#111" }}>
            {String(it.data.name || "Project")}
          </Text>
          {it.data.description ? (
            <Text style={{ fontSize: 11, color: "#222" }}>{String(it.data.description)}</Text>
          ) : null}
        </>
      ))}

      {(items.skills?.length ?? 0) > 0 ? (
        <View style={{ marginTop: 12 }}>
          <Text style={[styles.pvSectionTitle, { color: accent, borderColor: accent }]}>SKILLS</Text>
          <Text style={{ fontSize: 12, color: "#222", marginTop: 4 }}>
            {(items.skills ?? []).map((s) => String(s.data.name)).join(" · ")}
          </Text>
        </View>
      ) : null}

      {renderGroup("Certifications", "certifications", (it) => (
        <Text style={{ fontSize: 12, color: "#222" }}>
          {String(it.data.name || "")}{it.data.issuer ? ` — ${String(it.data.issuer)}` : ""}
        </Text>
      ))}

      {renderGroup("Awards", "awards", (it) => (
        <Text style={{ fontSize: 12, color: "#222" }}>{String(it.data.title || "")}</Text>
      ))}

      {(items.languages?.length ?? 0) > 0 ? (
        <View style={{ marginTop: 12 }}>
          <Text style={[styles.pvSectionTitle, { color: accent, borderColor: accent }]}>LANGUAGES</Text>
          <Text style={{ fontSize: 12, color: "#222", marginTop: 4 }}>
            {(items.languages ?? [])
              .map((s) => `${String(s.data.name)}${s.data.proficiency ? ` (${String(s.data.proficiency)})` : ""}`)
              .join(" · ")}
          </Text>
        </View>
      ) : null}

      {(items.links?.length ?? 0) > 0 ? (
        <View style={{ marginTop: 12 }}>
          <Text style={[styles.pvSectionTitle, { color: accent, borderColor: accent }]}>LINKS</Text>
          {(items.links ?? []).map((l) => (
            <Text key={l.id} style={{ fontSize: 11, color: "#1d4ed8", marginTop: 2 }}>
              {String(l.data.label)} — {String(l.data.url)}
            </Text>
          ))}
        </View>
      ) : null}
    </Card>
  );
}

// ── Helpers ─────────────────────────────────────────────────────

function useDebouncedEffect(effect: () => void, deps: unknown[], delay: number) {
  useEffect(() => {
    const t = setTimeout(effect, delay);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 16, gap: 10, paddingBottom: 80 },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 14,
    letterSpacing: 0.4,
    textTransform: "uppercase",
    marginTop: 14,
    marginBottom: 6,
  },
  sectionHeadRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  subhead: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    letterSpacing: 0.5,
    textTransform: "uppercase",
  },
  statusRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 4,
    paddingTop: 4,
  },
  statusDot: { width: 8, height: 8, borderRadius: 4 },
  statusText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  linkText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  rowGap: { flexDirection: "row", gap: 8, marginTop: 4 },
  switchRow: { flexDirection: "row", alignItems: "center", paddingVertical: 6 },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
    flexDirection: "row",
    alignItems: "center",
  },
  swatch: { width: 28, height: 28, borderRadius: 14, borderWidth: 2 },
  photoRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 4,
  },
  photoThumb: {
    width: 64,
    height: 64,
    borderRadius: 32,
    borderWidth: 1,
    overflow: "hidden",
    alignItems: "center",
    justifyContent: "center",
  },
  photoImg: { width: "100%", height: "100%" },
  pvSectionTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 11,
    letterSpacing: 1.5,
    paddingBottom: 2,
    borderBottomWidth: 1.2,
  },
  hint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11,
    lineHeight: 16,
    paddingHorizontal: 4,
    textAlign: "center",
    marginTop: 14,
  },
  sheetBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "flex-end",
  },
  sheet: {
    maxHeight: "90%",
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    overflow: "hidden",
  },
  sheetHandle: {
    alignSelf: "center",
    width: 40,
    height: 4,
    borderRadius: 2,
    backgroundColor: "#9ca3af",
    marginTop: 8,
    marginBottom: 4,
  },
  sheetHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 4,
  },
  visRow: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 10,
    paddingHorizontal: 8,
    borderRadius: 8,
    borderWidth: 1.2,
    gap: 8,
  },
});
