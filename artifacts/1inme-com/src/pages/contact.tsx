import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { motion } from "framer-motion";
import { Mail, MapPin, Clock, Languages, Zap, Handshake, Lightbulb, Check, AlertCircle, Loader2 } from "lucide-react";
import { useState } from "react";
import { submitContactMessage, ApiError } from "@workspace/api-client-react";

const SUPPORT_EMAIL = "support@1inme.com";

export default function Contact() {
  const [submitted, setSubmitted] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({
    name: "",
    email: "",
    subject: "",
    message: "",
    // Honeypot — hidden from real users; bots that fill it are dropped server-side.
    website: "",
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (submitting) return;
    setError(null);
    setSubmitting(true);

    try {
      await submitContactMessage({
        name: form.name,
        email: form.email,
        subject: form.subject,
        message: form.message,
        website: form.website,
      });
      setSubmitted(true);
    } catch (err) {
      if (err instanceof ApiError && err.status === 429) {
        const data = err.data as { error?: { message?: string } } | null;
        setError(
          data?.error?.message ??
            "You're sending messages a little too quickly. Please wait a minute and try again.",
        );
      } else {
        setError(
          "We couldn't send your message. Please try again, or email us directly below.",
        );
      }
    } finally {
      setSubmitting(false);
    }
  };

  const mailtoHref = `mailto:${SUPPORT_EMAIL}?subject=${encodeURIComponent(
    form.subject || "Hello from 1inme.com",
  )}&body=${encodeURIComponent(
    `${form.message}\n\n— ${form.name} (${form.email})`,
  )}`;

  const cards = [
    {
      icon: Zap,
      title: "Fast replies",
      description: "We answer within one business day.",
    },
    {
      icon: Handshake,
      title: "Partnerships",
      description: "Let's build something together.",
    },
    {
      icon: Lightbulb,
      title: "Feature ideas",
      description: "Tell us what would make 1INME better.",
    },
  ];

  return (
    <PageLayout
      title="Contact"
      description="We love hearing from you. Reach the 1INME team — we reply within one business day."
    >
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-3xl mx-auto text-center mb-16">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              Contact us
            </p>
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              We love{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                hearing from you.
              </span>
            </h1>
            <div className="flex flex-wrap justify-center gap-x-6 gap-y-3 text-sm text-muted-foreground">
              <span className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-primary" /> Replies within 1 business day
              </span>
              <span className="flex items-center gap-2">
                <Languages className="w-4 h-4 text-primary" /> EN · हिन्दी
              </span>
            </div>
          </div>

          <div className="grid lg:grid-cols-5 gap-12 max-w-5xl mx-auto">
            <div className="lg:col-span-2 space-y-6">
              <div className="glass-card p-6 rounded-3xl">
                <div className="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mb-4">
                  <Mail className="w-6 h-6" />
                </div>
                <h3 className="font-semibold mb-1">Email us</h3>
                <a
                  href={`mailto:${SUPPORT_EMAIL}`}
                  className="text-muted-foreground hover:text-primary transition-colors break-all"
                >
                  {SUPPORT_EMAIL}
                </a>
              </div>

              <div className="glass-card p-6 rounded-3xl">
                <div className="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mb-4">
                  <MapPin className="w-6 h-6" />
                </div>
                <h3 className="font-semibold mb-1">Where we are</h3>
                <p className="text-muted-foreground">Hyderabad · India</p>
              </div>

              <div className="space-y-3">
                {cards.map((card) => (
                  <div
                    key={card.title}
                    className="flex items-start gap-4 p-4 rounded-2xl border bg-card/30"
                  >
                    <div className="w-10 h-10 shrink-0 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                      <card.icon className="w-5 h-5" />
                    </div>
                    <div>
                      <h4 className="font-medium text-sm">{card.title}</h4>
                      <p className="text-sm text-muted-foreground">
                        {card.description}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5 }}
              className="lg:col-span-3 glass-card p-8 rounded-3xl"
            >
              {submitted ? (
                <div className="h-full flex flex-col items-center justify-center text-center py-12">
                  <div className="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-6">
                    <Check className="w-8 h-8" />
                  </div>
                  <h3 className="text-2xl font-bold mb-3">Thanks for reaching out</h3>
                  <p className="text-muted-foreground mb-8 max-w-sm">
                    We've received your message and will reply within one business
                    day. Prefer email? Send it directly below.
                  </p>
                  <Button asChild variant="outline" className="rounded-full">
                    <a href={mailtoHref}>Email us directly</a>
                  </Button>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-6">
                  {/* Honeypot: visually hidden and off-screen, hidden from
                      assistive tech and excluded from tab order. Bots that
                      autofill it get their submission dropped server-side. */}
                  <div
                    aria-hidden="true"
                    className="absolute -left-[9999px] top-0 h-0 w-0 overflow-hidden"
                  >
                    <label htmlFor="website">Leave this field empty</label>
                    <input
                      id="website"
                      name="website"
                      type="text"
                      tabIndex={-1}
                      autoComplete="off"
                      value={form.website}
                      onChange={(e) =>
                        setForm({ ...form, website: e.target.value })
                      }
                    />
                  </div>
                  <div className="grid sm:grid-cols-2 gap-6">
                    <div className="space-y-2">
                      <Label htmlFor="name">Your name</Label>
                      <Input
                        id="name"
                        required
                        value={form.name}
                        onChange={(e) =>
                          setForm({ ...form, name: e.target.value })
                        }
                        placeholder="Jane Doe"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="email">Email</Label>
                      <Input
                        id="email"
                        type="email"
                        required
                        value={form.email}
                        onChange={(e) =>
                          setForm({ ...form, email: e.target.value })
                        }
                        placeholder="jane@example.com"
                      />
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="subject">Subject</Label>
                    <Input
                      id="subject"
                      required
                      value={form.subject}
                      onChange={(e) =>
                        setForm({ ...form, subject: e.target.value })
                      }
                      placeholder="How can we help?"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="message">Message</Label>
                    <Textarea
                      id="message"
                      required
                      rows={6}
                      value={form.message}
                      onChange={(e) =>
                        setForm({ ...form, message: e.target.value })
                      }
                      placeholder="Tell us a little more..."
                    />
                  </div>
                  {error && (
                    <div
                      role="alert"
                      className="flex items-start gap-3 rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
                    >
                      <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                      <span>{error}</span>
                    </div>
                  )}
                  <Button
                    type="submit"
                    size="lg"
                    disabled={submitting}
                    className="w-full rounded-full h-12"
                  >
                    {submitting ? (
                      <>
                        <Loader2 className="w-4 h-4 animate-spin" />
                        Sending...
                      </>
                    ) : (
                      "Send message"
                    )}
                  </Button>
                </form>
              )}
            </motion.div>
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
