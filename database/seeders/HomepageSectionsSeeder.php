<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class HomepageSectionsSeeder extends Seeder
{
    /**
     * Homepage sections — 5 languages: EN / DE / LT / FR / ES.
     *
     * IMPORTANT: This is a direct export of the LIVE `sections` table
     * (location=homepage) content, sort_order, and is_active flags — it is
     * meant to always mirror production, NOT to hold aspirational/draft copy.
     * If you edit homepage content or section order via the admin panel,
     * re-export this file in the same pass (see the export script pattern
     * used to generate this version) so the two never diverge again.
     *
     * sort_order also encodes the section rhythm: no two adjacent sections
     * share the same background shade (bg-ivory / bg-ivory-alt / bg-paper /
     * bg-ink), and the 3 bg-ink ("dark") sections — part_inquiry, banner,
     * contact_cta — sit spread apart (positions 70, 100, 120) rather than
     * clustered, so the page never has two dark or two identical-shade
     * sections back to back. See `ReorderHomepageSectionsSeeder` /
     * `sections:reorder-homepage` for the standalone reorder tools kept in
     * sync with this same order.
     */
    private const SECTIONS = [

        // ── HERO (sort_order=10) ──
        [
            'type'  => 'hero',
            'title' => 'Hero',
            'content' => [
                'headline' => [
                    'en' => 'Find Genuine OEM Parts — Fast',
                    'de' => 'Originale OEM-Teile finden — Schnell',
                    'lt' => 'Raskite originalias OEM dalis — Greitai',
                    'fr' => 'Trouvez des pièces OEM d\'origine — Rapidement',
                    'es' => 'Encuentre piezas OEM originales — Rápido',
                ],
                'subheadline' => [
                    'en' => 'Enter your OEM number for guaranteed fitment — genuine parts only, shipped fast from EU warehouses.',
                    'de' => 'OEM-Nummer eingeben für garantierte Passform — nur originale Teile, schneller Versand ab EU-Lager.',
                    'lt' => 'Įveskite OEM numerį garantuotam tikslumui — tik originalios dalys, greitas siuntimas iš ES sandėlių.',
                    'fr' => 'Entrez votre numéro OEM pour un ajustement garanti — pièces d\'origine uniquement, expédiées rapidement depuis nos entrepôts UE.',
                    'es' => 'Introduzca su número OEM para un ajuste garantizado — solo piezas originales, envío rápido desde almacenes UE.',
                ],
                'placeholder' => [
                    'en' => 'Enter OEM number, e.g. 1K0407271F',
                    'de' => 'OEM-Nummer eingeben, z. B. 1K0407271F',
                    'lt' => 'Įveskite OEM numerį, pvz. 1K0407271F',
                    'fr' => 'Entrez le numéro OEM, ex. 1K0407271F',
                    'es' => 'Introduce el número OEM, ej. 1K0407271F',
                ],
                'button_text' => [
                    'en' => 'Find Part',
                    'de' => 'Suchen',
                    'lt' => 'Ieškoti',
                    'fr' => 'Rechercher',
                    'es' => 'Buscar',
                ],
            ],
            'sort_order' => 10,
            'is_active'  => true,
        ],

        // ── TRUST_BAR (sort_order=20) ──
        [
            'type'  => 'trust_bar',
            'title' => 'Trust Bar',
            'content' => [
                'items' => [
                    [
                        'icon' => 'truck',
                        'text' => [
                            'en' => 'Fast, Tracked EU Delivery',
                            'de' => 'Schnelle, verfolgte EU-Lieferung',
                            'lt' => 'Greitas, sekamas pristatymas ES viduje',
                            'fr' => 'Livraison UE rapide et suivie',
                            'es' => 'Entrega rápida y rastreada en la UE',
                        ],
                    ],
                    [
                        'icon' => 'shield-check',
                        'text' => [
                            'en' => 'Genuine OEM Parts Only',
                            'de' => 'Nur originale OEM-Teile',
                            'lt' => 'Tik originalios OEM dalys',
                            'fr' => 'Pièces OEM d\'origine uniquement',
                            'es' => 'Solo piezas OEM originales',
                        ],
                    ],
                    [
                        'icon' => 'arrow-path',
                        'text' => [
                            'en' => '14-Day Returns',
                            'de' => '14-tägiges Rückgaberecht',
                            'lt' => '14 dienų grąžinimas',
                            'fr' => 'Retours sous 14 jours',
                            'es' => 'Devoluciones en 14 días',
                        ],
                    ],
                    [
                        'icon' => 'lock-closed',
                        'text' => [
                            'en' => 'Secure Payment (Card/SEPA)',
                            'de' => 'Sichere Zahlung (Karte/SEPA)',
                            'lt' => 'Saugus mokėjimas (Kortele/SEPA)',
                            'fr' => 'Paiement sécurisé (Carte/SEPA)',
                            'es' => 'Pago seguro (Tarjeta/SEPA)',
                        ],
                    ],
                ],
            ],
            'sort_order' => 20,
            'is_active'  => true,
        ],

        // ── HOW_IT_WORKS (sort_order=30) ──
        [
            'type'  => 'how_it_works',
            'title' => 'How It Works',
            'content' => [
                'eyebrow' => [
                    'en' => 'PROCESS',
                    'de' => 'ABLAUF',
                    'lt' => 'PROCESAS',
                    'fr' => 'PROCESSUS',
                    'es' => 'PROCESO',
                ],
                'headline' => [
                    'en' => 'How It Works',
                    'de' => 'So funktioniert es',
                    'lt' => 'Kaip tai veikia',
                    'fr' => 'Comment ça marche',
                    'es' => 'Cómo funciona',
                ],
                'subheadline' => [
                    'en' => 'Three simple steps to get the right part at the right price.',
                    'de' => 'Drei einfache Schritte zum richtigen Teil zum richtigen Preis.',
                    'lt' => 'Trys paprasti žingsniai norint gauti tinkamą dalį už tinkamą kainą.',
                    'fr' => 'Trois étapes simples pour obtenir la bonne pièce au bon prix.',
                    'es' => 'Tres simples pasos para obtener la pieza correcta al precio correcto.',
                ],
                'steps' => [
                    [
                        'icon' => 'magnifying-glass',
                        'step_number' => 1,
                        'title' => [
                            'en' => 'Search by OEM Number',
                            'de' => 'Nach OEM-Nummer suchen',
                            'lt' => 'Ieškokite pagal OEM numerį',
                            'fr' => 'Cherchez par numéro OEM',
                            'es' => 'Busca por número OEM',
                        ],
                        'description' => [
                            'en' => 'Enter the exact OEM part number from your vehicle manual or old part.',
                            'de' => 'Geben Sie die genaue OEM-Teilenummer aus Ihrem Fahrzeughandbuch ein.',
                            'lt' => 'Įveskite tikslų OEM dalies numerį iš jūsų automobilio vadovo.',
                            'fr' => 'Entrez le numéro de pièce OEM exact de votre manuel de véhicule.',
                            'es' => 'Introduce el número exacto de pieza OEM de tu manual de vehículo.',
                        ],
                    ],
                    [
                        'icon' => 'shopping-cart',
                        'step_number' => 2,
                        'title' => [
                            'en' => 'Compare & Order',
                            'de' => 'Vergleichen & Bestellen',
                            'lt' => 'Lyginkite ir užsakykite',
                            'fr' => 'Comparez & Commandez',
                            'es' => 'Compara y Pide',
                        ],
                        'description' => [
                            'en' => 'See real-time stock availability and condition (Grade A/B/C). Add to cart securely.',
                            'de' => 'Sieh dir Echtzeit-Verfügbarkeit an. Sicher in den Warenkorb und Kasse.',
                            'lt' => 'Matykite realaus laiko likučius. Saugiai atsiskaitykite.',
                            'fr' => 'Voir dispo en temps réel. Ajoutez au panier et payez en toute sécurité.',
                            'es' => 'Ver stock en tiempo real. Añade al carrito y paga con seguridad.',
                        ],
                    ],
                    [
                        'icon' => 'truck',
                        'step_number' => 3,
                        'title' => [
                            'en' => 'Fast EU Delivery',
                            'de' => 'Schnelle EU-Lieferung',
                            'lt' => 'Greitas pristatymas ES',
                            'fr' => 'Livraison UE rapide',
                            'es' => 'Entrega rápida en UE',
                        ],
                        'description' => [
                            'en' => 'Your genuine OEM part ships from our EU warehouse within 24 hours.',
                            'de' => 'Ihr originales OEM-Teil wird innerhalb von 24 Stunden aus unserem EU-Lager versendet.',
                            'lt' => 'Jūsų originali OEM dalis išsiųsta iš mūsų ES sandėlio per 24 valandas.',
                            'fr' => 'Votre pièce OEM d\'origine est expédiée depuis notre entrepôt UE en 24 heures.',
                            'es' => 'Su pieza OEM original se envía desde nuestro almacén UE en 24 horas.',
                        ],
                    ],
                ],
            ],
            'sort_order' => 30,
            'is_active'  => true,
        ],

        // ── STATS_COUNTER (sort_order=40) ──
        [
            'type'  => 'stats_counter',
            'title' => 'Stats Counter',
            'content' => [
                'eyebrow' => [
                    'en' => 'BY THE NUMBERS',
                    'de' => 'IN ZAHLEN',
                    'lt' => 'SKAIČIAIS',
                    'fr' => 'EN CHIFFRES',
                    'es' => 'EN CIFRAS',
                ],
                'headline' => [
                    'en' => 'Trusted Across Europe',
                    'de' => 'Europaweit vertraut',
                    'lt' => 'Pasitikima visoje Europoje',
                    'fr' => 'La confiance à travers l\'Europe',
                    'es' => 'La confianza en toda Europa',
                ],
                'subheadline' => [
                    'en' => 'Real numbers from car owners and workshops who order with us every day.',
                    'de' => 'Echte Zahlen von Autobesitzern und Werkstätten, die täglich bei uns bestellen.',
                    'lt' => 'Realūs skaičiai iš automobilių savininkų ir dirbtuvių, kurie užsako pas mus kasdien.',
                    'fr' => 'Des chiffres réels venant de propriétaires de véhicules et d\'ateliers qui commandent chez nous chaque jour.',
                    'es' => 'Cifras reales de propietarios de vehículos y talleres que piden con nosotros cada día.',
                ],
                'items' => [
                    [
                        'key' => 'customers_count',
                        'suffix' => '+',
                        'label' => [
                            'en' => 'Customers',
                            'de' => 'Kunden',
                            'lt' => 'Klientai',
                            'fr' => 'Clients',
                            'es' => 'Clientes',
                        ],
                    ],
                    [
                        'key' => 'parts_count',
                        'suffix' => '+',
                        'label' => [
                            'en' => 'OEM Numbers Sourced',
                            'de' => 'Beschaffte OEM-Nummern',
                            'lt' => 'Surastų OEM numerių',
                            'fr' => 'Références OEM sourcées',
                            'es' => 'Números OEM abastecidos',
                        ],
                    ],
                    [
                        'key' => 'countries_count',
                        'suffix' => '',
                        'label' => [
                            'en' => 'EU Countries',
                            'de' => 'EU-Länder',
                            'lt' => 'ES šalys',
                            'fr' => 'Pays UE',
                            'es' => 'Países UE',
                        ],
                    ],
                    [
                        'key' => 'rating',
                        'suffix' => '',
                        'label' => [
                            'en' => 'Customer Rating',
                            'de' => 'Bewertung',
                            'lt' => 'Įvertinimas',
                            'fr' => 'Évaluation',
                            'es' => 'Calificación',
                        ],
                    ],
                ],
            ],
            'sort_order' => 40,
            'is_active'  => true,
        ],

        // ── POPULAR_SEARCHES (sort_order=50) ──
        [
            'type'  => 'popular_searches',
            'title' => 'Popular Searches',
            'content' => [
                'eyebrow' => [
                    'en' => 'TRENDING NOW',
                    'de' => 'AKTUELL BELIEBT',
                    'lt' => 'POPULIARU DABAR',
                    'fr' => 'EN CE MOMENT',
                    'es' => 'TENDENCIAS',
                ],
                'headline' => [
                    'en' => 'Popular OEM Numbers',
                    'de' => 'Beliebte OEM-Nummern',
                    'lt' => 'Populiarūs OEM numeriai',
                    'fr' => 'Numéros OEM populaires',
                    'es' => 'Números OEM populares',
                ],
                'subheadline' => [
                    'en' => 'Real search activity from drivers across Europe — updated live.',
                    'de' => 'Echte Suchaktivität von Autofahrern in ganz Europa — live aktualisiert.',
                    'lt' => 'Tikra vairuotojų paieškos veikla visoje Europoje — atnaujinama gyvai.',
                    'fr' => 'Activité de recherche réelle des automobilistes à travers l\'Europe — mise à jour en direct.',
                    'es' => 'Actividad de búsqueda real de conductores en toda Europa — actualizada en vivo.',
                ],
                'search_cta_text' => [
                    'en' => 'Search by OEM Number',
                    'de' => 'Nach OEM-Nummer suchen',
                    'lt' => 'Ieškoti pagal OEM numerį',
                    'fr' => 'Rechercher par numéro OEM',
                    'es' => 'Buscar por número OEM',
                ],
            ],
            'sort_order' => 50,
            'is_active'  => true,
        ],

        // ── FEATURED_BRANDS (sort_order=60) ──
        [
            'type'  => 'featured_brands',
            'title' => 'Featured Brands',
            'content' => [
                'eyebrow' => [
                    'en' => 'OEM MANUFACTURERS',
                    'de' => 'OEM-HERSTELLER',
                    'lt' => 'OEM GAMINTOJAI',
                    'fr' => 'FABRICANTS OEM',
                    'es' => 'FABRICANTES OEM',
                ],
                'headline' => [
                    'en' => 'Genuine Parts by Manufacturer',
                    'de' => 'Originalteile nach Hersteller',
                    'lt' => 'Originalios dalys pagal gamintoją',
                    'fr' => 'Pièces d\'origine par fabricant',
                    'es' => 'Piezas originales por fabricante',
                ],
                'view_all_text' => [
                    'en' => 'View All Brands',
                    'de' => 'Alle Marken ansehen',
                    'lt' => 'Peržiūrėti visas markes',
                    'fr' => 'Voir toutes les marques',
                    'es' => 'Ver todas las marcas',
                ],
                'subheadline' => [
                    'en' => 'Genuine parts sourced from verified EU distributors — guaranteed fitment for your vehicle.',
                    'de' => 'Originalteile von geprüften EU-Distributoren — garantierte Passform für Ihr Fahrzeug.',
                    'lt' => 'Originalios dalys iš patikrintų ES platintojų — garantuotas tikslumas jūsų automobiliui.',
                    'fr' => 'Pièces d\'origine issues de distributeurs UE vérifiés — ajustement garanti pour votre véhicule.',
                    'es' => 'Piezas originales de distribuidores UE verificados — ajuste garantizado para su vehículo.',
                ],
            ],
            'sort_order' => 60,
            'is_active'  => true,
        ],

        // ── PART_INQUIRY (sort_order=70) ──
        [
            'type'  => 'part_inquiry',
            'title' => 'Part Inquiry',
            'content' => [
                'eyebrow' => [
                    'en' => 'SOURCING SERVICE',
                    'de' => 'BESCHAFFUNGSSERVICE',
                    'lt' => 'PAIEŠKOS PASLAUGA',
                    'fr' => 'SERVICE DE SOURCING',
                    'es' => 'SERVICIO DE BÚSQUEDA',
                ],
                'headline' => [
                    'en' => 'Can\'t Find Your Part?',
                    'de' => 'Teil nicht gefunden?',
                    'lt' => 'Nerandate savo dalies?',
                    'fr' => 'Vous ne trouvez pas votre pièce ?',
                    'es' => '¿No encuentras tu pieza?',
                ],
                'subheadline' => [
                    'en' => 'Submit a part inquiry and our specialists will source it for you within 24 hours.',
                    'de' => 'Senden Sie eine Teileanfrage und unsere Spezialisten beschaffen es innerhalb von 24 Stunden.',
                    'lt' => 'Pateikite užklausą ir mūsų specialistai ją surinks per 24 valandas.',
                    'fr' => 'Soumettez une demande de pièce et nos spécialistes la trouveront dans les 24 heures.',
                    'es' => 'Envíe una consulta de pieza y nuestros especialistas la conseguirán en 24 horas.',
                ],
                'button_text' => [
                    'en' => 'Submit Inquiry',
                    'de' => 'Anfrage senden',
                    'lt' => 'Pateikti užklausą',
                    'fr' => 'Soumettre une demande',
                    'es' => 'Enviar consulta',
                ],
            ],
            'sort_order' => 70,
            'is_active'  => true,
        ],

        // ── TESTIMONIALS (sort_order=80) ──
        [
            'type'  => 'testimonials',
            'title' => 'Testimonials',
            'content' => [
                'eyebrow' => [
                    'en' => 'CUSTOMER REVIEWS',
                    'de' => 'KUNDENBEWERTUNGEN',
                    'lt' => 'KLIENTŲ ATSILIEPIMAI',
                    'fr' => 'AVIS CLIENTS',
                    'es' => 'OPINIONES DE CLIENTES',
                ],
                'headline' => [
                    'en' => 'What Our Customers Say',
                    'de' => 'Was unsere Kunden sagen',
                    'lt' => 'Ką sako mūsų klientai',
                    'fr' => 'Ce que disent nos clients',
                    'es' => 'Lo que dicen nuestros clientes',
                ],
                'subheadline' => [
                    'en' => 'Verified feedback from car owners, mechanics, and fleet managers across Europe.',
                    'de' => 'Verifiziertes Feedback von Autobesitzern, Mechanikern und Flottenmanagern in ganz Europa.',
                    'lt' => 'Patikrintos apžvalgos iš automobilių savininkų, mechanikų ir transporto vadybininkų visoje Europoje.',
                    'fr' => 'Retours vérifiés de propriétaires de véhicules, mécaniciens et gestionnaires de flotte à travers l\'Europe.',
                    'es' => 'Opiniones verificadas de propietarios de vehículos, mecánicos y gestores de flotas en toda Europa.',
                ],
            ],
            'sort_order' => 80,
            'is_active'  => true,
        ],

        // ── SHIPPING_INFO (sort_order=90) ──
        [
            'type'  => 'shipping_info',
            'title' => 'Shipping Info',
            'content' => [
                'eyebrow' => [
                    'en' => 'LOGISTICS',
                    'de' => 'LOGISTIK',
                    'lt' => 'LOGISTIKA',
                    'fr' => 'LOGISTIQUE',
                    'es' => 'LOGÍSTICA',
                ],
                'headline' => [
                    'en' => 'Shipping Across the European Union',
                    'de' => 'Versand in die gesamte Europäische Union',
                    'lt' => 'Pristatymas visoje Europos Sąjungoje',
                    'fr' => 'Livraison dans toute l\'Union européenne',
                    'es' => 'Envío por toda la Unión Europea',
                ],
                'subheadline' => [
                    'en' => 'Fast, tracked delivery via DHL, DPD, GLS and more. Ships across all 27 EU member states.',
                    'de' => 'Schnelle, verfolgte Lieferung per DHL, DPD, GLS und mehr. Lieferung in alle 27 EU-Mitgliedstaaten.',
                    'lt' => 'Greitas siuntimas su sekimu per DHL, DPD, GLS ir kt. Pristatome į visas 27 ES valstybes nares.',
                    'fr' => 'Livraison rapide et suivie via DHL, DPD, GLS et plus. Expédié dans les 27 États membres de l\'UE.',
                    'es' => 'Entrega rápida y rastreada por DHL, DPD, GLS y más. Enviamos a los 27 estados miembros de la UE.',
                ],
                'carriers' => [
                    'DHL',
                    'DPD',
                    'GLS',
                    'FedEx',
                    'UPS',
                ],
                'features' => [
                    [
                        'icon' => 'truck',
                        'value' => [
                            'en' => '3–5 Days',
                            'de' => '3–5 Tage',
                            'lt' => '3–5 dienos',
                            'fr' => '3–5 jours',
                            'es' => '3–5 días',
                        ],
                        'label' => [
                            'en' => 'Express Delivery',
                            'de' => 'Expresslieferung',
                            'lt' => 'Greitas pristatymas',
                            'fr' => 'Livraison express',
                            'es' => 'Entrega exprés',
                        ],
                    ],
                    [
                        'icon' => 'globe-europe-africa',
                        'value' => [
                            'en' => '27 Countries',
                            'de' => '27 Länder',
                            'lt' => '27 šalys',
                            'fr' => '27 pays',
                            'es' => '27 países',
                        ],
                        'label' => [
                            'en' => 'Full EU Coverage',
                            'de' => 'Ganz EU abgedeckt',
                            'lt' => 'Visa ES padengta',
                            'fr' => 'Toute l\'UE couverte',
                            'es' => 'Toda la UE cubierta',
                        ],
                    ],
                    [
                        'icon' => 'map-pin',
                        'value' => [
                            'en' => '100%',
                            'de' => '100%',
                            'lt' => '100%',
                            'fr' => '100%',
                            'es' => '100%',
                        ],
                        'label' => [
                            'en' => 'Live Tracking',
                            'de' => 'Live-Tracking',
                            'lt' => 'Sekimas',
                            'fr' => 'Suivi en direct',
                            'es' => 'Seguimiento en vivo',
                        ],
                    ],
                    [
                        'icon' => 'arrow-path',
                        'value' => [
                            'en' => '14 Days',
                            'de' => '14 Tage',
                            'lt' => '14 dienų',
                            'fr' => '14 jours',
                            'es' => '14 días',
                        ],
                        'label' => [
                            'en' => 'Easy Returns',
                            'de' => 'Einfache Rückgabe',
                            'lt' => 'Lengvas grąžinimas',
                            'fr' => 'Retours faciles',
                            'es' => 'Devoluciones fáciles',
                        ],
                    ],
                ],
            ],
            'sort_order' => 90,
            'is_active'  => true,
        ],

        // ── BANNER (sort_order=100) ──
        [
            'type'  => 'banner',
            'title' => 'Promo Banner',
            'content' => [
                'eyebrow' => [
                    'en' => 'FOR WORKSHOPS',
                    'de' => 'FÜR WERKSTÄTTEN',
                    'lt' => 'DIRBTUVĖMS',
                    'fr' => 'POUR ATELIERS',
                    'es' => 'PARA TALLERES',
                ],
                'headline' => [
                    'en' => 'Professional Workshop?',
                    'de' => 'Professionelle Werkstatt?',
                    'lt' => 'Profesionalios dirbtuvės?',
                    'fr' => 'Atelier professionnel ?',
                    'es' => '¿Taller profesional?',
                ],
                'subheadline' => [
                    'en' => 'Open a trade account to get wholesale pricing and priority support.',
                    'de' => 'Eröffnen Sie ein Geschäftskonto für Großhandelspreise und Support.',
                    'lt' => 'Atidarykite verslo sąskaitą didmeninėms kainoms ir palaikymui gauti.',
                    'fr' => 'Ouvrez un compte pro pour les tarifs grossistes et le support prioritaire.',
                    'es' => 'Abra una cuenta comercial para precios al por mayor y soporte prioritario.',
                ],
                'button_text' => [
                    'en' => 'Register as Partner',
                    'de' => 'Als Partner registrieren',
                    'lt' => 'Registruotis partneriu',
                    'fr' => 'S\'inscrire comme partenaire',
                    'es' => 'Registrarse como socio',
                ],
                'button_url' => '',
                'features' => [
                    [
                        'icon' => 'wrench-screwdriver',
                        'title' => [
                            'en' => 'Workshop Pricing',
                            'de' => 'Werkstattpreise',
                            'lt' => 'Dirbtuvių kainos',
                            'fr' => 'Tarifs Atelier',
                            'es' => 'Precios de Taller',
                        ],
                        'desc' => [
                            'en' => 'Volume-based pricing tiers — automatic on every invoice once you qualify.',
                            'de' => 'Mengenbasierte Preisstaffeln — automatisch auf jeder Rechnung, sobald Sie qualifiziert sind.',
                            'lt' => 'Kiekiu pagrįsti kainų lygiai — automatiškai kiekvienoje sąskaitoje, kai atitinkate reikalavimus.',
                            'fr' => 'Paliers tarifaires basés sur le volume — appliqués automatiquement dès que vous êtes éligible.',
                            'es' => 'Escalas de precios por volumen — automático en cada factura una vez que califique.',
                        ],
                    ],
                    [
                        'icon' => 'document-text',
                        'title' => [
                            'en' => 'Net-30 Terms',
                            'de' => 'Zahlungsziel Netto-30',
                            'lt' => '30 dienų atidėjimas',
                            'fr' => 'Paiement à 30 jours',
                            'es' => 'Pago a 30 días',
                        ],
                        'desc' => [
                            'en' => 'Order on account, pay monthly. Credit lines from :low to :high based on history.',
                            'de' => 'Auf Rechnung bestellen, monatlich zahlen. Kreditrahmen von :low bis :high je nach Historie.',
                            'lt' => 'Užsakykite sąskaita, mokėkite kas mėnesį. Kredito linija nuo :low iki :high pagal istoriją.',
                            'fr' => 'Commandez sur compte, payez mensuellement. Ligne de crédit de :low à :high selon votre historique.',
                            'es' => 'Pida a cuenta, pague mensualmente. Línea de crédito desde :low hasta :high según su historial.',
                        ],
                    ],
                    [
                        'icon' => 'clipboard-check',
                        'title' => [
                            'en' => 'Bulk RFQ Desk',
                            'de' => 'Sammelanfrage-Desk',
                            'lt' => 'Didmeninių užklausų skyrius',
                            'fr' => 'Bureau RFQ en gros',
                            'es' => 'Mesa de solicitudes al por mayor',
                        ],
                        'desc' => [
                            'en' => 'Quote 50+ OEM numbers in one request. Answers within 4 working hours.',
                            'de' => 'Fordern Sie 50+ OEM-Nummern in einer Anfrage an. Antwort innerhalb von 4 Werkstunden.',
                            'lt' => 'Užklauskite 50+ OEM numerių viena užklausa. Atsakymas per 4 darbo valandas.',
                            'fr' => 'Demandez un devis pour 50+ références OEM en une seule requête. Réponse sous 4 heures ouvrées.',
                            'es' => 'Solicite presupuesto de 50+ números OEM en una sola petición. Respuesta en 4 horas laborables.',
                        ],
                    ],
                    [
                        'icon' => 'chat-bubble',
                        'title' => [
                            'en' => 'Dedicated B2B Support',
                            'de' => 'Persönlicher B2B-Support',
                            'lt' => 'Asmeninis B2B palaikymas',
                            'fr' => 'Support B2B Dédié',
                            'es' => 'Soporte B2B Dedicado',
                        ],
                        'desc' => [
                            'en' => 'Named account manager, direct line, DE · EN · FR · LT · ES.',
                            'de' => 'Persönlicher Kundenbetreuer, Direktdurchwahl, DE · EN · FR · LT · ES.',
                            'lt' => 'Asmeninis paskyros vadybininkas, tiesioginė linija, DE · EN · FR · LT · ES.',
                            'fr' => 'Gestionnaire de compte dédié, ligne directe, DE · EN · FR · LT · ES.',
                            'es' => 'Gestor de cuenta personal, línea directa, DE · EN · FR · LT · ES.',
                        ],
                    ],
                    [
                        'icon' => 'truck',
                        'title' => [
                            'en' => 'Scheduled Delivery',
                            'de' => 'Planmäßige Lieferung',
                            'lt' => 'Planinis pristatymas',
                            'fr' => 'Livraison Planifiée',
                            'es' => 'Entrega Programada',
                        ],
                        'desc' => [
                            'en' => 'Daily courier runs across the EU. Morning-order, next-day-arrival on stocked SKUs.',
                            'de' => 'Tägliche Kurierfahrten in der gesamten EU. Bei Bestellung am Vormittag Ankunft am nächsten Tag bei Lagerware.',
                            'lt' => 'Kasdieniai kurjerio reisai visoje ES. Užsakius iki pietų — pristatymas kitą dieną, jei prekė sandėlyje.',
                            'fr' => 'Tournées de coursier quotidiennes dans toute l\'UE. Commande le matin, livraison le lendemain pour les articles en stock.',
                            'es' => 'Rutas de mensajería diarias en toda la UE. Pedido por la mañana, entrega al día siguiente en artículos con stock.',
                        ],
                    ],
                    [
                        'icon' => 'shield-check',
                        'title' => [
                            'en' => 'Certified Genuine',
                            'de' => 'Zertifiziert Original',
                            'lt' => 'Sertifikuota originalu',
                            'fr' => 'Authenticité Certifiée',
                            'es' => 'Autenticidad Certificada',
                        ],
                        'desc' => [
                            'en' => 'Only OEM-authorised distributors. ISO 9001 supply chain, traceable lot numbers.',
                            'de' => 'Nur OEM-autorisierte Distributoren. ISO-9001-Lieferkette, rückverfolgbare Chargennummern.',
                            'lt' => 'Tik OEM autorizuoti platintojai. ISO 9001 tiekimo grandinė, atsekami partijos numeriai.',
                            'fr' => 'Uniquement des distributeurs agréés OEM. Chaîne d\'approvisionnement ISO 9001, numéros de lot traçables.',
                            'es' => 'Solo distribuidores autorizados OEM. Cadena de suministro ISO 9001, números de lote trazables.',
                        ],
                    ],
                ],
            ],
            'sort_order' => 100,
            'is_active'  => true,
        ],

        // ── FAQS (sort_order=110) ──
        [
            'type'  => 'faqs',
            'title' => 'FAQs',
            'content' => [
                'eyebrow' => [
                    'en' => 'SUPPORT',
                    'de' => 'SUPPORT',
                    'lt' => 'PAGALBA',
                    'fr' => 'ASSISTANCE',
                    'es' => 'SOPORTE',
                ],
                'headline' => [
                    'en' => 'Frequently Asked Questions',
                    'de' => 'Häufig gestellte Fragen',
                    'lt' => 'Dažniausiai užduodami klausimai',
                    'fr' => 'Questions fréquemment posées',
                    'es' => 'Preguntas frecuentes',
                ],
                'subheadline' => [
                    'en' => 'Everything you need to know about ordering genuine OEM parts.',
                    'de' => 'Alles, was Sie über das Bestellen originaler OEM-Teile wissen müssen.',
                    'lt' => 'Viskas, ką reikia žinoti apie originalių OEM dalių užsakymą.',
                    'fr' => 'Tout ce que vous devez savoir sur la commande de pièces OEM d\'origine.',
                    'es' => 'Todo lo que necesitas saber sobre cómo pedir piezas OEM originales.',
                ],
            ],
            'sort_order' => 110,
            'is_active'  => true,
        ],

        // ── CONTACT_CTA (sort_order=120) ──
        [
            'type'  => 'contact_cta',
            'title' => 'Contact CTA',
            'content' => [
                'eyebrow' => [
                    'en' => 'GET IN TOUCH',
                    'de' => 'KONTAKT AUFNEHMEN',
                    'lt' => 'SUSISIEKITE',
                    'fr' => 'CONTACTEZ-NOUS',
                    'es' => 'PONTE EN CONTACTO',
                ],
                'headline' => [
                    'en' => 'Need Help? Talk to an Expert.',
                    'de' => 'Brauchen Sie Hilfe? Sprechen Sie mit einem Experten.',
                    'lt' => 'Reikia pagalbos? Pasikalbėkite su specialistu.',
                    'fr' => 'Besoin d\'aide ? Parlez à un expert.',
                    'es' => '¿Necesitas ayuda? Habla con un experto.',
                ],
                'subheadline' => [
                    'en' => 'Our parts specialists are available Monday–Friday, 9:00–18:00 CET.',
                    'de' => 'Unsere Teilespezialisten sind Montag–Freitag von 9:00–18:00 Uhr MEZ erreichbar.',
                    'lt' => 'Mūsų specialistai dirba pirmadienį–penktadienį, 9:00–18:00 CET.',
                    'fr' => 'Nos spécialistes sont disponibles du lundi au vendredi de 9h à 18h CET.',
                    'es' => 'Nuestros especialistas están disponibles de lunes a viernes, de 9:00 a 18:00 CET.',
                ],
                'button_text' => [
                    'en' => 'Contact Us',
                    'de' => 'Kontaktieren Sie uns',
                    'lt' => 'Susisiekite',
                    'fr' => 'Nous contacter',
                    'es' => 'Contáctenos',
                ],
            ],
            'sort_order' => 120,
            'is_active'  => true,
        ],

        // ── NEWSLETTER (sort_order=130) ──
        [
            'type'  => 'newsletter',
            'title' => 'Newsletter',
            'content' => [
                'eyebrow' => [
                    'en' => 'STAY INFORMED',
                    'de' => 'BLEIBEN SIE INFORMIERT',
                    'lt' => 'BŪKITE INFORMUOTI',
                    'fr' => 'RESTEZ INFORMÉ',
                    'es' => 'MANTENTE INFORMADO',
                ],
                'headline' => [
                    'en' => 'Stay Updated on New Parts & Deals',
                    'de' => 'Bleiben Sie über neue Teile & Angebote informiert',
                    'lt' => 'Gaukite naujienas apie naujas dalis ir pasiūlymus',
                    'fr' => 'Restez informé des nouvelles pièces & offres',
                    'es' => 'Mantente informado sobre nuevas piezas y ofertas',
                ],
                'subheadline' => [
                    'en' => 'Join :count+ verified customers across Europe. No spam, unsubscribe anytime.',
                    'de' => 'Schließen Sie sich :count+ verifizierten Kunden in ganz Europa an. Kein Spam, jederzeit abmelden.',
                    'lt' => 'Prisijunkite prie :count+ patikrintų klientų visoje Europoje. Šlamšto nėra, atsisakyti galima bet kada.',
                    'fr' => 'Rejoignez :count+ clients vérifiés à travers l\'Europe. Pas de spam, désinscription à tout moment.',
                    'es' => 'Únete a :count+ clientes verificados en toda Europa. Sin spam, cancela cuando quieras.',
                ],
                'button_text' => [
                    'en' => 'Subscribe',
                    'de' => 'Abonnieren',
                    'lt' => 'Prenumeruoti',
                    'fr' => 'S\'abonner',
                    'es' => 'Suscribirse',
                ],
                'placeholder' => [
                    'en' => 'Your email address',
                    'de' => 'Ihre E-Mail-Adresse',
                    'lt' => 'Jūsų el. paštas',
                    'fr' => 'Votre adresse e-mail',
                    'es' => 'Tu dirección de correo',
                ],
                'success_text' => [
                    'en' => 'Thank you! Check your inbox.',
                    'de' => 'Danke! Prüfen Sie Ihren Posteingang.',
                    'lt' => 'Ačiū! Patikrinkite savo el. paštą.',
                    'fr' => 'Merci ! Vérifiez votre boîte de réception.',
                    'es' => '¡Gracias! Revisa tu bandeja de entrada.',
                ],
            ],
            'sort_order' => 130,
            'is_active'  => true,
        ],

        // ── BLOG_PREVIEW (sort_order=140) ──
        [
            'type'  => 'blog_preview',
            'title' => 'Blog Preview',
            'content' => [
                'headline' => [
                    'en' => 'From the OeParts Blog',
                    'de' => 'Aus dem OeParts Blog',
                    'lt' => 'Iš OeParts tinklaraščio',
                    'fr' => 'Du blog OeParts',
                    'es' => 'Del blog de OeParts',
                ],
                'view_all_text' => [
                    'en' => 'View All Articles',
                    'de' => 'Alle Artikel ansehen',
                    'lt' => 'Peržiūrėti visus straipsnius',
                    'fr' => 'Voir tous les articles',
                    'es' => 'Ver todos los artículos',
                ],
            ],
            'sort_order' => 140,
            'is_active'  => true,
        ],
    ];

    public function run(): void
    {
        echo "Seeding homepage sections (synced from live content)...\n\n";

        foreach (self::SECTIONS as $sectionData) {
            Section::updateOrCreate(
                ['type' => $sectionData['type'], 'location' => 'homepage'],
                [
                    'title'      => $sectionData['title'],
                    'content'    => $sectionData['content'],
                    'sort_order' => $sectionData['sort_order'],
                    'is_active'  => $sectionData['is_active'],
                ]
            );
            echo "  ✓ {$sectionData['type']}\n";
        }

        echo "\n✅ Done — " . count(self::SECTIONS) . " sections seeded.\n";
    }
}
