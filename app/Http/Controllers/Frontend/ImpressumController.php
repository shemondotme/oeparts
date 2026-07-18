<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Legal Notice / Impressum — required commercial disclosure for an EU-facing
 * storefront (e.g. German §5 TMG-style requirements). Deliberately NOT a CMS
 * Page row: the company particulars below are the exact fields an admin
 * already maintains in Settings → Company (app/Filament/Pages/Settings/
 * CompanySettings.php), so this view reads them live via settings() rather
 * than duplicating a frozen snapshot into a second, driftable copy — the
 * same reasoning applied to the invoice/email branding fixes elsewhere in
 * this codebase. Only the surrounding labels/prose are translated per
 * locale (lang/{locale}/impressum.php); the company data itself doesn't
 * change by language.
 *
 * Route: /{lang}/impressum · name: frontend.impressum
 */
class ImpressumController extends Controller
{
    public function index(Request $request, string $lang)
    {
        return view('frontend.impressum', [
            'lang' => $lang,
            'company' => [
                'name' => settings('company.name', 'OeParts'),
                'address' => settings('company.address', ''),
                'vat_number' => settings('company.vat_number', ''),
                'registration_number' => settings('company.registration_number', ''),
                'managing_director' => settings('company.managing_director', ''),
                'email' => settings('company.email', '') ?: settings('general.site_email', ''),
                'phone' => settings('company.phone', '') ?: settings('general.site_phone', ''),
            ],
        ]);
    }
}
