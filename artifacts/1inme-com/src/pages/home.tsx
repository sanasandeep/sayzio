import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { motion, useReducedMotion } from "framer-motion";
import { SIGNUP_URL } from "@/config";
import { Link } from "wouter";
import { ArrowRight, BarChart3, Link2, MessageSquare, QrCode } from "lucide-react";
import { useState, useEffect } from "react";

const ROLES = ["Creators", "Coaches", "Freelancers", "Agencies", "Businesses"];

export default function Home() {
  const [roleIndex, setRoleIndex] = useState(0);
  const prefersReducedMotion = useReducedMotion();

  useEffect(() => {
    if (prefersReducedMotion) return;
    const interval = setInterval(() => {
      setRoleIndex((current) => (current + 1) % ROLES.length);
    }, 2000);
    return () => clearInterval(interval);
  }, [prefersReducedMotion]);

  return (
    <PageLayout
      title="All-in-one Link Platform"
      description="One link to show your work, capture leads, message your audience, and sell."
    >
      {/* Hero Section */}
      <section className="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        <div className="container mx-auto px-6 relative z-10">
          <div className="grid lg:grid-cols-2 gap-12 items-center">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6 }}
              className="max-w-2xl"
            >
              <h1 className="text-5xl lg:text-7xl font-bold tracking-tight text-foreground mb-6 leading-tight">
                Your biolink,{" "}
                <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                  reimagined.
                </span>
              </h1>
              
              <div className="text-2xl lg:text-3xl font-medium text-muted-foreground mb-6 h-10">
                Built for <span className="text-foreground transition-all duration-300">{ROLES[roleIndex]}</span>
              </div>

              <p className="text-lg lg:text-xl text-muted-foreground mb-8 max-w-xl">
                One link to show your work, capture leads, message your audience, and sell. Professional biolinks, short links, and QR codes that convert.
              </p>

              <div className="text-base font-medium text-foreground mb-10 p-4 rounded-xl border bg-card/30 backdrop-blur-sm shadow-sm inline-block">
                Everything you need to build, grow, and monetize your audience — without juggling ten different tools.
              </div>

              <div className="flex flex-col sm:flex-row gap-4">
                <Button asChild size="lg" className="rounded-full h-14 px-8 text-base">
                  <a href={SIGNUP_URL}>
                    Sign up free <ArrowRight className="ml-2 w-5 h-5" />
                  </a>
                </Button>
                <Button asChild variant="outline" size="lg" className="rounded-full h-14 px-8 text-base bg-transparent border-primary/20 hover:bg-primary/5">
                  <Link href="/features">See how it works</Link>
                </Button>
              </div>

              <div className="mt-12 flex flex-wrap gap-x-8 gap-y-4 text-sm font-medium text-muted-foreground">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-green-500" />
                  120,000+ creators served
                </div>
                <div>40+ block types</div>
                <div>9 teammates</div>
                <div>3 years young</div>
              </div>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.8, delay: 0.2 }}
              className="relative lg:ml-auto"
            >
              <div className="relative w-full max-w-[340px] mx-auto">
                <img 
                  src={`${import.meta.env.BASE_URL}hero-mockup.png`} 
                  alt="1INME Biolink Profile" 
                  className="w-full h-auto drop-shadow-2xl rounded-[3rem]"
                />
                
                {/* Floating Badges */}
                <motion.div 
                  animate={{ y: [0, -10, 0] }}
                  transition={{ repeat: Infinity, duration: 4, ease: "easeInOut" }}
                  className="absolute top-1/4 -left-12 glass-card py-2 px-4 rounded-full text-sm font-semibold flex items-center gap-2 whitespace-nowrap"
                >
                  <span className="w-2 h-2 rounded-full bg-red-500 animate-pulse" />
                  Live · 247 visitors
                </motion.div>

                <motion.div 
                  animate={{ y: [0, 10, 0] }}
                  transition={{ repeat: Infinity, duration: 5, ease: "easeInOut", delay: 1 }}
                  className="absolute bottom-1/3 -right-16 glass-card py-3 px-5 rounded-2xl text-sm font-semibold flex flex-col gap-1 whitespace-nowrap"
                >
                  <span className="text-muted-foreground text-xs">Top link today</span>
                  <span className="text-primary flex items-center gap-2">
                    /new-album <span className="text-green-500">+18%</span>
                  </span>
                </motion.div>
              </div>
            </motion.div>
          </div>
        </div>
      </section>

      {/* Quick Feature Highlights */}
      <section className="py-24 bg-card/50 border-y">
        <div className="container mx-auto px-6">
          <div className="text-center mb-16">
            <h2 className="text-3xl font-bold mb-4">Everything you need, in one place.</h2>
            <p className="text-muted-foreground max-w-2xl mx-auto">Replace link-in-bio tools, link shorteners, analytics dashboards, and basic CRMs with a single, powerful platform.</p>
          </div>
          
          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
            {[
              { icon: Link2, title: "Biolink Builder", desc: "Drag, drop, ship. Professional pages in minutes." },
              { icon: QrCode, title: "Dynamic QR", desc: "High-resolution, editable, branded codes." },
              { icon: BarChart3, title: "Live Analytics", desc: "Real-time insights and conversion tracking." },
              { icon: MessageSquare, title: "Forms & Contacts", desc: "Capture leads and send broadcast messages." }
            ].map((feature, i) => (
              <motion.div 
                key={i}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.1 }}
                className="glass-card p-6 rounded-2xl"
              >
                <div className="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary mb-4">
                  <feature.icon className="w-6 h-6" />
                </div>
                <h3 className="text-lg font-semibold mb-2">{feature.title}</h3>
                <p className="text-muted-foreground text-sm">{feature.desc}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
