import { serial, varchar, text, timestamp } from "drizzle-orm/pg-core";
import { drizzleSchema } from "./drizzle-schema";

export const contactMessagesTable = drizzleSchema.table("contact_messages", {
  id: serial("id").primaryKey(),
  name: varchar("name", { length: 120 }).notNull(),
  email: varchar("email", { length: 255 }).notNull(),
  subject: varchar("subject", { length: 200 }).notNull(),
  message: text("message").notNull(),
  // Inbox status: "new" until an admin marks it "read"/handled.
  status: varchar("status", { length: 16 }).notNull().default("new"),
  // Stamped when the message is first marked read; cleared if marked unread.
  readAt: timestamp("read_at", { withTimezone: true }),
  createdAt: timestamp("created_at", { withTimezone: true })
    .defaultNow()
    .notNull(),
});

export type ContactMessage = typeof contactMessagesTable.$inferSelect;
export type InsertContactMessage = typeof contactMessagesTable.$inferInsert;
