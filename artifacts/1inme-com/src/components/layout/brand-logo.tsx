import { useEffect, useState } from "react";
import { useTheme } from "@/components/theme-provider";
import { fetchBrandLogos, type BrandLogos } from "@/lib/branding";

/**
 * Renders the admin-configured brand logo, swapping the light/dark variant
 * with the site theme. While the logo is loading — or if the feed is
 * unreachable / unset — it falls back to the "Sayzio" text wordmark so the
 * header/footer never render empty.
 */
export function BrandLogo({
  imgHeight = 28,
  textClassName,
  variant = "full",
}: {
  /** Rendered logo image height in px. */
  imgHeight?: number;
  /** Class applied to the text-wordmark fallback. */
  textClassName?: string;
  /** `full` shows the wordmark logo; `icon` shows the square brand mark. */
  variant?: "full" | "icon";
}) {
  const { theme } = useTheme();
  const [logos, setLogos] = useState<BrandLogos | null>(null);
  const [systemDark, setSystemDark] = useState<boolean>(() =>
    typeof window !== "undefined"
      ? window.matchMedia("(prefers-color-scheme: dark)").matches
      : false,
  );

  useEffect(() => {
    let alive = true;
    fetchBrandLogos().then((l) => {
      if (alive) setLogos(l);
    });
    return () => {
      alive = false;
    };
  }, []);

  // Keep the resolved variant live when theme is "system" and the OS flips.
  useEffect(() => {
    if (theme !== "system" || typeof window === "undefined") return;
    const mq = window.matchMedia("(prefers-color-scheme: dark)");
    const onChange = (e: MediaQueryListEvent) => setSystemDark(e.matches);
    setSystemDark(mq.matches);
    mq.addEventListener("change", onChange);
    return () => mq.removeEventListener("change", onChange);
  }, [theme]);

  const resolved = theme === "system" ? (systemDark ? "dark" : "light") : theme;

  const wordmark = resolved === "dark" ? logos?.logoDark : logos?.logoLight;
  // The icon mark is theme-agnostic; fall back to the wordmark for the variant.
  const src = variant === "icon" ? logos?.icon ?? wordmark : wordmark;

  if (src) {
    return (
      <img
        src={src}
        alt="Sayzio"
        style={{ height: imgHeight, width: "auto" }}
        className="block"
      />
    );
  }

  if (variant === "icon") {
    return <span className={textClassName}>S</span>;
  }

  return <span className={textClassName}>Sayzio</span>;
}
