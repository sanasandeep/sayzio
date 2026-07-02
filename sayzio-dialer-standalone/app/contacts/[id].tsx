import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { ActivityIndicator, View } from "react-native";

import { useColors } from "@/hooks/useColors";
import { getContact } from "@/lib/api/contacts";
import ContactForm from "./_form";

export default function EditContactScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);

  const q = useQuery({
    queryKey: ["contact", id],
    queryFn: () => getContact(id),
    enabled: !!id,
  });

  if (q.isLoading || !q.data) {
    return (
      <View
        style={{
          flex: 1,
          alignItems: "center",
          justifyContent: "center",
          backgroundColor: colors.background,
        }}
      >
        <Stack.Screen options={{ title: "Edit contact" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <ContactForm
      mode="edit"
      contact={q.data}
      onSuccess={() => {
        qc.invalidateQueries({ queryKey: ["contacts"] });
        qc.invalidateQueries({ queryKey: ["contact", id] });
        router.back();
      }}
    />
  );
}
