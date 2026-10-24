import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Pressable, Text } from "react-native";
import { useColors } from "@/hooks/useColors";
import { updateLink, type Link } from "@/lib/api/links";
import { showAlert } from "@/lib/webAlert";

export function LinkStatusControl({ link }: { link: Link }) {
  const colors = useColors();
  const qc = useQueryClient();
  const save = useMutation({
    mutationFn: () => updateLink(link.id, { is_active: !link.is_active }),
    onSuccess: () =>
      Promise.all([
        qc.invalidateQueries({ queryKey: ["links"] }),
        qc.invalidateQueries({ queryKey: ["link", link.id] }),
        qc.invalidateQueries({ queryKey: ["dashboard"] }),
        qc.invalidateQueries({ queryKey: ["creator-settings-links"] }),
      ]),
    onError: (e: Error) => showAlert("Could not change link status", e.message),
  });
  return (
    <Pressable
      accessibilityRole="switch"
      accessibilityState={{ checked: link.is_active, disabled: save.isPending }}
      aria-checked={link.is_active}
      aria-disabled={save.isPending}
      accessibilityLabel={`${link.is_active ? "Disable" : "Enable"} ${link.title || link.alias}`}
      disabled={save.isPending}
      onPress={(event) => {
        event.stopPropagation();
        save.mutate();
      }}
      style={{
        minHeight: 44,
        paddingHorizontal: 10,
        justifyContent: "center",
        borderWidth: 1,
        borderColor: colors.border,
        borderRadius: colors.radius,
      }}
    >
      <Text
        style={{
          color: link.is_active ? colors.primary : colors.mutedForeground,
        }}
      >
        {save.isPending ? "Saving…" : link.is_active ? "Enabled" : "Disabled"}
      </Text>
    </Pressable>
  );
}
