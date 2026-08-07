import { Stack, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  type BroadcastAudience,
  type BroadcastOverview,
  getEventBroadcasts,
  sendEventBroadcast,
} from "@/lib/api/events";
import { showAlert } from "@/lib/webAlert";
import { EventsModuleGate } from "@/components/EventsModuleGate";

const AUDIENCES: { value: BroadcastAudience; label: string }[] = [
  { value: "going", label: "Going" },
  { value: "waitlist", label: "Waitlist" },
  { value: "all_rsvps", label: "All RSVPs" },
  { value: "ticket_holders", label: "Ticket holders" },
];

function EventBroadcastScreenInner() {
  const { linkId } = useLocalSearchParams<{ linkId: string }>();
  const colors = useColors();
  const id = Number(linkId);

  const [overview, setOverview] = useState<BroadcastOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [audience, setAudience] = useState<BroadcastAudience>("all_rsvps");
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [sending, setSending] = useState(false);

  const load = useCallback(async () => {
    const data = await getEventBroadcasts(id);
    setOverview(data);
  }, [id]);

  useEffect(() => {
    if (!id) return;
    load().finally(() => setLoading(false));
  }, [id, load]);

  const count = overview?.counts?.[audience] ?? 0;

  const send = useCallback(() => {
    if (!subject.trim() || !message.trim()) {
      showAlert("Missing info", "Enter a subject and a message.");
      return;
    }
    if (count === 0) {
      showAlert("No recipients", "No guests match that audience.");
      return;
    }
    showAlert(
      "Send this message?",
      `This emails ${count} guest(s) in the "${
        AUDIENCES.find((a) => a.value === audience)?.label ?? audience
      }" audience. This can't be undone.`,
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Send",
          onPress: async () => {
            setSending(true);
            try {
              await sendEventBroadcast(id, {
                audience,
                subject: subject.trim(),
                message: message.trim(),
              });
              setSubject("");
              setMessage("");
              await load();
              showAlert("Sent", `Message sent to ${count} guest(s).`);
            } catch (err) {
              showAlert(
                "Could not send",
                (err as Error)?.message ?? "Try again.",
              );
            } finally {
              setSending(false);
            }
          },
        },
      ],
    );
  }, [id, audience, subject, message, count, load]);

  if (loading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <ScrollView
      style={{ backgroundColor: colors.background }}
      contentContainerStyle={styles.wrap}
    >
      <Stack.Screen options={{ title: "Message guests" }} />

      <Text style={[styles.label, { color: colors.mutedForeground }]}>
        Audience
      </Text>
      <View style={styles.audienceRow}>
        {AUDIENCES.map((a) => {
          const active = a.value === audience;
          return (
            <Pressable
              key={a.value}
              onPress={() => setAudience(a.value)}
              style={[
                styles.chip,
                {
                  backgroundColor: active ? colors.primary : colors.card,
                  borderColor: active ? colors.primary : colors.border,
                },
              ]}
            >
              <Text
                style={{
                  color: active ? colors.primaryForeground : colors.foreground,
                  fontWeight: "600",
                  fontSize: 13,
                }}
              >
                {a.label} ({overview?.counts?.[a.value] ?? 0})
              </Text>
            </Pressable>
          );
        })}
      </View>
      <Text style={{ color: colors.mutedForeground, fontSize: 12, marginBottom: 12 }}>
        {count} recipient(s) will receive this message.
      </Text>

      <Text style={[styles.label, { color: colors.mutedForeground }]}>Subject</Text>
      <TextInput
        value={subject}
        onChangeText={setSubject}
        placeholder="e.g. Venue has moved"
        placeholderTextColor={colors.mutedForeground}
        maxLength={255}
        style={[styles.input, { borderColor: colors.border, color: colors.foreground }]}
      />

      <Text style={[styles.label, { color: colors.mutedForeground }]}>Message</Text>
      <TextInput
        value={message}
        onChangeText={setMessage}
        placeholder="Write your update to guests…"
        placeholderTextColor={colors.mutedForeground}
        multiline
        maxLength={5000}
        style={[
          styles.input,
          styles.textarea,
          { borderColor: colors.border, color: colors.foreground },
        ]}
      />

      <Pressable
        onPress={send}
        disabled={sending || count === 0}
        style={[
          styles.sendBtn,
          { backgroundColor: colors.primary, opacity: sending || count === 0 ? 0.5 : 1 },
        ]}
      >
        <Text style={{ color: colors.primaryForeground, fontWeight: "700" }}>
          {sending ? "Sending…" : `Send to ${count} guest(s)`}
        </Text>
      </Pressable>

      {overview && overview.broadcasts.length > 0 ? (
        <>
          <Text style={[styles.section, { color: colors.foreground }]}>
            Past broadcasts
          </Text>
          {overview.broadcasts.map((b) => (
            <View
              key={b.id}
              style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}
            >
              <Text style={{ color: colors.foreground, fontWeight: "600" }}>
                {b.subject}
              </Text>
              <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }}>
                {b.audience_label} · {b.recipients_count} recipient(s)
                {b.created_at ? ` · ${new Date(b.created_at).toLocaleDateString()}` : ""}
              </Text>
            </View>
          ))}
        </>
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  wrap: { padding: 20, paddingBottom: 60, gap: 6 },
  label: { fontSize: 12, fontWeight: "700", marginTop: 10, marginBottom: 6 },
  audienceRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    borderWidth: 1,
    borderRadius: 20,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  input: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontSize: 15,
    marginBottom: 4,
  },
  textarea: { height: 140, textAlignVertical: "top" },
  sendBtn: {
    height: 50,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 10,
  },
  section: { fontSize: 16, fontWeight: "700", marginTop: 24, marginBottom: 4 },
  card: { borderWidth: 1, borderRadius: 14, padding: 12, marginTop: 8 },
});

// Task #6729 — platform-wide Events module gate: shows a graceful
// "not available" state (instead of API 404 errors) when events are off.
export default function EventBroadcastScreen() {
  return (
    <EventsModuleGate>
      <EventBroadcastScreenInner />
    </EventsModuleGate>
  );
}
