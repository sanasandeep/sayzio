import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  Alert,
  Platform,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { getProfile, updateProfile, type ProfilePayload } from "@/lib/api/profile";

export default function ProfileEdit() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const q = useQuery({ queryKey: ["profile"], queryFn: getProfile });

  const [form, setForm] = useState<ProfilePayload>({});
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (q.data) {
      setForm({
        name: q.data.name ?? q.data.display_name ?? "",
        bio: q.data.bio ?? "",
        handle: q.data.handle ?? "",
        phone: q.data.phone ?? q.data.mobile ?? "",
        timezone: q.data.timezone ?? "",
        language: q.data.language ?? "",
        discoverable: q.data.discoverable ?? true,
        allow_followers: q.data.allow_followers ?? true,
      });
    }
  }, [q.data]);

  const m = useMutation({
    mutationFn: (p: ProfilePayload) => updateProfile(p),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["profile"] });
      qc.invalidateQueries({ queryKey: ["auth-me"] });
      if (Platform.OS === "web") {
        router.back();
      } else {
        Alert.alert("Saved", "Profile updated.", [
          { text: "OK", onPress: () => router.back() },
        ]);
      }
    },
    onError: (e: any) => {
      if (e?.errors) {
        const flat: Record<string, string> = {};
        Object.entries(e.errors).forEach(([k, v]) => {
          flat[k] = Array.isArray(v) ? (v[0] as string) : String(v);
        });
        setErrors(flat);
      } else {
        Alert.alert("Could not save", e?.message ?? "Unknown error");
      }
    },
  });

  const set = <K extends keyof ProfilePayload>(k: K, v: ProfilePayload[K]) => {
    setForm((f) => ({ ...f, [k]: v }));
    if (errors[k as string]) {
      setErrors((er) => ({ ...er, [k as string]: "" }));
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Edit profile" }} />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 14, paddingBottom: 40 }}>
        <TextField
          label="Display name"
          value={form.name ?? ""}
          onChangeText={(t) => set("name", t)}
          error={errors.name}
          autoCapitalize="words"
        />
        <TextField
          label="Handle"
          value={form.handle ?? ""}
          onChangeText={(t) => set("handle", t.toLowerCase().replace(/[^a-z0-9_]/g, ""))}
          autoCapitalize="none"
          hint="Lowercase letters, digits and underscores only"
          error={errors.handle}
        />
        <TextField
          label="Bio"
          value={form.bio ?? ""}
          onChangeText={(t) => set("bio", t)}
          multiline
          numberOfLines={4}
          style={{ minHeight: 100, paddingVertical: 12, textAlignVertical: "top" }}
          error={errors.bio}
        />
        <TextField
          label="Phone"
          value={form.phone ?? ""}
          onChangeText={(t) => set("phone", t)}
          keyboardType="phone-pad"
          error={errors.phone}
        />
        <TextField
          label="Timezone"
          value={form.timezone ?? ""}
          onChangeText={(t) => set("timezone", t)}
          placeholder="e.g. Europe/London"
          autoCapitalize="none"
          error={errors.timezone}
        />
        <TextField
          label="Language"
          value={form.language ?? ""}
          onChangeText={(t) => set("language", t)}
          placeholder="e.g. en"
          autoCapitalize="none"
          error={errors.language}
        />

        <View
          style={[
            styles.toggleRow,
            { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          <View style={{ flex: 1 }}>
            <Text style={[styles.tLabel, { color: colors.foreground }]}>Discoverable</Text>
            <Text style={[styles.tHint, { color: colors.mutedForeground }]}>
              Show up in creator discovery
            </Text>
          </View>
          <Switch
            value={!!form.discoverable}
            onValueChange={(v) => set("discoverable", v)}
            trackColor={{ true: colors.primary }}
          />
        </View>

        <View
          style={[
            styles.toggleRow,
            { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          <View style={{ flex: 1 }}>
            <Text style={[styles.tLabel, { color: colors.foreground }]}>Allow followers</Text>
            <Text style={[styles.tHint, { color: colors.mutedForeground }]}>
              Let other people follow your updates
            </Text>
          </View>
          <Switch
            value={!!form.allow_followers}
            onValueChange={(v) => set("allow_followers", v)}
            trackColor={{ true: colors.primary }}
          />
        </View>

        <Button
          label="Save changes"
          onPress={() => m.mutate(form)}
          loading={m.isPending}
        />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 16,
    borderWidth: 1,
  },
  tLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  tHint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 2 },
});
