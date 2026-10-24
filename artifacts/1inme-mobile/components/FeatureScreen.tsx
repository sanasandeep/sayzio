import type { ReactNode } from "react";
import { ActivityIndicator, ScrollView, Text, View } from "react-native";
import { Stack, usePathname, useRouter } from "expo-router";
import { Button } from "@/components/Button";
import { useFeatureStates } from "@/hooks/useFeatureStates";
import { useColors } from "@/hooks/useColors";
export function FeatureScreen({
  title,
  loading,
  error,
  retry,
  children,
}: {
  title: string;
  loading?: boolean;
  error?: unknown;
  retry: () => void;
  children: ReactNode;
}) {
  const c = useColors();
  const path = usePathname();
  const router = useRouter();
  const state = useFeatureStates().stateForHref(path);
  if (state?.status === "coming_soon")
    return (
      <View
        style={{ padding: 24, gap: 16, backgroundColor: c.background, flex: 1 }}
      >
        <Stack.Screen options={{ title }} />
        <Text style={{ color: c.foreground }}>
          {state.label} is currently unavailable.
        </Text>
        <Button
          label="View availability"
          onPress={() =>
            router.replace({
              pathname: "/coming-soon",
              params: { key: state.key },
            })
          }
        />
      </View>
    );
  return (
    <View style={{ flex: 1, backgroundColor: c.background }}>
      <Stack.Screen options={{ title }} />
      <ScrollView
        contentContainerStyle={{
          padding: 20,
          gap: 16,
          width: "100%",
          maxWidth: 760,
          alignSelf: "center",
        }}
      >
        <Text style={{ fontSize: 26, color: c.foreground, fontWeight: "700" }}>
          {title}
        </Text>
        {loading ? (
          <ActivityIndicator color={c.primary} />
        ) : error ? (
          <View style={{ gap: 12 }}>
            <Text style={{ color: c.foreground }}>
              {(error as Error)?.message || "Could not load this feature."}
            </Text>
            <Button label="Retry" onPress={retry} />
          </View>
        ) : (
          children
        )}
      </ScrollView>
    </View>
  );
}
