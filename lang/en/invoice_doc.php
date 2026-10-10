<?php

/*
|--------------------------------------------------------------------------
| Quotation / proforma / invoice / credit note wording
|--------------------------------------------------------------------------
|
| Every label printed on the PDF and in the email of a hand-written document,
| so each document can be issued in the client's language. The other locales
| are machine-translated: have a native speaker check them before you rely on
| them for anything official. The legal VAT notices are NOT here — they stay in
| English unless you type your own wording in the invoice's "Legal wording" box.
*/

return [
    // titles
    'title_quote' => 'QUOTATION',
    'title_proforma' => 'PROFORMA INVOICE',
    'title_invoice' => 'INVOICE',
    'title_credit_note' => 'CREDIT NOTE',
    'name_quote' => 'Quotation',
    'name_proforma' => 'Proforma invoice',
    'name_invoice' => 'Invoice',
    'name_credit_note' => 'Credit note',

    // header block
    'number' => 'No.',
    'date' => 'Date',
    'supply_date' => 'Supply date',
    'valid_until' => 'Valid until',
    'pay_before' => 'Pay before',
    'due' => 'Due',
    'your_ref' => 'Your ref. / PO',
    'delivery' => 'Delivery',
    'credit_for' => 'Credit for',
    'ref' => 'Ref.',
    'bill_to' => 'Bill To',
    'prepared_for' => 'Prepared For',
    'email' => 'Email',
    'phone' => 'Phone',
    'vat_id' => 'VAT',
    'reg_no' => 'Reg. No',
    'seller' => 'Seller',
    'bill_ship_to' => 'Bill & ship to',
    'ship_to' => 'Ship To',

    // table
    'items' => 'Items',
    'part_no' => 'Part no.',
    'description' => 'Description',
    'availability' => 'Availability',
    'quantity' => 'Quantity',
    'unit_price' => 'Unit Price',
    'discount_short' => 'Disc.',
    'vat_short' => 'VAT',
    'total_col' => 'Total',

    // totals
    'subtotal' => 'Subtotal',
    'discount' => 'Discount',
    'vat' => 'VAT',
    'vat_of' => 'VAT (:rate% of :base)',
    'vat_rate' => 'VAT (:rate%)',
    'total' => 'Total',
    'reverse_charge' => 'Reverse charge',
    'buyer_vat_id' => 'Buyer VAT ID',
    'terms' => 'Terms & Conditions',

    // disclaimers
    'disclaimer_quote' => 'This is a quotation, not an invoice. Prices are valid until the date shown.',
    'disclaimer_proforma' => 'This is a proforma invoice, not a tax invoice. A tax invoice will be issued when payment is received.',

    // payment block
    'payment_bank' => 'Payment Details · Bank Transfer',
    'payment_online' => 'Payment Details · Pay Online',
    'payment_cash' => 'Payment Details · Cash',
    'account_holder' => 'Account holder',
    'bank' => 'Bank',
    'iban' => 'IBAN',
    'swift' => 'SWIFT / BIC',
    'intermediary' => 'Intermediary bank',
    'payment_reference' => 'Payment reference',
    'quote_reference' => 'Please quote the payment reference exactly so your transfer can be matched to this invoice. Bank charges are borne by the payer.',
    'pay_online_text' => 'Pay securely online with a card or bank at:',
    'pay_online_ref' => 'Please quote invoice :number if the page asks for a reference.',
    'cash_text' => 'Payable in cash on delivery or pickup. Please quote invoice :number.',

    // footer
    'questions' => 'If you have any questions about this document, please contact :email',
    'generated_on' => 'Generated on',

    // email
    'mail_subject' => ':type :number from :company',
    'mail_eyebrow' => 'FINANCE · :type',
    'mail_hello' => 'Hello :name,',
    'mail_attached' => 'Please find your :type attached as a PDF. A summary is below.',
    'mail_no' => ':type NO.',
    'mail_amount_due' => 'AMOUNT DUE',
    'mail_credit_amount' => 'CREDIT AMOUNT',
    'mail_total' => 'TOTAL',
    'mail_pay_bank' => 'PAY BY BANK TRANSFER',
    'mail_pay_online' => 'PAY ONLINE',
    'mail_questions' => 'Questions about this document? Just reply to this email or contact :email.',

    // payment reminder email
    'reminder_subject' => 'Payment reminder: invoice :number from :company',
    'reminder_eyebrow' => 'FINANCE · PAYMENT REMINDER',
    'reminder_intro' => 'This is a friendly reminder that the invoice below is still open',
    'reminder_late' => '(it was due :count day ago)|(it was due :count days ago)',
    'reminder_ignore' => 'If you have already paid it, please ignore this message and accept our thanks.',
    'reminder_outstanding' => 'OUTSTANDING',
    'reminder_attached_again' => 'The invoice is attached again as a PDF. Questions? Just reply to this email or contact :email.',
    'send_copy_note' => 'Copy',
    'order_ref' => 'Order',
    'condition' => 'Condition',
    'shipping' => 'Shipping',
    'rush_processing' => 'Rush Processing',
    'handling_fee' => 'Handling Fee',
    'reverse_charge_text' => 'VAT to be accounted for by the recipient under Article 194/196 of Council Directive 2006/112/EC.',
    'zero_rated_title' => 'Zero-rated export',
    'zero_rated_text' => 'supply to a destination outside the EU, exempt from VAT under Article 146 of Council Directive 2006/112/EC.',
    'oversized_title' => 'Oversized parts — shipping notice.',
    'oversized_text' => 'The shipping cost above is a fixed rate for standard-size parcels. If this order includes an oversized or heavy part, the carrier may apply an additional freight surcharge, which will be invoiced separately after dispatch.',
];
