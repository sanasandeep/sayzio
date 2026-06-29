import { Feather } from "@expo/vector-icons";
import { useMutation, useQueryClient } from "@tanstack/react-query";
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
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  type Contact,
  type ContactPayload,
  createContact,
  deleteContact,
  smsBiolinkToContact,
  updateContact,
} from "@/lib/api/contacts";

export default function ContactForm({
  mode,
  contact,
  onSuccess,
}: {
  mode: "create" | "edit";
  contact?: Contact;
  onSuccess: () => void;
}) {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const [given, setGiven] = useState(contact?.given_name ?? "");
  const [family, setFamily] = useState(contact?.family_name ?? "");
  const [org, setOrg] = useState(contact?.organization ?? "");
  const [job, setJob] = useState(contact?.job_title ?? "");
  const [notes, setNotes] = useState(contact?.notes ?? "");
  const [emails, setEmails] = useState<{ value: string; label: string | null }[]>(
    contact?.emails.map((e) => ({ value: e.value, label: e.label })) ?? [],
  );
  const [phones, setPhones] = useState<{ value: string; label: string | null }[]>(
    contact?.phones.map((p) => ({ value: p.value, label: p.label })) ?? [],
  );

  const buildPayload = (): ContactPayload => ({
    display_name: [given, family].filter(Boolean).join(" ") || null,
    given_name: given || null,
    family_name: family || null,
    organization: org || null,
    job_title: job || null,
    notes: notes || null,
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
          <Row
            key={`p${i}`}
            value={p.value}
            onChange={(v) =>
              setPhones(phones.map((x, j) => (j === i ? { ...x, value: v } : x)))
            }
            onRemove={() => setPhones(phones.filter((_, j) => j !== i))}
            keyboardType="phone-pad"
            placeholder="+1 555 123 4567"
          />
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
