import { Redirect, Stack } from "expo-router";

import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";

export default function AuthLayout() {
  const colors = useColors();
  const { ready, user } = useAuth();

  if (ready && user) return <Redirect href="/(tabs)/dialer" />;

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: colors.background },
        headerTitleStyle: {
          color: colors.foreground,
          fontFamily: "SpaceGrotesk_600SemiBold",
        },
        headerTintColor: colors.primary,
        contentStyle: { backgroundColor: colors.background },
      }}
    >
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Screen name="verify" options={{ title: "Verify code" }} />
      <Stack.Screen
        name="cancel-change"
        options={{ title: "Cancel pending change" }}
      />
    </Stack>
  );
}
