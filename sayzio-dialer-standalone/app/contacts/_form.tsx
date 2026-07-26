import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useState } from "react";
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { ChannelActions } from "@/components/ChannelActions";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  type Contact,
  type ContactPayload,
  clearContactFollowUp,
  createContact,
  deleteContact,
  listContactCalls,
  setContactFollowUp,
  smsBiolinkToContact,
  updateContact,
} from "@/lib/api/contacts";

const FOLLOW_UP_PRESETS: { label: string; ms: number }[] = [
  { label: "In 1 hour", ms: 60 * 60 * 1000 },
  { label: "Tomorrow", ms: 24 * 60 * 60 * 1000 },
  { label: "In 3 days", ms: 3 * 24 * 60 * 60 * 1000 },
  { label: "Next week", ms: 7 * 24 * 60 * 60 * 1000 },
];

function relativeFollowUp(at: string | null): string {
  if (!at) return "";
  const ms = new Date(at).getTime();
  if (Number.isNaN(ms)) return "";
  const diff = ms - Date.now();
  const abs = Math.abs(diff);
  const past = diff < 0;
  const mins = Math.round(abs / 60000);
  if (mins < 1) return "now";
  if (mins < 60) return `${past ? "" : "in "}${mins}m${past ? " ago" : ""}`;
  const hrs = Math.round(mins / 60);
  if (hrs < 24) return `${past ? "" : "in "}${hrs}h${past ? " ago" : ""}`;
  const days = Math.round(hrs / 24);
  return `${past ? "" : "in "}${days}d${past ? " ago" : ""}`;
}

