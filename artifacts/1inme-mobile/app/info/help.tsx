import { InfoPage } from "@/components/InfoPage";

export default function Help() {
  return (
    <InfoPage
      title="Help & Support"
      intro="Need a hand? Most answers live below. If you're still stuck, our team replies within one business day."
      sections={[
        {
          heading: "Sign-in problems",
          body: "If a verification code never arrives, double-check the email or phone number for typos and try again. Codes expire after 10 minutes.",
        },
        {
          heading: "Web and mobile out of sync",
          body: "Pull to refresh on any screen to fetch the latest data. Changes you make on the web appear on mobile within a few seconds.",
        },
        {
          heading: "NFC tag will not write",
          body: "Hold the tag still against the back of your phone for at least two seconds. Some tags ship pre-locked — try a fresh one.",
        },
        {
          heading: "Contact us",
          body: "Tap \"Contact us\" on your Profile to request a call back, a WhatsApp call, or an email reply — our team picks it up from there. You can also email support@sayzio.app or visit https://sayzio.app/help for live chat.",
        },
      ]}
    />
  );
}
