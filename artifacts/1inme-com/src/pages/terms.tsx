import { LegalPage } from "@/components/marketing/legal-page";

export default function Terms() {
  return (
    <LegalPage
      metaTitle="Terms & Conditions"
      metaDescription="The terms governing your use of Sayzio — your account, what you can publish, billing, intellectual property, governing law and how we end the relationship."
      titleLead="Terms &"
      titleHighlight="Conditions"
      lastUpdated="June 27, 2026"
      intro="These terms govern your use of Sayzio, operated by Sayzio with its registered office in Hyderabad, Telangana, India. By creating an account or using the service you agree to be bound by them."
      sections={[
        {
          title: "1. Acceptance",
          body: [
            "By creating an account or using Sayzio you agree to these terms. If you are using the service on behalf of a company you confirm you have authority to bind that company. If you do not agree, please do not use the service.",
          ],
        },
        {
          title: "2. Your account",
          body: [
            "You are responsible for everything that happens under your account, including the actions of teammates you invite. Keep your sign-in details safe, use a strong unique password, and tell us straight away if you suspect unauthorised access.",
          ],
        },
        {
          title: "3. Acceptable use",
          body: [
            "You must not use Sayzio to host or distribute illegal content, run scams or phishing, send unsolicited bulk messages, infringe other people's intellectual property, or harass other users. We reserve the right to remove content and suspend accounts that break these rules.",
          ],
        },
        {
          title: "4. Your content",
          body: [
            "You keep ownership of everything you upload or publish. You grant us the limited licence we need to host, display and back up your content so the service can run. You are responsible for making sure you have the right to publish what you upload.",
          ],
        },
        {
          title: "5. Plans, billing and renewals",
          body: [
            "Paid plans renew automatically at the end of each billing period using the payment method on file. You can cancel or downgrade at any time from your account — changes take effect at the next renewal. See the Refunds Policy for refund eligibility.",
          ],
        },
        {
          title: "6. Service availability",
          body: [
            "We work hard to keep Sayzio fast and available, but we cannot guarantee 100% uptime. Planned maintenance is announced in advance where possible. The service is provided \"as is\" without implied warranties to the extent permitted by law.",
          ],
        },
        {
          title: "7. Hosting and AI providers",
          body: [
            "We host the service on Amazon Web Services (AWS), using Amazon RDS for our database and Amazon S3 for files and media. Some features rely on AI providers — OpenAI (text), OpenAI Whisper (speech-to-text) and ElevenLabs (text-to-speech) — which process the content you submit when you choose to use those features. See our Privacy Policy for the full hosting and sub-processor details.",
          ],
        },
        {
          title: "8. Termination",
          body: [
            "You can close your account at any time from your settings. We may suspend or close accounts that violate these terms, with notice where reasonable. On termination, your published pages stop resolving and your data is removed within a reasonable period.",
          ],
        },
        {
          title: "9. Governing law and disputes",
          body: [
            "These terms are governed by the laws of India. The courts located in Hyderabad, Telangana, India have exclusive jurisdiction over any dispute, except where mandatory law assigns jurisdiction to another court such as your local consumer court. Before raising a formal claim, please contact us so we can try to resolve the matter informally.",
          ],
        },
        {
          title: "10. Changes to these terms",
          body: [
            "We may update these terms when the service changes or the law requires. Material changes are announced by email and inside the dashboard at least 14 days before they take effect.",
          ],
        },
        {
          title: "11. Contact",
          body: [
            "Questions about these terms? Contact Sayzio through the contact form. Our registered office is in Hyderabad, Telangana, India.",
          ],
        },
      ]}
    />
  );
}
