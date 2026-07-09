import { Redirect, Tabs } from "expo-router";
import { ActivityIndicator, View } from "react-native";

import { DrawerSidebar } from "@/components/DrawerSidebar";
import { FloatingTabBar } from "@/components/FloatingTabBar";
import { VoiceAssistant } from "@/components/VoiceAssistant";
import { useAuth } from "@/contexts/AuthContext";
import { DrawerProvider } from "@/contexts/DrawerContext";
import { TabBarProvider } from "@/contexts/TabBarContext";
import { useColors } from "@/hooks/useColors";

export default function SignedInTabsLayout() {
  const colors = useColors();
  const { ready, user } = useAuth();

  if (!ready) {
    return (
      <View
        style={{
          flex: 1,
          alignItems: "center",
          justifyContent: "center",
          backgroundColor: colors.background,
        }}
      >
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (!user) return <Redirect href="/(auth)" />;

  return (
    <DrawerProvider>
      <TabBarProvider>
        <Tabs
          screenOptions={{
            headerShown: false,
            tabBarStyle: { display: "none" },
          }}
        >
          <Tabs.Screen name="index" />
          <Tabs.Screen name="links" />
          <Tabs.Screen name="create" />
          <Tabs.Screen name="inbox" />
          <Tabs.Screen name="profile" />
          <Tabs.Screen name="notifications" options={{ href: null }} />
        </Tabs>

        {/* Floating glassmorphic tab bar — rendered at the layout level so it
            sits above all tab screens and overlays content uniformly. */}
        <FloatingTabBar />

        {/* Slide-in drawer navigation (menu button in each tab header). */}
        <DrawerSidebar />

        {/* Floating tap-to-talk mic, mirrors the web's voice assistant
            widget. Mounted here so it stays above every signed-in screen. */}
        <VoiceAssistant />
      </TabBarProvider>
    </DrawerProvider>
  );
}
