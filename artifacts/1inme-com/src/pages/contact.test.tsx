import { describe, it, expect, beforeEach, vi } from "vitest";
import { render, screen } from "@testing-library/react";

/**
 * Frontend guardrail for the marketing Contact page (task #3259).
 *
 * Asserts that contact.tsx renders the contact details fetched from the product
 * app's /api/v1/site/contact endpoint, AND that when the fetch fails it falls
 * back to the correct brand defaults — hello@sayzio.app, the Banjara Hills
 * address, and NO fake phone number — never the old stale placeholders
 * (support@1inme.com / a made-up phone). Without this, a regression in the
 * fetcher or the defaults could silently ship wrong contact info to visitors.
 */

// PageLayout pulls in Header/Footer/SEO which need router/theme context we
// don't care about here — stub it to a passthrough so we test the contact
// content wiring in isolation.
vi.mock("@/components/layout/page-layout", () => ({
  PageLayout: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

// The contact form's submit path talks to the product API; stub the client so
// importing the page doesn't drag in real network code.
vi.mock("@workspace/api-client-react", () => ({
  submitContactMessage: vi.fn(),
  ApiError: class ApiError extends Error {
    status = 0;
    data: unknown = null;
  },
}));

import Contact from "@/pages/contact";

beforeEach(() => {
  // jsdom lacks the observers framer-motion (whileInView) and Radix rely on.
  global.IntersectionObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
    takeRecords() {
      return [];
    }
  } as unknown as typeof IntersectionObserver;
  global.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
});

describe("Contact page — contact details", () => {
  it("renders the values fetched from /api/v1/site/contact", async () => {
    const payload = {
      data: {
        title: "Contact us",
        email: "override@sayzio.app",
        phone: "+91 40 5555 0000",
        address: "Override Tower\n99 Editable Street, Hyderabad",
        hours: "",
        social: {
          twitter: "",
          instagram: "",
          linkedin: "",
          youtube: "",
          facebook: "",
        },
        map: { lat: 0, lng: 0, zoom: 1, label: "" },
      },
    };
    global.fetch = vi.fn(() =>
      Promise.resolve({
        ok: true,
        status: 200,
        json: async () => payload,
      }),
    ) as unknown as typeof fetch;

    render(<Contact />);

    // The fetched email/address/phone replace the defaults.
    expect(await screen.findByText("override@sayzio.app")).toBeTruthy();
    expect(screen.getByText(/Override Tower/)).toBeTruthy();
    expect(screen.getByText("+91 40 5555 0000")).toBeTruthy();
    // A phone was provided, so the "Call us" card shows.
    expect(screen.getByText("Call us")).toBeTruthy();
  });

  it("falls back to the correct brand defaults when the fetch fails", async () => {
    global.fetch = vi.fn(() =>
      Promise.reject(new Error("network down")),
    ) as unknown as typeof fetch;

    render(<Contact />);

    // Real brand defaults — not stale placeholders.
    expect(await screen.findByText("hello@sayzio.app")).toBeTruthy();
    expect(screen.getByText(/Banjara Hills/)).toBeTruthy();
    expect(screen.getByText(/EEFind Private Limited/)).toBeTruthy();

    // No fake phone: the default phone is blank, so the "Call us" card is
    // never rendered, and the old placeholder email must be gone.
    expect(screen.queryByText("Call us")).toBeNull();
    expect(screen.queryByText(/support@1inme\.com/)).toBeNull();
    expect(screen.queryByText(/\+91 40 1234 5678/)).toBeNull();
  });
});
