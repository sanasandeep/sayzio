import { useState } from "react";
import { Pressable, Text, View } from "react-native";
import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
} from "@tanstack/react-query";
import { FeatureScreen } from "@/components/FeatureScreen";
import { TextField } from "@/components/TextField";
import { Button } from "@/components/Button";
import { useWorkspace } from "@/contexts/WorkspaceContext";
import { useColors } from "@/hooks/useColors";
import { apiFetch } from "@/lib/api";
import { showAlert } from "@/lib/webAlert";
type Lead = {
  source_type: string;
  source_id: number;
  source_label: string;
  name: string | null;
  email: string | null;
  phone: string | null;
  context: string | null;
};
type Page = {
  items: Lead[];
  sources: Record<string, string>;
  meta: { current_page: number; last_page: number; total: number };
};
export default function LeadsScreen() {
  const c = useColors();
  const qc = useQueryClient();
  const { activeWorkspace } = useWorkspace();
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [source, setSource] = useState("");
  const q = useInfiniteQuery({
    queryKey: ["leads", activeWorkspace?.id, query, source],
    queryFn: async ({ pageParam }) =>
      (
        await apiFetch<{ data: Page }>(
          `/leads?${new URLSearchParams({ q: query, ...(source ? { source } : {}), page: String(pageParam) })}`,
        )
      ).data,
    initialPageParam: 1,
    getNextPageParam: (last) =>
      last.meta.current_page < last.meta.last_page
        ? last.meta.current_page + 1
        : undefined,
  });
  const decision = useMutation({
    mutationFn: (v: { lead: Lead; decision: "approve" | "dismiss" }) =>
      apiFetch("/leads/decision", {
        method: "POST",
        body: JSON.stringify({
          source_type: v.lead.source_type,
          source_id: v.lead.source_id,
          decision: v.decision,
        }),
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["leads"] });
      void qc.invalidateQueries({ queryKey: ["contacts"] });
    },
    onError: (e: Error) => showAlert("Could not update lead", e.message),
  });
  const items = q.data?.pages.flatMap((p) => p.items) ?? [];
  return (
    <FeatureScreen
      title="Leads"
      loading={q.isLoading}
      error={q.error}
      retry={() => q.refetch()}
    >
      <TextField label="Search leads" value={search} onChangeText={setSearch} />
      <Button label="Search" onPress={() => setQuery(search.trim())} />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
        {Object.entries({
          "": "All sources",
          ...(q.data?.pages[0]?.sources ?? {}),
        }).map(([key, label]) => (
          <Pressable
            key={key}
            accessibilityRole="radio"
            accessibilityState={{ checked: source === key }}
            aria-checked={source === key}
            onPress={() => setSource(key)}
            style={{
              padding: 10,
              borderWidth: 1,
              borderColor: source === key ? c.primary : c.border,
              borderRadius: c.radius,
            }}
          >
            <Text style={{ color: c.foreground }}>{label}</Text>
          </Pressable>
        ))}
      </View>
      {!items.length ? (
        <Text style={{ color: c.mutedForeground }}>No pending leads.</Text>
      ) : (
        items.map((item) => (
          <View
            key={`${item.source_type}-${item.source_id}`}
            style={{
              padding: 16,
              gap: 8,
              borderWidth: 1,
              borderColor: c.border,
              borderRadius: c.radius,
            }}
          >
            <Text style={{ color: c.foreground, fontWeight: "700" }}>
              {item.name || "Unnamed lead"}
            </Text>
            <Text style={{ color: c.mutedForeground }}>
              {item.source_label}
              {item.context ? ` · ${item.context}` : ""}
            </Text>
            <Text style={{ color: c.foreground }}>
              {[item.email, item.phone].filter(Boolean).join(" · ")}
            </Text>
            <Button
              label="Approve into contacts"
              disabled={decision.isPending}
              onPress={() =>
                showAlert(
                  "Approve this lead?",
                  "This creates or merges a contact.",
                  [
                    { text: "Cancel", style: "cancel" },
                    {
                      text: "Approve",
                      onPress: () =>
                        decision.mutate({ lead: item, decision: "approve" }),
                    },
                  ],
                )
              }
            />
            <Button
              label="Dismiss"
              variant="secondary"
              disabled={decision.isPending}
              onPress={() =>
                showAlert(
                  "Dismiss this lead?",
                  "It will leave your pending review queue.",
                  [
                    { text: "Cancel", style: "cancel" },
                    {
                      text: "Dismiss",
                      onPress: () =>
                        decision.mutate({ lead: item, decision: "dismiss" }),
                    },
                  ],
                )
              }
            />
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
