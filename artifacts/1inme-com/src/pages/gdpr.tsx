import { LegalPage } from "@/components/marketing/legal-page";

export default function Gdpr() {
  return (
    <LegalPage
      metaTitle="GDPR Policy"
      metaDescription="Our compliance with the EU General Data Protection Regulation, including lawful bases, your rights and international data transfers."
      titleLead="GDPR"
      titleHighlight="Policy"
      sections={[
        {
          title: "Who this applies to",
          body: [
            "This policy explains how we comply with the EU General Data Protection Regulation (GDPR) and the UK GDPR. It applies whenever we process personal data of users, teammates or visitors located in the EU/EEA or the UK.",
          ],
        },
        {
          title: "Roles",
          body: [
            "For your account information you are the data subject and we are the data controller. For the personal data of your visitors and contacts that you collect through 1INME (form submissions, leads, followers), you are the data controller and we act as your data processor.",
          ],
        },
        {
          title: "Lawful bases for processing",
          body: [
            "We process personal data on the basis of contract performance (running the service for you), legitimate interest (security, fraud prevention, product improvement), legal obligation (tax records, abuse reports), and your consent where required (optional analytics, marketing emails).",
          ],
        },
        {
          title: "Your rights under GDPR",
          body: [
            "You can request access to your data, correction of inaccurate data, deletion (\"right to be forgotten\"), restriction of processing, data portability, and you can object to processing based on legitimate interest. Most of these are self-serve from your account; for the rest, contact us.",
          ],
        },
        {
          title: "International data transfers",
          body: [
            "Where personal data is transferred outside the EU/EEA or UK, we rely on the European Commission's Standard Contractual Clauses (and the UK addendum where applicable) with each sub-processor to provide an adequate level of protection.",
          ],
        },
        {
          title: "Breach notification",
          body: [
            "In the unlikely event of a personal data breach that is likely to result in a risk to your rights and freedoms, we will notify the relevant supervisory authority within 72 hours and inform affected users without undue delay.",
          ],
        },
        {
          title: "Data Processing Agreement",
          body: [
            "If you process personal data of EU/EEA or UK residents through 1INME on behalf of your own users, you can request our standard Data Processing Agreement (DPA) — we'll countersign it and send it back.",
          ],
        },
      ]}
    />
  );
}
