import { Feather } from "@expo/vector-icons";
import * as Clipboard from "expo-clipboard";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  KeyboardAvoidingView,
  Linking,
  Platform,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { MapPickerModal, type PickedPoint } from "@/components/MapPickerModal";
import { MapMarkersPreview, MapPreview } from "@/components/MapPreview";
import { useColors } from "@/hooks/useColors";
import {
  type ManualChannel,
  type ManualLocation,
  type ManualSocial,
  updateContactManualProfile,
} from "@/lib/api/contacts";
import {
  type DialerActivity,
  type DialerChannel,
  type DialerLookupResult,
  type DialerProfile,
  addFavorite,
  clearCallback,
  dialerProfile,
  flagNumber,
  logCall,
  lookupNumber,
  setCallback,
} from "@/lib/api/dialer";

const E164 = /^\+[1-9]\d{6,14}$/;

const OUTCOMES: { v: string; label: string }[] = [
  { v: "called", label: "Called" },
  { v: "messaged", label: "Messaged" },
  { v: "no_answer", label: "No answer" },
  { v: "voicemail", label: "Voicemail" },
  { v: "wrong_number", label: "Wrong number" },
  { v: "completed", label: "Completed" },
];

const CALLBACK_PRESETS: { label: string; ms: number }[] = [
  { label: "In 1 hour", ms: 60 * 60 * 1000 },
  { label: "In 3 hours", ms: 3 * 60 * 60 * 1000 },
  { label: "Tomorrow", ms: 24 * 60 * 60 * 1000 },
  { label: "In 3 days", ms: 3 * 24 * 60 * 60 * 1000 },
];

const CHANNEL_TYPES: { value: string; label: string }[] = [
  { value: "phone", label: "Phone" },
  { value: "sms", label: "SMS" },
  { value: "whatsapp", label: "WhatsApp" },
  { value: "telegram", label: "Telegram" },
  { value: "facetime_audio", label: "FaceTime Audio" },
  { value: "facetime_video", label: "FaceTime" },
  { value: "email", label: "Email" },
  { value: "custom", label: "Custom link" },
];

function iconForChannel(type: string): keyof typeof Feather.glyphMap {
  switch (type) {
    case "phone":
      return "phone";
    case "sms":
      return "message-circle";
    case "whatsapp":
    case "whatsapp_channel":
      return "message-square";
    case "telegram":
      return "send";
    case "facetime_audio":
    case "facetime_video":
      return "video";
    case "email":
      return "mail";
    default:
      return "external-link";
  }
}

function relative(at: string | null): string {
  if (!at) return "";
  const ms = new Date(at).getTime();
  if (Number.isNaN(ms)) return "";
  const diff = Date.now() - ms;
  const abs = Math.abs(diff);
  const fut = diff < 0;
  const mins = Math.round(abs / 60000);
  if (mins < 1) return "just now";
  if (mins < 60) return `${fut ? "in " : ""}${mins}m${fut ? "" : " ago"}`;
  const hrs = Math.round(mins / 60);
  if (hrs < 24) return `${fut ? "in " : ""}${hrs}h${fut ? "" : " ago"}`;
  const days = Math.round(hrs / 24);
  return `${fut ? "in " : ""}${days}d${fut ? "" : " ago"}`;
}

