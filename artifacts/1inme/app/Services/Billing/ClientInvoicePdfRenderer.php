<?php

namespace App\Services\Billing;

use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Receipt;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a client invoice (and its payment receipt) into a polished,
 * print-ready PDF using dompdf — the same engine the resume + subscription
 * invoice PDFs already use, so no new dependency is introduced.
 *
 * Branding (name, logo, address, tax IDs) is resolved from the invoice's
 * BillingCompany when present, falling back to the stored merchant snapshot
 * and finally the platform merchant config. The tax breakdown and line
 * items come straight from what InvoiceCalculator persisted on the invoice
 * so the PDF always matches the on-screen figures.
 *
 * Generated PDFs are cached for a short window keyed by the invoice's
 * effective version (updated_at + status + paid_at) so repeated downloads
 * of an unchanged document don't pay the render cost again.
 */
class ClientInvoicePdfRenderer
{
    /** Cache TTL for a generated PDF (seconds). */
    private const CACHE_TTL = 600;

    /**
     * @return array{filename:string, body:string}
     */
    public function renderInvoice(Invoice $invoice): array
    {
        $version  = $this->invoiceVersion($invoice);
        $cacheKey = sprintf('client_invoice_pdf:%d:%s', $invoice->id, $version);

        $body = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($invoice) {
            return $this->toPdf($this->invoiceHtml($invoice));
        });

        return [
            'filename' => $this->slugNumber($invoice->number) . '.pdf',
            'body'     => $body,
        ];
    }

    /**
     * Render the invoice print HTML (pre-PDF). Exposed as a hook so the figures
     * laid out by the Blade template — subtotal, discount, per-line totals and
     * grand total — can be asserted directly without parsing the binary PDF,
     * guarding against the stored InvoiceCalculator shape drifting away from
     * what the web/API surfaces show.
     */
    public function invoiceHtml(Invoice $invoice): string
    {
        return view('user.client_invoices.pdf', [
            'invoice' => $invoice,
            'brand'   => $this->resolveBrand($invoice),
        ])->render();
    }

    /**
     * @return array{filename:string, body:string}
     */
    public function renderReceipt(Invoice $invoice, Receipt $receipt): array
    {
        $version  = $this->invoiceVersion($invoice) . ':' . ($receipt->updated_at?->getTimestamp() ?: $receipt->id);
        $cacheKey = sprintf('client_receipt_pdf:%d:%s', $receipt->id, substr(sha1($version), 0, 12));

        $body = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($invoice, $receipt) {
            return $this->toPdf($this->receiptHtml($invoice, $receipt));
        });

        return [
            'filename' => $this->slugNumber($receipt->number) . '.pdf',
            'body'     => $body,
        ];
    }

    /**
     * Render the receipt print HTML (pre-PDF). Companion hook to
     * {@see invoiceHtml()} so the receipt's figures can be asserted without
     * parsing the binary PDF.
     */
    public function receiptHtml(Invoice $invoice, Receipt $receipt): string
    {
        return view('user.client_invoices.receipt-pdf', [
            'invoice' => $invoice,
            'receipt' => $receipt,
            'brand'   => $this->resolveBrand($invoice),
        ])->render();
    }

    private function toPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Normalise the issuing entity into a flat shape the print views use:
     * name, legal_name, logo_data_uri, email, phone, website,
     * address_lines[], tax_ids[]. Prefers the live BillingCompany, then the
     * stored merchant snapshot, then the platform merchant config.
     */
    private function resolveBrand(Invoice $invoice): array
    {
        $company = $invoice->billing_company_id
            ? BillingCompany::find($invoice->billing_company_id)
            : null;

        if ($company) {
            return [
                'name'          => $company->name ?: ($company->legal_name ?: 'Invoice'),
                'legal_name'    => $company->legal_name,
                'logo_data_uri' => $this->logoDataUri($company->logo_path),
                'email'         => $company->email,
                'phone'         => $company->phone,
                'website'       => $company->website,
                'address_lines' => array_values(array_filter([
                    $company->address_line1,
                    $company->address_line2,
                    trim(implode(' ', array_filter([$company->city, $company->state, $company->postal_code]))),
                    $company->country,
                ])),
                'tax_ids'       => array_values(array_filter([
                    $company->tax_id_label && $company->tax_id_value ? "{$company->tax_id_label}: {$company->tax_id_value}" : null,
                    $company->secondary_tax_label && $company->secondary_tax_value ? "{$company->secondary_tax_label}: {$company->secondary_tax_value}" : null,
                ])),
            ];
        }

        $snap = is_array($invoice->merchant_snapshot) ? $invoice->merchant_snapshot : [];
        if (!empty($snap)) {
            $address = $snap['address'] ?? [];
            $taxIds  = $snap['tax_ids'] ?? array_values(array_filter([
                !empty($snap['gstin']) ? 'GSTIN: ' . $snap['gstin'] : null,
                !empty($snap['vatin']) ? 'VAT: ' . $snap['vatin'] : null,
            ]));
            return [
                'name'          => $snap['name'] ?? 'Invoice',
                'legal_name'    => $snap['legal_name'] ?? null,
                'logo_data_uri' => $this->logoDataUri($snap['logo_path'] ?? null),
                'email'         => $snap['email'] ?? ($snap['support_email'] ?? null),
                'phone'         => $snap['phone'] ?? null,
                'website'       => $snap['website'] ?? null,
                'address_lines' => is_array($address) ? array_values(array_filter($address)) : array_filter([$address]),
                'tax_ids'       => is_array($taxIds) ? array_values(array_filter($taxIds)) : [],
            ];
        }

        $merchant = (array) config('billing.merchant', []);
        return [
            'name'          => $merchant['name'] ?? 'Invoice',
            'legal_name'    => null,
            'logo_data_uri' => null,
            'email'         => $merchant['support_email'] ?? null,
            'phone'         => null,
            'website'       => null,
            'address_lines' => array_filter([$merchant['address'] ?? null]),
            'tax_ids'       => array_values(array_filter([
                !empty($merchant['gstin']) ? 'GSTIN: ' . $merchant['gstin'] : null,
                !empty($merchant['vatin']) ? 'VAT: ' . $merchant['vatin'] : null,
            ])),
        ];
    }

    /**
     * Read a logo file off the configured disk and inline it as a data URI.
     * dompdf runs with isRemoteEnabled=false, so embedding bytes is the only
     * safe way to show the logo. Returns null when no logo is set or the
     * bytes can't be read for any reason — the logo is always optional.
     */
    private function logoDataUri(?string $path): ?string
    {
        if (!$path) return null;

        foreach (['public', config('filesystems.default', 'local')] as $diskName) {
            try {
                if (Storage::disk($diskName)->exists($path)) {
                    $bytes = Storage::disk($diskName)->get($path);
                    if (is_string($bytes) && $bytes !== '') {
                        $mime = $this->mimeFor($path);
                        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
                    }
                }
            } catch (\Throwable $e) {
                // try next disk
            }
        }
        return null;
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png'        => 'image/png',
            'gif'        => 'image/gif',
            'svg'        => 'image/svg+xml',
            'webp'       => 'image/webp',
            default      => 'image/jpeg',
        };
    }

    private function invoiceVersion(Invoice $invoice): string
    {
        return substr(sha1(implode('|', [
            $invoice->updated_at?->getTimestamp() ?: 0,
            $invoice->status,
            $invoice->paid_at?->getTimestamp() ?: 0,
            (int) $invoice->grand_total_minor,
        ])), 0, 12);
    }

    private function slugNumber(?string $number): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $number);
        return $slug !== '' ? $slug : 'document';
    }
}
