import { Stack } from "expo-router";

import { useColors } from "@/hooks/useColors";

export default function SignedInLayout() {
  const colors = useColors();
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
    </Stack>
  );
}
