import { Redirect } from "expo-router";
import { ActivityIndicator, View } from "react-native";

import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";

/**
 * Launch gate. While the stored session is being restored we show a spinner;
 * once ready we send signed-in users to the Dialer tab and everyone else to
 * the shared Sayzio login (email / WhatsApp OTP + social).
 */
export default function Index() {
  const { ready, user } = useAuth();
  const colors = useColors();

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

  return <Redirect href={user ? "/(tabs)/dialer" : "/(auth)"} />;
}