export default function ContactForm({
  mode,
  contact,
  onSuccess,
  initialName,
  initialPhone,
}: {
  mode: "create" | "edit";
  contact?: Contact;
  onSuccess: () => void;
  /** Prefill (create mode only) — e.g. "Add to contacts" from a recents row. */
  initialName?: string | null;
  initialPhone?: string | null;
}) {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const [given, setGiven] = useState(
    contact?.given_name ?? (mode === "create" ? initialName?.trim() || "" : ""),
  );
  const [family, setFamily] = useState(contact?.family_name ?? "");
  const [contactType, setContactType] = useState<"personal" | "brand">(
    contact?.contact_type === "brand" ? "brand" : "personal",
  );
  const [org, setOrg] = useState(contact?.organization ?? "");
  const [job, setJob] = useState(contact?.job_title ?? "");
  const [notes, setNotes] = useState(contact?.notes ?? "");
  const [emails, setEmails] = useState<{ value: string; label: string | null }[]>(
    contact?.emails.map((e) => ({ value: e.value, label: e.label })) ?? [],
  );
  const [phones, setPhones] = useState<{ value: string; label: string | null }[]>(
    contact?.phones.map((p) => ({ value: p.value, label: p.label })) ??
      (mode === "create" && initialPhone?.trim()
        ? [{ value: initialPhone.trim(), label: null }]
        : []),
  );

  const buildPayload = (): ContactPayload => ({
    display_name: [given, family].filter(Boolean).join(" ") || null,
    given_name: given || null,
    family_name: family || null,
    organization: org || null,
    job_title: job || null,
    notes: notes || null,
    contact_type: contactType,
    emails: emails.filter((e) => e.value.trim()).map((e, i) => ({
      ...e,
      is_primary: i === 0,
    })),
    phones: phones.filter((p) => p.value.trim()).map((p, i) => ({
      ...p,
      is_primary: i === 0,
    })),
  });

  const save = useMutation({
    mutationFn: () =>
      mode === "create"
        ? createContact(buildPayload())
        : updateContact(contact!.id, buildPayload()),
    onSuccess,
    onError: (e: any) => Alert.alert("Failed", e?.message ?? "Try again"),
  });

  const remove = useMutation({
    mutationFn: () => deleteContact(contact!.id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["contacts"] });
      router.back();
    },
  });

  const smsBiolink = useMutation({
    mutationFn: () => smsBiolinkToContact(contact!.id),
    onSuccess: (r) =>
      Alert.alert("Sent", `Your Link in Bio was texted to ${r.to}.`),
    onError: (e: any) =>
      Alert.alert("Couldn't text", e?.message ?? "Try again"),
  });

  const [followUpNote, setFollowUpNote] = useState(contact?.follow_up_note ?? "");
  const [editingFollowUp, setEditingFollowUp] = useState(false);
  const invalidateContact = () => {
    qc.invalidateQueries({ queryKey: ["contact", contact?.id] });
    qc.invalidateQueries({ queryKey: ["contacts"] });
  };
  const deviceTimeZone = (() => {
    try {
      return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
    } catch {
      return null;
    }
  })();
  const scheduleFollowUp = useMutation({
    mutationFn: (ms: number) =>
      setContactFollowUp(
        contact!.id,
        new Date(Date.now() + ms).toISOString(),
        followUpNote.trim() || null,
        deviceTimeZone,
      ),
    onSuccess: () => {
      invalidateContact();
      setEditingFollowUp(false);
      Alert.alert("Reminder set", "We'll remind you to follow up.");
    },
    onError: (e: any) =>
      Alert.alert("Error", e?.message ?? "Could not set the reminder."),
  });
  const clearFollowUp = useMutation({
    mutationFn: () => clearContactFollowUp(contact!.id),
    onSuccess: () => {
      invalidateContact();
      setEditingFollowUp(false);
    },
    onError: () => Alert.alert("Error", "Could not clear the reminder."),
  });

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.background }}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
    >
      <Stack.Screen
        options={{
          title: mode === "create" ? "New contact" : "Edit contact",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
          headerRight: () =>
            mode === "edit" ? (
              <Pressable
                onPress={() =>
                  Alert.alert("Delete contact?", "This cannot be undone.", [
                    { text: "Cancel", style: "cancel" },
                    {
                      text: "Delete",
                      style: "destructive",
                      onPress: () => remove.mutate(),
                    },
                  ])
                }
                style={{ paddingRight: 12 }}
                hitSlop={8}
              >
                <Feather name="trash-2" size={18} color={colors.destructive} />
              </Pressable>
            ) : null,
        }}
      />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 14 }}>
        <View style={{ flexDirection: "row", gap: 12 }}>
          <View style={{ flex: 1 }}>
            <TextField label="First name" value={given} onChangeText={setGiven} />
          </View>
          <View style={{ flex: 1 }}>
            <TextField label="Last name" value={family} onChangeText={setFamily} />
          </View>
        </View>
        <TextField label="Organization" value={org} onChangeText={setOrg} />
        <TextField label="Job title" value={job} onChangeText={setJob} />

        <Text style={[styles.section, { color: colors.mutedForeground }]}>
          Contact type
        </Text>
        <View style={{ flexDirection: "row", gap: 8 }}>
          {(
            [
              { key: "personal", label: "Personal", icon: "user" },
              { key: "brand", label: "Brand", icon: "briefcase" },
            ] as const
          ).map((t) => {
            const on = contactType === t.key;
            return (
              <Pressable
                key={t.key}
                onPress={() => setContactType(t.key)}
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 6,
                  paddingVertical: 8,
                  paddingHorizontal: 14,
                  borderRadius: 999,
                  borderWidth: 1,
                  borderColor: on ? colors.primary : colors.border,
                  backgroundColor: on ? colors.primary + "14" : colors.card,
                }}
              >
                <Feather
                  name={t.icon}
                  size={14}
                  color={on ? colors.primary : colors.mutedForeground}
                />
                <Text
                  style={{
                    color: on ? colors.primary : colors.mutedForeground,
                    fontFamily: "SpaceGrotesk_500Medium",
                    fontSize: 13,
                  }}
                >
                  {t.label}
                </Text>
              </Pressable>
            );
          })}
        </View>

        <Text style={[styles.section, { color: colors.mutedForeground }]}>
          Emails
        </Text>
        {emails.map((e, i) => (
          <Row
            key={`e${i}`}
            value={e.value}
            onChange={(v) =>
              setEmails(emails.map((x, j) => (j === i ? { ...x, value: v } : x)))
            }
            onRemove={() => setEmails(emails.filter((_, j) => j !== i))}
            keyboardType="email-address"
            placeholder="email@example.com"
          />
        ))}
        <AddBtn
          label="Add email"
          onPress={() => setEmails([...emails, { value: "", label: null }])}
        />

        <Text style={[styles.section, { color: colors.mutedForeground }]}>
          Phones
        </Text>
        {phones.map((p, i) => (
          <View key={`p${i}`} style={{ gap: 6 }}>
            <Row
              value={p.value}
              onChange={(v) =>
                setPhones(phones.map((x, j) => (j === i ? { ...x, value: v } : x)))
              }
              onRemove={() => setPhones(phones.filter((_, j) => j !== i))}
              keyboardType="phone-pad"
              placeholder="+1 555 123 4567"
            />
            {p.value.trim() ? (
              <ChannelActions number={p.value} size="sm" align="flex-start" />
            ) : null}
          </View>
        ))}
        <AddBtn
          label="Add phone"
          onPress={() => setPhones([...phones, { value: "", label: null }])}
        />

        <TextField
          label="Notes"
          value={notes}
          onChangeText={setNotes}
          multiline
          numberOfLines={3}
        />

        {mode === "edit" && contact ? (
          <View
            style={{
              borderWidth: 1,
              borderColor: colors.border,
              backgroundColor: colors.card,
              borderRadius: colors.radius,
              padding: 14,
              gap: 10,
            }}
          >
            <Text style={[styles.section, { color: colors.mutedForeground, marginTop: 0 }]}>
              Follow-up reminder
            </Text>
            {contact.follow_up_at && !editingFollowUp ? (
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Feather name="bell" size={14} color={colors.primary} />
                <View style={{ flex: 1 }}>
                  <Text
                    style={{
                      color: colors.foreground,
                      fontFamily: "SpaceGrotesk_500Medium",
                    }}
                  >
                    Follow up {relativeFollowUp(contact.follow_up_at)}
                  </Text>
                  {contact.follow_up_note ? (
                    <Text
                      style={{
                        color: colors.mutedForeground,
                        fontFamily: "SpaceGrotesk_400Regular",
                        fontSize: 12,
                        marginTop: 2,
                      }}
                    >
                      {contact.follow_up_note}
                    </Text>
                  ) : null}
                </View>
                <Pressable
                  onPress={() => {
                    setFollowUpNote(contact.follow_up_note ?? "");
                    setEditingFollowUp(true);
                  }}
                >
                  <Text
                    style={{
                      color: colors.primary,
                      fontFamily: "SpaceGrotesk_500Medium",
                    }}
                  >
                    Edit
                  </Text>
                </Pressable>
                <Pressable
                  onPress={() => clearFollowUp.mutate()}
                  disabled={clearFollowUp.isPending}
                >
                  <Text
                    style={{
                      color: colors.destructive,
                      fontFamily: "SpaceGrotesk_500Medium",
                    }}
                  >
                    Clear
                  </Text>
                </Pressable>
              </View>
            ) : (
              <>
                <TextField
                  label="Note (optional)"
                  value={followUpNote}
                  onChangeText={setFollowUpNote}
                  placeholder="What to follow up about…"
                />
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
                  {FOLLOW_UP_PRESETS.map((p) => (
                    <Pressable
                      key={p.label}
                      onPress={() => scheduleFollowUp.mutate(p.ms)}
                      disabled={scheduleFollowUp.isPending}
                      style={{
                        paddingVertical: 8,
                        paddingHorizontal: 12,
                        borderRadius: 999,
                        backgroundColor: colors.muted,
                        borderWidth: 1,
                        borderColor: colors.border,
                        opacity: scheduleFollowUp.isPending ? 0.6 : 1,
                      }}
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
                {editingFollowUp ? (
                  <Pressable onPress={() => setEditingFollowUp(false)}>
                    <Text
                      style={{
                        color: colors.mutedForeground,
                        fontFamily: "SpaceGrotesk_500Medium",
                        fontSize: 12,
                      }}
                    >
                      Cancel
                    </Text>
                  </Pressable>
                ) : null}
              </>
            )}
          </View>
        ) : null}

        {mode === "edit" && contact ? <CallHistory contactId={contact.id} /> : null}

        <Button
          label={save.isPending ? "Saving…" : "Save contact"}
          onPress={() => save.mutate()}
          disabled={save.isPending}
        />
        {mode === "edit" && phones.some((p) => p.value.trim()) ? (
          <Pressable
            onPress={() =>
              Alert.alert(
                "Text my Link in Bio?",
                `Send your Sayzio page to ${phones[0].value} via SMS.`,
                [
                  { text: "Cancel", style: "cancel" },
                  { text: "Send", onPress: () => smsBiolink.mutate() },
                ],
              )
            }
            disabled={smsBiolink.isPending}
            style={{
              flexDirection: "row",
              alignItems: "center",
              justifyContent: "center",
              gap: 8,
              paddingVertical: 12,
              borderRadius: colors.radius,
              borderWidth: 1,
              borderColor: colors.border,
              backgroundColor: colors.card,
              opacity: smsBiolink.isPending ? 0.6 : 1,
            }}
          >
            <Feather name="send" size={16} color={colors.primary} />
            <Text
              style={{
                color: colors.primary,
                fontFamily: "SpaceGrotesk_600SemiBold",
                fontSize: 14,
              }}
            >
              {smsBiolink.isPending ? "Sending…" : "Text my Link in Bio"}
            </Text>
          </Pressable>
        ) : null}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function formatCallMoment(iso: string | null): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

/**
 * Structured "Call history" timeline (Dialer caller-ID drains). Rendered
 * only in edit mode and hidden entirely while there are no logged calls,
 * so fresh contacts don't show an empty section.
 */
function CallHistory({ contactId }: { contactId: number }) {
  const colors = useColors();
  const q = useQuery({
    queryKey: ["contact-calls", contactId],
    queryFn: () => listContactCalls(contactId),
  });
  const calls = q.data ?? [];
  if (!calls.length) return null;

  return (
    <View
      style={{
        borderWidth: 1,
        borderColor: colors.border,
        backgroundColor: colors.card,
        borderRadius: colors.radius,
        padding: 14,
        gap: 0,
      }}
    >
      <Text
        style={[
          styles.section,
          { color: colors.mutedForeground, marginTop: 0, marginBottom: 10 },
        ]}
      >
        Call history
      </Text>
      {calls.map((call, i) => (
        <View
          key={call.id}
          style={{ flexDirection: "row", gap: 12, alignItems: "stretch" }}
        >
          {/* Timeline rail: icon dot + connecting line */}
          <View style={{ alignItems: "center", width: 28 }}>
            <View
              style={{
                width: 28,
                height: 28,
                borderRadius: 999,
                backgroundColor: colors.muted,
                borderWidth: 1,
                borderColor: colors.border,
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Feather
                name={
                  call.direction === "outgoing"
                    ? "phone-outgoing"
                    : call.direction === "missed"
                      ? "phone-missed"
                      : "phone-incoming"
                }
                size={13}
                color={
                  call.direction === "missed"
                    ? colors.destructive
                    : colors.primary
                }
              />
            </View>
            {i < calls.length - 1 ? (
              <View
                style={{ width: 1, flex: 1, backgroundColor: colors.border }}
              />
            ) : null}
          </View>
          <View style={{ flex: 1, paddingBottom: i < calls.length - 1 ? 14 : 0 }}>
            <Text
              style={{
                color: colors.foreground,
                fontFamily: "SpaceGrotesk_500Medium",
                fontSize: 14,
              }}
            >
              {call.direction === "outgoing"
                ? "Call placed"
                : call.direction === "missed"
                  ? "Call missed"
                  : "Call received"}
            </Text>
            <Text
              style={{
                color: colors.mutedForeground,
                fontFamily: "SpaceGrotesk_400Regular",
                fontSize: 12,
                marginTop: 2,
              }}
            >
              {call.number}
              {formatCallMoment(call.occurred_at)
                ? ` · ${formatCallMoment(call.occurred_at)}`
                : ""}
            </Text>
          </View>
        </View>
      ))}
    </View>
  );
}

function Row({
  value,
  onChange,
  onRemove,
  keyboardType,
  placeholder,
}: {
  value: string;
  onChange: (v: string) => void;
  onRemove: () => void;
  keyboardType: "email-address" | "phone-pad";
  placeholder: string;
}) {
  const colors = useColors();
  return (
    <View style={{ flexDirection: "row", gap: 8, alignItems: "flex-end" }}>
      <View style={{ flex: 1 }}>
        <TextField
          value={value}
          onChangeText={onChange}
          keyboardType={keyboardType}
          autoCapitalize="none"
          placeholder={placeholder}
        />
      </View>
      <Pressable
        onPress={onRemove}
        style={{
          width: 44,
          height: 44,
          borderRadius: 999,
          backgroundColor: colors.card,
          borderWidth: 1,
          borderColor: colors.border,
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        <Feather name="x" size={16} color={colors.destructive} />
      </Pressable>
    </View>
  );
}

function AddBtn({ label, onPress }: { label: string; onPress: () => void }) {
  const colors = useColors();
  return (
    <Pressable
      onPress={onPress}
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 6,
        alignSelf: "flex-start",
        paddingVertical: 6,
      }}
    >
      <Feather name="plus" size={16} color={colors.primary} />
      <Text
        style={{
          color: colors.primary,
          fontFamily: "SpaceGrotesk_600SemiBold",
          fontSize: 13,
        }}
      >
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  section: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.6,
    textTransform: "uppercase",
    marginTop: 8,
  },
});
