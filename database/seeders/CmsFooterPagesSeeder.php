<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Seeds the four footer-linked CMS pages so /about, /privacy-policy,
 * /terms-of-service and /cookie-policy resolve instead of 404-ing.
 *
 * Content is intentionally generic boilerplate — editable from the admin
 * CMS once the team has their final copy.
 */
class CmsFooterPagesSeeder extends Seeder
{
    public function run(): void
    {
        $creatorId = \App\Models\Admin::query()->orderBy('id')->value('id') ?? 1;

        foreach ($this->pages() as $slug => $payload) {
            Page::updateOrCreate(
                ['slug' => $slug],
                array_merge($payload, [
                    'status'       => ContentStatus::Published,
                    'published_at' => now(),
                    'is_footer'    => true,
                    'is_header'    => false,
                    'is_homepage'  => false,
                    'created_by'   => $creatorId,
                ])
            );
        }
    }

    private function pages(): array
    {
        return [
            'about' => [
                'title' => [
                    'en' => 'About OEMHub',
                    'de' => 'Über OEMHub',
                    'lt' => 'Apie OEMHub',
                    'fr' => 'À propos d’OEMHub',
                    'es' => 'Acerca de OEMHub',
                ],
                'meta_title' => [
                    'en' => 'About OEMHub — the B2B marketplace for genuine OEM auto parts',
                ],
                'meta_description' => [
                    'en' => 'OEMHub is the dedicated B2B marketplace for verified OEM auto parts — cross-reference by OEM number, compare suppliers, ship across the EU.',
                ],
                'content' => [
                    'en' => <<<'HTML'
<p class="lead">OEMHub is a specialist B2B marketplace built for workshops, dealers and parts buyers across Europe. We help professionals find, verify and source genuine OEM auto parts without the catalogue guesswork.</p>

<h2>Our mission</h2>
<p>Every day, millions of OEM part numbers change hands across Europe. Cross-referencing them, verifying authenticity, and getting a fair price still takes far too much time. OEMHub exists to remove that friction — one searchable, verified catalogue, backed by vetted suppliers and transparent logistics.</p>

<h2>What we do</h2>
<ul>
    <li><strong>Cross-reference by OEM number.</strong> Normalised across manufacturers and aftermarket equivalents.</li>
    <li><strong>Verify supplier authenticity.</strong> Every listing is tied to a verified merchant with documented sourcing.</li>
    <li><strong>Quote transparently.</strong> Part inquiry and bulk-quote flows surface true landed cost, not ad-hoc markups.</li>
    <li><strong>Ship across the EU.</strong> Partner carriers cover all EU member states plus Norway, Switzerland and the UK.</li>
</ul>

<h2>Who we serve</h2>
<p>Independent workshops, franchise dealers, fleet operators, wholesalers, and export buyers. If you purchase OEM parts in volume, we can cut your quoting cycle and landed cost.</p>

<h2>Get in touch</h2>
<p>Have a sourcing requirement we should know about? Use the contact desk and we’ll route you to the right supplier.</p>
HTML,
                ],
            ],

            'privacy-policy' => [
                'title' => [
                    'en' => 'Privacy Policy',
                    'de' => 'Datenschutzerklärung',
                    'lt' => 'Privatumo politika',
                    'fr' => 'Politique de confidentialité',
                    'es' => 'Política de privacidad',
                ],
                'meta_title' => [
                    'en' => 'Privacy Policy — OEMHub',
                ],
                'meta_description' => [
                    'en' => 'How OEMHub collects, uses, stores and protects personal data under GDPR and EU regulations.',
                ],
                'content' => [
                    'en' => <<<'HTML'
<p class="lead">This policy explains what personal data OEMHub collects, why we collect it, how long we keep it, and the rights you have under the EU General Data Protection Regulation (GDPR).</p>

<h2>1. Data controller</h2>
<p>The data controller for this website is OEMHub. You can reach our privacy team via the contact desk linked in the footer.</p>

<h2>2. What data we collect</h2>
<ul>
    <li><strong>Account data</strong> — name, email, password hash, preferred locale.</li>
    <li><strong>Order data</strong> — billing and shipping addresses, purchased items, invoices.</li>
    <li><strong>Payment metadata</strong> — card network, last four digits, transaction references. Full card details never touch our servers; they are handled by our PCI-compliant payment providers.</li>
    <li><strong>Usage data</strong> — IP address, browser, pages viewed, search queries. Used solely to operate and improve the service.</li>
</ul>

<h2>3. Legal bases</h2>
<p>We process personal data on the following legal bases: performance of contract (when you place an order), legitimate interest (fraud prevention, service improvement), consent (marketing emails, non-essential cookies) and legal obligation (tax, accounting and EU consumer law).</p>

<h2>4. Retention</h2>
<p>Order records are retained for the statutory period required by EU tax law. Account data is retained until you delete your account, after which it is anonymised within 30 days except where longer retention is legally required.</p>

<h2>5. Your rights</h2>
<ul>
    <li>Right to access the personal data we hold about you.</li>
    <li>Right to rectification of inaccurate data.</li>
    <li>Right to erasure (“right to be forgotten”).</li>
    <li>Right to restrict or object to processing.</li>
    <li>Right to data portability.</li>
    <li>Right to lodge a complaint with a supervisory authority.</li>
</ul>

<h2>6. International transfers</h2>
<p>Where data is transferred outside the European Economic Area, we rely on EU Standard Contractual Clauses and additional safeguards where required.</p>

<h2>7. Changes</h2>
<p>We may update this policy from time to time. The revision date shown above reflects the most recent change.</p>
HTML,
                ],
            ],

            'terms-of-service' => [
                'title' => [
                    'en' => 'Terms of Service',
                    'de' => 'Nutzungsbedingungen',
                    'lt' => 'Paslaugų teikimo sąlygos',
                    'fr' => 'Conditions d’utilisation',
                    'es' => 'Términos del servicio',
                ],
                'meta_title' => [
                    'en' => 'Terms of Service — OEMHub',
                ],
                'meta_description' => [
                    'en' => 'The rules that govern your use of the OEMHub marketplace, including account, ordering, payment and liability terms.',
                ],
                'content' => [
                    'en' => <<<'HTML'
<p class="lead">By accessing or using OEMHub you agree to these Terms of Service. Please read them carefully. If you do not accept any part of these terms, please stop using the service.</p>

<h2>1. Eligibility &amp; accounts</h2>
<p>OEMHub is a B2B marketplace intended for businesses and professional buyers. You confirm that you have the authority to act on behalf of the account you register, and that the information you provide is accurate.</p>

<h2>2. Orders &amp; contract formation</h2>
<p>Placing an order constitutes an offer to purchase. A binding contract is formed only once we confirm acceptance of your order. We reserve the right to refuse or cancel orders in cases of pricing errors, supply unavailability or suspected fraud.</p>

<h2>3. Pricing &amp; payment</h2>
<p>Prices are shown inclusive or exclusive of VAT as indicated at checkout. Payment is due at the time of order unless a credit arrangement has been agreed in writing. Bank transfer orders ship once funds are cleared.</p>

<h2>4. Shipping &amp; delivery</h2>
<p>Delivery estimates are provided in good faith but are not guaranteed. Risk passes to the buyer upon delivery to the shipping carrier. You are responsible for inspecting the goods on arrival.</p>

<h2>5. Returns &amp; refunds</h2>
<p>Returns are accepted within the window shown on your order confirmation, subject to the part being unused, in original packaging and not subject to a no-return exclusion. Consumables, sealed electronic units and special-order parts may be excluded.</p>

<h2>6. Liability</h2>
<p>To the extent permitted by law, OEMHub’s aggregate liability for any claim arising from these terms is limited to the price paid for the part(s) giving rise to the claim. We are not liable for indirect or consequential losses.</p>

<h2>7. Intellectual property</h2>
<p>All OEMHub content, trademarks and software are the property of OEMHub or its licensors. Third-party trademarks appear solely for identification and remain the property of their respective owners.</p>

<h2>8. Governing law</h2>
<p>These terms are governed by the laws of the jurisdiction in which OEMHub is established. Any dispute is subject to the exclusive jurisdiction of the competent courts in that jurisdiction, save where consumer law grants you protection in another jurisdiction.</p>

<h2>9. Changes</h2>
<p>We may amend these terms from time to time. Material changes will be communicated via email or a notice on the site.</p>
HTML,
                ],
            ],

            'cookie-policy' => [
                'title' => [
                    'en' => 'Cookie Policy',
                    'de' => 'Cookie-Richtlinie',
                    'lt' => 'Slapukų politika',
                    'fr' => 'Politique relative aux cookies',
                    'es' => 'Política de cookies',
                ],
                'meta_title' => [
                    'en' => 'Cookie Policy — OEMHub',
                ],
                'meta_description' => [
                    'en' => 'How OEMHub uses cookies and similar technologies, and how you can control them.',
                ],
                'content' => [
                    'en' => <<<'HTML'
<p class="lead">This page explains the cookies and similar technologies that OEMHub uses, what they do, and how you can manage your preferences.</p>

<h2>1. What is a cookie?</h2>
<p>A cookie is a small text file stored on your device when you visit a website. Cookies allow the site to remember your actions and preferences over a period of time, so you don’t have to keep re-entering them whenever you come back.</p>

<h2>2. Categories of cookies we use</h2>
<ul>
    <li><strong>Strictly necessary.</strong> Required for the site to function — login session, cart contents, CSRF protection. These cannot be disabled.</li>
    <li><strong>Functional.</strong> Remember your language, currency and UI preferences.</li>
    <li><strong>Analytics.</strong> Help us understand how the site is used so we can improve it. Loaded only with your consent.</li>
    <li><strong>Marketing.</strong> Used to measure the effectiveness of advertising and, where applicable, tailor communications. Loaded only with your consent.</li>
</ul>

<h2>3. Managing your preferences</h2>
<p>You can change or withdraw your consent at any time using the cookie banner on this site. You can also block or delete cookies through your browser settings, although doing so may impact some features.</p>

<h2>4. Third-party cookies</h2>
<p>Some cookies are placed by trusted third parties that help us operate the service — for example, payment providers, fraud-prevention systems and analytics platforms. Those providers may process your data in accordance with their own privacy policies.</p>

<h2>5. Changes</h2>
<p>We update this policy when our use of cookies changes. Please revisit this page from time to time to stay informed.</p>
HTML,
                ],
            ],
        ];
    }
}
