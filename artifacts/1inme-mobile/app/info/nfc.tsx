import { InfoPage } from "@/components/InfoPage";

export default function HowNfcWorks() {
  return (
    <InfoPage
      title="How NFC works"
      intro="NFC (Near Field Communication) lets nearby devices exchange small amounts of data with a tap. Sayzio uses NFC to write your profile URL onto inexpensive stickers, cards, and wristbands so anyone can open your Sayzio with a single tap of their phone."
      sections={[
        {
          heading: "What gets written",
          body: "We only write a public URL pointing to your Sayzio profile (for example, https://sayzio.app/yourhandle). No private data, contacts, or tokens leave your device.",
        },
        {
          heading: "Compatible tags",
          body: "Most NFC stickers and cards work, including NTAG213, NTAG215, and NTAG216. Larger memory tags (215, 216) are recommended if you plan to write longer links or vCards.",
        },
        {
          heading: "Reading is universal",
          body: "Almost every modern smartphone reads NFC tags by default. Your audience does not need the Sayzio app; tapping the tag simply opens their browser to your profile.",
        },
        {
          heading: "Re-writing and locking",
          body: "Tags can be re-written from the NFC tab in this app at any time, or permanently locked when you are happy with the destination.",
        },
      ]}
    />
  );
}
