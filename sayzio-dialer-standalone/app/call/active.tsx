import { Feather } from "@expo/vector-icons";
import * as Haptics from "expo-haptics";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useRef, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { ChannelActions } from "@/components/ChannelActions";
import { useColors } from "@/hooks/useColors";

function formatDuration(sec: number): string {
  const m = Math.floor(sec / 60)
    .toString()
    .padStart(2, "0");
  const s = Math.floor(sec % 60)
    .toString()
    .padStart(2, "0");
  return `${m}:${s}`;
}

export default function ActiveCallScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const params = useLocalSearchParams<{ number?: string; name?: string }>();
  const number = String(params.number ?? "").trim();
  const name =
    typeof params.name === "string" && params.name.trim() !== ""
      ? params.name.trim()
      : null;

  const [muted, setMuted] = useState(false);
  const [speaker, setSpeaker] = useState(false);
  const [seconds, setSeconds] = useState(0);

  // Local stopwatch — there's no real telephony yet; this just makes the
  // shell feel alive. Cleared on unmount so backgrounding doesn't leak it.
  const startedAt = useRef<number>(Date.now());
  useEffect(() => {
    const id = setInterval(() => {
      setSeconds(Math.floor((Date.now() - startedAt.current) / 1000));
    }, 1000);
    return () => clearInterval(id);
  }, []);

  const end = () => {
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    if (router.canGoBack()) router.back();
    else router.replace("/dialer");
  };

  return (
    <View
      style={[
        styles.root,
        { backgroundColor: colors.background, paddingTop: insets.top + 24 },
      ]}
    >
      <Stack.Screen
        options={{ headerShown: false, gestureEnabled: false }}
      />
      <View style={styles.header}>
        <Text style={[styles.status, { color: colors.mutedForeground }]}>
          {number ? "On call" : "Calling…"}
        </Text>
        <Text
          style={[styles.name, { color: colors.foreground }]}
          numberOfLines={1}
        >
          {name || number || "Unknown"}
        </Text>
        {name && number ? (
          <Text style={[styles.number, { color: colors.mutedForeground }]}>
            {number}
          </Text>
        ) : null}
        <Text style={[styles.timer, { color: colors.mutedForeground }]}>
          {formatDuration(seconds)}
        </Text>
        {number ? (
          <View style={{ marginTop: 12 }}>
            <ChannelActions number={number} size="md" />
          </View>
        ) : null}
      </View>

      <View
        style={[
          styles.avatar,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}
      >
        <Feather name="user" size={84} color={colors.mutedForeground} />
      </View>

      <View
        style={[styles.controls, { paddingBottom: insets.bottom + 24 }]}
      >
        <View style={styles.row}>
          <ControlButton
            icon={muted ? "mic-off" : "mic"}
            label={muted ? "Unmute" : "Mute"}
            active={muted}
            onPress={() => {
              void Haptics.selectionAsync();
              setMuted((m) => !m);
            }}
            colors={colors}
          />
          <ControlButton
            icon="grid"
            label="Keypad"
            onPress={() => {
              void Haptics.selectionAsync();
            }}
            colors={colors}
          />
          <ControlButton
            icon={speaker ? "volume-2" : "volume-1"}
            label="Speaker"
            active={speaker}
            onPress={() => {
              void Haptics.selectionAsync();
              setSpeaker((s) => !s);
            }}
            colors={colors}
          />
        </View>

        <Pressable
          onPress={end}
          style={({ pressed }) => [
            styles.end,
            {
              backgroundColor: colors.destructive,
              opacity: pressed ? 0.8 : 1,
            },
          ]}
        >
          <Feather name="phone-off" size={28} color="#fff" />
        </Pressable>
        <Text style={[styles.endLabel, { color: colors.mutedForeground }]}>
          End call
        </Text>
      </View>
    </View>
  );
}

function ControlButton({
  icon,
  label,
  active,
  onPress,
  colors,
}: {
  icon: keyof typeof Feather.glyphMap;
  label: string;
  active?: boolean;
  onPress: () => void;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.ctrl, { opacity: pressed ? 0.7 : 1 }]}
    >
      <View
        style={[
          styles.ctrlCircle,
          {
            backgroundColor: active ? colors.foreground : colors.card,
            borderColor: colors.border,
          },
        ]}
      >
        <Feather
          name={icon}
          size={26}
          color={active ? colors.background : colors.foreground}
        />
      </View>
      <Text style={[styles.ctrlLabel, { color: colors.mutedForeground }]}>
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, alignItems: "center", justifyContent: "space-between" },
  header: { alignItems: "center", gap: 6, paddingHorizontal: 24 },
  status: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
    textTransform: "uppercase",
    letterSpacing: 1,
  },
  name: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 28,
    textAlign: "center",
  },
  number: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 16 },
  timer: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 16,
    marginTop: 6,
  },
  avatar: {
    width: 160,
    height: 160,
    borderRadius: 999,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  controls: { width: "100%", alignItems: "center", gap: 16, paddingHorizontal: 24 },
  row: {
    flexDirection: "row",
    justifyContent: "space-around",
    width: "100%",
    maxWidth: 420,
  },
  ctrl: { alignItems: "center", gap: 8, width: 96 },
  ctrlCircle: {
    width: 68,
    height: 68,
    borderRadius: 999,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  ctrlLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  end: {
    width: 76,
    height: 76,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 8,
  },
  endLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
});
