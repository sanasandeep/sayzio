import { Redirect, Tabs } from "expo-router";
import {
  AccessibilityInfo,
  ActivityIndicator,
  View,
  useWindowDimensions,
} from "react-native";
import Animated, {
  interpolate,
  useAnimatedStyle,
} from "react-native-reanimated";
import { useEffect, useState } from "react";
import React from "react";

import { DrawerSidebar } from "@/components/DrawerSidebar";
import { FloatingTabBar } from "@/components/FloatingTabBar";
import { PinnedTopBar } from "@/components/PinnedTopBar";
import { VoiceAssistant } from "@/components/VoiceAssistant";
import { useAuth } from "@/contexts/AuthContext";
import { DrawerProvider, useDrawer } from "@/contexts/DrawerContext";
import { WorkspaceProvider } from "@/contexts/WorkspaceContext";
import { TabBarProvider } from "@/contexts/TabBarContext";
import { useColors } from "@/hooks/useColors";

const DRAWER_WIDTH_FRAC = 0.78;
const MAX_DRAWER_W = 320;

function AnimatedContent({ children }: { children: React.ReactNode }) {
  const { contentProgress, isOpen } = useDrawer();
  const { width } = useWindowDimensions();
  const drawerW = Math.min(width * DRAWER_WIDTH_FRAC, MAX_DRAWER_W);
  const [reduceMotion, setReduceMotion] = useState(false);

  useEffect(() => {
    AccessibilityInfo.isReduceMotionEnabled()
      .then(setReduceMotion)
      .catch(() => {});
    const sub = AccessibilityInfo.addEventListener(
      "reduceMotionChanged",
      setReduceMotion,
    );
    return () => sub.remove();
  }, []);

  const contentStyle = useAnimatedStyle(() => {
    const p = contentProgress.value;
    if (reduceMotion) {
      return {
        transform: [],
        borderRadius: 0,
      };
    }
    const scale = interpolate(p, [0, 1], [1, 0.86]);
    const tx = interpolate(p, [0, 1], [0, drawerW * 0.18]);
    const rotateY = interpolate(p, [0, 1], [0, 6]);
    const radius = interpolate(p, [0, 1], [0, 20]);

    return {
      transform: [
        { perspective: 1400 },
        { translateX: tx },
        { scale },
        { rotateY: `${rotateY}deg` },
      ],
      borderRadius: radius,
      overflow: "hidden" as const,
    };
  });

  return (
    <Animated.View
      style={[{ flex: 1 }, contentStyle]}
      pointerEvents={isOpen ? "none" : "auto"}
    >
      {children}
    </Animated.View>
  );
}

function SignedInLayout() {
  return (
    <WorkspaceProvider>
      <TabBarProvider>
        <AnimatedContent>
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
        </AnimatedContent>

        {/* Pinned top bar — hamburger chip + centered logo + bell chip. */}
        <PinnedTopBar />

        {/* Floating glassmorphic tab bar — rendered at the layout level so it
            sits above all tab screens and overlays content uniformly. */}
        <FloatingTabBar />

        {/* Slide-in drawer navigation (menu button in each tab header). */}
        <DrawerSidebar />

        {/* Floating tap-to-talk mic, mirrors the web's voice assistant
            widget. Mounted here so it stays above every signed-in screen. */}
        <VoiceAssistant />
      </TabBarProvider>
    </WorkspaceProvider>
  );
}

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
      <SignedInLayout />
    </DrawerProvider>
  );
}
