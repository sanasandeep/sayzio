import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as ImagePicker from "expo-image-picker";
import { Image } from "expo-image";
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
import { CoinCostHint } from "@/components/CoinCostHint";
import { DictationMic } from "@/components/DictationMic";
import { TextField } from "@/components/TextField";
import { setVoiceSurface } from "@/components/VoiceAssistant";
import { useColors } from "@/hooks/useColors";
import {
  getAiChat,
  saveAiChat,
  saveAiChatWithAvatar,
  validateAiAvatar,
  type AiAvatarUpload,
  type AiChatTheme,
} from "@/lib/api/aiChat";
import { showAlert } from "@/lib/webAlert";

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
  const [accent, setAccent] = useState("#3d6bff");
  const [theme, setTheme] = useState<AiChatTheme>("auto");
  const [showBranding, setShowBranding] = useState(true);
  const [groundInProfile, setGroundInProfile] = useState(true);
  const [avatarUrl, setAvatarUrl] = useState("");
  // A device-picked image waiting to be uploaded on the next save. When set it
  // takes precedence over the URL field (mirrors the web's upload/URL choice).
  const [avatarUpload, setAvatarUpload] = useState<AiAvatarUpload | null>(null);
  const [customBrandingText, setCustomBrandingText] = useState("");
  const [customBrandingUrl, setCustomBrandingUrl] = useState("");
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
    setAccent(d.config.accent ?? "#3d6bff");
    setTheme(d.config.theme ?? "auto");
    setShowBranding(d.config.show_branding ?? true);
    setGroundInProfile(d.config.ground_in_profile ?? true);
    setAvatarUrl(d.config.avatar_url ?? "");
    setAvatarUpload(null);
    setCustomBrandingText(d.config.custom_branding_text ?? "");
    setCustomBrandingUrl(d.config.custom_branding_url ?? "");
    const filled = [...d.starters].slice(0, STARTER_SLOTS);
    while (filled.length < STARTER_SLOTS) filled.push("");
    setStarters(filled);
  }, [q.data]);

  const save = useMutation({
    mutationFn: () => {
      if (!name.trim()) throw new Error("Please enter a display name");
      if (!personaId) throw new Error("Please pick a persona");
      const payload = {
        name: name.trim(),
        persona_id: personaId,
        config: {
          greeting: greeting.trim() || null,
          placeholder: placeholder.trim() || "Ask me anything…",
          accent: accent.trim() || "#3d6bff",
          theme,
          show_branding: showBranding,
          ground_in_profile: groundInProfile,
          avatar_url: avatarUrl.trim() || null,
          custom_branding_text: customBrandingText.trim() || null,
          custom_branding_url: customBrandingUrl.trim() || null,
        },
        starters: starters.map((s) => s.trim()).filter(Boolean),
      };
      // When a device image is staged, send it as multipart so the server
      // stores it in the vault; otherwise fall back to the JSON/URL path.
      return avatarUpload
        ? saveAiChatWithAvatar(id, payload, avatarUpload)
        : saveAiChat(id, payload);
    },
    onSuccess: (data) => {
      qc.setQueryData(["ai-chat", id], data);
      qc.invalidateQueries({ queryKey: ["link", id] });
      qc.invalidateQueries({ queryKey: ["links"] });
      setAvatarUpload(null);
      setAvatarUrl(data.ai_chat.config.avatar_url ?? "");
      setSaved(true);
      setTimeout(() => setSaved(false), 2500);
    },
  });

  async function pickAvatar(source: "library" | "camera") {
    const perm =
      source === "camera"
        ? await ImagePicker.requestCameraPermissionsAsync()
        : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      showAlert(
        source === "camera" ? "Camera access needed" : "Photos access needed",
        `Allow ${
          source === "camera" ? "camera" : "photo library"
        } access in Settings to set an avatar.`,
      );
      return;
    }
    const res =
      source === "camera"
        ? await ImagePicker.launchCameraAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            allowsEditing: true,
            aspect: [1, 1],
            quality: 0.85,
          })
        : await ImagePicker.launchImageLibraryAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            allowsEditing: true,
            aspect: [1, 1],
            quality: 0.85,
          });
    if (res.canceled || !res.assets?.[0]) return;
    const asset = res.assets[0];
    const picked: AiAvatarUpload = {
      uri: asset.uri,
      mimeType: asset.mimeType ?? null,
      fileName: asset.fileName ?? null,
      size: asset.fileSize ?? null,
    };
    const problem = validateAiAvatar(picked);
    if (problem) {
      showAlert("Can't use that image", problem);
      return;
    }
    setAvatarUpload(picked);
  }

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

        {/* Every visitor turn is charged to YOUR wallet — surface the shared
            coin-cost + balance hint so an empty wallet is never a surprise. */}
        <CoinCostHint
          cost={q.data.coin_cost}
          balance={q.data.coin_balance ?? null}
          actionLabel="visitor chats"
          verb="visitor turn"
          testID="ai-chat-coins"
        />

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
                { backgroundColor: isHex(accent) ? accent : "#3d6bff" },
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
                placeholder="#3d6bff"
              />
            </View>
          </View>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Branding & avatar
          </Text>

          {d.branding.can_avatar ? (
            <>
              <View style={styles.avatarRow}>
                <View
                  style={[
                    styles.avatarPreview,
                    {
                      backgroundColor: colors.card,
                      borderColor: avatarUpload ? colors.primary : colors.border,
                    },
                  ]}
                >
                  {avatarUpload ? (
                    <Image
                      source={{ uri: avatarUpload.uri }}
                      style={styles.avatarImage}
                      contentFit="cover"
                    />
                  ) : avatarUrl.trim() ? (
                    <Image
                      source={{ uri: avatarUrl.trim() }}
                      style={styles.avatarImage}
                      contentFit="cover"
                    />
                  ) : (
                    <Feather
                      name="cpu"
                      size={26}
                      color={colors.mutedForeground}
                    />
                  )}
                </View>
                <View style={styles.avatarActions}>
                  <Pressable
                    onPress={() => pickAvatar("library")}
                    style={[
                      styles.avatarBtn,
                      {
                        backgroundColor: colors.primary + "1c",
                        borderColor: colors.primary + "55",
                        borderRadius: colors.radius,
                      },
                    ]}
                  >
                    <Feather name="image" size={15} color={colors.primary} />
                    <Text style={[styles.avatarBtnText, { color: colors.primary }]}>
                      Choose photo
                    </Text>
                  </Pressable>
                  <Pressable
                    onPress={() => pickAvatar("camera")}
                    style={[
                      styles.avatarBtn,
                      {
                        backgroundColor: colors.primary + "1c",
                        borderColor: colors.primary + "55",
                        borderRadius: colors.radius,
                      },
                    ]}
                  >
                    <Feather name="camera" size={15} color={colors.primary} />
                    <Text style={[styles.avatarBtnText, { color: colors.primary }]}>
                      Take photo
                    </Text>
                  </Pressable>
                </View>
              </View>

              {avatarUpload ? (
                <Pressable
                  onPress={() => setAvatarUpload(null)}
                  style={styles.avatarClear}
                >
                  <Feather name="x" size={13} color={colors.destructive} />
                  <Text
                    style={[styles.avatarClearText, { color: colors.destructive }]}
                  >
                    Discard picked image
                  </Text>
                </Pressable>
              ) : null}

              <Text style={[styles.hint, { color: colors.mutedForeground }]}>
                {avatarUpload
                  ? "This image uploads when you save. It replaces the URL below."
                  : "Pick a photo from your device, or paste an image URL below. Leave both blank to use the default robot avatar. Max 2MB (JPG, PNG, WebP, GIF)."}
              </Text>

              <TextField
                label="Agent avatar URL"
                value={avatarUrl}
                onChangeText={setAvatarUrl}
                editable={!avatarUpload}
                autoCapitalize="none"
                autoCorrect={false}
                maxLength={2048}
                placeholder="https://…/avatar.png"
              />
            </>
          ) : (
            <LockedRow
              label="Agent avatar"
              hint="Give your AI agent its own face instead of the default robot. Upgrade your plan to unlock."
              colors={colors}
            />
          )}

          {d.branding.can_hide_branding ? (
            <RowSwitch
              label='Show "Powered by Sayzio"'
              hint="Turn off to hide the footer entirely."
              value={showBranding}
              onValueChange={setShowBranding}
            />
          ) : (
            <LockedRow
              label="Branding footer"
              hint='Your page shows a "Powered by Sayzio" footer. Upgrade your plan to hide or replace it.'
              colors={colors}
            />
          )}

          {d.branding.can_custom_branding ? (
            <>
              <TextField
                label="Custom branding text"
                value={customBrandingText}
                onChangeText={setCustomBrandingText}
                maxLength={60}
                placeholder="Powered by Your Brand"
              />
              <TextField
                label="Custom branding link"
                value={customBrandingUrl}
                onChangeText={setCustomBrandingUrl}
                autoCapitalize="none"
                autoCorrect={false}
                maxLength={300}
                placeholder="https://yourbrand.com"
              />
              <Text style={[styles.hint, { color: colors.mutedForeground }]}>
                Replaces "Powered by Sayzio" with your own text and link. Leave
                blank to keep the default.
              </Text>
            </>
          ) : (
            <LockedRow
              label="Custom branding"
              hint='Replace "Powered by Sayzio" with your own text and link. Upgrade your plan to unlock.'
              colors={colors}
            />
          )}
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

