import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Feather } from "@expo/vector-icons";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { getLink } from "@/lib/api/links";
import { detachPageTemplate } from "@/lib/api/pageTemplates";
import { showAlert } from "@/lib/webAlert";

/**
 * Gate for a styling settings screen. When the parent biolink is
 * design-locked to a template, the styling form is replaced with a lock
 * notice + a "Detach from template" action (parity with the web editor,
 * which hides the Appearance / Layout / Block theme / Themes tabs while
 * locked). Content-level screens should NOT be wrapped in this gate.
 */
export function DesignLockGate({
  linkId,
  children,
}: {
  linkId: number;
  children: React.ReactNode;
}) {
  const colors = useColors();
  const qc = useQueryClient();
  const q = useQuery({
    queryKey: ["link", linkId],
    queryFn: () => getLink(linkId),
    enabled: Number.isFinite(linkId),
  });

  const detach = useMutation({
    mutationFn: () => detachPageTemplate(linkId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["link", linkId] });
    },
  });

  if (q.isLoading) {
    return (
      <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (!q.data?.design_locked) {
    return <>{children}</>;
  }

  const templateName = q.data.design_lock?.template_name ?? "template";

  return (
    <View style={styles.wrap} testID="design-lock-gate">
      <View
        style={[
          styles.card,
          { backgroundColor: colors.card, borderColor: "rgba(245,158,11,0.4)" },
        ]}
      >
        <Feather name="lock" size={22} color="#f59e0b" />
        <Text style={[styles.title, { color: colors.foreground }]}>
          Design locked by "{templateName}"
        </Text>
        <Text style={[styles.body, { color: colors.mutedForeground }]}>
          This page's styling follows its template design. Your content stays
          fully editable. Detach from the template to unlock all styling
          controls — the page keeps its current look.
        </Text>
        <Button
          label={detach.isPending ? "Detaching…" : "Detach from template"}
          variant="cta"
          loading={detach.isPending}
          onPress={() =>
            showAlert(
              "Detach from template?",
              "Future template design updates will no longer apply, and all styling controls will be unlocked.",
              [
                { text: "Cancel", style: "cancel" },
                { text: "Detach", onPress: () => detach.mutate() },
              ],
            )
          }
          testID="design-lock-detach"
        />
        {detach.isError ? (
          <Text style={[styles.body, { color: colors.destructive }]}>
            Couldn't detach. Please try again.
          </Text>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, padding: 20, justifyContent: "center" },
  card: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 20,
    alignItems: "center",
    gap: 10,
  },
  title: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 16,
    textAlign: "center",
  },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
    textAlign: "center",
  },
});
