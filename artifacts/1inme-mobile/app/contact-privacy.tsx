import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  getContactPrivacy,
  updateContactPrivacy,
  type ContactPrivacyCandidate,
  type ContactPrivacyPrefs,
} from "@/lib/api/contactPrivacy";

type TriField = "share_phone" | "share_email" | "share_location" | "share_socials";

const FIELDS: { key: TriField; label: string; desc: string }[] = [
  {
    key: "share_phone",
    label: "Phone number",
    desc: "Your number, plus call / text / WhatsApp-by-number / FaceTime shortcuts.",
  },
  {
    key: "share_email",
    label: "Email address",
    desc: "Your email, when available on a lookup.",
  },
  {
    key: "share_location",
    label: "Exact location",
    desc: "Precise map location(s) you've shared on your biolink.",
  },
  {
    key: "share_socials",
    label: "Socials & other channels",
    desc: "Instagram, WhatsApp, Telegram and other links pulled from your biolink.",
  },
];

// Task #3497 — lets a creator control what strangers (anyone who hasn't
// already saved them as a contact) can see via caller-ID / search. Mirrors
// the web Settings > Contact Privacy tab; no forced default (tri-state).
export default function ContactPrivacyScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["contact-privacy"],
    queryFn: getContactPrivacy,
  });

  const [prefs, setPrefs] = useState<ContactPrivacyPrefs | null>(null);
  const [hidden, setHidden] = useState<Set<string>>(new Set());
  const [dirty, setDirty] = useState(false);

  useEffect(() => {
    if (!q.data) return;
    setPrefs(q.data.prefs);
    setHidden(
      new Set(
        [...q.data.candidates.socials, ...q.data.candidates.channels]
          .filter((c) => c.hidden)
          .map((c) => c.key),
      ),
    );
    setDirty(false);
  }, [q.data]);

  const save = useMutation({
    mutationFn: () =>
      updateContactPrivacy({
        ...(prefs ?? {}),
        hidden_channels: Array.from(hidden),
      }),
    onSuccess: (data) => {
      qc.setQueryData(["contact-privacy"], data);
      setDirty(false);
    },
  });

  const setField = (field: TriField, value: boolean | null) => {
    setPrefs((p) => (p ? { ...p, [field]: value } : p));
    setDirty(true);
  };

  const toggleHidden = (key: string) => {
    setHidden((s) => {
      const next = new Set(s);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
    setDirty(true);
  };

  const candidates: ContactPrivacyCandidate[] = q.data
    ? [...q.data.candidates.socials, ...q.data.candidates.channels]
    : [];

  return (
    <View style={[styles.root, { backgroundColor: colors.background }]}>
      <Stack.Screen
        options={{
          title: "Contact privacy",
          headerStyle: { backgroundColor: colors.background },
          headerTintColor: colors.text,
        }}
      />

      {q.isLoading || !prefs ? (
        <View style={styles.loading}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={styles.scroll}>
          <Text style={[styles.intro, { color: colors.mutedForeground }]}>
            By default, everything stays visible to strangers who look you up
            via caller-ID or search. People who've already saved you as a
            contact — and you yourself — always see everything, no matter
            what you choose here.
          </Text>

          {FIELDS.map((f) => (
            <View
              key={f.key}
              style={[
                styles.card,
                { backgroundColor: colors.card, borderColor: colors.border },
              ]}
            >
              <Text style={[styles.label, { color: colors.text }]}>{f.label}</Text>
              <Text style={[styles.desc, { color: colors.mutedForeground }]}>
                {f.desc}
              </Text>
              <TriToggle
                value={prefs[f.key]}
                onChange={(v) => setField(f.key, v)}
                colors={colors}
              />
            </View>
          ))}

          {candidates.length > 0 ? (
            <View
              style={[
                styles.card,
                { backgroundColor: colors.card, borderColor: colors.border },
              ]}
            >
              <Text style={[styles.label, { color: colors.text }]}>
                Un-share individual channels
              </Text>
              <Text style={[styles.desc, { color: colors.mutedForeground }]}>
                Hide specific socials/channels even when their category above
                is shown.
              </Text>
              {candidates.map((c) => (
                <View key={c.key} style={styles.channelRow}>
                  <Text
                    style={[styles.channelLabel, { color: colors.text }]}
                    numberOfLines={1}
                  >
                    {c.label ?? c.platform ?? c.type ?? "Channel"}
                  </Text>
                  <Switch
                    value={!hidden.has(c.key)}
                    onValueChange={() => toggleHidden(c.key)}
                    trackColor={{ false: colors.border, true: colors.primary }}
                    thumbColor="#fff"
                  />
                </View>
              ))}
            </View>
          ) : null}
        </ScrollView>
      )}

      <View
        style={[
          styles.bar,
          { backgroundColor: colors.card, borderTopColor: colors.border },
        ]}
      >
        <TouchableOpacity
          disabled={!dirty || save.isPending}
          onPress={() => save.mutate()}
          style={[
            styles.saveBtn,
            {
              backgroundColor:
                !dirty || save.isPending ? colors.border : colors.primary,
            },
          ]}
        >
          {save.isPending ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.saveText}>
              {dirty ? "Save preferences" : "Saved"}
            </Text>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
}

function TriToggle({
  value,
  onChange,
  colors,
}: {
  value: boolean | null;
  onChange: (v: boolean | null) => void;
  colors: ReturnType<typeof useColors>;
}) {
  const options: { value: boolean | null; label: string }[] = [
    { value: null, label: "Shown" },
    { value: true, label: "Always" },
    { value: false, label: "Hidden" },
  ];
  return (
    <View style={styles.triRow}>
      {options.map((opt) => {
        const active = value === opt.value;
        return (
          <TouchableOpacity
            key={String(opt.value)}
            onPress={() => onChange(opt.value)}
            style={[
              styles.triOption,
              {
                backgroundColor: active ? colors.primary : "transparent",
                borderColor: active ? colors.primary : colors.border,
              },
            ]}
          >
            <Text
              style={[
                styles.triOptionText,
                { color: active ? "#fff" : colors.mutedForeground },
              ]}
            >
              {opt.label}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  loading: { flex: 1, alignItems: "center", justifyContent: "center" },
  scroll: { padding: 16, paddingBottom: 120 },
  intro: { fontSize: 13, marginBottom: 16, lineHeight: 18 },
  card: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 14,
    marginBottom: 12,
  },
  label: { fontSize: 15, fontWeight: "600" },
  desc: { fontSize: 12, marginTop: 4, marginBottom: 10 },
  triRow: { flexDirection: "row", gap: 8 },
  triOption: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 10,
    paddingVertical: 8,
    alignItems: "center",
  },
  triOptionText: { fontSize: 12, fontWeight: "600" },
  channelRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingVertical: 6,
    gap: 8,
  },
  channelLabel: { fontSize: 13, flex: 1 },
  bar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    padding: 16,
    borderTopWidth: 1,
  },
  saveBtn: {
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 12,
    borderRadius: 12,
  },
  saveText: { color: "#fff", fontWeight: "700", fontSize: 14 },
});
