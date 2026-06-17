import { Router, type IRouter } from "express";
import { SubmitContactMessageBody } from "@workspace/api-zod";
import { db, contactMessagesTable } from "@workspace/db";

const router: IRouter = Router();

router.post("/contact", async (req, res) => {
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

export default router;
