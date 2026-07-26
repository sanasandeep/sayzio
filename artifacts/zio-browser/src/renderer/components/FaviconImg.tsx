/**
 * Favicon image with a deterministic local fallback.
 *
 * Renders the given favicon URL; if it's missing or fails to load
 * (offline, 404 /favicon.ico, blocked), renders the provided fallback
 * node instead so icon slots never appear blank.
 */
import { useState, useEffect } from 'react';

interface Props {
  src: string | null | undefined;
  size: number;
  fallback: React.ReactNode;
}

export function FaviconImg({ src, size, fallback }: Props) {
  const [failed, setFailed] = useState(false);
  useEffect(() => { setFailed(false); }, [src]);
  if (!src || failed) return <>{fallback}</>;
  return (
    <img
      src={src}
      width={size}
      height={size}
      style={{ borderRadius: size >= 16 ? 3 : 2, flexShrink: 0 }}
      alt=""
      onError={() => setFailed(true)}
    />
  );
}
