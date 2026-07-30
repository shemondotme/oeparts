<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Seeds the CMS pages linked from the storefront footer — /about,
 * /privacy-policy, /terms-of-service, /cookie-policy, /returns-policy and
 * /shipping-information — so none of them 404. The footer's legal-links row
 * (resources/views/components/footer.blade.php) links to returns-policy and
 * shipping-information specifically; those two were missing from this
 * seeder entirely (confirmed live 404s) until this pass added them.
 *
 * Content is intentionally generic boilerplate — editable from the admin
 * CMS once the team has their final copy. Returns/shipping figures here
 * (14-day window, DHL/DPD/GLS) are pulled from the real seeded values
 * (orders.refund_window_days, CarriersSeeder) so the copy matches what the
 * rest of the site actually promises, not invented numbers.
 */
class CmsFooterPagesSeeder extends Seeder
{
    public function run(): void
    {
        $creatorId = Admin::query()->orderBy('id')->value('id') ?? 1;

        // firstOrCreate (not updateOrCreate): only creates a page whose slug
        // doesn't exist yet — never overwrites title/content an admin has since
        // edited via the CMS. Safe to run unattended on every future update.
        foreach ($this->pages() as $slug => $payload) {
            Page::firstOrCreate(
                ['slug' => $slug],
                array_merge($payload, [
                    'status' => ContentStatus::Published,
                    'published_at' => now(),
                    'is_footer' => true,
                    'is_header' => false,
                    'is_homepage' => false,
                    'created_by' => $creatorId,
                ])
            );
        }
    }

