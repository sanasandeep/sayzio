import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { getProfile, updateProfile } from "@/lib/api/profile";

/**
 * Mobile-friendly Resume editor + preview.
 *
 * The web app's full resume builder is a multi-template editor that
 * isn't yet exposed via the API. To deliver a usable mobile flow today
 * we let users edit a focused "About / resume" section anchored to
 * their profile bio — which is what the public profile renders as the
 * resume hero on web. Future iterations will broaden this once the
 * resume schema is exposed via OpenAPI.
 */
export default function ResumeScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({ queryKey: ["profile-resume"], queryFn: getProfile });

  const [headline, setHeadline] = useState("");
  const [bio, setBio] = useState("");
  const [dirty, setDirty] = useState(false);

  useEffect(() => {
    if (!q.data) return;
    setHeadline(q.data.display_name ?? q.data.name ?? "");
    setBio(q.data.bio ?? "");
    setDirty(false);
  }, [q.data]);

  const save = useMutation({
    mutationFn: () =>
      updateProfile({
        name: headline.trim() || undefined,
        bio: bio.trim() ? bio.trim() : null,
      }),
    onSuccess: () => {
      setDirty(false);
      qc.invalidateQueries({ queryKey: ["profile-resume"] });
      qc.invalidateQueries({ queryKey: ["profile"] });
      Alert.alert("Saved", "Your resume is up to date.");
    },
    onError: (e: { message?: string }) => {
      Alert.alert("Couldn't save", e?.message ?? "Try again in a moment.");
    },
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Resume" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled">
          <Text style={[styles.h, { color: colors.foreground }]}>Headline</Text>
          <TextField
            label="Display name"
            value={headline}
            onChangeText={(t) => {
              setHeadline(t);
              setDirty(true);
            }}
            placeholder="How your resume is signed"
          />

          <Text style={[styles.h, { color: colors.foreground }]}>About</Text>
          <TextField
            label="Bio / summary"
            value={bio}
            onChangeText={(t) => {
              setBio(t);
              setDirty(true);
            }}
            placeholder="A few sentences about who you are and what you do."
            multiline
            numberOfLines={6}
          />

          <View
            style={[
              styles.preview,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Text style={[styles.previewLabel, { color: colors.mutedForeground }]}>
              Preview
            </Text>
            <Text style={[styles.previewName, { color: colors.foreground }]}>
              {headline.trim() || "Your name"}
            </Text>
            {q.data?.handle ? (
              <Text style={[styles.previewHandle, { color: colors.primary }]}>
                @{q.data.handle}
              </Text>
            ) : null}
            <Text style={[styles.previewBio, { color: colors.foreground }]}>
              {bio.trim() || "Your bio will appear here once you add some text above."}
            </Text>
          </View>

          <Button
            label={save.isPending ? "Saving…" : dirty ? "Save resume" : "Up to date"}
            onPress={() => save.mutate()}
            loading={save.isPending}
            disabled={!dirty || save.isPending}
          />

          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            Looking for richer templates, work history and downloadable PDFs?
            Open the full resume builder on the web from your profile.
          </Text>
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 12, paddingBottom: 60 },
  h: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
    letterSpacing: 0.5,
    textTransform: "uppercase",
    marginTop: 6,
  },
  preview: { padding: 16, borderWidth: 1, gap: 6, marginTop: 6 },
  previewLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    letterSpacing: 0.5,
    textTransform: "uppercase",
  },
  previewName: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22 },
  previewHandle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  previewBio: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 20,
    marginTop: 4,
  },
  hint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 18,
    paddingHorizontal: 4,
    textAlign: "center",
    marginTop: 4,
  },
});
