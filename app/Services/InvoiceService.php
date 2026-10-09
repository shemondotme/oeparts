<?php

namespace App\Services;

use App\Models\InvoiceBankAccount;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Generate PDF invoice for an order.
     *
     * @param  bool  $download  Whether to return download response or PDF content
     * @param  bool  $skipAuthorization  Whether to bypass user authorization check (useful for system jobs)
     */
    public function generate(Order $order, bool $download = false, bool $skipAuthorization = false): \Barryvdh\DomPDF\PDF|Response
    {
        if (! $skipAuthorization) {
            $this->authorize($order);
        }

        try {
            $order->loadMissing(['items.product']);

            $shippingAddress = $this->addressFromOrderSnapshot($order, 'shipping');
            // A separate billing address is optional; without one the invoice is
            // billed to the delivery address, exactly as before.
            $billingAddress = $order->billing_address_line1
                ? $this->addressFromOrderSnapshot($order, 'billing')
                : $shippingAddress;

            $data = [
                'order' => $order,
                'user' => $order->user,
                'items' => $order->items,
                'billingAddress' => $billingAddress,
                'shippingAddress' => $shippingAddress,
                'bank' => $this->bankDetails(settings('general.currency', 'EUR')),
                'settings' => [
                    'company_name' => settings('company.name', 'OeParts'),
                    'company_address' => settings('company.address', ''),
                    'company_vat' => settings('company.vat_number', ''),
                    'company_registration' => settings('company.registration_number', ''),
                    'company_email' => settings('company.email', 'info@oeparts.lt'),
                    'company_phone' => settings('company.phone', ''),
                ],
            ];

            $pdf = Pdf::loadView('pdf.invoice', $data);

            if ($download) {
                return $pdf->download("invoice-{$order->order_number}.pdf");
            }

            return $pdf;
        } catch (\Exception $e) {
            Log::error('Invoice generation failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * The store's bank-transfer details, used for order invoices and the storefront
     * checkout. The source of truth is Sales → Bank Accounts: the active account set
     * up for $currency if there is one, otherwise the first active account (lowest
     * sort order). The single account in Settings is only a fallback for installs that
     * have not created any account yet. Null when no IBAN is known anywhere, so the
     * block is omitted rather than printed empty.
     *
     * @return array{bank_name: string, iban: string, bic: string, account_holder: string, intermediary_bank: string, instructions: string}|null
     */
    public function bankDetails(?string $currency = null): ?array
    {
        $accounts = InvoiceBankAccount::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id');

        $account = (filled($currency) ? (clone $accounts)->where('currency', strtoupper((string) $currency))->first() : null)
            ?? $accounts->first();

        if ($account) {
            return $this->accountDetails($account);
        }

        return $this->legacySettingsBank();
    }

    /**
     * The one account that used to live in Settings → Store Operations. Kept only as a
     * fallback (and for the one-time copy into Bank Accounts).
     *
     * @return array{bank_name: string, iban: string, bic: string, account_holder: string, intermediary_bank: string, instructions: string}|null
     */
    private function legacySettingsBank(): ?array
    {
        $iban = trim((string) settings('payment.bank_iban', ''));

        if ($iban === '') {
            return null;
        }

        return [
            'bank_name' => trim((string) settings('payment.bank_name', '')),
            'iban' => $iban,
            'bic' => trim((string) settings('payment.bank_bic', '')),
            'account_holder' => trim((string) settings('payment.bank_account_holder', '')) ?: (string) settings('company.name', ''),
            'intermediary_bank' => '',
            'instructions' => '',
        ];
    }

    /**
     * The bank account an invoice should print. An explicitly chosen account wins
     * (even if it has since been deactivated, so an old invoice keeps what it was
     * issued with); otherwise the active account set up for the invoice currency;
     * otherwise the store's default account.
     *
     * @return array{bank_name: string, iban: string, bic: string, account_holder: string, intermediary_bank: string, instructions: string}|null
     */
    public function bankDetailsFor(?int $bankAccountId, ?string $currency): ?array
    {
        if ($bankAccountId && ($account = InvoiceBankAccount::find($bankAccountId))) {
            return $this->accountDetails($account);
        }

        return $this->bankDetails($currency);
    }

    /** @return array{bank_name: string, iban: string, bic: string, account_holder: string, intermediary_bank: string, instructions: string} */
    private function accountDetails(InvoiceBankAccount $account): array
    {
        return [
            'bank_name' => (string) $account->bank_name,
            'iban' => $account->formattedIban(),
            'bic' => (string) $account->bic,
            'account_holder' => (string) $account->account_holder,
            'intermediary_bank' => (string) $account->intermediary_bank,
            'instructions' => (string) $account->instructions,
        ];
    }

    /**
     * Build a bill/ship address object from order shipping snapshot (no saved Address rows).
     */
    private function addressFromOrderSnapshot(Order $order, string $kind = 'shipping'): object
    {
        $billing = $kind === 'billing';

        // No case-normalization: this name is printed verbatim on the invoice
        // PDF, so lowercasing it (as this used to do) turned "John Doe" into
        // "john doe" on an official billing document.
        $name = trim((string) ($billing ? ($order->billing_name ?: $order->shipping_name) : $order->shipping_name));
        $parts = $name === '' ? ['', ''] : preg_split('/\s+/u', $name, 2);

        return (object) [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
            'company' => $order->company_name,
            'address_line_1' => (string) ($billing ? $order->billing_address_line1 : $order->shipping_address_line1),
            'address_line_2' => ($billing ? $order->billing_address_line2 : $order->shipping_address_line2) ?: null,
            'city' => (string) ($billing ? $order->billing_city : $order->shipping_city),
            'state' => (string) ($billing ? $order->billing_state : $order->shipping_state),
            'postal_code' => (string) ($billing ? $order->billing_postal_code : $order->shipping_postal_code),
            'country_code' => (string) ($billing ? $order->billing_country_code : $order->shipping_country_code),
            'phone' => $order->customer_phone ?: null,
        ];
    }

    /**
     * Download an invoice, serving a cached PDF when one already exists
     * instead of always re-rendering. Previously every download (customer
     * account page AND admin) called generate($order, true) directly, live-
     * rendering on every single request — the GenerateInvoicePdf job's
     * saveToStorage() output (dispatched at checkout, and from the admin
     * "Print Invoice"/"Generate Invoice PDF" actions) was written but never
     * read back by anything, making that whole pipeline pure waste.
     */
    public function download(Order $order): Response
    {
        $this->authorize($order);

        $filename = "invoices/{$order->order_number}.pdf";

        if (! Storage::disk('local')->exists($filename)) {
            $this->saveToStorage($order);
        }

        return response(Storage::disk('local')->get($filename), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.$order->order_number.'.pdf"',
        ]);
    }

    /**
     * Save invoice to storage and return path.
     */
    public function saveToStorage(Order $order): string
    {
        $pdf = $this->generate($order, false, true);
        $filename = "invoices/{$order->order_number}.pdf";

        $content = $pdf->output();
        if (strlen($content) > 10 * 1024 * 1024) {
            throw new \RuntimeException('PDF too large');
        }

        try {
            Storage::disk('local')->put($filename, $content);
        } catch (\Exception $e) {
            Log::error('Failed to save invoice to storage', [
                'order_id' => $order->id,
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $filename;
    }

    /**
     * Check if invoice exists in storage.
     */
    public function exists(Order $order): bool
    {
        $filename = "invoices/{$order->order_number}.pdf";

        return Storage::disk('local')->exists($filename);
    }

    /**
     * Get invoice from storage.
     */
    public function getFromStorage(Order $order): ?string
    {
        $this->authorize($order);

        $filename = "invoices/{$order->order_number}.pdf";

        if (! Storage::disk('local')->exists($filename)) {
            Log::error('Invoice file not found in storage', [
                'order_id' => $order->id,
                'filename' => $filename,
            ]);

            return null;
        }

        return Storage::disk('local')->get($filename);
    }

    /**
     * Verify the order belongs to the requesting user or user is admin.
     */
    private function authorize(Order $order): void
    {
        $admin = Auth::guard('admin')->user();
        if ($admin) {
            return;
        }

        $user = Auth::guard('web')->user();
        if ($user && $order->user_id === $user->id) {
            return;
        }

        abort(403, 'Unauthorized access to invoice.');
    }
}
