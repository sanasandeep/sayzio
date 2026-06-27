import { LegalPage } from "@/components/marketing/legal-page";

export default function Cookies() {
  return (
    <LegalPage
      metaTitle="Cookie Policy"
      metaDescription="What cookies, local storage, tracking and optional marketing pixels Sayzio uses, why we use them, and how you can control them."
      titleLead="Cookie"
      titleHighlight="Policy"
      lastUpdated="June 27, 2026"
      intro="This policy explains the cookies, local storage and tracking technologies Sayzio uses, and the choices you have. It complements our Privacy Policy."
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
          title: "Analytics, click and scan tracking",
          body: [
            "We use first-party analytics to record link clicks, QR-code scans, page sessions, referrers, approximate location (from IP), device and browser, so we can run and improve the product. We also use your browser's local storage and session storage to keep you signed in and avoid double-counting events. This data is aggregated and never sold. Where required by law we ask for your consent before setting non-essential analytics.",
          ],
        },
        {
          title: "Optional marketing pixels",
          body: [
            "On our public marketing pages we may load optional third-party advertising/analytics pixels to measure campaigns, only where you have consented if consent is required. These may include Facebook (Meta) Pixel, Google Analytics / Google Tag Manager, LinkedIn Insight Tag, Twitter/X Pixel, Pinterest Tag, TikTok Pixel, Snapchat Pixel and Quora Pixel. They are not injected into the pages you publish for your own visitors.",
          ],
        },
        {
          title: "Cookies on your published pages",
          body: [
            "Pages you publish on Sayzio set only the strictly necessary cookies needed to run them, plus the first-party analytics above. We do not inject third-party advertising or marketing cookies into your visitors' browsers.",
          ],
        },
        {
          title: "How to control cookies",
          body: [
            "You can clear and block cookies from your browser settings at any time, and change your consent for non-essential cookies and marketing pixels through our cookie preferences. Disabling strictly necessary cookies will sign you out and break parts of the dashboard; disabling functional or analytics cookies is safe but may make the experience less convenient.",
          ],
        },
      ]}
    />
  );
}
