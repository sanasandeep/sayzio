import { Stack, useLocalSearchParams } from "expo-router";
import { View } from "react-native";

import { ReviewsWall } from "@/components/ReviewsWall";
import { useColors } from "@/hooks/useColors";

export default function ReviewsScreen() {
  const colors = useColors();
  const { alias } = useLocalSearchParams<{ alias: string }>();
  const resolved = typeof alias === "string" ? alias : "";

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Reviews" }} />
      <ReviewsWall alias={resolved} colors={colors} scroll />
    </View>
  );
}
