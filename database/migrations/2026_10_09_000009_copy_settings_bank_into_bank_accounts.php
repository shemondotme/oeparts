<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bank details used to live in two places: one account in Settings → Store Operations
 * and any number under Sales → Bank Accounts. Bank Accounts is now the single source,
 * so the account saved in Settings is copied there once (if it holds real details and
 * no account exists yet). The Settings values are left untouched as a fallback.
 *
 * Idempotent: does nothing when an account already exists or the Settings IBAN is
 * empty or the placeholder IBAN the demo seeder installs. Never removes anything.
 */
return new class extends Migration
{
    private const DEMO_IBAN = 'DE89370400440532013000';

    public function up(): void
    {
        if (! Schema::hasTable('invoice_bank_accounts') || ! Schema::hasTable('settings')) {
            return;
        }

        if (DB::table('invoice_bank_accounts')->exists()) {
            return;
        }

        $value = fn (string $key): string => trim((string) DB::table('settings')->where('group', 'payment')->where('key', $key)->value('value'));

        $iban = strtoupper(preg_replace('/\s+/', '', $value('bank_iban')) ?? '');

        if ($iban === '' || $iban === self::DEMO_IBAN) {
            return;
        }

        $company = trim((string) DB::table('settings')->where('group', 'company')->where('key', 'name')->value('value'));
        $currency = strtoupper(trim((string) DB::table('settings')->where('group', 'general')->where('key', 'currency')->value('value'))) ?: 'EUR';
        $bic = strtoupper(preg_replace('/\s+/', '', $value('bank_bic')) ?? '');

        DB::table('invoice_bank_accounts')->insert([
            'label' => 'Main account',
            'currency' => $currency,
            'account_holder' => $value('bank_account_holder') ?: ($company ?: 'Account holder'),
            'bank_name' => $value('bank_name') ?: null,
            'iban' => $iban,
            'bic' => $bic === '' ? null : $bic,
            'intermediary_bank' => null,
            'instructions' => null,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Nothing to undo: the copied account may since have been edited by the admin.
    }
};