    private function pages(): array
    {
        return [
            'about' => [
                'title' => [
                    'en' => 'About OeParts',
                    'de' => 'Über OeParts',
                    'lt' => 'Apie OeParts',
                    'fr' => 'À propos d’OeParts',
                    'es' => 'Acerca de OeParts',
                ],
                'meta_title' => [
                    'en' => 'About OeParts — the B2C & B2B marketplace for genuine OEM auto parts',
                    'de' => 'Über OeParts — der B2C- und B2B-Marktplatz für echte OEM-Autoteile',
                    'lt' => 'Apie „OeParts“ — B2C ir B2B rinka originalioms OEM automobilių dalims',
                    'fr' => 'À propos d\'OeParts — la marketplace B2C et B2B pour pièces auto OEM authentiques',
                    'es' => 'Acerca de OeParts — el mercado B2C y B2B de piezas de automóvil OEM genuinas',
                ],
                'meta_description' => [
                    'en' => 'OeParts is the marketplace for genuine OEM auto parts — for individual drivers and professional buyers alike. Cross-reference by OEM number, compare suppliers, ship across the EU.',
                    'de' => 'OeParts ist der Marktplatz für echte OEM-Autoteile — für Privatkunden und professionelle Käufer gleichermaßen. Kreuzreferenz nach OEM-Nummer, Lieferantenvergleich, Versand in die gesamte EU.',
                    'lt' => '„OeParts“ — rinka originalioms OEM automobilių dalims, skirta tiek privatiems asmenims, tiek profesionaliems pirkėjams. Kryžminis sutikrinimas pagal OEM numerį, tiekėjų palyginimas, pristatymas visoje ES.',
                    'fr' => 'OeParts est la marketplace pour pièces auto OEM authentiques — pour les particuliers comme pour les acheteurs professionnels. Recoupement par numéro OEM, comparaison de fournisseurs, livraison dans toute l\'UE.',
                    'es' => 'OeParts es el mercado de piezas de automóvil OEM genuinas — para particulares y compradores profesionales por igual. Referencia cruzada por número OEM, comparación de proveedores, envíos a toda la UE.',
                ],
                'content' => [
                    'en' => <<<'HTML'
<p class="lead">OeParts is a specialist marketplace for genuine OEM auto parts — built for everyone from individual drivers to workshops, dealers and fleet buyers across Europe. We help you find, verify and source genuine OEM auto parts without the catalogue guesswork.</p>

<h2>Our mission</h2>
<p>Every day, millions of OEM part numbers change hands across Europe. Cross-referencing them, verifying authenticity, and getting a fair price still takes far too much time. OeParts exists to remove that friction — one searchable, verified catalogue, backed by vetted suppliers and transparent logistics.</p>

<h2>What we do</h2>
<ul>
    <li><strong>Cross-reference by OEM number.</strong> Normalised across manufacturers and aftermarket equivalents.</li>
    <li><strong>Verify supplier authenticity.</strong> Every listing is tied to a verified merchant with documented sourcing.</li>
    <li><strong>Quote transparently.</strong> Part inquiry and bulk-quote flows surface true landed cost, not ad-hoc markups.</li>
    <li><strong>Ship across the EU.</strong> Partner carriers cover all EU member states plus Norway, Switzerland and the UK.</li>
</ul>

<h2>Who we serve</h2>
<p>Individual drivers and car owners sourcing a single genuine part, alongside independent workshops, franchise dealers, fleet operators, wholesalers, and export buyers. Whether you need one part or purchase OEM parts in volume, we can cut your search time, quoting cycle and landed cost.</p>

<h2>Get in touch</h2>
<p>Have a sourcing requirement we should know about? Use the contact desk and we’ll route you to the right supplier.</p>
HTML,
                    'de' => <<<'HTML'
<p class="lead">OeParts ist ein spezialisierter Marktplatz für echte OEM-Autoteile — für alle, vom einzelnen Autofahrer bis zu Werkstätten, Händlern und Flottenbetreibern in ganz Europa. Wir helfen Ihnen, echte OEM-Autoteile zu finden, zu verifizieren und zu beziehen — ohne Katalog-Rätselraten.</p>

<h2>Unsere Mission</h2>
<p>Jeden Tag wechseln Millionen von OEM-Teilenummern in ganz Europa den Besitzer. Sie zu kreuzreferenzieren, ihre Echtheit zu prüfen und einen fairen Preis zu erzielen, kostet immer noch viel zu viel Zeit. OeParts wurde geschaffen, um diese Reibung zu beseitigen — ein durchsuchbarer, verifizierter Katalog, unterstützt von geprüften Lieferanten und transparenter Logistik.</p>

<h2>Was wir tun</h2>
<ul>
    <li><strong>Kreuzreferenzierung nach OEM-Nummer.</strong> Standardisiert über Hersteller und Aftermarket-Äquivalente hinweg.</li>
    <li><strong>Lieferantenauthentizität prüfen.</strong> Jedes Angebot ist an einen verifizierten Händler mit dokumentierter Beschaffung gebunden.</li>
    <li><strong>Transparent kalkulieren.</strong> Teileanfragen und Sammelangebote zeigen die tatsächlichen Gesamtkosten, keine willkürlichen Aufschläge.</li>
    <li><strong>Versand in die gesamte EU.</strong> Partnerspediteure decken alle EU-Mitgliedstaaten sowie Norwegen, die Schweiz und das Vereinigte Königreich ab.</li>
</ul>

<h2>Wem wir dienen</h2>
<p>Privatpersonen und Autobesitzer auf der Suche nach einem einzelnen Originalteil, ebenso wie unabhängige Werkstätten, Vertragshändler, Flottenbetreiber, Großhändler und Exportkäufer. Ob Sie ein einzelnes Teil benötigen oder OEM-Teile in großen Mengen kaufen — wir können Ihre Suchzeit, Ihren Angebotszyklus und Ihre Gesamtkosten senken.</p>

<h2>Kontaktieren Sie uns</h2>
<p>Haben Sie einen Beschaffungsbedarf, von dem wir wissen sollten? Nutzen Sie unser Kontaktformular und wir leiten Sie an den richtigen Lieferanten weiter.</p>
HTML,
                    'lt' => <<<'HTML'
<p class="lead">„OeParts“ yra specializuota rinka originalioms OEM automobilių dalims — skirta visiems: nuo pavienių vairuotojų iki dirbtuvių, prekiautojų ir transporto parkų operatorių visoje Europoje. Padedame rasti, patikrinti ir įsigyti originalias OEM automobilių dalis be katalogų spėliojimo.</p>

<h2>Mūsų misija</h2>
<p>Kasdien visoje Europoje keičia savininkus milijonai OEM dalių numerių. Jų kryžminis sutikrinimas, autentiškumo patvirtinimas ir sąžiningos kainos gavimas vis dar užima per daug laiko. „OeParts“ egzistuoja tam, kad pašalintų šį trikdį — vienas paieškos, patikrintas katalogas, paremtas patikrintais tiekėjais ir skaidria logistika.</p>

<h2>Ką mes darome</h2>
<ul>
    <li><strong>Kryžminis sutikrinimas pagal OEM numerį.</strong> Standartizuota tarp gamintojų ir analogiškų dalių atitikmenų.</li>
    <li><strong>Tiekėjų autentiškumo patvirtinimas.</strong> Kiekvienas skelbimas susietas su patikrintu prekybininku, turinčiu dokumentuotą tiekimo šaltinį.</li>
    <li><strong>Skaidrus kainų pateikimas.</strong> Dalių užklausų ir didmeninių pasiūlymų procesai atskleidžia tikrąją bendrą kainą, o ne savavališkus antkainius.</li>
    <li><strong>Pristatymas visoje ES.</strong> Partnerių vežėjai aptarnauja visas ES valstybes nares bei Norvegiją, Šveicariją ir Jungtinę Karalystę.</li>
</ul>

<h2>Kam mes dirbame</h2>
<p>Privatiems asmenims ir automobilių savininkams, ieškantiems vienos originalios dalies, taip pat nepriklausomoms dirbtuvėms, prekės ženklo atstovams, transporto parkų operatoriams, didmenininkams ir eksporto pirkėjams. Nesvarbu, ar jums reikia vienos dalies, ar perkate OEM dalis dideliais kiekiais — galime sutrumpinti jūsų paieškos laiką, pasiūlymo ciklą ir sumažinti bendrą kainą.</p>

<h2>Susisiekite</h2>
<p>Turite tiekimo poreikį, apie kurį turėtume žinoti? Naudokitės kontaktų skyriumi, ir mes nukreipsime jus pas tinkamą tiekėją.</p>
HTML,
                    'fr' => <<<'HTML'
<p class="lead">OeParts est une marketplace spécialisée dans les pièces auto OEM authentiques — conçue pour tous, du particulier aux ateliers, concessionnaires et gestionnaires de flottes à travers l'Europe. Nous vous aidons à trouver, vérifier et vous approvisionner en pièces automobiles OEM authentiques sans devinettes de catalogue.</p>

<h2>Notre mission</h2>
<p>Chaque jour, des millions de références OEM changent de mains à travers l'Europe. Les recouper, vérifier leur authenticité et obtenir un prix juste prend encore beaucoup trop de temps. OeParts existe pour éliminer cette friction — un catalogue consultable et vérifié, soutenu par des fournisseurs contrôlés et une logistique transparente.</p>

<h2>Ce que nous faisons</h2>
<ul>
    <li><strong>Recoupement par numéro OEM.</strong> Normalisé entre les fabricants et les équivalents du marché secondaire.</li>
    <li><strong>Vérification de l'authenticité des fournisseurs.</strong> Chaque annonce est liée à un marchand vérifié avec un approvisionnement documenté.</li>
    <li><strong>Devis transparents.</strong> Les demandes de pièces et les devis groupés révèlent le coût réel rendu, pas des majorations arbitraires.</li>
    <li><strong>Livraison dans toute l'UE.</strong> Nos transporteurs partenaires couvrent tous les États membres de l'UE ainsi que la Norvège, la Suisse et le Royaume-Uni.</li>
</ul>

<h2>Qui nous servons</h2>
<p>Particuliers et propriétaires de véhicules à la recherche d'une pièce d'origine, ainsi qu'ateliers indépendants, concessionnaires de réseau, gestionnaires de flottes, grossistes et acheteurs à l'export. Que vous ayez besoin d'une seule pièce ou que vous achetiez des pièces OEM en volume, nous pouvons réduire votre temps de recherche, votre cycle de devis et votre coût rendu.</p>

<h2>Contactez-nous</h2>
<p>Vous avez un besoin d'approvisionnement dont nous devrions être informés ? Utilisez notre service de contact et nous vous orienterons vers le bon fournisseur.</p>
HTML,
                    'es' => <<<'HTML'
<p class="lead">OeParts es un mercado especializado en piezas de automóvil OEM genuinas — creado para todos, desde conductores particulares hasta talleres, concesionarios y operadores de flotas en toda Europa. Le ayudamos a encontrar, verificar y adquirir piezas de automóvil OEM genuinas sin conjeturas de catálogo.</p>

<h2>Nuestra misión</h2>
<p>Cada día, millones de números de pieza OEM cambian de manos en toda Europa. Cotejarlos, verificar su autenticidad y obtener un precio justo sigue llevando demasiado tiempo. OeParts existe para eliminar esa fricción — un catálogo verificado y con capacidad de búsqueda, respaldado por proveedores comprobados y una logística transparente.</p>

<h2>Qué hacemos</h2>
<ul>
    <li><strong>Referencia cruzada por número OEM.</strong> Normalizada entre fabricantes y equivalentes del mercado de recambios.</li>
    <li><strong>Verificación de la autenticidad del proveedor.</strong> Cada anuncio está vinculado a un comerciante verificado con abastecimiento documentado.</li>
    <li><strong>Presupuestos transparentes.</strong> Los procesos de consulta de piezas y presupuestos por volumen muestran el coste real puesto en destino, no márgenes arbitrarios.</li>
    <li><strong>Envíos a toda la UE.</strong> Nuestros transportistas asociados cubren todos los estados miembros de la UE, además de Noruega, Suiza y el Reino Unido.</li>
</ul>

<h2>A quién servimos</h2>
<p>Particulares y propietarios de vehículos que buscan una pieza original, así como talleres independientes, concesionarios de franquicia, operadores de flotas, mayoristas y compradores de exportación. Tanto si necesita una sola pieza como si compra piezas OEM en volumen, podemos reducir su tiempo de búsqueda, su ciclo de presupuesto y su coste puesto en destino.</p>

<h2>Contacte con nosotros</h2>
<p>¿Tiene una necesidad de abastecimiento que deberíamos conocer? Utilice nuestro servicio de contacto y le dirigiremos al proveedor adecuado.</p>
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
                    'en' => 'Privacy Policy — OeParts',
                    'de' => 'Datenschutzerklärung — OeParts',
                    'lt' => 'Privatumo politika — „OeParts“',
                    'fr' => 'Politique de confidentialité — OeParts',
                    'es' => 'Política de privacidad — OeParts',
                ],
                'meta_description' => [
                    'en' => 'How OeParts collects, uses, stores and protects personal data under GDPR and EU regulations.',
                    'de' => 'Wie OeParts personenbezogene Daten gemäß DSGVO und EU-Vorschriften erhebt, nutzt, speichert und schützt.',
                    'lt' => 'Kaip „OeParts“ renka, naudoja, saugo ir apsaugo asmens duomenis pagal BDAR ir ES teisės aktus.',
                    'fr' => 'Comment OeParts collecte, utilise, stocke et protège les données personnelles conformément au RGPD et à la réglementation européenne.',
                    'es' => 'Cómo OeParts recopila, utiliza, almacena y protege los datos personales conforme al RGPD y la normativa de la UE.',
                ],
                'content' => [
                    'en' => <<<'HTML'
<p class="lead">This policy explains what personal data OeParts collects, why we collect it, how long we keep it, and the rights you have under the EU General Data Protection Regulation (GDPR).</p>

<h2>1. Data controller</h2>
<p>The data controller for this website is OeParts. You can reach our privacy team via the contact desk linked in the footer.</p>

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
                    'de' => <<<'HTML'
<p class="lead">Diese Richtlinie erklärt, welche personenbezogenen Daten OeParts erhebt, warum wir sie erheben, wie lange wir sie speichern und welche Rechte Sie nach der EU-Datenschutz-Grundverordnung (DSGVO) haben.</p>

<h2>1. Verantwortlicher</h2>
<p>Der Verantwortliche für diese Website ist OeParts. Sie erreichen unser Datenschutzteam über das im Footer verlinkte Kontaktformular.</p>

<h2>2. Welche Daten wir erheben</h2>
<ul>
    <li><strong>Kontodaten</strong> — Name, E-Mail, Passwort-Hash, bevorzugte Sprache.</li>
    <li><strong>Bestelldaten</strong> — Rechnungs- und Lieferadressen, gekaufte Artikel, Rechnungen.</li>
    <li><strong>Zahlungsmetadaten</strong> — Kartennetzwerk, letzte vier Ziffern, Transaktionsreferenzen. Vollständige Kartendaten erreichen unsere Server nie; sie werden von unseren PCI-konformen Zahlungsdienstleistern verarbeitet.</li>
    <li><strong>Nutzungsdaten</strong> — IP-Adresse, Browser, aufgerufene Seiten, Suchanfragen. Werden ausschließlich zum Betrieb und zur Verbesserung des Dienstes verwendet.</li>
</ul>

<h2>3. Rechtsgrundlagen</h2>
<p>Wir verarbeiten personenbezogene Daten auf folgenden Rechtsgrundlagen: Vertragserfüllung (bei Bestellungen), berechtigtes Interesse (Betrugsprävention, Serviceverbesserung), Einwilligung (Marketing-E-Mails, nicht notwendige Cookies) und rechtliche Verpflichtung (Steuer-, Buchhaltungs- und EU-Verbraucherrecht).</p>

<h2>4. Aufbewahrung</h2>
<p>Bestelldaten werden für den gesetzlich vorgeschriebenen Zeitraum nach EU-Steuerrecht aufbewahrt. Kontodaten werden bis zur Löschung Ihres Kontos aufbewahrt und danach innerhalb von 30 Tagen anonymisiert, sofern keine längere Aufbewahrung gesetzlich vorgeschrieben ist.</p>

<h2>5. Ihre Rechte</h2>
<ul>
    <li>Recht auf Auskunft über die von uns gespeicherten personenbezogenen Daten.</li>
    <li>Recht auf Berichtigung unrichtiger Daten.</li>
    <li>Recht auf Löschung („Recht auf Vergessenwerden“).</li>
    <li>Recht auf Einschränkung oder Widerspruch der Verarbeitung.</li>
    <li>Recht auf Datenübertragbarkeit.</li>
    <li>Recht auf Beschwerde bei einer Aufsichtsbehörde.</li>
</ul>

<h2>6. Internationale Übermittlungen</h2>
<p>Werden Daten außerhalb des Europäischen Wirtschaftsraums übermittelt, stützen wir uns auf EU-Standardvertragsklauseln und zusätzliche Schutzmaßnahmen, soweit erforderlich.</p>

<h2>7. Änderungen</h2>
<p>Wir können diese Richtlinie von Zeit zu Zeit aktualisieren. Das oben angezeigte Überarbeitungsdatum spiegelt die letzte Änderung wider.</p>
HTML,
                    'lt' => <<<'HTML'
<p class="lead">Ši politika paaiškina, kokius asmens duomenis renka „OeParts“, kodėl juos renkame, kiek laiko juos saugome ir kokias teises turite pagal ES Bendrąjį duomenų apsaugos reglamentą (BDAR).</p>

<h2>1. Duomenų valdytojas</h2>
<p>Šios svetainės duomenų valdytojas yra „OeParts“. Su mūsų privatumo komanda galite susisiekti per kontaktų skyrių, nurodytą poraštėje.</p>

<h2>2. Kokius duomenis renkame</h2>
<ul>
    <li><strong>Paskyros duomenys</strong> — vardas, el. paštas, slaptažodžio maiša, pageidaujama kalba.</li>
    <li><strong>Užsakymo duomenys</strong> — atsiskaitymo ir pristatymo adresai, įsigytos prekės, sąskaitos faktūros.</li>
    <li><strong>Mokėjimo metaduomenys</strong> — kortelės tinklas, paskutiniai keturi skaitmenys, operacijų nuorodos. Visi kortelės duomenys niekada nepasiekia mūsų serverių; juos tvarko mūsų PCI reikalavimus atitinkantys mokėjimo paslaugų teikėjai.</li>
    <li><strong>Naudojimo duomenys</strong> — IP adresas, naršyklė, peržiūrėti puslapiai, paieškos užklausos. Naudojami tik paslaugai teikti ir tobulinti.</li>
</ul>

<h2>3. Teisiniai pagrindai</h2>
<p>Asmens duomenis tvarkome remdamiesi šiais teisiniais pagrindais: sutarties vykdymu (pateikus užsakymą), teisėtu interesu (sukčiavimo prevencija, paslaugos tobulinimas), sutikimu (rinkodaros el. laiškai, neprivalomi slapukai) ir teisine prievole (mokesčių, apskaitos ir ES vartotojų teisė).</p>

<h2>4. Saugojimas</h2>
<p>Užsakymų įrašai saugomi ES mokesčių teisės nustatytą privalomą laikotarpį. Paskyros duomenys saugomi tol, kol ištrinsite savo paskyrą, po to jie anonimizuojami per 30 dienų, išskyrus atvejus, kai ilgesnis saugojimas privalomas pagal įstatymus.</p>

<h2>5. Jūsų teisės</h2>
<ul>
    <li>Teisė susipažinti su jūsų asmens duomenimis, kuriuos saugome.</li>
    <li>Teisė ištaisyti netikslius duomenis.</li>
    <li>Teisė reikalauti ištrinti duomenis („teisė būti pamirštam“).</li>
    <li>Teisė apriboti ar prieštarauti duomenų tvarkymui.</li>
    <li>Teisė į duomenų perkeliamumą.</li>
    <li>Teisė pateikti skundą priežiūros institucijai.</li>
</ul>

<h2>6. Tarptautiniai duomenų perdavimai</h2>
<p>Kai duomenys perduodami už Europos ekonominės erdvės ribų, remiamės ES standartinėmis sutartinėmis sąlygomis ir, jei reikia, papildomomis apsaugos priemonėmis.</p>

<h2>7. Pakeitimai</h2>
<p>Retkarčiais galime atnaujinti šią politiką. Aukščiau nurodyta peržiūros data atspindi paskutinį pakeitimą.</p>
HTML,
                    'fr' => <<<'HTML'
<p class="lead">Cette politique explique quelles données personnelles OeParts collecte, pourquoi nous les collectons, combien de temps nous les conservons, et les droits dont vous disposez en vertu du Règlement général sur la protection des données de l'UE (RGPD).</p>

<h2>1. Responsable du traitement</h2>
<p>Le responsable du traitement pour ce site est OeParts. Vous pouvez contacter notre équipe chargée de la confidentialité via le service de contact accessible depuis le pied de page.</p>

<h2>2. Quelles données nous collectons</h2>
<ul>
    <li><strong>Données de compte</strong> — nom, e-mail, hachage du mot de passe, langue préférée.</li>
    <li><strong>Données de commande</strong> — adresses de facturation et de livraison, articles achetés, factures.</li>
    <li><strong>Métadonnées de paiement</strong> — réseau de la carte, quatre derniers chiffres, références de transaction. Les détails complets de la carte ne transitent jamais par nos serveurs ; ils sont traités par nos prestataires de paiement conformes PCI.</li>
    <li><strong>Données d'utilisation</strong> — adresse IP, navigateur, pages consultées, requêtes de recherche. Utilisées uniquement pour exploiter et améliorer le service.</li>
</ul>

<h2>3. Bases légales</h2>
<p>Nous traitons les données personnelles sur les bases légales suivantes : exécution du contrat (lors d'une commande), intérêt légitime (prévention de la fraude, amélioration du service), consentement (e-mails marketing, cookies non essentiels) et obligation légale (droit fiscal, comptable et de la consommation de l'UE).</p>

<h2>4. Conservation</h2>
<p>Les données de commande sont conservées pendant la durée légale requise par le droit fiscal de l'UE. Les données de compte sont conservées jusqu'à la suppression de votre compte, après quoi elles sont anonymisées sous 30 jours, sauf lorsqu'une conservation plus longue est légalement requise.</p>

<h2>5. Vos droits</h2>
<ul>
    <li>Droit d'accès aux données personnelles que nous détenons à votre sujet.</li>
    <li>Droit de rectification des données inexactes.</li>
    <li>Droit à l'effacement (« droit à l'oubli »).</li>
    <li>Droit de limiter ou de s'opposer au traitement.</li>
    <li>Droit à la portabilité des données.</li>
    <li>Droit d'introduire une réclamation auprès d'une autorité de contrôle.</li>
</ul>

<h2>6. Transferts internationaux</h2>
<p>Lorsque des données sont transférées en dehors de l'Espace économique européen, nous nous appuyons sur les clauses contractuelles types de l'UE et des garanties supplémentaires lorsque nécessaire.</p>

<h2>7. Modifications</h2>
<p>Nous pouvons mettre à jour cette politique de temps à autre. La date de révision affichée ci-dessus reflète la modification la plus récente.</p>
HTML,
                    'es' => <<<'HTML'
<p class="lead">Esta política explica qué datos personales recopila OeParts, por qué los recopilamos, cuánto tiempo los conservamos y los derechos que le asisten conforme al Reglamento General de Protección de Datos de la UE (RGPD).</p>

<h2>1. Responsable del tratamiento</h2>
<p>El responsable del tratamiento de este sitio web es OeParts. Puede contactar con nuestro equipo de privacidad a través del servicio de contacto enlazado en el pie de página.</p>

<h2>2. Qué datos recopilamos</h2>
<ul>
    <li><strong>Datos de cuenta</strong> — nombre, correo electrónico, hash de contraseña, idioma preferido.</li>
    <li><strong>Datos de pedido</strong> — direcciones de facturación y envío, artículos comprados, facturas.</li>
    <li><strong>Metadatos de pago</strong> — red de la tarjeta, últimos cuatro dígitos, referencias de transacción. Los datos completos de la tarjeta nunca llegan a nuestros servidores; son gestionados por nuestros proveedores de pago conformes con PCI.</li>
    <li><strong>Datos de uso</strong> — dirección IP, navegador, páginas visitadas, búsquedas realizadas. Se utilizan únicamente para operar y mejorar el servicio.</li>
</ul>

<h2>3. Bases legales</h2>
<p>Tratamos los datos personales sobre las siguientes bases legales: ejecución del contrato (al realizar un pedido), interés legítimo (prevención del fraude, mejora del servicio), consentimiento (correos de marketing, cookies no esenciales) y obligación legal (normativa fiscal, contable y de consumo de la UE).</p>

<h2>4. Conservación</h2>
<p>Los registros de pedidos se conservan durante el período legal exigido por la normativa fiscal de la UE. Los datos de la cuenta se conservan hasta que elimine su cuenta, momento en el que se anonimizan en un plazo de 30 días, salvo que la ley exija una conservación más prolongada.</p>

<h2>5. Sus derechos</h2>
<ul>
    <li>Derecho de acceso a los datos personales que tenemos sobre usted.</li>
    <li>Derecho de rectificación de datos inexactos.</li>
    <li>Derecho de supresión ("derecho al olvido").</li>
    <li>Derecho a limitar u oponerse al tratamiento.</li>
    <li>Derecho a la portabilidad de los datos.</li>
    <li>Derecho a presentar una reclamación ante una autoridad de control.</li>
</ul>

<h2>6. Transferencias internacionales</h2>
<p>Cuando los datos se transfieren fuera del Espacio Económico Europeo, nos basamos en las Cláusulas Contractuales Tipo de la UE y garantías adicionales cuando sea necesario.</p>

<h2>7. Cambios</h2>
<p>Podemos actualizar esta política de vez en cuando. La fecha de revisión mostrada arriba refleja el cambio más reciente.</p>
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
                    'en' => 'Terms of Service — OeParts',
                    'de' => 'Nutzungsbedingungen — OeParts',
                    'lt' => 'Paslaugų teikimo sąlygos — „OeParts“',
                    'fr' => 'Conditions d\'utilisation — OeParts',
                    'es' => 'Términos del servicio — OeParts',
                ],
                'meta_description' => [
                    'en' => 'The rules that govern your use of the OeParts marketplace, including account, ordering, payment and liability terms.',
                    'de' => 'Die Regeln für die Nutzung des OeParts-Marktplatzes, einschließlich Konto-, Bestell-, Zahlungs- und Haftungsbedingungen.',
                    'lt' => 'Taisyklės, reglamentuojančios „OeParts“ rinkos naudojimą, įskaitant paskyros, užsakymo, mokėjimo ir atsakomybės sąlygas.',
                    'fr' => 'Les règles régissant votre utilisation de la marketplace OeParts, y compris les conditions de compte, de commande, de paiement et de responsabilité.',
                    'es' => 'Las normas que rigen el uso del mercado OeParts, incluidas las condiciones de cuenta, pedido, pago y responsabilidad.',
                ],
                'content' => [
                    'en' => <<<'HTML'
<p class="lead">By accessing or using OeParts you agree to these Terms of Service. Please read them carefully. If you do not accept any part of these terms, please stop using the service.</p>

<h2>1. Eligibility &amp; accounts</h2>
<p>OeParts is open to both individual consumers and business/professional buyers. You confirm that you have the authority to act on behalf of the account you register — whether as an individual or on behalf of a business — and that the information you provide is accurate.</p>

<h2>2. Orders &amp; contract formation</h2>
<p>Placing an order constitutes an offer to purchase. A binding contract is formed only once we confirm acceptance of your order. We reserve the right to refuse or cancel orders in cases of pricing errors, supply unavailability or suspected fraud.</p>

<h2>3. Pricing &amp; payment</h2>
<p>Prices are shown inclusive or exclusive of VAT as indicated at checkout. Payment is due at the time of order unless a credit arrangement has been agreed in writing. Bank transfer orders ship once funds are cleared.</p>

<h2>4. Shipping &amp; delivery</h2>
<p>Delivery estimates are provided in good faith but are not guaranteed. Risk passes to the buyer upon delivery to the shipping carrier. You are responsible for inspecting the goods on arrival. Shipping rates shown at checkout are fixed rates for standard-size parcels. Where an ordered part is classified by the carrier as oversized or heavy freight due to its size, weight or dimensions, an additional shipping surcharge may apply beyond the rate shown at checkout. Any such surcharge will be invoiced to you separately after dispatch, and by placing an order you acknowledge and agree that this surcharge may apply.</p>

<h2>5. Returns &amp; refunds</h2>
<p>Returns are accepted within the window shown on your order confirmation, subject to the part being unused, in original packaging and not subject to a no-return exclusion. Consumables, sealed electronic units and special-order parts may be excluded.</p>

<h2>6. Liability</h2>
<p>To the extent permitted by law, OeParts’s aggregate liability for any claim arising from these terms is limited to the price paid for the part(s) giving rise to the claim. We are not liable for indirect or consequential losses.</p>

<h2>7. Intellectual property</h2>
<p>All OeParts content, trademarks and software are the property of OeParts or its licensors. Third-party trademarks appear solely for identification and remain the property of their respective owners.</p>

<h2>8. Governing law</h2>
<p>These terms are governed by the laws of the jurisdiction in which OeParts is established. Any dispute is subject to the exclusive jurisdiction of the competent courts in that jurisdiction, save where consumer law grants you protection in another jurisdiction.</p>

<h2>9. Changes</h2>
<p>We may amend these terms from time to time. Material changes will be communicated via email or a notice on the site.</p>
HTML,
                    'de' => <<<'HTML'
<p class="lead">Durch den Zugriff auf oder die Nutzung von OeParts stimmen Sie diesen Nutzungsbedingungen zu. Bitte lesen Sie sie sorgfältig. Wenn Sie einen Teil dieser Bedingungen nicht akzeptieren, stellen Sie bitte die Nutzung des Dienstes ein.</p>

<h2>1. Berechtigung &amp; Konten</h2>
<p>OeParts steht sowohl Privatkunden als auch Unternehmen und professionellen Käufern offen. Sie bestätigen, dass Sie befugt sind, im Namen des von Ihnen registrierten Kontos zu handeln — sei es als Privatperson oder im Namen eines Unternehmens —, und dass die von Ihnen bereitgestellten Informationen zutreffend sind.</p>

<h2>2. Bestellungen &amp; Vertragsschluss</h2>
<p>Die Aufgabe einer Bestellung stellt ein Kaufangebot dar. Ein verbindlicher Vertrag kommt erst zustande, wenn wir die Annahme Ihrer Bestellung bestätigen. Wir behalten uns das Recht vor, Bestellungen bei Preisfehlern, Lieferengpässen oder Verdacht auf Betrug abzulehnen oder zu stornieren.</p>

<h2>3. Preise &amp; Zahlung</h2>
<p>Preise werden inklusive oder exklusive Mehrwertsteuer angezeigt, wie an der Kasse angegeben. Die Zahlung ist zum Zeitpunkt der Bestellung fällig, sofern nicht schriftlich eine Kreditvereinbarung getroffen wurde. Bestellungen per Banküberweisung werden nach Zahlungseingang versandt.</p>

<h2>4. Versand &amp; Lieferung</h2>
<p>Lieferschätzungen werden nach bestem Wissen angegeben, sind jedoch nicht garantiert. Das Risiko geht bei Übergabe an den Versanddienstleister auf den Käufer über. Sie sind verantwortlich für die Prüfung der Ware bei Ankunft. Die beim Checkout angezeigten Versandkosten sind Festpreise für Standardpakete. Wird eine bestellte Ware vom Transportunternehmen aufgrund ihrer Größe, ihres Gewichts oder ihrer Abmessungen als übergroße oder schwere Fracht eingestuft, kann ein zusätzlicher Versandzuschlag über den beim Checkout angezeigten Betrag hinaus anfallen. Ein solcher Zuschlag wird Ihnen nach dem Versand separat in Rechnung gestellt; mit der Bestellung erkennen Sie an und stimmen zu, dass dieser Zuschlag anfallen kann.</p>

<h2>5. Rückgaben &amp; Erstattungen</h2>
<p>Rückgaben werden innerhalb des in Ihrer Bestellbestätigung angegebenen Zeitraums akzeptiert, sofern das Teil unbenutzt, in der Originalverpackung und nicht von einem Rückgabeausschluss betroffen ist. Verbrauchsmaterialien, versiegelte elektronische Einheiten und Sonderbestellungen können ausgeschlossen sein.</p>

<h2>6. Haftung</h2>
<p>Soweit gesetzlich zulässig, ist die Gesamthaftung von OeParts für Ansprüche aus diesen Bedingungen auf den für das/die betreffende(n) Teil(e) gezahlten Preis begrenzt. Wir haften nicht für indirekte oder Folgeschäden.</p>

<h2>7. Geistiges Eigentum</h2>
<p>Alle Inhalte, Marken und Software von OeParts sind Eigentum von OeParts oder seinen Lizenzgebern. Marken Dritter erscheinen ausschließlich zu Identifikationszwecken und bleiben Eigentum ihrer jeweiligen Inhaber.</p>

<h2>8. Anwendbares Recht</h2>
<p>Diese Bedingungen unterliegen dem Recht des Landes, in dem OeParts niedergelassen ist. Streitigkeiten unterliegen der ausschließlichen Zuständigkeit der zuständigen Gerichte dieses Landes, außer wenn das Verbraucherrecht Ihnen Schutz in einer anderen Rechtsordnung gewährt.</p>

<h2>9. Änderungen</h2>
<p>Wir können diese Bedingungen von Zeit zu Zeit ändern. Wesentliche Änderungen werden per E-Mail oder durch einen Hinweis auf der Website mitgeteilt.</p>
HTML,
                    'lt' => <<<'HTML'
<p class="lead">Naudodamiesi „OeParts“ svetaine, sutinkate su šiomis Paslaugų teikimo sąlygomis. Prašome atidžiai jas perskaityti. Jei nesutinkate su bet kuria šių sąlygų dalimi, nustokite naudotis paslauga.</p>

<h2>1. Tinkamumas ir paskyros</h2>
<p>„OeParts“ skirta tiek privatiems vartotojams, tiek įmonėms ir profesionaliems pirkėjams. Jūs patvirtinate, kad turite įgaliojimus veikti registruojamos paskyros vardu — ar tai būtų kaip privatus asmuo, ar įmonės vardu — ir kad jūsų pateikta informacija yra tiksli.</p>

<h2>2. Užsakymai ir sutarties sudarymas</h2>
<p>Užsakymo pateikimas yra pasiūlymas pirkti. Privaloma sutartis sudaroma tik mums patvirtinus jūsų užsakymo priėmimą. Pasiliekame teisę atsisakyti vykdyti arba atšaukti užsakymus kainų klaidų, tiekimo trūkumo ar įtariamo sukčiavimo atvejais.</p>

<h2>3. Kainos ir mokėjimas</h2>
<p>Kainos rodomos su PVM arba be jo, kaip nurodyta atsiskaitant. Mokėjimas turi būti atliktas užsakymo pateikimo metu, nebent raštu sutarta dėl atidėto mokėjimo. Užsakymai, apmokami banko pavedimu, siunčiami tik gavus lėšas.</p>

<h2>4. Pristatymas ir siuntimas</h2>
<p>Pristatymo terminai nurodomi sąžiningai, tačiau negarantuojami. Rizika pereina pirkėjui prekes perdavus vežėjui. Jūs atsakote už prekių patikrinimą jas gavus. Atsiskaitymo metu rodomos pristatymo kainos yra fiksuotos standartinio dydžio siuntoms. Jei vežėjas užsakytą prekę dėl jos dydžio, svorio ar matmenų priskiria didelių gabaritų ar sunkiam kroviniui, gali būti taikomas papildomas pristatymo mokestis, viršijantis atsiskaitymo metu nurodytą sumą. Toks mokestis bus pateiktas atskira sąskaita po išsiuntimo, o pateikdami užsakymą jūs patvirtinate ir sutinkate, kad toks mokestis gali būti taikomas.</p>

<h2>5. Grąžinimai ir pinigų grąžinimas</h2>
<p>Grąžinimai priimami per užsakymo patvirtinime nurodytą laikotarpį, jei dalis nenaudota, originalioje pakuotėje ir jai netaikoma negrąžinimo išimtis. Vartojamosios medžiagos, sandarūs elektroniniai įrenginiai ir specialaus užsakymo dalys gali būti neįtraukiamos.</p>

<h2>6. Atsakomybė</h2>
<p>Įstatymų leidžiama apimtimi bendra „OeParts“ atsakomybė už bet kokį pagal šias sąlygas kylantį reikalavimą apribojama iki už reikalavimą sukėlusią(-ias) dalį(-is) sumokėtos kainos. Nesame atsakingi už netiesioginius ar pasekminius nuostolius.</p>

<h2>7. Intelektinė nuosavybė</h2>
<p>Visas „OeParts“ turinys, prekių ženklai ir programinė įranga priklauso „OeParts“ arba jos licencijų teikėjams. Trečiųjų šalių prekių ženklai naudojami tik identifikavimo tikslais ir lieka atitinkamų jų savininkų nuosavybe.</p>

<h2>8. Taikytina teisė</h2>
<p>Šias sąlygas reglamentuoja valstybės, kurioje įsteigta „OeParts“, įstatymai. Bet koks ginčas priklauso išimtinei tos valstybės kompetentingų teismų jurisdikcijai, išskyrus atvejus, kai vartotojų teisė suteikia jums apsaugą kitoje jurisdikcijoje.</p>

<h2>9. Pakeitimai</h2>
<p>Retkarčiais galime keisti šias sąlygas. Apie esminius pakeitimus informuosime el. paštu arba pranešimu svetainėje.</p>
HTML,
                    'fr' => <<<'HTML'
<p class="lead">En accédant à OeParts ou en l'utilisant, vous acceptez les présentes Conditions Générales de Vente. Veuillez les lire attentivement. Si vous n'acceptez pas une partie quelconque de ces conditions, veuillez cesser d'utiliser le service.</p>

<h2>1. Éligibilité et comptes</h2>
<p>OeParts est ouvert aussi bien aux particuliers qu'aux entreprises et acheteurs professionnels. Vous confirmez avoir l'autorité nécessaire pour agir au nom du compte que vous enregistrez — que ce soit en tant que particulier ou au nom d'une entreprise — et que les informations que vous fournissez sont exactes.</p>

<h2>2. Commandes et formation du contrat</h2>
<p>Passer une commande constitue une offre d'achat. Un contrat contraignant n'est formé qu'une fois que nous avons confirmé l'acceptation de votre commande. Nous nous réservons le droit de refuser ou d'annuler des commandes en cas d'erreurs de prix, d'indisponibilité de l'approvisionnement ou de suspicion de fraude.</p>

<h2>3. Prix et paiement</h2>
<p>Les prix sont affichés TVA incluse ou exclue, comme indiqué lors du paiement. Le paiement est dû au moment de la commande, sauf accord de crédit convenu par écrit. Les commandes par virement bancaire sont expédiées une fois les fonds crédités.</p>

<h2>4. Expédition et livraison</h2>
<p>Les délais de livraison sont fournis de bonne foi mais ne sont pas garantis. Le risque est transféré à l'acheteur dès la remise au transporteur. Vous êtes responsable de l'inspection des marchandises à leur arrivée. Les frais de livraison affichés lors du paiement sont des tarifs fixes pour les colis de taille standard. Si un article commandé est classé par le transporteur comme fret hors gabarit ou lourd en raison de sa taille, de son poids ou de ses dimensions, un supplément de livraison peut s'appliquer au-delà du tarif affiché lors du paiement. Ce supplément vous sera facturé séparément après expédition, et en passant commande, vous reconnaissez et acceptez qu'un tel supplément puisse s'appliquer.</p>

<h2>5. Retours et remboursements</h2>
<p>Les retours sont acceptés dans le délai indiqué sur votre confirmation de commande, à condition que la pièce soit inutilisée, dans son emballage d'origine et non soumise à une exclusion de retour. Les consommables, unités électroniques scellées et pièces sur commande spéciale peuvent être exclus.</p>

<h2>6. Responsabilité</h2>
<p>Dans la mesure permise par la loi, la responsabilité globale d'OeParts pour toute réclamation découlant des présentes conditions est limitée au prix payé pour la ou les pièce(s) à l'origine de la réclamation. Nous ne sommes pas responsables des pertes indirectes ou consécutives.</p>

<h2>7. Propriété intellectuelle</h2>
<p>Tout le contenu, les marques et les logiciels d'OeParts sont la propriété d'OeParts ou de ses concédants de licence. Les marques de tiers apparaissent uniquement à des fins d'identification et restent la propriété de leurs détenteurs respectifs.</p>

<h2>8. Droit applicable</h2>
<p>Les présentes conditions sont régies par les lois du pays dans lequel OeParts est établie. Tout litige relève de la compétence exclusive des tribunaux compétents de ce pays, sauf lorsque le droit de la consommation vous accorde une protection dans une autre juridiction.</p>

<h2>9. Modifications</h2>
<p>Nous pouvons modifier ces conditions de temps à autre. Les changements substantiels seront communiqués par e-mail ou par un avis sur le site.</p>
HTML,
                    'es' => <<<'HTML'
<p class="lead">Al acceder a OeParts o utilizarlo, usted acepta estas Condiciones de Servicio. Léalas atentamente. Si no acepta alguna parte de estas condiciones, deje de utilizar el servicio.</p>

<h2>1. Elegibilidad y cuentas</h2>
<p>OeParts está abierto tanto a consumidores particulares como a empresas y compradores profesionales. Usted confirma que tiene la autoridad para actuar en nombre de la cuenta que registra — ya sea como particular o en nombre de una empresa — y que la información que proporciona es exacta.</p>

<h2>2. Pedidos y formación del contrato</h2>
<p>Realizar un pedido constituye una oferta de compra. Solo se forma un contrato vinculante una vez que confirmamos la aceptación de su pedido. Nos reservamos el derecho de rechazar o cancelar pedidos en casos de errores de precio, falta de disponibilidad de suministro o sospecha de fraude.</p>

<h2>3. Precios y pago</h2>
<p>Los precios se muestran con o sin IVA incluido, según se indique al finalizar la compra. El pago vence en el momento del pedido, salvo que se haya acordado por escrito un acuerdo de crédito. Los pedidos por transferencia bancaria se envían una vez confirmados los fondos.</p>

<h2>4. Envío y entrega</h2>
<p>Las estimaciones de entrega se proporcionan de buena fe pero no están garantizadas. El riesgo se transfiere al comprador en el momento de la entrega al transportista. Usted es responsable de inspeccionar los productos a su llegada. Los costes de envío mostrados en el pago son tarifas fijas para paquetes de tamaño estándar. Si el transportista clasifica un artículo pedido como carga de gran tamaño o pesada debido a su tamaño, peso o dimensiones, puede aplicarse un recargo de envío adicional más allá de la tarifa mostrada en el pago. Dicho recargo se le facturará por separado tras el envío, y al realizar un pedido, usted reconoce y acepta que este recargo pueda aplicarse.</p>

<h2>5. Devoluciones y reembolsos</h2>
<p>Las devoluciones se aceptan dentro del plazo indicado en la confirmación de su pedido, siempre que la pieza esté sin usar, en su embalaje original y no sujeta a una exclusión de devolución. Los consumibles, unidades electrónicas selladas y piezas de pedido especial pueden quedar excluidos.</p>

<h2>6. Responsabilidad</h2>
<p>En la medida permitida por la ley, la responsabilidad total de OeParts por cualquier reclamación derivada de estas condiciones se limita al precio pagado por la(s) pieza(s) que dio(dieron) lugar a la reclamación. No somos responsables de pérdidas indirectas o consecuentes.</p>

<h2>7. Propiedad intelectual</h2>
<p>Todo el contenido, marcas y software de OeParts son propiedad de OeParts o de sus licenciantes. Las marcas de terceros aparecen únicamente con fines de identificación y siguen siendo propiedad de sus respectivos titulares.</p>

<h2>8. Ley aplicable</h2>
<p>Estas condiciones se rigen por las leyes de la jurisdicción en la que OeParts está establecida. Cualquier disputa está sujeta a la jurisdicción exclusiva de los tribunales competentes de esa jurisdicción, salvo cuando la normativa de protección al consumidor le otorgue protección en otra jurisdicción.</p>

<h2>9. Cambios</h2>
<p>Podemos modificar estas condiciones periódicamente. Los cambios importantes se comunicarán por correo electrónico o mediante un aviso en el sitio.</p>
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
                    'en' => 'Cookie Policy — OeParts',
                    'de' => 'Cookie-Richtlinie — OeParts',
                    'lt' => 'Slapukų politika — „OeParts“',
                    'fr' => 'Politique relative aux cookies — OeParts',
                    'es' => 'Política de cookies — OeParts',
                ],
                'meta_description' => [
                    'en' => 'How OeParts uses cookies and similar technologies, and how you can control them.',
                    'de' => 'Wie OeParts Cookies und ähnliche Technologien verwendet und wie Sie diese steuern können.',
                    'lt' => 'Kaip „OeParts“ naudoja slapukus ir panašias technologijas bei kaip galite juos valdyti.',
                    'fr' => 'Comment OeParts utilise les cookies et technologies similaires, et comment vous pouvez les gérer.',
                    'es' => 'Cómo OeParts utiliza cookies y tecnologías similares, y cómo puede controlarlas.',
                ],
                'content' => [
                    'en' => <<<'HTML'
<p class="lead">This page explains the cookies and similar technologies that OeParts uses, what they do, and how you can manage your preferences.</p>

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
                    'de' => <<<'HTML'
<p class="lead">Diese Seite erklärt, welche Cookies und ähnlichen Technologien OeParts verwendet, was sie tun und wie Sie Ihre Einstellungen verwalten können.</p>

<h2>1. Was ist ein Cookie?</h2>
<p>Ein Cookie ist eine kleine Textdatei, die beim Besuch einer Website auf Ihrem Gerät gespeichert wird. Cookies ermöglichen es der Website, sich über einen bestimmten Zeitraum an Ihre Aktionen und Präferenzen zu erinnern, damit Sie diese bei einem erneuten Besuch nicht wiederholt eingeben müssen.</p>

<h2>2. Kategorien der von uns verwendeten Cookies</h2>
<ul>
    <li><strong>Unbedingt erforderlich.</strong> Notwendig für die Funktion der Website — Login-Sitzung, Warenkorbinhalt, CSRF-Schutz. Diese können nicht deaktiviert werden.</li>
    <li><strong>Funktional.</strong> Merken sich Ihre Sprach-, Währungs- und UI-Präferenzen.</li>
    <li><strong>Analyse.</strong> Helfen uns zu verstehen, wie die Website genutzt wird, damit wir sie verbessern können. Werden nur mit Ihrer Zustimmung geladen.</li>
    <li><strong>Marketing.</strong> Dienen dazu, die Wirksamkeit von Werbung zu messen und, sofern zutreffend, Kommunikation anzupassen. Werden nur mit Ihrer Zustimmung geladen.</li>
</ul>

<h2>3. Verwaltung Ihrer Einstellungen</h2>
<p>Sie können Ihre Zustimmung jederzeit über das Cookie-Banner auf dieser Website ändern oder widerrufen. Sie können Cookies auch über Ihre Browsereinstellungen blockieren oder löschen, was jedoch einige Funktionen beeinträchtigen kann.</p>

<h2>4. Cookies von Drittanbietern</h2>
<p>Einige Cookies werden von vertrauenswürdigen Drittanbietern gesetzt, die uns beim Betrieb des Dienstes unterstützen — zum Beispiel Zahlungsanbieter, Betrugspräventionssysteme und Analyseplattformen. Diese Anbieter verarbeiten Ihre Daten möglicherweise gemäß ihren eigenen Datenschutzrichtlinien.</p>

<h2>5. Änderungen</h2>
<p>Wir aktualisieren diese Richtlinie, wenn sich unsere Cookie-Nutzung ändert. Bitte besuchen Sie diese Seite von Zeit zu Zeit erneut, um informiert zu bleiben.</p>
HTML,
                    'lt' => <<<'HTML'
<p class="lead">Šis puslapis paaiškina, kokius slapukus ir panašias technologijas naudoja „OeParts“, ką jie daro ir kaip galite valdyti savo nuostatas.</p>

<h2>1. Kas yra slapukas?</h2>
<p>Slapukas — tai nedidelis tekstinis failas, saugomas jūsų įrenginyje apsilankius svetainėje. Slapukai leidžia svetainei tam tikrą laiką prisiminti jūsų veiksmus ir pageidavimus, kad kitą kartą apsilankę nebūtų reikalo jų iš naujo suvedinėti.</p>

<h2>2. Slapukų, kuriuos naudojame, kategorijos</h2>
<ul>
    <li><strong>Būtinieji.</strong> Reikalingi svetainės veikimui — prisijungimo sesija, krepšelio turinys, CSRF apsauga. Šių slapukų išjungti negalima.</li>
    <li><strong>Funkciniai.</strong> Prisimena jūsų kalbos, valiutos ir naudotojo sąsajos nuostatas.</li>
    <li><strong>Analitiniai.</strong> Padeda mums suprasti, kaip naudojamasi svetaine, kad galėtume ją tobulinti. Įkeliami tik gavus jūsų sutikimą.</li>
    <li><strong>Rinkodaros.</strong> Naudojami reklamos efektyvumui įvertinti ir, kai taikoma, pranešimams pritaikyti. Įkeliami tik gavus jūsų sutikimą.</li>
</ul>

<h2>3. Nuostatų valdymas</h2>
<p>Savo sutikimą galite bet kada pakeisti arba atšaukti naudodamiesi šios svetainės slapukų juosta. Taip pat galite blokuoti ar ištrinti slapukus per naršyklės nustatymus, tačiau tai gali paveikti kai kurias funkcijas.</p>

<h2>4. Trečiųjų šalių slapukai</h2>
<p>Kai kuriuos slapukus nustato patikimos trečiosios šalys, padedančios mums teikti paslaugą — pavyzdžiui, mokėjimo paslaugų teikėjai, sukčiavimo prevencijos sistemos ir analitikos platformos. Šie teikėjai gali tvarkyti jūsų duomenis pagal savo privatumo politiką.</p>

<h2>5. Pakeitimai</h2>
<p>Šią politiką atnaujiname, kai pasikeičia mūsų slapukų naudojimas. Kartkartėmis apsilankykite šiame puslapyje, kad būtumėte informuoti.</p>
HTML,
                    'fr' => <<<'HTML'
<p class="lead">Cette page explique les cookies et technologies similaires utilisés par OeParts, ce qu'ils font et comment vous pouvez gérer vos préférences.</p>

<h2>1. Qu'est-ce qu'un cookie ?</h2>
<p>Un cookie est un petit fichier texte stocké sur votre appareil lorsque vous visitez un site web. Les cookies permettent au site de se souvenir de vos actions et préférences pendant une certaine période, afin que vous n'ayez pas à les ressaisir à chaque visite.</p>

<h2>2. Catégories de cookies que nous utilisons</h2>
<ul>
    <li><strong>Strictement nécessaires.</strong> Requis pour le fonctionnement du site — session de connexion, contenu du panier, protection CSRF. Ils ne peuvent pas être désactivés.</li>
    <li><strong>Fonctionnels.</strong> Mémorisent vos préférences de langue, de devise et d'interface.</li>
    <li><strong>Analytiques.</strong> Nous aident à comprendre comment le site est utilisé afin de l'améliorer. Chargés uniquement avec votre consentement.</li>
    <li><strong>Marketing.</strong> Utilisés pour mesurer l'efficacité de la publicité et, le cas échéant, personnaliser les communications. Chargés uniquement avec votre consentement.</li>
</ul>

<h2>3. Gestion de vos préférences</h2>
<p>Vous pouvez modifier ou retirer votre consentement à tout moment via la bannière de cookies de ce site. Vous pouvez également bloquer ou supprimer les cookies via les paramètres de votre navigateur, bien que cela puisse affecter certaines fonctionnalités.</p>

<h2>4. Cookies tiers</h2>
<p>Certains cookies sont déposés par des tiers de confiance qui nous aident à exploiter le service — par exemple, les prestataires de paiement, les systèmes de prévention de la fraude et les plateformes d'analyse. Ces prestataires peuvent traiter vos données conformément à leurs propres politiques de confidentialité.</p>

<h2>5. Modifications</h2>
<p>Nous mettons à jour cette politique lorsque notre utilisation des cookies évolue. Veuillez revisiter cette page de temps à autre pour rester informé.</p>
HTML,
                    'es' => <<<'HTML'
<p class="lead">Esta página explica las cookies y tecnologías similares que utiliza OeParts, qué hacen y cómo puede gestionar sus preferencias.</p>

<h2>1. ¿Qué es una cookie?</h2>
<p>Una cookie es un pequeño archivo de texto que se almacena en su dispositivo al visitar un sitio web. Las cookies permiten que el sitio recuerde sus acciones y preferencias durante un período de tiempo, para que no tenga que volver a introducirlas cada vez que regrese.</p>

<h2>2. Categorías de cookies que utilizamos</h2>
<ul>
    <li><strong>Estrictamente necesarias.</strong> Necesarias para el funcionamiento del sitio — sesión de inicio, contenido del carrito, protección CSRF. No se pueden desactivar.</li>
    <li><strong>Funcionales.</strong> Recuerdan sus preferencias de idioma, moneda e interfaz.</li>
    <li><strong>Analíticas.</strong> Nos ayudan a entender cómo se usa el sitio para poder mejorarlo. Solo se cargan con su consentimiento.</li>
    <li><strong>Marketing.</strong> Se utilizan para medir la eficacia de la publicidad y, cuando corresponda, personalizar las comunicaciones. Solo se cargan con su consentimiento.</li>
</ul>

<h2>3. Gestión de sus preferencias</h2>
<p>Puede cambiar o retirar su consentimiento en cualquier momento mediante el banner de cookies de este sitio. También puede bloquear o eliminar cookies a través de la configuración de su navegador, aunque esto puede afectar a algunas funciones.</p>

<h2>4. Cookies de terceros</h2>
<p>Algunas cookies son colocadas por terceros de confianza que nos ayudan a operar el servicio — por ejemplo, proveedores de pago, sistemas de prevención de fraude y plataformas de análisis. Dichos proveedores pueden tratar sus datos conforme a sus propias políticas de privacidad.</p>

<h2>5. Cambios</h2>
<p>Actualizamos esta política cuando cambia nuestro uso de cookies. Por favor, visite esta página periódicamente para mantenerse informado.</p>
HTML,
                ],
            ],

            'returns-policy' => [
                'title' => [
                    'en' => 'Returns Policy',
                    'de' => 'Rückgaberichtlinie',
                    'lt' => 'Grąžinimo politika',
                    'fr' => 'Politique de retour',
                    'es' => 'Política de devoluciones',
                ],
                'meta_title' => [
                    'en' => 'Returns Policy — OeParts',
                    'de' => 'Rückgaberichtlinie — OeParts',
                    'lt' => 'Grąžinimo politika — „OeParts“',
                    'fr' => 'Politique de retour — OeParts',
                    'es' => 'Política de devoluciones — OeParts',
                ],
                'meta_description' => [
                    'en' => 'How to return a part to OeParts, what is eligible, and how refunds are processed.',
                    'de' => 'Wie Sie ein Teil an OeParts zurücksenden, was rückgabefähig ist und wie Erstattungen abgewickelt werden.',
                    'lt' => 'Kaip grąžinti dalį „OeParts“, kas gali būti grąžinama ir kaip vykdomas pinigų grąžinimas.',
                    'fr' => 'Comment retourner une pièce à OeParts, ce qui est éligible et comment les remboursements sont traités.',
                    'es' => 'Cómo devolver una pieza a OeParts, qué es elegible y cómo se procesan los reembolsos.',
                ],
                'content' => [
                    'en' => <<<'HTML'
<p class="lead">You may return most parts within 14 days of delivery. This page explains what qualifies, how to start a return, and how refunds are paid out.</p>

<h2>1. Return window</h2>
<p>Returns must be requested within 14 days of the delivery date shown on your order. Requests submitted after this window cannot be accepted, except where the part arrived damaged, defective or incorrect — see Section 5.</p>

<h2>2. Eligibility</h2>
<p>To qualify for a return, a part must be unused, in its original packaging, and not subject to a no-return exclusion (Section 4). We may decline a return that shows signs of installation or use.</p>

<h2>3. How to start a return</h2>
<p>Sign in to your account, open the order from Account &rarr; Orders, and submit a refund request with the reason for return. Our team reviews the request and provides return shipping instructions. Do not send a part back before your request has been reviewed — items returned without an approved request may be delayed or refused.</p>

<h2>4. Non-returnable items</h2>
<ul>
    <li>Consumables (fluids, filters, gaskets and similar single-use items) once opened.</li>
    <li>Sealed electronic units once the seal has been broken.</li>
    <li>Parts ordered specially for you outside our standard catalogue.</li>
</ul>

<h2>5. Damaged, defective or incorrect items</h2>
<p>If a part arrives damaged, is defective, or does not match your order, contact us within 14 days of delivery with photos of the item and packaging. We will arrange a replacement or full refund, including return shipping, at no cost to you.</p>

<h2>6. Refunds</h2>
<p>Approved refunds are issued to your original payment method. Processing times vary by payment provider; once we confirm the returned part, refunds are typically visible within a few business days. Bank-transfer orders are refunded to the account the payment originated from.</p>

<h2>7. Return shipping costs</h2>
<p>For change-of-mind returns, the customer is responsible for return shipping costs. For damaged, defective or incorrect items (Section 5), we cover return shipping.</p>
HTML,
                    'de' => <<<'HTML'
<p class="lead">Die meisten Teile können Sie innerhalb von 14 Tagen nach Lieferung zurücksenden. Diese Seite erklärt, was rückgabefähig ist, wie Sie eine Rückgabe starten und wie Erstattungen ausgezahlt werden.</p>

<h2>1. Rückgabefrist</h2>
<p>Rückgaben müssen innerhalb von 14 Tagen ab dem auf Ihrer Bestellung angezeigten Lieferdatum beantragt werden. Anfragen nach Ablauf dieser Frist können nicht angenommen werden, außer wenn das Teil beschädigt, defekt oder falsch geliefert wurde — siehe Abschnitt 5.</p>

<h2>2. Voraussetzungen</h2>
<p>Um für eine Rückgabe infrage zu kommen, muss ein Teil unbenutzt, in der Originalverpackung und nicht von einem Rückgabeausschluss (Abschnitt 4) betroffen sein. Wir können eine Rückgabe ablehnen, wenn Anzeichen von Einbau oder Nutzung erkennbar sind.</p>

<h2>3. So starten Sie eine Rückgabe</h2>
<p>Melden Sie sich in Ihrem Konto an, öffnen Sie die Bestellung unter Konto &rarr; Bestellungen und reichen Sie eine Erstattungsanfrage mit dem Grund für die Rückgabe ein. Unser Team prüft die Anfrage und stellt Ihnen Rücksendeanweisungen bereit. Senden Sie das Teil nicht zurück, bevor Ihre Anfrage geprüft wurde — ohne genehmigte Anfrage zurückgesendete Artikel können verzögert oder abgelehnt werden.</p>

<h2>4. Von der Rückgabe ausgeschlossene Artikel</h2>
<ul>
    <li>Verbrauchsmaterialien (Flüssigkeiten, Filter, Dichtungen und ähnliche Einwegartikel) nach dem Öffnen.</li>
    <li>Versiegelte elektronische Einheiten nach Bruch der Versiegelung.</li>
    <li>Speziell für Sie außerhalb unseres Standardkatalogs bestellte Teile.</li>
</ul>

<h2>5. Beschädigte, defekte oder falsche Artikel</h2>
<p>Wenn ein Teil beschädigt ankommt, defekt ist oder nicht Ihrer Bestellung entspricht, kontaktieren Sie uns innerhalb von 14 Tagen nach Lieferung mit Fotos des Artikels und der Verpackung. Wir organisieren einen Ersatz oder eine vollständige Erstattung, einschließlich Rückversand, ohne Kosten für Sie.</p>

<h2>6. Erstattungen</h2>
<p>Genehmigte Erstattungen erfolgen auf Ihre ursprüngliche Zahlungsmethode. Die Bearbeitungszeit hängt vom Zahlungsanbieter ab; nach Bestätigung des zurückgesendeten Teils sind Erstattungen in der Regel innerhalb weniger Werktage sichtbar. Per Banküberweisung bezahlte Bestellungen werden auf das Konto erstattet, von dem die Zahlung stammt.</p>

<h2>7. Rückversandkosten</h2>
<p>Bei Rückgaben ohne Mangelgrund trägt der Kunde die Rückversandkosten. Bei beschädigten, defekten oder falschen Artikeln (Abschnitt 5) übernehmen wir den Rückversand.</p>
HTML,
                    'lt' => <<<'HTML'
<p class="lead">Daugumą dalių galite grąžinti per 14 dienų nuo pristatymo. Šiame puslapyje paaiškinama, kas gali būti grąžinama, kaip pradėti grąžinimą ir kaip išmokamas pinigų grąžinimas.</p>

<h2>1. Grąžinimo terminas</h2>
<p>Grąžinimo prašymas turi būti pateiktas per 14 dienų nuo jūsų užsakyme nurodytos pristatymo datos. Po šio termino pateikti prašymai negali būti priimti, išskyrus atvejus, kai dalis atvyko pažeista, su defektu ar neteisinga — žr. 5 skyrių.</p>

<h2>2. Tinkamumas</h2>
<p>Kad dalis būtų tinkama grąžinti, ji turi būti nenaudota, originalioje pakuotėje ir jai neturi būti taikoma negrąžinimo išimtis (4 skyrius). Galime atsisakyti priimti grąžinimą, jei matomi montavimo ar naudojimo požymiai.</p>

<h2>3. Kaip pradėti grąžinimą</h2>
<p>Prisijunkite prie savo paskyros, atidarykite užsakymą skiltyje Paskyra &rarr; Užsakymai ir pateikite pinigų grąžinimo prašymą, nurodydami grąžinimo priežastį. Mūsų komanda peržiūri prašymą ir pateikia grąžinimo siuntimo instrukcijas. Nesiųskite dalies atgal, kol jūsų prašymas nebus peržiūrėtas — be patvirtinto prašymo grąžintos prekės gali būti vėluojamos arba atmestos.</p>

<h2>4. Negrąžinamos prekės</h2>
<ul>
    <li>Vartojamosios medžiagos (skysčiai, filtrai, tarpikliai ir panašūs vienkartiniai daiktai) atidarius pakuotę.</li>
    <li>Sandarūs elektroniniai įrenginiai, kai plomba pažeista.</li>
    <li>Dalys, užsakytos specialiai jums, nepriklausančios standartiniam katalogui.</li>
</ul>

<h2>5. Pažeisti, su defektu ar neteisingi daiktai</h2>
<p>Jei dalis atvyksta pažeista, su defektu arba neatitinka jūsų užsakymo, susisiekite su mumis per 14 dienų nuo pristatymo, pateikdami daikto ir pakuotės nuotraukas. Suorganizuosime pakeitimą arba visišką pinigų grąžinimą, įskaitant grąžinimo siuntimą, jums nemokamai.</p>

<h2>6. Pinigų grąžinimas</h2>
<p>Patvirtintas grąžinimas atliekamas į jūsų pradinį mokėjimo būdą. Apdorojimo laikas priklauso nuo mokėjimo paslaugų teikėjo; patvirtinus grąžintą dalį, pinigai paprastai matomi per kelias darbo dienas. Banko pavedimu apmokėti užsakymai grąžinami į sąskaitą, iš kurios buvo atliktas mokėjimas.</p>

<h2>7. Grąžinimo siuntimo išlaidos</h2>
<p>Grąžinant dėl apsigalvojimo, siuntimo išlaidas dengia klientas. Už pažeistus, su defektu ar neteisingus daiktus (5 skyrius) grąžinimo siuntimą apmokame mes.</p>
HTML,
                    'fr' => <<<'HTML'
<p class="lead">Vous pouvez retourner la plupart des pièces dans les 14 jours suivant la livraison. Cette page explique ce qui est éligible, comment démarrer un retour et comment les remboursements sont versés.</p>

<h2>1. Délai de retour</h2>
<p>Les retours doivent être demandés dans les 14 jours suivant la date de livraison indiquée sur votre commande. Les demandes soumises après ce délai ne peuvent être acceptées, sauf si la pièce est arrivée endommagée, défectueuse ou incorrecte — voir la section 5.</p>

<h2>2. Éligibilité</h2>
<p>Pour être éligible à un retour, une pièce doit être inutilisée, dans son emballage d'origine, et ne pas faire l'objet d'une exclusion de retour (section 4). Nous pouvons refuser un retour présentant des signes d'installation ou d'utilisation.</p>

<h2>3. Comment démarrer un retour</h2>
<p>Connectez-vous à votre compte, ouvrez la commande depuis Compte &rarr; Commandes, et soumettez une demande de remboursement avec le motif du retour. Notre équipe examine la demande et vous fournit les instructions de retour. N'envoyez pas la pièce avant que votre demande n'ait été examinée — les articles retournés sans demande approuvée peuvent être retardés ou refusés.</p>

<h2>4. Articles non retournables</h2>
<ul>
    <li>Consommables (fluides, filtres, joints et articles à usage unique similaires) une fois ouverts.</li>
    <li>Unités électroniques scellées une fois le sceau brisé.</li>
    <li>Pièces commandées spécialement pour vous en dehors de notre catalogue standard.</li>
</ul>

<h2>5. Articles endommagés, défectueux ou incorrects</h2>
<p>Si une pièce arrive endommagée, est défectueuse, ou ne correspond pas à votre commande, contactez-nous dans les 14 jours suivant la livraison avec des photos de l'article et de l'emballage. Nous organiserons un remplacement ou un remboursement intégral, frais de retour inclus, sans frais pour vous.</p>

<h2>6. Remboursements</h2>
<p>Les remboursements approuvés sont émis sur votre moyen de paiement d'origine. Les délais de traitement varient selon le prestataire de paiement ; une fois la pièce retournée confirmée, les remboursements sont généralement visibles sous quelques jours ouvrés. Les commandes par virement bancaire sont remboursées sur le compte d'origine du paiement.</p>

<h2>7. Frais de retour</h2>
<p>Pour les retours par convenance, les frais de retour sont à la charge du client. Pour les articles endommagés, défectueux ou incorrects (section 5), nous prenons en charge le retour.</p>
HTML,
                    'es' => <<<'HTML'
<p class="lead">Puede devolver la mayoría de las piezas dentro de los 14 días posteriores a la entrega. Esta página explica qué es elegible, cómo iniciar una devolución y cómo se abonan los reembolsos.</p>

<h2>1. Plazo de devolución</h2>
<p>Las devoluciones deben solicitarse dentro de los 14 días posteriores a la fecha de entrega indicada en su pedido. Las solicitudes enviadas después de este plazo no podrán aceptarse, salvo que la pieza haya llegado dañada, defectuosa o incorrecta — véase la sección 5.</p>

<h2>2. Elegibilidad</h2>
<p>Para ser elegible para una devolución, una pieza debe estar sin usar, en su embalaje original y no estar sujeta a una exclusión de devolución (sección 4). Podemos rechazar una devolución que muestre señales de instalación o uso.</p>

<h2>3. Cómo iniciar una devolución</h2>
<p>Inicie sesión en su cuenta, abra el pedido desde Cuenta &rarr; Pedidos, y envíe una solicitud de reembolso con el motivo de la devolución. Nuestro equipo revisa la solicitud y le proporciona las instrucciones de devolución. No envíe la pieza antes de que su solicitud haya sido revisada — los artículos devueltos sin una solicitud aprobada pueden retrasarse o rechazarse.</p>

<h2>4. Artículos no devolubles</h2>
<ul>
    <li>Consumibles (líquidos, filtros, juntas y artículos de un solo uso similares) una vez abiertos.</li>
    <li>Unidades electrónicas selladas una vez rota la precinta.</li>
    <li>Piezas pedidas especialmente para usted fuera de nuestro catálogo estándar.</li>
</ul>

<h2>5. Artículos dañados, defectuosos o incorrectos</h2>
<p>Si una pieza llega dañada, es defectuosa o no coincide con su pedido, contáctenos dentro de los 14 días posteriores a la entrega con fotos del artículo y el embalaje. Organizaremos un reemplazo o un reembolso completo, incluido el envío de devolución, sin coste para usted.</p>

<h2>6. Reembolsos</h2>
<p>Los reembolsos aprobados se emiten a su método de pago original. Los tiempos de procesamiento varían según el proveedor de pago; una vez confirmada la pieza devuelta, los reembolsos suelen ser visibles en pocos días hábiles. Los pedidos por transferencia bancaria se reembolsan a la cuenta desde la que se originó el pago.</p>

<h2>7. Costes de envío de devolución</h2>
<p>Para devoluciones por cambio de opinión, el cliente es responsable de los costes de envío de devolución. Para artículos dañados, defectuosos o incorrectos (sección 5), cubrimos el envío de devolución.</p>
HTML,
                ],
            ],

            'shipping-information' => [
                'title' => [
                    'en' => 'Shipping Information',
                    'de' => 'Versandinformationen',
                    'lt' => 'Pristatymo informacija',
                    'fr' => 'Informations de livraison',
                    'es' => 'Información de envío',
                ],
                'meta_title' => [
                    'en' => 'Shipping Information — OeParts',
                    'de' => 'Versandinformationen — OeParts',
                    'lt' => 'Pristatymo informacija — „OeParts“',
                    'fr' => 'Informations de livraison — OeParts',
                    'es' => 'Información de envío — OeParts',
                ],
                'meta_description' => [
                    'en' => 'Delivery areas, carriers, processing times and shipping costs for OeParts orders.',
                    'de' => 'Liefergebiete, Versanddienstleister, Bearbeitungszeiten und Versandkosten für OeParts-Bestellungen.',
                    'lt' => 'Pristatymo teritorijos, vežėjai, apdorojimo laikas ir pristatymo kainos „OeParts“ užsakymams.',
                    'fr' => 'Zones de livraison, transporteurs, délais de traitement et frais de port pour les commandes OeParts.',
                    'es' => 'Zonas de entrega, transportistas, tiempos de procesamiento y costes de envío para los pedidos de OeParts.',
                ],
                'content' => [
                    'en' => <<<'HTML'
<p class="lead">OeParts ships across the EU plus Norway, Switzerland and the UK, using tracked and insured carrier services.</p>

<h2>1. Delivery area</h2>
<p>We despatch to every EU member state, as well as Norway, Switzerland and the United Kingdom. The countries available to you are shown automatically at checkout based on your delivery address.</p>

<h2>2. Carriers</h2>
<p>Orders are shipped via DHL, DPD or GLS depending on destination and parcel size. Every shipment is tracked and insured; a tracking link is emailed once your order despatches and is also available from Account &rarr; Orders.</p>

<h2>3. Processing time</h2>
<p>Orders placed before our daily cutoff are processed the same business day; orders placed after cutoff are processed the next business day. Delivery estimates shown at checkout are calculated from despatch, not from order placement, and are provided in good faith rather than guaranteed.</p>

<h2>4. Shipping costs</h2>
<p>Shipping cost is calculated at checkout based on your delivery address and the shipping method you select. Some shipping methods offer free shipping once your order subtotal passes a threshold — this is shown in your cart when applicable.</p>

<h2>5. Oversized or heavy freight</h2>
<p>Shipping rates shown at checkout are fixed rates for standard-size parcels. Where an ordered part is classified by the carrier as oversized or heavy freight due to its size, weight or dimensions, an additional shipping surcharge may apply beyond the rate shown at checkout. Any such surcharge is invoiced separately after dispatch — placing an order confirms you accept that this surcharge may apply.</p>

<h2>6. Customs &amp; import duties</h2>
<p>Shipments within the EU move freely with no customs formalities. Shipments to Norway, Switzerland and the UK may be subject to import duties, VAT or customs clearance fees charged by the destination country — these are the responsibility of the recipient and are not included in the price shown at checkout.</p>

<h2>7. Delivery issues</h2>
<p>If a shipment is lost, significantly delayed, or arrives damaged, contact us with your order number and we will investigate with the carrier and arrange a resolution.</p>
HTML,
                    'de' => <<<'HTML'
<p class="lead">OeParts versendet in die gesamte EU sowie nach Norwegen, in die Schweiz und ins Vereinigte Königreich, mit verfolgten und versicherten Versanddiensten.</p>

<h2>1. Liefergebiet</h2>
<p>Wir versenden in jeden EU-Mitgliedstaat sowie nach Norwegen, in die Schweiz und ins Vereinigte Königreich. Die für Sie verfügbaren Länder werden beim Checkout automatisch anhand Ihrer Lieferadresse angezeigt.</p>

<h2>2. Versanddienstleister</h2>
<p>Bestellungen werden je nach Ziel und Paketgröße per DHL, DPD oder GLS versandt. Jede Sendung wird verfolgt und ist versichert; ein Tracking-Link wird nach Versand Ihrer Bestellung per E-Mail gesendet und ist auch unter Konto &rarr; Bestellungen verfügbar.</p>

<h2>3. Bearbeitungszeit</h2>
<p>Bestellungen, die vor unserem täglichen Annahmeschluss aufgegeben werden, werden noch am selben Werktag bearbeitet; Bestellungen danach werden am nächsten Werktag bearbeitet. Die beim Checkout angezeigten Lieferschätzungen werden ab Versand, nicht ab Bestelldatum berechnet und nach bestem Wissen angegeben, jedoch nicht garantiert.</p>

<h2>4. Versandkosten</h2>
<p>Die Versandkosten werden beim Checkout anhand Ihrer Lieferadresse und der gewählten Versandmethode berechnet. Einige Versandmethoden bieten kostenlosen Versand ab einem bestimmten Bestellwert — dies wird in Ihrem Warenkorb angezeigt, sofern zutreffend.</p>

<h2>5. Übergroße oder schwere Fracht</h2>
<p>Die beim Checkout angezeigten Versandkosten sind Festpreise für Standardpakete. Wird eine bestellte Ware vom Transportunternehmen aufgrund ihrer Größe, ihres Gewichts oder ihrer Abmessungen als übergroße oder schwere Fracht eingestuft, kann ein zusätzlicher Versandzuschlag über den beim Checkout angezeigten Betrag hinaus anfallen. Ein solcher Zuschlag wird nach dem Versand separat in Rechnung gestellt — mit der Bestellung bestätigen Sie, dass dieser Zuschlag anfallen kann.</p>

<h2>6. Zoll &amp; Einfuhrabgaben</h2>
<p>Sendungen innerhalb der EU erfolgen ohne Zollformalitäten. Sendungen nach Norwegen, in die Schweiz und ins Vereinigte Königreich können Einfuhrabgaben, Mehrwertsteuer oder Zollabfertigungsgebühren des Bestimmungslandes unterliegen — diese gehen zulasten des Empfängers und sind im beim Checkout angezeigten Preis nicht enthalten.</p>

<h2>7. Lieferprobleme</h2>
<p>Wenn eine Sendung verloren geht, erheblich verzögert ist oder beschädigt ankommt, kontaktieren Sie uns mit Ihrer Bestellnummer; wir klären dies mit dem Versanddienstleister und finden eine Lösung.</p>
HTML,
                    'lt' => <<<'HTML'
<p class="lead">„OeParts“ pristato prekes visoje ES, taip pat į Norvegiją, Šveicariją ir Jungtinę Karalystę, naudodama sekamas ir apdraustas vežėjų paslaugas.</p>

<h2>1. Pristatymo teritorija</h2>
<p>Siunčiame į visas ES valstybes nares, taip pat į Norvegiją, Šveicariją ir Jungtinę Karalystę. Jums prieinamos šalys automatiškai rodomos atsiskaitant, pagal jūsų pristatymo adresą.</p>

<h2>2. Vežėjai</h2>
<p>Užsakymai siunčiami DHL, DPD arba GLS priklausomai nuo paskirties vietos ir siuntos dydžio. Kiekviena siunta yra sekama ir apdrausta; sekimo nuoroda išsiunčiama el. paštu, kai užsakymas išsiunčiamas, ir taip pat prieinama skiltyje Paskyra &rarr; Užsakymai.</p>

<h2>3. Apdorojimo laikas</h2>
<p>Užsakymai, pateikti iki mūsų kasdienio priėmimo laiko, apdorojami tą pačią darbo dieną; po to pateikti užsakymai — kitą darbo dieną. Atsiskaitant rodomi pristatymo terminai skaičiuojami nuo išsiuntimo, o ne nuo užsakymo pateikimo, ir pateikiami sąžiningai, tačiau negarantuojami.</p>

<h2>4. Pristatymo kainos</h2>
<p>Pristatymo kaina apskaičiuojama atsiskaitant, pagal jūsų pristatymo adresą ir pasirinktą pristatymo būdą. Kai kurie pristatymo būdai siūlo nemokamą pristatymą, kai užsakymo suma viršija nustatytą ribą — tai rodoma jūsų krepšelyje, kai taikoma.</p>

<h2>5. Didelių gabaritų ar sunkus krovinys</h2>
<p>Atsiskaitymo metu rodomos pristatymo kainos yra fiksuotos standartinio dydžio siuntoms. Jei vežėjas užsakytą prekę dėl jos dydžio, svorio ar matmenų priskiria didelių gabaritų ar sunkiam kroviniui, gali būti taikomas papildomas pristatymo mokestis, viršijantis atsiskaitymo metu nurodytą sumą. Toks mokestis pateikiamas atskira sąskaita po išsiuntimo — pateikdami užsakymą patvirtinate, kad toks mokestis gali būti taikomas.</p>

<h2>6. Muitai ir importo mokesčiai</h2>
<p>Siuntos ES viduje vyksta be muitinės formalumų. Siuntoms į Norvegiją, Šveicariją ir Jungtinę Karalystę gali būti taikomi importo mokesčiai, PVM ar muitinio įforminimo mokesčiai, kuriuos taiko paskirties šalis — juos apmoka gavėjas, ir jie neįtraukti į atsiskaitymo metu rodomą kainą.</p>

<h2>7. Pristatymo problemos</h2>
<p>Jei siunta pametama, gerokai vėluoja ar atvyksta pažeista, susisiekite su mumis nurodydami užsakymo numerį — išsiaiškinsime su vežėju ir surasime sprendimą.</p>
HTML,
                    'fr' => <<<'HTML'
<p class="lead">OeParts livre dans toute l'UE ainsi qu'en Norvège, en Suisse et au Royaume-Uni, via des services de transport suivis et assurés.</p>

<h2>1. Zone de livraison</h2>
<p>Nous livrons dans tous les États membres de l'UE, ainsi qu'en Norvège, en Suisse et au Royaume-Uni. Les pays disponibles pour vous sont affichés automatiquement lors du paiement, en fonction de votre adresse de livraison.</p>

<h2>2. Transporteurs</h2>
<p>Les commandes sont expédiées via DHL, DPD ou GLS selon la destination et la taille du colis. Chaque envoi est suivi et assuré ; un lien de suivi est envoyé par e-mail dès l'expédition de votre commande et est également disponible depuis Compte &rarr; Commandes.</p>

<h2>3. Délai de traitement</h2>
<p>Les commandes passées avant notre heure limite quotidienne sont traitées le jour même ; celles passées après sont traitées le jour ouvré suivant. Les délais de livraison affichés lors du paiement sont calculés à partir de l'expédition, et non de la commande, et sont fournis de bonne foi sans être garantis.</p>

<h2>4. Frais de port</h2>
<p>Les frais de port sont calculés lors du paiement en fonction de votre adresse de livraison et du mode d'expédition choisi. Certains modes d'expédition offrent la livraison gratuite au-delà d'un certain montant de commande — cela est indiqué dans votre panier lorsque applicable.</p>

<h2>5. Fret hors gabarit ou lourd</h2>
<p>Les frais de livraison affichés lors du paiement sont des tarifs fixes pour les colis de taille standard. Si un article commandé est classé par le transporteur comme fret hors gabarit ou lourd en raison de sa taille, de son poids ou de ses dimensions, un supplément de livraison peut s'appliquer au-delà du tarif affiché lors du paiement. Ce supplément est facturé séparément après expédition — en passant commande, vous confirmez accepter que ce supplément puisse s'appliquer.</p>

<h2>6. Douane et droits d'importation</h2>
<p>Les envois au sein de l'UE circulent librement sans formalités douanières. Les envois vers la Norvège, la Suisse et le Royaume-Uni peuvent être soumis à des droits d'importation, à la TVA ou à des frais de dédouanement facturés par le pays de destination — ceux-ci sont à la charge du destinataire et ne sont pas inclus dans le prix affiché lors du paiement.</p>

<h2>7. Problèmes de livraison</h2>
<p>Si un envoi est perdu, significativement retardé ou arrive endommagé, contactez-nous avec votre numéro de commande ; nous mènerons une enquête auprès du transporteur et organiserons une solution.</p>
HTML,
                    'es' => <<<'HTML'
<p class="lead">OeParts envía a toda la UE, así como a Noruega, Suiza y el Reino Unido, mediante servicios de transporte rastreados y asegurados.</p>

<h2>1. Zona de entrega</h2>
<p>Enviamos a todos los estados miembros de la UE, así como a Noruega, Suiza y el Reino Unido. Los países disponibles para usted se muestran automáticamente al finalizar la compra, según su dirección de entrega.</p>

<h2>2. Transportistas</h2>
<p>Los pedidos se envían mediante DHL, DPD o GLS según el destino y el tamaño del paquete. Cada envío es rastreado y está asegurado; se envía un enlace de seguimiento por correo electrónico en cuanto su pedido se despacha, también disponible en Cuenta &rarr; Pedidos.</p>

<h2>3. Tiempo de procesamiento</h2>
<p>Los pedidos realizados antes de nuestra hora límite diaria se procesan el mismo día hábil; los realizados después se procesan al siguiente día hábil. Las estimaciones de entrega mostradas al finalizar la compra se calculan desde el envío, no desde la realización del pedido, y se proporcionan de buena fe sin garantía.</p>

<h2>4. Costes de envío</h2>
<p>El coste de envío se calcula al finalizar la compra según su dirección de entrega y el método de envío elegido. Algunos métodos de envío ofrecen envío gratuito a partir de un determinado importe de pedido — esto se muestra en su carrito cuando corresponda.</p>

<h2>5. Carga de gran tamaño o pesada</h2>
<p>Los costes de envío mostrados al finalizar la compra son tarifas fijas para paquetes de tamaño estándar. Si el transportista clasifica un artículo pedido como carga de gran tamaño o pesada debido a su tamaño, peso o dimensiones, puede aplicarse un recargo de envío adicional más allá de la tarifa mostrada al finalizar la compra. Dicho recargo se factura por separado tras el envío — al realizar un pedido, usted confirma que acepta que este recargo pueda aplicarse.</p>

<h2>6. Aduanas y derechos de importación</h2>
<p>Los envíos dentro de la UE circulan libremente sin trámites aduaneros. Los envíos a Noruega, Suiza y el Reino Unido pueden estar sujetos a derechos de importación, IVA o tasas de despacho aduanero cobrados por el país de destino — estos corren a cargo del destinatario y no están incluidos en el precio mostrado al finalizar la compra.</p>

<h2>7. Problemas de entrega</h2>
<p>Si un envío se pierde, se retrasa significativamente o llega dañado, contáctenos con su número de pedido; investigaremos con el transportista y organizaremos una solución.</p>
HTML,
                ],
            ],
        ];
    }
}
