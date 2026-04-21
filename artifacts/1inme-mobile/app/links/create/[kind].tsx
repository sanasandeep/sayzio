import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useState } from "react";
import { Alert, ScrollView, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { createLink } from "@/lib/api/links";
import { metaForKind, type LinkKind } from "@/lib/linkKinds";

export default function CreateLinkScreen() {
  const colors = useColors();
  const router = useRouter();
  const { kind } = useLocalSearchParams<{ kind: LinkKind }>();
  const meta = metaForKind((kind as LinkKind) || "url");

  const [title, setTitle] = useState("");
  const [alias, setAlias] = useState("");
  const [longUrl, setLongUrl] = useState("");
  // vCard
  const [vcFullName, setVcFullName] = useState("");
  const [vcOrg, setVcOrg] = useState("");
  const [vcEmail, setVcEmail] = useState("");
  const [vcPhone, setVcPhone] = useState("");
  // Calendar / event
  const [evStart, setEvStart] = useState("");
  const [evEnd, setEvEnd] = useState("");
  const [evLocation, setEvLocation] = useState("");
  // File
  const [fileUrl, setFileUrl] = useState("");
  const [fileName, setFileName] = useState("");

  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit() {
    setError(null);
    setBusy(true);
    try {
      const settings: Record<string, unknown> = {};
      let payload: Parameters<typeof createLink>[0] = {
        type: meta.apiType,
        title: title || null,
        alias: alias || undefined,
      };

      if (meta.kind === "url") {
        if (!longUrl) throw new Error("Please enter a destination URL");
        payload.long_url = longUrl;
      } else if (meta.kind === "biolink") {
        // biolink uses no long_url; blocks built next
      } else if (meta.kind === "file") {
        if (!fileUrl) throw new Error("Please enter the file URL");
        payload.long_url = fileUrl;
        settings.file = { url: fileUrl, filename: fileName || null };
      } else if (meta.kind === "vcard") {
        if (!vcFullName) throw new Error("Please enter a name for the vCard");
        settings.vcard = {
          full_name: vcFullName,
          organization: vcOrg || null,
          email: vcEmail || null,
          phone: vcPhone || null,
        };
      } else if (meta.kind === "calendar") {
        if (!evStart) throw new Error("Please enter a start time");
        settings.event = {
          start: evStart,
          end: evEnd || null,
          location: evLocation || null,
        };
      }

      payload.settings = settings;
      const link = await createLink(payload);

      if (meta.kind === "biolink") {
        router.replace(`/links/${link.id}/blocks` as any);
      } else {
        router.replace(`/links/${link.id}/edit` as any);
      }
    } catch (e: any) {
      setError(e?.message || "Failed to create link");
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: `New ${meta.label}` }} />
      <ScrollView contentContainerStyle={styles.body}>
        <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
          {meta.blurb}
        </Text>

        <TextField
          label="Title"
          value={title}
          onChangeText={setTitle}
          placeholder="Optional internal label"
        />
        <TextField
          label="Custom alias"
          value={alias}
          onChangeText={setAlias}
          placeholder="leave blank to auto-generate"
          autoCapitalize="none"
          autoCorrect={false}
        />

        {meta.kind === "url" ? (
          <TextField
            label="Destination URL"
            value={longUrl}
            onChangeText={setLongUrl}
            keyboardType="url"
            autoCapitalize="none"
            placeholder="https://example.com/very/long/path"
          />
        ) : null}

        {meta.kind === "file" ? (
          <>
            <TextField
              label="File URL"
              value={fileUrl}
              onChangeText={setFileUrl}
              autoCapitalize="none"
              placeholder="https://your.cdn/file.pdf"
            />
            <TextField
              label="File name"
              value={fileName}
              onChangeText={setFileName}
              placeholder="report.pdf"
            />
          </>
        ) : null}

        {meta.kind === "vcard" ? (
          <>
            <TextField
              label="Full name"
              value={vcFullName}
              onChangeText={setVcFullName}
              placeholder="Jane Doe"
            />
            <TextField
              label="Organization"
              value={vcOrg}
              onChangeText={setVcOrg}
              placeholder="Acme Inc."
            />
            <TextField
              label="Email"
              value={vcEmail}
              onChangeText={setVcEmail}
              keyboardType="email-address"
              autoCapitalize="none"
              placeholder="jane@acme.com"
            />
            <TextField
              label="Phone"
              value={vcPhone}
              onChangeText={setVcPhone}
              keyboardType="phone-pad"
              placeholder="+1 555 0100"
            />
          </>
        ) : null}

        {meta.kind === "calendar" ? (
          <>
            <TextField
              label="Starts (ISO 8601)"
              value={evStart}
              onChangeText={setEvStart}
              autoCapitalize="none"
              placeholder="2025-12-31T18:00:00Z"
            />
            <TextField
              label="Ends (ISO 8601)"
              value={evEnd}
              onChangeText={setEvEnd}
              autoCapitalize="none"
              placeholder="2025-12-31T20:00:00Z"
            />
            <TextField
              label="Location"
              value={evLocation}
              onChangeText={setEvLocation}
              placeholder="123 Main St"
            />
          </>
        ) : null}

        {error ? (
          <Text style={{ color: colors.destructive }}>{error}</Text>
        ) : null}

        <Button
          label={meta.kind === "biolink" ? "Create & open editor" : "Create link"}
          onPress={onSubmit}
          loading={busy}
        />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  body: { padding: 20, gap: 14, paddingBottom: 40 },
  blurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
});
