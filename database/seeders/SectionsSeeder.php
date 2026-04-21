<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

/**
 * Seeds the 14 homepage section types with default multilang content.
 * All text is provided for en, de, lt, fr, es.
 * Sort order follows the intended visual layout top → bottom.
 */
class SectionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->sections() as $data) {
            Section::updateOrCreate(
                ['type' => $data['type'], 'location' => 'homepage'],
                [
                    'title'      => $data['title'],
                    'content'    => $data['content'],
                    'is_active'  => $data['is_active'] ?? true,
                    'sort_order' => $data['sort_order'],
                ]
            );
        }
    }

    private function ml(string $en, string $de, string $lt, string $fr, string $es): array
    {
        return compact('en', 'de', 'lt', 'fr', 'es');
    }

    private function sections(): array
    {
        return [
            // 1 — Hero: full-width OEM search bar with headline
            [
                'type'       => 'hero',
                'title'      => 'Hero',
                'sort_order' => 10,
                'content'    => [
                    'headline'    => $this->ml(
                        'Find Genuine OEM Parts — Fast',
                        'Originale OEM-Teile finden — Schnell',
                        'Raskite originalias OEM dalis — Greitai',
                        'Trouvez des pièces OEM d\'origine — Rapidement',
                        'Encuentre piezas OEM originales — Rápido'
                    ),
                    'subheadline' => $this->ml(
                        'Save up to 40% off dealer prices. Direct from EU warehouses.',
                        'Bis zu 40% günstiger als beim Händler. Direkt ab EU-Lager.',
                        'Sutaupykite iki 40% lyginant su atstovų kainomis. Tiesiogiai iš ES sandėlių.',
                        'Économisez jusqu\'à 40 % sur les prix concessionnaires. Direct depuis les entrepôts UE.',
                        'Ahorre hasta un 40% respecto al concesionario. Directo desde almacenes de la UE.'
                    ),
                    'placeholder' => $this->ml(
                        'Enter OEM number, e.g. 1K0407271F',
                        'OEM-Nummer eingeben, z. B. 1K0407271F',
                        'Įveskite OEM numerį, pvz. 1K0407271F',
                        'Entrez le numéro OEM, ex. 1K0407271F',
                        'Introduce el número OEM, ej. 1K0407271F'
                    ),
                    'button_text' => $this->ml('Find Part', 'Suchen', 'Ieškoti', 'Rechercher', 'Buscar'),
                    'bg_style'    => 'navy',
                ],
            ],

            // 2 — Trust bar: 4 trust signals below hero
            [
                'type'       => 'trust_bar',
                'title'      => 'Trust Bar',
                'sort_order' => 20,
                'content'    => [
                    'items' => [
                        [
                            'icon' => 'truck',
                            'text' => $this->ml('1-5 Days Tracked Delivery', '1-5 Tage verfolgte Lieferung', '1-5 dienų sekamas pristatymas', 'Livraison suivie 1-5 jours', 'Entrega rastreada 1-5 días'),
                        ],
                        [
                            'icon' => 'shield-check',
                            'text' => $this->ml('Genuine OEM Parts Only', 'Nur originale OEM-Teile', 'Tik originalios OEM dalys', 'Pièces OEM d\'origine uniquement', 'Solo piezas OEM originales'),
                        ],
                        [
                            'icon' => 'arrow-path',
                            'text' => $this->ml('14-Day Returns', '14-tägiges Rückgaberecht', '14 dienų grąžinimas', 'Retours sous 14 jours', 'Devoluciones en 14 días'),
                        ],
                        [
                            'icon' => 'lock-closed',
                            'text' => $this->ml('Secure Payment (Card/SEPA)', 'Sichere Zahlung (Karte/SEPA)', 'Saugus mokėjimas (Kortele/SEPA)', 'Paiement sécurisé (Carte/SEPA)', 'Pago seguro (Tarjeta/SEPA)'),
                        ],
                    ],
                ],
            ],

            // 3 — Stats counter: animated numbers from settings group stats_counter
            [
                'type'       => 'stats_counter',
                'title'      => 'Stats Counter',
                'sort_order' => 40,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'BY THE NUMBERS', 'IN ZAHLEN', 'SKAIČIAIS', 'EN CHIFFRES', 'EN CIFRAS'
                    ),
                    'headline' => $this->ml(
                        'Trusted by European Workshops',
                        'Von europäischen Werkstätten vertraut',
                        'Europos autoservisai pasitiki mumis',
                        'La confiance des ateliers européens',
                        'La confianza de los talleres europeos'
                    ),
                    'subheadline' => $this->ml(
                        'Real metrics from workshops and professionals across Europe.',
                        'Echte Kennzahlen von Werkstätten und Profis in ganz Europa.',
                        'Realūs duomenys iš dirbtuvių ir specialistų visoje Europoje.',
                        'Des données réelles issues d\'ateliers et de professionnels à travers l\'Europe.',
                        'Datos reales de talleres y profesionales de toda Europa.'
                    ),
                    'items' => [
                        ['key' => 'customers_count', 'suffix' => '+', 'label' => $this->ml('Customers', 'Kunden', 'Klientai', 'Clients', 'Clientes')],
                        ['key' => 'parts_count',     'suffix' => '+', 'label' => $this->ml('Parts Listed', 'Teile gelistet', 'Dalys', 'Pièces listées', 'Piezas listadas')],
                        ['key' => 'countries_count', 'suffix' => '',  'label' => $this->ml('EU Countries', 'EU-Länder', 'ES šalys', 'Pays UE', 'Países UE')],
                        ['key' => 'rating',          'suffix' => '',  'label' => $this->ml('Customer Rating', 'Bewertung', 'Įvertinimas', 'Évaluation', 'Calificación')],
                    ],
                ],
            ],

            // 4 — How it works: 3-step process
            [
                'type'       => 'how_it_works',
                'title'      => 'How It Works',
                'sort_order' => 30,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'PROCESS', 'ABLAUF', 'PROCESAS', 'PROCESSUS', 'PROCESO'
                    ),
                    'headline' => $this->ml('How It Works', 'So funktioniert es', 'Kaip tai veikia', 'Comment ça marche', 'Cómo funciona'),
                    'subheadline' => $this->ml(
                        'Three simple steps to get the right part at the right price.',
                        'Drei einfache Schritte zum richtigen Teil zum richtigen Preis.',
                        'Trys paprasti žingsniai norint gauti tinkamą dalį už tinkamą kainą.',
                        'Trois étapes simples pour obtenir la bonne pièce au bon prix.',
                        'Tres simples pasos para obtener la pieza correcta al precio correcto.'
                    ),
                    'steps' => [
                        [
                            'icon'        => 'magnifying-glass',
                            'step_number' => 1,
                            'title'       => $this->ml('Search by OEM Number', 'Nach OEM-Nummer suchen', 'Ieškokite pagal OEM numerį', 'Cherchez par numéro OEM', 'Busca por número OEM'),
                            'description' => $this->ml(
                                'Enter the exact OEM part number from your vehicle manual or old part.',
                                'Geben Sie die genaue OEM-Teilenummer aus Ihrem Fahrzeughandbuch ein.',
                                'Įveskite tikslų OEM dalies numerį iš jūsų automobilio vadovo.',
                                'Entrez le numéro de pièce OEM exact de votre manuel de véhicule.',
                                'Introduce el número exacto de pieza OEM de tu manual de vehículo.'
                            ),
                        ],
                        [
                            'icon'        => 'shopping-cart',
                            'step_number' => 2,
                            'title'       => $this->ml('Compare & Order', 'Vergleichen & Bestellen', 'Lyginkite ir užsakykite', 'Comparez & Commandez', 'Compara y Pide'),
                            'description' => $this->ml(
                                'See real-time stock availability and condition (Grade A/B/C). Add to cart securely.',
                                'Sieh dir Echtzeit-Verfügbarkeit an. Sicher in den Warenkorb und Kasse.',
                                'Matykite realaus laiko likučius. Saugiai atsiskaitykite.',
                                'Voir dispo en temps réel. Ajoutez au panier et payez en toute sécurité.',
                                'Ver stock en tiempo real. Añade al carrito y paga con seguridad.'
                            ),
                        ],
                        [
                            'icon'        => 'truck',
                            'step_number' => 3,
                            'title'       => $this->ml('Fast EU Delivery', 'Schnelle EU-Lieferung', 'Greitas pristatymas ES', 'Livraison UE rapide', 'Entrega rápida en UE'),
                            'description' => $this->ml(
                                'Your genuine OEM part ships from our EU warehouse within 1–2 business days.',
                                'Ihr originales OEM-Teil wird innerhalb von 1–2 Werktagen aus unserem EU-Lager versendet.',
                                'Jūsų originali OEM dalis išsiųsta iš mūsų ES sandėlio per 1–2 darbo dienas.',
                                'Votre pièce OEM d\'origine est expédiée depuis notre entrepôt UE en 1–2 jours ouvrables.',
                                'Su pieza OEM original se envía desde nuestro almacén UE en 1–2 días hábiles.'
                            ),
                        ],
                    ],
                ],
            ],

            // 5 — Featured brands: top manufacturer grid
            [
                'type'       => 'featured_brands',
                'title'      => 'Featured Brands',
                'sort_order' => 50,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'OEM MANUFACTURERS', 'OEM-HERSTELLER', 'OEM GAMINTOJAI',
                        'FABRICANTS OEM', 'FABRICANTES OEM'
                    ),
                    'headline' => $this->ml('Shop by Brand', 'Nach Marke einkaufen', 'Pirkite pagal prekės ženklą', 'Acheter par marque', 'Comprar por marca'),
                    'view_all_text' => $this->ml(
                        'View All Brands', 'Alle Marken ansehen', 'Peržiūrėti visas markes',
                        'Voir toutes les marques', 'Ver todas las marcas'
                    ),
                    'subheadline' => $this->ml(
                        'Genuine parts sourced directly from OEM manufacturers for guaranteed fitment.',
                        'Originalteile direkt vom OEM-Hersteller für garantierte Passform.',
                        'Originalios dalys tiesiogiai iš OEM gamintojų garantuotam suderinamumui.',
                        'Pièces d\'origine sourcées directement auprès de fabricants OEM.',
                        'Piezas originales obtenidas directamente de fabricantes OEM.'
                    ),
                ],
            ],

            // 6 — Popular searches: most-searched OEM numbers
            [
                'type'       => 'popular_searches',
                'title'      => 'Popular Searches',
                'sort_order' => 60,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'TRENDING NOW', 'AKTUELL BELIEBT', 'POPULIARU DABAR',
                        'EN CE MOMENT', 'TENDENCIAS'
                    ),
                    'headline' => $this->ml('Popular OEM Numbers', 'Beliebte OEM-Nummern', 'Populiarūs OEM numeriai', 'Numéros OEM populaires', 'Números OEM populares'),
                    'subheadline' => $this->ml(
                        'Frequently searched genuine parts',
                        'Häufig gesuchte Originalteile',
                        'Dažniausiai ieškomi originalūs komponentai',
                        'Pièces d\'origine fréquemment recherchées',
                        'Piezas originales frecuentemente buscadas'
                    ),
                    'search_cta_text' => $this->ml(
                        'Search by OEM Number', 'Nach OEM-Nummer suchen', 'Ieškoti pagal OEM numerį',
                        'Rechercher par numéro OEM', 'Buscar por número OEM'
                    ),
                ],
            ],

            // 7 — Testimonials: customer reviews from DB
            [
                'type'       => 'testimonials',
                'title'      => 'Testimonials',
                'sort_order' => 70,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'CUSTOMER REVIEWS', 'KUNDENBEWERTUNGEN', 'KLIENTŲ ATSILIEPIMAI',
                        'AVIS CLIENTS', 'OPINIONES DE CLIENTES'
                    ),
                    'headline' => $this->ml(
                        'What Our Customers Say',
                        'Was unsere Kunden sagen',
                        'Ką sako mūsų klientai',
                        'Ce que disent nos clients',
                        'Lo que dicen nuestros clientes'
                    ),
                    'subheadline' => $this->ml(
                        'Verified feedback from mechanics and individual owners.',
                        'Verifiziertes Feedback von Mechanikern, Flottenmanagern und unabhängigen Werkstätten.',
                        'Patikrintos apžvalgos iš mechanikų, transporto vadybininkų ir autoservisų.',
                        'Retours vérifiés de mécaniciens, gestionnaires de flotte et ateliers indépendants.',
                        'Opiniones verificadas de mecánicos, gestores de flotas y talleres independientes.'
                    ),
                ],
            ],

            // 8 — FAQs: accordion from DB
            [
                'type'       => 'faqs',
                'title'      => 'FAQs',
                'sort_order' => 80,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'SUPPORT', 'SUPPORT', 'PAGALBA', 'ASSISTANCE', 'SOPORTE'
                    ),
                    'headline' => $this->ml(
                        'Frequently Asked Questions',
                        'Häufig gestellte Fragen',
                        'Dažniausiai užduodami klausimai',
                        'Questions fréquemment posées',
                        'Preguntas frecuentes'
                    ),
                    'subheadline' => $this->ml(
                        'Everything you need to know about ordering genuine OEM parts.',
                        'Alles, was Sie über das Bestellen originaler OEM-Teile wissen müssen.',
                        'Viskas, ką reikia žinoti apie originalių OEM dalių užsakymą.',
                        'Tout ce que vous devez savoir sur la commande de pièces OEM d\'origine.',
                        'Todo lo que necesitas saber sobre cómo pedir piezas OEM originales.'
                    ),
                ],
            ],

            // 9 — Newsletter: email subscribe form
            [
                'type'       => 'newsletter',
                'title'      => 'Newsletter',
                'sort_order' => 90,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'STAY INFORMED', 'BLEIBEN SIE INFORMIERT', 'BŪKITE INFORMUOTI',
                        'RESTEZ INFORMÉ', 'MANTENTE INFORMADO'
                    ),
                    'headline' => $this->ml(
                        'Stay Updated on New Parts & Deals',
                        'Bleiben Sie über neue Teile & Angebote informiert',
                        'Gaukite naujienas apie naujas dalis ir pasiūlymus',
                        'Restez informé des nouvelles pièces & offres',
                        'Mantente informado sobre nuevas piezas y ofertas'
                    ),
                    'subheadline' => $this->ml(
                        'Join 10,000+ automotive professionals. No spam, unsubscribe anytime.',
                        'Schließen Sie sich 10.000+ Kfz-Profis an. Kein Spam, jederzeit abmelden.',
                        'Prisijunkite prie 10 000+ automobilių specialistų. Šlamšto nėra, atsisakyti galima bet kada.',
                        'Rejoignez 10 000+ professionnels de l\'automobile. Pas de spam, désinscription à tout moment.',
                        'Únase a más de 10.000 profesionales del automóvil. Sin spam, cancela cuando quieras.'
                    ),
                    'button_text'   => $this->ml('Subscribe', 'Abonnieren', 'Prenumeruoti', 'S\'abonner', 'Suscribirse'),
                    'placeholder'   => $this->ml('Your email address', 'Ihre E-Mail-Adresse', 'Jūsų el. paštas', 'Votre adresse e-mail', 'Tu dirección de correo'),
                    'success_text'  => $this->ml('Thank you! Check your inbox.', 'Danke! Prüfen Sie Ihren Posteingang.', 'Ačiū! Patikrinkite savo el. paštą.', 'Merci ! Vérifiez votre boîte de réception.', '¡Gracias! Revisa tu bandeja de entrada.'),
                ],
            ],

            // 10 — Blog preview: latest 3 posts from DB
            [
                'type'       => 'blog_preview',
                'title'      => 'Blog Preview',
                'sort_order' => 100,
                'is_active'  => false, // off by default until blog posts exist
                'content'    => [
                    'headline' => $this->ml(
                        'From the OEMHub Blog',
                        'Aus dem OEMHub Blog',
                        'Iš OEMHub tinklaraščio',
                        'Du blog OEMHub',
                        'Del blog de OEMHub'
                    ),
                    'view_all_text' => $this->ml('View All Articles', 'Alle Artikel ansehen', 'Peržiūrėti visus straipsnius', 'Voir tous les articles', 'Ver todos los artículos'),
                ],
            ],

            // 11 — Part inquiry CTA: quick find-my-part form
            [
                'type'       => 'part_inquiry',
                'title'      => 'Part Inquiry',
                'sort_order' => 110,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'SOURCING SERVICE', 'BESCHAFFUNGSSERVICE', 'PAIEŠKOS PASLAUGA',
                        'SERVICE DE SOURCING', 'SERVICIO DE BÚSQUEDA'
                    ),
                    'headline' => $this->ml(
                        'Can\'t Find Your Part?',
                        'Teil nicht gefunden?',
                        'Nerandate savo dalies?',
                        'Vous ne trouvez pas votre pièce ?',
                        '¿No encuentras tu pieza?'
                    ),
                    'subheadline' => $this->ml(
                        'Submit a part inquiry and our specialists will source it for you within 24 hours.',
                        'Senden Sie eine Teileanfrage und unsere Spezialisten beschaffen es innerhalb von 24 Stunden.',
                        'Pateikite užklausą ir mūsų specialistai ją surinks per 24 valandas.',
                        'Soumettez une demande de pièce et nos spécialistes la trouveront dans les 24 heures.',
                        'Envíe una consulta de pieza y nuestros especialistas la conseguirán en 24 horas.'
                    ),
                    'button_text' => $this->ml('Submit Inquiry', 'Anfrage senden', 'Pateikti užklausą', 'Soumettre une demande', 'Enviar consulta'),
                ],
            ],

            // 12 — Contact CTA: simple banner linking to /contact
            [
                'type'       => 'contact_cta',
                'title'      => 'Contact CTA',
                'sort_order' => 120,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'GET IN TOUCH', 'KONTAKT AUFNEHMEN', 'SUSISIEKITE',
                        'CONTACTEZ-NOUS', 'PONTE EN CONTACTO'
                    ),
                    'headline' => $this->ml(
                        'Need Help? Talk to an Expert.',
                        'Brauchen Sie Hilfe? Sprechen Sie mit einem Experten.',
                        'Reikia pagalbos? Pasikalbėkite su specialistu.',
                        'Besoin d\'aide ? Parlez à un expert.',
                        '¿Necesitas ayuda? Habla con un experto.'
                    ),
                    'subheadline' => $this->ml(
                        'Our parts specialists are available Monday–Friday, 9:00–18:00 CET.',
                        'Unsere Teilespezialisten sind Montag–Freitag von 9:00–18:00 Uhr MEZ erreichbar.',
                        'Mūsų specialistai dirba pirmadienį–penktadienį, 9:00–18:00 CET.',
                        'Nos spécialistes sont disponibles du lundi au vendredi de 9h à 18h CET.',
                        'Nuestros especialistas están disponibles de lunes a viernes, de 9:00 a 18:00 CET.'
                    ),
                    'button_text' => $this->ml('Contact Us', 'Kontaktieren Sie uns', 'Susisiekite', 'Nous contacter', 'Contáctenos'),
                    'phone'       => $this->ml('', '', '', '', ''),
                ],
            ],

            // 13 — Promotional banner: configurable banner with CTA
            [
                'type'       => 'banner',
                'title'      => 'Promo Banner',
                'sort_order' => 50,
                'is_active'  => true,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'FOR WORKSHOPS', 'FÜR WERKSTÄTTEN', 'DIRBTUVĖMS', 'POUR ATELIERS', 'PARA TALLERES'
                    ),
                    'headline' => $this->ml(
                        'Professional Workshop?',
                        'Professionelle Werkstatt?',
                        'Profesionalios dirbtuvės?',
                        'Atelier professionnel ?',
                        '¿Taller profesional?'
                    ),
                    'subheadline' => $this->ml(
                        'Open a trade account to get wholesale pricing and priority support.',
                        'Eröffnen Sie ein Geschäftskonto für Großhandelspreise und Support.',
                        'Atidarykite verslo sąskaitą didmeninėms kainoms ir palaikymui gauti.',
                        'Ouvrez un compte pro pour les tarifs grossistes et le support prioritaire.',
                        'Abra una cuenta comercial para precios al por mayor y soporte prioritario.'
                    ),
                    'button_text' => $this->ml('Register as Partner', 'Als Partner registrieren', 'Registruotis partneriu', "S'inscrire comme partenaire", 'Registrarse como socio'),
                    'button_url'  => '#register'
                ],
            ],

            // 14 — Shipping info: EU coverage & carriers
            [
                'type'       => 'shipping_info',
                'title'      => 'Shipping Info',
                'sort_order' => 140,
                'content'    => [
                    'eyebrow' => $this->ml(
                        'LOGISTICS', 'LOGISTIK', 'LOGISTIKA', 'LOGISTIQUE', 'LOGÍSTICA'
                    ),
                    'headline' => $this->ml(
                        'Shipping Across the European Union',
                        'Versand in die gesamte Europäische Union',
                        'Pristatymas visoje Europos Sąjungoje',
                        'Livraison dans toute l\'Union européenne',
                        'Envío por toda la Unión Europea'
                    ),
                    'subheadline' => $this->ml(
                        'Fast, tracked delivery via DHL, DPD, GLS and more. Ships across all 27 EU member states.',
                        'Schnelle, verfolgte Lieferung per DHL, DPD, GLS und mehr. Lieferung in alle 27 EU-Mitgliedstaaten.',
                        'Greitas siuntimas su sekimu per DHL, DPD, GLS ir kt. Pristatome į visas 27 ES valstybes nares.',
                        'Livraison rapide et suivie via DHL, DPD, GLS et plus. Expédié dans les 27 États membres de l\'UE.',
                        'Entrega rápida y rastreada por DHL, DPD, GLS y más. Enviamos a los 27 estados miembros de la UE.'
                    ),
                    'carriers' => ['DHL', 'DPD', 'GLS', 'FedEx', 'UPS'],
                    'features' => [
                        [
                            'icon'  => 'truck',
                            'value' => $this->ml('1–3 Days', '1–3 Tage', '1–3 dienos', '1–3 jours', '1–3 días'),
                            'label' => $this->ml('Express Delivery', 'Expresslieferung', 'Greitas pristatymas', 'Livraison express', 'Entrega exprés'),
                        ],
                        [
                            'icon'  => 'globe-europe-africa',
                            'value' => $this->ml('27 Countries', '27 Länder', '27 šalys', '27 pays', '27 países'),
                            'label' => $this->ml('Full EU Coverage', 'Ganz EU abgedeckt', 'Visa ES padengta', 'Toute l\'UE couverte', 'Toda la UE cubierta'),
                        ],
                        [
                            'icon'  => 'map-pin',
                            'value' => $this->ml('100%', '100%', '100%', '100%', '100%'),
                            'label' => $this->ml('Live Tracking', 'Live-Tracking', 'Sekimas', 'Suivi en direct', 'Seguimiento en vivo'),
                        ],
                        [
                            'icon'  => 'arrow-path',
                            'value' => $this->ml('14 Days', '14 Tage', '14 dienų', '14 jours', '14 días'),
                            'label' => $this->ml('Easy Returns', 'Einfache Rückgabe', 'Lengvas grąžinimas', 'Retours faciles', 'Devoluciones fáciles'),
                        ],
                    ],
                ],
            ],
        ];
    }
}
