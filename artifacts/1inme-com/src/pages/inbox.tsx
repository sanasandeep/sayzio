import { useMemo, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import {
  useListContactMessages,
  useUpdateContactMessage,
  getListContactMessagesQueryKey,
  type ContactMessage,
  type ListContactMessagesParams,
} from "@workspace/api-client-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Inbox as InboxIcon,
  Search,
  Loader2,
  AlertCircle,
  Mail,
  Check,
  RotateCcw,
  LogOut,
  ChevronLeft,
  ChevronRight,
  Lock,
} from "lucide-react";

const TOKEN_KEY = "1inme_admin_token";
const PER_PAGE = 20;

type StatusFilter = "all" | "new" | "read";

function formatDate(value: string): string {
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleString(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  });
}

function TokenGate({ onSubmit }: { onSubmit: (token: string) => void }) {
  const [value, setValue] = useState("");

  return (
    <div className="min-h-screen flex items-center justify-center px-6">
      <form
        onSubmit={(e) => {
          e.preventDefault();
          if (value.trim()) onSubmit(value.trim());
        }}
        className="glass-card w-full max-w-md p-8 rounded-3xl space-y-6"
      >
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
            <Lock className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-xl font-bold">Contact inbox</h1>
            <p className="text-sm text-muted-foreground">
              Enter the admin token to continue.
            </p>
          </div>
        </div>
        <div className="space-y-2">
          <Label htmlFor="token">Admin token</Label>
          <Input
            id="token"
            type="password"
            autoFocus
            value={value}
            onChange={(e) => setValue(e.target.value)}
            placeholder="Paste your admin token"
          />
        </div>
        <Button type="submit" className="w-full rounded-full h-11">
          Unlock inbox
        </Button>
      </form>
    </div>
  );
}

function MessageRow({
  message,
  token,
  onUnauthorized,
}: {
  message: ContactMessage;
  token: string;
  onUnauthorized: () => void;
}) {
  const queryClient = useQueryClient();
  const [expanded, setExpanded] = useState(false);

  const mutation = useUpdateContactMessage({
    request: { headers: { Authorization: `Bearer ${token}` } },
    mutation: {
      onSuccess: () => {
        queryClient.invalidateQueries({
          queryKey: ["/api/contact/messages"],
        });
      },
      onError: (err) => {
        if (err.status === 401) onUnauthorized();
      },
    },
  });

  const isRead = message.status === "read";
  const nextStatus = isRead ? "new" : "read";

  return (
    <div
      className={`rounded-2xl border p-4 transition-colors ${
        isRead ? "bg-card/20 border-border/60" : "bg-card/40 border-primary/30"
      }`}
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            {!isRead && (
              <span className="w-2 h-2 rounded-full bg-primary shrink-0" />
            )}
            <h3 className="font-semibold truncate">{message.subject}</h3>
          </div>
          <p className="text-sm text-muted-foreground mt-1">
            <span className="font-medium text-foreground">{message.name}</span>{" "}
            <a
              href={`mailto:${message.email}`}
              className="hover:text-primary transition-colors"
            >
              &lt;{message.email}&gt;
            </a>
          </p>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          <span className="text-xs text-muted-foreground whitespace-nowrap">
            {formatDate(message.createdAt)}
          </span>
        </div>
      </div>

      <p
        className={`mt-3 text-sm text-muted-foreground whitespace-pre-wrap ${
          expanded ? "" : "line-clamp-2"
        }`}
      >
        {message.message}
      </p>

      <div className="mt-3 flex flex-wrap items-center gap-2">
        {message.message.length > 140 && (
          <Button
            type="button"
            size="sm"
            variant="ghost"
            className="rounded-full h-8 px-3 text-xs"
            onClick={() => setExpanded((v) => !v)}
          >
            {expanded ? "Show less" : "Show more"}
          </Button>
        )}
        <Button
          type="button"
          size="sm"
          variant="outline"
          className="rounded-full h-8 px-3 text-xs"
          disabled={mutation.isPending}
          onClick={() => mutation.mutate({ id: message.id, data: { status: nextStatus } })}
        >
          {mutation.isPending ? (
            <Loader2 className="w-3.5 h-3.5 animate-spin" />
          ) : isRead ? (
            <RotateCcw className="w-3.5 h-3.5" />
          ) : (
            <Check className="w-3.5 h-3.5" />
          )}
          {isRead ? "Mark unread" : "Mark read"}
        </Button>
        <Button
          asChild
          size="sm"
          variant="ghost"
          className="rounded-full h-8 px-3 text-xs"
        >
          <a href={`mailto:${message.email}?subject=Re: ${encodeURIComponent(message.subject)}`}>
            <Mail className="w-3.5 h-3.5" />
            Reply
          </a>
        </Button>
      </div>
    </div>
  );
}

