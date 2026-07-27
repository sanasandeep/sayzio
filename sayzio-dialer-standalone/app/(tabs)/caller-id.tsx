import Feather from "@expo/vector-icons/Feather";
import * as Clipboard from "expo-clipboard";
import { useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  AppState,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";
import QRCode from "react-native-qrcode-svg";

import { Button } from "@/components/Button";
import { ChannelActions } from "@/components/ChannelActions";
import { EmptyState } from "@/components/EmptyState";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  dismissUnknownCall,
  dismissUnknownCallsForNumber,
  flushPendingSpamReports,
  getBrowserCallMirrorEnabled,
  getCallerIdStatus,
  getUnknownCalls,
  openOverlaySettings,
  requestCallScreeningRole,
  setBrowserCallMirrorEnabled,
  setCallerIdEnabled,
  showTestAlert,
  syncCallerDirectory,
  type CallerIdStatus,
  type UnknownCall,
} from "@/lib/callerId";
import {
  type CallerIdProfile,
  listCallerIdProfiles,
  setPrimaryCallerIdProfile,
} from "@/lib/api/dialer";

const E164 = /^\+[1-9]\d{6,14}$/;

/** Normalize loose user input toward E.164 ("+" + digits). */
function normalize(raw: string): string {
  const trimmed = raw.trim();
  const hasPlus = trimmed.startsWith("+");
  const digits = trimmed.replace(/[^\d]/g, "");
  if (!digits) return "";
  return hasPlus ? `+${digits}` : `+${digits}`;
}

/**
 * Truecaller-style incoming-call alert setup (Android APK builds only).
 * Walks the user through the two system grants the floating card needs:
 * "Display over other apps" and the caller ID & spam app role. Degrades
 * gracefully — the rest of the Caller ID tab keeps working if declined.
 */
function LiveCallerIdCard() {
  const colors = useColors();
  const [status, setStatus] = useState<CallerIdStatus>(() =>
    getCallerIdStatus(),
  );

  const refresh = useCallback(() => setStatus(getCallerIdStatus()), []);

  // Permission grants happen in system settings / system dialogs — re-check
  // whenever the app comes back to the foreground.
  useEffect(() => {
    const sub = AppState.addEventListener("change", (s) => {
      if (s === "active") refresh();
    });
    return () => sub.remove();
  }, [refresh]);

  // Keep the native lookup directory warm while the user is here, and push
  // any overlay "Report spam" taps queued while the app was dead.
  useEffect(() => {
    if (status.enabled) {
      void flushPendingSpamReports();
      void syncCallerDirectory();
    }
  }, [status.enabled]);

  if (!status.supported) return null;

  const toggle = (next: boolean) => {
    setCallerIdEnabled(next);
    refresh();
    if (next) void syncCallerDirectory({ force: true });
  };

  const grantOverlay = () => {
    openOverlaySettings();
  };

  const grantRole = async () => {
    await requestCallScreeningRole();
    refresh();
  };

  const steps: {
    key: string;
    done: boolean;
    title: string;
    body: string;
    action?: () => void;
    actionLabel?: string;
  }[] = [
    {
      key: "overlay",
      done: status.overlayGranted,
      title: "Display over other apps",
      body: "Lets the caller card float over whatever you're doing — even the lock screen.",
      action: grantOverlay,
      actionLabel: "Open settings",
    },
    {
      key: "role",
      done: status.roleHeld,
      title: "Caller ID & spam app",
      body: "Android only tells caller-ID apps about ringing calls. Zio Dialer never blocks or silences anything.",
      action: () => void grantRole(),
      actionLabel: "Set Zio Dialer",
    },
  ];

  return (
    <View
      style={[
        styles.card,
        { backgroundColor: colors.card, borderColor: colors.border },
      ]}
    >
      <View style={styles.cardHeader}>
        <View style={{ flex: 1, paddingRight: 12 }}>
          <Text style={[styles.cardTitle, { color: colors.foreground }]}>
            Live caller ID alerts
          </Text>
          <Text style={[styles.cardSub, { color: colors.mutedForeground }]}>
            When your phone rings, a floating card shows who's calling —
            matched from your contacts and Sayzio data, with your last call
            context.
          </Text>
        </View>
        <Switch
          value={status.enabled}
          onValueChange={toggle}
          trackColor={{ true: colors.primary }}
        />
      </View>

      {status.enabled ? (
        <>
          {steps.map((step) => (
            <View key={step.key} style={styles.stepRow}>
              <Feather
                name={step.done ? "check-circle" : "circle"}
                size={18}
                color={step.done ? "#16a34a" : colors.mutedForeground}
                style={{ marginTop: 2 }}
              />
              <View style={{ flex: 1 }}>
                <Text style={[styles.stepTitle, { color: colors.foreground }]}>
                  {step.title}
                </Text>
                <Text
                  style={[styles.stepBody, { color: colors.mutedForeground }]}
                >
                  {step.body}
                </Text>
                {!step.done && step.action ? (
                  <Pressable onPress={step.action} style={{ marginTop: 6 }}>
                    <Text style={[styles.stepAction, { color: colors.primary }]}>
                      {step.actionLabel}
                    </Text>
                  </Pressable>
                ) : null}
              </View>
            </View>
          ))}

          {status.active ? (
            <View style={styles.readyRow}>
              <Text style={[styles.readyText, { color: "#16a34a" }]}>
                You're all set — alerts will appear when calls ring.
              </Text>
              <Pressable onPress={() => showTestAlert("+15551234567")}>
                <Text style={[styles.stepAction, { color: colors.primary }]}>
                  See a preview
                </Text>
              </Pressable>
            </View>
          ) : (
            <Text
              style={[styles.declinedNote, { color: colors.mutedForeground }]}
            >
              Alerts stay off until both permissions are granted. You can
              still look up any number manually below.
            </Text>
          )}
        </>
      ) : null}
    </View>
  );
}

