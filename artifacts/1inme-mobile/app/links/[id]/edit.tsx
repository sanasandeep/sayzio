import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Platform,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  deleteLink,
  duplicateLink,
  getLink,
  resetLink,
  updateLink,
  type Link,
} from "@/lib/api/links";
import { metaForApiType } from "@/lib/linkKinds";

const VISIBILITIES: Link["visibility"][] = [
  "public",
  "registered",
  "followers",
  "subscribers",
];

function confirm(title: string, msg: string, onYes: () => void) {
  if (Platform.OS === "web") {
    if (typeof window !== "undefined" && window.confirm(`${title}\n\n${msg}`)) {
      onYes();
    }
    return;
  }
  Alert.alert(title, msg, [
    { text: "Cancel", style: "cancel" },
    { text: "OK", style: "destructive", onPress: onYes },
  ]);
}

export default function EditLinkScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useQuery({
    queryKey: ["link", id],
    queryFn: () => getLink(id),
    enabled: Number.isFinite(id),
  });

  const [title, setTitle] = useState("");
  const [alias, setAlias] = useState("");
  const [longUrl, setLongUrl] = useState("");
  const [seoTitle, setSeoTitle] = useState("");
  const [seoDesc, setSeoDesc] = useState("");
  const [visibility, setVisibility] = useState<Link["visibility"]>("public");
  const [active, setActive] = useState(true);

  useEffect(() => {
    const l = q.data;
    if (!l) return;
    setTitle(l.title ?? "");
    setAlias(l.alias);
    setLongUrl(l.long_url ?? "");
    setSeoTitle(l.seo_title ?? "");
    setSeoDesc(l.seo_description ?? "");
    setVisibility(l.visibility);
    setActive(l.is_active);
  }, [q.data]);

  const save = useMutation({
    mutationFn: () =>
      updateLink(id, {
        title: title || null,
        alias,
        long_url: longUrl || null,
        seo_title: seoTitle || null,
        seo_description: seoDesc || null,
        visibility,
        is_active: active,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["link", id] });
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });

  const dup = useMutation({
    mutationFn: () => duplicateLink(q.data!),
    onSuccess: (link) => {
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["dashboard"] });
      router.replace(`/links/${link.id}/edit` as any);
    },
  });

  const reset = useMutation({
    mutationFn: () => resetLink(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["link", id] });
      qc.invalidateQueries({ queryKey: ["analytics", id] });
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });

  const del = useMutation({
    mutationFn: () => deleteLink(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["links"] });
      qc.invalidateQueries({ queryKey: ["dashboard"] });
      router.replace("/(tabs)/links");
    },
  });

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
        <Text style={{ color: colors.destructive }}>Couldn't load link.</Text>
      </View>
    );
  }

  const l = q.data;
  const meta = metaForApiType(l.type);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          headerShown: true,
          title: meta.label,
        }}
      />
      <ScrollView contentContainerStyle={styles.body}>
        <View
          style={[
            styles.banner,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <View
            style={[
              styles.iconWrap,
              { backgroundColor: colors.primary + "1c" },
            ]}
          >
            <Feather name={meta.icon} size={20} color={colors.primary} />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={[styles.shortUrl, { color: colors.foreground }]}>
              {l.short_url}
            </Text>
            <Text style={[styles.muted, { color: colors.mutedForeground }]}>
              {l.total_clicks} clicks · {l.unique_clicks} unique
            </Text>
          </View>
          <Pressable
            onPress={() => Share.share({ message: l.short_url })}
            hitSlop={8}
          >
            <Feather name="share-2" size={20} color={colors.primary} />
          </Pressable>
        </View>

        <View style={styles.actionsRow}>
          <ActionTile
            icon="bar-chart-2"
            label="Analytics"
            onPress={() => router.push(`/links/${id}/analytics` as any)}
          />
          {meta.kind === "biolink" ? (
            <ActionTile
              icon="grid"
              label="Blocks"
              onPress={() => router.push(`/links/${id}/blocks` as any)}
            />
          ) : null}
          <ActionTile
            icon="copy"
            label="Duplicate"
            onPress={() => dup.mutate()}
          />
          <ActionTile
            icon="rotate-ccw"
            label="Reset"
            onPress={() =>
              confirm(
                "Reset link?",
                "This clears the click counters and stored analytics for this link.",
                () => reset.mutate(),
              )
            }
          />
        </View>

        {meta.kind === "biolink" ? (
          <View style={styles.actionsRow}>
            <ActionTile
              icon="sliders"
              label="Appearance"
              onPress={() =>
                router.push(`/links/${id}/settings/appearance` as any)
              }
            />
            <ActionTile
              icon="layout"
              label="Layout"
              onPress={() => router.push(`/links/${id}/settings/layout` as any)}
            />
            <ActionTile
              icon="droplet"
              label="Block theme"
              onPress={() =>
                router.push(`/links/${id}/settings/block-theme` as any)
              }
            />
            <ActionTile
              icon="settings"
              label="Advanced"
              onPress={() =>
                router.push(`/links/${id}/settings/advanced` as any)
              }
            />
          </View>
        ) : null}

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Basics
          </Text>
          <TextField label="Title" value={title} onChangeText={setTitle} />
          <TextField
            label="Alias"
            value={alias}
            onChangeText={setAlias}
            autoCapitalize="none"
            autoCorrect={false}
          />
          {meta.kind !== "biolink" && meta.kind !== "vcard" ? (
            <TextField
              label="Destination URL"
              value={longUrl}
              onChangeText={setLongUrl}
              keyboardType="url"
              autoCapitalize="none"
            />
          ) : null}
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Visibility
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
            {VISIBILITIES.map((v) => {
              const on = visibility === v;
              return (
                <Pressable
                  key={v}
                  onPress={() => setVisibility(v)}
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
                    {v}
                  </Text>
                </Pressable>
              );
            })}
          </View>
          <View
            style={[
              styles.row,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Text style={[styles.rowLabel, { color: colors.foreground }]}>
              Active
            </Text>
            <Switch
              value={active}
              onValueChange={setActive}
              trackColor={{ true: colors.primary, false: colors.border }}
            />
          </View>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            SEO
          </Text>
          <TextField
            label="SEO title"
            value={seoTitle}
            onChangeText={setSeoTitle}
          />
          <TextField
            label="SEO description"
            value={seoDesc}
            onChangeText={setSeoDesc}
            multiline
            numberOfLines={3}
            style={{ height: 88, textAlignVertical: "top", paddingTop: 12 }}
          />
        </View>

        <Button
          label="Save changes"
          onPress={() => save.mutate()}
          loading={save.isPending}
        />

        <Pressable
          onPress={() =>
            confirm("Delete link?", `Delete /${l.alias} permanently?`, () =>
              del.mutate(),
            )
          }
          style={({ pressed }) => [
            styles.deleteRow,
            { opacity: pressed ? 0.7 : 1 },
          ]}
        >
          <Feather name="trash-2" size={16} color={colors.destructive} />
          <Text style={[styles.deleteText, { color: colors.destructive }]}>
            Delete this link
          </Text>
        </Pressable>
      </ScrollView>
    </View>
  );
}

function ActionTile({
  icon,
  label,
  onPress,
}: {
  icon: keyof typeof Feather.glyphMap;
  label: string;
  onPress: () => void;
}) {
  const colors = useColors();
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.actionTile,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          opacity: pressed ? 0.85 : 1,
        },
      ]}
    >
      <Feather name={icon} size={18} color={colors.primary} />
      <Text style={[styles.actionLabel, { color: colors.foreground }]}>
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 16, paddingBottom: 40 },
  banner: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  shortUrl: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  muted: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  actionsRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
  },
  actionTile: {
    flexBasis: "47%",
    flexGrow: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 14,
    paddingHorizontal: 14,
    borderWidth: 1,
  },
  actionLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  section: { gap: 10 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
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
  row: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    padding: 14,
    borderWidth: 1,
  },
  rowLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  deleteRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 14,
    marginTop: 8,
  },
  deleteText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
});
