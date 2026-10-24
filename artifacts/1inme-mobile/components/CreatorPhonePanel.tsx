import { EvidenceFiles, type EvidenceFile } from "@/components/EvidenceFiles";
import { evidenceForm } from "@/lib/evidenceForm";
import { useState } from "react";
import { Text, View } from "react-native";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { apiFetch } from "@/lib/api";
import { showAlert } from "@/lib/webAlert";

type Phones = {
  numbers: { phone: string; source: string }[];
  claims: {
    id: number;
    phone: string;
    status: string;
    admin_notes: string | null;
  }[];
};
export function CreatorPhonePanel({ profileId }: { profileId: number }) {
  const colors = useColors();
  const qc = useQueryClient();
  const [files, setFiles] = useState<EvidenceFile[]>([]);
  const [phone, setPhone] = useState("");
  const [message, setMessage] = useState("");
  const [urls, setUrls] = useState("");
  const key = ["creator-phones", profileId];
  const q = useQuery({
    queryKey: key,
    queryFn: async () =>
      (await apiFetch<{ data: Phones }>("/me/creator-profile/phones")).data,
  });
  const save = useMutation({
    mutationFn: async (claim: boolean) => {
      const values = {
        creator_profile_id: profileId,
        phone,
        ...(claim
          ? {
              message,
              supporting_urls: urls
                .split(/\r?\n/)
                .map((u) => u.trim())
                .filter(Boolean),
            }
          : {}),
      };
      return apiFetch(
        claim
          ? "/me/creator-profile/phone-claims"
          : "/me/creator-profile/phones",
        {
          method: "POST",
          body:
            claim && files.length
              ? await evidenceForm(values, files)
              : JSON.stringify(values),
        },
      );
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: key });
      showAlert("Saved", "Number attachment or ownership request saved.");
    },
    onError: (e: Error) => showAlert("Could not save number", e.message),
  });
  return (
    <View style={{ gap: 10 }}>
      <Text style={{ color: colors.foreground, fontWeight: "700" }}>
        Caller identity numbers
      </Text>
      <Text style={{ color: colors.mutedForeground }}>
        Each number belongs to one creator profile. Different workspaces can use
        different verified numbers. Login numbers remain separate.
      </Text>
      {q.isError ? (
        <Button label="Retry numbers" onPress={() => q.refetch()} />
      ) : q.isLoading ? (
        <Text style={{ color: colors.mutedForeground }}>Loading…</Text>
      ) : q.data?.numbers.length ? (
        q.data.numbers.map((n) => (
          <Text key={n.phone} style={{ color: colors.foreground }}>
            {n.phone}
          </Text>
        ))
      ) : (
        <Text style={{ color: colors.mutedForeground }}>
          No number attached.
        </Text>
      )}
      <TextField
        label="Number with country code"
        value={phone}
        onChangeText={setPhone}
        keyboardType="phone-pad"
        placeholder="+919876543210"
      />
      <Button
        label="Attach verified number"
        loading={save.isPending}
        disabled={!phone.trim()}
        onPress={() => save.mutate(false)}
      />
      <TextField
        label="Ownership request message"
        value={message}
        onChangeText={setMessage}
        multiline
        maxLength={3000}
        hint="If another profile holds your number, ask admin to review it. No reassignment occurs before approval."
      />
      <EvidenceFiles files={files} onChange={setFiles} />
      <TextField
        label="Supporting URLs (one per line)"
        value={urls}
        onChangeText={setUrls}
        multiline
        autoCapitalize="none"
      />
      <Button
        label="Request admin ownership review"
        variant="secondary"
        loading={save.isPending}
        disabled={!phone.trim() || !message.trim()}
        onPress={() => save.mutate(true)}
      />
      {q.data?.claims.map((c) => (
        <Text key={c.id} style={{ color: colors.mutedForeground }}>
          {c.phone} · {c.status}
          {c.admin_notes ? ` · ${c.admin_notes}` : ""}
        </Text>
      ))}
    </View>
  );
}
