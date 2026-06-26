import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  deletePost,
  listPosts,
  pinPost,
  unpinPost,
  type Post,
} from "@/lib/api/posts";

export default function PostsScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const q = useQuery({ queryKey: ["posts"], queryFn: listPosts });

  const togglePin = useMutation({
    mutationFn: (p: Post) => (p.is_pinned ? unpinPost(p.id) : pinPost(p.id)),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["posts"] }),
    onError: (e: any) => Alert.alert("Failed", e?.message ?? "Try again"),
  });

  const remove = useMutation({
    mutationFn: (id: number) => deletePost(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["posts"] }),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Posts",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
          headerRight: () => (
            <Pressable
              onPress={() => router.push("/posts/new")}
              hitSlop={8}
              style={{ paddingRight: 12 }}
            >
              <Feather name="plus" size={22} color={colors.primary} />
            </Pressable>
          ),
        }}
      />
      {q.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={q.data?.items ?? []}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
          renderItem={({ item }) => (
            <Pressable
              onPress={() => router.push(`/posts/${item.id}`)}
              style={[
                styles.card,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <View style={styles.head}>
                <Text
                  style={[styles.status, { color: colors.primary }]}
                >
                  {item.status}
                </Text>
                {item.is_pinned ? (
                  <Feather name="bookmark" size={14} color={colors.primary} />
                ) : null}
              </View>
              {item.title ? (
                <Text
                  numberOfLines={1}
                  style={[styles.title, { color: colors.foreground }]}
                >
                  {item.title}
                </Text>
              ) : null}
              <Text
                numberOfLines={3}
                style={[styles.body, { color: colors.mutedForeground }]}
              >
                {item.body}
              </Text>
              <View style={styles.actions}>
                <ActionBtn
                  icon={item.is_pinned ? "bookmark" : "bookmark"}
                  label={item.is_pinned ? "Unpin" : "Pin"}
                  onPress={() => togglePin.mutate(item)}
                />
                <ActionBtn
                  icon="trash-2"
                  label="Delete"
                  danger
                  onPress={() =>
                    Alert.alert("Delete post?", "This cannot be undone.", [
                      { text: "Cancel", style: "cancel" },
                      {
                        text: "Delete",
                        style: "destructive",
                        onPress: () => remove.mutate(item.id),
                      },
                    ])
                  }
                />
              </View>
            </Pressable>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="message-square"
              title="No posts yet"
              body="Share an update with your followers."
            />
          }
        />
      )}
    </View>
  );
}

function ActionBtn({
  icon,
  label,
  danger,
  onPress,
}: {
  icon: keyof typeof Feather.glyphMap;
  label: string;
  danger?: boolean;
  onPress: () => void;
}) {
  const colors = useColors();
  const c = danger ? colors.destructive : colors.primary;
  return (
    <Pressable
      onPress={onPress}
      hitSlop={6}
      style={({ pressed }) => [
        styles.actionBtn,
        { borderColor: colors.border, opacity: pressed ? 0.7 : 1 },
      ]}
    >
      <Feather name={icon} size={14} color={c} />
      <Text style={[styles.actionLabel, { color: c }]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { padding: 16, borderWidth: 1, gap: 6 },
  head: { flexDirection: "row", alignItems: "center", gap: 8 },
  status: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  body: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 18 },
  actions: { flexDirection: "row", gap: 8, marginTop: 8 },
  actionBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  actionLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
});
