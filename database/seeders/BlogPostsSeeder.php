<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogPostsSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->createCategories();
        $author = $this->getAuthor();
        $this->createPosts($categories, $author);
    }

    private function createCategories(): array
    {
        $data = [
            ['key' => 'maintenance', 'en' => 'Maintenance Tips',   'de' => 'Wartungstipps',     'lt' => 'Priežiūros patarimai', 'fr' => "Conseils d'entretien",   'es' => 'Consejos de mantenimiento'],
            ['key' => 'guides',      'en' => 'How-To Guides',      'de' => 'Anleitungen',        'lt' => 'Vadovai',              'fr' => 'Guides pratiques',       'es' => 'Guías prácticas'],
            ['key' => 'industry',    'en' => 'Industry News',      'de' => 'Branchennews',       'lt' => 'Pramonės naujienos',   'fr' => 'Actualités du secteur',  'es' => 'Noticias del sector'],
            ['key' => 'arrivals',    'en' => 'New Arrivals',       'de' => 'Neu eingetroffen',   'lt' => 'Nauji atvykimai',      'fr' => 'Nouveautés',             'es' => 'Novedades'],
            ['key' => 'oem-tips',    'en' => 'OEM Tips',           'de' => 'OEM-Tipps',          'lt' => 'OEM patarimai',        'fr' => 'Conseils OEM',           'es' => 'Consejos OEM'],
            ['key' => 'workshop',    'en' => 'Workshop Insights',  'de' => 'Werkstatteinblicke', 'lt' => 'Dirbtuvių įžvalgos',   'fr' => "Aperçus d'atelier",      'es' => 'Perspectivas de taller'],
        ];

        $categories = [];
        foreach ($data as $i => $cat) {
            $categories[$cat['key']] = Category::firstOrCreate(
                ['slug' => $cat['key']],
                [
                    'name' => ['en' => $cat['en'], 'de' => $cat['de'], 'lt' => $cat['lt'], 'fr' => $cat['fr'], 'es' => $cat['es']],
                    'sort_order' => ($i + 1) * 10,
                ]
            );
        }
        return $categories;
    }

    private function getAuthor(): Admin
    {
        $author = Admin::first();
        if (!$author) {
            $author = Admin::create([
                'name' => 'OeParts Editorial',
                'email' => 'editorial@oeparts.test',
                'password' => bcrypt('password'),
            ]);
        }
        return $author;
    }

    private function createPosts(array $categories, Admin $author): void
    {
        $posts = [];
        $posts[] = $this->post1($categories, $author);
        $posts[] = $this->post2($categories, $author);
        $posts[] = $this->post3($categories, $author);
        $posts[] = $this->post4($categories, $author);
        $posts[] = $this->post5($categories, $author);
        $posts[] = $this->post6($categories, $author);
        $posts[] = $this->post7($categories, $author);
        $posts[] = $this->post8($categories, $author);
        $posts[] = $this->post9($categories, $author);
        $posts[] = $this->post10($categories, $author);

        foreach ($posts as $post) {
            $post->save();
        }

        $this->command->info('Created 10 blog posts across 6 categories.');
    }

    private function post1($cat, $author): BlogPost
    {
        return new BlogPost([
            'title' => ['en' => 'How to Choose the Right OEM Parts for Your Vehicle', 'de' => 'So waehlen Sie die richtigen OEM-Teile fuer Ihr Fahrzeug', 'lt' => 'Kaip pasirinkti tinkamas OEM dalis savo automobiliui', 'fr' => 'Comment choisir les bonnes pieces OEM pour votre vehicule', 'es' => 'Como elegir las piezas OEM adecuadas para su vehiculo'],
            'slug' => 'how-to-choose-the-right-oem-parts-for-your-vehicle',
            'excerpt' => ['en' => 'Learn the key factors when selecting genuine OEM parts — from compatibility checks to warranty verification.', 'de' => 'Erfahren Sie, worauf es bei der Auswahl echter OEM-Teile ankommt.', 'lt' => 'Suinokite pagrindinius veiksnius renkantis originalias OEM dalis.', 'fr' => 'Decouvrez les facteurs cles pour choisir des pieces OEM d origine.', 'es' => 'Conozca los factores clave para seleccionar piezas OEM genuinas.'],
            'content' => ['en' => "## Why OEM Parts Matter\n\nOEM parts guarantee perfect fitment, reliable performance, and long-term durability. Unlike aftermarket alternatives, they are built to exact vehicle specifications.\n\n## Compatibility Checklist\n\n1. Check your VIN — the 17-digit number is the most reliable way to verify compatibility.\n2. Compare OEM numbers from your old component.\n3. Consult our search tool to verify fitment across models.\n\n## Warranty\n\nAll OEM parts on OeParts come with a manufacturer-backed warranty of 12 to 24 months.", 'de' => "## Warum OEM-Teile wichtig sind\n\nOEM-Teile garantieren perfekte Passform und lange Haltbarkeit.\n\n## Kompatibilitaet\n\n1. FIN pruefen\n2. OEM-Nummern vergleichen\n3. Suchfunktion nutzen\n\n## Garantie\n\n12 bis 24 Monate Herstellergarantie.", 'lt' => "## Kodel OEM dalys svarbios\n\nOEM dalys garantuoja tobulai suderinamuma ir patikimuma.\n\n## Suderinamumas\n\n1. Tikrinkite VIN\n2. Palyginkite OEM numerius\n3. Naudokite paieska\n\n## Garantija\n\n12-24 menesiu gamintojo garantija.", 'fr' => "## Pourquoi les pieces OEM sont importantes\n\nLes pieces OEM garantissent un ajustement parfait.\n\n## Compatibilite\n\n1. Verifiez votre VIN\n2. Comparez les numeros OEM\n3. Utilisez notre outil de recherche\n\n## Garantie\n\n12 a 24 mois de garantie constructeur.", 'es' => "## Por que las piezas OEM son importantes\n\nLas piezas OEM garantizan un ajuste perfecto.\n\n## Compatibilidad\n\n1. Verifique su VIN\n2. Compare numeros OEM\n3. Use nuestra busqueda\n\n## Garantia\n\n12 a 24 meses de garantia."],
            'category_id' => $cat['maintenance']->id,
            'author_id' => $author->id,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(2),
        ]);
    }

    private function post2($cat, $author): BlogPost
    {
        return new BlogPost([
            'title' => ['en' => '5 Signs Your Brake Pads Need Replacement', 'de' => '5 Anzeichen fuer neue Bremsbelaege', 'lt' => '5 zenklai, kad reikia keisti stabdziu kaladeles', 'fr' => '5 signes que vos plaquettes de frein doivent etre remplacees', 'es' => '5 senales de que necesita reemplazar las pastillas de freno'],
            'slug' => '5-signs-your-brake-pads-need-replacement',
            'excerpt' => ['en' => 'Dont ignore these warning signs. Discover when to replace your brake pads.', 'de' => 'Ignorieren Sie diese Warnsignale nicht.', 'lt' => 'Neignoruokite siu isp ejamuju zenklu.', 'fr' => "N ignorez pas ces signes d avertissement.", 'es' => 'No ignore estas senales de advertencia.'],
            'content' => ['en' => "## Recognising Brake Wear\n\nHere are five unmistakable signs its time for new brake pads:\n\n1. Squeaking noises when braking\n2. Spongy brake pedal\n3. Vibrations during braking\n4. Pad material less than 3mm thick\n5. Dashboard warning light\n\nAlways choose OEM brake pads for guaranteed safety.", 'de' => "## Bremsverschleiss erkennen\n\n1. Quietschgeraeusche\n2. Schwammiges Bremspedal\n3. Vibrationen\n4. Belagstaerke unter 3 mm\n5. Warnleuchte\n\nOEM-Bremsbelaege waehlen.", 'lt' => "## Stabdziu susidevejimo atpazinimas\n\n1. Cypimas\n2. Minkstas pedalas\n3. Vibracijos\n4. Stores mazesnis nei 3 mm\n5. Isp ejamoji lempute\n\nRinkites OEM kaladeles.", 'fr' => "## Usure des freins\n\n1. Grincements\n2. Pedale molle\n3. Vibrations\n4. Epaisseur sous 3 mm\n5. Temoin au tableau\n\nPlaquettes OEM.", 'es' => "## Desgaste de frenos\n\n1. Chirridos\n2. Pedal blando\n3. Vibraciones\n4. Grosor bajo 3 mm\n5. Luz de advertencia\n\nPastillas OEM."],
            'category_id' => $cat['maintenance']->id,
            'author_id' => $author->id,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(5),
        ]);
    }

    private function post3($cat, $author): BlogPost
    {
        return new BlogPost([
            'title' => ['en' => 'The Rise of EV Components in European Workshops', 'de' => 'Der Aufstieg der EV-Komponenten', 'lt' => 'Elektromobiliu komponentu augimas', 'fr' => "L essor des composants EV", 'es' => 'El auge de los componentes EV'],
            'slug' => 'the-rise-of-ev-components-in-european-workshops',
            'excerpt' => ['en' => 'Explore the growing demand for EV components across European workshops.', 'de' => 'Die wachsende Nachfrage nach EV-Komponenten.', 'lt' => 'Auganti elektromobiliu komponentu paklausa.', 'fr' => 'La demande croissante de composants EV.', 'es' => 'La creciente demanda de componentes EV.'],
            'content' => ['en' => "## The EV Revolution\n\nBy 2026, EVs account for over 25% of new car sales in the EU. This shift is reshaping the aftermarket.\n\n## Key EV Components in Demand\n\n- Battery Management Systems\n- Electric Drive Motors\n- Inverters and Converters\n- HVAC Compressors\n- Regenerative Braking parts\n\n## Workshop Readiness\n\nOeParts partners with leading OEM suppliers to ensure genuine EV components reach every EU member state.", 'de' => "## Die EV-Revolution\n\nUeber 25% der Neuwagen in der EU sind EVs.\n\n## Gefragte Komponenten\n\n- Batteriemanagementsysteme\n- Elektrische Antriebsmotoren\n- Wechselrichter\n- HVAC-Kompressoren\n- Rekuperationsbremsen\n\n## Bereitschaft\n\nOeParts liefert OEM-EV-Komponenten in alle EU-Laender.", 'lt' => "## Elektromobiliu revoliucija\n\n>25% nauju automobiliu ES yra EV.\n\n## Paklausus komponentai\n\n- Bateriju valdymo sistemos\n- Elektriniai varikliai\n- Inverteriai\n- Kompresoriai\n- Regeneracinio stabdymo dalys", 'fr' => "## Revolution EV\n\n>25% des voitures neuves dans l UE sont des EV.\n\n## Composants recherches\n\n- Systemes de gestion batterie\n- Moteurs electriques\n- Onduleurs\n- Compresseurs HVAC\n- Freinage regeneratif", 'es' => "## Revolucion EV\n\n>25% de autos nuevos en la UE son EV.\n\n## Componentes demandados\n\n- Sistemas de gestion de bateria\n- Motores electricos\n- Inversores\n- Compresores HVAC\n- Frenado regenerativo"],
            'category_id' => $cat['industry']->id,
            'author_id' => $author->id,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(8),
        ]);
    }

    private function post4($cat, $author): BlogPost
    {
        return new BlogPost([
            'title' => ['en' => 'Bosch Fuel Injectors — New OEM Stock Arriving Weekly', 'de' => 'Bosch Einspritzdüsen — Neue Ware wöchentlich', 'lt' => 'Bosch purkštukai — Nauja atsarga kas savaitę', 'fr' => 'Injecteurs Bosch — Nouveau stock chaque semaine', 'es' => 'Inyectores Bosch — Nuevo stock cada semana'],
            'slug' => 'bosch-fuel-injectors-new-oem-stock',
            'excerpt' => ['en' => 'Expanding our Bosch fuel injector catalogue with genuine OEM units for VW, Audi, BMW, and Mercedes.', 'de' => 'Erweiterung unseres Bosch-Sortiments für VW, Audi, BMW und Mercedes.', 'lt' => 'Pleciame Bosch purkstuku kataloga VW, Audi, BMW ir Mercedes.', 'fr' => 'Elargissement du catalogue Bosch pour VW, Audi, BMW et Mercedes.', 'es' => 'Ampliando el catalogo Bosch para VW, Audi, BMW y Mercedes.'],
            'content' => ['en' => "## New Bosch Fuel Injector Stock\n\nWe are expanding our Bosch fuel injector inventory covering the most popular European platforms.\n\n### Available Now\n\n- VW/Audi: 06L906036x for EA888 Gen 3/4\n- BMW: 121375888xx for N54 / N55 / B58\n- Mercedes: A276906xx for M276 / M278\n\n### Why OEM Bosch?\n\nBosch is the original equipment supplier for most European manufacturers. OEM injectors guarantee precise fuel metering and plug-and-play fitment with 24-month warranty.", 'de' => "## Neue Bosch-Einspritzdüsen\n\nBosch ist der Erstausrüster fuer die meisten europaeischen Hersteller. 24 Monate Garantie.", 'lt' => "## Naujos Bosch purkstuku atsargos\n\nBosch yra originalios irangos tiekejas daugumai Europos gamintoju.", 'fr' => "## Nouveau stock d injecteurs Bosch\n\nBosch est le fournisseur d origine pour la plupart des constructeurs europeens.", 'es' => "## Nuevo stock de inyectores Bosch\n\nBosch es el proveedor original de la mayoria de los fabricantes europeos."],
            'category_id' => $cat['arrivals']->id,
            'author_id' => $author->id,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(11),
        ]);
    }

    private function post5($cat, $author): BlogPost
    {
        return new BlogPost([
            'title' => ['en' => 'Understanding OEM Part Numbers — A Complete Guide', 'de' => 'OEM-Teilenummern verstehen', 'lt' => 'OEM daliu numeriu supratimas', 'fr' => 'Comprendre les numeros de pieces OEM', 'es' => 'Comprension de los numeros de piezas OEM'],
            'slug' => 'understanding-oem-part-numbers',
            'excerpt' => ['en' => 'Decode OEM part numbers like a pro. Learn numbering systems used by major manufacturers.', 'de' => 'OEM-Teilenummern wie ein Profi entschluesseln.', 'lt' => 'Issifruokite OEM daliu numerius kaip profesionalas.', 'fr' => 'Decodez les numeros OEM comme un pro.', 'es' => 'Decodifique los numeros OEM como un profesional.'],
            'content' => ['en' => "## How OEM Part Numbers Work\n\nEvery genuine OEM part has a unique identifier.\n\n### BMW\n7-digit or 11-digit format (e.g. 12137588837). First 7 digits identify the part, last 4 indicate colour/variant.\n\n### Mercedes-Benz\nA-prefix system (e.g. A2769060000).\n\n### Volkswagen/Audi\nVAG numbers use a model prefix + component code + revision suffix.\n\n## Authenticity Tips\n\n1. Check packaging for holographic seals\n2. Verify font consistency\n3. Weight test — counterfeits are often lighter\n4. Use OeParts search to cross-reference against 5M+ OEM numbers", 'de' => "## OEM-Nummern verstehen\n\nBMW: 7- oder 11-stellig. Mercedes: A-Praefix. VAG: Modellpraefix + Code + Suffix.\n\n## Echtheit\n\nVerpackung, Schriftarten, Gewichtstest, OeParts-Suche.", 'lt' => "## OEM numeriu supratimas\n\nBMW: 7 ar 11 skaitmenu. Mercedes: A-priesdelis. VAG: modelio priesdelis.\n\n## Autentiskumas\n\nPatikrinkite pakuote, siuntus, svori. Naudokite OeParts paieska.", 'fr' => "## Numeros OEM\n\nBMW: 7 ou 11 chiffres. Mercedes: prefixe A. VAG: prefixe modele.\n\n## Authenticite\n\nEmballage, polices, poids, recherche OeParts.", 'es' => "## Numeros OEM\n\nBMW: 7 u 11 digitos. Mercedes: prefijo A. VAG: prefijo de modelo.\n\n## Autenticidad\n\nEmpaque, fuentes, peso, busqueda OeParts."],
            'category_id' => $cat['oem-tips']->id,
            'author_id' => $author->id,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(14),
        ]);
    }

    private function post6($cat, $author): BlogPost
    {
        return new BlogPost([
            'title' => ['en' => 'Top 10 Tools Every Auto Workshop Needs in 2026', 'de' => 'Die 10 wichtigsten Werkzeuge 2026', 'lt' => '10 svarbiausiu irankiu 2026', 'fr' => 'Top 10 outils atelier 2026', 'es' => '10 herramientas esenciales 2026'],
            'slug' => 'top-10-tools-every-auto-workshop-needs-2026',
            'excerpt' => ['en' => 'From diagnostic scanners to torque wrenches, discover essential tools for 2026 workshops.', 'de' => 'Von Diagnose-Scannern bis Drehmomentschluesseln.', 'lt' => 'Nuo diagnostikos skaitytuvu iki dinamometriniu raktu.', 'fr' => 'Des scanners aux cles dynamometriques.', 'es' => 'Desde escaneres hasta llaves dinamometricas.'],
            'content' => ['en' => "## Essential Workshop Tools for 2026\n\n1. Multi-brand diagnostic scanner\n2. Digital torque wrench with angle measurement\n3. EV high-voltage safety kit\n4. Endoscope camera\n5. Oil filter wrench set\n6. Brake pad spreader for EPB systems\n7. Timing belt locking kit\n8. Wireless magnetic LED lighting\n9. Ultrasonic parts washer\n10. OeParts account for genuine OEM parts with EU delivery", 'de' => "1. Multimarken-Diagnose-Scanner\n2. Digitaler Drehmomentschluessel\n3. EV-Hochspannungs-Set\n4. Endoskopkamera\n5. OElfilter-Schluesselsatz\n6. Bremskolben-Rueckstellwerkzeug\n7. Steuerriemen-Arretierung\n8. Kabellose LED-Beleuchtung\n9. Ultraschall-Reiniger\n10. OeParts-Konto", 'lt' => "1. Daugiamarkis diagnostikos skaitytuvas\n2. Skaitmeninis dinamometrinis raktas\n3. Aukstos itampos rinkinys\n4. Endoskopo kamera\n5. Alyvos filtro raktu rinkinys\n6. Stabdziu stumoklio irankis\n7. Paskirstymo dizo fiksavimas\n8. Belaidis apvietimas\n9. Ultragarsine plovykla\n10. OeParts paskyra", 'fr' => "1. Scanner diagnostic multimarque\n2. Cle dynamometrique numerique\n3. Kit haute tension EV\n4. Camera endoscopique\n5. Jeu de cles a filtre a huile\n6. Outil de repousse de piston\n7. Kit de calage distribution\n8. Eclairage LED sans fil\n9. Nettoyeur a ultrasons\n10. Compte OeParts", 'es' => "1. Escaner de diagnostico multimarca\n2. Llave dinamometrica digital\n3. Kit de alto voltaje EV\n4. Camara endoscopica\n5. Juego de llaves para filtro\n6. Herramienta de retroceso de piston\n7. Kit de correa de distribucion\n8. Iluminacion LED inalambrica\n9. Limpiador ultrasonico\n10. Cuenta OeParts"],
            'category_id' => $cat['workshop']->id,
            'author_id' => $author->id,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(17),
        ]);
    }

    private function post7($cat, $author): BlogPost
    {
        return new BlogPost([
            'title' => ['en' => 'Winter Car Maintenance — OEM Parts Checklist', 'de' => 'Winter-Wartung — OEM-Checkliste', 'lt' => 'Ziemos prieziura — OEM kontrolinis sarasas', 'fr' => 'Entretien hivernal — Liste OEM', 'es' => 'Mantenimiento de invierno — Lista OEM'],
            'slug' => 'winter-car-maintenance-oem-parts-checklist',
            'excerpt' => ['en' => 'Prepare your vehicle for winter with our OEM parts checklist.', 'de' => 'Bereiten Sie Ihr Fahrzeug mit unserer OEM-Checkliste vor.', 'lt' => 'Paruoskite automobiliui ziemai su mūsų OEM kontroliniu sarasu.', 'fr' => 'Preparez votre vehicule pour l hiver.', 'es' => 'Prepare su vehiculo para el invierno.'],
            'content' => ['en' => "## Winter-Ready Checklist\n\n1. Winter tyres with 3PMSF symbol\n2. Battery — check CCA rating\n3. Coolant mixture for -35C\n4. Heater blower motor\n5. Glow plugs for diesel engines\n6. Wiper blades\n7. ABS sensors\n\nAll OEM parts available via OeParts with EU-wide delivery.", 'de' => "1. Winterreifen\n2. Batterie\n3. Kuehlmittel\n4. Geblaesemotor\n5. Gluehkerzen\n6. Scheibenwischer\n7. ABS-Sensoren\n\nOEM-Teile ueber OeParts.", 'lt' => "1. Ziemines padangos\n2. Akumuliatorius\n3. Ausinimo skystis\n4. Sildytuvo variklis\n5. Kaitinimo zvakes\n6.Valytuvai\n7. ABS jutikliai", 'fr' => "1. Pneus hiver\n2. Batterie\n3. Liquide refroidissement\n4. Moteur soufflante\n5. Bougies prechauffage\n6. Balais essuie-glace\n7. Capteurs ABS", 'es' => "1. Neumaticos de invierno\n2. Bateria\n3. Refrigerante\n4. Motor del soplador\n5. Bujias incandescentes\n6. Escobillas\n7. Sensores ABS"],
            'category_id' => $cat['maintenance']->id,
            'author_id' => $author->id,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(20),
        ]);
    }

    private function post8($cat, $author): BlogPost
    {
        return new BlogPost([
            'title' => ['en' => 'Genuine vs Aftermarket — Why OEM Saves You Money', 'de' => 'Original vs. Nachruestung — OEM spart Geld', 'lt' => 'Originalios vs alternatyvios — Kodel OEM sutaupo', 'fr' => 'OEM vs Aftermarket — Pourquoi economiser', 'es' => 'OEM vs Aftermarket — Por que ahorrar'],
            'slug' => 'genuine-vs-aftermarket-why-oem-saves-money',
            'excerpt' => ['en' => 'Cost analysis showing why genuine OEM parts deliver better value over the full ownership lifecycle.', 'de' => 'Kostenanalyse: OEM-Teile bieten bessere Wertbestaendigkeit.', 'lt' => 'Sanaudu analize: OEM dalys uztikrina geresne verte.', 'fr' => 'Analyse des couts: les pieces OEM offrent un meilleur rapport qualite-prix.', 'es' => 'Analisis: las piezas OEM ofrecen mejor valor.'],
            'content' => ['en' => "## True Cost of Parts\n\nUpfront price is only part of the equation.\n\n### Longevity\n- OEM brake pads: 50,000-70,000 km\n- Aftermarket: 30,000-45,000 km\n- OEM suspension: 80,000-100,000 km\n- Aftermarket: 40,000-60,000 km\n\n### Hidden Aftermarket Costs\n- Double labour from premature failure\n- Vehicle downtime\n- Compatibility issues\n- 10-15% lower resale value\n\n### OeParts Advantage\n\nPrices up to 40% below dealer list with 100% OEM authenticity.", 'de' => "## Wahre Kosten\n\nOEM Bremsbelaege: 50.000-70.000 km. Nachruestung: 30.000-45.000 km.\n\n## Versteckte Kosten\nDoppelte Arbeit, Ausfallzeiten, niedrigerer Wiederverkaufswert.\n\n## OeParts\nBis zu 40% unter Haendlerpreisen.", 'lt' => "## Tikroji kaina\n\nOEM kaladeles: 50.000-70.000 km. Alternatyvios: 30.000-45.000 km.\n\n## Pasislepcios islaidos\nDvigubas darbas, prastovos, mazesne perpardavimo verte.", 'fr' => "## Cout reel\n\nPlaquettes OEM: 50.000-70.000 km. Aftermarket: 30.000-45.000 km.\n\n## Couts caches\nDouble main-d oeuvre, immobilisation, valeur de revente reduite.", 'es' => "## Costo real\n\nPastillas OEM: 50.000-70.000 km. Aftermarket: 30.000-45.000 km.\n\n## Costos ocultos\nDoble mano de obra, tiempo de inactividad, menor valor de reventa."],
            'category_id' => $cat['oem-tips']->id,
            'author_id' => $author->id,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(23),
        ]);
    }

    private function post9($cat, $author): BlogPost
    {
        return new BlogPost([
            'title' => ['en' => 'How to Read Your VIN — Decode Vehicle Specs', 'de' => 'FIN entschluesseln — Fahrzeugspezifikationen', 'lt' => 'Kaip skaityti VIN — automobilio specifikacijos', 'fr' => 'Comment lire votre VIN', 'es' => 'Como leer su VIN'],
            'slug' => 'how-to-read-your-vin-decode-vehicle-specs',
            'excerpt' => ['en' => 'Learn to decode the 17-character VIN for guaranteed part compatibility.', 'de' => 'Die 17-stellige FIN entschluesseln.', 'lt' => 'Issifruokite 17 simboliu VIN.', 'fr' => 'Decodez le VIN de 17 caracteres.', 'es' => 'Decodifique el VIN de 17 caracteres.'],
            'content' => ['en' => "## Mastering the VIN\n\nThe VIN is your most powerful tool for part compatibility.\n\n### VIN Structure (17 characters)\n\n- Positions 1-3: World Manufacturer Identifier (e.g. WBA = BMW)\n- Positions 4-8: Vehicle attributes\n- Position 9: Check digit\n- Position 10: Model year\n- Position 11: Assembly plant\n- Positions 12-17: Production sequence\n\n### Where to Find Your VIN\n\n- Dashboard (driver side, through windshield)\n- Driver door jamb sticker\n- Registration documents\n\n### Use with OeParts\nEnter your VIN to automatically filter compatible OEM parts across 5M+ OEM numbers.", 'de' => "## FIN verstehen\n\nPosition 1-3: Herstellercode, 4-8: Merkmale, 10: Baujahr, 12-17: Seriennummer.\n\n## FIN finden\nArmaturenbrett, Tuerpfosten, Fahrzeugpapiere.\n\n## Mit OeParts nutzen\nFIN eingeben fuer kompatible Teile.", 'lt' => "## VIN supratimas\n\n1-3: Gamintojo kodas, 4-8: Savybes, 10: Metai, 12-17: Serija.\n\n## VIN radimas\nPrietaisu skydelis, durys, dokumentai.\n\n## Su OeParts\nIveskite VIN automatiniam filtravimui.", 'fr' => "## Comprendre le VIN\n\n1-3: Identifiant constructeur, 4-8: Attributs, 10: Annee, 12-17: Serie.\n\n## Ou trouver le VIN\nTableau de bord, montant de porte, documents.\n\n## Avec OeParts\nEntrez votre VIN pour filtrer les pieces.", 'es' => "## Comprender el VIN\n\n1-3: Identificador, 4-8: Atributos, 10: Anio, 12-17: Serie.\n\n## Donde encontrar el VIN\nTablero, pilar puerta, documentos.\n\n## Con OeParts\nIngrese su VIN para filtrar piezas."],
            'category_id' => $cat['guides']->id,
            'author_id' => $author->id,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(26),
        ]);
    }

    private function post10($cat, $author): BlogPost
    {
        return new BlogPost([
            'title' => ['en' => 'EU Cross-Border Parts Shipping — Workshop Guide', 'de' => 'EU-weiter Teileversand — Werkstattfuehrer', 'lt' => 'ES tarpvalstybinis siuntimas — Dirbtuviu vadovas', 'fr' => 'Expedition transfrontaliere UE — Guide atelier', 'es' => 'Envio transfronterizo UE — Guia para talleres'],
            'slug' => 'eu-cross-border-parts-shipping-workshop-guide',
            'excerpt' => ['en' => 'Everything workshops need to know about cross-border OEM parts shipping in the EU.', 'de' => 'Alles zum grenzueberschreitenden OEM-Teileversand in der EU.', 'lt' => 'Viskas apie tarpvalstybini OEM daliu siuntima ES.', 'fr' => 'Tout savoir sur l expedition transfrontaliere de pieces OEM.', 'es' => 'Todo sobre el envio transfronterizo de piezas OEM en la UE.'],
            'content' => ['en' => "## Cross-Border Logistics\n\nShipping within the EU is customs-free across all 27 member states.\n\n### Delivery Options\n\n- Standard: 5-14 business days, tracked\n- Express: 1-3 business days, tracked\n- Priority: Next business day\n\n### Carriers\nDHL, DPD, GLS, FedEx, UPS — covering all EU countries including remote regions.\n\n### VAT\n- B2B: Reverse charge for VAT-registered businesses\n- B2C: Charged at destination country rate\n\n### Returns\n14-day return window across all EU destinations.", 'de' => "## EU-Logistik\n\nKein Zoll innerhalb der 27 EU-Staaten.\n\n## Versand\nStandard: 5-14 Tage. Express: 1-3 Tage. Priority: naechster Werktag.\n\n## Speditionen\nDHL, DPD, GLS, FedEx, UPS.\n\n## MwSt.\nB2B: Reverse Charge. B2C: Ziellandsatz.\n\n## Rueckgabe\n14 Tage in allen EU-Laendern.", 'lt' => "## ES logistika\n\nJokio muito tarp 27 ES valstybiu.\n\n## Pristatymas\nStandartinis: 5-14 d. Express: 1-3 d. Priority: kita darbo diena.\n\n## Vezejai\nDHL, DPD, GLS, FedEx, UPS.\n\n## PVM\nB2B: Reverse charge. B2C: pagal salies tarifa.", 'fr' => "## Logistique UE\n\nAucune douane dans l UE.\n\n## Livraison\nStandard: 5-14 jours. Express: 1-3 jours. Prioritaire: J+1.\n\n## Transporteurs\nDHL, DPD, GLS, FedEx, UPS.", 'es' => "## Logistica UE\n\nSin aduanas en la UE.\n\n## Entrega\nEstandar: 5-14 dias. Express: 1-3 dias. Prioritario: dia siguiente.\n\n## Transportistas\nDHL, DPD, GLS, FedEx, UPS."],
            'category_id' => $cat['workshop']->id,
            'author_id' => $author->id,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(30),
        ]);
    }
}
