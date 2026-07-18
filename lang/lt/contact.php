<?php

return [
    // Breadcrumb / page chrome
    'breadcrumb_home' => 'Pradžia',
    'breadcrumb_contact_us' => 'Kontaktai',
    'eyebrow_contact_desk' => 'Kontaktas · Skyrius',
    'eyebrow_direct_channel' => 'Tiesioginis · Kanalas',
    'channel_email_label' => 'El. paštas',
    'channel_phone_label' => 'Telefonas',
    'hours_label' => 'Darbo laikas',
    'eyebrow_enquiry_form' => 'Užklausa · Forma',
    'spam_protected_note' => 'Apsaugota nuo šlamšto · BDAR atitinka',
    'eyebrow_info_rail' => 'Info · Skydelis',
    'looking_for_part_heading' => 'Ieškote dalies?',
    'looking_for_part_body' => 'Dalių paieškai naudokite paieškos konsolę tiesiogiai — rezultatus gausite per kelias sekundes.',
    'open_search_btn' => 'Atidaryti paiešką',
    'network_error' => 'Tinklo klaida. Bandykite dar kartą.',

    // Validation messages (ContactFormRequest)
    'validation_email_required' => 'Įveskite savo el. pašto adresą.',
    'validation_email_invalid' => 'Įveskite tinkamą el. pašto adresą.',
    'validation_name_required' => 'Įveskite savo vardą.',
    'validation_subject_required' => 'Pasirinkite temą.',
    'validation_message_required' => 'Įveskite savo žinutę.',
    'validation_message_min' => 'Jūsų žinutę turi sudaryti bent 10 simbolių.',
    'validation_message_max' => 'Jūsų žinutė negali viršyti 5000 simbolių.',

    // Hero
    'title' => 'Susisiekite su mūsų komanda',
    'description' => 'Susisiekite su OeParts komanda dėl tiekimo, užsakymų ar partnerystės klausimų. Kiekviena žinutė nukreipiama tinkamam specialistui ir gaunate atsakymą per vieną darbo dieną.',

    // Form fields
    'name' => 'Vardas, pavardė',
    'name_placeholder' => 'Jonas Jonaitis',
    'email' => 'El. pašto adresas',
    'email_placeholder' => 'vardas@pavyzdys.lt',

    // Email verification
    'verify_email' => 'Siųsti kodą',
    'sending' => 'Siunčiama',
    'email_verification_note' => 'Prieš nukreipdami jūsų žinutę, atsiųsime 6 skaitmenų kodą adresui patvirtinti.',
    'verification_code' => 'Patvirtinimo kodas',
    'verify' => 'Patvirtinti',
    'verifying' => 'Tikrinama',
    'email_verified' => 'El. paštas patvirtintas',
    'change_email' => 'Keisti el. paštą',
    'code_sent_note' => 'Kodas išsiųstas. Įveskite 6 skaitmenų kodą iš savo pašto dėžutės, kad tęstumėte.',
    'resend_code' => 'Siųsti kodą iš naujo',

    // Subject
    'subject' => 'Tema',
    'select_subject' => 'Pasirinkite temą…',
    'subjects' => [
        'general_inquiry' => 'Bendras klausimas',
        'part_not_found' => 'Dalis nerasta',
        'order_issue' => 'Esamo užsakymo problema',
        'shipping_question' => 'Klausimas apie pristatymą',
        'return_refund' => 'Grąžinimas ar pinigų grąžinimas',
        'b2b_partnership' => 'B2B partnerystė',
        'other' => 'Kita',
    ],

    // Optional / conditional fields
    'order_number' => 'Užsakymo numeris',
    'order_number_placeholder' => 'ORD-2026-00123',
    'oem_number' => 'OEM numeris',
    'oem_number_placeholder' => '11127556503',
    'manufacturer' => 'Gamintojas / markė',
    'manufacturer_placeholder' => 'BMW, Audi, Mercedes…',
    'company_name' => 'Įmonės pavadinimas',
    'company_name_placeholder' => 'UAB Acme Automotive',
    'car_model' => 'Automobilio modelis',
    'car_model_placeholder' => '3 serija, A4, C klasė…',
    'vehicle_year' => 'Metai',
    'vehicle_year_placeholder' => '2018',
    'vin_number' => 'VIN (nebūtina)',
    'vin_number_placeholder' => '17 ženklų transporto priemonės identifikavimo numeris',
    'section_order_details' => 'Užsakymo informacija',
    'section_part_details' => 'Dalies ir automobilio informacija',
    'section_b2b_details' => 'Įmonės informacija',

    // Message
    'message' => 'Jūsų žinutė',
    'message_placeholder' => 'Papasakokite, ko jums reikia — dalis, automobilis, kiekis, terminas…',
    'message_min_length' => 'Mažiausiai 10 simbolių.',

    // Submit
    'send_message' => 'Siųsti žinutę',

    // Sidebar info cards
    'email_us' => 'Rašykite mums',
    'whatsapp_label' => 'WhatsApp',
    'viber_label' => 'Viber',
    'address_label' => 'Adresas',
    'response_time' => 'Atsakymo laikas',
    'response_time_value' => 'Per 1 darbo dieną',
    'secure' => 'Saugus kanalas',
    'secure_note' => 'Jūsų žinutė siunčiama šifruotu ryšiu (HTTPS/TLS), o duomenys tvarkomi laikantis BDAR.',

    // Flash / status
    'sent_success' => 'Žinutė išsiųsta — netrukus su jumis susisieksime.',
    'sent_failed' => 'Siunčiant jūsų žinutę įvyko klaida. Bandykite dar kartą.',
    'otp_invalid' => 'Kodas neteisingas arba nebegalioja.',
];
