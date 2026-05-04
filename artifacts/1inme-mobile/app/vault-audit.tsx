import { WebFeatureRedirect } from "@/components/WebFeatureRedirect";

export default function VaultAuditScreen() {
  return (
    <WebFeatureRedirect
      title="Vault audit & export"
      iconName="shield"
      blurb="Review every credential reveal, change and export in your vault."
      webPath="/user/vault/audit"
      features={[
        "Full audit log with actor and timestamp",
        "Filter by client or credential",
        "Export to CSV for compliance",
      ]}
    />
  );
}
