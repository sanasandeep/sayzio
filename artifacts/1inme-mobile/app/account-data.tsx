import { useState } from "react";
import { Text, View, Switch } from "react-native";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { FeatureScreen } from "@/components/FeatureScreen";
import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  listDataRequests,
  createDataRequest,
  downloadAccountData,
} from "@/lib/api/accountData";
import { showAlert } from "@/lib/webAlert";
export default function AccountData() {
  const c = useColors(),
    qc = useQueryClient();
  const [type, setType] = useState("export"),
    [reason, setReason] = useState(""),
    [confirmation, setConfirmation] = useState(""),
    [scopes, setScopes] = useState<string[]>([]),
    [downloading, setDownloading] = useState(false);
  const q = useQuery({ queryKey: ["account-data"], queryFn: listDataRequests });
  const submit = useMutation({
    mutationFn: () =>
      createDataRequest({
        type,
        reason,
        confirmation,
        data_scopes: type === "data_deletion" ? scopes : undefined,
      }),
    onSuccess: () => {
      setConfirmation("");
      void qc.invalidateQueries({ queryKey: ["account-data"] });
      showAlert(
        "Request submitted",
        "Your request is in the privacy review queue.",
      );
    },
    onError: (e: Error) => showAlert("Request failed", e.message),
  });
  return (
    <FeatureScreen
      title="Your data & account"
      loading={q.isLoading}
      error={q.error}
      retry={() => q.refetch()}
    >
      <Text style={{ color: c.foreground }}>
        Download your data or request deletion. Approved deletions have a
        seven-day waiting period. Account deletion does not cancel an Apple or
        Google subscription; manage that in the store.
      </Text>
      {(
        [
          ["export", "Download my data"],
          ["data_deletion", "Delete selected personal data"],
          ["deletion", "Delete my account"],
        ] as const
      ).map(([key, label]) => (
        <Button
          key={key}
          label={`${type === key ? "✓ " : ""}${label}`}
          onPress={() => {
            setType(key);
            setConfirmation("");
          }}
        />
      ))}
      {type === "data_deletion" ? (
        <View>
          <Text style={{ color: c.mutedForeground }}>
            Only your personal workspace. Team data, billing and account records
            stay intact.
          </Text>
          {["links", "contacts", "notifications"].map((scope) => (
            <View
              key={scope}
              style={{
                flexDirection: "row",
                justifyContent: "space-between",
                padding: 8,
              }}
            >
              <Text style={{ color: c.foreground }}>{scope}</Text>
              <Switch
                accessibilityLabel={`Delete ${scope}`}
                value={scopes.includes(scope)}
                onValueChange={(on) =>
                  setScopes(
                    on ? [...scopes, scope] : scopes.filter((s) => s !== scope),
                  )
                }
              />
            </View>
          ))}
        </View>
      ) : null}
      <TextField
        label="Additional details (optional)"
        value={reason}
        onChangeText={setReason}
      />
      {type !== "export" ? (
        <TextField
          label={`Type ${type === "deletion" ? "DELETE ACCOUNT" : "DELETE DATA"} to request deletion`}
          value={confirmation}
          onChangeText={setConfirmation}
        />
      ) : null}
      <Button
        label="Submit request"
        loading={submit.isPending}
        onPress={() => submit.mutate()}
      />
      <Text style={{ color: c.foreground, fontWeight: "700" }}>
        Your requests
      </Text>
      {q.data?.data.items.map((item) => (
        <View
          key={item.id}
          style={{
            padding: 12,
            borderWidth: 1,
            borderColor: c.border,
            borderRadius: 12,
            gap: 6,
          }}
        >
          <Text style={{ color: c.foreground }}>
            {item.label} · {item.status}
          </Text>
          {item.scheduled_at ? (
            <Text style={{ color: c.mutedForeground }}>
              Scheduled: {item.scheduled_at}
            </Text>
          ) : null}
          {item.can_download ? (
            <Button
              label="Download archive"
              disabled={downloading}
              onPress={async () => {
                setDownloading(true);
                try {
                  await downloadAccountData(item.id);
                } catch (e) {
                  showAlert("Download failed", (e as Error).message);
                } finally {
                  setDownloading(false);
                }
              }}
            />
          ) : null}
        </View>
      ))}
    </FeatureScreen>
  );
}
