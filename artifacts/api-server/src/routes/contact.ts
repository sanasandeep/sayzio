import { Router, type IRouter, type RequestHandler } from "express";
import { timingSafeEqual } from "node:crypto";
import {
  SubmitContactMessageBody,
  ListContactMessagesQueryParams,
  UpdateContactMessageParams,
  UpdateContactMessageBody,
} from "@workspace/api-zod";
import { db, contactMessagesTable } from "@workspace/db";
import { and, desc, eq, ilike, or, sql, type SQL } from "drizzle-orm";
import { contactRateLimiter } from "../middlewares/rate-limit";
import { sendContactNotificationSafely } from "../lib/mailer";

const router: IRouter = Router();

// ---------------------------------------------------------------------------
// Admin gate
// ---------------------------------------------------------------------------
// The contact inbox is admin-only. There is no user/session auth on this
// service, so the inbox endpoints are protected by a shared bearer token read
// from CONTACT_ADMIN_TOKEN. When the token is unset the endpoints fail closed
// (503) so the inbox is never accidentally world-readable.
function safeEqual(a: string, b: string): boolean {
  const bufA = Buffer.from(a);
  const bufB = Buffer.from(b);
  if (bufA.length !== bufB.length) return false;
  return timingSafeEqual(bufA, bufB);
}

const requireAdmin: RequestHandler = (req, res, next) => {
  const expected = process.env["CONTACT_ADMIN_TOKEN"];

  if (!expected || expected.trim() === "") {
    res.status(503).json({
      error: {
        message:
          "The contact inbox isn't configured yet. Set CONTACT_ADMIN_TOKEN on the API server to enable it.",
        code: "admin_not_configured",
      },
    });
    return;
  }

  const header = req.header("authorization") ?? "";
  const provided = header.startsWith("Bearer ")
    ? header.slice("Bearer ".length).trim()
    : "";

  if (!provided || !safeEqual(provided, expected)) {
    res.status(401).json({
      error: {
        message: "Invalid or missing admin token.",
        code: "unauthorized",
      },
    });
    return;
  }

  next();
};

// GET /contact — instant 200 for deployment health checks. The Replit promote
// probe GETs every path this service claims in artifact.toml (here
// /api/contact); without this handler a GET fell through to the Laravel proxy
// and returned 404/500, which stalled the Promote step until the deploy
// failed. Contact submissions remain POST-only below.
router.get("/contact", (_req, res) => {
  res.status(200).json({ ok: true });
});

router.post("/contact", contactRateLimiter, async (req, res) => {
  const parsed = SubmitContactMessageBody.safeParse(req.body);

  if (!parsed.success) {
    res.status(422).json({
      error: {
        message: "Some fields need attention. Please review and try again.",
        code: "validation_failed",
        details: parsed.error.flatten().fieldErrors,
      },
    });
    return;
  }

  const { name, email, subject, message, website } = parsed.data;

  // Honeypot: real users never see or fill the `website` field. A populated
  // value is almost certainly a bot, so we accept the request (so the bot
  // gets a normal success response) but silently drop it without storing.
  if (website && website.trim() !== "") {
    req.log.info({ email }, "contact: dropped honeypot-flagged submission");
    res.status(201).json({ success: true });
    return;
  }

  try {
    const [row] = await db
      .insert(contactMessagesTable)
      .values({ name, email, subject, message })
      .returning({ id: contactMessagesTable.id });

    req.log.info({ id: row?.id, email }, "contact: stored message");
    res.status(201).json({ success: true });

    // Best-effort team notification. Runs after the response so a slow or
    // failing email never blocks or fails the request — the message is
    // already safely stored above.
    void sendContactNotificationSafely(
      { name, email, subject, message },
      req.log,
    );
  } catch (err) {
    req.log.error({ err }, "contact: failed to store message");
    res.status(500).json({
      error: {
        message: "We couldn't send your message right now. Please try again.",
        code: "delivery_failed",
      },
    });
  }
});

// GET /contact/messages — admin inbox listing (paginated, searchable).
router.get("/contact/messages", requireAdmin, async (req, res) => {
  const parsed = ListContactMessagesQueryParams.safeParse(req.query);

  if (!parsed.success) {
    res.status(422).json({
      error: {
        message: "Invalid query parameters.",
        code: "validation_failed",
        details: parsed.error.flatten().fieldErrors,
      },
    });
    return;
  }

  const { page, perPage, status, search } = parsed.data;

  const filters: SQL[] = [];
  if (status === "new" || status === "read") {
    filters.push(eq(contactMessagesTable.status, status));
  }
  if (search && search.trim() !== "") {
    const term = `%${search.trim()}%`;
    const match = or(
      ilike(contactMessagesTable.name, term),
      ilike(contactMessagesTable.email, term),
      ilike(contactMessagesTable.subject, term),
      ilike(contactMessagesTable.message, term),
    );
    if (match) filters.push(match);
  }

  const where = filters.length > 0 ? and(...filters) : undefined;

  try {
    const [{ total }] = await db
      .select({ total: sql<number>`count(*)::int` })
      .from(contactMessagesTable)
      .where(where);

    const rows = await db
      .select()
      .from(contactMessagesTable)
      .where(where)
      .orderBy(desc(contactMessagesTable.createdAt))
      .limit(perPage)
      .offset((page - 1) * perPage);

    res.json({
      data: rows.map((row) => ({
        id: row.id,
        name: row.name,
        email: row.email,
        subject: row.subject,
        message: row.message,
        status: row.status,
        readAt: row.readAt ? row.readAt.toISOString() : null,
        createdAt: row.createdAt.toISOString(),
      })),
      meta: {
        page,
        perPage,
        total: total ?? 0,
        totalPages: Math.max(1, Math.ceil((total ?? 0) / perPage)),
      },
    });
  } catch (err) {
    req.log.error({ err }, "contact: failed to list messages");
    res.status(500).json({
      error: {
        message: "We couldn't load the inbox right now. Please try again.",
        code: "list_failed",
      },
    });
  }
});

// PATCH /contact/messages/:id — mark a message read/unread (handled).
router.patch("/contact/messages/:id", requireAdmin, async (req, res) => {
  const parsedParams = UpdateContactMessageParams.safeParse(req.params);
  const parsedBody = UpdateContactMessageBody.safeParse(req.body);

  if (!parsedParams.success || !parsedBody.success) {
    res.status(422).json({
      error: {
        message: "Invalid request.",
        code: "validation_failed",
        details: {
          ...(parsedParams.success
            ? {}
            : parsedParams.error.flatten().fieldErrors),
          ...(parsedBody.success
            ? {}
            : parsedBody.error.flatten().fieldErrors),
        },
      },
    });
    return;
  }

  const { id } = parsedParams.data;
  const { status } = parsedBody.data;

  try {
    const [row] = await db
      .update(contactMessagesTable)
      .set({ status, readAt: status === "read" ? new Date() : null })
      .where(eq(contactMessagesTable.id, id))
      .returning();

    if (!row) {
      res.status(404).json({
        error: { message: "Message not found.", code: "not_found" },
      });
      return;
    }

    res.json({
      data: {
        id: row.id,
        name: row.name,
        email: row.email,
        subject: row.subject,
        message: row.message,
        status: row.status,
        readAt: row.readAt ? row.readAt.toISOString() : null,
        createdAt: row.createdAt.toISOString(),
      },
    });
  } catch (err) {
    req.log.error({ err, id }, "contact: failed to update message");
    res.status(500).json({
      error: {
        message: "We couldn't update that message. Please try again.",
        code: "update_failed",
      },
    });
  }
});

export default router;
