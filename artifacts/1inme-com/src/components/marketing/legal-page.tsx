import { PageLayout } from "@/components/layout/page-layout";
import { motion } from "framer-motion";

export interface LegalSection {
  title: string;
  body: string[];
}

interface LegalPageProps {
  eyebrow?: string;
  titleLead: string;
  titleHighlight: string;
  metaTitle: string;
  metaDescription: string;
  intro?: string;
  lastUpdated?: string;
  sections: LegalSection[];
}

export function LegalPage({
  eyebrow = "Legal",
  titleLead,
  titleHighlight,
  metaTitle,
  metaDescription,
  intro,
  lastUpdated = "June 1, 2026",
  sections,
}: LegalPageProps) {
  return (
    <PageLayout title={metaTitle} description={metaDescription}>
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-3xl mx-auto text-center mb-16">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              {eyebrow}
            </p>
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              {titleLead}{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                {titleHighlight}
              </span>
            </h1>
            <p className="text-lg text-muted-foreground">Last updated: {lastUpdated}</p>
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
            className="max-w-3xl mx-auto glass-card p-8 sm:p-12 rounded-3xl"
          >
            {intro && (
              <p className="text-sm text-muted-foreground mb-10 leading-relaxed">{intro}</p>
            )}
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
