import Feather from "@expo/vector-icons/Feather";
import { Redirect, Tabs, useRouter } from "expo-router";
import { Pressable } from "react-native";

import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { useContactAutoSync } from "@/hooks/useContactAutoSync";

export default function TabsLayout() {
  const colors = useColors();
  const router = useRouter();
  const { ready, user } = useAuth();

  // Near-instant contacts sync while signed in: import the device address book
  // on open / foreground and trigger the account's Google Contacts sync.
  useContactAutoSync(ready && !!user);

  if (ready && !user) return <Redirect href="/(auth)" />;

  const SearchButton = () => (
    <Pressable
      onPress={() => router.push("/search")}
      hitSlop={12}
      style={{ paddingHorizontal: 12 }}
    >
      <Feather name="search" size={22} color={colors.primary} />
    </Pressable>
  );

  return (
    <Tabs
      screenOptions={{
        headerStyle: { backgroundColor: colors.background },
        headerTitleStyle: {
          color: colors.foreground,
          fontFamily: "SpaceGrotesk_600SemiBold",
        },
        headerShadowVisible: false,
        headerRight: () => <SearchButton />,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.mutedForeground,
        tabBarStyle: {
          backgroundColor: colors.card,
          borderTopColor: colors.border,
        },
        tabBarLabelStyle: { fontFamily: "SpaceGrotesk_500Medium" },
      }}
    >
      <Tabs.Screen
        name="dialer"
        options={{
          title: "Dialer",
          tabBarIcon: ({ color, size }) => (
            <Feather name="grid" size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="contacts"
        options={{
          title: "Contacts",
          tabBarIcon: ({ color, size }) => (
            <Feather name="users" size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="caller-id"
        options={{
          title: "Caller ID",
          tabBarIcon: ({ color, size }) => (
            <Feather name="search" size={size} color={color} />
          ),
        }}
      />
    </Tabs>
  );
}
