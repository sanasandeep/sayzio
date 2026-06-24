import { Ionicons } from "@expo/vector-icons";
import { Linking, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";

// Friendly provider names for the merge copy. Mirrors PROVIDER_LABELS in
// app/oauth-callback.tsx and SOCIALS in app/(auth)/index.tsx.
const PROVIDER_LABELS: Record<string, string> = {
  google: "Google",
  apple: "Apple",
  instagram: "Instagram",
  facebook: "Facebook",
  twitter: "X",
  linkedin: "LinkedIn",
  pinterest: "Pinterest",
  tiktok: "TikTok",
};

/**
 * Inline "merge accounts?" prompt raised when a social sign-in finds the
 * provider identity (or its email) already bound to a different 1INME
 * account — the backend returns HTTP 409 `identity_taken`.
 *
 * Merging two accounts is a web-session-only flow (it requires proving
 * ownership of the other account via the challenge step), so the mobile app
 * can't complete it in-process. Instead we explain the conflict and hand the
 * user off to the web merge flow at /user/merge.
 */
export function SocialMergePrompt({
  provider,
  onDismiss,
}: {
  provider: string;
  onDismiss: () => void;
}) {
  const colors = useColors();
  const label = PROVIDER_LABELS[provider] ?? "That social";

  const openWebMerge = () => {
    const url = `${getBaseUrl()}/user/merge`;
    void Linking.openURL(url);
  };

  return (
    <View
      style={[
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.primary,
          borderRadius: colors.radius,
        },
      ]}
    >
      <View style={styles.header}>
        <Ionicons name="git-merge-outline" size={18} color={colors.primary} />
        <Text style={[styles.title, { color: colors.foreground }]}>
          Account already exists
        </Text>
      </View>
      <Text style={[styles.body, { color: colors.mutedForeground }]}>
        That {label} account already belongs to another 1INME account. You can
        merge them on the web — open the merge page, sign in to the account you
        want to keep, and confirm. This can&apos;t be undone.
      </Text>
      <View style={styles.actions}>
        <Button label="Merge on the web" onPress={openWebMerge} />
        <Button label="Not now" variant="outline" onPress={onDismiss} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    padding: 16,
    gap: 10,
    marginTop: 16,
  },
  header: { flexDirection: "row", alignItems: "center", gap: 8 },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 20,
  },
  actions: { gap: 10, marginTop: 4 },
});
