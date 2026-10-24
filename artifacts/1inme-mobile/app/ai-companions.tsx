import { useState } from "react";
import { Pressable, Text, View } from "react-native";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  listBiolinkCompanions,
  listAiPersonas,
  createBiolinkCompanion,
} from "@/lib/api/aiCompanions";
import { FeatureScreen } from "@/components/FeatureScreen";
import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useWorkspace } from "@/contexts/WorkspaceContext";
import { useColors } from "@/hooks/useColors";
import { showAlert } from "@/lib/webAlert";
export default function CompanionsScreen() {
  const c = useColors();
  const qc = useQueryClient();
  const { activeWorkspace } = useWorkspace();
  const [name, setName] = useState("");
  const [persona, setPersona] = useState<number | null>(null);
  const q = useQuery({
    queryKey: ["ai-companions", activeWorkspace?.id],
    queryFn: listBiolinkCompanions,
  });
  const personas = useQuery({
    queryKey: ["ai-personas", activeWorkspace?.id],
    queryFn: listAiPersonas,
  });
  const create = useMutation({
    mutationFn: () =>
      createBiolinkCompanion({ name: name.trim(), persona_id: persona! }),
    onSuccess: () => {
      setName("");
      void qc.invalidateQueries({ queryKey: ["ai-companions"] });
    },
    onError: (e: Error) => showAlert("Could not create chat widget", e.message),
  });
  return (
    <FeatureScreen
      title="Chat widgets"
      loading={q.isLoading}
      error={q.error || personas.error}
      retry={() => {
        q.refetch();
        personas.refetch();
      }}
    >
      <TextField
        label="Widget name"
        value={name}
        onChangeText={setName}
        maxLength={200}
      />
      <Text style={{ color: c.foreground }}>Choose an enabled persona</Text>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
        {personas.data?.items
          .filter((p) => !p.is_disabled)
          .map((p) => (
            <Pressable
              key={p.id}
              accessibilityRole="radio"
              accessibilityState={{ checked: persona === p.id }}
              aria-checked={persona === p.id}
              onPress={() => setPersona(p.id)}
              style={{
                padding: 12,
                borderWidth: 1,
                borderColor: persona === p.id ? c.primary : c.border,
                borderRadius: c.radius,
              }}
            >
              <Text style={{ color: c.foreground }}>{p.name}</Text>
            </Pressable>
          ))}
      </View>
      <Button
        label="Create chat widget"
        loading={create.isPending}
        disabled={!name.trim() || !persona}
        onPress={() => create.mutate()}
      />
      {!q.data?.items.length ? (
        <Text style={{ color: c.mutedForeground }}>
          No chat widgets yet. Create a persona first if none are available.
        </Text>
      ) : (
        q.data.items.map((item) => (
          <View
            key={item.id}
            style={{
              padding: 16,
              borderWidth: 1,
              borderColor: c.border,
              borderRadius: c.radius,
            }}
          >
            <Text style={{ color: c.foreground, fontWeight: "700" }}>
              {item.name}
            </Text>
            <Text style={{ color: c.mutedForeground }}>
              {item.is_disabled ? "Disabled" : "Enabled"}
            </Text>
          </View>
        ))
      )}
    </FeatureScreen>
  );
}
