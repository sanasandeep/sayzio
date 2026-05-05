import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useState } from "react";
import {
  Alert,
  KeyboardAvoidingView,
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
import { createPost, type PostMedia, type PostType } from "@/lib/api/posts";

const TYPE_OPTIONS: { key: PostType; label: string; emoji: string }[] = [
  { key: "text",    label: "Text",    emoji: "✍️" },
  { key: "image",   label: "Image",   emoji: "🖼️" },
  { key: "gallery", label: "Gallery", emoji: "🗂️" },
  { key: "video",   label: "Video",   emoji: "🎬" },
  { key: "audio",   label: "Audio",   emoji: "🎧" },
  { key: "link",    label: "Link",    emoji: "🔗" },
];

export default function NewPostScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [pinned, setPinned] = useState(false);
  const [postType, setPostType] = useState<PostType>("text");
  const [media1, setMedia1] = useState("");
  const [media2, setMedia2] = useState(""); // poster (video) / extra item / desc
  const [media3, setMedia3] = useState(""); // gallery extras (one per line)

  const buildMedia = (): { image?: string | null; media?: PostMedia | null } => {
    const trim = (s: string) => s.trim();
    switch (postType) {
      case "image":
        return media1.trim() ? { image: trim(media1) } : {};
      case "gallery": {
        const urls = [media1, media3]
          .flatMap((s) => s.split(/\n+/))
          .map(trim)
          .filter(Boolean);
        return urls.length
          ? { media: { items: urls.map((url) => ({ url })) } }
          : {};
      }
      case "video":
        return media1.trim()
          ? { media: { url: trim(media1), poster: trim(media2) || null } }
          : {};
      case "audio":
        return media1.trim()
          ? { media: { url: trim(media1), title: trim(media2) || null } }
          : {};
      case "link":
        return media1.trim()
          ? {
              media: {
                url: trim(media1),
                title: trim(media2) || null,
                description: trim(media3) || null,
              },
            }
          : {};
      default:
        return {};
    }
  };

  const m = useMutation({
    mutationFn: () => {
      const extra = buildMedia();
      return createPost({
        title: title || null,
        body,
        is_pinned: pinned,
        post_type: postType,
        ...extra,
      });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["posts"] });
      router.back();
    },
    onError: (e: any) => Alert.alert("Failed", e?.message ?? "Try again"),
  });

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.background }}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
    >
      <Stack.Screen
        options={{
          title: "New post",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
        }}
      />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 16 }}>
        <View>
          <Text
            style={{
              color: colors.foreground,
              fontFamily: "SpaceGrotesk_600SemiBold",
              fontSize: 14,
              marginBottom: 8,
            }}
          >
            Post type
          </Text>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ gap: 8 }}
          >
            {TYPE_OPTIONS.map((opt) => {
              const active = postType === opt.key;
              return (
                <Text
                  key={opt.key}
                  onPress={() => setPostType(opt.key)}
                  style={{
                    color: active ? "#fff" : colors.foreground,
                    backgroundColor: active ? colors.primary : colors.card,
                    borderColor: active ? colors.primary : colors.border,
                    borderWidth: 1,
                    paddingHorizontal: 12,
                    paddingVertical: 8,
                    borderRadius: 999,
                    overflow: "hidden",
                    fontWeight: "600",
                  }}
                >
                  {opt.emoji}  {opt.label}
                </Text>
              );
            })}
          </ScrollView>
        </View>

        <TextField
          label="Title (optional)"
          value={title}
          onChangeText={setTitle}
          placeholder="A snappy headline"
        />
        <TextField
          label={postType === "text" ? "Body" : "Caption / context"}
          value={body}
          onChangeText={setBody}
          placeholder={
            postType === "text" ? "What's new?" : "Add a short caption…"
          }
          multiline
          numberOfLines={postType === "text" ? 8 : 4}
        />

        {postType === "image" ? (
          <TextField
            label="Image URL"
            value={media1}
            onChangeText={setMedia1}
            placeholder="https://…"
            autoCapitalize="none"
          />
        ) : null}

        {postType === "gallery" ? (
          <>
            <TextField
              label="Image URL"
              value={media1}
              onChangeText={setMedia1}
              placeholder="https://… (first image)"
              autoCapitalize="none"
            />
            <TextField
              label="Additional images (one URL per line)"
              value={media3}
              onChangeText={setMedia3}
              placeholder={"https://…\nhttps://…"}
              multiline
              numberOfLines={4}
              autoCapitalize="none"
            />
          </>
        ) : null}

        {postType === "video" ? (
          <>
            <TextField
              label="Video URL (mp4 or hosted link)"
              value={media1}
              onChangeText={setMedia1}
              placeholder="https://…"
              autoCapitalize="none"
            />
            <TextField
              label="Poster image (optional)"
              value={media2}
              onChangeText={setMedia2}
              placeholder="https://…"
              autoCapitalize="none"
            />
          </>
        ) : null}

        {postType === "audio" ? (
          <>
            <TextField
              label="Audio URL"
              value={media1}
              onChangeText={setMedia1}
              placeholder="https://…"
              autoCapitalize="none"
            />
            <TextField
              label="Track title (optional)"
              value={media2}
              onChangeText={setMedia2}
              placeholder="Episode 14 — guest spot"
            />
          </>
        ) : null}

        {postType === "link" ? (
          <>
            <TextField
              label="URL"
              value={media1}
              onChangeText={setMedia1}
              placeholder="https://…"
              autoCapitalize="none"
            />
            <TextField
              label="Card title (optional)"
              value={media2}
              onChangeText={setMedia2}
              placeholder="What you're linking to"
            />
            <TextField
              label="Description (optional)"
              value={media3}
              onChangeText={setMedia3}
              multiline
              numberOfLines={3}
              placeholder="Why followers should click"
            />
          </>
        ) : null}
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
          <View style={{ flex: 1 }}>
            <Text style={[styles.label, { color: colors.foreground }]}>
              Pin this post
            </Text>
            <Text
              style={[styles.hint, { color: colors.mutedForeground }]}
            >
              It'll show at the top of your feed
            </Text>
          </View>
          <Switch
            value={pinned}
            onValueChange={setPinned}
            trackColor={{ true: colors.primary }}
          />
        </View>
        <Button
          label={m.isPending ? "Publishing…" : "Publish post"}
          onPress={() => body.trim() && m.mutate()}
          disabled={m.isPending || !body.trim()}
        />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  label: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  hint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 2 },
});
