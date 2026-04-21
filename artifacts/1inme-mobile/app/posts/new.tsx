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
import { createPost } from "@/lib/api/posts";

export default function NewPostScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [pinned, setPinned] = useState(false);

  const m = useMutation({
    mutationFn: () =>
      createPost({
        title: title || null,
        body,
        is_pinned: pinned,
      }),
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
        <TextField
          label="Title (optional)"
          value={title}
          onChangeText={setTitle}
          placeholder="A snappy headline"
        />
        <TextField
          label="Body"
          value={body}
          onChangeText={setBody}
          placeholder="What's new?"
          multiline
          numberOfLines={8}
        />
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
