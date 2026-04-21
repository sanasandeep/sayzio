import { InfoPage } from "@/components/InfoPage";

export default function Privacy() {
  return (
    <InfoPage
      title="Privacy"
      intro="Your privacy is part of the product. This is a plain-language summary of how 1INME handles data on mobile."
      sections={[
        {
          heading: "Account data",
          body: "When you sign in we store your account ID and a session token in the device's secure storage so you stay signed in. You can sign out at any time to remove this data.",
        },
        {
          heading: "What we send to the server",
          body: "Only the requests you make — viewing your dashboard, editing links, sending an OTP, writing an NFC tag, or placing a call — are sent to our servers. We do not collect your contact list, photos, or other on-device data without an explicit action.",
        },
        {
          heading: "Analytics",
          body: "Usage analytics are aggregate and do not include the contents of your messages, links, or contacts. You can opt out from the Profile screen.",
        },
        {
          heading: "Contact",
          body: "Email privacy@1inme.com for data export or deletion requests.",
        },
      ]}
    />
  );
}
