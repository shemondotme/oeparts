<?php

namespace App\Filament\Resources\CustomInvoiceResource\Pages;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoiceDocumentType;
use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\CustomInvoiceResource;
use App\Models\InvoiceClient;
use App\Models\PartInquiry;
use App\Services\CustomInvoiceService;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomInvoice extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CustomInvoiceResource::class;

    protected ?string $heading = 'New quotation, proforma or invoice';

    protected ?string $subheading = 'Write a document for a client outside the storefront checkout. It is saved as a draft; email it to the client from the list.';

    /** Arriving from a client's "New invoice" button pre-fills that client. */
    public function mount(): void
    {
        parent::mount();

        $client = InvoiceClient::find((int) request()->query('client'));
        if ($client) {
            $this->form->fill(array_merge($this->data ?? [], $client->toInvoiceFields(), array_filter(['currency' => $client->currency])));
        }

        // Arriving from a Part Inquiry's "Create quotation" button pre-fills the contact and the part.
        $inquiry = PartInquiry::find((int) request()->query('inquiry'));
        if ($inquiry) {
            $this->form->fill(array_merge($this->data ?? [], static::inquiryFields($inquiry)));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function inquiryFields(PartInquiry $inquiry): array
    {
        $vehicle = trim(implode(' ', array_filter([$inquiry->manufacturer, $inquiry->car_model, $inquiry->year])));

        return [
            'client_name' => $inquiry->email ?: 'Customer',
            'client_email' => $inquiry->email,
            'client_phone' => $inquiry->phone,
            'internal_notes' => trim("From part inquiry #{$inquiry->id}".($inquiry->vin_number ? " · VIN {$inquiry->vin_number}" : '').($inquiry->notes ? "
{$inquiry->notes}" : '')),
            'items' => [[
                'description' => 'OEM '.$inquiry->oem_number.($vehicle !== '' ? " — {$vehicle}" : ''),
                'part_number' => $inquiry->oem_number,
                'quantity' => max(1, (int) $inquiry->quantity),
            ]],
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // "Save as a client" is a form-only switch: keep the details and link the invoice to them.
        if (($data['save_client'] ?? false) && blank($data['client_id'] ?? null)) {
            $client = InvoiceClient::fromInvoiceFields($data);
            $client->save();
            $data['client_id'] = $client->id;
        }
        unset($data['save_client']);

        // Number, status and author are never form inputs. The number is drawn
        // from the shared invoice sequence at creation so it is stable from the
        // first save (and cancelled invoices keep theirs).
        $type = InvoiceDocumentType::tryFrom((string) ($data['document_type'] ?? '')) ?? InvoiceDocumentType::Invoice;
        $data['document_type'] = $type->value;
        $data['invoice_number'] = app(CustomInvoiceService::class)->nextNumber($type);
        $data['status'] = CustomInvoiceStatus::Draft;
        $data['created_by'] = auth('admin')->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return CustomInvoiceResource::getUrl('index');
    }
}
