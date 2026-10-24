import { Text, View, Switch } from "react-native";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { aiStaff } from "@/lib/api/aiStaff";
import { FeatureScreen } from "@/components/FeatureScreen";
import { Button } from "@/components/Button";
import { useWorkspace } from "@/contexts/WorkspaceContext";
import { useColors } from "@/hooks/useColors";
import { showAlert } from "@/lib/webAlert";
export default function AiStaffScreen() {
  const c = useColors();
  const qc = useQueryClient();
  const { activeWorkspace } = useWorkspace();
  const q = useQuery({
    queryKey: ["ai-staff", activeWorkspace?.id],
    queryFn: aiStaff.index,
  });
  const suggestions = useQuery({
    queryKey: ["ai-staff-suggestions", activeWorkspace?.id],
    queryFn: () => aiStaff.suggestions(),
  });
  const update = useMutation({
    mutationFn: (v: { id: number; enabled: boolean }) =>
      aiStaff.setEnabled(v.id, v.enabled),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["ai-staff"] }),
    onError: error,
  });
  const decision = useMutation({
    mutationFn: (v: { id: number; apply: boolean }) =>
      v.apply ? aiStaff.applySuggestion(v.id) : aiStaff.dismissSuggestion(v.id),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: ["ai-staff-suggestions"] }),
    onError: error,
  });
  function error(e: Error) {
    showAlert("Could not update AI staff", e.message);
  }
  return (
    <FeatureScreen
      title="AI Staff"
      loading={q.isLoading}
      error={q.error}
      retry={() => q.refetch()}
    >
      {!q.data?.staff.length ? (
        <Text style={{ color: c.mutedForeground }}>
          No AI staff configured yet.
        </Text>
      ) : (
        q.data.staff.map((s) => (
          <View
            key={s.id}
            style={{
              padding: 16,
              gap: 8,
              borderWidth: 1,
              borderColor: c.border,
              borderRadius: c.radius,
            }}
          >
            <Text style={{ color: c.foreground, fontWeight: "700" }}>
              {s.name}
            </Text>
            <Text style={{ color: c.mutedForeground }}>
              {s.domain_label}
              {!s.plan_allowed ? " · Upgrade required" : ""}
            </Text>
            <Text style={{ color: c.foreground }}>{s.instructions}</Text>
            <Switch
              accessibilityLabel={`Enable ${s.name}`}
              value={!s.is_disabled}
              disabled={!s.plan_allowed || update.isPending}
              onValueChange={(enabled) => update.mutate({ id: s.id, enabled })}
            />
          </View>
        ))
      )}
      <Text style={{ color: c.foreground, fontWeight: "700" }}>
        Suggestions awaiting your decision
      </Text>
      {suggestions.isError ? (
        <Button
          label="Retry suggestions"
          onPress={() => suggestions.refetch()}
        />
      ) : (
        suggestions.data?.suggestions
          .filter((s) => s.status === "pending")
          .map((s) => (
            <View
              key={s.id}
              style={{
                padding: 16,
                gap: 10,
                borderWidth: 1,
                borderColor: c.border,
                borderRadius: c.radius,
              }}
            >
              <Text style={{ color: c.foreground, fontWeight: "700" }}>
                {s.title}
              </Text>
              <Text style={{ color: c.mutedForeground }}>{s.message}</Text>
              <Button
                label="Review and apply"
                disabled={decision.isPending}
                onPress={() =>
                  showAlert("Apply this suggestion?", s.message || s.title, [
                    { text: "Cancel", style: "cancel" },
                    {
                      text: "Apply",
                      onPress: () => decision.mutate({ id: s.id, apply: true }),
                    },
                  ])
                }
              />
              <Button
                label="Dismiss"
                variant="secondary"
                disabled={decision.isPending}
                onPress={() => decision.mutate({ id: s.id, apply: false })}
              />
            </View>
          ))
      )}
    </FeatureScreen>
  );
}
