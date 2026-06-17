import { PageLayout } from "@/components/layout/page-layout";
import { motion } from "framer-motion";

const sections = [
  {
    title: "1. What we collect",
    body: [
      "We collect the information you give us — your name, email, and the content you publish — along with technical data like device type, browser, and approximate location derived from your IP address.",
      "When visitors interact with your links, we collect aggregate analytics (clicks, country, device, referrer) so you can understand your audience.",
    ],
  },
  {
    title: "2. How we use it",
    body: [
      "We use your data to operate the Service, deliver analytics, process payments, prevent abuse, and communicate important updates.",
      "We do not sell your personal data. We do not run shady resale or dark-pattern practices — privacy by default is one of our core values.",
    ],
  },
  {
    title: "3. Cookies",
    body: [
      "We use essential cookies to keep you signed in and to remember your preferences. Analytics cookies help us understand how the Service is used so we can improve it.",
      "You can control non-essential cookies through the consent banner and your browser settings.",
    ],
  },
  {
    title: "4. Sharing and processors",
    body: [
      "We share data only with trusted processors who help us run the Service — for example payment, email, and hosting providers — and only to the extent needed to deliver their function.",
      "We may disclose data if required by law or to protect the rights and safety of our users.",
    ],
  },
  {
    title: "5. Your rights",
    body: [
      "You can access, correct, export, or delete your personal data at any time. Deleting your account removes your personal data from active systems, subject to legal retention requirements.",
    ],
  },
  {
    title: "6. Data retention and security",
    body: [
      "We keep your data only as long as your account is active or as needed to provide the Service. We apply industry-standard safeguards to protect it, though no system is perfectly secure.",
    ],
  },
  {
    title: "7. Contact",
    body: [
      "Have a privacy question or request? Reach out through our contact page and we'll respond promptly.",
    ],
  },
];

export default function Privacy() {
  return (
    <PageLayout
      title="Privacy Policy"
      description="How 1INME collects, uses, and protects your data — privacy by default, no spying, no shady resale, no dark patterns."
    >
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-3xl mx-auto text-center mb-16">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              Legal
            </p>
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              Privacy{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                Policy
              </span>
            </h1>
            <p className="text-lg text-muted-foreground">
              Last updated: June 1, 2026
            </p>
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
            className="max-w-3xl mx-auto glass-card p-8 sm:p-12 rounded-3xl"
          >
            <p className="text-sm text-muted-foreground mb-10 leading-relaxed">
              This is a plain-language summary of how we handle your data. It is
              placeholder copy for the marketing site and not a substitute for
              legal advice — replace it with your finalized policy before launch.
            </p>
            <div className="space-y-10">
              {sections.map((section) => (
                <div key={section.title}>
                  <h2 className="text-xl font-semibold mb-3">{section.title}</h2>
                  {section.body.map((paragraph, index) => (
                    <p
                      key={index}
                      className="text-muted-foreground leading-relaxed mb-3 last:mb-0"
                    >
                      {paragraph}
                    </p>
                  ))}
                </div>
              ))}
            </div>
          </motion.div>
        </div>
      </section>
    </PageLayout>
  );
}
