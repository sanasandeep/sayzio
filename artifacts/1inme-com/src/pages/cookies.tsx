import { LegalPage } from "@/components/marketing/legal-page";

export default function Cookies() {
  return (
    <LegalPage
      metaTitle="Cookie Policy"
      metaDescription="What cookies and similar technologies 1INME uses, why we use them, and how you can control them."
      titleLead="Cookie"
      titleHighlight="Policy"
      sections={[
        {
          title: "What cookies are",
          body: [
            "Cookies are small text files a website places on your device to remember information between visits. We also use comparable technologies — local storage, session storage and pixel tags — for the same purposes; \"cookies\" in this policy covers all of them.",
          ],
        },
        {
          title: "Strictly necessary cookies",
          body: [
            "These keep you signed in, remember your workspace selection, protect form submissions from CSRF attacks and load-balance requests. The service cannot work without them and they cannot be disabled separately from disabling cookies entirely in your browser.",
          ],
        },
        {
          title: "Functional cookies",
          body: [
            "These remember your preferences — sidebar collapsed/expanded state, theme, language and recently viewed items — so the dashboard feels familiar between visits.",
          ],
        },
        {
          title: "Analytics cookies",
          body: [
            "We use first-party analytics to understand which features are used and where people get stuck, so we can improve the product. The data is aggregated and never sold. Where required by law we ask for your consent before setting these.",
          ],
        },
        {
          title: "Cookies on your published pages",
          body: [
            "Pages you publish on 1INME set only the strictly necessary cookies needed to run them. We do not inject third-party advertising or marketing cookies into your visitors' browsers.",
          ],
        },
        {
          title: "How to control cookies",
          body: [
            "You can clear and block cookies from your browser settings at any time. Disabling strictly necessary cookies will sign you out and break parts of the dashboard; disabling functional or analytics cookies is safe but may make the experience less convenient.",
          ],
        },
      ]}
    />
  );
}
