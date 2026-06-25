import { LegalPage } from "@/components/marketing/legal-page";

export default function Refunds() {
  return (
    <LegalPage
      metaTitle="Refunds Policy"
      metaDescription="How refunds work for Sayzio paid plans — eligibility, timing, exceptions and how to request one."
      titleLead="Refunds"
      titleHighlight="Policy"
      sections={[
        {
          title: "7-day refund window",
          body: [
            "You can request a full refund within 7 days of any new paid plan purchase, no questions asked. The refund covers the most recent charge only — earlier billing periods are not refundable.",
          ],
        },
        {
          title: "Renewals",
          body: [
            "Renewals (monthly or yearly) are not automatically refundable. We send a reminder before each renewal so you can downgrade or cancel beforehand. If a renewal slipped past you and you didn't use the service in the new period, contact us — we look at these case by case.",
          ],
        },
        {
          title: "Add-ons and overages",
          body: [
            "Usage-based add-ons (extra short links, broadcasts, storage) are billed for what you've used and are non-refundable once consumed. Unused, prepaid add-on capacity within the 7-day window can be refunded on request.",
          ],
        },
        {
          title: "How to request a refund",
          body: [
            "Email our team from your account email, include the invoice number, and tell us briefly what didn't work for you (it helps us improve). We process approved refunds within 5 business days back to the original payment method.",
          ],
        },
        {
          title: "Chargebacks",
          body: [
            "Please contact us first before raising a chargeback — we can almost always resolve the issue faster than your bank can. Accounts with unresolved chargebacks may be suspended pending review.",
          ],
        },
      ]}
    />
  );
}
