import { Feather } from "@expo/vector-icons";
import { useState } from "react";
import { Platform, Pressable, Text, View } from "react-native";

import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { useRouter } from "expo-router";

// Per-session dismissal flag — reset on app relaunch, same pattern as
// VerifyEmailReminder.
let dismissedThisSession = false;

// Nudges signed-in users who have no display_name to add one via the
// Edit Profile screen. Shown on the mobile dashboard only (no-op on web),
// just below the email-verification reminder. Dismissable per session.
export function MissingNameReminder() {
  const colors = useColors();
  const router = useRouter();
  const { user } = useAuth();
  const [dismissed, setDismissed] = useState(dismissedThisSession);

  // Only show on native; the web dashboard has its own patterns.
  if (Platform.OS === "web") return null;

  const shouldShow = !!user && !user.display_name && !user.name;

  if (!shouldShow || dismissed) return null;

  const dismiss = () => {
    dismissedThisSession = true;
    setDismissed(true);
  };

  const goToProfile = () => {
    router.push("/profile-edit");
  };

  const accent = colors.primary;

  return (
    <View
      style={{
        padding: 14,
        borderRadius: 14,
        borderWidth: 1,
        borderColor: accent + "4d",
        backgroundColor: accent + "1a",
        gap: 10,
      }}
    >
      <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 10 }}>
        <Feather
          name="user"
          size={18}
          color={accent}
          style={{ marginTop: 1 }}
        />
        <Text
          style={{
            flex: 1,
            color: colors.foreground,
            fontSize: 13,
            lineHeight: 18,
          }}
        >
          Add your name so your greeting and profile show who you really are.
        </Text>
        <Pressable
          onPress={dismiss}
          hitSlop={8}
          accessibilityLabel="Dismiss this reminder"
        >
          <Feather name="x" size={16} color={colors.mutedForeground} />
        </Pressable>
      </View>

      <Pressable
        onPress={goToProfile}
        style={{
          alignSelf: "flex-start",
          flexDirection: "row",
          alignItems: "center",
          gap: 6,
          paddingHorizontal: 14,
          paddingVertical: 8,
          borderRadius: 999,
          borderWidth: 1,
          borderColor: accent + "80",
          backgroundColor: accent + "24",
        }}
        accessibilityRole="button"
      >
        <Feather name="edit-2" size={13} color={accent} />
        <Text style={{ color: accent, fontWeight: "700", fontSize: 12 }}>
          Add your name
        </Text>
      </Pressable>
    </View>
  );
}
