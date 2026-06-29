<?php

namespace App\Services\Billing;

use App\Modules\User\Models\BillingCompany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

/**
 * Per-billing-company outbound mail transport. Mirrors the platform
 * {@see \App\Services\Integrations\MailSettings} pattern (Crypt-encrypted
 * password at rest, masked for the UI, an SMTP handshake "verify" and a
 * "send test" action) but scoped to one creator's BillingCompany instead of
 * the global app_settings store.
 *
 * When a company has SMTP enabled + a host, {@see emailOpts()} produces the
 * options the central {@see \App\Modules\Common\Services\Emailer} pipeline uses
 * to deliver that company's client-facing accounting emails through its own
 * server. When it is unset/disabled the pipeline keeps using the platform
 * MailSettings transport — so nothing changes for companies that don't opt in,
 * and platform/global emails are never affected.
 */
class CompanyMailSettings
{
    public const ENCRYPTION_OPTIONS = ['tls', 'ssl', 'none'];

    public function __construct(private BillingCompany $company)
    {
    }

    public static function for(BillingCompany $company): self
    {
        return new self($company);
    }

    /**
     * Resolve the sender identity for a workspace's client-facing sends that
     * aren't tied to a single invoice/company (e.g. a client-portal invite):
     * the workspace's default BillingCompany, else its first one. Returns null
     * when the workspace has no companies, so the caller keeps the platform
     * default mailer.
     */
    public static function forWorkspaceDefault(?int $workspaceId): ?self
    {
        if (!$workspaceId) return null;

        $company = BillingCompany::where('workspace_id', $workspaceId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        return $company ? new self($company) : null;
    }

    /** True when this company should send its own client emails via SMTP. */
    public function isConfigured(): bool
    {
        return (bool) $this->company->smtp_enabled
            && is_string($this->company->smtp_host)
            && trim($this->company->smtp_host) !== '';
    }

    /** One of tls|ssl|none (defaults to tls). */
    public function encryption(): string
    {
        $enc = is_string($this->company->smtp_encryption) ? trim($this->company->smtp_encryption) : '';
        return in_array($enc, self::ENCRYPTION_OPTIONS, true) ? $enc : 'tls';
    }

    /** Effective port (falls back to 465 for ssl, otherwise 587). */
    public function port(): int
    {
        $port = (int) ($this->company->smtp_port ?? 0);
        if ($port > 0) return $port;
        return $this->encryption() === 'ssl' ? 465 : 587;
    }

    /** Decrypted SMTP password, or null when none stored / undecryptable. */
    public function password(): ?string
    {
        $enc = $this->company->smtp_password_enc;
        if (!is_string($enc) || $enc === '') return null;
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Set (or clear, with null) the encrypted SMTP password on the model. Does
     * NOT persist — the caller saves the model so dirty-tracking can decide
     * whether to invalidate a prior verification stamp.
     */
    public function setPassword(?string $plain): void
    {
        if ($plain === null || trim($plain) === '') {
            $this->company->smtp_password_enc = null;
            return;
        }
        $this->company->smtp_password_enc = Crypt::encryptString(trim($plain));
    }

    /** Masked password for the UI: ••••••••wXyz. */
    public function maskedPassword(): ?string
    {
        $p = $this->password();
        if (!$p) return null;
        return '••••••••' . substr($p, -4);
    }

    public function hasPassword(): bool
    {
        return $this->password() !== null;
    }

    public function fromAddress(): ?string
    {
        $from = $this->company->smtp_from_address ?: $this->company->email;
        return ($from && trim($from) !== '') ? trim($from) : null;
    }

    public function fromName(): ?string
    {
        $name = $this->company->smtp_from_name ?: $this->company->name;
        return ($name && trim($name) !== '') ? trim($name) : null;
    }

    /** ['address'=>..,'name'=>..] for Emailer's from override, or null. */
    public function fromOpt(): ?array
    {
        $address = $this->fromAddress();
        if ($address === null) return null;
        return ['address' => $address, 'name' => $this->fromName()];
    }

    /** A request-unique mailer name so each company's config can't collide. */
    public function mailerName(): string
    {
        return 'company_smtp_' . ($this->company->id ?? 'new');
    }

    /** Runtime mail.mailers.* config array for this company's SMTP. */
    public function mailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'host'      => trim((string) $this->company->smtp_host),
            'port'      => $this->port(),
            'scheme'    => $this->encryption() === 'ssl' ? 'smtps' : 'smtp',
            'username'  => $this->company->smtp_username ?: null,
            'password'  => $this->password() ?: null,
            'timeout'   => 15,
        ];
    }

