import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useEffect, useState } from "react";
import { Pressable, ScrollView, StyleSheet, Switch, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { SettingsForm } from "@/components/SettingsForm";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { getLink, updateLink } from "@/lib/api/links";

const UTM_KEYS = [
  { key: "utm_source", placeholder: "1inme" },
  { key: "utm_medium", placeholder: "biolink" },
  { key: "utm_campaign", placeholder: "{slug}" },
  { key: "utm_term", placeholder: "optional" },
  { key: "utm_content", placeholder: "{block}" },
] as const;

function BiolinkAutoUtmCard({ linkId }: { linkId: number }) {
  const colors = useColors();
  const qc = useQueryClient();
  const q = useQuery({
    queryKey: ["link", linkId],
    queryFn: () => getLink(linkId),
    enabled: Number.isFinite(linkId),
  });

  const [enabled, setEnabled] = useState(false);
  const [defaults, setDefaults] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!q.data) return;
    const au = ((q.data.settings as Record<string, any>) ?? {}).biolink
      ?.auto_utm ?? {};
    setEnabled(!!au.enabled);
    const d: Record<string, string> = {};
    UTM_KEYS.forEach(({ key }) => {
      d[key] = typeof au.defaults?.[key] === "string" ? au.defaults[key] : "";
    });
    setDefaults(d);
  }, [q.data]);

  const save = useMutation({
    mutationFn: () => {
      const cleanDefaults: Record<string, string> = {};
      UTM_KEYS.forEach(({ key }) => {
        const v = (defaults[key] || "").trim();
        if (v) cleanDefaults[key] = v;
      });
      const prevSettings = (q.data?.settings ?? {}) as Record<string, any>;
      const prevBiolink = (prevSettings.biolink ?? {}) as Record<string, any>;
      return updateLink(linkId, {
        settings: {
          ...prevSettings,
          biolink: {
            ...prevBiolink,
            auto_utm: { enabled, defaults: cleanDefaults },
          },
        },
      });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["link", linkId] }),
  });

  return (
    <View
      style={{
        backgroundColor: colors.card,
        borderColor: colors.border,
        borderWidth: 1,
        borderRadius: colors.radius,
        padding: 16,
        gap: 12,
        marginBottom: 14,
      }}
    >
      <Text
        style={{
          color: colors.foreground,
          fontSize: 14,
          fontFamily: "SpaceGrotesk_600SemiBold",
        }}
      >
        Auto-UTM on outbound links
      </Text>
      <Text
        style={{
          color: colors.mutedForeground,
          fontSize: 12,
          fontFamily: "SpaceGrotesk_400Regular",
          lineHeight: 18,
        }}
      >
        Append clean attribution params to every outbound block click. Use{" "}
        <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold" }}>{"{slug}"}</Text>{" "}
        and{" "}
        <Text style={{ fontFamily: "SpaceGrotesk_600SemiBold" }}>{"{block}"}</Text>{" "}
        as tokens. Per-block overrides win, and any params already in the
        destination URL are preserved.
      </Text>
      <Pressable
        onPress={() => setEnabled((v) => !v)}
        style={{
          flexDirection: "row",
          alignItems: "center",
          justifyContent: "space-between",
          gap: 12,
          paddingVertical: 6,
        }}
      >
        <Text
          style={{
            color: colors.foreground,
            fontSize: 13,
            fontFamily: "SpaceGrotesk_600SemiBold",
            flex: 1,
          }}
        >
          Enable Auto-UTM for this biolink
        </Text>
        <Switch
          value={enabled}
          onValueChange={setEnabled}
          trackColor={{ true: colors.primary, false: colors.border }}
        />
      </Pressable>

      {enabled
        ? UTM_KEYS.map(({ key, placeholder }) => (
            <TextField
              key={key}
              label={key}
              hint={`default placeholder: ${placeholder}`}
              value={defaults[key] ?? ""}
              autoCapitalize="none"
              onChangeText={(t) => setDefaults((p) => ({ ...p, [key]: t }))}
            />
          ))
        : null}

      <Button
        label="Save Auto-UTM"
        onPress={() => save.mutate()}
        loading={save.isPending}
      />
    </View>
  );
}

export default function AdvancedSettings() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const linkId = Number(id);
  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Advanced" }} />
      <ScrollView contentContainerStyle={styles.body}>
        <BiolinkAutoUtmCard linkId={linkId} />
        <SettingsForm
          linkId={linkId}
          group="advanced"
          blurb="Tracking, integrations, and access control."
          fields={[
            { key: "ga_id", label: "Google Analytics ID", hint: "G-XXXXX" },
            { key: "fb_pixel", label: "Meta pixel ID" },
            { key: "tt_pixel", label: "TikTok pixel ID" },
            { key: "custom_head", label: "Custom <head> HTML", kind: "multiline" },
            {
              key: "custom_footer",
              label: "Custom footer HTML",
              kind: "multiline",
            },
            { key: "password", label: "Password", hint: "Leave blank to remove" },
            { key: "redirect_url", label: "Forward to URL", kind: "url" },
            { key: "noindex", label: "Hide from search engines", kind: "switch" },
          ]}
        />
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  body: { padding: 20, paddingBottom: 40 },
});
