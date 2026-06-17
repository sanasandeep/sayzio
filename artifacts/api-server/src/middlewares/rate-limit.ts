import rateLimit from "express-rate-limit";

/**
 * Per-IP rate limiter for the public contact endpoint.
 *
 * The contact form already has a honeypot, but a determined bot that ignores
 * it could still flood the database with rapid submissions. This caps each
 * client to a small number of submissions per minute and returns the unified
 * `{ error: { message, code } }` envelope (with HTTP 429) when exceeded, so
 * the frontend can surface it in its existing error state.
 */
export const contactRateLimiter = rateLimit({
  windowMs: 60 * 1000,
  limit: 5,
  standardHeaders: "draft-7",
  legacyHeaders: false,
  // Preserve honeypot behavior: requests with a populated `website` field are
  // bots and must always receive the deceptive 201 success response from the
  // route handler, so they bypass the limiter entirely (and never see a 429).
  skip: (req) =>
    typeof req.body?.website === "string" && req.body.website.trim() !== "",
  handler: (req, res) => {
    req.log.warn({ ip: req.ip }, "contact: rate limit exceeded");
    res.status(429).json({
      error: {
        message:
          "You're sending messages a little too quickly. Please wait a minute and try again.",
        code: "rate_limited",
      },
    });
  },
});
