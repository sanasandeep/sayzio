import { WebFeatureRedirect } from "@/components/WebFeatureRedirect";

export default function VisitorsScreen() {
  return (
    <WebFeatureRedirect
      title="Visitors"
      iconName="users"
      blurb="Detailed visitor analytics by country, device and referrer."
      webPath="/user/visitors"
    />
  );
}
