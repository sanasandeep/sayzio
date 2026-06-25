import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { SIGNUP_URL } from "@/config";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { motion } from "framer-motion";
import { Link } from "wouter";
import { faqCategories } from "@/content/faqs";

export default function Faq() {
  return (
    <PageLayout
      title="FAQ"
      description="Answers to common questions about Sayzio — getting started, Link in Bio pages, short links, QR codes, analytics, teams, billing, domains, security, integrations and more."
    >
      <section className="py-20 lg:py-28">
        <div className="container mx-auto px-6">
          <div className="max-w-3xl mx-auto text-center mb-16">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              FAQ
            </p>
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              Questions,{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                answered.
              </span>
            </h1>
            <p className="text-xl text-muted-foreground">
              Everything you need to know before you get started.
            </p>
          </div>

          <nav className="flex flex-wrap justify-center gap-2 max-w-4xl mx-auto mb-14">
            {faqCategories.map((cat) => (
              <a
                key={cat.category}
                href={`#${cat.category.replace(/[^a-z0-9]+/gi, "-").toLowerCase()}`}
                className="text-xs font-medium px-3 py-1.5 rounded-full glass-card hover:text-primary transition-colors"
              >
                {cat.category}
              </a>
            ))}
          </nav>

          <div className="max-w-3xl mx-auto space-y-12">
            {faqCategories.map((cat, catIndex) => (
              <motion.div
                key={cat.category}
                id={cat.category.replace(/[^a-z0-9]+/gi, "-").toLowerCase()}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "-80px" }}
                transition={{ duration: 0.5 }}
                className="scroll-mt-24"
              >
                <h2 className="text-xl font-bold tracking-tight mb-4 px-1">
                  {cat.category}
                </h2>
                <div className="glass-card p-4 sm:p-6 rounded-3xl">
                  <Accordion type="single" collapsible className="w-full">
                    {cat.items.map((faq, index) => (
                      <AccordionItem key={index} value={`${catIndex}-${index}`}>
                        <AccordionTrigger className="text-left text-base font-semibold hover:text-primary">
                          {faq.question}
                        </AccordionTrigger>
                        <AccordionContent className="text-muted-foreground leading-relaxed text-base">
                          {faq.answer}
                        </AccordionContent>
                      </AccordionItem>
                    ))}
                  </Accordion>
                </div>
              </motion.div>
            ))}
          </div>

          <div className="max-w-3xl mx-auto text-center mt-16">
            <p className="text-muted-foreground mb-6">Still have questions?</p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Button asChild size="lg" className="rounded-full h-14 px-8 text-base">
                <a href={SIGNUP_URL}>Sign up free</a>
              </Button>
              <Button
                asChild
                variant="outline"
                size="lg"
                className="rounded-full h-14 px-8 text-base bg-transparent border-primary/20 hover:bg-primary/5"
              >
                <Link href="/contact">Contact us</Link>
              </Button>
            </div>
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
