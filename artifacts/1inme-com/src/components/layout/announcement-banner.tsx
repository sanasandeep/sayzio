import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Megaphone, ArrowRight, X } from "lucide-react";
import { fetchAnnouncements, type Announcement } from "@/lib/announcements";

const STORE_KEY = "1inme_anno_dismissed";

function readDismissed(): Record<string, number> {
  try {
    return JSON.parse(localStorage.getItem(STORE_KEY) || "{}") || {};
  } catch {
    return {};
  }
}

function writeDismissed(obj: Record<string, number>) {
  try {
    localStorage.setItem(STORE_KEY, JSON.stringify(obj));
  } catch {
    /* ignore quota / private-mode errors */
  }
}

/**
 * Renders admin-authored marketing/guest announcement banners as a fixed bar
 * pinned above the (also-fixed) site header. Publishes its height as the
 * `--inme-anno-h` CSS variable so the header + page content can offset for it.
 * Dismissals persist per (audience, version) in localStorage.
 */
export function AnnouncementBanner() {
  const { data } = useQuery({
    queryKey: ["announcements"],
    queryFn: ({ signal }) => fetchAnnouncements(signal),
    staleTime: 60_000,
    retry: false,
  });

  const [dismissed, setDismissed] = useState<Record<string, number>>(() =>
    readDismissed(),
  );
  const ref = useRef<HTMLDivElement | null>(null);

  const visible: Announcement[] = (data ?? []).filter(
    (a) => a.message.trim() !== "" && (dismissed[a.audience] || 0) < a.version,
  );

  // Keep the header/content offset in sync with the live banner height.
  useLayoutEffect(() => {
    const root = document.documentElement;
    if (visible.length === 0) {
      root.style.removeProperty("--inme-anno-h");
      return;
    }
    const el = ref.current;
    if (!el) return;
    const apply = () =>
      root.style.setProperty("--inme-anno-h", `${el.offsetHeight}px`);
    apply();
    const ro = new ResizeObserver(apply);
    ro.observe(el);
    return () => {
      ro.disconnect();
      root.style.removeProperty("--inme-anno-h");
    };
  }, [visible.length]);

  // Clear the offset on unmount (e.g. fast route changes) as a safety net.
  useEffect(() => {
    return () => {
      document.documentElement.style.removeProperty("--inme-anno-h");
    };
  }, []);

  if (visible.length === 0) return null;

  function dismiss(a: Announcement) {
    const next = { ...readDismissed(), [a.audience]: a.version };
    writeDismissed(next);
    setDismissed(next);
  }

  return (
    <div ref={ref} className="fixed top-0 inset-x-0 z-[60]">
      {visible.map((a) => (
        <div
          key={a.audience}
          role="status"
          className="flex items-center gap-3 px-4 py-2 text-sm font-medium text-blue-50 border-b border-white/10"
          style={{
            background:
              "linear-gradient(90deg, rgba(61,107,255,0.97), rgba(110,97,255,0.97))",
          }}
        >
          <Megaphone className="h-4 w-4 shrink-0 opacity-90" />
          <span className="flex-1 min-w-0">{a.message}</span>
          {a.linkUrl !== "" && (
            <a
              href={a.linkUrl}
              rel="noopener"
              className="shrink-0 inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-semibold text-blue-800 transition-transform hover:-translate-y-0.5"
            >
              {a.linkLabel !== "" ? a.linkLabel : "Learn more"}
              <ArrowRight className="h-3 w-3" />
            </a>
          )}
          <button
            type="button"
            onClick={() => dismiss(a)}
            aria-label="Dismiss announcement"
            title="Dismiss"
            className="shrink-0 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/15 text-white/85 transition-colors hover:bg-white/25 hover:text-white"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      ))}
    </div>
  );
}
