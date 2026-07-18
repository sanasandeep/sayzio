import { Redirect, Stack } from "expo-router";

import { useAuth } from "@/contexts/AuthContext";
import { ForceDarkTheme } from "@/contexts/ThemeContext";
import colorTokens from "@/constants/colors";

const dk = colorTokens.dark;

export default function AuthLayout() {
  const { ready, user } = useAuth();

  if (ready && user) return <Redirect href="/(tabs)/dialer" />;

  return (
    <ForceDarkTheme>
      <Stack
        screenOptions={{
          headerStyle: { backgroundColor: "#0b0e1a" },
          headerTitleStyle: {
            color: dk.foreground,
            fontFamily: "SpaceGrotesk_600SemiBold",
          },
          headerTintColor: dk.primary,
          contentStyle: { backgroundColor: "#0b0e1a" },
        }}
      >
        <Stack.Screen name="index" options={{ headerShown: false }} />
        <Stack.Screen name="verify" options={{ title: "Verify code" }} />
        <Stack.Screen
          name="cancel-change"
          options={{ title: "Cancel pending change" }}
        />
      </Stack>
    </ForceDarkTheme>
  );
}
