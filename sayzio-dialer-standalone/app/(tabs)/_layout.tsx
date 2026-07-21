import Feather from "@expo/vector-icons/Feather";
import { Redirect, Tabs, usePathname, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import { AppState, Image, Pressable, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { DialerDrawer } from "@/components/DialerDrawer";
import { NameRequiredGate } from "@/components/NameRequiredGate";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { useContactAutoSync } from "@/hooks/useContactAutoSync";
import { useNearbyEventAlerts } from "@/hooks/useNearbyEventAlerts";
import {
  rearmNoteAlarmsOnForeground,
  rearmNoteAlarmsOnLaunch,
} from "@/lib/localReminders";

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

  // Re-arm local note alarms on app launch AND on foreground transitions.
  // Some OEM battery managers (Xiaomi/Oppo) drop scheduled notifications
  // after a reboot or while the app sits backgrounded for days; re-syncing
  // every open note with a future remind_at is idempotent (identifiers are
  // keyed dialer-note-{id}, so re-scheduling replaces rather than
  // duplicates). The foreground pass is throttled inside localReminders to
  // at most once per hour. Best-effort and fully async, never blocks the UI.
  const signedIn = ready && !!user;
  useEffect(() => {
    if (!signedIn) return;
    void rearmNoteAlarmsOnLaunch();
    const sub = AppState.addEventListener("change", (state) => {
      if (state === "active") void rearmNoteAlarmsOnForeground();
    });
    return () => sub.remove();
  }, [signedIn]);

  const [drawerOpen, setDrawerOpen] = useState(false);
  const pathname = usePathname();
  // "/dialer" | "/contacts" | "/caller-id" | "/events" → tab route name.
  const activeRoute = pathname?.replace(/^\//, "").split("/")[0] || null;

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

  const MenuButton = () => (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel="Open menu"
      onPress={() => setDrawerOpen(true)}
      hitSlop={12}
      style={{ paddingHorizontal: 12 }}
    >
      <View>
        <Feather name="menu" size={22} color={colors.foreground} />
        {newEventCount > 0 ? (
          <View
            style={{
              position: "absolute",
              top: -2,
              right: -4,
              width: 8,
              height: 8,
              borderRadius: 4,
              backgroundColor: colors.primary,
            }}
          />
        ) : null}
      </View>
    </Pressable>
  );

  return (
    <>
    <NameRequiredGate />
    <DialerDrawer
      open={drawerOpen}
      onClose={() => setDrawerOpen(false)}
      activeRoute={activeRoute}
      onNavigateTab={(name) => router.navigate(`/(tabs)/${name}` as never)}
      eventBadgeCount={newEventCount}
    />
    <Tabs
      // Navigation now lives in the slide-in drawer (hamburger in the
      // header); no bottom tab bar.
      tabBar={() => null}
      screenOptions={{
        headerStyle: { backgroundColor: colors.background },
        headerTitleStyle: {
          color: colors.foreground,
          fontFamily: "SpaceGrotesk_600SemiBold",
        },
        headerShadowVisible: false,
        headerLeft: () => <MenuButton />,
        headerRight: () => <SearchButton />,
        sceneStyle: {
          backgroundColor: colors.background,
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
      <Tabs.Screen
        name="notes"
        options={{
          title: "Notes",
          tabBarIcon: ({ color, size }) => (
            <Feather name="edit-3" size={size} color={color} />
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
