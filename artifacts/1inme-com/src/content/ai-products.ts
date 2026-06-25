export interface AiProduct {
  slug: string;
  eyebrow: string;
  tagline: string;
  navDesc: string;
  accent: string;
  title: string;
  description: string;
  sections: { heading: string; body: string }[];
  faqs: { question: string; answer: string }[];
}

const commonAiFaqs = [
  { question: "Do I need to write any prompts or code?", answer: "No — it trains on your existing 1INME content automatically. You can refine its tone in plain language, no prompts or code required." },
  { question: "What languages does it support?", answer: "Dozens of languages out of the box — it answers each visitor in the language they write in." },
  { question: "Can I hand off to a human?", answer: "Yes — at any point it can route the conversation to you, dropping the full transcript into your unified inbox." },
  { question: "How is my plan billed for usage?", answer: "Usage is metered against your plan's monthly AI allowance; overage is covered by your coin wallet so you're never cut off mid-conversation." },
];

export const aiProducts: AiProduct[] = [
  {
    slug: "ai-chatbot",
    eyebrow: "AI Chatbot",
    tagline: "A 24/7 chatbot for your Link in Bio — trained on you, on-brand, never asleep.",
    navDesc: "24/7 chatbot trained on your Link in Bio",
    accent: "#7c3aed",
    title: "AI Chatbot",
    description:
      "Drop a 24/7 AI chatbot onto your Link in Bio that greets every visitor in your voice, answers from your real content, captures leads and books calls — never asleep.",
    sections: [
      { heading: "Always on, always on-brand", body: "Greets every visitor in your tone of voice, day or night, so no question goes unanswered." },
      { heading: "Train it on what you already have", body: "It learns from your Link in Bio content, links and pages automatically — no prompts or setup docs needed." },
      { heading: "Captures leads while you sleep", body: "Collects names, emails and intent in conversation and drops them straight into your contacts." },
      { heading: "Books calls without back-and-forth", body: "Surfaces your calendar and books real meetings inside the chat." },
      { heading: "Hand-off to a human", body: "When it matters, it routes the conversation to you with the full transcript attached." },
      { heading: "You stay in control", body: "Review transcripts, tune its tone and set guardrails on what it can and can't say." },
    ],
    faqs: [
      { question: "Where does the chatbot show up?", answer: "It lives on your Link in Bio page, greeting visitors the moment they land — with an optional launcher bubble." },
      ...commonAiFaqs,
    ],
  },
  {
    slug: "ai-agent",
    eyebrow: "AI Agent",
    tagline: "A multi-step AI teammate that actually gets things done.",
    navDesc: "Runs multi-step playbooks for you",
    accent: "#1bd4d9",
    title: "AI Agent",
    description:
      "A multi-step AI agent that runs real tasks across your inbox, calendar and CRM — qualifying leads, drafting outreach and following up on its own.",
    sections: [
      { heading: "A teammate, not just a chatbot", body: "Runs multi-step tasks end to end instead of answering one question at a time." },
      { heading: "Connects your tools", body: "Works across your inbox, calendar and contacts to actually move work forward." },
      { heading: "Runs playbooks you can edit", body: "Define repeatable workflows in plain language and let the agent execute them." },
      { heading: "Knows when to ask", body: "Pauses and checks in with you whenever a decision needs a human." },
      { heading: "Memory that compounds", body: "Remembers context across conversations so every follow-up gets smarter." },
      { heading: "Full audit trail", body: "Every action the agent takes is logged so you always know what happened and why." },
    ],
    faqs: [
      { question: "What kinds of tasks can it run?", answer: "Qualifying leads, drafting and sending outreach, updating contacts, scheduling and following up — across the tools you connect." },
      ...commonAiFaqs,
    ],
  },
  {
    slug: "ai-widget",
    eyebrow: "AI Widget",
    tagline: "Embed an AI assistant on any website in one snippet.",
    navDesc: "Embeddable AI assistant for any site",
    accent: "#e94e8c",
    title: "AI Widget",
    description:
      "Embed an AI assistant on any website with one snippet — it answers questions, captures leads in context and routes the hot ones to your inbox.",
    sections: [
      { heading: "One snippet, any site", body: "Paste a single line on WordPress, Shopify, Webflow or your own site and the assistant is live." },
      { heading: "Looks like part of your brand", body: "Match colors, position and copy so it feels native to your site, not bolted on." },
      { heading: "Trained on your content", body: "It answers from your 1INME content and anything you add, so replies are accurate." },
      { heading: "Captures leads in context", body: "Knows what page a visitor is on and captures the right details at the right moment." },
      { heading: "Multi-language out of the box", body: "Replies in the visitor's language automatically, with no extra configuration." },
      { heading: "Privacy-first analytics", body: "See what visitors ask and where leads come from, without invasive tracking." },
    ],
    faqs: [
      { question: "Does it work on any website?", answer: "Yes — one snippet works on WordPress, Shopify, Webflow, Squarespace or any custom site." },
      ...commonAiFaqs,
    ],
  },
  {
    slug: "ai-voice-assistant",
    eyebrow: "AI Voice Assistant",
    tagline: "Pick up every call in your voice — never miss another lead.",
    navDesc: "AI receptionist that answers your calls",
    accent: "#ff8a3c",
    title: "AI Voice Assistant",
    description:
      "An AI voice assistant that picks up calls to your number in your voice, qualifies callers, books real meetings and warm-transfers when it matters.",
    sections: [
      { heading: "Never miss another call", body: "Picks up every call to your number, day or night, so a missed call never means a missed lead." },
      { heading: "Sounds like you, not a robot", body: "Answers in a natural voice you can tune, so callers feel taken care of." },
      { heading: "Qualifies and routes", body: "Asks the right questions, captures details and decides what happens next." },
      { heading: "Books real meetings", body: "Checks your calendar and books appointments right there on the call." },
      { heading: "Full transcript and recap", body: "Every call is transcribed and summarised, dropped straight into your inbox." },
      { heading: "You stay in control", body: "Set what it can promise, when to warm-transfer to you, and review every call." },
    ],
    faqs: [
      { question: "Do I get a phone number?", answer: "Yes — connect your existing number or get a new one, and the assistant answers calls to it." },
      ...commonAiFaqs,
    ],
  },
];

export function getAiProduct(slug: string): AiProduct | undefined {
  return aiProducts.find((p) => p.slug === slug);
}
