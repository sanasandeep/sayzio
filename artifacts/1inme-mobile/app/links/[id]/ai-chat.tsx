import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useFocusEffect, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { DictationMic } from "@/components/DictationMic";
import { TextField } from "@/components/TextField";
import { setVoiceSurface } from "@/components/VoiceAssistant";
import { useColors } from "@/hooks/useColors";
import {
  getAiChat,
  saveAiChat,
  type AiChatTheme,
} from "@/lib/api/aiChat";

const THEMES: AiChatTheme[] = ["auto", "light", "dark"];
const STARTER_SLOTS = 6;

export default function AiChatEditorScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useQuery({
    queryKey: ["ai-chat", id],
    queryFn: () => getAiChat(id),
    enabled: Number.isFinite(id),
  });

  const [name, setName] = useState("");
  const [personaId, setPersonaId] = useState<number | null>(null);
  const [greeting, setGreeting] = useState("");
  const [placeholder, setPlaceholder] = useState("");
  const [accent, setAccent] = useState("#7c3aed");
  const [theme, setTheme] = useState<AiChatTheme>("auto");
  const [showBranding, setShowBranding] = useState(true);
  const [groundInProfile, setGroundInProfile] = useState(true);
  const [starters, setStarters] = useState<string[]>(
    Array(STARTER_SLOTS).fill(""),
  );
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    const d = q.data?.ai_chat;
    if (!d) return;
    setName(d.name ?? "");
    setPersonaId(d.persona_id ?? q.data?.personas[0]?.id ?? null);
    setGreeting(d.config.greeting ?? "");
    setPlaceholder(d.config.placeholder ?? "Ask me anything…");
    setAccent(d.config.accent ?? "#7c3aed");
    setTheme(d.config.theme ?? "auto");
    setShowBranding(d.config.show_branding ?? true);
    setGroundInProfile(d.config.ground_in_profile ?? true);
    const filled = [...d.starters].slice(0, STARTER_SLOTS);
    while (filled.length < STARTER_SLOTS) filled.push("");
    setStarters(filled);
  }, [q.data]);

  const save = useMutation({
    mutationFn: () => {
      if (!name.trim()) throw new Error("Please enter a display name");
      if (!personaId) throw new Error("Please pick a persona");
      return saveAiChat(id, {
        name: name.trim(),
        persona_id: personaId,
        config: {
          greeting: greeting.trim() || null,
          placeholder: placeholder.trim() || "Ask me anything…",
          accent: accent.trim() || "#7c3aed",
          theme,
          show_branding: showBranding,
          ground_in_profile: groundInProfile,
        },
        starters: starters.map((s) => s.trim()).filter(Boolean),
      });
    },
    onSuccess: (data) => {
      qc.setQueryData(["ai-chat", id], data);
      qc.invalidateQueries({ queryKey: ["link", id] });
      qc.invalidateQueries({ queryKey: ["links"] });
      setSaved(true);
      setTimeout(() => setSaved(false), 2500);
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
        <Text style={{ color: colors.destructive }}>
          Couldn't load this AI chat page.
        </Text>
      </View>
    );
  }

  const d = q.data.ai_chat;
  const personas = q.data.personas;

  function setStarterAt(i: number, value: string) {
    setStarters((prev) => {
      const next = [...prev];
      next[i] = value;
      return next;
    });
  }

  // Append a dictated chunk to whichever field's setter is passed in,
  // mirroring the create form's per-field dictation factory.
  const dictateInto =
    (setter: React.Dispatch<React.SetStateAction<string>>) => (t: string) =>
      setter((v) => (v ? v.trim() + " " : "") + t);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "AI Chat" }} />
      <ScrollView contentContainerStyle={styles.body}>
        {!d.ai_enabled ? (
          <View
            style={[
              styles.banner,
              {
                backgroundColor: colors.primary + "14",
                borderColor: colors.primary + "44",
                borderRadius: colors.radius,
              },
            ]}
          >
            <Feather name="alert-triangle" size={16} color={colors.primary} />
            <Text style={[styles.bannerText, { color: colors.foreground }]}>
              AI features are currently turned off by the site administrator. You
              can still configure this page, but live answers won't run until an
              admin turns the AI engine back on.
            </Text>
          </View>
        ) : null}

        <Pressable
          onPress={() => Linking.openURL(d.public_url)}
          style={[
            styles.publicCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <View style={{ flex: 1 }}>
            <Text style={[styles.publicLabel, { color: colors.mutedForeground }]}>
              Visitors chat at
            </Text>
            <Text
              numberOfLines={1}
              style={[styles.publicUrl, { color: colors.primary }]}
            >
              {d.public_url}
            </Text>
          </View>
          <Feather name="external-link" size={18} color={colors.primary} />
        </Pressable>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Chat identity
          </Text>
          <TextField
            label="Display name"
            value={name}
            onChangeText={setName}
            maxLength={120}
            placeholder="Shown in the chat header"
            trailing={<DictationMic onText={dictateInto(setName)} />}
          />
          <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
            Persona (the brain)
          </Text>
          {personas.length === 0 ? (
            <Text style={[styles.hint, { color: colors.mutedForeground }]}>
              No personas yet — a default assistant was created for this page.
              Manage personas & knowledge on the web app.
            </Text>
          ) : (
            <View style={{ gap: 8 }}>
              {personas.map((p) => {
                const on = personaId === p.id;
                return (
                  <Pressable
                    key={p.id}
                    onPress={() => setPersonaId(p.id)}
                    style={[
                      styles.personaRow,
                      {
                        backgroundColor: on
                          ? colors.primary + "1c"
                          : colors.card,
                        borderColor: on ? colors.primary : colors.border,
                        borderRadius: colors.radius,
                      },
                    ]}
                  >
                    <Feather
                      name={on ? "check-circle" : "circle"}
                      size={18}
                      color={on ? colors.primary : colors.mutedForeground}
                    />
                    <Text
                      style={[styles.personaName, { color: colors.foreground }]}
                    >
                      {p.name}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          )}
          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            The persona supplies the system prompt, model, tone and knowledge.
          </Text>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Conversation
          </Text>
          <TextField
            label="Opening message"
            value={greeting}
            onChangeText={setGreeting}
            multiline
            numberOfLines={3}
            maxLength={1000}
            placeholder="Hi! Ask me anything about…"
            style={{ height: 88, textAlignVertical: "top", paddingTop: 12 }}
            trailing={<DictationMic onText={dictateInto(setGreeting)} />}
          />
          <TextField
            label="Input placeholder"
            value={placeholder}
            onChangeText={setPlaceholder}
            maxLength={120}
            placeholder="Ask me anything…"
            trailing={<DictationMic onText={dictateInto(setPlaceholder)} />}
          />
          <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
            Starter questions (up to 6)
          </Text>
          {starters.map((s, i) => (
            <TextField
              key={i}
              value={s}
              onChangeText={(v) => setStarterAt(i, v)}
              maxLength={200}
              placeholder={`Suggested question ${i + 1}`}
              trailing={
                <DictationMic
                  onText={(t) =>
                    setStarterAt(i, s ? s.trim() + " " + t : t)
                  }
                />
              }
            />
          ))}
          <RowSwitch
            label="Ground answers in this page's profile"
            hint="Let the assistant reference the page's title, bio and links."
            value={groundInProfile}
            onValueChange={setGroundInProfile}
          />
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Appearance
          </Text>
          <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
            Theme
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
            {THEMES.map((t) => {
              const on = theme === t;
              return (
                <Pressable
                  key={t}
                  onPress={() => setTheme(t)}
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
                    {t}
                  </Text>
                </Pressable>
              );
            })}
          </View>
          <View style={styles.accentRow}>
            <View
              style={[
                styles.swatch,
                { backgroundColor: isHex(accent) ? accent : "#7c3aed" },
              ]}
            />
            <View style={{ flex: 1 }}>
              <TextField
                label="Accent colour"
                value={accent}
                onChangeText={setAccent}
                autoCapitalize="none"
                autoCorrect={false}
                maxLength={32}
                placeholder="#7c3aed"
              />
            </View>
          </View>
          <RowSwitch
            label='Show "Powered by Sayzio"'
            value={showBranding}
            onValueChange={setShowBranding}
          />
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            This month
          </Text>
          <View
            style={[
              styles.statsCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Stat label="Turns used" value={d.usage.turns} colors={colors} />
            <Stat
              label="Free turns"
              value={d.usage.free_turns_per_month}
              colors={colors}
            />
            <Stat
              label="Monthly cap"
              value={d.usage.hard_cap_per_month}
              colors={colors}
              last
            />
          </View>
        </View>

        {save.error ? (
          <Text style={{ color: colors.destructive }}>
            {(save.error as Error).message}
          </Text>
        ) : null}
        {saved ? (
          <Text style={{ color: colors.primary }}>AI chat saved.</Text>
        ) : null}

        <Button
          label="Save AI chat"
          onPress={() => save.mutate()}
          loading={save.isPending}
        />
      </ScrollView>
    </View>
  );
}

function isHex(v: string): boolean {
  return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v.trim());
}

function Stat({
  label,
  value,
  colors,
  last,
}: {
  label: string;
  value: number;
  colors: ReturnType<typeof useColors>;
  last?: boolean;
}) {
  return (
    <View
      style={[
        styles.stat,
        last
          ? null
          : { borderBottomWidth: 1, borderBottomColor: colors.border },
      ]}
    >
      <Text style={[styles.statLabel, { color: colors.mutedForeground }]}>
        {label}
      </Text>
      <Text style={[styles.statValue, { color: colors.foreground }]}>
        {value}
      </Text>
    </View>
  );
}

function RowSwitch({
  label,
  hint,
  value,
  onValueChange,
}: {
  label: string;
  hint?: string;
  value: boolean;
  onValueChange: (v: boolean) => void;
}) {
  const colors = useColors();
  return (
    <View
      style={[
        styles.switchRow,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <View style={{ flex: 1, paddingRight: 12 }}>
        <Text style={[styles.switchLabel, { color: colors.foreground }]}>
          {label}
        </Text>
        {hint ? (
          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            {hint}
          </Text>
        ) : null}
      </View>
      <Switch
        value={value}
        onValueChange={onValueChange}
        trackColor={{ true: colors.primary, false: colors.border }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 18, paddingBottom: 48 },
  banner: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 14,
    borderWidth: 1,
  },
  bannerText: {
    flex: 1,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    lineHeight: 17,
  },
  publicCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  publicLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    textTransform: "uppercase",
    letterSpacing: 0.4,
  },
  publicUrl: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14, marginTop: 2 },
  section: { gap: 10 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  fieldLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
    marginTop: 2,
  },
  hint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11,
    lineHeight: 16,
  },
  personaRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 14,
    borderWidth: 1,
  },
  personaName: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
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
  accentRow: { flexDirection: "row", alignItems: "flex-end", gap: 12 },
  swatch: {
    width: 42,
    height: 42,
    borderRadius: 10,
    marginBottom: 2,
  },
  switchRow: {
    flexDirection: "row",
    alignItems: "center",
    padding: 14,
    borderWidth: 1,
  },
  switchLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  statsCard: { padding: 4, borderWidth: 1 },
  stat: {
    flexDirection: "row",
    justifyContent: "space-between",
    paddingVertical: 12,
    paddingHorizontal: 12,
  },
  statLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  statValue: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 14 },
});
