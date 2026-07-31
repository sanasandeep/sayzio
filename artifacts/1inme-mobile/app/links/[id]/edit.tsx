import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Stack,
  useFocusEffect,
  useLocalSearchParams,
  useRouter,
} from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
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
import { DictationMic } from "@/components/DictationMic";
import { DomainPicker } from "@/components/DomainPicker";
import { NfcWriteSheet } from "@/components/NfcWriteSheet";
import { TextField } from "@/components/TextField";
import { setVoiceSurface } from "@/components/VoiceAssistant";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import { listAvailableDomains } from "@/lib/api/domains";
import {
  checkAlias,
  deleteLink,
  duplicateLink,
  getLink,
  resetLink,
  updateLink,
  type AliasCheck,
  type Link,
} from "@/lib/api/links";
import { listNfcWrites } from "@/lib/api/nfc";
import {
  getTransferCapability,
  transferLink,
} from "@/lib/api/transfers";
import { metaForApiType } from "@/lib/linkKinds";
import { PaidPageTemplatePreview } from "@/components/PaidPageTemplatePreview";
import {
  getPaidPageTemplate,
  PAID_PAGE_TEMPLATES,
  paidPageTemplateId,
} from "@/lib/paidPage";
import { showAlert } from "@/lib/webAlert";

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
  showAlert(title, msg, [
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
  // Live "Custom URL availability" check — same debounced indicator the
  // create flow shows. The link's own id is passed to checkAlias so its
  // current (unchanged) alias reads as available, not taken.
  const [aliasCheck, setAliasCheck] = useState<AliasCheck | null>(null);
  const [aliasChecking, setAliasChecking] = useState(false);
  const [longUrl, setLongUrl] = useState("");
  const [seoTitle, setSeoTitle] = useState("");
  const [seoDesc, setSeoDesc] = useState("");
  const [visibility, setVisibility] = useState<Link["visibility"]>("public");
  const [active, setActive] = useState(true);
  const [domainId, setDomainId] = useState<number | null>(null);
  // Paid Page (paid_page) template + public/gated toggle. Mirrors the
  // web editor: visibility "public" => anyone, "registered" => gated.
  const [paidTemplate, setPaidTemplate] = useState<string>("aurora");

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
    setPaidTemplate(paidPageTemplateId(readPaidPageTemplate(l.settings ?? null)));
    const privacy = readPrivacy(l.settings ?? null);
    setPrivacyHide(privacy.hide_public_visitor_counts ?? true);
    setPrivacyNoRef(privacy.disable_referrer_logging ?? true);
    setPrivacyBanner(privacy.consent_banner_enabled ?? false);
    setPrivacyText(privacy.consent_banner_text ?? DEFAULT_BANNER_TEXT);
    setPrivacyAccept(privacy.consent_accept_label ?? DEFAULT_ACCEPT_LABEL);
    setPrivacyDecline(privacy.consent_decline_label ?? DEFAULT_DECLINE_LABEL);
  }, [q.data]);

  useEffect(() => {
    const trimmed = alias.trim();
    if (trimmed === "") {
      setAliasCheck(null);
      setAliasChecking(false);
      return;
    }
    setAliasChecking(true);
    let cancelled = false;
    const t = setTimeout(async () => {
      try {
        const res = await checkAlias(trimmed, id, domainId);
        if (!cancelled) setAliasCheck(res);
      } catch {
        // Network/transient errors leave the last known state; the save
        // still enforces the rules server-side regardless.
        if (!cancelled) setAliasCheck(null);
      } finally {
        if (!cancelled) setAliasChecking(false);
      }
    }, 450);
    return () => {
      cancelled = true;
      clearTimeout(t);
    };
  }, [alias, id, domainId]);

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
      const isPaidPage =
        (q.data?.type ?? "").toString() === "paid_page" ||
        meta.kind === "paid_page";
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
      if (isPaidPage) {
        // Deep-merge so other paid_page settings (posts/tiers metadata
        // the mobile editor doesn't know about) are preserved.
        const existing: SettingsRecord = (q.data?.settings ??
          {}) as SettingsRecord;
        const existingPaid: SettingsRecord = isRecord(existing.paid_page)
          ? existing.paid_page
          : {};
        payload.settings = {
          ...existing,
          paid_page: {
            ...existingPaid,
            template: paidPageTemplateId(paidTemplate),
          },
        };
      }
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

  // Admin-granted asset transfer: the capability probe drives visibility,
  // the server re-checks the grant + ownership on submit.
  const transferCap = useQuery({
    queryKey: ["transfer-capability"],
    queryFn: getTransferCapability,
    staleTime: 5 * 60 * 1000,
  });
  const [transferOpen, setTransferOpen] = useState(false);
  const [transferEmail, setTransferEmail] = useState("");
  const [transferError, setTransferError] = useState<string | null>(null);
  const transfer = useMutation({
    mutationFn: () => transferLink(id, transferEmail.trim()),
    onSuccess: (t) => {
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["dashboard"] });
      showAlert(
        "Link transferred",
        `Ownership moved to ${t.to_email ?? transferEmail.trim()}.`,
        [{ text: "OK", onPress: () => router.replace("/(tabs)/links") }],
      );
    },
    onError: (e) => {
      setTransferError(
        e && typeof e === "object" && typeof (e as { message?: unknown }).message === "string"
          ? (e as { message: string }).message
          : "Transfer failed. Please try again.",
      );
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

  // Voice turns started while this editor is open prefer the general
  // in-app tools; dictation works via the per-field mics regardless.
  useFocusEffect(
    useCallback(() => {
      setVoiceSurface("app");
      return () => setVoiceSurface(null);
    }, []),
  );

  // Append a dictated chunk to whichever field's setter is passed in,
  // mirroring the create form's per-field dictation factory.
  const dictateInto =
    (setter: React.Dispatch<React.SetStateAction<string>>) => (t: string) =>
      setter((v) => (v ? v.trim() + " " : "") + t);

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

  // Dedicated editors for slides / conversational / restaurant-menu pages
  // live on the web; open them in the authenticated in-app browser so the
  // freshly-created link is fully usable from mobile.
  const openWebEditor = (path: string) => {
    const url = `${getBaseUrl()}${path}`;
    if (Platform.OS === "web") {
      window.location.href = url;
      return;
    }
    WebBrowser.openBrowserAsync(url, {
      toolbarColor: colors.background,
      controlsColor: colors.primary,
    }).catch(() => {});
  };

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
          <ActionTile
            icon="users"
            label="Visitor Insights"
            onPress={() => router.push(`/links/${id}/visitors` as any)}
          />
          {meta.kind === "biolink" ? (
            <ActionTile
              icon="grid"
              label="Blocks"
              onPress={() => router.push(`/links/${id}/blocks` as any)}
            />
          ) : null}
          {meta.kind === "biolink" ? (
            <ActionTile
              icon="map"
              label="Roadmap"
              onPress={() => router.push(`/links/${id}/roadmap` as any)}
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
          {meta.kind === "restaurant_menu" ? (
            <ActionTile
              icon="edit-3"
              label="Edit menu"
              onPress={() => router.push(`/links/${id}/restaurant-menu` as any)}
            />
          ) : null}
          {meta.kind === "store_menu" ? (
            <ActionTile
              icon="shopping-bag"
              label="Requests"
              onPress={() => router.push(`/links/${id}/store-orders` as any)}
            />
          ) : null}
          {meta.kind === "store_menu" ? (
            <ActionTile
              icon="edit-3"
              label="Edit store"
              onPress={() => router.push(`/links/${id}/store-menu` as any)}
            />
          ) : null}
          {meta.kind === "service_booking" ? (
            <ActionTile
              icon="calendar"
              label="Bookings"
              onPress={() =>
                router.push(`/links/${id}/service-booking-dashboard` as any)
              }
            />
          ) : null}
          {meta.kind === "service_booking" ? (
            <ActionTile
              icon="edit-3"
              label="Edit services"
              onPress={() =>
                router.push(`/links/${id}/service-booking-builder` as any)
              }
            />
          ) : null}
          {meta.kind === "calendar" ? (
            <ActionTile
              icon="credit-card"
              label="Ticketing"
              onPress={() => router.push(`/events/tiers/${id}` as any)}
            />
          ) : null}
          {meta.kind === "slides" ? (
            <ActionTile
              icon="edit-3"
              label="Edit slides"
              onPress={() => openWebEditor(`/user/links/${id}/slides`)}
            />
          ) : null}
          {meta.kind === "conversational" ? (
            <ActionTile
              icon="edit-3"
              label="Edit flow"
              onPress={() => router.push(`/links/${id}/conversational` as any)}
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
          {l.type === "paid_page" ? (
            <ActionTile
              icon="layout"
              label="View page"
              onPress={() =>
                router.push(`/paid-page/${encodeURIComponent(l.alias)}` as any)
              }
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
          <ActionTile
            icon="shield"
            label="Insurance"
            onPress={() =>
              router.push(`/links/${id}/settings/insurance` as any)
            }
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
              icon="smile"
              label="Stickers"
              onPress={() =>
                router.push(`/links/${id}/settings/stickers` as any)
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
          <TextField
            label="Title"
            value={title}
            onChangeText={setTitle}
            trailing={<DictationMic onText={dictateInto(setTitle)} />}
          />
          <TextField
            label="Alias"
            value={alias}
            onChangeText={setAlias}
            autoCapitalize="none"
            autoCorrect={false}
            trailing={<DictationMic onText={dictateInto(setAlias)} />}
          />
          {alias.trim() !== "" ? (
            <Text
              style={[
                styles.aliasStatus,
                {
                  color: aliasChecking
                    ? colors.mutedForeground
                    : aliasCheck?.available
                      ? colors.success
                      : aliasCheck
                        ? colors.destructive
                        : colors.mutedForeground,
                },
              ]}
            >
              {aliasChecking
                ? "Checking availability…"
                : aliasCheck
                  ? `${aliasCheck.available ? "✓" : "✕"} ${aliasCheck.message}`
                  : ""}
            </Text>
          ) : null}
          {meta.kind !== "biolink" &&
          meta.kind !== "vcard" &&
          meta.kind !== "slides" &&
          meta.kind !== "conversational" &&
          meta.kind !== "restaurant_menu" &&
          meta.kind !== "store_menu" &&
          meta.kind !== "service_booking" &&
          meta.kind !== "reviews" ? (
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

        {meta.kind === "paid_page" ? (
          <View style={styles.section}>
            <Text
              style={[styles.sectionLabel, { color: colors.mutedForeground }]}
            >
              Template
            </Text>
            <View
              style={[
                styles.tplPreviewFrame,
                {
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <PaidPageTemplatePreview templateId={paidTemplate} />
            </View>
            <Text style={[styles.tplTagline, { color: colors.mutedForeground }]}>
              {getPaidPageTemplate(paidTemplate).tagline}
            </Text>
            <View style={styles.tplGrid}>
              {PAID_PAGE_TEMPLATES.map((t) => {
                const on = paidTemplate === t.id;
                return (
                  <Pressable
                    key={t.id}
                    onPress={() => setPaidTemplate(t.id)}
                    style={[
                      styles.tplCard,
                      {
                        borderColor: on ? colors.primary : colors.border,
                        borderWidth: on ? 2 : 1,
                      },
                    ]}
                  >
                    <View
                      style={[styles.tplSwatch, { backgroundColor: t.swatch }]}
                    />
                    <Text
                      style={[styles.tplName, { color: colors.foreground }]}
                    >
                      {t.name}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </View>
        ) : null}

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Visibility
          </Text>
          {meta.kind === "paid_page" ? (
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
              <View style={{ flex: 1, paddingRight: 12 }}>
                <Text style={[styles.rowLabel, { color: colors.foreground }]}>
                  Public page
                </Text>
                <Text
                  style={{
                    fontFamily: "SpaceGrotesk_500Medium",
                    fontSize: 11,
                    marginTop: 4,
                    color: colors.mutedForeground,
                  }}
                >
                  {visibility === "public"
                    ? "Anyone can view this page."
                    : "Viewers must be signed in to view this page."}
                </Text>
              </View>
              <Switch
                value={visibility === "public"}
                onValueChange={(on) =>
                  setVisibility(on ? "public" : "registered")
                }
                trackColor={{ true: colors.primary, false: colors.border }}
              />
            </View>
          ) : (
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
          )}
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
            trailing={<DictationMic onText={dictateInto(setSeoTitle)} />}
          />
          <TextField
            label="SEO description"
            value={seoDesc}
            onChangeText={setSeoDesc}
            multiline
            numberOfLines={3}
            style={{ height: 88, textAlignVertical: "top", paddingTop: 12 }}
            trailing={<DictationMic onText={dictateInto(setSeoDesc)} />}
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
                  trailing={<DictationMic onText={dictateInto(setPrivacyText)} />}
                />
                <TextField
                  label="Accept button"
                  value={privacyAccept}
                  onChangeText={setPrivacyAccept}
                  maxLength={40}
                  placeholder={DEFAULT_ACCEPT_LABEL}
                  trailing={
                    <DictationMic onText={dictateInto(setPrivacyAccept)} />
                  }
                />
                <TextField
                  label="Decline button"
                  value={privacyDecline}
                  onChangeText={setPrivacyDecline}
                  maxLength={40}
                  placeholder={DEFAULT_DECLINE_LABEL}
                  trailing={
                    <DictationMic onText={dictateInto(setPrivacyDecline)} />
                  }
                />
              </View>
            ) : null}
          </View>
        ) : null}

        <Button
          label="Save changes"
          variant="cta"
          onPress={() => save.mutate()}
          loading={save.isPending}
        />

        {transferCap.data?.can_transfer ? (
          <View style={{ gap: 10 }}>
            <Pressable
              onPress={() => {
                setTransferError(null);
                setTransferOpen((v) => !v);
              }}
              style={({ pressed }) => [
                styles.deleteRow,
                { opacity: pressed ? 0.7 : 1 },
              ]}
              accessibilityRole="button"
              accessibilityLabel="Transfer this link"
            >
              <Feather name="send" size={16} color={colors.primary} />
              <Text style={[styles.deleteText, { color: colors.primary }]}>
                Transfer to another user
              </Text>
            </Pressable>
            {transferOpen ? (
              <View style={{ gap: 10 }}>
                <TextField
                  label="Recipient email"
                  value={transferEmail}
                  onChangeText={(t) => {
                    setTransferEmail(t);
                    setTransferError(null);
                  }}
                  placeholder="recipient@example.com"
                  autoCapitalize="none"
                  keyboardType="email-address"
                />
                {transferError ? (
                  <Text style={{ color: colors.destructive, fontSize: 13 }}>
                    {transferError}
                  </Text>
                ) : null}
                <Button
                  label="Transfer ownership"
                  onPress={() => {
                    const email = transferEmail.trim();
                    if (!email) {
                      setTransferError("Enter the recipient's email.");
                      return;
                    }
                    confirm(
                      "Transfer link?",
                      `Move /${l.alias} and its data to ${email}? This is instant and cannot be undone.`,
                      () => transfer.mutate(),
                    );
                  }}
                  loading={transfer.isPending}
                />
              </View>
            ) : null}
          </View>
        ) : null}

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

function readPaidPageTemplate(settings: unknown): unknown {
  if (!isRecord(settings)) return undefined;
  const paid = settings.paid_page;
  if (!isRecord(paid)) return undefined;
  return paid.template;
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
  aliasStatus: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    marginTop: -8,
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
  tplPreviewFrame: {
    borderWidth: 1,
    overflow: "hidden",
  },
  tplTagline: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
  },
  tplGrid: { flexDirection: "row", flexWrap: "wrap", gap: 10 },
  tplCard: {
    width: "30%",
    borderRadius: 14,
    padding: 8,
    alignItems: "center",
    gap: 6,
  },
  tplSwatch: { width: "100%", height: 36, borderRadius: 8 },
  tplName: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11 },
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
