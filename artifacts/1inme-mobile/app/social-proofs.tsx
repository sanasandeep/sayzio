import { useState } from "react";
import { Pressable, Text, View } from "react-native";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { listSocialProofs, createSocialProof } from "@/lib/api/socialProofs";
import { FeatureScreen } from "@/components/FeatureScreen";
import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { useWorkspace } from "@/contexts/WorkspaceContext";
import { showAlert } from "@/lib/webAlert";
export default function BuzzScreen() {
  const c = useColors();
  const qc = useQueryClient();
  const { activeWorkspace } = useWorkspace();
  const [name, setName] = useState("");
  const [type, setType] = useState("");
  const q = useQuery({
    queryKey: ["social-proofs", activeWorkspace?.id],
    queryFn: listSocialProofs,
  });
  const save = useMutation({
    mutationFn: () => createSocialProof({ name: name.trim(), type }),
    onSuccess: () => {
      setName("");
      void qc.invalidateQueries({ queryKey: ["social-proofs"] });
    },
    onError: (e: Error) => showAlert("Could not create campaign", e.message),
  });
  return (
    <FeatureScreen
      title="Buzz campaigns"
      loading={q.isLoading}
      error={q.error}
      retry={() => q.refetch()}
    >
      <TextField
        label="Campaign name"
        value={name}
        onChangeText={setName}
        maxLength={200}
      />
      <View style={{ flexDirection: "row", gap: 8, flexWrap: "wrap" }}>
        {q.data?.types.map((t) => (
          <Pressable
            key={t.type}
            accessibilityRole="radio"
            accessibilityState={{ checked: type === t.type }}
            aria-checked={type === t.type}
            onPress={() => setType(t.type)}
            style={{
              padding: 12,
              borderWidth: 1,
              borderColor: type === t.type ? c.primary : c.border,
              borderRadius: c.radius,
            }}
          >
            <Text style={{ color: c.foreground }}>{t.label}</Text>
          </Pressable>
        ))}
      </View>
      <Button
        label="Create campaign"
        disabled={!name.trim() || !type}
        loading={save.isPending}
        onPress={() => save.mutate()}
      />
      {!q.data?.items.length ? (
        <Text style={{ color: c.mutedForeground }}>No Buzz campaigns yet.</Text>
      ) : (
        q.data.items.map((item) => (
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
            <Text style={{ color: c.foreground, fontWeight: "700" }}>
              {item.name}
            </Text>
            <Text style={{ color: c.mutedForeground }}>
              {item.type_label} · {item.is_active ? "Active" : "Disabled"}
            </Text>
            <Text style={{ color: c.foreground }}>
              {item.impressions} views · {item.clicks} clicks ·{" "}
              {item.conversions} conversions
            </Text>
          </View>
        ))
      )}
    </FeatureScreen>
  );
}
