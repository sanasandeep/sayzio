import { Text, View, Pressable } from "react-native";
import { useInfiniteQuery } from "@tanstack/react-query";
import { useRouter } from "expo-router";
import { FeatureScreen } from "@/components/FeatureScreen";
import { Button } from "@/components/Button";
import { apiFetch } from "@/lib/api";
import { useColors } from "@/hooks/useColors";
type Feed = {
  items: {
    id: number;
    type: string;
    occurred_at: string | null;
    data: { title?: string; body?: string; description?: string };
    creator: { name: string; handle: string | null } | null;
  }[];
  meta: { current_page: number; last_page: number };
};
export default function ActivityFeed() {
  const c = useColors();
  const router = useRouter();
  const q = useInfiniteQuery({
    queryKey: ["activity-feed"],
    queryFn: async ({ pageParam }) =>
      (await apiFetch<{ data: Feed }>(`/feed?page=${pageParam}`)).data,
    initialPageParam: 1,
    getNextPageParam: (last) =>
      last.meta.current_page < last.meta.last_page
        ? last.meta.current_page + 1
        : undefined,
  });
  const items = q.data?.pages.flatMap((p) => p.items) ?? [];
  return (
    <FeatureScreen
      title="Activity feed"
      loading={q.isLoading}
      error={q.error}
      retry={() => q.refetch()}
    >
      {!items.length ? (
        <Text style={{ color: c.mutedForeground }}>
          No activity to show yet.
        </Text>
      ) : (
        items.map((item) => (
          <View
            key={item.id}
            style={{
              padding: 16,
              gap: 8,
              borderWidth: 1,
              borderColor: c.border,
              borderRadius: c.radius,
            }}
          >
            <Pressable
              disabled={!item.creator?.handle}
              onPress={() =>
                router.push({
                  pathname: "/profile/[handle]",
                  params: { handle: item.creator!.handle! },
                })
              }
            >
              <Text style={{ color: c.primary, fontWeight: "700" }}>
                {item.creator?.name || "Creator"}
              </Text>
            </Pressable>
            <Text style={{ color: c.foreground }}>
              {item.data?.title || item.type.replaceAll("_", " ")}
            </Text>
            {item.data?.body || item.data?.description ? (
              <Text style={{ color: c.foreground }}>
                {item.data.body || item.data.description}
              </Text>
            ) : null}
            <Text style={{ color: c.mutedForeground }}>
              {item.occurred_at
                ? new Date(item.occurred_at).toLocaleString()
                : ""}
            </Text>
          </View>
        ))
      )}
      {q.hasNextPage ? (
        <Button
          label="Load more"
          loading={q.isFetchingNextPage}
          onPress={() => q.fetchNextPage()}
        />
      ) : null}
    </FeatureScreen>
  );
}
