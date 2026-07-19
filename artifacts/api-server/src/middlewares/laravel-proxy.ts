import { Readable } from "node:stream";
import type { RequestHandler } from "express";

// ---------------------------------------------------------------------------
// Laravel fallthrough proxy  (DEV / PREVIEW ONLY)
// ---------------------------------------------------------------------------
// The api-server's artifact.toml claims only the specific paths it natively
// implements ("/api/healthz", "/api/contact"). The shared proxy (Replit) and
// the EC2 Nginx config both use most-specific-first routing, so "/api/v1/*"
// requests fall straight through to the Laravel service (mounted at "/")
// WITHOUT passing through Express — in both dev and production.
//
// In PRODUCTION this middleware must never be reached for Laravel traffic.
// If it is, the routing is misconfigured and we fail fast with a 404 instead
// of silently attempting localhost:5000 (which does not exist on EC2/Replit
// production deployments, producing the "couldn't reach backend" 502 error).
//
// In DEV/PREVIEW this proxy remains active so the mobile app and API clients
// can reach Laravel through the single :8080 Express port during development.
//
// The upstream target is configurable via LARAVEL_BACKEND_URL.

const DEFAULT_LARAVEL_BACKEND_URL = "http://127.0.0.1:5000";

function laravelBaseUrl(): string {
  const fromEnv = process.env["LARAVEL_BACKEND_URL"];
  return (fromEnv && fromEnv.trim() !== ""
    ? fromEnv.trim()
    : DEFAULT_LARAVEL_BACKEND_URL
  ).replace(/\/$/, "");
}

// Hop-by-hop headers must not be forwarded between connections (RFC 7230 §6.1).
const HOP_BY_HOP = new Set([
  "connection",
  "keep-alive",
  "proxy-authenticate",
  "proxy-authorization",
  "te",
  "trailer",
  "transfer-encoding",
  "upgrade",
]);

export const laravelProxy: RequestHandler = async (req, res) => {
  // ------------------------------------------------------------------
  // Production guard — fail fast instead of attempting localhost:5000.
  // ------------------------------------------------------------------
  // In production, the EC2 Nginx config and the Replit artifact.toml both
  // route only /api/healthz and /api/contact to this Express process.
  // Any other request that arrives here means a routing misconfiguration
  // (e.g. Nginx still has the old `location /api` broad match).
  // We return an explicit 404 with a clear error code so the misconfiguration
  // is immediately visible in logs and to the caller, rather than a confusing
  // "couldn't reach backend" 502 from a failed localhost:5000 connection.
  if (process.env["NODE_ENV"] === "production") {
    req.log.error(
      {
        method: req.method,
        path: req.originalUrl,
        hint: "Check that the EC2 Nginx config (deploy/ec2/nginx/sayzio.conf) and artifact.toml paths both list only the Express-native paths (/api/healthz, /api/contact). All /api/v1/* traffic must go directly to Laravel/PHP-FPM.",
      },
      "laravel-proxy: unhandled request reached the fallthrough proxy in production — routing misconfiguration",
    );
    res.status(404).json({
      error: {
        message:
          "This route is not handled by the API server. Routing misconfiguration detected — see server logs.",
        code: "routing_misconfiguration",
      },
    });
    return;
  }

  // Preserve the full original path (including the `/api` prefix and query
  // string) so Laravel sees exactly what the client sent.
  const target = `${laravelBaseUrl()}${req.originalUrl}`;

  // Forward the incoming headers, dropping hop-by-hop and length/host/encoding
  // headers that this proxy hop must set or that would otherwise be wrong.
  const headers: Record<string, string> = {};
  for (const [key, value] of Object.entries(req.headers)) {
    const lower = key.toLowerCase();
    if (HOP_BY_HOP.has(lower)) continue;
    if (lower === "host" || lower === "content-length") continue;
    // Force an identity response so the body we stream back matches the
    // upstream Content-Type/Length without an encoding mismatch.
    if (lower === "accept-encoding") continue;
    if (value === undefined) continue;
    headers[key] = Array.isArray(value) ? value.join(", ") : value;
  }

  const method = req.method.toUpperCase();
  const hasBody = method !== "GET" && method !== "HEAD";

  // Reconstruct the request body. express.json()/urlencoded() already consumed
  // the stream for those content types, so re-serialize from req.body. For any
  // other content type (e.g. multipart uploads) the raw stream is untouched, so
  // pipe it through directly.
  let body: string | ReadableStream | undefined;
  const contentType = (req.headers["content-type"] ?? "").toLowerCase();
  if (hasBody) {
    if (contentType.includes("application/json")) {
      body = req.body == null ? undefined : JSON.stringify(req.body);
    } else if (contentType.includes("application/x-www-form-urlencoded")) {
      body =
        req.body && typeof req.body === "object"
          ? new URLSearchParams(
              req.body as Record<string, string>,
            ).toString()
          : undefined;
    } else {
      body = Readable.toWeb(req) as unknown as ReadableStream;
    }
  }

  // A streaming body can only be consumed once, so alternate-target retries
  // are only safe for re-serializable bodies (string/undefined).
  const canRetry = !(body instanceof ReadableStream);
  const targets = [target];
  if (canRetry) {
    for (const alt of ["http://localhost:5000", "http://[::1]:5000"]) {
      const altTarget = `${alt}${req.originalUrl}`;
      if (!targets.includes(altTarget) && !process.env["LARAVEL_BACKEND_URL"]) {
        targets.push(altTarget);
      }
    }
  }

  let upstream: Response | undefined;
  let lastErr: unknown;
  for (const t of targets) {
    try {
      upstream = await fetch(t, {
        method,
        headers,
        body,
        redirect: "manual",
        // Required when sending a streaming body.
        ...(body instanceof ReadableStream ? { duplex: "half" } : {}),
      } as RequestInit);
      break;
    } catch (err) {
      lastErr = err;
      req.log.error(
        { err, target: t, method },
        "laravel-proxy: failed to reach Laravel backend",
      );
    }
  }
  if (!upstream) {
    // Surface a non-sensitive error code (e.g. ECONNREFUSED, UND_ERR_*) so
    // production failures are diagnosable from the response alone — the
    // deployment logs do not reliably surface request-time app logs.
    const cause = (lastErr as { cause?: { code?: string } } | undefined)
      ?.cause;
    const causeCode =
      typeof cause?.code === "string"
        ? cause.code
        : lastErr instanceof Error
          ? lastErr.name
          : "unknown";
    res.status(502).json({
      error: {
        message:
          "We couldn't reach the application backend. Please try again.",
        code: "upstream_unavailable",
        details: { cause: causeCode },
      },
    });
    return;
  }

  res.status(upstream.status);

  upstream.headers.forEach((value, key) => {
    const lower = key.toLowerCase();
    if (HOP_BY_HOP.has(lower)) return;
    // Let Node manage the framing of the body we stream back.
    if (lower === "content-length") return;
    res.setHeader(key, value);
  });

  if (!upstream.body) {
    res.end();
    return;
  }

  try {
    const nodeStream = Readable.fromWeb(
      upstream.body as unknown as Parameters<typeof Readable.fromWeb>[0],
    );
    nodeStream.on("error", (err) => {
      req.log.error({ err, target }, "laravel-proxy: upstream stream error");
      if (!res.headersSent) {
        res.status(502).end();
      } else {
        res.destroy(err);
      }
    });
    nodeStream.pipe(res);
  } catch (err) {
    req.log.error({ err, target }, "laravel-proxy: failed streaming response");
    if (!res.headersSent) {
      res.status(502).end();
    } else {
      res.end();
    }
  }
};

export default laravelProxy;
