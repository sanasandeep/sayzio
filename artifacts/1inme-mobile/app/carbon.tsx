import { WebFeatureRedirect } from "@/components/WebFeatureRedirect";

export default function CarbonScreen() {
  return (
    <WebFeatureRedirect
      title="Carbon footprint"
      iconName="cloud"
      blurb="Estimated carbon impact of your hosted pages and short links."
      webPath="/user/carbon"
    />
  );
}
