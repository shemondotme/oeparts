<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Every multilang settings row across the app was seeded with the identical
 * English string duplicated into every locale slot (en/de/lt/fr/es all equal).
 * Two different consumption patterns exist, so the fix differs by group:
 *
 * - `ui.*` (258 rows, read via ui_copy()/settings_trans() with a lang-file
 *   fallback baked into the helper): blanking non-en locales to '' correctly
 *   makes ui_copy() fall through to the already-translated lang/{locale}/*.php
 *   files (empty string, not a missing key — trans_field()'s `??` only skips
 *   null, so a present-but-blank key is required to trigger the fallback).
 * - announcement/maintenance/checkout/preloader (11 rows, read via bare
 *   settings()/settings_trans() with only a hardcoded-English default — no
 *   lang-file fallback exists): blanking would leave them empty or English
 *   either way, so these get real per-locale translations written in instead.
 *
 * contact.hours and shipping.nudge_text are excluded — confirmed unread by
 * any view or controller, so translating them would be dead work.
 *
 * Guarded on "still identical across all 5 locales" so a genuine operator
 * customization made between seeding and this migration running is left
 * untouched. Idempotent: a second run finds en already differs from the
 * fixed locales and skips those rows.
 */
return new class extends Migration
{
    private function translations(): array
    {
        return [
            'announcement' => [
                'cta_text' => [
                    'de' => 'Kostenloser, nachverfolgter EU-Versand ab €150 Bestellwert. 500.000+ Original-OEM-Teile auf Lager.',
                    'lt' => 'Nemokamas sekamas pristatymas ES nuo €150 užsakymo. 500 000+ originalių OEM dalių sandėlyje.',
                    'fr' => "Livraison UE suivie gratuite dès 150 € d'achat. Plus de 500 000 pièces OEM d'origine en stock.",
                    'es' => 'Envío UE con seguimiento gratuito en pedidos superiores a 150 €. Más de 500 000 piezas OEM originales en stock.',
                ],
            ],
            'maintenance' => [
                'message' => [
                    'de' => 'Wir sind bald wieder da.',
                    'lt' => 'Netrukus grįšime.',
                    'fr' => 'Nous serons bientôt de retour.',
                    'es' => 'Volveremos pronto.',
                ],
            ],
            'checkout' => [
                'payment_error_message' => [
                    'de' => 'Zahlung fehlgeschlagen. Bitte versuchen Sie es erneut.',
                    'lt' => 'Apmokėjimas nepavyko. Bandykite dar kartą.',
                    'fr' => 'Le paiement a échoué. Veuillez réessayer.',
                    'es' => 'El pago ha fallado. Inténtelo de nuevo.',
                ],
                'payment_success_message' => [
                    'de' => 'Zahlung erhalten. Vielen Dank!',
                    'lt' => 'Mokėjimas gautas. Dėkojame!',
                    'fr' => 'Paiement reçu. Merci !',
                    'es' => '¡Pago recibido. Gracias!',
                ],
            ],
            'preloader' => [
                'aria_label' => [
                    'de' => 'Wird geladen',
                    'lt' => 'Kraunama',
                    'fr' => 'Chargement',
                    'es' => 'Cargando',
                ],
                'subline' => [
                    'de' => 'Original-Teile-Index',
                    'lt' => 'Originalių dalių indeksas',
                    'fr' => "Index des pièces d'origine",
                    'es' => 'Índice de piezas originales',
                ],
                'status_line' => [
                    'de' => 'Index wird kalibriert',
                    'lt' => 'Kalibruojamas indeksas',
                    'fr' => "Calibrage de l'index",
                    'es' => 'Calibrando el índice',
                ],
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
        // Group 1: `ui.*` — blank non-en so ui_copy() falls through to lang files.
        DB::table('settings')->where('group', 'ui')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if (! $this->isIdenticalAcrossLocales($row->value)) {
                        continue;
                    }

                    $decoded = json_decode($row->value, true);
                    foreach (['de', 'lt', 'fr', 'es'] as $locale) {
                        $decoded[$locale] = '';
                    }

                    DB::table('settings')->where('id', $row->id)->update([
                        'value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });

        // Group 2: no lang-file fallback exists — write real translations.
        foreach ($this->translations() as $group => $keys) {
            foreach ($keys as $key => $locales) {
                $row = DB::table('settings')->where('group', $group)->where('key', $key)->first();

                if ($row === null || ! $this->isIdenticalAcrossLocales($row->value)) {
                    continue;
                }

                $decoded = json_decode($row->value, true);
                foreach ($locales as $locale => $text) {
                    $decoded[$locale] = $text;
                }

                DB::table('settings')->where('id', $row->id)->update([
                    'value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }

        foreach (['ui', 'announcement', 'maintenance', 'checkout', 'preloader'] as $group) {
            Cache::forget("settings.{$group}");
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'ui')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
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
            });

        foreach ($this->translations() as $group => $keys) {
            foreach ($keys as $key => $locales) {
                $row = DB::table('settings')->where('group', $group)->where('key', $key)->first();
                if ($row === null) {
                    continue;
                }

                $decoded = json_decode($row->value, true);
                if (! is_array($decoded) || ! isset($decoded['en'])) {
                    continue;
                }

                foreach (array_keys($locales) as $locale) {
                    $decoded[$locale] = $decoded['en'];
                }

                DB::table('settings')->where('id', $row->id)->update([
                    'value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }

        foreach (['ui', 'announcement', 'maintenance', 'checkout', 'preloader'] as $group) {
            Cache::forget("settings.{$group}");
        }
    }
};