export default function DialerProfileScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const params = useLocalSearchParams<{
    number?: string;
    contactId?: string;
    name?: string;
  }>();

  const number = typeof params.number === "string" ? params.number.trim() : "";
  const contactId = params.contactId ? Number(params.contactId) : null;
  const presetName = typeof params.name === "string" ? params.name : null;

  const [loading, setLoading] = useState(true);
  const [lookup, setLookup] = useState<DialerLookupResult | null>(null);
  const [profile, setProfile] = useState<DialerProfile | null>(null);
  const [busy, setBusy] = useState(false);

  // Call-log draft state.
  const [outcome, setOutcome] = useState<string | null>(null);
  const [note, setNote] = useState("");
  const [tag, setTag] = useState("");

  // Manual-additions editor draft state (only when a saved contact is attached).
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [draftChannels, setDraftChannels] = useState<ManualChannel[]>([]);
  const [draftSocials, setDraftSocials] = useState<ManualSocial[]>([]);
  const [draftLocation, setDraftLocation] = useState<ManualLocation>({
    label: "",
    address: "",
    lat: null,
    lng: null,
  });
  const [mapPickerOpen, setMapPickerOpen] = useState(false);

  const e164 = useMemo(() => (E164.test(number) ? number : null), [number]);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const tasks: Promise<void>[] = [];
      if (e164) {
        tasks.push(
          lookupNumber(e164)
            .then((res) => setLookup(res))
            .catch(() => {}),
        );
      }
      if (number || contactId) {
        tasks.push(
          dialerProfile({
            number: number || undefined,
            contact: contactId ?? undefined,
          })
            .then((p) => {
              setProfile(p);
              setDraftChannels(p.manual.channels.map((c) => ({ ...c })));
              setDraftSocials(p.manual.socials.map((s) => ({ ...s })));
              setDraftLocation(
                p.manual.location
                  ? {
                      label: p.manual.location.label,
                      address: p.manual.location.address,
                      lat: p.manual.location.lat,
                      lng: p.manual.location.lng,
                    }
                  : { label: "", address: "", lat: null, lng: null },
              );
            })
            .catch(() => {}),
        );
      }
      await Promise.all(tasks);
    } finally {
      setLoading(false);
    }
  }, [e164, number, contactId]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const displayName =
    profile?.contact?.display_name ||
    lookup?.contact?.display_name ||
    lookup?.biolink?.name ||
    profile?.biolink?.name ||
    presetName ||
    number ||
    "Unknown number";

  const initials = useMemo(() => {
    const src = displayName.trim();
    if (!src || src === number) {
      const digits = number.replace(/\D+/g, "");
      return digits ? digits.slice(-2) : "#";
    }
    return src
      .split(/\s+/)
      .slice(0, 2)
      .map((s) => s[0])
      .join("")
      .toUpperCase();
  }, [displayName, number]);

  const avatarUri =
    profile?.contact?.photo_url || profile?.biolink?.avatar_url || undefined;

  // ---- Biolink (live lookup wins, fall back to identity payload) ----
  const bioUrl = lookup?.biolink?.url ?? profile?.biolink?.url ?? null;
  const bioName = lookup?.biolink?.name ?? profile?.biolink?.name ?? "";
  const bioHandle = lookup?.biolink?.handle ?? profile?.biolink?.handle ?? null;

  // ---- Reach-via channels (auto + manual), filtered to openable ones ----
  const allChannels = useMemo<DialerChannel[]>(() => {
    if (!profile) return [];
    return [...profile.channels, ...profile.manual.channels];
  }, [profile]);

  const [openableUrls, setOpenableUrls] = useState<Set<string>>(new Set());

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const ok = new Set<string>();
      await Promise.all(
        allChannels.map(async (c) => {
          const probe = c.scheme_url ?? c.url;
          if (/^(tel:|sms:|mailto:|https?:)/.test(probe)) {
            ok.add(c.url);
            return;
          }
          try {
            if (await Linking.canOpenURL(probe)) ok.add(c.url);
          } catch {
            /* not openable */
          }
        }),
      );
      if (!cancelled) setOpenableUrls(ok);
    })();
    return () => {
      cancelled = true;
    };
  }, [allChannels]);

  // ---- Quick actions ----
  const openTel = useCallback(() => {
    if (!number) return;
    void logCall({ number, contact_id: contactId, outcome: "called" }).catch(
      () => {},
    );
    router.push({
      pathname: "/call/active",
      params: { number, ...(displayName ? { name: displayName } : {}) },
    });
  }, [number, contactId, displayName, router]);

  const openSms = useCallback(() => {
    if (!number) return;
    void logCall({ number, contact_id: contactId, outcome: "messaged" }).catch(
      () => {},
    );
    void Linking.openURL(`sms:${number}`);
  }, [number, contactId]);

  const openEmail = useCallback(() => {
    const email = profile?.contact?.emails?.[0]?.value;
    if (!email) {
      Alert.alert("No email", "This contact has no email address saved.");
      return;
    }
    void Linking.openURL(`mailto:${email}`);
  }, [profile]);

  const shareBio = useCallback(async () => {
    if (!bioUrl) {
      Alert.alert("No biolink", "No 1INME biolink found for this number.");
      return;
    }
    try {
      await Share.share({ message: bioUrl, url: bioUrl });
    } catch {
      /* user cancelled */
    }
  }, [bioUrl]);

  const copyNumber = useCallback(async () => {
    if (!number) return;
    await Clipboard.setStringAsync(number);
    Alert.alert("Copied", "Number copied to clipboard.");
  }, [number]);

  const saveOrEdit = useCallback(() => {
    if (contactId) {
      router.push({
        pathname: "/contacts/[id]",
        params: { id: String(contactId) },
      });
    } else {
      router.push("/contacts/new");
    }
  }, [contactId, router]);

  // ---- Reach-via openers + vCard export ----
  const openChannel = useCallback(async (c: DialerChannel) => {
    const target = c.scheme_url ?? c.url;
    try {
      const can = await Linking.canOpenURL(target);
      await Linking.openURL(can ? target : c.url);
    } catch {
      try {
        await Linking.openURL(c.url);
      } catch {
        Alert.alert("Can't open", "No app available to handle this.");
      }
    }
  }, []);

  const openUrl = useCallback((u: string) => {
    Linking.openURL(u).catch(() =>
      Alert.alert("Can't open", "Unable to open this link."),
    );
  }, []);

  const exportVcard = useCallback(async () => {
    if (!profile?.vcard_url) return;
    try {
      await Share.share({ message: profile.vcard_url, url: profile.vcard_url });
    } catch {
      openUrl(profile.vcard_url);
    }
  }, [profile, openUrl]);

  // ---- Spam / block / favorite toggles ----
  const toggleFavorite = useCallback(async () => {
    if (!number) return;
    setBusy(true);
    try {
      await addFavorite({ number, contact_id: contactId });
      setLookup((l) => (l ? { ...l, is_favorite: true } : l));
      Alert.alert("Added", "Added to speed dial.");
    } catch {
      Alert.alert("Error", "Could not add to speed dial.");
    } finally {
      setBusy(false);
    }
  }, [number, contactId]);

  const toggleFlag = useCallback(
    async (field: "is_spam" | "is_blocked") => {
      if (!number) return;
      const next = !(lookup?.[field] ?? false);
      setBusy(true);
      try {
        const res = await flagNumber({ number, [field]: next });
        setLookup((l) =>
          l ? { ...l, is_spam: res.is_spam, is_blocked: res.is_blocked } : l,
        );
      } catch {
        Alert.alert("Error", "Could not update flag.");
      } finally {
        setBusy(false);
      }
    },
    [number, lookup],
  );

  // ---- Call log + callback ----
  const saveLog = useCallback(async () => {
    if (!number) return;
    setBusy(true);
    try {
      await logCall({
        number,
        contact_id: contactId,
        outcome,
        note: note.trim() || null,
        tag: tag.trim() || null,
      });
      setNote("");
      setTag("");
      setOutcome(null);
      await refresh();
      Alert.alert("Saved", "Call logged.");
    } catch {
      Alert.alert("Error", "Could not log the call.");
    } finally {
      setBusy(false);
    }
  }, [number, contactId, outcome, note, tag, refresh]);

  const scheduleCallback = useCallback(
    async (ms: number) => {
      if (!number) return;
      setBusy(true);
      try {
        await setCallback({
          number,
          contact_id: contactId,
          callback_at: new Date(Date.now() + ms).toISOString(),
          note: note.trim() || null,
        });
        await refresh();
        Alert.alert("Reminder set", "We'll remind you to call back.");
      } catch {
        Alert.alert("Error", "Could not set the reminder.");
      } finally {
        setBusy(false);
      }
    },
    [number, contactId, note, refresh],
  );

  const pendingCallback = useMemo<DialerActivity | null>(() => {
    const act = lookup?.activity ?? [];
    return act.find((a) => a.callback_at) ?? null;
  }, [lookup]);

  // ---- Manual additions editor ----
  const saveManual = useCallback(async () => {
    if (!contactId) return;
    setSaving(true);
    try {
      const hasLocation =
        draftLocation.address.trim() !== "" ||
        draftLocation.lat !== null ||
        draftLocation.lng !== null;
      await updateContactManualProfile(contactId, {
        channels: draftChannels.filter((c) => c.value.trim() !== ""),
        socials: draftSocials.filter((s) => s.url.trim() !== ""),
        location: hasLocation ? draftLocation : null,
      });
      setEditing(false);
      await refresh();
    } catch (e) {
      Alert.alert(
        "Save failed",
        e instanceof Error ? e.message : "Could not save your changes.",
      );
    } finally {
      setSaving(false);
    }
  }, [contactId, draftChannels, draftSocials, draftLocation, refresh]);

  if (loading) {
    return (
      <View
        style={[styles.root, styles.center, { backgroundColor: colors.background }]}
      >
        <Stack.Screen options={{ title: "Profile" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  const visibleChannels = allChannels.filter((c) => openableUrls.has(c.url));
  const allSocials = profile
    ? [...profile.socials, ...profile.manual.socials]
    : [];
  const allLocations = profile
    ? [
        ...profile.locations,
        ...(profile.manual.location ? [profile.manual.location] : []),
      ]
    : [];
  const mapLocations = allLocations.filter(
    (l) =>
      typeof l.lat === "number" &&
      isFinite(l.lat) &&
      typeof l.lng === "number" &&
      isFinite(l.lng),
  );

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.background }}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
    >
      <Stack.Screen options={{ title: "Profile" }} />
      <ScrollView
        style={styles.root}
        contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32 }}
      >
        {/* Header */}
        <View style={styles.header}>
          <View
            style={[
              styles.avatar,
              {
                backgroundColor: avatarUri ? colors.muted : colors.primary,
                borderColor: colors.border,
              },
            ]}
          >
            {avatarUri ? (
              <Image source={{ uri: avatarUri }} style={styles.avatarImg} />
            ) : (
              <Text style={styles.avatarText}>{initials}</Text>
            )}
          </View>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text
              style={[styles.name, { color: colors.foreground }]}
              numberOfLines={1}
            >
              {displayName}
            </Text>
            {!!number && (
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                {number}
              </Text>
            )}
            {!!profile?.contact?.organization && (
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 13,
                  marginTop: 2,
                }}
              >
                {profile.contact.organization}
              </Text>
            )}
            <View style={styles.badges}>
              {lookup?.is_spam && <Badge text="SPAM" color="#ef4444" />}
              {lookup?.is_blocked && <Badge text="BLOCKED" color="#9ca3af" />}
              {(lookup?.biolink || profile?.biolink) && (
                <Badge text="1INME" color="#ec4899" />
              )}
            </View>
          </View>
        </View>

        {/* Quick-action bar */}
        <View style={styles.actions}>
          <Action icon="phone" label="Call" tint="#22c55e" onPress={openTel} />
          <Action
            icon="message-circle"
            label="SMS"
            tint="#3b82f6"
            onPress={openSms}
          />
          <Action icon="mail" label="Email" tint="#818cf8" onPress={openEmail} />
          <Action icon="share-2" label="Bio" tint="#ec4899" onPress={shareBio} />
          <Action
            icon="copy"
            label="Copy"
            tint={colors.foreground}
            onPress={copyNumber}
          />
          <Action
            icon={contactId ? "edit-2" : "user-plus"}
            label={contactId ? "Edit" : "Save"}
            tint={colors.foreground}
            onPress={saveOrEdit}
          />
        </View>

        {/* Export vCard */}
        {!!profile?.vcard_url && (
          <Pressable
            onPress={exportVcard}
            style={({ pressed }) => [
              styles.vcardBtn,
              { borderColor: colors.border, opacity: pressed ? 0.7 : 1 },
            ]}
          >
            <Feather name="share-2" size={15} color={colors.primary} />
            <Text
              style={{
                color: colors.primary,
                marginLeft: 8,
                fontFamily: "SpaceGrotesk_600SemiBold",
              }}
            >
              Export vCard
            </Text>
          </Pressable>
        )}

        {/* Toggles */}
        {e164 && (
          <View style={styles.toggles}>
            <Toggle
              icon="star"
              label={lookup?.is_favorite ? "In speed dial" : "Speed dial"}
              active={!!lookup?.is_favorite}
              color="#fbbf24"
              onPress={toggleFavorite}
              disabled={busy}
            />
            <Toggle
              icon="alert-triangle"
              label={lookup?.is_spam ? "Unmark spam" : "Mark spam"}
              active={!!lookup?.is_spam}
              color="#ef4444"
              onPress={() => toggleFlag("is_spam")}
              disabled={busy}
            />
            <Toggle
              icon="slash"
              label={lookup?.is_blocked ? "Unblock" : "Block"}
              active={!!lookup?.is_blocked}
              color="#9ca3af"
              onPress={() => toggleFlag("is_blocked")}
              disabled={busy}
            />
          </View>
        )}

        {/* Biolink card */}
        {bioUrl && (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: "#ec489955" },
            ]}
          >
            <Text style={[styles.cardKicker, { color: "#ec4899" }]}>
              1INME BIOLINK
            </Text>
            <Text style={[styles.cardTitle, { color: colors.foreground }]}>
              {bioName}
            </Text>
            {!!bioHandle && (
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                @{bioHandle}
              </Text>
            )}
            <Pressable
              onPress={() => openUrl(bioUrl)}
              style={[styles.bioBtn, { backgroundColor: colors.primary }]}
            >
              <Text style={styles.bioBtnText}>Open biolink</Text>
            </Pressable>
          </View>
        )}

        {/* Reach via — multi-app chooser */}
        {visibleChannels.length > 0 && (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Text
              style={[styles.cardKicker, { color: colors.mutedForeground }]}
            >
              REACH VIA
            </Text>
            <View style={styles.channelGrid}>
              {visibleChannels.map((c, i) => (
                <Pressable
                  key={`${c.url}-${i}`}
                  onPress={() => openChannel(c)}
                  style={({ pressed }) => [
                    styles.channel,
                    {
                      borderColor: colors.border,
                      backgroundColor: pressed ? colors.muted : "transparent",
                    },
                  ]}
                >
                  <Feather
                    name={iconForChannel(c.type)}
                    size={18}
                    color={colors.primary}
                  />
                  <View style={{ flex: 1, marginLeft: 10 }}>
                    <Text
                      numberOfLines={1}
                      style={{
                        color: colors.foreground,
                        fontFamily: "SpaceGrotesk_600SemiBold",
                      }}
                    >
                      {c.label}
                    </Text>
                    <Text
                      numberOfLines={1}
                      style={{ color: colors.mutedForeground, fontSize: 12 }}
                    >
                      {c.value}
                    </Text>
                  </View>
                  {c.source === "manual" && (
                    <View style={[styles.tag, { backgroundColor: colors.muted }]}>
                      <Text style={{ color: colors.mutedForeground, fontSize: 9 }}>
                        manual
                      </Text>
                    </View>
                  )}
                </Pressable>
              ))}
            </View>
          </View>
        )}

        {/* Socials */}
        {allSocials.length > 0 && (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Text
              style={[styles.cardKicker, { color: colors.mutedForeground }]}
            >
              SOCIALS
            </Text>
            <View style={styles.socialWrap}>
              {allSocials.map((s, i) => (
                <Pressable
                  key={`${s.url}-${i}`}
                  onPress={() => openUrl(s.url)}
                  style={({ pressed }) => [
                    styles.socialChip,
                    {
                      borderColor: colors.border,
                      backgroundColor: pressed ? colors.muted : "transparent",
                    },
                  ]}
                >
                  <Feather name="globe" size={13} color={colors.primary} />
                  <Text
                    style={{
                      color: colors.foreground,
                      marginLeft: 6,
                      fontFamily: "SpaceGrotesk_500Medium",
                      fontSize: 13,
                    }}
                  >
                    {s.label}
                  </Text>
                  {s.source === "manual" && (
                    <View
                      style={[
                        styles.tag,
                        { backgroundColor: colors.muted, marginLeft: 6 },
                      ]}
                    >
                      <Text style={{ color: colors.mutedForeground, fontSize: 9 }}>
                        manual
                      </Text>
                    </View>
                  )}
                </Pressable>
              ))}
            </View>
          </View>
        )}

        {/* Locations */}
        {allLocations.length > 0 && (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Text
              style={[styles.cardKicker, { color: colors.mutedForeground }]}
            >
              LOCATIONS
            </Text>
            {mapLocations.length >= 2 && (
              <MapMarkersPreview
                markers={mapLocations.map((l) => ({
                  lat: l.lat as number,
                  lng: l.lng as number,
                  label: l.label,
                  address: l.address,
                  url: l.maps_url,
                }))}
                onMarkerPress={openUrl}
                style={[styles.combinedMap, { borderColor: colors.border }]}
              />
            )}
            {allLocations.map((loc, i) => {
              const hasPoint =
                typeof loc.lat === "number" &&
                isFinite(loc.lat) &&
                typeof loc.lng === "number" &&
                isFinite(loc.lng);
              const showThumb = hasPoint && mapLocations.length < 2;
              const showBadge = hasPoint && mapLocations.length >= 2;
              const badgeNum = showBadge
                ? allLocations
                    .slice(0, i + 1)
                    .filter(
                      (l) =>
                        typeof l.lat === "number" &&
                        isFinite(l.lat) &&
                        typeof l.lng === "number" &&
                        isFinite(l.lng),
                    ).length
                : 0;
              return (
              <Pressable
                key={`${loc.maps_url}-${i}`}
                onPress={() => openUrl(loc.maps_url)}
                style={({ pressed }) => [
                  showThumb ? styles.locationCard : styles.locationRow,
                  {
                    borderColor: colors.border,
                    backgroundColor: pressed ? colors.muted : "transparent",
                  },
                ]}
              >
                {showThumb && (
                  <MapPreview
                    lat={loc.lat as number}
                    lng={loc.lng as number}
                    style={styles.locationMap}
                  />
                )}
                <View style={showThumb ? styles.locationCardBody : styles.locationRowBody}>
                {showBadge ? (
                  <View style={styles.locationBadge}>
                    <Text style={styles.locationBadgeText}>{badgeNum}</Text>
                  </View>
                ) : (
                  <Feather name="map-pin" size={16} color="#f87171" />
                )}
                <View style={{ flex: 1, marginLeft: 10 }}>
                  <Text
                    numberOfLines={1}
                    style={{
                      color: colors.foreground,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                    }}
                  >
                    {loc.label}
                  </Text>
                  {!!loc.address && (
                    <Text
                      numberOfLines={1}
                      style={{ color: colors.mutedForeground, fontSize: 12 }}
                    >
                      {loc.address}
                    </Text>
                  )}
                </View>
                {loc.source === "manual" && (
                  <View style={[styles.tag, { backgroundColor: colors.muted }]}>
                    <Text style={{ color: colors.mutedForeground, fontSize: 9 }}>
                      manual
                    </Text>
                  </View>
                )}
                </View>
              </Pressable>
              );
            })}
          </View>
        )}

        {/* Log a call */}
        {e164 && (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Text
              style={[styles.cardKicker, { color: colors.mutedForeground }]}
            >
              LOG THIS CALL
            </Text>
            <View style={styles.chips}>
              {OUTCOMES.map((o) => {
                const active = outcome === o.v;
                return (
                  <Pressable
                    key={o.v}
                    onPress={() => setOutcome(active ? null : o.v)}
                    style={[
                      styles.chip,
                      {
                        backgroundColor: active ? colors.primary : colors.muted,
                        borderColor: active ? colors.primary : colors.border,
                      },
                    ]}
                  >
                    <Text
                      style={{
                        color: active ? "#fff" : colors.foreground,
                        fontFamily: "SpaceGrotesk_500Medium",
                        fontSize: 12,
                      }}
                    >
                      {o.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            <TextInput
              value={tag}
              onChangeText={setTag}
              placeholder="Tag (e.g. lead, family)"
              placeholderTextColor={colors.mutedForeground}
              maxLength={50}
              style={[
                styles.input,
                {
                  color: colors.foreground,
                  borderColor: colors.border,
                  backgroundColor: colors.muted,
                },
              ]}
            />
            <TextInput
              value={note}
              onChangeText={setNote}
              placeholder="Note about this call…"
              placeholderTextColor={colors.mutedForeground}
              multiline
              maxLength={2000}
              style={[
                styles.input,
                {
                  color: colors.foreground,
                  borderColor: colors.border,
                  backgroundColor: colors.muted,
                  minHeight: 64,
                  textAlignVertical: "top",
                },
              ]}
            />
            <Pressable
              onPress={saveLog}
              disabled={busy}
              style={[
                styles.saveBtn,
                { backgroundColor: colors.primary, opacity: busy ? 0.6 : 1 },
              ]}
            >
              <Text style={styles.saveBtnText}>Save log</Text>
            </Pressable>
          </View>
        )}

        {/* Callback reminder */}
        {e164 && (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Text
              style={[styles.cardKicker, { color: colors.mutedForeground }]}
            >
              CALL-BACK REMINDER
            </Text>
            {pendingCallback?.callback_at ? (
              <View style={styles.cbActive}>
                <Feather name="bell" size={14} color={colors.primary} />
                <Text
                  style={{
                    color: colors.foreground,
                    fontFamily: "SpaceGrotesk_500Medium",
                  }}
                >
                  {" "}
                  Reminder {relative(pendingCallback.callback_at)}
                </Text>
                <Pressable
                  onPress={async () => {
                    if (!pendingCallback) return;
                    setBusy(true);
                    try {
                      await clearCallback(pendingCallback.id);
                      await refresh();
                    } finally {
                      setBusy(false);
                    }
                  }}
                  style={{ marginLeft: "auto" }}
                >
                  <Text
                    style={{
                      color: "#ef4444",
                      fontFamily: "SpaceGrotesk_500Medium",
                    }}
                  >
                    Clear
                  </Text>
                </Pressable>
              </View>
            ) : (
              <View style={styles.chips}>
                {CALLBACK_PRESETS.map((p) => (
                  <Pressable
                    key={p.label}
                    onPress={() => scheduleCallback(p.ms)}
                    disabled={busy}
                    style={[
                      styles.chip,
                      { backgroundColor: colors.muted, borderColor: colors.border },
                    ]}
                  >
                    <Text
                      style={{
                        color: colors.foreground,
                        fontFamily: "SpaceGrotesk_500Medium",
                        fontSize: 12,
                      }}
                    >
                      {p.label}
                    </Text>
                  </Pressable>
                ))}
              </View>
            )}
          </View>
        )}

        {/* Recent activity */}
        {(lookup?.activity?.length ?? 0) > 0 && (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Text
              style={[styles.cardKicker, { color: colors.mutedForeground }]}
            >
              RECENT ACTIVITY
            </Text>
            {lookup!.activity.map((a) => (
              <View
                key={a.id}
                style={[styles.activityRow, { borderTopColor: colors.border }]}
              >
                <View style={{ flex: 1 }}>
                  <Text
                    style={{
                      color: colors.foreground,
                      fontFamily: "SpaceGrotesk_500Medium",
                      textTransform: "capitalize",
                    }}
                  >
                    {a.outcome ? a.outcome.replace(/_/g, " ") : "Lookup"}
                    {a.tag ? `  ·  ${a.tag}` : ""}
                  </Text>
                  {!!a.note && (
                    <Text
                      style={{
                        color: colors.mutedForeground,
                        fontSize: 12,
                        marginTop: 2,
                      }}
                    >
                      {a.note}
                    </Text>
                  )}
                </View>
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  {a.at_human}
                </Text>
              </View>
            ))}
          </View>
        )}

        {/* Manual additions — only when a saved contact is attached */}
        {profile?.contact && (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                justifyContent: "space-between",
              }}
            >
              <Text
                style={[styles.cardKicker, { color: colors.mutedForeground, marginBottom: 0 }]}
              >
                MANUAL ADDITIONS
              </Text>
              {!editing ? (
                <Pressable onPress={() => setEditing(true)}>
                  <Text
                    style={{
                      color: colors.primary,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                    }}
                  >
                    Edit
                  </Text>
                </Pressable>
              ) : (
                <Pressable onPress={saveManual} disabled={saving}>
                  {saving ? (
                    <ActivityIndicator color={colors.primary} size="small" />
                  ) : (
                    <Text
                      style={{
                        color: colors.primary,
                        fontFamily: "SpaceGrotesk_600SemiBold",
                      }}
                    >
                      Save
                    </Text>
                  )}
                </Pressable>
              )}
            </View>

            {!editing ? (
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 13,
                  marginTop: 8,
                }}
              >
                Add your own channels, socials, or a location for this contact.
                These stay separate from anything pulled from their biolink.
              </Text>
            ) : (
              <View style={{ marginTop: 12 }}>
                {/* Channels */}
                <Text style={[styles.subLabel, { color: colors.foreground }]}>
                  Channels
                </Text>
                {draftChannels.map((c, i) => (
                  <View key={`dc-${i}`} style={styles.editorRow}>
                    <View style={styles.typeChips}>
                      {CHANNEL_TYPES.map((t) => (
                        <Pressable
                          key={t.value}
                          onPress={() =>
                            setDraftChannels((prev) =>
                              prev.map((x, j) =>
                                j === i ? { ...x, type: t.value } : x,
                              ),
                            )
                          }
                          style={[
                            styles.typeChip,
                            {
                              borderColor:
                                c.type === t.value
                                  ? colors.primary
                                  : colors.border,
                              backgroundColor:
                                c.type === t.value
                                  ? colors.primary
                                  : "transparent",
                            },
                          ]}
                        >
                          <Text
                            style={{
                              color:
                                c.type === t.value
                                  ? "#fff"
                                  : colors.mutedForeground,
                              fontSize: 11,
                            }}
                          >
                            {t.label}
                          </Text>
                        </Pressable>
                      ))}
                    </View>
                    <View
                      style={{
                        flexDirection: "row",
                        alignItems: "center",
                        marginTop: 6,
                      }}
                    >
                      <TextInput
                        value={c.value}
                        onChangeText={(v) =>
                          setDraftChannels((prev) =>
                            prev.map((x, j) => (j === i ? { ...x, value: v } : x)),
                          )
                        }
                        placeholder="Number / URL / handle"
                        placeholderTextColor={colors.mutedForeground}
                        autoCapitalize="none"
                        style={[
                          styles.mInput,
                          { color: colors.foreground, borderColor: colors.border },
                        ]}
                      />
                      <Pressable
                        onPress={() =>
                          setDraftChannels((prev) =>
                            prev.filter((_, j) => j !== i),
                          )
                        }
                        style={{ paddingHorizontal: 8 }}
                      >
                        <Feather name="trash-2" size={16} color="#ef4444" />
                      </Pressable>
                    </View>
                  </View>
                ))}
                <Pressable
                  onPress={() =>
                    setDraftChannels((prev) => [
                      ...prev,
                      { type: "phone", label: "", value: "" },
                    ])
                  }
                  style={styles.addBtn}
                >
                  <Feather name="plus" size={14} color={colors.primary} />
                  <Text
                    style={{ color: colors.primary, marginLeft: 6, fontSize: 13 }}
                  >
                    Add channel
                  </Text>
                </Pressable>

                {/* Socials */}
                <Text
                  style={[
                    styles.subLabel,
                    { color: colors.foreground, marginTop: 16 },
                  ]}
                >
                  Socials
                </Text>
                {draftSocials.map((s, i) => (
                  <View
                    key={`ds-${i}`}
                    style={{
                      flexDirection: "row",
                      alignItems: "center",
                      marginTop: 6,
                    }}
                  >
                    <TextInput
                      value={s.platform}
                      onChangeText={(v) =>
                        setDraftSocials((prev) =>
                          prev.map((x, j) => (j === i ? { ...x, platform: v } : x)),
                        )
                      }
                      placeholder="Platform"
                      placeholderTextColor={colors.mutedForeground}
                      autoCapitalize="none"
                      style={[
                        styles.mInput,
                        {
                          color: colors.foreground,
                          borderColor: colors.border,
                          flex: 0,
                          width: 100,
                        },
                      ]}
                    />
                    <TextInput
                      value={s.url}
                      onChangeText={(v) =>
                        setDraftSocials((prev) =>
                          prev.map((x, j) => (j === i ? { ...x, url: v } : x)),
                        )
                      }
                      placeholder="https://…"
                      placeholderTextColor={colors.mutedForeground}
                      autoCapitalize="none"
                      style={[
                        styles.mInput,
                        {
                          color: colors.foreground,
                          borderColor: colors.border,
                          marginLeft: 6,
                        },
                      ]}
                    />
                    <Pressable
                      onPress={() =>
                        setDraftSocials((prev) => prev.filter((_, j) => j !== i))
                      }
                      style={{ paddingHorizontal: 8 }}
                    >
                      <Feather name="trash-2" size={16} color="#ef4444" />
                    </Pressable>
                  </View>
                ))}
                <Pressable
                  onPress={() =>
                    setDraftSocials((prev) => [
                      ...prev,
                      { platform: "", label: "", url: "" },
                    ])
                  }
                  style={styles.addBtn}
                >
                  <Feather name="plus" size={14} color={colors.primary} />
                  <Text
                    style={{ color: colors.primary, marginLeft: 6, fontSize: 13 }}
                  >
                    Add social
                  </Text>
                </Pressable>

                {/* Location */}
                <View
                  style={{
                    flexDirection: "row",
                    alignItems: "center",
                    justifyContent: "space-between",
                    marginTop: 16,
                  }}
                >
                  <Text style={[styles.subLabel, { color: colors.foreground }]}>
                    Location
                  </Text>
                  <Pressable
                    onPress={() => setMapPickerOpen(true)}
                    style={{
                      flexDirection: "row",
                      alignItems: "center",
                      gap: 6,
                      paddingHorizontal: 10,
                      paddingVertical: 6,
                      borderRadius: 8,
                      backgroundColor: colors.primary + "22",
                    }}
                  >
                    <Feather name="map-pin" size={13} color={colors.primary} />
                    <Text
                      style={{
                        color: colors.primary,
                        fontFamily: "SpaceGrotesk_600SemiBold",
                        fontSize: 12,
                      }}
                    >
                      Pick on map
                    </Text>
                  </Pressable>
                </View>
                <TextInput
                  value={draftLocation.label}
                  onChangeText={(v) =>
                    setDraftLocation((p) => ({ ...p, label: v }))
                  }
                  placeholder="Label (e.g. Office)"
                  placeholderTextColor={colors.mutedForeground}
                  style={[
                    styles.mInput,
                    {
                      color: colors.foreground,
                      borderColor: colors.border,
                      marginTop: 6,
                    },
                  ]}
                />
                <TextInput
                  value={draftLocation.address}
                  onChangeText={(v) =>
                    setDraftLocation((p) => ({ ...p, address: v }))
                  }
                  placeholder="Address"
                  placeholderTextColor={colors.mutedForeground}
                  style={[
                    styles.mInput,
                    {
                      color: colors.foreground,
                      borderColor: colors.border,
                      marginTop: 6,
                    },
                  ]}
                />
                <View style={{ flexDirection: "row", marginTop: 6 }}>
                  <TextInput
                    value={draftLocation.lat == null ? "" : String(draftLocation.lat)}
                    onChangeText={(v) =>
                      setDraftLocation((p) => ({
                        ...p,
                        lat: v.trim() === "" ? null : Number(v),
                      }))
                    }
                    placeholder="Latitude"
                    placeholderTextColor={colors.mutedForeground}
                    keyboardType="numbers-and-punctuation"
                    style={[
                      styles.mInput,
                      { color: colors.foreground, borderColor: colors.border, flex: 1 },
                    ]}
                  />
                  <TextInput
                    value={draftLocation.lng == null ? "" : String(draftLocation.lng)}
                    onChangeText={(v) =>
                      setDraftLocation((p) => ({
                        ...p,
                        lng: v.trim() === "" ? null : Number(v),
                      }))
                    }
                    placeholder="Longitude"
                    placeholderTextColor={colors.mutedForeground}
                    keyboardType="numbers-and-punctuation"
                    style={[
                      styles.mInput,
                      {
                        color: colors.foreground,
                        borderColor: colors.border,
                        flex: 1,
                        marginLeft: 6,
                      },
                    ]}
                  />
                </View>
                {draftLocation.lat != null &&
                  draftLocation.lng != null &&
                  isFinite(draftLocation.lat) &&
                  isFinite(draftLocation.lng) && (
                    <View style={{ marginTop: 8 }}>
                      <MapPreview
                        lat={draftLocation.lat}
                        lng={draftLocation.lng}
                        height={140}
                        style={{
                          borderRadius: 12,
                          overflow: "hidden",
                          borderWidth: 1,
                          borderColor: colors.border,
                        }}
                      />
                      <Text
                        style={{
                          color: colors.mutedForeground,
                          fontFamily: "SpaceGrotesk_400Regular",
                          fontSize: 11,
                          marginTop: 6,
                        }}
                      >
                        Preview of the point you'll save.
                      </Text>
                    </View>
                  )}
              </View>
            )}
          </View>
        )}
      </ScrollView>
      <MapPickerModal
        visible={mapPickerOpen}
        initialLat={draftLocation.lat}
        initialLng={draftLocation.lng}
        onClose={() => setMapPickerOpen(false)}
        onPick={(point: PickedPoint) =>
          setDraftLocation((p) => ({
            ...p,
            lat: point.lat,
            lng: point.lng,
            address: point.address || p.address,
          }))
        }
      />
    </KeyboardAvoidingView>
  );
}

