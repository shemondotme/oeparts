<?php

namespace App\Services\Migration\Export\Transform;

/**
 * What a scrambled (staging) copy does to each personal column, and, just as important, what it does NOT do.
 *
 * Two rules decide everything. Columns that name a person or a place are replaced by a stand-in that is the
 * same for the same input (so records still line up). Columns that hold free text or JSON are blanked whole,
 * never searched for names inside, because text cannot be shown clean from its column name.
 *
 * `notScrubbed()` is the other half: the plain list the wizard and the package report show, and the list the
 * audit test accepts as the only places a canary may survive. The two lists are tested against each other.
 */
final class PiiMap
{
    public const EMAIL = 'email';

    public const NAME = 'name';

    public const FIRST_NAME = 'first_name';

    public const LAST_NAME = 'last_name';

    public const COMPANY = 'company';

    public const STREET = 'street';

    public const CITY = 'city';

    public const POSTAL = 'postal';

    public const PHONE = 'phone';

    public const IP = 'ip';

    public const VIN = 'vin';

    public const CODE = 'code';

    /** Free text and JSON: the whole value goes. */
    public const BLANK = 'blank';

    /** A login secret that must not survive. */
    public const FORGET = 'forget';

    public const PASSWORD = 'password';

    /** @var array<string, array<string, string>> */
    private const RULES = [
        'users' => ['name' => self::NAME, 'email' => self::EMAIL, 'phone' => self::PHONE, 'password' => self::PASSWORD, 'remember_token' => self::FORGET],
        'admins' => ['remember_token' => self::FORGET],
        'user_addresses' => [
            'label' => self::BLANK, 'first_name' => self::FIRST_NAME, 'last_name' => self::LAST_NAME, 'company' => self::COMPANY,
            'address_line1' => self::STREET, 'address_line2' => self::BLANK, 'city' => self::CITY, 'state' => self::BLANK,
            'postal_code' => self::POSTAL, 'phone' => self::PHONE,
        ],
        'orders' => [
            'guest_email' => self::EMAIL, 'customer_phone' => self::PHONE, 'customer_note' => self::BLANK, 'ip_address' => self::IP,
            'company_name' => self::COMPANY, 'vat_number' => self::CODE,
            'shipping_name' => self::NAME, 'shipping_address_line1' => self::STREET, 'shipping_address_line2' => self::BLANK,
            'shipping_city' => self::CITY, 'shipping_state' => self::BLANK, 'shipping_postal_code' => self::POSTAL,
            'billing_name' => self::NAME, 'billing_address_line1' => self::STREET, 'billing_address_line2' => self::BLANK,
            'billing_city' => self::CITY, 'billing_state' => self::BLANK, 'billing_postal_code' => self::POSTAL,
        ],
        'order_notes' => ['note' => self::BLANK],
        'order_status_history' => ['note' => self::BLANK],
        'payments' => ['gateway_response' => self::BLANK],
        'refund_requests' => ['reason' => self::BLANK, 'admin_note' => self::BLANK, 'return_images' => self::BLANK],
        'contact_messages' => [
            'name' => self::NAME, 'email' => self::EMAIL, 'vin_number' => self::VIN, 'message' => self::BLANK,
            'reply_body' => self::BLANK, 'ip_address' => self::IP,
        ],
        'part_inquiries' => [
            'email' => self::EMAIL, 'phone' => self::PHONE, 'vin_number' => self::VIN, 'notes' => self::BLANK,
            'admin_note' => self::BLANK, 'ip_address' => self::IP,
        ],
        'newsletter_subscribers' => ['email' => self::EMAIL, 'unsubscribe_token' => self::CODE, 'ip_address' => self::IP],
        'newsletter_campaign_recipients' => ['email' => self::EMAIL],
        'product_reviews' => ['reviewer_name' => self::NAME, 'title' => self::BLANK, 'comment' => self::BLANK, 'ip_address' => self::IP],
    ];

