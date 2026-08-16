<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * checkout.payment_success_message/payment_error_message were fixed once
 * already by 2026_07_11_000002_fix_locale_blind_settings_copy (real
 * per-locale translations written in place of the identical-English-in-
 * every-locale duplicate) — but SettingsSeeder.php was never updated to
 * match, so any full reseed since then silently regressed both rows back
 * to English-only. Confirmed live: an admin viewing the (also newly
 * translatable-tabs'd) Customer Messages fields would otherwise still see
 * English duplicated into every locale tab on this database. Same
 * idempotent "still identical across all 5 locales" guard as the original
 * migration, so a genuine operator customization made since then is left
 * untouched.
 */
return new class extends Migration
{
    private function translations(): array
    {
        return [
            'payment_success_message' => [
                'en' => 'Payment received. Thank you!',
                'de' => 'Zahlung erhalten. Vielen Dank!',
                'lt' => 'Mokėjimas gautas. Dėkojame!',
                'fr' => 'Paiement reçu. Merci !',
                'es' => '¡Pago recibido. Gracias!',
            ],
            'payment_error_message' => [
                'en' => 'Payment failed. Please try again.',
                'de' => 'Zahlung fehlgeschlagen. Bitte versuchen Sie es erneut.',
                'lt' => 'Apmokėjimas nepavyko. Bandykite dar kartą.',
                'fr' => 'Le paiement a échoué. Veuillez réessayer.',
                'es' => 'El pago ha fallado. Inténtelo de nuevo.',
            ],
        ];
    }

    private function isIdenticalAcrossLocales(?string $rawJson): bool
    {
        if (! $rawJson) {
            return false;
        }

        $decoded = json_decode($rawJson, true);
        if (! is_array($decoded)) {
            return false;
        }

        $keys = array_keys($decoded);
        sort($keys);
        if ($keys !== ['de', 'en', 'es', 'fr', 'lt']) {
            return false;
        }

        return count(array_unique(array_values($decoded))) === 1;
    }

    public function up(): void
    {
        foreach ($this->translations() as $key => $locales) {
            $row = DB::table('settings')->where('group', 'checkout')->where('key', $key)->first();

            if ($row === null || ! $this->isIdenticalAcrossLocales($row->value)) {
                continue;
            }

            DB::table('settings')->where('id', $row->id)->update([
                'value' => json_encode($locales, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        Cache::forget('settings.checkout');
    }

    public function down(): void
    {
        foreach (array_keys($this->translations()) as $key) {
            $row = DB::table('settings')->where('group', 'checkout')->where('key', $key)->first();

            if ($row === null) {
                continue;
            }

            $decoded = json_decode($row->value, true);
            if (! is_array($decoded) || ! isset($decoded['en'])) {
                continue;
            }

            foreach (['de', 'lt', 'fr', 'es'] as $locale) {
                $decoded[$locale] = $decoded['en'];
            }

            DB::table('settings')->where('id', $row->id)->update([
                'value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        Cache::forget('settings.checkout');
    }
};
