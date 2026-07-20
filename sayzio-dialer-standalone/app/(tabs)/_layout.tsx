import Feather from "@expo/vector-icons/Feather";
import { Redirect, Tabs, useRouter } from "expo-router";
import { Image, Pressable, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { DialerTabBar, TAB_BAR_CLEARANCE } from "@/components/DialerTabBar";
import { NameRequiredGate } from "@/components/NameRequiredGate";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { useContactAutoSync } from "@/hooks/useContactAutoSync";
import { useNearbyEventAlerts } from "@/hooks/useNearbyEventAlerts";

export default function TabsLayout() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { ready, user } = useAuth();

  // Near-instant contacts sync while signed in: import the device address book
  // on open / foreground and trigger the account's Google Contacts sync.
  useContactAutoSync(ready && !!user, user?.id ?? null);

  // Surfaces the real server-side `event.new_nearby` notification while
  // foregrounded — see the hook for how it reuses the existing
  // notifications feed. `latest` drives the deep-linking banner below;
  // `count` drives the tab badge.
  const { latest: newEvent, count: newEventCount, dismiss: dismissNewEvent } =
    useNearbyEventAlerts(ready && !!user);

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
    <>
    <NameRequiredGate />
    <Tabs
      tabBar={(props) => (
        <DialerTabBar {...props} eventBadgeCount={newEventCount} />
      )}
      screenOptions={{
        headerStyle: { backgroundColor: colors.background },
        headerTitleStyle: {
          color: colors.foreground,
          fontFamily: "SpaceGrotesk_600SemiBold",
        },
        headerShadowVisible: false,
        headerRight: () => <SearchButton />,
        // The floating bar hovers over content, so every scene reserves
        // clearance at the bottom instead of a solid tab strip.
        sceneStyle: {
          backgroundColor: colors.background,
          paddingBottom: TAB_BAR_CLEARANCE,
        },
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
      <Tabs.Screen
        name="events"
        options={{
          title: "Events",
          tabBarIcon: ({ color, size }) => (
            <View>
              <Feather name="calendar" size={size} color={color} />
              {newEventCount > 0 ? (
                <View
                  style={{
                    position: "absolute",
                    top: -4,
                    right: -8,
                    minWidth: 14,
                    height: 14,
                    borderRadius: 7,
                    backgroundColor: colors.primary,
                    alignItems: "center",
                    justifyContent: "center",
                    paddingHorizontal: 2,
                  }}
                />
              ) : null}
            </View>
          ),
        }}
      />
    </Tabs>
      {newEvent ? (
        <Pressable
          onPress={() => {
            const alias = newEvent.alias;
            dismissNewEvent();
            router.push({ pathname: "/events/[alias]", params: { alias } });
          }}
          style={{
            position: "absolute",
            top: insets.top + 4,
            left: 12,
            right: 12,
            flexDirection: "row",
            alignItems: "center",
            gap: 10,
            backgroundColor: colors.card,
            borderWidth: 1,
            borderColor: colors.border,
            borderRadius: 14,
            paddingVertical: 10,
            paddingHorizontal: 12,
            shadowColor: "#000",
            shadowOpacity: 0.15,
            shadowRadius: 8,
            shadowOffset: { width: 0, height: 3 },
            elevation: 4,
          }}
        >
          {newEvent.cover_image_url ? (
            <Image
              source={{ uri: newEvent.cover_image_url }}
              style={{ width: 36, height: 36, borderRadius: 8 }}
            />
          ) : (
            <View
              style={{
                width: 36,
                height: 36,
                borderRadius: 8,
                backgroundColor: colors.primary,
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Feather name="calendar" size={18} color={colors.primaryForeground} />
            </View>
          )}
          <View style={{ flex: 1 }}>
            <Text
              style={{ color: colors.foreground, fontWeight: "700", fontSize: 13 }}
              numberOfLines={1}
            >
              New event near you
            </Text>
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }} numberOfLines={1}>
              {newEvent.title}
            </Text>
          </View>
          <Pressable
            onPress={(e) => {
              e.stopPropagation();
              dismissNewEvent();
            }}
            hitSlop={8}
            style={{ padding: 4 }}
          >
            <Feather name="x" size={16} color={colors.mutedForeground} />
          </Pressable>
        </Pressable>
      ) : null}
    </>
  );
}