    /**
     * Columns that look personal, or sit in a personal table, and are deliberately kept. Each entry is shown to
     * the person exporting as a plain sentence, and is the only place the audit lets a canary survive.
     *
     * @var array<string, array{columns: array<int, string>, why: string}>
     */
    private const NOT_SCRUBBED = [
        'admins' => ['columns' => ['name', 'email', 'password'], 'why' => 'Administrator accounts keep their real names and emails (and password hashes) so the team can log in.'],
        'ip_blocklists' => ['columns' => ['ip_address', 'reason'], 'why' => 'The shop\'s own list of blocked visitor addresses is kept as it is.'],
        'orders' => ['columns' => ['order_number', 'payment_reference', 'tracking_number', 'invoice_number', 'shipping_method_name_snapshot'], 'why' => 'Order numbers, payment and tracking references and invoice numbers are kept so orders stay recognisable.'],
        'payments' => ['columns' => ['transaction_id'], 'why' => 'Payment gateway transaction ids are kept.'],
        'contact_messages' => ['columns' => ['order_number', 'oem_number', 'manufacturer', 'car_model', 'year'], 'why' => 'The part, order number and car model a message refers to are kept.'],
        'part_inquiries' => ['columns' => ['oem_number', 'manufacturer', 'car_model', 'year'], 'why' => 'The part and car model an inquiry asks about are kept.'],
        'tax_rates' => ['columns' => ['notes'], 'why' => 'Notes staff wrote on tax rates are kept.'],
        'inventory_logs' => ['columns' => ['note'], 'why' => 'Staff notes on stock changes are kept.'],
        'testimonials' => ['columns' => ['name', 'company', 'location', 'quote'], 'why' => 'Testimonials are public website content and are kept.'],
        'settings' => ['columns' => ['key', 'value'], 'why' => 'The shop\'s own settings, branding, addresses and bank details are kept.'],
    ];

    /**
     * Columns that hold a status, a code, a language, a country or a setting. They say nothing about a person,
     * are not shown to the person exporting, and are the only other place the audit lets a canary survive.
     *
     * @var array<string, array<int, string>>
     */
    private const STRUCTURAL = [
        'admins' => ['role', 'dashboard_preferences'],
        'contact_messages' => ['status', 'subject_type'],
        'newsletter_subscribers' => ['lang'],
        'order_status_history' => ['old_status', 'new_status'],
        'orders' => ['billing_country_code', 'shipping_country_code', 'carrier', 'locale', 'payment_status', 'status', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content'],
        'product_reviews' => ['status'],
        'refund_requests' => ['status'],
        'tax_rates' => ['country_code', 'country_name'],
        'user_addresses' => ['country_code'],
        'users' => ['preferred_locale', 'timezone'],
    ];

    /** "table.column" for every structural column. */
    public static function structuralColumns(): array
    {
        $out = [];

        foreach (self::STRUCTURAL as $table => $columns) {
            foreach ($columns as $column) {
                $out[] = "{$table}.{$column}";
            }
        }

        sort($out);

        return $out;
    }

    /** @return array<int, string> */
    public static function tables(): array
    {
        return array_keys(self::RULES);
    }

    /** @return array<string, string> column => rule */
    public static function rulesFor(string $table): array
    {
        return self::RULES[$table] ?? [];
    }

    /** @return array<string, array<string, string>> */
    public static function rules(): array
    {
        return self::RULES;
    }

    /** @return array<string, array{columns: array<int, string>, why: string}> */
    public static function notScrubbed(): array
    {
        return self::NOT_SCRUBBED;
    }

    /** "table.column" for every column that is deliberately left as it is. */
    public static function notScrubbedColumns(): array
    {
        $out = [];

        foreach (self::NOT_SCRUBBED as $table => $entry) {
            foreach ($entry['columns'] as $column) {
                $out[] = "{$table}.{$column}";
            }
        }

        sort($out);

        return $out;
    }

    /**
     * The plain list shown in the wizard and written into the package report.
     *
     * @return array<int, array{table: string, columns: array<int, string>, why: string}>
     */
    public static function notScrubbedForReport(): array
    {
        $list = [];

        foreach (self::NOT_SCRUBBED as $table => $entry) {
            $list[] = ['table' => $table, 'columns' => $entry['columns'], 'why' => $entry['why']];
        }

        return $list;
    }
}
