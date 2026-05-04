import { WebFeatureRedirect } from "@/components/WebFeatureRedirect";

export default function ClientPortalsScreen() {
  return (
    <WebFeatureRedirect
      title="Client portals"
      iconName="briefcase"
      blurb="Private spaces where each of your clients can see files, invoices and updates."
      webPath="/user/client-portals"
    />
  );
}
