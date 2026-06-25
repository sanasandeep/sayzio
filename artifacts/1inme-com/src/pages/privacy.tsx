import { LegalPage } from "@/components/marketing/legal-page";

export default function Privacy() {
  return (
    <LegalPage
      metaTitle="Privacy Policy"
      metaDescription="How Sayzio collects, uses, stores and protects your personal data — and the rights you have over it."
      titleLead="Privacy"
      titleHighlight="Policy"
      sections={[
        {
          title: "What we collect",
          body: [
            "We collect three kinds of information: account details you give us (name, email, billing address), the content you publish or upload (pages, blocks, files, contacts), and basic usage data needed to run the service (IP address, browser, pages visited).",
          ],
        },
        {
          title: "How we use it",
          body: [
            "We use your data to provide the service, support you, send essential service emails, prevent abuse, and improve the product. We do not sell your personal data, and we do not run third-party advertising trackers on the pages you publish.",
          ],
        },
        {
          title: "Who we share it with",
          body: [
            "We share data only with the sub-processors we need to run the service — hosting, payment processing, transactional email and analytics. Each is bound by a data-processing agreement and listed in our sub-processor register on request.",
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
            "You can access, export, correct or delete your data at any time from your account settings, or by emailing us. If you are in a region with additional privacy rights (such as the EU/EEA, UK, California or Brazil), see the GDPR Policy for the full list of rights and how to exercise them.",
          ],
        },
        {
          title: "Contacting us about privacy",
          body: [
            "If you have a privacy question or want to raise a concern, contact our team through the contact form. We respond to verified privacy requests within 30 days.",
          ],
        },
      ]}
    />
  );
}
