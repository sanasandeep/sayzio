<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A per-user "billing company" — the legal entity that issues an
 * invoice/receipt or records an expense. Distinct from the platform's
 * own config-managed merchant (see config/billing.php). Users may keep
 * several and choose which one issues a given document.
 */
class BillingCompany extends Model
{
    protected $fillable = [
        'user_id', 'workspace_id', 'name', 'legal_name', 'logo_path',
        'email', 'phone', 'website',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
        'tax_id_label', 'tax_id_value', 'secondary_tax_label', 'secondary_tax_value',
        'default_currency', 'invoice_prefix', 'default_tax_rule_id', 'notes', 'is_default',
        // Per-company outbound mail (the encrypted password + verified stamp are
        // never mass-assigned — see CompanyMailSettings / the controller).
        'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption',
        'smtp_username', 'smtp_from_address', 'smtp_from_name',
        // Default letterhead (PDF background), overridable per invoice (Task #3522):
        'letterhead_path', 'letterhead_orientation',
        'letterhead_margin_top', 'letterhead_margin_right',
        'letterhead_margin_bottom', 'letterhead_margin_left',
        'letterhead_width', 'letterhead_height',
    ];

    protected $hidden = [
        'smtp_password_enc',
    ];

    protected function casts(): array
    {
        return [
            'is_default'          => 'boolean',
            'default_tax_rule_id' => 'integer',
            'smtp_enabled'        => 'boolean',
            'smtp_port'           => 'integer',
            'smtp_verified_at'    => 'datetime',
            'letterhead_margin_top'    => 'integer',
            'letterhead_margin_right'  => 'integer',
            'letterhead_margin_bottom' => 'integer',
            'letterhead_margin_left'   => 'integer',
            'letterhead_width'         => 'integer',
            'letterhead_height'        => 'integer',
        ];
    }

    public function emailTemplates()
    {
        return $this->hasMany(CompanyEmailTemplate::class, 'billing_company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function defaultTaxRule()
    {
        return $this->belongsTo(TaxRule::class, 'default_tax_rule_id');
    }

    public function taxRules()
    {
        return $this->hasMany(TaxRule::class, 'billing_company_id');
    }

    /**
     * The addresses a SMTP test message may be sent to. Restricting the test
     * send to addresses the creator already controls (their own account email,
     * this company's contact email, and its configured sender address) stops
     * the feature being abused as an open relay for unsolicited mail to
     * arbitrary third parties. All entries are lowercased + de-duplicated.
     *
     * @return array<int,string>
     */
    public function allowedTestRecipients(?User $user = null): array
    {
        $user = $user ?: $this->user;

        $candidates = [
            $user?->email,
            $this->email,
            $this->smtp_from_address,
        ];

        $allowed = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') continue;
            $allowed[strtolower($candidate)] = strtolower($candidate);
        }

        return array_values($allowed);
    }

    /** True when $email is one of this company's permitted test-send recipients. */
    public function allowsTestRecipient(string $email, ?User $user = null): bool
    {
        return in_array(strtolower(trim($email)), $this->allowedTestRecipients($user), true);
    }

    /** Flat snapshot stored on invoices/receipts so later edits never mutate history. */
    public function toSnapshot(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'legal_name'=> $this->legal_name,
            'logo_path' => $this->logo_path,
            'email'     => $this->email,
            'phone'     => $this->phone,
            'website'   => $this->website,
            'address'   => array_filter([
                $this->address_line1, $this->address_line2,
                trim(implode(' ', array_filter([$this->city, $this->state, $this->postal_code]))),
                $this->country,
            ]),
            'tax_ids'   => array_values(array_filter([
                $this->tax_id_label && $this->tax_id_value ? "{$this->tax_id_label}: {$this->tax_id_value}" : null,
                $this->secondary_tax_label && $this->secondary_tax_value ? "{$this->secondary_tax_label}: {$this->secondary_tax_value}" : null,
            ])),
            'currency'  => $this->default_currency,
        ];
    }

    /** True when this company has a default letterhead configured. */
    public function hasLetterhead(): bool
    {
        return (bool) $this->letterhead_path;
    }
}
