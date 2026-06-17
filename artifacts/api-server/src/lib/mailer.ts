import nodemailer, { type Transporter } from "nodemailer";
import type { Logger } from "pino";

const TEAM_EMAIL = process.env["CONTACT_NOTIFICATION_TO"] ?? "support@1inme.com";

export interface ContactNotification {
  name: string;
  email: string;
  subject: string;
  message: string;
}

interface SmtpConfig {
  host: string;
  port: number;
  secure: boolean;
  auth?: { user: string; pass: string };
  from: string;
}

/**
 * Reads SMTP configuration from environment variables. Returns null when the
 * minimum required settings (host + from address) are absent, so the contact
 * form keeps working (submissions still stored) before SMTP is configured.
 *
 * Expected variables:
 *   SMTP_HOST      - SMTP server hostname (required)
 *   SMTP_PORT      - port number (default 587)
 *   SMTP_SECURE    - "true" to use implicit TLS (default: true when port 465)
 *   SMTP_USER      - username for authentication (optional)
 *   SMTP_PASS      - password for authentication (optional)
 *   SMTP_FROM      - From address (default: SMTP_USER or CONTACT_NOTIFICATION_TO)
 */
function readSmtpConfig(): SmtpConfig | null {
  const host = process.env["SMTP_HOST"]?.trim();
  if (!host) return null;

  const port = Number(process.env["SMTP_PORT"]) || 587;
  const secureRaw = process.env["SMTP_SECURE"]?.trim().toLowerCase();
  const secure =
    secureRaw === "true" || secureRaw === "1"
      ? true
      : secureRaw === "false" || secureRaw === "0"
        ? false
        : port === 465;

  const user = process.env["SMTP_USER"]?.trim();
  const pass = process.env["SMTP_PASS"];
  const from =
    process.env["SMTP_FROM"]?.trim() || user || TEAM_EMAIL;

  const config: SmtpConfig = { host, port, secure, from };
  if (user && pass) {
    config.auth = { user, pass };
  }
  return config;
}

let transporter: Transporter | null = null;
let transporterKey: string | null = null;

function getTransporter(): { transporter: Transporter; from: string } | null {
  const config = readSmtpConfig();
  if (!config) return null;

  // Rebuild the transporter only when the underlying config changes, so the
  // connection pool is reused across submissions.
  const key = JSON.stringify(config);
  if (!transporter || transporterKey !== key) {
    transporter = nodemailer.createTransport({
      host: config.host,
      port: config.port,
      secure: config.secure,
      ...(config.auth ? { auth: config.auth } : {}),
    });
    transporterKey = key;
  }
  return { transporter, from: config.from };
}

/**
 * Sends a notification email to the team for a contact form submission.
 * Resolves `false` when SMTP is not configured (no-op); resolves `true` on a
 * successful send. Throws only on an actual delivery failure.
 */
export async function sendContactNotification(
  notification: ContactNotification,
): Promise<boolean> {
  const active = getTransporter();
  if (!active) return false;

  const { name, email, subject, message } = notification;
  const safeName = name.replace(/[\r\n]+/g, " ").trim();
  const safeSubject = subject.replace(/[\r\n]+/g, " ").trim();

  await active.transporter.sendMail({
    from: active.from,
    to: TEAM_EMAIL,
    replyTo: { name: safeName, address: email },
    subject: `[Contact form] ${safeSubject}`,
    text:
      `New contact form submission from ${safeName} <${email}>.\n\n` +
      `Subject: ${safeSubject}\n\n` +
      `Message:\n${message}\n`,
  });
  return true;
}

/**
 * Fire-and-forget wrapper used by request handlers. Never throws. Email
 * delivery is best-effort and must never block or fail the originating
 * request — the submission is already stored before this runs.
 */
export async function sendContactNotificationSafely(
  notification: ContactNotification,
  log: Logger,
): Promise<void> {
  try {
    const sent = await sendContactNotification(notification);
    if (sent) {
      log.info(
        { email: notification.email },
        "contact: notification email sent",
      );
    } else {
      log.warn(
        { email: notification.email },
        "contact: SMTP not configured; skipped notification email (submission stored)",
      );
    }
  } catch (err) {
    log.error(
      { err, email: notification.email },
      "contact: failed to send notification email (submission still stored)",
    );
  }
}
