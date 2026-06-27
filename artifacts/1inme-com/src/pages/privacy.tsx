import { LegalPage } from "@/components/marketing/legal-page";

export default function Privacy() {
  return (
    <LegalPage
      metaTitle="Privacy Policy"
      metaDescription="How Sayzio collects, uses, hosts and protects your personal data — including AWS hosting, AI providers and the rights you have over it."
      titleLead="Privacy"
      titleHighlight="Policy"
      lastUpdated="June 27, 2026"
      intro="The service is operated by Sayzio, registered office Hyderabad, Telangana, India. This policy explains what personal data we collect, how we use, host and share it, and your rights. For privacy requests or to reach our Data Protection Officer, use the contact form."
      sections={[
        {
          title: "What we collect",
          body: [
            "We collect three kinds of information: account details you give us (name, email, billing address), the content you publish or upload (pages, blocks, files, contacts), and basic usage data needed to run the service (IP address, browser, pages visited, link clicks and QR-code scans).",
          ],
        },
        {
          title: "How we use it",
          body: [
            "We use your data to provide the service, support you, send essential service emails, prevent abuse, power the AI features you choose to use, and improve the product. We do not sell your personal data, and we do not run third-party advertising trackers on the pages you publish.",
          ],
        },
        {
          title: "Where we host your data",
          body: [
            "We host the service on Amazon Web Services (AWS). Your account data and content are stored in managed AWS infrastructure — principally Amazon RDS (our PostgreSQL database) for structured data and Amazon S3 for uploaded files and media. Data may be processed in AWS regions outside your country of residence, subject to the safeguards below.",
          ],
        },
        {
          title: "AI features and your content",
          body: [
            "When you use our AI features, the content you submit is sent to AI sub-processors to generate a response: OpenAI (text generation and embeddings), OpenAI Whisper (speech-to-text transcription) and ElevenLabs (text-to-speech). We send only what is needed to perform the feature, and these providers are restricted from using your content to train their models except as permitted by their terms. If you prefer, simply don't use the AI features.",
          ],
        },
        {
          title: "Analytics and tracking",
          body: [
            "We use first-party analytics to measure clicks, QR-code scans, page sessions and feature usage, relying on cookies and local storage. On our marketing pages we may also load optional third-party marketing pixels (such as Facebook, Google Analytics/Tag Manager, LinkedIn, Twitter/X, Pinterest, TikTok, Snapchat and Quora), only where you have consented. See the Cookie Policy for the full list and your choices.",
          ],
        },
        {
          title: "Who we share it with (sub-processors)",
          body: [
            "We share data only with the sub-processors we need to run the service: Amazon Web Services (AWS — RDS, S3) for hosting and storage; OpenAI, OpenAI Whisper and ElevenLabs for AI features; plus payment processing, transactional email, SMS, analytics and error monitoring. Each is bound by a data-processing agreement, and the current list is available on request.",
          ],
        },
        {
          title: "International transfers",
          body: [
            "Because we use AWS and the AI providers named above, personal data may be processed outside the EU/EEA or the UK. Where that happens we rely on the European Commission's Standard Contractual Clauses (and the UK addendum where relevant) with each sub-processor.",
          ],
        },
        {
          title: "How long we keep it",
          body: [
            "We keep your account data for as long as your account is open, plus a short retention window for backups. When you delete your account, your data is removed from our active systems within 30 days and from backups within 90.",
          ],
        },
        {
          title: "Security",
          body: [
            "Data is encrypted in transit and at rest. Access to production systems is restricted to a small team with multi-factor authentication, and every privileged action is logged.",
          ],
        },
        {
          title: "Your rights",
          body: [
            "You can access, export, correct or delete your data at any time from your account settings, or by contacting us. If you are in a region with additional privacy rights (such as the EU/EEA, UK, California or Brazil), see the GDPR Policy for the full list of rights and how to exercise them.",
          ],
        },
        {
          title: "Contacting us about privacy",
          body: [
            "If you have a privacy question or want to raise a concern, contact our team — including our Data Protection Officer — through the contact form. Our registered office is in Hyderabad, Telangana, India. We respond to verified privacy requests within 30 days.",
          ],
        },
      ]}
    />
  );
}
