<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    /** Stream the tax invoice PDF for an invoice owned by the current user. */
    public function pdf(Invoice $invoice): Response
    {
        $user = Auth::user();
        abort_unless(
            $user && (
                $user->id === $invoice->user_id
                || (method_exists($user, 'hasPermission') && $user->hasPermission('user.invoices.view_any'))
            ),
            403
        );

        $pdf = InvoiceService::renderPdf($invoice);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $invoice->number) . '.pdf';
        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
