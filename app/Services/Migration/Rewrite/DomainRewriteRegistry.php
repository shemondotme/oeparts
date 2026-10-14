<?php

namespace App\Services\Migration\Rewrite;

/**
 * The single list of where this application stores text that can hold the address of the site, and what is done
 * about each place. Nothing is rewritten that is not on it, and tests fail the build when a column that could hold
 * an address is on no list: a new migration cannot quietly add a place the rewrite does not know about.
 *
 * Three kinds of entry:
 *   targets     tables whose columns are rewritten (RewriteTarget);
 *   settings    per-key rules for the settings table, whose rows are otherwise all handled by type;
 *   classified  columns that are deliberately NOT rewritten, each with the reason (history, mail, scripts, noise).
 */
class DomainRewriteRegistry
{
    /** @var array<string, RewriteTarget>|null */
    private ?array $targets = null;

    /** @return array<string, RewriteTarget> table => target */
    public function targets(): array
    {
        if ($this->targets !== null) {
            return $this->targets;
        }

        $multi = RewriteKind::MultilangJson;
        $list = [
            new RewriteTarget('redirects', ['to_url' => RewriteKind::Url]),
            new RewriteTarget('seo_meta', [
                'meta_title' => RewriteKind::Plain, 'meta_description' => RewriteKind::Plain, 'canonical_url' => RewriteKind::Url,
                'og_title' => RewriteKind::Plain, 'og_description' => RewriteKind::Plain,
            ]),
            new RewriteTarget('menu_items', ['url' => RewriteKind::Url, 'label' => $multi]),
            new RewriteTarget('sections', ['title' => $multi, 'content' => $multi]),
            new RewriteTarget('section_versions', ['snapshot' => RewriteKind::Json, 'change_summary' => RewriteKind::Plain]),
            new RewriteTarget('content_revisions', ['content_snapshot' => RewriteKind::Json]),
            new RewriteTarget('pages', ['title' => $multi, 'content' => $multi, 'meta_title' => $multi, 'meta_description' => $multi]),
            new RewriteTarget('blog_posts', ['title' => $multi, 'excerpt' => $multi, 'content' => $multi, 'meta_title' => $multi, 'meta_description' => $multi]),
            new RewriteTarget('faqs', ['question' => $multi, 'answer' => $multi]),
            new RewriteTarget('testimonials', ['quote' => $multi]),
            new RewriteTarget('shipping_methods', ['description' => $multi]),
            new RewriteTarget('media_files', ['file_url' => RewriteKind::Url]),
            new RewriteTarget('language_strings', ['value' => RewriteKind::Plain]),
            // A campaign that was already sent is a record of what the customer received: only drafts are touched.
            new RewriteTarget('newsletter_campaigns', ['subject' => RewriteKind::Plain, 'html_content' => RewriteKind::Html, 'plain_content' => RewriteKind::Plain], RewriteTarget::SMALL, ['status' => 'draft']),
            new RewriteTarget('products', ['description' => $multi, 'specifications' => $multi, 'video_url' => RewriteKind::Url], RewriteTarget::BULK),
        ];

        $this->targets = [];

        foreach ($list as $target) {
            $this->targets[$target->table] = $target;
        }

        return $this->targets;
    }

    public function target(string $table): ?RewriteTarget
    {
        return $this->targets()[$table] ?? null;
    }