/**
 * "Show calls in Zio Browser" toggle — when on, incoming calls the
 * caller-ID service sees are mirrored (best-effort) to the Zio Browser
 * Dialer pane on desktop. Off by default; purely additive telemetry the
 * user controls.
 */
function BrowserMirrorCard() {
  const colors = useColors();
  const [enabled, setEnabled] = useState(false);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    let mounted = true;
    void getBrowserCallMirrorEnabled().then((v) => {
      if (mounted) {
        setEnabled(v);
        setLoaded(true);
      }
    });
    return () => {
      mounted = false;
    };
  }, []);

  const toggle = (next: boolean) => {
    setEnabled(next);
    void setBrowserCallMirrorEnabled(next);
  };

  return (
    <View
      style={[
        styles.card,
        { backgroundColor: colors.card, borderColor: colors.border },
      ]}
    >
      <View style={styles.cardHeader}>
        <View style={{ flex: 1, paddingRight: 12 }}>
          <Text style={[styles.cardTitle, { color: colors.foreground }]}>
            Show calls in Zio Browser
          </Text>
          <Text style={[styles.cardSub, { color: colors.mutedForeground }]}>
            Mirror incoming calls to the Dialer pane in Zio Browser on your
            computer, so you see who's calling without picking up your phone.
            Works alongside live caller ID alerts.
          </Text>
        </View>
        <Switch
          value={enabled}
          onValueChange={toggle}
          disabled={!loaded}
          trackColor={{ true: colors.primary }}
        />
      </View>
    </View>
  );
}

/**
 * "Call as" identity picker — the Sayzio profiles (Personal + owned
 * workspaces/brands) the user can present as their outgoing caller ID,
 * plus a QR code and share/copy actions for their public profile link
 * so people they call can identify and follow them back.
 */
