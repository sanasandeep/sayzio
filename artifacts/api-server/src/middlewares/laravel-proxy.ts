import { Readable } from "node:stream";
import type { RequestHandler } from "express";

// ---------------------------------------------------------------------------
// Laravel fallthrough proxy
// ---------------------------------------------------------------------------
// In the Replit preview every `/api/*` request is routed by the shared proxy
// to this Node api-server, which only implements `health` and `contact`. The
// mobile app's real backend (auth, profile, links, onboarding, ...) lives in
// the Laravel app mounted at `/`. To make the mobile app work end-to-end in
// the preview, any `/api` request this server does NOT handle itself is
// forwarded to the local Laravel backend.
//
// The upstream target is configurable via LARAVEL_BACKEND_URL with a sensible
// local default. In production the mobile app talks to Laravel directly (the
// api-server never receives these calls), so this path is preview-only and
// leaves production behavior unchanged.

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

  let upstream: Response;
  try {
    upstream = await fetch(target, {
      method,
      headers,
      body,
      redirect: "manual",
      // Required when sending a streaming body.
      ...(body instanceof ReadableStream ? { duplex: "half" } : {}),
    } as RequestInit);
  } catch (err) {
    req.log.error(
      { err, target, method },
      "laravel-proxy: failed to reach Laravel backend",
    );
    res.status(502).json({
      error: {
        message:
          "We couldn't reach the application backend. Please try again.",
        code: "upstream_unavailable",
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
