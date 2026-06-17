import { PageLayout } from "@/components/layout/page-layout";
import { motion } from "framer-motion";

const sections = [
  {
    title: "1. Acceptance of terms",
    body: [
      "By accessing or using 1INME (the \"Service\"), you agree to be bound by these Terms of Service. If you do not agree to these terms, you may not use the Service.",
      "We may update these terms from time to time. Continued use of the Service after changes take effect constitutes acceptance of the revised terms.",
    ],
  },
  {
    title: "2. Your account",
    body: [
      "You are responsible for safeguarding your account credentials and for any activity that occurs under your account. Notify us immediately of any unauthorized use.",
      "You must be at least 13 years old (or the age of digital consent in your country) to create an account.",
    ],
  },
  {
    title: "3. Acceptable use",
    body: [
      "You agree not to use the Service to host or distribute unlawful content, infringe intellectual property, distribute malware, or engage in spam, phishing, or fraudulent activity.",
      "We reserve the right to suspend or terminate accounts that violate these rules or that create risk or legal exposure for us or other users.",
    ],
  },
  {
    title: "4. Your content",
    body: [
      "You retain ownership of the content you publish through 1INME. By publishing, you grant us a limited license to host, display, and distribute that content solely to operate and improve the Service.",
      "You are solely responsible for the content you publish and for ensuring you have the rights to use it.",
    ],
  },
  {
    title: "5. Plans, billing, and refunds",
    body: [
      "Paid plans are billed in advance on a recurring basis. You can cancel at any time; cancellation takes effect at the end of the current billing period.",
      "We offer a 7-day no-questions-asked refund on new subscriptions. Coin purchases and usage-based charges are non-refundable once consumed.",
    ],
  },
  {
    title: "6. Disclaimers and liability",
    body: [
      "The Service is provided \"as is\" without warranties of any kind. To the maximum extent permitted by law, 1INME is not liable for indirect, incidental, or consequential damages arising from your use of the Service.",
    ],
  },
  {
    title: "7. Contact",
    body: [
      "Questions about these terms? Reach out through our contact page and we'll be happy to help.",
    ],
  },
];

export default function Terms() {
  return (
    <PageLayout
      title="Terms of Service"
      description="The terms that govern your use of 1INME — your account, acceptable use, content ownership, billing, and more."
    >
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-3xl mx-auto text-center mb-16">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              Legal
            </p>
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              Terms of{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                Service
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
              This is a plain-language summary of how we work together. It is
              placeholder copy for the marketing site and not a substitute for
              legal advice — replace it with your finalized terms before launch.
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
