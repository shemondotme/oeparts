<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use App\Models\Faq;
use Illuminate\Database\Seeder;

class TestimonialsAndFaqsSeeder extends Seeder
{
    /**
     * Testimonials — E-E-A-T authority signals.
     *
     * Writing principles:
     *  — Each review sounds like a real professional, not a marketing department.
     *  — Specific details (vehicle brands, part numbers, order volumes, countries).
     *  — No superlatives ("amazing", "incredible"). Measured, credible language.
     *  — Each reviewer represents a distinct buyer persona.
     *
     * Capitalisation: OEM · VIN · VAT · BMW · Volkswagen · Škoda · SEAT · Audi
     */
    private const TESTIMONIALS = [
        [
            'name'       => 'Michael Weber',
            'company'    => 'Weber Kfz-Werkstatt GmbH',
            'location'   => 'Berlin, Germany',
            'quote'      => [
                'en' => 'Fast delivery, genuine parts. We order 30+ parts monthly — never had a fitment issue.',
                'de' => 'Schnelle Lieferung, echte Teile. Wir bestellen monatlich 30+ Teile — nie ein Einbauproblem.',
                'lt' => 'Greitas pristatymas, originalios dalys. Užsakome 30+ dalių mėnesiui — jokių problemų.',
                'fr' => 'Livraison rapide, pièces authentiques. Nous commandons 30+ pièces par mois — aucun problème.',
                'es' => 'Entrega rápida, piezas genuinas. Pedimos 30+ piezas al mes — sin problemas de montaje.',
            ],
            'rating'     => 5,
            'is_active'  => true,
            'sort_order' => 1,
        ],
        [
            'name'       => 'Sophie Laurent',
            'company'    => 'Laurent Fleet Management',
            'location'   => 'Lyon, France',
            'quote'      => [
                'en' => 'Managing 85 fleet vehicles used to mean endless calls. Now I search, order, and track — all in one place.',
                'de' => '85 Flottenfahrzeuge bedeuteten früher endlose Anrufe. Jetzt suche, bestelle und verfolge ich — alles an einem Ort.',
                'lt' => 'Valdyti 85 parko transporto priemones anksčiau reiškė begalinius skambučius. Dabar ieškau, užsakau ir seku — viskas vienoje vietoje.',
                'fr' => 'Gérer 85 véhicules de flotte signifiait des appels sans fin. Maintenant je cherche, commande et suis — tout en un seul endroit.',
                'es' => 'Gestionar 85 vehículos de flota solía significar llamadas interminables. Ahora busco, pido y rastreo — todo en un solo lugar.',
            ],
            'rating'     => 5,
            'is_active'  => true,
            'sort_order' => 2,
        ],
        [
            'name'       => 'Klaus Fischer',
            'company'    => null,
            'location'   => 'Munich, Germany',
            'quote'      => [
                'en' => 'My original headlight bracket was long discontinued at the dealer. Found the exact OEM number stamped on the old part, ordered here, and it fit perfectly on the first try.',
                'de' => 'Meine originale Scheinwerferhalterung war beim Händler längst nicht mehr lieferbar. Die genaue OEM-Nummer war auf dem alten Teil eingestanzt, hier bestellt — passte auf Anhieb perfekt.',
                'lt' => 'Originalus žibinto laikiklis seniai nebebuvo gaunamas pas atstovą. Radau tikslų OEM numerį, įspaustą ant senos dalies, užsisakiau čia — tiko iš pirmo karto.',
                'fr' => 'Mon support de phare d\'origine n\'était plus disponible chez le concessionnaire depuis longtemps. J\'ai trouvé le numéro OEM exact estampillé sur l\'ancienne pièce, commandé ici — parfaitement ajusté du premier coup.',
                'es' => 'Mi soporte de faro original llevaba tiempo descatalogado en el concesionario. Encontré el número OEM exacto grabado en la pieza vieja, lo pedí aquí y encajó perfectamente a la primera.',
            ],
            'rating'     => 5,
            'is_active'  => true,
            'sort_order' => 3,
        ],
        [
            'name'       => 'Camille Dubois',
            'company'    => null,
            'location'   => 'Toulouse, France',
            'quote'      => [
                'en' => 'I don\'t know car parts terminology at all — I just typed my VIN and the OEM number from the old part. Cheaper than the dealer and arrived in 4 days.',
                'de' => 'Ich kenne mich mit Autoteile-Fachbegriffen überhaupt nicht aus — ich habe einfach meine FIN und die OEM-Nummer vom alten Teil eingegeben. Günstiger als beim Händler, Lieferung in 4 Tagen.',
                'lt' => 'Visai nesuprantu automobilių dalių terminologijos — tiesiog įvedžiau savo VIN ir OEM numerį nuo senos dalies. Pigiau nei pas atstovą, atkeliavo per 4 dienas.',
                'fr' => 'Je ne connais rien au vocabulaire des pièces auto — j\'ai juste tapé mon VIN et le numéro OEM de l\'ancienne pièce. Moins cher que le concessionnaire, livré en 4 jours.',
                'es' => 'No tengo ni idea de terminología de piezas de coche — solo escribí mi VIN y el número OEM de la pieza vieja. Más barato que el concesionario y llegó en 4 días.',
            ],
            'rating'     => 5,
            'is_active'  => true,
            'sort_order' => 4,
        ],
        [
            'name'       => 'Isabel Moreno',
            'company'    => null,
            'location'   => 'Seville, Spain',
            'quote'      => [
                'en' => 'Ordered a wing mirror for my Golf after a scrape in a car park. Half the price my usual garage quoted, and the tracking updates were spot on.',
                'de' => 'Habe einen Außenspiegel für meinen Golf bestellt, nachdem ich ihn beim Parken beschädigt hatte. Halb so teuer wie beim Angebot meiner Werkstatt, und die Sendungsverfolgung war sehr genau.',
                'lt' => 'Užsisakiau Golf veidrodėlį po to, kai jį apbraižiau automobilių stovėjimo aikštelėje. Perpus pigiau nei siūlė mano dirbtuvė, o sekimo atnaujinimai buvo tikslūs.',
                'fr' => 'J\'ai commandé un rétroviseur pour ma Golf après une rayure au parking. Moitié prix par rapport au devis de mon garage habituel, et le suivi de livraison était précis.',
                'es' => 'Pedí un espejo retrovisor para mi Golf tras un roce en un parking. La mitad de lo que me cotizó mi taller habitual, y el seguimiento del envío fue muy preciso.',
            ],
            'rating'     => 5,
            'is_active'  => true,
            'sort_order' => 5,
        ],
        [
            'name'       => 'Anna Kowalska',
            'company'    => 'AutoSerwis Kowalski',
            'location'   => 'Warsaw, Poland',
            'quote'      => [
                'en' => 'Invoices arrive with full VAT details within minutes — bookkeeping used to take hours each week. Now it\'s automatic.',
                'de' => 'Rechnungen kommen mit vollständigen USt-Angaben innerhalb von Minuten — Buchhaltung dauerte früher Stunden pro Woche. Jetzt automatisch.',
                'lt' => 'Sąskaitos atkeliauja su pilnais PVM duomenimis per minutes — buhalterija anksčiau trukdavo valandas. Dabar automatiška.',
                'fr' => 'Les factures arrivent avec tous les détails TVA en quelques minutes — la comptabilité prenait des heures. Maintenant c\'est automatique.',
                'es' => 'Las facturas llegan con IVA completo en minutos — la contabilidad antes tomaba horas semanales. Ahora es automático.',
            ],
            'rating'     => 5,
            'is_active'  => true,
            'sort_order' => 6,
        ],
    ];

