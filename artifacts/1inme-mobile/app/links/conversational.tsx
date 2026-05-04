import { WebFeatureRedirect } from "@/components/WebFeatureRedirect";

export default function ConversationalLinksScreen() {
  return (
    <WebFeatureRedirect
      title="Conversational links"
      iconName="message-circle"
      blurb="Links that open into an AI-guided conversation with your visitor."
      webPath="/user/links/conversational"
    />
  );
}
