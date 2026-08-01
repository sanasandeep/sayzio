/**
 * Bundled brand icons for the new-tab quick links.
 *
 * Inline SVGs (official-style logo marks) so the tiles render instantly,
 * offline, and without contacting any third-party favicon service —
 * matching the app's privacy stance. The Sayzio tile uses the bundled
 * app icon PNG.
 */
import zioIcon from '../assets/zio-icon.png';

interface IconProps {
  size?: number;
}

export function SayzioIcon({ size = 24 }: IconProps) {
  return <img src={zioIcon} width={size} height={size} style={{ borderRadius: 6, flexShrink: 0 }} alt="" />;
}

export function GmailIcon({ size = 24 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true">
      <path fill="#4285F4" d="M19 20h1.5a1.5 1.5 0 0 0 1.5-1.5V7l-3 2.2V20Z" />
      <path fill="#34A853" d="M5 20H3.5A1.5 1.5 0 0 1 2 18.5V7l3 2.2V20Z" />
      <path fill="#FBBC04" d="M2 7v-.9a2 2 0 0 1 3.2-1.6L5 4.7v4.5L2 7Z" />
      <path fill="#C5221F" d="M22 7v-.9a2 2 0 0 0-3.2-1.6l-.2.2.4 4.5L22 7Z" />
      <path fill="#EA4335" d="M5 9.2V4.7L12 10l7-5.3v4.5L12 14.4 5 9.2Z" />
    </svg>
  );
}

export function GitHubIcon({ size = 24 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true">
      <path
        fill="var(--color-text)"
        d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.58.11.79-.25.79-.55 0-.27-.01-1.17-.02-2.12-3.2.7-3.88-1.36-3.88-1.36-.52-1.33-1.28-1.68-1.28-1.68-1.04-.71.08-.7.08-.7 1.15.08 1.76 1.19 1.76 1.19 1.03 1.76 2.69 1.25 3.35.96.1-.75.4-1.25.72-1.54-2.55-.29-5.24-1.28-5.24-5.69 0-1.26.45-2.28 1.19-3.09-.12-.29-.52-1.46.11-3.04 0 0 .97-.31 3.17 1.18a11 11 0 0 1 5.78 0c2.2-1.49 3.16-1.18 3.16-1.18.63 1.58.24 2.75.12 3.04.74.81 1.18 1.83 1.18 3.09 0 4.42-2.69 5.39-5.26 5.68.41.35.78 1.05.78 2.12 0 1.53-.02 2.77-.02 3.15 0 .3.21.67.8.55A11.51 11.51 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5Z"
      />
    </svg>
  );
}

export function LinkedInIcon({ size = 24 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true">
      <rect width="24" height="24" rx="4" fill="#0A66C2" />
      <path
        fill="#fff"
        d="M6.94 8.5a1.44 1.44 0 1 0 0-2.88 1.44 1.44 0 0 0 0 2.88ZM5.7 9.64h2.5v8.06H5.7V9.64Zm4.09 0h2.4v1.1h.03c.33-.63 1.15-1.3 2.37-1.3 2.53 0 3 1.67 3 3.83v4.43h-2.5v-3.93c0-.94-.02-2.14-1.3-2.14-1.31 0-1.51 1.02-1.51 2.07v4h-2.49V9.64Z"
      />
    </svg>
  );
}

export function XIcon({ size = 24 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true">
      <rect width="24" height="24" rx="5" fill="var(--color-text)" />
      <path
        fill="var(--color-bg)"
        d="M13.48 10.77 18.94 4.5h-1.3l-4.74 5.45L9.13 4.5H4.77l5.73 8.25-5.73 6.58h1.3l5-5.76 4 5.76h4.36l-5.95-8.56Zm-1.77 2.04-.58-.82-4.62-6.54h1.99l3.73 5.28.58.82 4.84 6.86h-1.99l-3.95-5.6Z"
      />
    </svg>
  );
}

export function YouTubeIcon({ size = 24 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true">
      <path
        fill="#FF0000"
        d="M23.5 7.2a3 3 0 0 0-2.12-2.13C19.5 4.55 12 4.55 12 4.55s-7.5 0-9.38.52A3 3 0 0 0 .5 7.2 31.6 31.6 0 0 0 0 12c0 1.62.17 3.23.5 4.8a3 3 0 0 0 2.12 2.13c1.88.52 9.38.52 9.38.52s7.5 0 9.38-.52a3 3 0 0 0 2.12-2.13c.33-1.57.5-3.18.5-4.8s-.17-3.23-.5-4.8Z"
      />
      <path fill="#fff" d="M9.6 15.3 15.8 12 9.6 8.7v6.6Z" />
    </svg>
  );
}
