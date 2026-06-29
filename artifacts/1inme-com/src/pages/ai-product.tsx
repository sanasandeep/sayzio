import { useParams, Link } from "wouter";
import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CTABand,
  FaqAccordion,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { getAiProduct, aiProducts } from "@/content/ai-products";
import { Bot, Workflow, Code2, PhoneCall, MessageCircle, Sparkles, type LucideIcon } from "lucide-react";
import NotFound from "@/pages/not-found";

const aiIcons: Record<string, LucideIcon> = {
  "ai-chatbot": Bot,
  "ai-agent": Workflow,
  "ai-widget": Code2,
  "ai-voice-assistant": PhoneCall,
  "whatsapp-agent": MessageCircle,
};

const AI_PRODUCT_COUNT_WORD: Record<number, string> = {
  2: "two",
  3: "three",
  4: "four",
  5: "five",
  6: "six",
  7: "seven",
};

export default function AiProduct() {
  const params = useParams();
  const product = getAiProduct(params.slug ?? "");

  if (!product) return <NotFound />;

  const others = aiProducts.filter((p) => p.slug !== product.slug);

  return (
    <PageLayout title={`${product.title} — Sayzio AI`} description={product.description}>
      <MarketingHero
        eyebrow={`AI Suite · ${product.eyebrow}`}
        title={product.tagline}
        subtitle={product.description}
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      >
        <div className="mt-10 flex flex-wrap items-center justify-center gap-3">
          <span className="glass-card rounded-full px-4 py-2 text-sm font-medium">Live in minutes</span>
          <span className="glass-card rounded-full px-4 py-2 text-sm font-medium">AI always on</span>
        </div>
      </MarketingHero>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="What it does" title={`Why creators love the ${product.eyebrow}.`} />
          <FeatureGrid
            items={product.sections.map((s) => ({ name: s.heading, description: s.body }))}
          />
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="FAQ" title="Common questions." />
          <FaqAccordion items={product.faqs} />
        </div>
      </section>

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading
            eyebrow="The rest of the AI Suite"
            title={`One login, ${AI_PRODUCT_COUNT_WORD[aiProducts.length] ?? aiProducts.length} AI products.`}
          />
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto">
            {others.map((p) => {
              const Icon = aiIcons[p.slug] ?? Sparkles;
              return (
                <Link
                  key={p.slug}
                  href={`/ai/${p.slug}`}
                  className="glass-card rounded-3xl p-6 hover:border-primary/40 transition-colors"
                >
                  <div
                    className="w-10 h-10 rounded-xl flex items-center justify-center mb-4"
                    style={{ backgroundColor: `${p.accent}1a`, color: p.accent }}
                  >
                    <Icon className="w-5 h-5" />
                  </div>
                  <div className="font-semibold mb-1">{p.eyebrow}</div>
                  <div className="text-sm text-muted-foreground">{p.navDesc}</div>
                </Link>
              );
            })}
          </div>
        </div>
      </section>

      <CTABand
        title={`Ready to put the ${product.eyebrow} to work?`}
        subtitle="Free forever to start. Upgrade only when you outgrow it."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
