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
import {
  setAuthTokenGetter,
  setBaseUrl,
} from "@workspace/api-client-react";
import { Stack, usePathname } from "expo-router";
import { VoiceAssistant } from "@/components/VoiceAssistant";
import * as SplashScreen from "expo-splash-screen";
import React, { useEffect } from "react";
import { View } from "react-native";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import { KeyboardProvider } from "react-native-keyboard-controller";
import { SafeAreaProvider } from "react-native-safe-area-context";

import { DeepLinkRouter } from "@/components/DeepLinkRouter";
import { ErrorBoundary } from "@/components/ErrorBoundary";
import { IdleLockWarning } from "@/components/IdleLockWarning";
import { ImpersonationBanner } from "@/components/ImpersonationBanner";
import { AuthProvider, useAuth } from "@/contexts/AuthContext";
import { ThemeProvider } from "@/contexts/ThemeContext";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import {
  addPushResponseListener,
  configurePushHandler,
  syncPushRegistration,
} from "@/lib/push";
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

// Wraps the app in a transparent View that captures bubble-phase touches so
// any tap anywhere in the UI resets the idle re-lock timer. We also reset
// the timer on navigation (pathname changes) so swipes/back gestures count.
function ActivityWatcher({ children }: { children: React.ReactNode }) {
  const { noteActivity } = useAuth();
  const pathname = usePathname();
  useEffect(() => {
    noteActivity();
  }, [pathname, noteActivity]);
  return (
    <View style={{ flex: 1 }} onTouchStart={noteActivity}>
      {children}
    </View>
  );
}

/**
 * The Voice Assistant floating mic + sheet should appear on every
 * signed-in tab screen, but stay hidden on auth, lock, onboarding,
 * and modal flows where it would conflict visually.
 */
function GlobalVoiceAssistant() {
  const { user, locked } = useAuth();
  const pathname = usePathname() ?? "";
  if (!user || locked) return null;
  // Tab routes either start with the (tabs) group prefix or land at
  // one of the five known tab paths after expo-router strips the group.
  const isTabRoute =
    pathname === "/" ||
    pathname.startsWith("/(tabs)") ||
    pathname === "/links" ||
    pathname === "/create" ||
    pathname === "/inbox" ||
    pathname === "/profile";
  if (!isTabRoute) return null;
  return <VoiceAssistant />;
}

function PushRegistrar() {
  const { user, token, locked } = useAuth();

  useEffect(() => {
    configurePushHandler();
    const sub = addPushResponseListener();
    return () => sub.remove();
  }, []);

  useEffect(() => {
    if (user && token && !locked) {
      void syncPushRegistration();
    }
  }, [user, token, locked]);

  return null;
}

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
      <Stack.Screen name="setup" options={{ headerShown: false, gestureEnabled: false }} />
      <Stack.Screen name="(auth)" options={{ headerShown: false }} />
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen name="lock" options={{ headerShown: false, gestureEnabled: false }} />
      <Stack.Screen name="info" options={{ headerShown: false }} />
      <Stack.Screen name="biolink/[handle]" options={{ headerShown: false }} />
      <Stack.Screen name="reviews/[alias]" options={{ title: "Reviews" }} />
      <Stack.Screen name="paid-page/[alias]" options={{ headerShown: false }} />
      <Stack.Screen name="oauth-callback" options={{ headerShown: false }} />
      <Stack.Screen name="account-merge" options={{ title: "Merge accounts" }} />
      <Stack.Screen name="resume" options={{ title: "AI Resume" }} />
      <Stack.Screen name="payouts" options={{ title: "Earnings & Payouts" }} />
      <Stack.Screen name="qr-studio" options={{ title: "QR studio" }} />
      <Stack.Screen name="brand-kits" options={{ title: "AI Brand Kit" }} />
      <Stack.Screen
        name="marketing-strategist/index"
        options={{ title: "Performer Specialist" }}
      />
      <Stack.Screen
        name="marketing-strategist/new"
        options={{ title: "New strategy" }}
      />
      <Stack.Screen
        name="marketing-strategist/[id]"
        options={{ title: "Strategy" }}
      />
      <Stack.Screen name="teardown/index" options={{ title: "Competitor Teardown" }} />
      <Stack.Screen name="teardown/[id]" options={{ title: "Teardown" }} />
      <Stack.Screen name="backlinks" options={{ title: "Backlinks" }} />
      <Stack.Screen name="visitors" options={{ title: "Visitors" }} />
      <Stack.Screen name="team" options={{ title: "Team" }} />
      <Stack.Screen name="client-portals" options={{ title: "Client portals" }} />
      <Stack.Screen name="client-portals/[id]" options={{ title: "Portal" }} />
      <Stack.Screen name="invoices" options={{ title: "Invoices" }} />
      <Stack.Screen name="invoices/new" options={{ title: "New invoice" }} />
      <Stack.Screen name="invoices/[id]" options={{ title: "Invoice" }} />
      <Stack.Screen name="delivery-projects" options={{ title: "Delivery projects" }} />
      <Stack.Screen name="delivery-projects/[id]" options={{ title: "Project" }} />
      <Stack.Screen name="insider" options={{ title: "Insider" }} />
      <Stack.Screen name="leaderboard" options={{ title: "Leaderboard" }} />
      <Stack.Screen name="vault-audit" options={{ title: "Vault audit" }} />
      <Stack.Screen name="upgrade" options={{ title: "Upgrade" }} />
      <Stack.Screen name="plans" options={{ title: "Plans & billing" }} />
      <Stack.Screen name="billing/downgrade" options={{ title: "Downgrade plan" }} />
      <Stack.Screen name="coin-packages" options={{ title: "Coin packages" }} />
      <Stack.Screen name="api-usage" options={{ title: "API usage" }} />
      <Stack.Screen name="calendars/index" options={{ title: "My Calendar" }} />
      <Stack.Screen name="calendars/[id]" options={{ title: "Calendar" }} />
      <Stack.Screen name="calendars/edit" options={{ title: "Calendar" }} />
      <Stack.Screen name="calendars/event" options={{ title: "Event" }} />
      <Stack.Screen name="events/index" options={{ title: "Events near you" }} />
      <Stack.Screen name="events/[alias]" options={{ title: "Event" }} />
      <Stack.Screen name="events/my-tickets" options={{ title: "My tickets" }} />
      <Stack.Screen name="events/ticket/[alias]/[code]" options={{ title: "Ticket" }} />
      <Stack.Screen name="events/tiers/[linkId]" options={{ title: "Ticketing" }} />
      <Stack.Screen
        name="events/checkin/[linkId]"
        options={{ title: "Door check-in", headerShown: false }}
      />
      <Stack.Screen name="whatsapp-verify" options={{ title: "Verify WhatsApp" }} />
      <Stack.Screen name="identifiers" options={{ title: "Linked emails & phones" }} />
      <Stack.Screen name="coming-soon" options={{ title: "Coming soon" }} />
      <Stack.Screen
        name="dashboard-customize"
        options={{ title: "Customize dashboard" }}
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
      <ErrorBoundary>
        <QueryClientProvider client={queryClient}>
          <ThemeProvider>
            <AuthProvider>
              <SubscriptionProvider>
                <GestureHandlerRootView>
                  <KeyboardProvider>
                    <DeepLinkRouter />
                    <ActivityWatcher>
                      <RootLayoutNav />
                      <ImpersonationBanner />
                      <GlobalVoiceAssistant />
                      <PushRegistrar />
                      <IdleLockWarning />
                    </ActivityWatcher>
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
