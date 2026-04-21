import { StyleSheet, View, type ViewProps } from "react-native";

import { useColors } from "@/hooks/useColors";

export function Card({ style, children, ...rest }: ViewProps) {
  const colors = useColors();
  return (
    <View
      {...rest}
      style={[
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderWidth: 1,
          borderRadius: colors.radius,
          padding: 16,
        },
        style,
      ]}
    >
      {children}
    </View>
  );
}

export const cardStyles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "center", gap: 12 },
});
