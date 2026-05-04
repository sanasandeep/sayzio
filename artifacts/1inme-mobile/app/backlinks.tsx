import { WebFeatureRedirect } from "@/components/WebFeatureRedirect";

export default function BacklinksScreen() {
  return (
    <WebFeatureRedirect
      title="Backlinks"
      iconName="link"
      blurb="See which sites are linking back to your 1INME profile and short links."
      webPath="/user/backlinks"
    />
  );
}
