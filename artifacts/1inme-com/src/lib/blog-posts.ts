export interface BlogPost {
  slug: string;
  title: string;
  excerpt: string;
  date: string;
  readingTime: string;
  author: string;
  category: string;
  content: string[];
}

export const blogPosts: BlogPost[] = [
  {
    slug: "one-link-to-rule-them-all",
    title: "One link to rule them all: why your bio deserves better",
    excerpt:
      "A list of buttons stopped being enough a long time ago. Here's how we think about turning a single link into a real home for your audience.",
    date: "2026-05-28",
    readingTime: "5 min read",
    author: "The 1INME Team",
    category: "Product",
    content: [
      "For years the biolink was a glorified list of buttons. You dropped in a few URLs, picked a background color, and called it a day. That was fine when the job was simply 'point people somewhere else'. It is not fine anymore.",
      "Your audience now expects to do things on your page — watch a clip, buy a product, book a call, leave their email, read your story. When every one of those actions sends them off to a different tool, you lose them at each hop. The whole point of a single link is to keep the momentum in one place.",
      "That is the lens we build through. Every block you add should earn its place by helping a visitor take the next step without leaving. Video that plays inline. Products that check out in place. Forms that capture a lead the moment someone is interested. The link stops being a directory and becomes a destination.",
      "We are not done — far from it. But the direction is fixed: one link that shows your work, captures leads, sells, messages and tells your story. Everything else is detail.",
    ],
  },
  {
    slug: "analytics-that-tell-you-what-to-fix",
    title: "Analytics that tell you what to fix, not just what happened",
    excerpt:
      "Dashboards full of numbers are easy. Knowing what to change is hard. Here's how the Performance Coach turns clicks into concrete next steps.",
    date: "2026-04-15",
    readingTime: "4 min read",
    author: "The 1INME Team",
    category: "Analytics",
    content: [
      "Most analytics tools are very good at telling you what already happened. You get charts, breakdowns, a satisfying little spike when a post does well. What they rarely tell you is the only thing that matters: what should you change next?",
      "We built the Performance Coach to close that gap. Instead of leaving you to interpret a wall of metrics, it surfaces concrete, ranked fixes — a slow-loading page, a block nobody clicks, a missing call to action, a broken link quietly leaking visitors.",
      "Each insight comes with a threshold so you understand why it fired, and a one-tap jump to the exact setting that needs attention. The goal is to shorten the distance between 'something is off' and 'it's fixed' to a single afternoon.",
      "Numbers are still there when you want them — real-time visitors, country and device breakdowns, conversion paths. But the headline is always action, not just observation.",
    ],
  },
  {
    slug: "qr-codes-you-can-actually-reprint",
    title: "QR codes you can actually reprint",
    excerpt:
      "Printed a QR code and then the link changed? With editable destinations and a built-in scannability checker, that's a non-problem.",
    date: "2026-03-02",
    readingTime: "3 min read",
    author: "The 1INME Team",
    category: "Product",
    content: [
      "There is a special kind of dread that comes from printing a thousand flyers and then realizing the QR code points to the wrong place. The old answer was to reprint everything. That is expensive and slow.",
      "Every QR code in QR Studio points to an editable link. The pattern on the page stays exactly the same, but where it sends people is yours to change at any time. Repoint a printed code to a new landing page, a seasonal offer, or an updated menu without touching the printer.",
      "We also added a live scannability checker. Before you commit a design to print, it grades contrast, logo coverage against error correction, quiet zone and risky shape combinations — so the beautiful code you made actually scans in the real world.",
      "Design freedom and reliability usually pull in opposite directions. The checker is how we let you have both.",
    ],
  },
];

export function getPostBySlug(slug: string): BlogPost | undefined {
  return blogPosts.find((post) => post.slug === slug);
}

export function formatPostDate(date: string): string {
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}
