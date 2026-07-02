import { Ionicons } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";

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
 * provider identity (or its email) already bound to a different Sayzio
 * account — the backend returns HTTP 409 `identity_taken`.
 *
 * Merging is completed natively (Task #2174): the in-app merge flow at
 * /account-merge proves ownership of the other account via an OTP challenge
 * and then runs the merge over the REST API. We send the user there instead
 * of bouncing them out to the web /user/merge page.
 */
export function SocialMergePrompt({
  provider,
  onDismiss,
}: {
  provider: string;
  onDismiss: () => void;
}) {
  const colors = useColors();
  const router = useRouter();
  const label = PROVIDER_LABELS[provider] ?? "That social";

  const openMerge = () => {
    onDismiss();
    router.push("/account-merge");
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
        That {label} account already belongs to another Sayzio account. You can
        merge them here — we&apos;ll send a code to the other account to confirm
        it&apos;s yours, then move everything across. This can&apos;t be undone.
      </Text>
      <View style={styles.actions}>
        <Button label="Merge accounts" onPress={openMerge} />
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
