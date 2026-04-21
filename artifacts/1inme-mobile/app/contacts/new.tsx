import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import ContactForm from "./_form";

export default function NewContactScreen() {
  const router = useRouter();
  const qc = useQueryClient();
  return (
    <ContactForm
      mode="create"
      onSuccess={() => {
        qc.invalidateQueries({ queryKey: ["contacts"] });
        router.back();
      }}
    />
  );
}
