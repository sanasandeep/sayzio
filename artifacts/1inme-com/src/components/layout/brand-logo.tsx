import { useEffect, useState } from "react";
import { useTheme } from "@/components/theme-provider";
import { fetchBrandLogos, type BrandLogos } from "@/lib/branding";

/**
 * Renders the admin-configured brand logo, swapping the light/dark variant
 * with the site theme. While the logo is loading — or if the feed is
 * unreachable / unset — it falls back to the "1INME" text wordmark so the
 * header/footer never render empty.
 */
export function BrandLogo({
  imgHeight = 28,
  textClassName,
}: {
  /** Rendered logo image height in px. */
  imgHeight?: number;
  /** Class applied to the text-wordmark fallback. */
  textClassName?: string;
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

  const src = resolved === "dark" ? logos?.logoDark : logos?.logoLight;

  if (src) {
    return (
      <img
        src={src}
        alt="1INME"
        style={{ height: imgHeight, width: "auto" }}
        className="block"
      />
    );
  }

  return <span className={textClassName}>1INME</span>;
}
