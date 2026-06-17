import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Platform,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { DomainPicker } from "@/components/DomainPicker";
import { NfcWriteSheet } from "@/components/NfcWriteSheet";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { listAvailableDomains } from "@/lib/api/domains";
import {
  deleteLink,
  duplicateLink,
  getLink,
  resetLink,
  updateLink,
  type Link,
} from "@/lib/api/links";
import { listNfcWrites } from "@/lib/api/nfc";
import { metaForApiType } from "@/lib/linkKinds";

const VISIBILITIES: Link["visibility"][] = [
  "public",
  "registered",
  "followers",
  "subscribers",
];

function confirm(title: string, msg: string, onYes: () => void) {
  if (Platform.OS === "web") {
    if (typeof window !== "undefined" && window.confirm(`${title}\n\n${msg}`)) {
      onYes();
    }
    return;
  }
  Alert.alert(title, msg, [
    { text: "Cancel", style: "cancel" },
    { text: "OK", style: "destructive", onPress: onYes },
  ]);
}

export default function EditLinkScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useQuery({
    queryKey: ["link", id],
    queryFn: () => getLink(id),
    enabled: Number.isFinite(id),
  });

  const [nfcOpen, setNfcOpen] = useState(false);
  const nfcQ = useQuery({
    queryKey: ["nfc-writes", id],
    queryFn: () => listNfcWrites(id, 1, 1),
    enabled: Number.isFinite(id),
  });
  const nfcCount = nfcQ.data?.meta.total ?? 0;

  const [title, setTitle] = useState("");
  const [alias, setAlias] = useState("");
  const [longUrl, setLongUrl] = useState("");
  const [seoTitle, setSeoTitle] = useState("");
  const [seoDesc, setSeoDesc] = useState("");
  const [visibility, setVisibility] = useState<Link["visibility"]>("public");
  const [active, setActive] = useState(true);
  const [domainId, setDomainId] = useState<number | null>(null);

  const domainsQ = useQuery({
    queryKey: ["domains-available"],
    queryFn: listAvailableDomains,
  });
  // Per-biolink privacy controls (task #1114). Defaults are
  // privacy-respecting so a brand-new biolink is GDPR-safe; existing
  // pages keep whatever the creator already saved.
  const DEFAULT_BANNER_TEXT =
    "This page uses essential cookies to work. With your consent we also load analytics and marketing pixels.";
  const DEFAULT_ACCEPT_LABEL = "Accept";
  const DEFAULT_DECLINE_LABEL = "Decline";
  const [privacyHide, setPrivacyHide] = useState(true);
  const [privacyNoRef, setPrivacyNoRef] = useState(true);
  const [privacyBanner, setPrivacyBanner] = useState(false);
  const [privacyText, setPrivacyText] = useState(DEFAULT_BANNER_TEXT);
  const [privacyAccept, setPrivacyAccept] = useState(DEFAULT_ACCEPT_LABEL);
  const [privacyDecline, setPrivacyDecline] = useState(DEFAULT_DECLINE_LABEL);

  useEffect(() => {
    const l = q.data;
    if (!l) return;
    setTitle(l.title ?? "");
    setAlias(l.alias);
    setLongUrl(l.long_url ?? "");
    setSeoTitle(l.seo_title ?? "");
    setSeoDesc(l.seo_description ?? "");
    setVisibility(l.visibility);
    setActive(l.is_active);
    setDomainId(l.domain_id ?? null);
    const privacy = readPrivacy(l.settings ?? null);
    setPrivacyHide(privacy.hide_public_visitor_counts ?? true);
    setPrivacyNoRef(privacy.disable_referrer_logging ?? true);
    setPrivacyBanner(privacy.consent_banner_enabled ?? false);
    setPrivacyText(privacy.consent_banner_text ?? DEFAULT_BANNER_TEXT);
    setPrivacyAccept(privacy.consent_accept_label ?? DEFAULT_ACCEPT_LABEL);
    setPrivacyDecline(privacy.consent_decline_label ?? DEFAULT_DECLINE_LABEL);
  }, [q.data]);

  const save = useMutation({
    mutationFn: () => {
      // Deep-merge into the existing settings so we don't clobber
      // appearance/blocks data the editor isn't aware of. The web
      // updatePageSettings handler stores the same shape.
      // Privacy controls only apply to biolink-type links. For
      // short/vcard/other types we don't touch settings at all so the
      // backend's deep-merge doesn't silently introduce a privacy block.
      const isBiolink =
        (q.data?.type ?? "").toString() === "biolink" ||
        meta.kind === "biolink";
      const payload: Parameters<typeof updateLink>[1] = {
        title: title || null,
        alias,
        long_url: longUrl || null,
        seo_title: seoTitle || null,
        seo_description: seoDesc || null,
        visibility,
        is_active: active,
        domain_id: domainId,
      };
      if (isBiolink) {
        const existing: SettingsRecord = (q.data?.settings ??
          {}) as SettingsRecord;
        const existingBiolink: SettingsRecord = isRecord(existing.biolink)
          ? existing.biolink
          : {};
        const existingPrivacy: SettingsRecord = isRecord(existingBiolink.privacy)
          ? existingBiolink.privacy
          : {};
        payload.settings = {
          ...existing,
          biolink: {
            ...existingBiolink,
            privacy: {
              ...existingPrivacy,
              hide_public_visitor_counts: privacyHide,
              disable_referrer_logging: privacyNoRef,
              consent_banner_enabled: privacyBanner,
              consent_banner_text: privacyText.trim() || DEFAULT_BANNER_TEXT,
              consent_accept_label:
                privacyAccept.trim() || DEFAULT_ACCEPT_LABEL,
              consent_decline_label:
                privacyDecline.trim() || DEFAULT_DECLINE_LABEL,
            },
          },
        };
      }
      return updateLink(id, payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["link", id] });
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });

  const dup = useMutation({
    mutationFn: () => duplicateLink(q.data!),
    onSuccess: (link) => {
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["dashboard"] });
      router.replace(`/links/${link.id}/edit` as any);
    },
  });

  const reset = useMutation({
    mutationFn: () => resetLink(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["link", id] });
      qc.invalidateQueries({ queryKey: ["analytics", id] });
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });

  const del = useMutation({
    mutationFn: () => deleteLink(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["dashboard"] });
      router.replace("/(tabs)/links");
    },
  });

  if (!Number.isFinite(id)) return null;
  if (q.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (q.error || !q.data) {
    return (
      <View style={styles.center}>
        <Text style={{ color: colors.destructive }}>Couldn't load link.</Text>
      </View>
    );
  }

  const l = q.data;
  const meta = metaForApiType(l.type);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          headerShown: true,
          title: meta.label,
        }}
      />
      <ScrollView contentContainerStyle={styles.body}>
        <View
          style={[
            styles.banner,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <View
            style={[
              styles.iconWrap,
              { backgroundColor: colors.primary + "1c" },
            ]}
          >
            <Feather name={meta.icon} size={20} color={colors.primary} />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={[styles.shortUrl, { color: colors.foreground }]}>
              {l.short_url}
            </Text>
            <Text style={[styles.muted, { color: colors.mutedForeground }]}>
              {l.total_clicks} clicks · {l.unique_clicks} unique
            </Text>
          </View>
          <Pressable
            onPress={() => Share.share({ message: l.short_url })}
            hitSlop={8}
          >
            <Feather name="share-2" size={20} color={colors.primary} />
          </Pressable>
        </View>

        <View style={styles.actionsRow}>
          <ActionTile
            icon="bar-chart-2"
            label="Analytics"
            onPress={() => router.push(`/links/${id}/analytics` as any)}
          />
          {meta.kind === "biolink" ? (
            <ActionTile
              icon="grid"
              label="Blocks"
              onPress={() => router.push(`/links/${id}/blocks` as any)}
            />
          ) : null}
          {meta.kind === "ai_chat" ? (
            <ActionTile
              icon="message-circle"
              label="AI Chat"
              onPress={() => router.push(`/links/${id}/ai-chat` as any)}
            />
          ) : null}
          {meta.kind === "restaurant_menu" ? (
            <ActionTile
              icon="coffee"
              label="Orders"
              onPress={() =>
                router.push(`/links/${id}/restaurant-orders` as any)
              }
            />
          ) : null}
          {meta.kind === "resume" ? (
            <ActionTile
              icon="edit-3"
              label="Edit resume"
              onPress={() => router.push("/resume" as any)}
            />
          ) : null}
          {meta.kind === "resume" ? (
            <ActionTile
              icon="file-text"
              label="View resume"
              onPress={() => {
                if (l.short_url)
                  WebBrowser.openBrowserAsync(l.short_url).catch(() => {});
              }}
            />
          ) : null}
          {l.type === "reviews" ? (
            <ActionTile
              icon="star"
              label="Reviews"
              onPress={() =>
                router.push(`/reviews/${encodeURIComponent(l.alias)}` as any)
              }
            />
          ) : null}
          {l.type === "reviews" ? (
            <ActionTile
              icon="check-circle"
              label="Moderate"
              onPress={() => router.push(`/reviews/manage` as any)}
            />
          ) : null}
          <ActionTile
            icon="copy"
            label="Duplicate"
            onPress={() => dup.mutate()}
          />
          <ActionTile
            icon="rotate-ccw"
            label="Reset"
            onPress={() =>
              confirm(
                "Reset link?",
                "This clears the click counters and stored analytics for this link.",
                () => reset.mutate(),
              )
            }
          />
          <ActionTile
            icon="wifi"
            label={nfcCount > 0 ? `NFC · ${nfcCount}` : "Write NFC"}
            onPress={() => setNfcOpen(true)}
          />
        </View>
        <NfcWriteSheet
          visible={nfcOpen}
          onClose={() => setNfcOpen(false)}
          linkId={id}
          url={l.short_url}
          onWritten={() => {
            qc.invalidateQueries({ queryKey: ["nfc-writes", id] });
          }}
        />

        {meta.kind === "biolink" ? (
          <View style={styles.actionsRow}>
            <ActionTile
              icon="sliders"
              label="Appearance"
              onPress={() =>
                router.push(`/links/${id}/settings/appearance` as any)
              }
            />
            <ActionTile
              icon="layout"
              label="Layout"
              onPress={() => router.push(`/links/${id}/settings/layout` as any)}
            />
            <ActionTile
              icon="droplet"
              label="Block theme"
              onPress={() =>
                router.push(`/links/${id}/settings/block-theme` as any)
              }
            />
            <ActionTile
              icon="calendar"
              label="Themes"
              onPress={() =>
                router.push(`/links/${id}/settings/themes` as any)
              }
            />
            <ActionTile
              icon="settings"
              label="Advanced"
              onPress={() =>
                router.push(`/links/${id}/settings/advanced` as any)
              }
            />
          </View>
        ) : null}

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Basics
          </Text>
          <TextField label="Title" value={title} onChangeText={setTitle} />
          <TextField
            label="Alias"
            value={alias}
            onChangeText={setAlias}
            autoCapitalize="none"
            autoCorrect={false}
          />
          {meta.kind !== "biolink" && meta.kind !== "vcard" ? (
            <TextField
              label="Destination URL"
              value={longUrl}
              onChangeText={setLongUrl}
              keyboardType="url"
              autoCapitalize="none"
            />
          ) : null}
          <DomainPicker
            value={domainId}
            onChange={setDomainId}
            data={domainsQ.data}
            loading={domainsQ.isLoading}
          />
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Visibility
          </Text>
          <View
            style={[
              styles.segment,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {VISIBILITIES.map((v) => {
              const on = visibility === v;
              return (
                <Pressable
                  key={v}
                  onPress={() => setVisibility(v)}
                  style={[
                    styles.segmentItem,
                    {
                      backgroundColor: on ? colors.background : "transparent",
                      borderRadius: colors.radius - 4,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.segmentText,
                      { color: on ? colors.primary : colors.mutedForeground },
                    ]}
                  >
                    {v}
                  </Text>
                </Pressable>
              );
            })}
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
              Active
            </Text>
            <Switch
              value={active}
              onValueChange={setActive}
              trackColor={{ true: colors.primary, false: colors.border }}
            />
          </View>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            SEO
          </Text>
          <TextField
            label="SEO title"
            value={seoTitle}
            onChangeText={setSeoTitle}
          />
          <TextField
            label="SEO description"
            value={seoDesc}
            onChangeText={setSeoDesc}
            multiline
            numberOfLines={3}
            style={{ height: 88, textAlignVertical: "top", paddingTop: 12 }}
          />
        </View>

        {meta.kind === "biolink" ? (
          <View style={styles.section}>
            <Text
              style={[styles.sectionLabel, { color: colors.mutedForeground }]}
            >
              Privacy
            </Text>
            <PrivacyRow
              label="Hide public visitor counts"
              hint="Don't show live visitor counters publicly. Your analytics still work."
              value={privacyHide}
              onValueChange={setPrivacyHide}
            />
            <PrivacyRow
              label="Don't log referrer URLs"
              hint="Skip storing the page each visitor came from."
              value={privacyNoRef}
              onValueChange={setPrivacyNoRef}
            />
            <PrivacyRow
              label="Show visitor consent banner"
              hint="Ask visitors before loading non-essential cookies and pixels."
              value={privacyBanner}
              onValueChange={setPrivacyBanner}
            />
            {privacyBanner ? (
              <View style={{ gap: 10 }}>
                <TextField
                  label="Banner copy"
                  value={privacyText}
                  onChangeText={setPrivacyText}
                  multiline
                  maxLength={500}
                  placeholder={DEFAULT_BANNER_TEXT}
                  style={{ height: 88, textAlignVertical: "top", paddingTop: 12 }}
                />
                <TextField
                  label="Accept button"
                  value={privacyAccept}
                  onChangeText={setPrivacyAccept}
                  maxLength={40}
                  placeholder={DEFAULT_ACCEPT_LABEL}
                />
                <TextField
                  label="Decline button"
                  value={privacyDecline}
                  onChangeText={setPrivacyDecline}
                  maxLength={40}
                  placeholder={DEFAULT_DECLINE_LABEL}
                />
              </View>
            ) : null}
          </View>
        ) : null}

        <Button
          label="Save changes"
          onPress={() => save.mutate()}
          loading={save.isPending}
        />

        <Pressable
          onPress={() =>
            confirm("Delete link?", `Delete /${l.alias} permanently?`, () =>
              del.mutate(),
            )
          }
          style={({ pressed }) => [
            styles.deleteRow,
            { opacity: pressed ? 0.7 : 1 },
          ]}
        >
          <Feather name="trash-2" size={16} color={colors.destructive} />
          <Text style={[styles.deleteText, { color: colors.destructive }]}>
            Delete this link
          </Text>
        </Pressable>
      </ScrollView>
    </View>
  );
}

type SettingsRecord = Record<string, unknown>;

type PrivacySettings = {
  hide_public_visitor_counts?: boolean;
  disable_referrer_logging?: boolean;
  consent_banner_enabled?: boolean;
  consent_banner_text?: string;
  consent_accept_label?: string;
  consent_decline_label?: string;
};

function isRecord(value: unknown): value is SettingsRecord {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function readPrivacy(settings: unknown): PrivacySettings {
  if (!isRecord(settings)) return {};
  const biolink = settings.biolink;
  if (!isRecord(biolink)) return {};
  const privacy = biolink.privacy;
  if (!isRecord(privacy)) return {};
  const out: PrivacySettings = {};
  if (typeof privacy.hide_public_visitor_counts === "boolean")
    out.hide_public_visitor_counts = privacy.hide_public_visitor_counts;
  if (typeof privacy.disable_referrer_logging === "boolean")
    out.disable_referrer_logging = privacy.disable_referrer_logging;
  if (typeof privacy.consent_banner_enabled === "boolean")
    out.consent_banner_enabled = privacy.consent_banner_enabled;
  if (typeof privacy.consent_banner_text === "string")
    out.consent_banner_text = privacy.consent_banner_text;
  if (typeof privacy.consent_accept_label === "string")
    out.consent_accept_label = privacy.consent_accept_label;
  if (typeof privacy.consent_decline_label === "string")
    out.consent_decline_label = privacy.consent_decline_label;
  return out;
}

function PrivacyRow({
  label,
  hint,
  value,
  onValueChange,
}: {
  label: string;
  hint: string;
  value: boolean;
  onValueChange: (v: boolean) => void;
}) {
  const colors = useColors();
  return (
    <View
      style={[
        styles.privacyRow,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <View style={{ flex: 1, paddingRight: 12 }}>
        <Text style={[styles.rowLabel, { color: colors.foreground }]}>
          {label}
        </Text>
        <Text
          style={{
            fontFamily: "SpaceGrotesk_500Medium",
            fontSize: 11,
            marginTop: 4,
            color: colors.mutedForeground,
          }}
        >
          {hint}
        </Text>
      </View>
      <Switch
        value={value}
        onValueChange={onValueChange}
        trackColor={{ true: colors.primary, false: colors.border }}
      />
    </View>
  );
}

function ActionTile({
  icon,
  label,
  onPress,
}: {
  icon: keyof typeof Feather.glyphMap;
  label: string;
  onPress: () => void;
}) {
  const colors = useColors();
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.actionTile,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : 1,
        },
      ]}
    >
      <Feather name={icon} size={18} color={colors.primary} />
      <Text style={[styles.actionLabel, { color: colors.foreground }]}>
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 16, paddingBottom: 40 },
  banner: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  shortUrl: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  muted: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  actionsRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
  },
  actionTile: {
    flexBasis: "47%",
    flexGrow: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 14,
    paddingHorizontal: 14,
    borderWidth: 1,
  },
  actionLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  section: { gap: 10 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  segment: { flexDirection: "row", padding: 4, borderWidth: 1 },
  segmentItem: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 10,
  },
  segmentText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    textTransform: "capitalize",
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    padding: 14,
    borderWidth: 1,
  },
  rowLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  privacyRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    padding: 14,
    borderWidth: 1,
  },
  deleteRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 14,
    marginTop: 8,
  },
  deleteText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
});
