<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class HomepageSectionsSeeder extends Seeder
{
    /**
     * Homepage sections — Google SEO 2026.
     * E-E-A-T, AI Overviews, People Also Ask, Helpful Content Update.
     * 5 languages: EN / DE / LT / FR / ES.
     *
     * Capitalisation rules enforced throughout:
     *   OEM · EU · VIN · VAT · B2B · SEPA · PCI-DSS · DHL · DPD · GLS · FedEx · UPS
     *   Grade A · Grade B · Grade C · Monday–Friday (en-dash)
     *   Brand names: BMW · Volkswagen · Audi · Mercedes-Benz · Skoda · SEAT · Porsche
     *   Eyebrow labels: ALL CAPS
     */
    private const SECTIONS = [

        // ──────────────────────────────────────────────────────────────────────
        // 1. HERO
        //    Primary keyword: "genuine OEM auto parts"
        //    Intent: transactional — buyer knows the OEM number, wants to buy
        //    Headline: direct benefit, includes keyword naturally
        //    Subheadline: three concrete USPs — catalogue size, trust, delivery
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'hero',
            'title' => [
                'en' => 'Find Genuine OEM Auto Parts by Part Number — OEMHub',
                'de' => 'Originale OEM-Autoteile nach Teilenummer finden — OEMHub',
                'lt' => 'Raskite originalias OEM automobilių dalis pagal dalies numerį',
                'fr' => 'Trouvez des pièces auto OEM d\'origine par numéro — OEMHub',
                'es' => 'Encuentre piezas de auto OEM genuinas por número de pieza',
            ],
            'content' => [
                'headline' => [
                    'en' => 'Genuine OEM Parts',
                    'de' => 'Original OEM-Teile',
                    'lt' => 'Originalios OEM dalys',
                    'fr' => 'Pièces OEM d\'origine',
                    'es' => 'Piezas OEM genuinas',
                ],
                'subheadline' => [
                    'en' => 'Save up to 40% off dealer prices. Enter your OEM number for a guaranteed perfect fit for your car.',
                    'de' => 'Bis zu 40% sparen. OEM-Nummer eingeben für garantierte Passform.',
                    'lt' => 'Sutaupykite iki 40%. Įveskite OEM numerį garantuotam tikslumui.',
                    'fr' => 'Économisez jusqu\'à 40 %. Entrez votre numéro OEM pour un ajustement garanti.',
                    'es' => 'Ahorre hasta un 40%. Ingrese su número OEM para un ajuste garantizado.',
                ],
                'placeholder' => [
                    'en' => 'Enter OEM number, e.g. 1K0407271F',
                    'de' => 'OEM-Nummer, z. B. 1K0407271F',
                    'lt' => 'OEM numeris, pvz. 1K0407271F',
                    'fr' => 'Numéro OEM, ex. 1K0407271F',
                    'es' => 'Número OEM, ej. 1K0407271F',
                ],
                'button_text' => [
                    'en' => 'Find Your Part',
                    'de' => 'Teil Finden',
                    'lt' => 'Rasti Dalį',
                    'fr' => 'Trouver Votre Pièce',
                    'es' => 'Encontrar Su Pieza',
                ],
                'popular_oem' => [
                    '06L906036L',   // VW/Audi fuel injector — very high search volume
                    '12137588837',  // BMW spark plug — popular
                    'A2769060000',  // Mercedes-Benz throttle body
                    '0280158837',   // Bosch/multi-brand MAF sensor
                    '5Q0407271R',   // VW Passat driveshaft
                    '3C0615301B',   // VW brake disc
                ],
            ],
            'sort_order' => 10,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 2. TRUST BAR
        //    Four trust pillars — specific, verifiable, customer-focused.
        //    Each item is a concrete claim, not a vague benefit.
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'trust_bar',
            'title' => [
                'en' => 'Why Buyers Choose OEMHub',
                'de' => 'Warum Käufer OEMHub wählen',
                'lt' => 'Kodėl pirkėjai renkasi OEMHub',
                'fr' => 'Pourquoi les acheteurs choisissent OEMHub',
                'es' => 'Por qué los compradores eligen OEMHub',
            ],
            'content' => [
                'items' => [
                    [
                        'icon' => 'truck',
                        'text' => [
                            'en' => 'Fast, Tracked EU Delivery',
                            'de' => '1-5 Tage verfolgte Lieferung',
                            'lt' => '1-5 dienų sekamas pristatymas',
                            'fr' => 'Livraison suivie 1-5 jours',
                            'es' => 'Entrega rastreada 1-5 días',
                        ],
                    ],
                    [
                        'icon' => 'shield-check',
                        'text' => [
                            'en' => '100% Genuine OEM Only',
                            'de' => '100 % Original OEM',
                            'lt' => '100 % originalus OEM',
                            'fr' => '100 % OEM d\'origine',
                            'es' => '100 % OEM genuino',
                        ],
                    ],
                    [
                        'icon' => 'arrow-path',
                        'text' => [
                            'en' => '14-Day Free Returns',
                            'de' => '14 Tage kostenlose Rückgabe',
                            'lt' => 'Nemokamas grąžinimas per 14 dienų',
                            'fr' => 'Retours gratuits sous 14 jours',
                            'es' => 'Devoluciones gratis en 14 días',
                        ],
                    ],
                    [
                        'icon' => 'lock-closed',
                        'text' => [
                            'en' => 'Secure Payment (Cards, Apple/Google Pay)',
                            'de' => 'Sichere Zahlungen (Karte/SEPA)',
                            'lt' => 'Saugūs mokėjimai (Kortele/SEPA)',
                            'fr' => 'Paiements sécurisés (Carte/SEPA)',
                            'es' => 'Pagos seguros (Tarjeta/SEPA)',
                        ],
                    ],
                ],
            ],
            'sort_order' => 140,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 3. STATS COUNTER
        //    Specific, credible numbers — authority through precision.
        //    Labels explain what the number means, not just what it is.
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'stats_counter',
            'title' => [
                'en' => 'OEMHub by the Numbers',
                'de' => 'OEMHub in Zahlen',
                'lt' => 'OEMHub skaičiais',
                'fr' => 'OEMHub en chiffres',
                'es' => 'OEMHub en cifras',
            ],
            'content' => [
                'items' => [
                    [
                        'key'    => 'parts_count',
                        'suffix' => '+',
                        'label'  => [
                            'en' => 'Genuine OEM Parts',
                            'de' => 'Original OEM-Teile',
                            'lt' => 'Originalios OEM dalys',
                            'fr' => 'Pièces OEM d\'origine',
                            'es' => 'Piezas OEM genuinas',
                        ],
                    ],
                    [
                        'key'    => 'customers_count',
                        'suffix' => '+',
                        'label'  => [
                            'en' => 'Happy Car Owners',
                            'de' => 'Zufriedene Autobesitzer',
                            'lt' => 'Laimingi automobilių savininkai',
                            'fr' => 'Propriétaires de voitures heureux',
                            'es' => 'Propietarios de coches felices',
                        ],
                    ],
                    [
                        'key'    => 'countries_count',
                        'suffix' => '',
                        'label'  => [
                            'en' => 'EU Countries',
                            'de' => 'EU-Länder',
                            'lt' => 'ES šalys',
                            'fr' => 'Pays UE',
                            'es' => 'Países UE',
                        ],
                    ],
                    [
                        'key'    => 'rating',
                        'suffix' => '',
                        'label'  => [
                            'en' => 'Customer Rating',
                            'de' => 'Kundenbewertung',
                            'lt' => 'Klientų įvertinimas',
                            'fr' => 'Note clients',
                            'es' => 'Calificación clientes',
                        ],
                    ],
                ],
            ],
            'sort_order' => 20,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 3. HOW IT WORKS
        //    Target query: "how to order OEM parts online"
        //    Featured snippet format: numbered 3-step process.
        //    Descriptions are practical and specific — built for trust, not flair.
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'how_it_works',
            'title' => [
                'en' => 'How to Order Genuine OEM Parts on OEMHub',
                'de' => 'So bestellen Sie originale OEM-Teile bei OEMHub',
                'lt' => 'Kaip užsakyti originalias OEM dalis OEMHub',
                'fr' => 'Comment commander des pièces OEM d\'origine sur OEMHub',
                'es' => 'Cómo pedir piezas OEM genuinas en OEMHub',
            ],
            'content' => [
                'eyebrow' => [
                    'en' => 'HOW IT WORKS',
                    'de' => 'SO FUNKTIONIERT ES',
                    'lt' => 'KAIP TAI VEIKIA',
                    'fr' => 'COMMENT ÇA MARCHE',
                    'es' => 'CÓMO FUNCIONA',
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
                    'lt' => 'Trys paprasti žingsniai gauti tinkamą dalį už tinkamą kainą.',
                    'fr' => 'Trois étapes simples pour obtenir la bonne pièce au bon prix.',
                    'es' => 'Tres pasos simples para obtener la pieza correcta al precio correcto.',
                ],
                'steps' => [
                    [
                        'icon'        => 'magnifying-glass',
                        'step_number' => '01',
                        'title'       => [
                            'en' => 'Search OEM Number',
                            'de' => 'OEM-Nummer suchen',
                            'lt' => 'Ieškoti OEM numerio',
                            'fr' => 'Rechercher OEM',
                            'es' => 'Buscar número OEM',
                        ],
                        'description' => [
                            'en' => 'Enter your OEM number or VIN. We\'ll find the exact part for your vehicle.',
                            'de' => 'OEM-Nummer oder FIN eingeben. Wir finden das exakte Teil für Ihr Fahrzeug.',
                            'lt' => 'Įveskite OEM numerį arba VIN. Mes rasime tikslią dalį jūsų transporto priemonei.',
                            'fr' => 'Entrez votre numéro OEM ou VIN. Nous trouvons la pièce exacte pour votre véhicule.',
                            'es' => 'Ingrese su número OEM o VIN. Encontramos la pieza exacta para su vehículo.',
                        ],
                    ],
                    [
                        'icon'        => 'shopping-cart',
                        'step_number' => '02',
                        'title'       => [
                            'en' => 'Compare & Choose',
                            'de' => 'Vergleichen & Wählen',
                            'lt' => 'Palyginti ir Pasirinkti',
                            'fr' => 'Comparer & Choisir',
                            'es' => 'Comparar y Elegir',
                        ],
                        'description' => [
                            'en' => 'See real-time stock availability and condition. Pick the best option for your budget.',
                            'de' => 'Echtzeit-Verfügbarkeit ansehen. Wählen Sie die beste Option für Ihr Budget.',
                            'lt' => 'Matykite realaus laiko likučius. Pasirinkite geriausią variantą savo biudžetui.',
                            'fr' => 'Voir dispo en temps réel. Choisissez la meilleure option pour votre budget.',
                            'es' => 'Ver stock en tiempo real. Elija la mejor opción para su presupuesto.',
                        ],
                    ],
                    [
                        'icon'        => 'truck',
                        'step_number' => '03',
                        'title'       => [
                            'en' => 'Fast Delivery',
                            'de' => 'Schnelle Lieferung',
                            'lt' => 'Greitas pristatymas',
                            'fr' => 'Livraison rapide',
                            'es' => 'Entrega rápida',
                        ],
                        'description' => [
                            'en' => 'Tracked, insured delivery to your door across Europe. Get it in 5-7 days with Express.',
                            'de' => 'Erhalten Sie Ihre Teile schnell mit vollständiger Sendungsverfolgung.',
                            'lt' => 'Gaukite savo dalis greitai su pilnu sekimu ir draudimu.',
                            'fr' => 'Recevez vos pièces rapidement avec suivi complet et assurance.',
                            'es' => 'Reciba sus piezas rápidamente con seguimiento completo y seguro.',
                        ],
                    ],
                ],
            ],
            'sort_order' => 30,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 4. FEATURED BRANDS
        //    Authority through association — top-tier OEM brands.
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'featured_brands',
            'title' => [
                'en' => 'Genuine Parts from Original OEM Manufacturers',
                'de' => 'Originalteile von OEM-Herstellern',
                'lt' => 'Originalios dalys iš OEM gamintojų',
                'fr' => 'Pièces d\'origine des fabricants OEM',
                'es' => 'Piezas genuinas de fabricantes OEM originales',
            ],
            'content' => [
                'eyebrow' => [
                    'en' => 'ORIGINAL MANUFACTURERS',
                    'de' => 'ORIGINALHERSTELLER',
                    'lt' => 'ORIGINALŪS GAMINTOJAI',
                    'fr' => 'FABRICANTS D\'ORIGINE',
                    'es' => 'FABRICANTES ORIGINALES',
                ],
                'headline' => [
                    'en' => 'Genuine OEM Brands',
                    'de' => 'Original OEM-Marken',
                    'lt' => 'Originalūs OEM prekės ženklai',
                    'fr' => 'Marques OEM d\'origine',
                    'es' => 'Marcas OEM genuinas',
                ],
                'subheadline' => [
                    'en' => 'BMW, Audi, Mercedes, VW, Porsche & more. Perfectly fitting parts sourced for your car.',
                    'de' => 'Originalteile direkt vom OEM-Hersteller für garantierte Passform.',
                    'lt' => 'Originalios dalys tiesiogiai iš OEM gamintojų garantuotam suderinamumui.',
                    'fr' => 'Pièces d\'origine sourcées directement auprès de fabricants OEM.',
                    'es' => 'Piezas originales obtenidas directamente de fabricantes OEM.',
                ],
                'view_all_text' => [
                    'en' => 'View All Brands',
                    'de' => 'Alle Marken Anzeigen',
                    'lt' => 'Rodyti Visus Prekės Ženklus',
                    'fr' => 'Voir Toutes les Marques',
                    'es' => 'Ver Todas las Marcas',
                ],
            ],
            'sort_order' => 40,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 6. POPULAR SEARCHES
        //    Social proof through real search data.
        //    Subheadline adds urgency (availability / demand).
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'popular_searches',
            'title' => [
                'en' => 'Most Searched OEM Part Numbers This Month',
                'de' => 'Meistgesuchte OEM-Teilenummern diesen Monat',
                'lt' => 'Dažniausiai ieškomi OEM dalių numeriai šį mėnesį',
                'fr' => 'Numéros OEM les plus recherchés ce mois',
                'es' => 'Números OEM más buscados este mes',
            ],
            'content' => [
                'eyebrow' => [
                    'en' => 'TRENDING NOW',
                    'de' => 'IM TREND',
                    'lt' => 'POPULIARŪS',
                    'fr' => 'TENDANCE',
                    'es' => 'TENDENCIA',
                ],
                'headline' => [
                    'en' => 'Popular OEM Numbers',
                    'de' => 'Beliebte OEM-Nummern',
                    'lt' => 'Populiariausi OEM numeriai',
                    'fr' => 'Numéros OEM populaires',
                    'es' => 'Números OEM populares',
                ],
                'subheadline' => [
                    'en' => 'Limited stock available. Order now before these popular parts sell out.',
                    'de' => 'Begrenzter Vorrat verfügbar. Jetzt bestellen bevor diese beliebten Teile ausverkauft sind.',
                    'lt' => 'Ribotos atsargos. Užsakykite dabar, kol šios populiarios dalys neišparduotos.',
                    'fr' => 'Stock limité disponible. Commandez maintenant avant que ces pièces populaires ne soient vendues.',
                    'es' => 'Stock limitado disponible. Ordene ahora antes de que estas piezas populares se agoten.',
                ],
                'search_cta_text' => [
                    'en' => 'Search Your Part',
                    'de' => 'Ihr Teil Suchen',
                    'lt' => 'Ieškokite Dalies',
                    'fr' => 'Recherchez Votre Pièce',
                    'es' => 'Busque Su Pieza',
                ],
            ],
            'sort_order' => 50,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 7. PART INQUIRY
        //    Target: "can't find OEM part" — high-intent, high-frustration query.
        //    Tone: helpful expert, not a sales pitch.
        //    Subheadline explains the service concretely: what, how, how long.
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'part_inquiry',
            'title' => [
                'en' => 'Can\'t Find Your OEM Part Number? We\'ll Source It.',
                'de' => 'OEM-Teilenummer nicht gefunden? Wir beschaffen es.',
                'lt' => 'Neradote OEM dalies numerio? Mes jį surasime.',
                'fr' => 'Numéro OEM introuvable ? Nous le sourçons.',
                'es' => '¿No encuentra su número OEM? Nosotros lo localizamos.',
            ],
            'content' => [
                'eyebrow' => [
                    'en' => 'CAN\'T FIND YOUR PART?',
                    'de' => 'TEIL NICHT GEFUNDEN?',
                    'lt' => 'NERADOTE DALIES?',
                    'fr' => 'PIÈCE INTROUVABLE ?',
                    'es' => '¿NO ENCUENTRA SU PIEZA?',
                ],
                'headline' => [
                    'en' => 'We\'ll Source Your Part',
                    'de' => 'Wir beschaffen Ihr Teil',
                    'lt' => 'Mes surasime jūsų dalį',
                    'fr' => 'Nous sourçons votre pièce',
                    'es' => 'Localizamos su pieza',
                ],
                'subheadline' => [
                    'en' => 'Can\'t find it? Our specialists are available 7 days a week to source it for your car.',
                    'de' => 'Nicht gefunden? Wir beschaffen es in 24 Stunden — kostenlos.',
                    'lt' => 'Neradote? Mes surasime per 24 valandas — nemokamai.',
                    'fr' => 'Introuvable ? Nous le sourçons en 24h — gratuitement.',
                    'es' => '¿No lo encuentra? Lo localizamos en 24h — sin coste.',
                ],
                'button_text' => [
                    'en' => 'Request Part',
                    'de' => 'Teil Anfragen',
                    'lt' => 'Pateikti Užklausą',
                    'fr' => 'Demander Pièce',
                    'es' => 'Solicitar Pieza',
                ],
            ],
            'sort_order' => 60,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 8. TESTIMONIALS
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'testimonials',
            'title' => [
                'en' => 'What Car Owners Across Europe Say About OEMHub',
                'de' => 'Was Kfz-Profis aus ganz Europa über OEMHub sagen',
                'lt' => 'Ką automobilių remonto profesionalai visoje Europoje sako apie OEMHub',
                'fr' => 'Ce que les professionnels de l\'auto à travers l\'Europe disent d\'OEMHub',
                'es' => 'Lo que los profesionales del automóvil en Europa dicen sobre OEMHub',
            ],
            'content' => [
                'eyebrow' => [
                    'en' => 'VERIFIED REVIEWS',
                    'de' => 'VERIFIZIERTE BEWERTUNGEN',
                    'lt' => 'PATIKRINTI ATSILIEPIMAI',
                    'fr' => 'AVIS VÉRIFIÉS',
                    'es' => 'RESEÑAS VERIFICADAS',
                ],
                'headline' => [
                    'en' => 'Trusted by EU Car Owners',
                    'de' => 'Vertraut von EU-Käufern',
                    'lt' => 'Pasitiki ES pirkėjai',
                    'fr' => 'Approuvé par les acheteurs UE',
                    'es' => 'Con la confianza de compradores UE',
                ],
                'subheadline' => [
                    'en' => '2,500+ car owners across Europe trust us for genuine parts.',
                    'de' => '3.500+ Werkstätten & Käufer in 27 EU-Ländern vertrauen uns für originale Teile.',
                    'lt' => '3.500+ dirbtuvių ir pirkėjų 27 ES šalyse pasitiki mumis originalių dalių.',
                    'fr' => '3 500+ ateliers et acheteurs dans 27 pays UE nous font confiance pour des pièces authentiques.',
                    'es' => '3.500+ talleres y compradores en 27 países UE confían en nosotros para piezas genuinas.',
                ],
            ],
            'sort_order' => 70,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 9. SHIPPING INFO
        //    Headline answers the top concern: "will it reach me?"
        //    Subheadline addresses customs (EU-to-EU = no issues) — major objection.
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'shipping_info',
            'title' => [
                'en' => 'OEM Parts Delivered Across the EU — Tracked and Insured',
                'de' => 'OEM-Teile in der gesamten EU geliefert — Verfolgt und Versichert',
                'lt' => 'OEM dalys pristatomos visoje ES — Su sekimu ir draudimu',
                'fr' => 'Pièces OEM livrées dans toute l\'UE — Suivies et Assurées',
                'es' => 'Piezas OEM entregadas en toda la UE — Rastreadas y Aseguradas',
            ],
            'content' => [
                'eyebrow' => [
                    'en' => 'EU DELIVERY',
                    'de' => 'EU-LIEFERUNG',
                    'lt' => 'ES PRISTATYMAS',
                    'fr' => 'LIVRAISON UE',
                    'es' => 'ENTREGA UE',
                ],
                'headline' => [
                    'en' => 'EU-Wide Delivery',
                    'de' => 'EU-Lieferung',
                    'lt' => 'Pristatymas ES',
                    'fr' => 'Livraison UE',
                    'es' => 'Entrega UE',
                ],
                'subheadline' => [
                    'en' => 'No customs, no duties. Every order tracked and fully insured to your door.',
                    'de' => 'Kein Zoll, keine Gebühren. Jede Bestellung verfolgt und vollständig versichert an Ihre Tür.',
                    'lt' => 'Jokių muitų, jokių mokesčių. Kiekvienas užsakymas sekamas ir pilnai draustas prie durų.',
                    'fr' => 'Pas de douane, pas de frais. Chaque commande suivie et entièrement assurée à votre porte.',
                    'es' => 'Sin aduanas, sin aranceles. Cada pedido rastreado y completamente asegurado a su puerta.',
                ],
                'features' => [
                    [
                        'icon'  => 'clock',
                        'value' => [
                            'en' => '5-14 Days',
                            'de' => '1–5 Tage',
                            'lt' => '1–5 Dienos',
                            'fr' => '1–5 Jours',
                            'es' => '1–5 Días',
                        ],
                        'label' => [
                            'en' => 'EU Delivery',
                            'de' => 'EU-Lieferung',
                            'lt' => 'ES pristatymas',
                            'fr' => 'Livraison UE',
                            'es' => 'Entrega UE',
                        ],
                    ],
                    [
                        'icon'  => 'globe-europe',
                        'value' => [
                            'en' => '27',
                            'de' => '27',
                            'lt' => '27',
                            'fr' => '27',
                            'es' => '27',
                        ],
                        'label' => [
                            'en' => 'EU Countries',
                            'de' => 'EU-Länder',
                            'lt' => 'ES šalys',
                            'fr' => 'Pays UE',
                            'es' => 'Países UE',
                        ],
                    ],
                    [
                        'icon'  => 'shield-check',
                        'value' => [
                            'en' => '100%',
                            'de' => '100 %',
                            'lt' => '100 %',
                            'fr' => '100 %',
                            'es' => '100 %',
                        ],
                        'label' => [
                            'en' => 'Insured',
                            'de' => 'Versichert',
                            'lt' => 'Drausta',
                            'fr' => 'Assuré',
                            'es' => 'Asegurado',
                        ],
                    ],
                    [
                        'icon'  => 'arrow-path',
                        'value' => [
                            'en' => '14 Days',
                            'de' => '14 Tage',
                            'lt' => '14 Dienų',
                            'fr' => '14 Jours',
                            'es' => '14 Días',
                        ],
                        'label' => [
                            'en' => 'Free Returns',
                            'de' => 'Kostenlose Rückgabe',
                            'lt' => 'Nemokamas grąžinimas',
                            'fr' => 'Retours gratuits',
                            'es' => 'Devoluciones gratis',
                        ],
                    ],
                ],
                'carriers' => ['DHL', 'DPD', 'GLS', 'FedEx', 'UPS'],
            ],
            'sort_order' => 80,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 10. FAQS
        //     JSON-LD FAQPage schema — targets People Also Ask & AI Overviews.
        //     Eyebrow invites rather than declares.
        //     Subheadline removes jargon-anxiety: "plain answers from real experts."
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'faqs',
            'title' => [
                'en' => 'Frequently Asked Questions About Buying OEM Auto Parts Online',
                'de' => 'Häufig gestellte Fragen zum Kauf von OEM-Autoteilen online',
                'lt' => 'Dažnai užduodami klausimai apie OEM automobilių dalių pirkimą internete',
                'fr' => 'Questions fréquentes sur l\'achat de pièces auto OEM en ligne',
                'es' => 'Preguntas frecuentes sobre la compra de piezas OEM en línea',
            ],
            'content' => [
                'eyebrow' => [
                    'en' => 'FAQs',
                    'de' => 'HÄUFIGE FRAGEN',
                    'lt' => 'DUK',
                    'fr' => 'QUESTIONS FRÉQUENTES',
                    'es' => 'PREGUNTAS FRECUENTES',
                ],
                'headline' => [
                    'en' => 'Frequently Asked Questions',
                    'de' => 'Häufig Gestellte Fragen',
                    'lt' => 'Dažnai Užduodami Klausimai',
                    'fr' => 'Questions Fréquemment Posées',
                    'es' => 'Preguntas Frecuentes',
                ],
                'subheadline' => [
                    'en' => 'Quick answers about OEM numbers, shipping, returns, and more.',
                    'de' => 'Schnelle Antworten zu OEM-Nummern, Versand, Rückgabe und mehr.',
                    'lt' => 'Greiti atsakymai apie OEM numerius, pristatymą, grąžinimą ir daugiau.',
                    'fr' => 'Réponses rapides sur les numéros OEM, livraison, retours et plus encore.',
                    'es' => 'Respuestas rápidas sobre números OEM, envío, devoluciones y más.',
                ],
            ],
            'sort_order' => 90,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 11. CONTACT CTA
        //     Headline: surfaces the real doubt — "not sure which part."
        //     Subheadline: explains the service, qualifies the specialist, gives hours.
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'contact_cta',
            'title' => [
                'en' => 'Speak Directly with an OEM Parts Specialist',
                'de' => 'Direkt mit einem OEM-Teile-Spezialisten sprechen',
                'lt' => 'Kalbėkitės tiesiogiai su OEM dalių specialistu',
                'fr' => 'Parlez directement à un spécialiste en pièces OEM',
                'es' => 'Hable directamente con un especialista en piezas OEM',
            ],
            'content' => [
                'eyebrow' => [
                    'en' => 'FREE EXPERT HELP',
                    'de' => 'KOSTENLOSE EXPERTENHILFE',
                    'lt' => 'NEMOKAMA EKSPERTŲ PAGALBA',
                    'fr' => 'AIDE EXPERT GRATUITE',
                    'es' => 'AYUDA EXPERTA GRATUITA',
                ],
                'headline' => [
                    'en' => 'Need Help?',
                    'de' => 'Hilfe benötigt?',
                    'lt' => 'Reikia pagalbos?',
                    'fr' => 'Besoin d\'aide ?',
                    'es' => '¿Necesita ayuda?',
                ],
                'subheadline' => [
                    'en' => 'Call us or send a message. Free expert advice, available 7 days a week, 9 AM - 5 PM.',
                    'de' => 'Rufen Sie an oder schreiben Sie. Kostenlose Beratung, verfügbar Mo–Fr.',
                    'lt' => 'Paskambinkite arba parašykite. Nemokamos konsultacijos, galimos Pr–Pn.',
                    'fr' => 'Appelez ou envoyez un message. Conseils gratuits, disponibles Lun–Ven.',
                    'es' => 'Llámenos o envíe un mensaje. Asesoramiento gratuito, disponible Lun–Vie.',
                ],
                'button_text' => [
                    'en' => 'Contact Expert',
                    'de' => 'Experten Kontaktieren',
                    'lt' => 'Susisiekti su Ekspertu',
                    'fr' => 'Contacter l\'Expert',
                    'es' => 'Contactar Experto',
                ],
                'phone' => '+370 600 00000',
            ],
            'sort_order' => 100,
            'is_active'  => true,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 12. NEWSLETTER
        //     Eyebrow: social proof number — "50,000+" anchors scale.
        //     Headline: solves a real problem — back-in-stock alerts.
        //     Subheadline: two specific value props, not generic.
        //     Placeholder: "professional email" signals B2B intent.
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'newsletter',
            'title' => [
                'en' => 'OEM Part Alerts and Exclusive Subscriber Deals',
                'de' => 'OEM-Teil-Benachrichtigungen und exklusive Abonnentenangebote',
                'lt' => 'OEM dalių įspėjimai ir išskirtiniai prenumeratorių pasiūlymai',
                'fr' => 'Alertes pièces OEM et offres exclusives abonnés',
                'es' => 'Alertas de piezas OEM y ofertas exclusivas para suscriptores',
            ],
            'content' => [
                'eyebrow' => [
                    'en' => 'JOIN 2,500+ BUYERS',
                    'de' => '2.500+ KÄUFER BEITRETEN',
                    'lt' => 'PRISIJUNKITE PRIE 2.500+',
                    'fr' => 'REJOIGNEZ 2 500+ ACHETEURS',
                    'es' => 'ÚNASE A 2.500+ COMPRADORES',
                ],
                'headline' => [
                    'en' => 'Get OEM Stock Alerts',
                    'de' => 'OEM-Benachrichtigungen',
                    'lt' => 'Gaukite OEM įspėjimus',
                    'fr' => 'Alertes stock OEM',
                    'es' => 'Alertas de stock OEM',
                ],
                'subheadline' => [
                    'en' => 'Subscribe for personalized car part deals and restock alerts based on your vehicle history.',
                    'de' => 'Verfügbarkeitsbenachrichtigungen + Rabatte bis 20% direkt in Ihren Posteingang.',
                    'lt' => 'Atsargų įspėjimai + nuolaidos iki 20% tiesiai į jūsų pašto dėžutę.',
                    'fr' => 'Alertes stock + réductions jusqu\'à 20% directement dans votre boîte mail.',
                    'es' => 'Alertas de stock + descuentos hasta 20% directamente en su bandeja de entrada.',
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
                    'lt' => 'Jūsų el. pašto adresas',
                    'fr' => 'Votre adresse e-mail',
                    'es' => 'Su correo electrónico',
                ],
                'success_text' => [
                    'en' => 'You\'re in! Check your inbox.',
                    'de' => 'Sie sind dabei! Prüfen Sie Ihren Posteingang.',
                    'lt' => 'Jūs prisijungėte! Patikrinkite pašto dėžutę.',
                    'fr' => 'Vous êtes inscrit ! Vérifiez votre boîte.',
                    'es' => '¡Estás dentro! Revisa tu bandeja.',
                ],
            ],
            'sort_order' => 110,
            'is_active'  => true,
        ],
        // ──────────────────────────────────────────────────────────────────────
        // 13. BLOG PREVIEW
        //     Internal linking, freshness, and helpful content (E-E-A-T).
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'blog_preview',
            'title' => [
                'en' => 'OEM Parts Guides & Automotive News',
                'de' => 'OEM-Teile-Ratgeber & Automotive-News',
                'lt' => 'OEM dalių vadovai ir automobilių naujienos',
                'fr' => 'Guides de pièces OEM et actualités auto',
                'es' => 'Guías de piezas OEM y noticias automotrices',
            ],
            'content' => [
                'headline' => [
                    'en' => 'From Our Experts',
                    'de' => 'Von unseren Experten',
                    'lt' => 'Iš mūsų ekspertų',
                    'fr' => 'De nos experts',
                    'es' => 'De nuestros expertos',
                ],
                'view_all_text' => [
                    'en' => 'Read All Guides',
                    'de' => 'Alle Ratgeber lesen',
                    'lt' => 'Skaityti visus vadovus',
                    'fr' => 'Lire tous les guides',
                    'es' => 'Leer todas las guías',
                ],
            ],
            'sort_order' => 120,
            'is_active'  => false,
        ],

        // ──────────────────────────────────────────────────────────────────────
        // 14. BANNER
        //     Promotional banner for deals, new features, or urgent notices.
        // ──────────────────────────────────────────────────────────────────────
        [
            'type'  => 'banner',
            'title' => [
                'en' => 'Special Deals on OEM Parts',
                'de' => 'Spezielles Angebot für OEM-Teile',
                'lt' => 'Specialūs OEM dalių pasiūlymai',
                'fr' => 'Offres spéciales sur les pièces OEM',
                'es' => 'Ofertas especiales en piezas OEM',
            ],
            'content' => [
                'headline' => [
                    'en' => 'New Arrivals: Genuine BMW & Mercedes Parts',
                    'de' => 'Neuheiten: Originale BMW & Mercedes Teile',
                    'lt' => 'Naujienos: Originalios BMW ir Mercedes dalys',
                    'fr' => 'Nouveautés : Pièces d\'origine BMW & Mercedes',
                    'es' => 'Novedades: Piezas genuinas de BMW y Mercedes',
                ],
                'subheadline' => [
                    'en' => 'Over 1 Million+ genuine OEM parts for top European cars. Find yours today.',
                    'de' => 'Frischer Bestand von geprüften EU-Distributoren. Heute bestellen, morgen versenden.',
                    'lt' => 'Šviežios atsargos iš patikrintų ES platintojų. Užsakykite šiandien, išsiųsime rytoj.',
                    'fr' => 'Stock frais de distributeurs de l\'UE vérifiés. Commandez aujourd\'hui, expédié demain.',
                    'es' => 'Stock fresco de distribuidores verificados de la UE. Ordene hoy, se enviará mañana.',
                ],
                'button_text' => [
                    'en' => 'Check Availability',
                    'de' => 'Verfügbarkeit prüfen',
                    'lt' => 'Tikrinti prieinamumą',
                    'fr' => 'Vérifier la disponibilité',
                    'es' => 'Ver disponibilidad',
                ],
                'button_url' => '',
                'bg_color'   => '#0B3A68',
                'text_color' => '#FFFFFF',
            ],
            'sort_order' => 130,
            'is_active'  => false,
        ],
    ];

    public function run(): void
    {
        echo "Seeding homepage sections — Google SEO 2026 (E-E-A-T · AI Overviews · Helpful Content)...\n\n";

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