    /**
     * Options the Emailer pipeline merges into a send so the message goes out
     * through this company's SMTP (with its own "from") and the log records
     * which transport was used. Empty array when SMTP isn't configured, so the
     * caller falls back to the platform transport.
     *
     * @return array<string,mixed>
     */
    public function emailOpts(): array
    {
        if (!$this->isConfigured()) {
            return ['transport_label' => 'system'];
        }

        $opts = [
            'mailer'          => $this->mailerName(),
            'mailer_config'   => $this->mailerConfig(),
            'transport_label' => 'company:' . $this->company->id,
        ];
        $from = $this->fromOpt();
        if ($from !== null) {
            $opts['from'] = $from;
        }
        return $opts;
    }

    /**
     * Surface a warning when this company has SMTP enabled but recorded state
     * shows its client-facing accounting emails are NOT actually going out
     * through that server — so the creator can fix their settings instead of
     * unknowingly sending platform-branded (or failed) mail.
     *
     * Driven only by recorded state, never guesswork:
     *   1. email_logs — the most recent client invoice/receipt/reminder for
     *      this company. If it went via the platform transport ('system')
     *      instead of this company's ('company:{id}'), or failed on the
     *      company transport, the creator's branded sender wasn't used.
     *      {@see \App\Modules\Common\Services\Emailer} stamps meta.transport.
     *   2. smtp_verified_at — when no conclusive recent send exists, an enabled
     *      but never-verified (or change-invalidated) SMTP means the handshake
     *      has never succeeded and a real send can silently fall back.
     *
     * Returns null when SMTP is off, the company is unsaved, or recorded state
     * proves delivery is working.
     *
     * @return array{level:string,title:string,body:string}|null
     */
    public function deliveryWarning(): ?array
    {
        // Only relevant once the creator has opted into their own SMTP.
        if (!$this->isConfigured() || !$this->company->id) {
            return null;
        }

        $latest    = $this->latestClientEmailLog();
        $transport = $latest && is_array($latest->meta) ? ($latest->meta['transport'] ?? null) : null;
        $status    = $latest?->status;
        $when      = $latest ? (optional($latest->created_at)->diffForHumans() ?: null) : null;

        return $this->evaluateDeliveryWarning(
            $transport,
            $status,
            $when,
            $this->company->smtp_verified_at !== null,
        );
    }

    /**
     * Pure decision core of {@see deliveryWarning()} — given the recorded
     * transport/status of this company's most recent client email (null when
     * there is none) and whether its SMTP handshake has ever been verified,
     * decide which (if any) warning to show. Kept side-effect-free so the
     * branch matrix can be exercised in-memory without a DB.
     *
     * @param  string|null  $transport  email_logs.meta.transport of the latest send
     * @param  string|null  $status     email_logs.status of the latest send
     * @param  string|null  $when       human "x ago" of the latest send
     * @param  bool         $verified   whether smtp_verified_at is set
     * @return array{level:string,title:string,body:string}|null
     */
    public function evaluateDeliveryWarning(?string $transport, ?string $status, ?string $when, bool $verified): ?array
    {
        $expected = 'company:' . ($this->company->id ?? 'new');
        $when     = $when ?: 'recently';

        if ($transport !== null) {
            // Proven working: the latest send went out via this company's SMTP.
            if ($transport === $expected && $status === 'sent') {
                return null;
            }

            if ($transport === $expected && $status === 'failed') {
                return [
                    'level' => 'danger',
                    'title' => 'Your last client email failed to send through your SMTP server.',
                    'body'  => "The most recent client email for this company ({$when}) couldn't be delivered through your mail server. Re-check the host, port and credentials below, then run “Verify connection”.",
                ];
            }

            if ($transport !== $expected) {
                return [
                    'level' => 'warning',
                    'title' => 'Recent client emails did not use your SMTP server.',
                    'body'  => "Your most recent client email for this company ({$when}) went out through the platform mailer, not your own server, so it wasn't sent from your branded address. Re-verify your SMTP settings below.",
                ];
            }
        }

        // No conclusive recent send: enabled but the handshake has never
        // succeeded (never verified, or invalidated by a settings change).
        if (!$verified) {
            return [
                'level' => 'warning',
                'title' => "Your SMTP settings haven't been verified yet.",
                'body'  => "You've enabled your own mail server for this company, but the connection has never been confirmed. Until you verify it, client invoices and receipts may not be delivered from your branded address. Run “Verify connection” below.",
            ];
        }

        return null;
    }

