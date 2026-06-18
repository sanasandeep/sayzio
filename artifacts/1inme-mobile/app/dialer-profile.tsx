import { Feather } from "@expo/vector-icons";
import * as Clipboard from "expo-clipboard";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Linking,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import { getContact, type Contact } from "@/lib/api/contacts";
import {
  type DialerActivity,
  type DialerLookupResult,
  addFavorite,
  clearCallback,
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
  const [contact, setContact] = useState<Contact | null>(null);
  const [busy, setBusy] = useState(false);

  const [outcome, setOutcome] = useState<string | null>(null);
  const [note, setNote] = useState("");
  const [tag, setTag] = useState("");

  const e164 = useMemo(() => (E164.test(number) ? number : null), [number]);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      if (e164) {
        const res = await lookupNumber(e164);
        setLookup(res);
      }
      if (contactId) {
        try {
          setContact(await getContact(contactId));
        } catch {
          /* ignore */
        }
      }
    } finally {
      setLoading(false);
    }
  }, [e164, contactId]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const displayName =
    contact?.display_name ||
    lookup?.contact?.display_name ||
    lookup?.biolink?.name ||
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
    const email = contact?.emails?.[0]?.value;
    if (!email) {
      Alert.alert("No email", "This contact has no email address saved.");
      return;
    }
    void Linking.openURL(`mailto:${email}`);
  }, [contact]);

  const shareBio = useCallback(async () => {
    const url = lookup?.biolink?.url;
    if (!url) {
      Alert.alert("No biolink", "No 1INME biolink found for this number.");
      return;
    }
    try {
      await Share.share({ message: url, url });
    } catch {
      /* user cancelled */
    }
  }, [lookup]);

  const copyNumber = useCallback(async () => {
    if (!number) return;
    await Clipboard.setStringAsync(number);
    Alert.alert("Copied", "Number copied to clipboard.");
  }, [number]);

  const saveOrEdit = useCallback(() => {
    if (contactId) {
      router.push({ pathname: "/contacts/[id]", params: { id: String(contactId) } });
    } else {
      router.push("/contacts/new");
    }
  }, [contactId, router]);

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

  if (loading) {
    return (
      <View style={[styles.root, styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Profile" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <ScrollView
      style={[styles.root, { backgroundColor: colors.background }]}
      contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32 }}
    >
      <Stack.Screen options={{ title: "Profile" }} />

      {/* Header */}
      <View style={styles.header}>
        <View style={[styles.avatar, { backgroundColor: colors.primary }]}>
          <Text style={styles.avatarText}>{initials}</Text>
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
            {displayName}
          </Text>
          <Text style={[styles.sub, { color: colors.mutedForeground }]}>{number}</Text>
          <View style={styles.badges}>
            {lookup?.is_spam && <Badge text="SPAM" color="#ef4444" />}
            {lookup?.is_blocked && <Badge text="BLOCKED" color="#9ca3af" />}
            {lookup?.biolink && <Badge text="1INME" color="#ec4899" />}
          </View>
        </View>
      </View>

      {/* Quick-action bar */}
      <View style={styles.actions}>
        <Action icon="phone" label="Call" tint="#22c55e" onPress={openTel} />
        <Action icon="message-circle" label="SMS" tint="#3b82f6" onPress={openSms} />
        <Action icon="mail" label="Email" tint="#818cf8" onPress={openEmail} />
        <Action icon="share-2" label="Bio" tint="#ec4899" onPress={shareBio} />
        <Action icon="copy" label="Copy" tint={colors.foreground} onPress={copyNumber} />
        <Action
          icon={contactId ? "edit-2" : "user-plus"}
          label={contactId ? "Edit" : "Save"}
          tint={colors.foreground}
          onPress={saveOrEdit}
        />
      </View>

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
      {lookup?.biolink?.url && (
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: "#ec489955" }]}>
          <Text style={[styles.cardKicker, { color: "#ec4899" }]}>1INME BIOLINK</Text>
          <Text style={[styles.cardTitle, { color: colors.foreground }]}>
            {lookup.biolink.name}
          </Text>
          {lookup.biolink.handle && (
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              @{lookup.biolink.handle}
            </Text>
          )}
          <Pressable
            onPress={() => lookup.biolink?.url && Linking.openURL(lookup.biolink.url)}
            style={[styles.bioBtn, { backgroundColor: colors.primary }]}
          >
            <Text style={styles.bioBtnText}>Open biolink</Text>
          </Pressable>
        </View>
      )}

      {/* Log a call */}
      {e164 && (
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Text style={[styles.cardKicker, { color: colors.mutedForeground }]}>LOG THIS CALL</Text>
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
            style={[styles.input, { color: colors.foreground, borderColor: colors.border, backgroundColor: colors.muted }]}
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
              { color: colors.foreground, borderColor: colors.border, backgroundColor: colors.muted, minHeight: 64, textAlignVertical: "top" },
            ]}
          />
          <Pressable
            onPress={saveLog}
            disabled={busy}
            style={[styles.saveBtn, { backgroundColor: colors.primary, opacity: busy ? 0.6 : 1 }]}
          >
            <Text style={styles.saveBtnText}>Save log</Text>
          </Pressable>
        </View>
      )}

      {/* Callback reminder */}
      {e164 && (
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Text style={[styles.cardKicker, { color: colors.mutedForeground }]}>CALL-BACK REMINDER</Text>
          {pendingCallback?.callback_at ? (
            <View style={styles.cbActive}>
              <Feather name="bell" size={14} color={colors.primary} />
              <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium" }}>
                {" "}Reminder {relative(pendingCallback.callback_at)}
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
                <Text style={{ color: "#ef4444", fontFamily: "SpaceGrotesk_500Medium" }}>Clear</Text>
              </Pressable>
            </View>
          ) : (
            <View style={styles.chips}>
              {CALLBACK_PRESETS.map((p) => (
                <Pressable
                  key={p.label}
                  onPress={() => scheduleCallback(p.ms)}
                  disabled={busy}
                  style={[styles.chip, { backgroundColor: colors.muted, borderColor: colors.border }]}
                >
                  <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 }}>
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
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Text style={[styles.cardKicker, { color: colors.mutedForeground }]}>RECENT ACTIVITY</Text>
          {lookup!.activity.map((a) => (
            <View key={a.id} style={[styles.activityRow, { borderTopColor: colors.border }]}>
              <View style={{ flex: 1 }}>
                <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium", textTransform: "capitalize" }}>
                  {a.outcome ? a.outcome.replace(/_/g, " ") : "Lookup"}
                  {a.tag ? `  ·  ${a.tag}` : ""}
                </Text>
                {!!a.note && (
                  <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }}>{a.note}</Text>
                )}
              </View>
              <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>{a.at_human}</Text>
            </View>
          ))}
        </View>
      )}
    </ScrollView>
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
        { backgroundColor: colors.card, borderColor: colors.border, opacity: pressed ? 0.7 : 1 },
      ]}
    >
      <Feather name={icon} size={18} color={tint} />
      <Text style={[styles.actionLabel, { color: colors.foreground }]}>{label}</Text>
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
      <Feather name={icon} size={13} color={active ? color : colors.mutedForeground} />
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
  header: { flexDirection: "row", alignItems: "center", gap: 14, marginBottom: 18 },
  avatar: { width: 60, height: 60, borderRadius: 30, alignItems: "center", justifyContent: "center" },
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
    borderWidth: StyleSheet.hairlineWidth,
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
  bioBtn: { marginTop: 12, paddingVertical: 10, borderRadius: 10, alignItems: "center" },
  bioBtnText: { color: "#fff", fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 10 },
  chip: { paddingHorizontal: 12, paddingVertical: 7, borderRadius: 999, borderWidth: StyleSheet.hairlineWidth },
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
});