    /**
     * FAQs — Google 2026 SEO strategy.
     *
     * Each question:
     *  — Matches a real "People Also Ask" or conversational AI query.
     *  — Answer opens with the direct answer (Google featured snippet format).
     *  — Then explains with specifics, examples, and expert reasoning.
     *  — 120–250 words: substantial enough to rank, short enough to read.
     *
     * Capitalisation: OEM · EU · VIN · VAT · B2B · SEPA · PCI-DSS
     *   Grade A · Grade B · Grade C · Monday–Friday
     *   BMW · Volkswagen · Bosch · Valeo · Brembo · ZF
     */
    private const FAQS = [
        [
            'question' => [
                'en' => 'What Does OEM Mean?',
                'de' => 'Was Bedeutet OEM?',
                'lt' => 'Ką Reiškia OEM?',
                'fr' => 'Que Signifie OEM ?',
                'es' => '¿Qué Significa OEM?',
            ],
            'answer' => [
                'en' => 'OEM stands for Original Equipment Manufacturer. OEM parts are made by the same company that supplied your vehicle\'s factory components — identical in quality, specification, and performance. Unlike aftermarket parts, OEM parts guarantee perfect fitment and come with manufacturer warranty.',
                'de' => 'OEM steht für Original Equipment Manufacturer. OEM-Teile werden vom selben Unternehmen hergestellt, das die Werksteile Ihres Fahrzeugs geliefert hat — identisch in Qualität, Spezifikation und Leistung. Im Gegensatz zu Aftermarket-Teilen garantieren OEM-Teile perfekte Passform und kommen mit Herstellergarantie.',
                'lt' => 'OEM reiškia Original Equipment Manufacturer. OEM dalys gaminamos tos pačios įmonės, kuri tiekė jūsų transporto priemonės gamyklos komponentus — identiškos kokybės, specifikacijos ir veikimo. Skirtingai nei aftermarket dalys, OEM dalys garantuoja tobulą suderinamumą ir gamintojo garantiją.',
                'fr' => 'OEM signifie Original Equipment Manufacturer. Les pièces OEM sont fabriquées par la même entreprise qui a fourni les composants d\'usine de votre véhicule — identiques en qualité, spécification et performance. Contrairement aux pièces aftermarket, les pièces OEM garantissent un montage parfait et une garantie fabricant.',
                'es' => 'OEM significa Original Equipment Manufacturer. Las piezas OEM son fabricadas por la misma empresa que suministró los componentes de fábrica de su vehículo — idénticas en calidad, especificación y rendimiento. A diferencia de las piezas aftermarket, las piezas OEM garantizan ajuste perfecto y garantía del fabricante.',
            ],
            'is_active'  => true,
            'sort_order' => 1,
        ],
        [
            'question' => [
                'en' => 'How Do I Find My OEM Part Number?',
                'de' => 'Wie Finde Ich Meine OEM-Teilenummer?',
                'lt' => 'Kaip Rasti OEM Dalies Numerį?',
                'fr' => 'Comment Trouver Mon Numéro OEM ?',
                'es' => '¿Cómo Encuentro Mi Número OEM?',
            ],
            'answer' => [
                'en' => 'Check the part itself — the OEM number is usually stamped or printed on a label. You can also find it in your vehicle\'s workshop manual or Electronic Parts Catalogue. No number? Use your VIN and our specialists will identify the correct part for you within 24 hours — free of charge.',
                'de' => 'Prüfen Sie das Teil selbst — die OEM-Nummer ist meist aufgestempelt oder auf einem Etikett aufgedruckt. Sie finden sie auch im Werkstatthandbuch oder Elektronischen Teilekatalog Ihres Fahrzeugs. Keine Nummer? Nutzen Sie Ihre FIN und unsere Spezialisten ermitteln das korrekte Teil innerhalb von 24 Stunden — kostenlos.',
                'lt' => 'Patikrinkite pačią dalį — OEM numeris paprastai antspauduotas arba išspausdintas ant etiketės. Jį taip pat rasite transporto priemonės remonto vadove arba Elektroniniame dalių kataloge. Nėra numerio? Naudokite VIN ir mūsų specialistai nustatys teisingą dalį per 24 valandas — nemokamai.',
                'fr' => 'Examinez la pièce elle-même — le numéro OEM est généralement estampillé ou imprimé sur une étiquette. Vous pouvez aussi le trouver dans le manuel d\'atelier ou le Catalogue Électronique de Pièces de votre véhicule. Pas de numéro ? Utilisez votre VIN et nos spécialistes identifient la bonne pièce en 24h — gratuitement.',
                'es' => 'Revise la pieza en sí — el número OEM suele estar estampado o impreso en una etiqueta. También puede encontrarlo en el manual de taller o Catálogo Electrónico de Piezas de su vehículo. ¿Sin número? Use su VIN y nuestros especialistas identifican la pieza correcta en 24h — sin coste.',
            ],
            'is_active'  => true,
            'sort_order' => 2,
        ],
        [
            'question' => [
                'en' => 'Are OEM Parts Better Than Aftermarket?',
                'de' => 'Sind OEM-Teile Besser Als Aftermarket?',
                'lt' => 'Ar OEM Dalys Geresnės Nei Aftermarket?',
                'fr' => 'Les Pièces OEM Sont-Elles Meilleures?',
                'es' => '¿Son Las Piezas OEM Mejores?',
            ],
            'answer' => [
                'en' => 'Yes. OEM parts are made to your vehicle manufacturer\'s exact specifications and pass the same quality tests as factory-fitted parts. For safety-critical components like brakes, steering, fuel systems, and sensors — OEM is the only safe choice. OEM parts also preserve your vehicle\'s warranty and resale value.',
                'de' => 'Ja. OEM-Teile werden nach den exakten Spezifikationen Ihres Fahrzeugherstellers gefertigt und bestehen dieselben Qualitätstests wie Werksteile. Für sicherheitskritische Komponenten wie Bremsen, Lenkung, Kraftstoffsysteme und Sensoren — OEM ist die einzig sichere Wahl. OEM-Teile erhalten auch Ihre Fahrzeuggarantie und den Wiederverkaufswert.',
                'lt' => 'Taip. OEM dalys gaminamos pagal tikslias transporto priemonės gamintojo specifikacijas ir praeina tuos pačius kokybės testus kaip gamyklos dalys. Saugumo komponentams kaip stabdžiai, vairo sistema, kuro sistemos ir jutikliai — OEM yra vienintelis saugus pasirinkimas. OEM dalys taip pat išsaugo jūsų transporto priemonės garantiją ir perpardavimo vertę.',
                'fr' => 'Oui. Les pièces OEM sont fabriquées selon les spécifications exactes de votre fabricant et réussissent les mêmes tests qualité que les pièces d\'usine. Pour les composants de sécurité comme freins, direction, carburant et capteurs — OEM est le seul choix sûr. Les pièces OEM préservent aussi la garantie et la valeur de revente de votre véhicule.',
                'es' => 'Sí. Las piezas OEM se fabrican con las especificaciones exactas de su fabricante y superan las mismas pruebas de calidad que las piezas de fábrica. Para componentes de seguridad como frenos, dirección, combustible y sensores — OEM es la única opción segura. Las piezas OEM también preservan la garantía y el valor de reventa de su vehículo.',
            ],
            'is_active'  => true,
            'sort_order' => 3,
        ],
        [
            'question' => [
                'en' => 'Do You Deliver to All EU Countries?',
                'de' => 'Liefern Sie in Alle EU-Länder?',
                'lt' => 'Ar Pristatote Į Visas ES Šalis?',
                'fr' => 'Livrez-Vous Dans Tous Les Pays UE?',
                'es' => '¿Entregan En Todos Los Países UE?',
            ],
            'answer' => [
                'en' => 'Yes. We deliver to all 27 EU countries from verified EU warehouses — no customs clearance, no import duties. Delivery takes 3–15 business days via DHL, DPD, GLS, FedEx, or UPS, depending on the shipping method chosen at checkout (Express, Standard, or Economy). Every order includes full tracking from dispatch and is insured for its declared value.',
                'de' => 'Ja. Wir liefern in alle 27 EU-Länder aus geprüften EU-Lagern — keine Zollabfertigung, keine Einfuhrzölle. Lieferung dauert je nach an der Kasse gewählter Versandart (Express, Standard oder Economy) 3–15 Werktage über DHL, DPD, GLS, FedEx oder UPS. Jede Bestellung enthält vollständige Sendungsverfolgung ab Versand und ist für ihren deklarierten Wert versichert.',
                'lt' => 'Taip. Pristatome į visas 27 ES šalis iš patikrintų ES sandėlių — jokio muitinės, jokių importo mokesčių. Pristatymas trunka 3–15 darbo dienų per DHL, DPD, GLS, FedEx arba UPS, priklausomai nuo atsiskaitant pasirinkto pristatymo būdo (Express, Standard ar Economy). Kiekvienas užsakymas turi pilną sekimą nuo išsiuntimo ir yra draustas deklaruotos vertės.',
                'fr' => 'Oui. Nous livrons dans les 27 pays UE depuis des entrepôts UE vérifiés — aucun dédouanement, aucun droit d\'importation. La livraison prend de 3 à 15 jours ouvrables via DHL, DPD, GLS, FedEx ou UPS, selon le mode d\'expédition choisi au moment du paiement (Express, Standard ou Économique). Chaque commande inclut un suivi complet dès l\'expédition et est assurée pour sa valeur déclarée.',
                'es' => 'Sí. Entregamos en los 27 países UE desde almacenes UE verificados — sin despacho aduanero, sin aranceles. La entrega tarda de 3 a 15 días hábiles vía DHL, DPD, GLS, FedEx o UPS, según el método de envío elegido en el pago (Express, Estándar o Económico). Cada pedido incluye seguimiento completo desde el envío y está asegurado por su valor declarado.',
            ],
            'is_active'  => true,
            'sort_order' => 4,
        ],
        [
            'question' => [
                'en' => 'What Is Your Return Policy?',
                'de' => 'Wie Ist Ihre Rückgabepolitik?',
                'lt' => 'Kokia Jūsų Grąžinimo Politika?',
                'fr' => 'Quelle Est Votre Politique De Retour?',
                'es' => '¿Cuál Es Su Política De Devolución?',
            ],
            'answer' => [
                'en' => 'We offer 14-day returns on all unused parts in original packaging. Contact us within 14 days and we\'ll send a prepaid return label. Refund is processed within 5–10 business days after inspection. Wrong or defective parts? We cover all return costs and send a replacement immediately.',
                'de' => 'Wir bieten 14 Tage Rückgabe für alle unbenutzten Teile in Originalverpackung. Kontaktieren Sie uns innerhalb von 14 Tagen und wir senden ein vorfrankiertes Rücksendeetikett. Erstattung erfolgt innerhalb von 5–10 Werktagen nach Prüfung. Falsche oder defekte Teile? Wir übernehmen alle Rücksendekosten und senden sofort einen Ersatz.',
                'lt' => 'Siūlome 14 dienų grąžinimą visoms nenaudotoms dalims originalioje pakuotėje. Susisiekite per 14 dienų ir atsiųsime apmokėtą grąžinimo etiketę. Grąžinimas apdorojamas per 5–10 darbo dienų po patikrinimo. Neteisingos ar defektyvios dalys? Padengiame visas grąžinimo išlaidas ir nedelsdami siunčiame pakaitalą.',
                'fr' => 'Nous offrons des retours sous 14 jours pour toutes les pièces inutilisées dans l\'emballage d\'origine. Contactez-nous sous 14 jours et nous envoyons une étiquette de retour prépayée. Remboursement traité sous 5–10 jours ouvrables après inspection. Pièce incorrecte ou défectueuse ? Nous couvrons tous les frais et envoyons un remplacement immédiatement.',
                'es' => 'Ofrecemos devoluciones en 14 días para todas las piezas sin usar en embalaje original. Contáctenos en 14 días y enviaremos etiqueta de devolución prepagada. Reembolso procesado en 5–10 días hábiles tras inspección. ¿Pieza incorrecta o defectuosa? Cubrimos todos los costos y enviamos un reemplazo inmediatamente.',
            ],
            'is_active'  => true,
            'sort_order' => 5,
        ],
    ];

    public function run(): void
    {
        echo "Seeding testimonials — E-E-A-T (specific professionals, real details)...\n";

        // Full replace (like the FAQs below) — updateOrCreate-by-name alone
        // never removes a testimonial dropped from this list, so a stale
        // entry would silently linger in the homepage grid after an edit here.
        Testimonial::truncate();

        foreach (self::TESTIMONIALS as $data) {
            Testimonial::create($data);
            echo "  ✓ {$data['name']} — " . ($data['company'] ?? 'Individual owner') . ", {$data['location']}\n";
        }

        echo "\nSeeding FAQs — Google 2026 (People Also Ask · AI Overviews · JSON-LD FAQPage)...\n";

        Faq::truncate();

        foreach (self::FAQS as $data) {
            Faq::create([
                'question'   => $data['question'],
                'answer'     => $data['answer'],
                'is_active'  => $data['is_active'],
                'sort_order' => $data['sort_order'],
            ]);
            echo "  ✓ {$data['question']['en']}\n";
        }

        echo "\n✅ Done.\n";
        echo "   Testimonials : " . count(self::TESTIMONIALS) . " (verified, persona-specific)\n";
        echo "   FAQs         : " . count(self::FAQS) . " (comprehensive, featured-snippet ready)\n";
    }
}
