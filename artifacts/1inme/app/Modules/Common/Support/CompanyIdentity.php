<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Single source of truth for the operating company's legal identity:
 * legal name, registered address, governing-law jurisdiction and the
 * contact mailboxes (general / legal / privacy-DPO) plus the public
 * website URL.
 *
 * Every value is admin-editable through the Company Identity settings
 * screen (stored as individual {@see AppSetting} keys) and falls back to
 * sensible defaults when an admin has not overridden it. The defaults are
 * authored for the Hyderabad, Telangana, India operating jurisdiction.
 *
 * These values flow into the long-form legal policy pages (via {@see
 * self::substitute()} token replacement at render time), the public site
 * footer and the static marketing-site mirror, so the company can keep its
 * legal disclosures consistent and up to date from one place.
 */
class CompanyIdentity
{
    /**
     * The admin-editable fields, each keyed by its AppSetting key. The
     * value is the field metadata used by the settings screen and the
     * fallback default used everywhere the field is read.
     *
     * @return array<string, array{label:string, help:string, type:string, default:string}>
     */
    public static function fields(): array
    {
        $appName = (string) config('app.name', 'Sayzio');

        return [
            'company_legal_name' => [
                'label'   => 'Registered legal name',
                'help'    => 'The full legal name of the company that operates the service, as it appears on registration documents.',
                'type'    => 'text',
                'default' => $appName,
            ],
            'company_registered_address' => [
                'label'   => 'Registered office address',
                'help'    => 'The full postal address of the registered office. Shown in legal policies and the site footer.',
                'type'    => 'textarea',
                'default' => 'Hyderabad, Telangana, India',
            ],
            'company_jurisdiction_city' => [
                'label'   => 'Jurisdiction — city',
                'help'    => 'City whose courts have jurisdiction over disputes (governing law).',
                'type'    => 'text',
                'default' => 'Hyderabad',
            ],
            'company_jurisdiction_state' => [
                'label'   => 'Jurisdiction — state / region',
                'help'    => 'State or region for the governing-law clause.',
                'type'    => 'text',
                'default' => 'Telangana',
            ],
            'company_jurisdiction_country' => [
                'label'   => 'Jurisdiction — country',
                'help'    => 'Country whose laws govern the Terms.',
                'type'    => 'text',
                'default' => 'India',
            ],
            'company_email_general' => [
                'label'   => 'General contact email',
                'help'    => 'Public contact mailbox for general enquiries.',
                'type'    => 'email',
                'default' => '',
            ],
            'company_email_legal' => [
                'label'   => 'Legal contact email',
                'help'    => 'Mailbox for legal notices, terms and dispute correspondence.',
                'type'    => 'email',
                'default' => '',
            ],
            'company_email_privacy' => [
                'label'   => 'Privacy / DPO email',
                'help'    => 'Mailbox for privacy requests and the Data Protection Officer.',
                'type'    => 'email',
                'default' => '',
            ],
            'company_email_grievance' => [
                'label'   => 'Grievance officer email',
                'help'    => 'Mailbox for the Grievance Officer (expected under the India IT Rules / DPDP Act).',
                'type'    => 'email',
                'default' => '',
            ],
            'company_website_url' => [
                'label'   => 'Public website URL',
                'help'    => 'The canonical marketing website URL (used to derive default email domains).',
                'type'    => 'url',
                'default' => '',
            ],
        ];
    }

    /**
     * The bare-string defaults keyed by AppSetting key.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::fields() as $key => $meta) {
            $out[$key] = (string) ($meta['default'] ?? '');
        }
        return $out;
    }

    /**
     * Resolve a single field: the admin override if set, otherwise a
     * sensible fallback. Email fields fall back to a domain-derived
     * mailbox (and the general mailbox additionally falls back to the
     * existing contact-inbox recipient) so published policies never show
     * an empty contact address.
     */
    public static function value(string $key): string
    {
        $defaults = self::defaults();
        $val = trim((string) AppSetting::get($key, ''));
        if ($val !== '') {
            return $val;
        }

        if (in_array($key, ['company_email_general', 'company_email_legal', 'company_email_privacy', 'company_email_grievance'], true)) {
            if ($key === 'company_email_general') {
                $inbox = trim((string) AppSetting::get('contact_recipient_email', ''));
                if ($inbox !== '') {
                    return $inbox;
                }
            }
            $localPart = [
                'company_email_general'   => 'support',
                'company_email_legal'     => 'legal',
                'company_email_privacy'   => 'privacy',
                'company_email_grievance' => 'grievance',
            ][$key];
            return $localPart . '@' . self::emailDomain();
        }

        return $defaults[$key] ?? '';
    }

    /**
     * All resolved field values keyed by AppSetting key.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::fields()) as $key) {
            $out[$key] = self::value($key);
        }
        return $out;
    }

    /**
     * The combined "City, State, Country" jurisdiction string, skipping
     * any empty component.
     */
    public static function jurisdiction(): string
    {
        return implode(', ', array_values(array_filter([
            self::value('company_jurisdiction_city'),
            self::value('company_jurisdiction_state'),
            self::value('company_jurisdiction_country'),
        ], static fn ($v) => trim((string) $v) !== '')));
    }

    /**
     * The token => value map applied to policy bodies/intros at render
     * time. Tokens are written as {{token}} in the seeded policy copy.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        return [
            'company_legal_name'         => self::value('company_legal_name'),
            'company_registered_address' => self::value('company_registered_address'),
            'jurisdiction_city'          => self::value('company_jurisdiction_city'),
            'jurisdiction_state'         => self::value('company_jurisdiction_state'),
            'jurisdiction_country'       => self::value('company_jurisdiction_country'),
            'jurisdiction'               => self::jurisdiction(),
            'company_email_general'      => self::value('company_email_general'),
            'company_email_legal'        => self::value('company_email_legal'),
            'company_email_privacy'      => self::value('company_email_privacy'),
            'company_email_grievance'    => self::value('company_email_grievance'),
            'company_website_url'        => self::value('company_website_url'),
            'app_name'                   => (string) config('app.name', 'Sayzio'),
        ];
    }

    /**
     * Replace every {{token}} in the given text with its resolved value.
     * Applied to policy intros and section bodies before they are rendered.
     */
    public static function substitute(?string $text): string
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }
        foreach (self::tokens() as $token => $value) {
            $text = str_replace('{{' . $token . '}}', $value, $text);
        }
        return $text;
    }

    /**
     * Best-effort bare domain (no scheme, no www) used to build default
     * email addresses when the admin has not set explicit mailboxes.
     */
    private static function emailDomain(): string
    {
        $site = trim((string) AppSetting::get('company_website_url', ''));
        if ($site === '') {
            $site = (string) config('app.url', '');
        }
        $host = is_string($site) ? (parse_url($site, PHP_URL_HOST) ?: '') : '';
        $host = preg_replace('/^www\./i', '', (string) $host);
        $host = trim((string) $host);
        if ($host === '' || $host === 'localhost') {
            return 'sayzio.app';
        }
        return $host;
    }
}