function CallerIdProfilesCard() {
  const colors = useColors();
  const [profiles, setProfiles] = useState<CallerIdProfile[]>([]);
  const [shareUrl, setShareUrl] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [copied, setCopied] = useState(false);

  const refresh = useCallback(async () => {
    try {
      const res = await listCallerIdProfiles();
      setProfiles(res.items);
      setShareUrl(res.share_url);
    } catch {
      setProfiles([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const pick = async (p: CallerIdProfile) => {
    if (p.is_primary || saving) return;
    setSaving(true);
    // Optimistic: mark the picked profile primary immediately.
    setProfiles((prev) =>
      prev.map((x) => ({ ...x, is_primary: x.workspace_id === p.workspace_id })),
    );
    try {
      await setPrimaryCallerIdProfile(p.workspace_id);
    } catch {
      void refresh();
    } finally {
      setSaving(false);
    }
  };

  const copy = async () => {
    if (!shareUrl) return;
    await Clipboard.setStringAsync(shareUrl);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const shareLink = () => {
    if (!shareUrl) return;
    void Share.share({ message: shareUrl });
  };

  if (!loading && profiles.length === 0) return null;

  return (
    <View
      style={[
        styles.card,
        { backgroundColor: colors.card, borderColor: colors.border },
      ]}
    >
      <Text style={[styles.cardTitle, { color: colors.foreground }]}>
        Your caller ID profile
      </Text>
      <Text style={[styles.cardSub, { color: colors.mutedForeground }]}>
        Pick which Sayzio profile people see when you call them — your
        personal profile or one of your brands.
      </Text>

      {loading ? (
        <ActivityIndicator color={colors.primary} />
      ) : (
        profiles.map((p) => {
          const key = p.workspace_id == null ? "personal" : `ws-${p.workspace_id}`;
          return (
            <Pressable
              key={key}
              onPress={() => void pick(p)}
              style={[
                styles.profileRow,
                {
                  borderColor: p.is_primary ? colors.primary : colors.border,
                  backgroundColor: p.is_primary
                    ? `${colors.primary}14`
                    : "transparent",
                },
              ]}
            >
              <View
                style={[
                  styles.profileAvatar,
                  { backgroundColor: colors.primary },
                ]}
              >
                {p.logo_url ? (
                  <Image
                    source={{ uri: p.logo_url }}
                    style={{ width: "100%", height: "100%" }}
                    resizeMode="cover"
                  />
                ) : (
                  <Feather
                    name={p.workspace_id == null ? "user" : "briefcase"}
                    size={16}
                    color="#fff"
                  />
                )}
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.stepTitle, { color: colors.foreground }]}>
                  {p.name || p.workspace_name || "Personal"}
                </Text>
                <Text style={[styles.stepBody, { color: colors.mutedForeground }]}>
                  {p.workspace_id == null
                    ? "Personal profile"
                    : p.tagline || p.workspace_name || "Brand workspace"}
                </Text>
              </View>
              <Feather
                name={p.is_primary ? "check-circle" : "circle"}
                size={18}
                color={p.is_primary ? colors.primary : colors.mutedForeground}
              />
            </Pressable>
          );
        })
      )}

      {shareUrl ? (
        <View style={{ alignItems: "center", gap: 10, marginTop: 4 }}>
          <View style={styles.qrWrap}>
            <QRCode value={shareUrl} size={140} backgroundColor="#ffffff" />
          </View>
          <Text
            style={[styles.stepBody, { color: colors.mutedForeground }]}
            numberOfLines={1}
          >
            {shareUrl}
          </Text>
          <View style={{ flexDirection: "row", gap: 18 }}>
            <Pressable onPress={shareLink} style={styles.shareBtn}>
              <Feather name="share-2" size={15} color={colors.primary} />
              <Text style={[styles.stepAction, { color: colors.primary }]}>
                Share link
              </Text>
            </Pressable>
            <Pressable onPress={() => void copy()} style={styles.shareBtn}>
              <Feather
                name={copied ? "check" : "copy"}
                size={15}
                color={copied ? "#16a34a" : colors.primary}
              />
              <Text
                style={[
                  styles.stepAction,
                  { color: copied ? "#16a34a" : colors.primary },
                ]}
              >
                {copied ? "Copied" : "Copy link"}
              </Text>
            </Pressable>
          </View>
        </View>
      ) : null}
    </View>
  );
}

function formatUnknownCallMoment(ts: number): string {
  const d = new Date(ts);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

/**
 * Calls from numbers not in contacts, queued natively by the call-screening
 * service while the app was dead and drained on foreground. Each row offers
 * "Save as contact" (prefilled new-contact form) or dismiss, so no missed
 * call is ever lost. Android-only — hidden when empty.
 */
function RecentUnknownCallersCard() {
  const colors = useColors();
  const router = useRouter();
  const [calls, setCalls] = useState<UnknownCall[]>([]);

  const refresh = useCallback(() => {
    void getUnknownCalls().then(setCalls);
  }, []);

  // The drain runs on foreground (useContactAutoSync) — re-read shortly
  // after each foreground so freshly drained calls appear.
  useEffect(() => {
    refresh();
    const sub = AppState.addEventListener("change", (s) => {
      if (s === "active") setTimeout(refresh, 1500);
    });
    return () => sub.remove();
  }, [refresh]);

  if (calls.length === 0) return null;

  const save = (call: UnknownCall) => {
    // Clear every queued ring from this number — it's about to be saved.
    void dismissUnknownCallsForNumber(call.number).then(refresh);
    router.push({ pathname: "/contacts/new", params: { phone: call.number } });
  };

  const dismiss = (call: UnknownCall) => {
    void dismissUnknownCall(call).then(refresh);
  };

  return (
    <View
      style={[
        styles.card,
        { backgroundColor: colors.card, borderColor: colors.border },
      ]}
    >
      <Text style={[styles.cardTitle, { color: colors.foreground }]}>
        Calls from unknown numbers
      </Text>
      <Text style={[styles.cardSub, { color: colors.mutedForeground }]}>
        These callers aren't in your contacts yet. Save them so the next call
        shows who it is.
      </Text>
      {calls.map((call) => (
        <View key={`${call.number}-${call.ts}`} style={styles.unknownRow}>
          <View style={{ flex: 1 }}>
            <Text style={[styles.unknownNumber, { color: colors.foreground }]}>
              {call.number}
            </Text>
            <Text
              style={[styles.unknownMoment, { color: colors.mutedForeground }]}
            >
              {formatUnknownCallMoment(call.ts)}
            </Text>
          </View>
          <Pressable
            onPress={() => save(call)}
            style={styles.unknownAction}
            accessibilityLabel={`Save ${call.number} as contact`}
          >
            <Text style={[styles.stepAction, { color: colors.primary }]}>
              Save as contact
            </Text>
          </Pressable>
          <Pressable
            onPress={() => dismiss(call)}
            style={styles.unknownDismiss}
            accessibilityLabel={`Dismiss call from ${call.number}`}
            hitSlop={8}
          >
            <Feather name="x" size={16} color={colors.mutedForeground} />
          </Pressable>
        </View>
      ))}
    </View>
  );
}

export default function CallerIdScreen() {
  const colors = useColors();
  const router = useRouter();
  const [number, setNumber] = useState("");

  const normalized = normalize(number);
  const valid = E164.test(normalized);

  const lookup = () => {
    if (!valid) return;
    router.push({ pathname: "/dialer-profile", params: { number: normalized } });
  };

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.background }}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
    >
      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
      >
        <Text style={[styles.title, { color: colors.foreground }]}>
          Who's calling?
        </Text>
        <Text style={[styles.sub, { color: colors.mutedForeground }]}>
          Enter any phone number in international format to see who it belongs
          to, whether it's saved, spam or a Sayzio member — then log the call,
          add notes and set a follow-up.
        </Text>

        <CallerIdProfilesCard />

        <LiveCallerIdCard />

        <BrowserMirrorCard />

        <RecentUnknownCallersCard />

        <View style={styles.field}>
          <TextField
            label="Phone number"
            placeholder="+1 555 123 4567"
            keyboardType="phone-pad"
            autoCapitalize="none"
            value={number}
            onChangeText={setNumber}
            onSubmitEditing={lookup}
            returnKeyType="search"
          />
          {number.length > 0 && !valid ? (
            <Text style={[styles.hint, { color: colors.destructive }]}>
              Enter a full international number, e.g. +15551234567
            </Text>
          ) : normalized ? (
            <Text style={[styles.hint, { color: colors.mutedForeground }]}>
              Looking up {normalized}
            </Text>
          ) : null}
          {valid ? (
            <View style={{ marginTop: 4 }}>
              <ChannelActions
                number={normalized}
                size="sm"
                align="flex-start"
              />
            </View>
          ) : null}
        </View>

        <Button label="Look up number" onPress={lookup} disabled={!valid} />

        <View style={styles.spacer} />
        <EmptyState
          icon="shield"
          title="Spam & block aware"
          body="Numbers you flag as spam or blocked are remembered across all your devices and shown here the next time they call."
        />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  content: { padding: 20, gap: 16 },
  title: { fontSize: 26, fontFamily: "SpaceGrotesk_700Bold" },
  sub: { fontSize: 15, lineHeight: 21, fontFamily: "SpaceGrotesk_400Regular" },
  field: { gap: 6 },
  hint: { fontSize: 13, fontFamily: "SpaceGrotesk_400Regular" },
  spacer: { height: 12 },
  card: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
    gap: 12,
  },
  cardHeader: { flexDirection: "row", alignItems: "flex-start" },
  cardTitle: { fontSize: 17, fontFamily: "SpaceGrotesk_700Bold" },
  cardSub: {
    fontSize: 13,
    lineHeight: 18,
    marginTop: 4,
    fontFamily: "SpaceGrotesk_400Regular",
  },
  stepRow: { flexDirection: "row", gap: 10 },
  stepTitle: { fontSize: 14, fontFamily: "SpaceGrotesk_500Medium" },
  stepBody: {
    fontSize: 12.5,
    lineHeight: 17,
    marginTop: 2,
    fontFamily: "SpaceGrotesk_400Regular",
  },
  stepAction: { fontSize: 13.5, fontFamily: "SpaceGrotesk_500Medium" },
  readyRow: { gap: 6 },
  readyText: { fontSize: 13, fontFamily: "SpaceGrotesk_500Medium" },
  declinedNote: {
    fontSize: 12.5,
    lineHeight: 17,
    fontFamily: "SpaceGrotesk_400Regular",
  },
  unknownRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  unknownNumber: { fontSize: 14.5, fontFamily: "SpaceGrotesk_500Medium" },
  unknownMoment: {
    fontSize: 12,
    marginTop: 1,
    fontFamily: "SpaceGrotesk_400Regular",
  },
  unknownAction: { paddingVertical: 4 },
  unknownDismiss: { padding: 4 },
  profileRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    borderWidth: 1,
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 12,
  },
  profileAvatar: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  qrWrap: {
    padding: 10,
    borderRadius: 12,
    backgroundColor: "#ffffff",
  },
  shareBtn: { flexDirection: "row", alignItems: "center", gap: 5, padding: 4 },
});
