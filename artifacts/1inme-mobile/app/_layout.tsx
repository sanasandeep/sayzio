import {
  SpaceGrotesk_400Regular,
  SpaceGrotesk_500Medium,
  SpaceGrotesk_600SemiBold,
  SpaceGrotesk_700Bold,
  useFonts,
} from "@expo-google-fonts/space-grotesk";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {
  setAuthTokenGetter,
  setBaseUrl,
} from "@workspace/api-client-react";
import { Stack } from "expo-router";
import * as SplashScreen from "expo-splash-screen";
import React, { useEffect } from "react";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import { KeyboardProvider } from "react-native-keyboard-controller";
import { SafeAreaProvider } from "react-native-safe-area-context";

import { DeepLinkRouter } from "@/components/DeepLinkRouter";
import { ErrorBoundary } from "@/components/ErrorBoundary";
import { AuthProvider } from "@/contexts/AuthContext";
import { ThemeProvider } from "@/contexts/ThemeContext";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import { initializeRevenueCat, SubscriptionProvider } from "@/lib/revenuecat";
import { getToken } from "@/lib/secure";

setBaseUrl(getBaseUrl());
setAuthTokenGetter(async () => (await getToken()) ?? null);

// In-app purchases are optional — if the public RevenueCat keys haven't
// been set yet (early development, fresh repl), this is a silent no-op
// and the plans screen will surface a friendly message at use time.
try {
  initializeRevenueCat();
} catch {
  /* swallow — see lib/revenuecat.tsx for messaging fallback */
}

SplashScreen.preventAutoHideAsync();

const queryClient = new QueryClient();

function RootLayoutNav() {
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
        headerBackTitle: "Back",
      }}
    >
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Screen name="onboarding" options={{ headerShown: false }} />
      <Stack.Screen name="(auth)" options={{ headerShown: false }} />
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen name="info" options={{ headerShown: false }} />
      <Stack.Screen name="biolink/[handle]" options={{ headerShown: false }} />
      <Stack.Screen name="oauth-callback" options={{ headerShown: false }} />
      <Stack.Screen name="plans" options={{ title: "Plans & billing" }} />
      <Stack.Screen name="dialer" options={{ title: "Dialer" }} />
      <Stack.Screen name="call/active" options={{ headerShown: false }} />
      <Stack.Screen name="call/incoming" options={{ headerShown: false }} />
    </Stack>
  );
}

export default function RootLayout() {
  const [fontsLoaded, fontError] = useFonts({
    SpaceGrotesk_400Regular,
    SpaceGrotesk_500Medium,
    SpaceGrotesk_600SemiBold,
    SpaceGrotesk_700Bold,
  });

  useEffect(() => {
    if (fontsLoaded || fontError) {
      SplashScreen.hideAsync();
    }
  }, [fontsLoaded, fontError]);

  if (!fontsLoaded && !fontError) return null;

  return (
    <SafeAreaProvider>
      <ErrorBoundary>
        <QueryClientProvider client={queryClient}>
          <ThemeProvider>
            <AuthProvider>
              <SubscriptionProvider>
                <GestureHandlerRootView>
                  <KeyboardProvider>
                    <DeepLinkRouter />
                    <RootLayoutNav />
                  </KeyboardProvider>
                </GestureHandlerRootView>
              </SubscriptionProvider>
            </AuthProvider>
          </ThemeProvider>
        </QueryClientProvider>
      </ErrorBoundary>
    </SafeAreaProvider>
  );
}
