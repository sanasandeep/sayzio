import { LinearGradient } from "expo-linear-gradient";
import * as Haptics from "expo-haptics";
import {
  ActivityIndicator,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
  type ViewStyle,
} from "react-native";

import { useColors } from "@/hooks/useColors";

type Variant = "primary" | "secondary" | "ghost" | "outline";

export function Button({
  label,
  onPress,
  variant = "primary",
  loading = false,
  disabled = false,
  style,
  testID,
  leading,
}: {
  label: string;
  onPress?: () => void;
  variant?: Variant;
  loading?: boolean;
  disabled?: boolean;
  style?: ViewStyle;
  testID?: string;
  leading?: React.ReactNode;
}) {
  const colors = useColors();
  const isDisabled = disabled || loading;

  const handlePress = () => {
    if (isDisabled || !onPress) return;
    if (Platform.OS !== "web") {
      Haptics.selectionAsync().catch(() => {});
    }
    onPress();
  };

  const base: ViewStyle = {
    minHeight: 52,
    borderRadius: colors.radius,
    paddingHorizontal: 20,
    alignItems: "center",
    justifyContent: "center",
  };

  const labelColor =
    variant === "primary"
      ? colors.primaryForeground
      : variant === "secondary"
        ? colors.secondaryForeground
        : colors.foreground;

  const content = (
    <View style={styles.row}>
      {loading ? (
        <ActivityIndicator color={labelColor} />
      ) : (
        <>
          {leading}
          <Text
            style={[
              styles.label,
              { color: labelColor, opacity: isDisabled ? 0.5 : 1 },
            ]}
          >
            {label}
          </Text>
        </>
      )}
    </View>
  );

  if (variant === "primary") {
    return (
      <Pressable
        onPress={handlePress}
        disabled={isDisabled}
        style={({ pressed }) => [
          base,
          { opacity: pressed ? 0.92 : 1 },
          style,
        ]}
        testID={testID}
      >
        <LinearGradient
          colors={[colors.primary, colors.accent]}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={[StyleSheet.absoluteFill, { borderRadius: colors.radius }]}
        />
        {content}
      </Pressable>
    );
  }

  const bg =
    variant === "secondary"
      ? colors.secondary
      : variant === "outline"
        ? "transparent"
        : "transparent";

  return (
    <Pressable
      onPress={handlePress}
      disabled={isDisabled}
      style={({ pressed }) => [
        base,
        {
          backgroundColor: bg,
          borderWidth: variant === "outline" ? 1 : 0,
          borderColor: colors.border,
          opacity: pressed ? 0.85 : 1,
        },
        style,
      ]}
      testID={testID}
    >
      {content}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "center", gap: 8 },
  label: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 16,
    letterSpacing: 0.2,
  },
});
