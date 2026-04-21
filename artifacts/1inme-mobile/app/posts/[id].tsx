import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { listPosts, updatePost } from "@/lib/api/posts";

export default function EditPostScreen() {
  const colors = useColors();
  const router = useRouter();
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);
  const qc = useQueryClient();

  const q = useQuery({ queryKey: ["posts"], queryFn: listPosts });
  const post = q.data?.items.find((p) => p.id === id);

  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");

  useEffect(() => {
    if (post) {
      setTitle(post.title ?? "");
      setBody(post.body);
    }
  }, [post?.id]);

  const m = useMutation({
    mutationFn: () =>
      updatePost(id, { title: title || null, body }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["posts"] });
      router.back();
    },
    onError: (e: any) => Alert.alert("Failed", e?.message ?? "Try again"),
  });

  if (!post) {
    return (
      <View
        style={{
          flex: 1,
          backgroundColor: colors.background,
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        <Stack.Screen options={{ title: "Edit post" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.background }}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
    >
      <Stack.Screen
        options={{
          title: "Edit post",
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
          label="Title"
          value={title}
          onChangeText={setTitle}
        />
        <TextField
          label="Body"
          value={body}
          onChangeText={setBody}
          multiline
          numberOfLines={8}
        />
        <Button
          label={m.isPending ? "Saving…" : "Save changes"}
          onPress={() => body.trim() && m.mutate()}
          disabled={m.isPending || !body.trim()}
        />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
