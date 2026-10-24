import { useEffect, useState } from "react";
import { Text, View } from "react-native";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FeatureScreen } from "@/components/FeatureScreen";
import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useWorkspace } from "@/contexts/WorkspaceContext";
import { useColors } from "@/hooks/useColors";
import { apiFetch } from "@/lib/api";
import { showAlert } from "@/lib/webAlert";
type Pixels = {
  meta_id: string | null;
  tiktok_id: string | null;
  google_id: string | null;
  google_label: string | null;
};
export default function PixelScreen() {
  const { activeWorkspace } = useWorkspace();
  return (
    <PixelForm key={activeWorkspace?.id} workspaceId={activeWorkspace?.id} />
  );
}
function PixelForm({ workspaceId }: { workspaceId?: number }) {
  const c = useColors();
  const qc = useQueryClient();
  const [values, setValues] = useState<Pixels>({
    meta_id: null,
    tiktok_id: null,
    google_id: null,
    google_label: null,
  });
  const [seeded, setSeeded] = useState(false);
  const q = useQuery({
    queryKey: ["workspace-pixels", workspaceId],
    queryFn: async () =>
      (
        await apiFetch<{ data: { pixels: Pixels } }>(
          `/workspace/pixels?workspace_id=${workspaceId}`,
        )
      ).data.pixels,
    enabled: !!workspaceId,
  });
  useEffect(() => {
    if (q.data && !seeded) {
      setValues(q.data);
      setSeeded(true);
    }
  }, [q.data, seeded]);
  const save = useMutation({
    mutationFn: () =>
      apiFetch(`/workspace/pixels?workspace_id=${workspaceId}`, {
        method: "PUT",
        body: JSON.stringify(values),
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["workspace-pixels"] });
      showAlert("Saved", "Workspace tracking pixels updated.");
    },
    onError: (e: Error) => showAlert("Could not save pixels", e.message),
  });
  return (
    <FeatureScreen
      title="Tracking pixels"
      loading={q.isLoading}
      error={q.error}
      retry={() => q.refetch()}
    >
      <Text style={{ color: c.mutedForeground }}>
        These tracking IDs apply to the current workspace.
      </Text>
      {(
        [
          ["meta_id", "Meta Pixel ID"],
          ["tiktok_id", "TikTok Pixel ID"],
          ["google_id", "Google Ads conversion ID"],
          ["google_label", "Google Ads conversion label"],
        ] as const
      ).map(([key, label]) => (
        <TextField
          key={key}
          label={label}
          value={values[key] ?? ""}
          autoCapitalize="none"
          onChangeText={(v) =>
            setValues((p) => ({ ...p, [key]: v.trim() || null }))
          }
        />
      ))}
      <Button
        label="Save tracking pixels"
        loading={save.isPending}
        disabled={!workspaceId || !seeded}
        onPress={() => save.mutate()}
      />
    </FeatureScreen>
  );
}
