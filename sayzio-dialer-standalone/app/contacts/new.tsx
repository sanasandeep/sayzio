import { useQueryClient } from "@tanstack/react-query";
import { useLocalSearchParams, useRouter } from "expo-router";
import ContactForm from "./_form";

export default function NewContactScreen() {
  const router = useRouter();
  const qc = useQueryClient();
  // "Add to contacts" from the dialer recents list prefills these.
  const params = useLocalSearchParams<{ phone?: string; name?: string }>();
  return (
    <ContactForm
      mode="create"
      initialPhone={typeof params.phone === "string" ? params.phone : null}
      initialName={typeof params.name === "string" ? params.name : null}
      onSuccess={() => {
        qc.invalidateQueries({ queryKey: ["contacts"] });
        router.back();
      }}
    />
  );
}