function Badge({ text, color }: { text: string; color: string }) {
  return (
    <View style={[styles.badge, { backgroundColor: `${color}22` }]}>
      <Text style={[styles.badgeText, { color }]}>{text}</Text>
    </View>
  );
}

function Action({
  icon,
  label,
  tint,
  onPress,
}: {
  icon: keyof typeof Feather.glyphMap;
  label: string;
  tint: string;
  onPress: () => void;
}) {
  const colors = useColors();
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.action,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          opacity: pressed ? 0.7 : 1,
        },
      ]}
    >
      <Feather name={icon} size={18} color={tint} />
      <Text style={[styles.actionLabel, { color: colors.foreground }]}>
        {label}
      </Text>
    </Pressable>
  );
}

function Toggle({
  icon,
  label,
  active,
  color,
  onPress,
  disabled,
}: {
  icon: keyof typeof Feather.glyphMap;
  label: string;
  active: boolean;
  color: string;
  onPress: () => void;
  disabled?: boolean;
}) {
  const colors = useColors();
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      style={({ pressed }) => [
        styles.toggle,
        {
          backgroundColor: active ? `${color}22` : colors.muted,
          borderColor: active ? `${color}55` : colors.border,
          opacity: pressed ? 0.7 : 1,
        },
      ]}
    >
      <Feather
        name={icon}
        size={13}
        color={active ? color : colors.mutedForeground}
      />
      <Text
        style={{
          color: active ? color : colors.foreground,
          fontFamily: "SpaceGrotesk_500Medium",
          fontSize: 12,
          marginLeft: 5,
        }}
      >
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  center: { alignItems: "center", justifyContent: "center" },
  header: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    marginBottom: 18,
  },
  avatar: {
    width: 60,
    height: 60,
    borderRadius: 30,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  avatarImg: { width: "100%", height: "100%" },
  avatarText: { color: "#fff", fontFamily: "SpaceGrotesk_700Bold", fontSize: 20 },
  name: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 20 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, marginTop: 2 },
  badges: { flexDirection: "row", gap: 6, marginTop: 6 },
  badge: { paddingHorizontal: 6, paddingVertical: 2, borderRadius: 5 },
  badgeText: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 9, letterSpacing: 0.5 },
  actions: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 12 },
  action: {
    width: "31.5%",
    paddingVertical: 12,
    borderRadius: 12,
    borderWidth: StyleSheet.hairlineWidth,
    alignItems: "center",
    gap: 4,
  },
  actionLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 11 },
  vcardBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    borderRadius: 12,
    paddingVertical: 11,
    marginBottom: 16,
  },
  toggles: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 16 },
  toggle: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderRadius: 10,
    borderWidth: StyleSheet.hairlineWidth,
  },
  card: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 16,
    marginBottom: 14,
  },
  cardKicker: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 10,
    letterSpacing: 1,
    marginBottom: 10,
  },
  cardTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 17 },
  bioBtn: {
    marginTop: 12,
    paddingVertical: 10,
    borderRadius: 10,
    alignItems: "center",
  },
  bioBtnText: { color: "#fff", fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 10 },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 999,
    borderWidth: StyleSheet.hairlineWidth,
  },
  input: {
    borderWidth: StyleSheet.hairlineWidth,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 10,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
  },
  saveBtn: { paddingVertical: 12, borderRadius: 10, alignItems: "center" },
  saveBtnText: { color: "#fff", fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  cbActive: { flexDirection: "row", alignItems: "center" },
  activityRow: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    gap: 8,
  },
  // Identity sections
  channelGrid: { marginTop: 10, gap: 8 },
  channel: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
  },
  socialWrap: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginTop: 0 },
  socialChip: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 7,
  },
  locationRow: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginTop: 10,
  },
  locationRowBody: { flex: 1, flexDirection: "row", alignItems: "center" },
  locationBadge: {
    width: 20,
    height: 20,
    borderRadius: 10,
    backgroundColor: "#7c3aed",
    alignItems: "center",
    justifyContent: "center",
  },
  locationBadgeText: {
    color: "#fff",
    fontSize: 11,
    fontFamily: "SpaceGrotesk_600SemiBold",
  },
  locationCard: {
    borderWidth: 1,
    borderRadius: 12,
    marginTop: 10,
    overflow: "hidden",
  },
  locationMap: { width: "100%" },
  combinedMap: {
    width: "100%",
    borderWidth: 1,
    borderRadius: 12,
    overflow: "hidden",
    marginTop: 10,
    marginBottom: 4,
  },
  locationCardBody: {
    flexDirection: "row",
    alignItems: "center",
    padding: 12,
  },
  tag: { borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2 },
  subLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
    marginBottom: 2,
  },
  editorRow: { marginTop: 10 },
  typeChips: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  typeChip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  mInput: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 9,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
  },
  addBtn: {
    flexDirection: "row",
    alignItems: "center",
    marginTop: 8,
    alignSelf: "flex-start",
  },
});
