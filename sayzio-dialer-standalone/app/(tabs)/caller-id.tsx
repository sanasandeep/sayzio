import Feather from "@expo/vector-icons/Feather";
import { useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  AppState,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { ChannelActions } from "@/components/ChannelActions";
import { EmptyState } from "@/components/EmptyState";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  getCallerIdStatus,
  openOverlaySettings,
  requestCallScreeningRole,
  setCallerIdEnabled,
  showTestAlert,
  syncCallerDirectory,
  type CallerIdStatus,
} from "@/lib/callerId";

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

  // Keep the native lookup directory warm while the user is here.
  useEffect(() => {
    if (status.enabled) void syncCallerDirectory();
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

        <LiveCallerIdCard />

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
});