    /**
     * The most recent client-facing accounting email logged for one of this
     * company's invoices (invoice / receipt / payment reminder), or null.
     * related_id is stored as a string by the Emailer, so the invoice ids are
     * cast to match.
     */
    private function latestClientEmailLog(): ?\App\Modules\Common\Models\EmailLog
    {
        $invoiceIds = \App\Modules\User\Models\Invoice::where('billing_company_id', $this->company->id)
            ->orderByDesc('id')
            ->limit(300)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (empty($invoiceIds)) {
            return null;
        }

        return \App\Modules\Common\Models\EmailLog::query()
            ->where('related_type', \App\Modules\User\Models\Invoice::class)
            ->whereIn('related_id', $invoiceIds)
            ->whereIn('email_key', ['billing.client_invoice', 'billing.receipt', 'billing.payment_reminder'])
            ->orderByDesc('id')
            ->first(['id', 'status', 'meta', 'created_at', 'email_key']);
    }

    /**
     * Open an SMTP handshake/auth against this company's transport without
     * sending a message. Stamps smtp_verified_at on success.
     *
     * @return array{ok:bool,error:?string}
     */
    public function verifyConnection(int $timeout = 10): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Enable company SMTP and set a host first.'];
        }

        $name = $this->mailerName();
        config(["mail.mailers.{$name}" => $this->mailerConfig()]);

        try {
            Mail::purge($name);
            $transport = Mail::mailer($name)->getSymfonyTransport();
            if (!$transport instanceof SmtpTransport) {
                return ['ok' => false, 'error' => 'The configured transport does not support a connection check.'];
            }
            $transport->getStream()->setTimeout((float) $timeout);
            $transport->start();
            $transport->stop();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $this->company->forceFill(['smtp_verified_at' => now()])->save();

        return ['ok' => true, 'error' => null];
    }

    /**
     * Send a plain-text client-facing message through this company's SMTP when
     * configured, otherwise through the platform default mailer. Used for
     * client-facing accounting sends that are NOT registry templates and so
     * don't flow through the {@see \App\Modules\Common\Services\Emailer}
     * pipeline (e.g. the client-portal magic-link invite). Returns the
     * transport label actually used ('company:{id}' or 'system') so callers can
     * log it consistently with the Emailer pipeline.
     */
    public function sendRaw(string $to, string $subject, string $body): string
    {
        $from = $this->fromOpt();

        if (!$this->isConfigured()) {
            Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
            return 'system';
        }

        $name = $this->mailerName();
        config(["mail.mailers.{$name}" => $this->mailerConfig()]);
        Mail::purge($name);
        Mail::mailer($name)->raw($body, function ($m) use ($to, $subject, $from) {
            $m->to($to)->subject($subject);
            if ($from !== null) {
                $m->from($from['address'], $from['name'] ?? null);
            }
        });

        return 'company:' . $this->company->id;
    }

    /**
     * Send a sample message through this company's SMTP and report the result.
     *
     * @return array{ok:bool,error:?string}
     */
    public function sendTest(string $to): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Enable company SMTP and set a host first.'];
        }

        $name = $this->mailerName();
        config(["mail.mailers.{$name}" => $this->mailerConfig()]);

        try {
            Mail::purge($name);
            $from    = $this->fromOpt();
            $company = $this->company->name;
            Mail::mailer($name)->raw(
                "This is a test message confirming the SMTP settings for \"{$company}\" are working.\n\n"
                . 'Your client-facing invoices and receipts will be delivered from this server.',
                function ($m) use ($to, $from, $company) {
                    $m->to($to)->subject("SMTP test — {$company}");
                    if ($from !== null) {
                        $m->from($from['address'], $from['name'] ?? null);
                    }
                }
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'error' => null];
    }
}
