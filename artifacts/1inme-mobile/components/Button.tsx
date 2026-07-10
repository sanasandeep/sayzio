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
import { WEB_FOCUS_RING_PROPS } from "@/hooks/useWebFocusRing";

// "cta" is the highlight variant (electric blue → cyan gradient) reserved for
// the handful of high-intent actions (sign-in, plan upgrade, claim handle).
// Everyday primary buttons keep the standard brand gradient.
type Variant = "primary" | "cta" | "secondary" | "ghost" | "outline";

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
    variant === "primary" || variant === "cta"
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

  if (variant === "primary" || variant === "cta") {
    const ctaGlow: ViewStyle =
      variant === "cta"
        ? {
            shadowColor: "#22d3ee",
            shadowOffset: { width: 0, height: 4 },
            shadowOpacity: isDisabled ? 0 : 0.35,
            shadowRadius: 12,
            elevation: isDisabled ? 0 : 6,
          }
        : {};
    return (
      <Pressable
        {...WEB_FOCUS_RING_PROPS}
        onPress={handlePress}
        disabled={isDisabled}
        style={({ pressed }) => [
          base,
          ctaGlow,
          { opacity: pressed ? 0.92 : 1 },
          style,
        ]}
        testID={testID}
      >
        <LinearGradient
          colors={variant === "cta" ? colors.ctaGradient : [colors.primary, colors.accent]}
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
      {...WEB_FOCUS_RING_PROPS}
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