function LockedRow({
  label,
  hint,
  colors,
}: {
  label: string;
  hint: string;
  colors: ReturnType<typeof useColors>;
}) {
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
        <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
          <Text style={[styles.switchLabel, { color: colors.foreground }]}>
            {label}
          </Text>
          <View
            style={{
              backgroundColor: colors.primary + "22",
              borderRadius: 999,
              paddingHorizontal: 6,
              paddingVertical: 1,
            }}
          >
            <Text
              style={{
                color: colors.primary,
                fontFamily: "SpaceGrotesk_700Bold",
                fontSize: 9,
                letterSpacing: 0.4,
              }}
            >
              PRO
            </Text>
          </View>
        </View>
        <Text style={[styles.hint, { color: colors.mutedForeground }]}>
          {hint}
        </Text>
      </View>
      <Feather name="lock" size={16} color={colors.mutedForeground} />
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
  avatarRow: { flexDirection: "row", alignItems: "center", gap: 12 },
  avatarPreview: {
    width: 64,
    height: 64,
    borderRadius: 16,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  avatarImage: { width: "100%", height: "100%" },
  avatarActions: { flex: 1, gap: 8 },
  avatarBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 10,
    borderWidth: 1,
  },
  avatarBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  avatarClear: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingVertical: 2,
  },
  avatarClearText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
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