    /**
     * Columns that are deliberately not rewritten: "table.column" => [kind, reason]. A reason is required: "we did
     * not look" is not a classification. `table.*` covers every column of the table.
     *
     * @return array<string, array{0: RewriteKind, 1: string}>
     */
    public function classified(): array
    {
        $history = RewriteKind::Preserve;
        $noise = RewriteKind::Ignore;
        $scan = RewriteKind::ScanOnly;

        return [
            // ---- history that must stay as it was written ---------------------------------------------------
            'payments.gateway_response' => [$history, "The gateway's own answer, kept exactly as received."],
            'orders.customer_note' => [$history, 'What the customer wrote at the time.'],
            'order_notes.note' => [$history, 'A staff note about what happened.'],
            'order_status_history.note' => [$history, 'The record of a status change.'],
            'contact_messages.message' => [$history, 'What the customer wrote.'],
            'contact_messages.reply_body' => [$history, 'What was answered.'],
            'part_inquiries.notes' => [$history, 'What the customer wrote.'],
            'part_inquiries.admin_note' => [$history, 'A staff note.'],
            'refund_requests.admin_note' => [$history, 'A staff note.'],
            'invoice_clients.notes' => [$history, 'A staff note on a customer of a legal document.'],
            'custom_invoices.*' => [$history, 'Invoices are legal documents: they are never edited by a domain change.'],
            'custom_invoice_payments.note' => [$history, 'Part of a legal document.'],
            'inventory_logs.note' => [$history, 'A log line.'],
            'activity_logs.*' => [$history, 'The audit trail.'],
            'email_logs.*' => [$history, 'What was sent, to whom, at the time.'],
            'indexnow_push_logs.error_message' => [$history, 'A log line.'],
            'notifications.data' => [$history, 'What a notification said when it was sent.'],
            'not_found_logs.*' => [$history, 'Requests the old site received.'],
            'search_logs.*' => [$history, 'Searches on the old site.'],
            'failed_search_logs.*' => [$history, 'Searches on the old site.'],
            'login_logs.*' => [$history, 'Sign-ins on the old site.'],
            'product_images.source_url' => [$history, 'Where an image was originally fetched from: a fact about the past.'],
            'carriers.tracking_url' => [$history, "A carrier's tracking address, not this site's."],
            'redirects.from_url' => [$history, 'The path being redirected FROM: rewriting it would break the redirect.'],
            'abandoned_carts.cart_snapshot' => [$history, 'A snapshot of what a visitor had in a cart.'],

            // ---- scripts and instructions, looked at and reported only ------------------------------------
            'invoice_bank_accounts.instructions' => [$scan, 'Payment instructions: reported if they mention the old address, never rewritten.'],

            // ---- columns that cannot hold the site's address ----------------------------------------------
            'conditions.text_color' => [$noise, 'A colour.'],
            'media_files.alt_text' => [$noise, 'A short description of an image.'],
            'product_images.alt_text' => [$noise, 'A short description of an image.'],
            'order_items.oem_number_snapshot' => [$noise, 'A part number as it was when ordered.'],
            'order_items.manufacturer_snapshot' => [$noise, 'A manufacturer name as it was when ordered.'],
            'order_items.condition_snapshot' => [$noise, 'A condition name as it was when ordered.'],
            'orders.shipping_method_name_snapshot' => [$noise, 'A shipping method name as it was when ordered.'],
            'orders.utm_content' => [$noise, 'Campaign tracking text.'],
            'tax_rates.notes' => [$noise, 'A note a member of staff wrote about a tax rate.'],
            'push_topics.description' => [$noise, 'A fixed description of a notification topic.'],
            'seo_meta.robots' => [$noise, 'A robots directive.'],
            'admin_push_deferred.*' => [$noise, 'A queue of notifications to admins; not content.'],
            'admin_push_subscriptions.content_encoding' => [$noise, 'A technical label.'],
            'cleanup_runs.message' => [$noise, 'A message about a cleanup run.'],
        ];
    }

    /**
     * Rules for single settings. Every other non-encrypted setting is processed by its type (text is rewritten, JSON
     * is rewritten and re-validated), so a key nobody listed here is still covered. A key containing `*` covers a
     * family.
     *
     * @return array<string, array{0: SettingAction, 1: string}>
     */
    public function settingRules(): array
    {
        return [
            'general.site_url' => [SettingAction::Set, 'The address of the site itself.'],
            'seo.canonical_host' => [SettingAction::CanonicalHost, 'Decided by the canonical host policy of the import, not by text replacement.'],
            'seo.gsc_property_url' => [SettingAction::Clear, 'A Search Console property belongs to the old address and means nothing on the new one.'],
            'general.header_scripts' => [SettingAction::ScanOnly, 'Scripts are reported, never edited.'],
            'general.footer_scripts' => [SettingAction::ScanOnly, 'Scripts are reported, never edited.'],
            'appearance.custom_css' => [SettingAction::Css, 'url() and @import in custom CSS.'],
            'announcement.url' => [SettingAction::Url, 'A link in the announcement bar.'],
            'social_links.*_url' => [SettingAction::Url, "Links to the shop's social accounts, which may point back at the shop."],
            'general.site_email' => [SettingAction::Email, 'A mailbox, not an address of the site.'],
            'contact.email' => [SettingAction::Email, 'A mailbox.'],
            'company.email' => [SettingAction::Email, 'A mailbox.'],
            'email.from_address' => [SettingAction::Email, 'A mailbox.'],
            'email.reply_to' => [SettingAction::Email, 'A mailbox.'],
            'email.admin_notify_email' => [SettingAction::Email, 'A mailbox.'],
            'maintenance.contact_email' => [SettingAction::Email, 'A mailbox.'],
            'email.smtp_host' => [SettingAction::Ignore, 'The mail server, not the site.'],
            'appearance.custom_css_enabled' => [SettingAction::Ignore, 'A switch, not CSS.'],
            'security.inquiry_max_per_email' => [SettingAction::Ignore, 'A number.'],
            'cleanup.*_email_logs' => [SettingAction::Ignore, 'A retention number or a switch for the email log, not an address.'],
        ];
    }

    /** @return array{0: RewriteKind, 1: string}|null the classification of "table.column", or null when none exists */
    public function classification(string $table, string $column): ?array
    {
        $classified = $this->classified();

        return $classified["{$table}.{$column}"] ?? $classified["{$table}.*"] ?? null;
    }

    /** @return array{0: SettingAction, 1: string}|null the rule for one setting, or null when it is handled by its type */
    public function settingRule(string $group, string $key): ?array
    {
        foreach ($this->settingRules() as $pattern => $rule) {
            if (fnmatch($pattern, "{$group}.{$key}")) {
                return $rule;
            }
        }

        return null;
    }
}
