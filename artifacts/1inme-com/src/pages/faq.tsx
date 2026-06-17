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

export default function Faq() {
  const faqs = [
    {
      question: "Can I use my own domain?",
      answer:
        "Yes. On any paid plan, you can connect your own custom domain (e.g., links.yourname.com) or a root domain.",
    },
    {
      question: "What are coins for?",
      answer:
        "Coins are a one-off currency used for usage-based features like AI generation, sending SMS broadcasts, or temporary storage boosts.",
    },
    {
      question: "Is there a free plan?",
      answer:
        "Yes, our Free plan is free forever and includes everything you need to get started: a biolink, short links, and basic analytics.",
    },
    {
      question: "How do refunds work?",
      answer:
        "We offer a 7-day no-questions-asked refund policy for all new subscriptions.",
    },
    {
      question: "Can I change the destination of a QR code after printing it?",
      answer:
        "Absolutely. Every QR code points to an editable link, so you can repoint the same printed code to a new destination at any time — no reprinting required.",
    },
    {
      question: "What analytics do I get?",
      answer:
        "You'll see visitors arrive in real time with country, city, device, referrer and conversion breakdowns. The Performance Coach also surfaces concrete fixes like slow pages, dead blocks and missing CTAs.",
    },
    {
      question: "Can I invite my team?",
      answer:
        "Yes. Create a workspace per brand or client and invite teammates with the right role — Owner, Admin, Editor or Viewer — while keeping billing, analytics and contacts cleanly separated. Pro includes up to 3 seats and Business includes 10.",
    },
    {
      question: "Do I need design skills to build a biolink?",
      answer:
        "Not at all. Stack blocks for text, images, video, audio, embeds, products, donations and forms, reorder them by dragging, and swap themes in a click. You can publish a polished page in minutes.",
    },
  ];

  return (
    <PageLayout
      title="FAQ"
      description="Answers to common questions about 1INME — domains, coins, the free plan, refunds, analytics, teams and more."
    >
      <section className="py-20 lg:py-32">
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

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
            className="max-w-3xl mx-auto glass-card p-4 sm:p-8 rounded-3xl"
          >
            <Accordion type="single" collapsible className="w-full">
              {faqs.map((faq, index) => (
                <AccordionItem key={index} value={`item-${index}`}>
                  <AccordionTrigger className="text-left text-base font-semibold hover:text-primary">
                    {faq.question}
                  </AccordionTrigger>
                  <AccordionContent className="text-muted-foreground leading-relaxed text-base">
                    {faq.answer}
                  </AccordionContent>
                </AccordionItem>
              ))}
            </Accordion>
          </motion.div>

          <div className="max-w-3xl mx-auto text-center mt-16">
            <p className="text-muted-foreground mb-6">
              Still have questions?
            </p>
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
