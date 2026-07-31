import {
  SpaceGrotesk_400Regular,
  SpaceGrotesk_500Medium,
  SpaceGrotesk_600SemiBold,
  SpaceGrotesk_700Bold,
  useFonts,
} from "@expo-google-fonts/space-grotesk";
import Feather from "@expo/vector-icons/Feather";
import Ionicons from "@expo/vector-icons/Ionicons";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Stack } from "expo-router";
import * as SplashScreen from "expo-splash-screen";
import { useEffect } from "react";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import { SafeAreaProvider } from "react-native-safe-area-context";

import { ErrorBoundary } from "@/components/ErrorBoundary";
import { AuthProvider, useAuth } from "@/contexts/AuthContext";
import { ThemeProvider } from "@/contexts/ThemeContext";
import { useColors } from "@/hooks/useColors";
import { useWebFocusRing } from "@/hooks/useWebFocusRing";
import { syncDialerDeviceRegistration } from "@/lib/api/dialerDevice";

SplashScreen.preventAutoHideAsync();

const queryClient = new QueryClient();

// Records this install with the backend at sign-in/unlock, independent of
// push-token registration, so the Zio Browser Dialer pane knows a phone is
// linked even when notification permission was denied (task #6353).
function DialerDeviceRegistrar() {
  const { user, token, locked } = useAuth();
  useEffect(() => {
    if (user && token && !locked) {
      void syncDialerDeviceRegistration();
    }
  }, [user, token, locked]);
  return null;
}

function RootLayoutNav() {
  const colors = useColors();
  // Inject the app-wide web keyboard :focus-visible ring stylesheet once and
  // keep its colour tracking the active theme (no-op on native).
  useWebFocusRing();
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
      <Stack.Screen name="(auth)" options={{ headerShown: false }} />
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen
        name="search"
        options={{ title: "Search", presentation: "modal" }}
      />
      <Stack.Screen name="dialer-profile" options={{ title: "Profile" }} />
      <Stack.Screen name="call/incoming" options={{ headerShown: false }} />
      <Stack.Screen name="contacts/[id]" options={{ title: "Contact" }} />
      <Stack.Screen name="contacts/new" options={{ title: "New contact" }} />
      <Stack.Screen name="contacts/import" options={{ title: "Import contacts" }} />
      <Stack.Screen
        name="contacts/google-sync"
        options={{ title: "Google sync" }}
      />
      <Stack.Screen
        name="contact-duplicates"
        options={{ title: "Duplicate contacts" }}
      />
      <Stack.Screen name="info" options={{ headerShown: false }} />
      <Stack.Screen name="oauth-callback" options={{ headerShown: false }} />
      <Stack.Screen name="events/[alias]" options={{ title: "Event" }} />
      <Stack.Screen name="events/my-tickets" options={{ title: "My tickets" }} />
      <Stack.Screen
        name="events/ticket/[alias]/[code]"
        options={{ title: "Ticket" }}
      />
    </Stack>
  );
}

export default function RootLayout() {
  const [fontsLoaded, fontError] = useFonts({
    SpaceGrotesk_400Regular,
    SpaceGrotesk_500Medium,
    SpaceGrotesk_600SemiBold,
    SpaceGrotesk_700Bold,
    ...Ionicons.font,
    ...Feather.font,
  });

  useEffect(() => {
    if (fontsLoaded || fontError) {
      SplashScreen.hideAsync();
    }
  }, [fontsLoaded, fontError]);

  if (!fontsLoaded && !fontError) return null;

  return (
    <SafeAreaProvider>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider>
          <ErrorBoundary>
            <AuthProvider>
              <GestureHandlerRootView style={{ flex: 1 }}>
                <RootLayoutNav />
                <DialerDeviceRegistrar />
              </GestureHandlerRootView>
            </AuthProvider>
          </ErrorBoundary>
        </ThemeProvider>
      </QueryClientProvider>
    </SafeAreaProvider>
  );
}