export default function Inbox() {
  const [token, setToken] = useState<string | null>(() =>
    typeof window === "undefined" ? null : localStorage.getItem(TOKEN_KEY),
  );
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");

  const saveToken = (value: string) => {
    localStorage.setItem(TOKEN_KEY, value);
    setToken(value);
  };
  const clearToken = () => {
    localStorage.removeItem(TOKEN_KEY);
    setToken(null);
  };

  const params = useMemo<ListContactMessagesParams>(
    () => ({
      page,
      perPage: PER_PAGE,
      status: statusFilter,
      ...(search ? { search } : {}),
    }),
    [page, statusFilter, search],
  );

  const query = useListContactMessages(params, {
    request: token
      ? { headers: { Authorization: `Bearer ${token}` } }
      : undefined,
    query: {
      enabled: Boolean(token),
      queryKey: getListContactMessagesQueryKey(params),
    },
  });

  if (!token) {
    return <TokenGate onSubmit={saveToken} />;
  }

  if (query.error && query.error.status === 401) {
    // Token went stale — drop it and show the gate again.
    clearToken();
    return <TokenGate onSubmit={saveToken} />;
  }

  const list = query.data;
  const meta = list?.meta;
  const totalPages = meta?.totalPages ?? 1;

  const applySearch = () => {
    setPage(1);
    setSearch(searchInput.trim());
  };

  const changeStatus = (value: StatusFilter) => {
    setPage(1);
    setStatusFilter(value);
  };

  return (
    <div className="min-h-screen">
      <div className="container mx-auto px-6 py-12 max-w-4xl">
        <div className="flex items-center justify-between gap-4 mb-8">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
              <InboxIcon className="w-6 h-6" />
            </div>
            <div>
              <h1 className="text-2xl font-bold">Contact inbox</h1>
              <p className="text-sm text-muted-foreground">
                {meta ? `${meta.total} message${meta.total === 1 ? "" : "s"}` : "Messages from the contact form"}
              </p>
            </div>
          </div>
          <Button
            variant="ghost"
            className="rounded-full"
            onClick={clearToken}
          >
            <LogOut className="w-4 h-4" />
            Sign out
          </Button>
        </div>

        <div className="flex flex-wrap items-center gap-3 mb-6">
          <form
            onSubmit={(e) => {
              e.preventDefault();
              applySearch();
            }}
            className="flex items-center gap-2 flex-1 min-w-[240px]"
          >
            <div className="relative flex-1">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="Search name, email, subject, message..."
                className="pl-9"
              />
            </div>
            <Button type="submit" variant="outline" className="rounded-full">
              Search
            </Button>
          </form>
          <div className="flex items-center gap-1 rounded-full border p-1">
            {(["all", "new", "read"] as StatusFilter[]).map((s) => (
              <button
                key={s}
                type="button"
                onClick={() => changeStatus(s)}
                className={`px-3 py-1.5 rounded-full text-sm capitalize transition-colors ${
                  statusFilter === s
                    ? "bg-primary text-primary-foreground"
                    : "text-muted-foreground hover:text-foreground"
                }`}
              >
                {s === "new" ? "Unread" : s}
              </button>
            ))}
          </div>
        </div>

        {query.isLoading ? (
          <div className="flex items-center justify-center py-24 text-muted-foreground">
            <Loader2 className="w-6 h-6 animate-spin" />
          </div>
        ) : query.error ? (
          <div className="flex items-start gap-3 rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
            <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
            <span>
              {query.error.status === 503
                ? "The inbox isn't configured on the server yet."
                : "We couldn't load the inbox. Please try again."}
            </span>
          </div>
        ) : !list || list.data.length === 0 ? (
          <div className="text-center py-24">
            <div className="w-14 h-14 rounded-2xl bg-muted/40 text-muted-foreground flex items-center justify-center mx-auto mb-4">
              <InboxIcon className="w-7 h-7" />
            </div>
            <p className="text-muted-foreground">
              {search || statusFilter !== "all"
                ? "No messages match your filters."
                : "No messages yet."}
            </p>
          </div>
        ) : (
          <div className="space-y-3">
            {list.data.map((message) => (
              <MessageRow
                key={message.id}
                message={message}
                token={token}
                onUnauthorized={clearToken}
              />
            ))}
          </div>
        )}

        {meta && totalPages > 1 && (
          <div className="flex items-center justify-between mt-8">
            <Button
              variant="outline"
              className="rounded-full"
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              <ChevronLeft className="w-4 h-4" />
              Previous
            </Button>
            <span className="text-sm text-muted-foreground">
              Page {meta.page} of {totalPages}
            </span>
            <Button
              variant="outline"
              className="rounded-full"
              disabled={page >= totalPages}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
              <ChevronRight className="w-4 h-4" />
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
