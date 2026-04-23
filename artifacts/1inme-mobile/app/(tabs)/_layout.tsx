import { Feather } from "@expo/vector-icons";
import { Redirect, Tabs } from "expo-router";
import { ActivityIndicator, Platform, View } from "react-native";

import { VoiceAssistant } from "@/components/VoiceAssistant";
import { useAuth } from "@/contexts/AuthContext";
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
    <>
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.mutedForeground,
        tabBarStyle: {
          backgroundColor: colors.card,
          borderTopColor: colors.border,
          borderTopWidth: 1,
          paddingTop: 6,
          paddingBottom: Platform.OS === "ios" ? 24 : 8,
          height: Platform.OS === "ios" ? 84 : 64,
        },
        tabBarLabelStyle: {
          fontFamily: "SpaceGrotesk_600SemiBold",
          fontSize: 11,
        },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: "Home",
          tabBarIcon: ({ color, size }) => (
            <Feather name="home" size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="links"
        options={{
          title: "Links",
          tabBarIcon: ({ color, size }) => (
            <Feather name="link" size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="create"
        options={{
          title: "Create",
          tabBarIcon: ({ color, size }) => (
            <Feather name="plus-circle" size={size + 6} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="inbox"
        options={{
          title: "Inbox",
          tabBarIcon: ({ color, size }) => (
            <Feather name="message-circle" size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: "Profile",
          tabBarIcon: ({ color, size }) => (
            <Feather name="user" size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="notifications"
        options={{ href: null }}
      />
    </Tabs>
    {/* Floating tap-to-talk mic, mirrors the web's voice assistant
        widget. Mounted at the tab layout level so it sits above every
        signed-in main screen but disappears on auth / lock screens. */}
    <VoiceAssistant />
    </>
  );
}
