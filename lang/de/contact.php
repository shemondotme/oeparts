<?php

return [
    // Hero
    'title' => 'Kontaktieren Sie unser Team',
    'description' => 'Erreichen Sie das OeParts-Team bei Beschaffungs-, Bestell- oder Partnerschaftsanfragen. Jede Nachricht wird an den richtigen Spezialisten weitergeleitet und innerhalb eines Werktags beantwortet.',

    // Form fields
    'name' => 'Vollständiger Name',
    'name_placeholder' => 'Erika Mustermann',
    'email' => 'Geschäftliche E-Mail',
    'email_placeholder' => 'name@firma.eu',

    // Email verification
    'verify_email' => 'Code senden',
    'sending' => 'Wird gesendet',
    'email_verification_note' => 'Wir senden einen 6-stelligen Code, um die Adresse zu verifizieren, bevor Ihre Nachricht weitergeleitet wird.',
    'verification_code' => 'Verifizierungscode',
    'verify' => 'Verifizieren',
    'verifying' => 'Wird geprüft',
    'email_verified' => 'E-Mail verifiziert',
    'change_email' => 'E-Mail ändern',
    'code_sent_note' => 'Code gesendet. Geben Sie den 6-stelligen Code aus Ihrem Posteingang ein, um fortzufahren.',
    'resend_code' => 'Code erneut senden',

    // Subject
    'subject' => 'Betreff',
    'select_subject' => 'Betreff auswählen…',
    'subjects' => [
        'general_inquiry' => 'Allgemeine Anfrage',
        'part_not_found' => 'Teil nicht gefunden',
        'order_issue' => 'Problem mit bestehender Bestellung',
        'shipping_question' => 'Frage zum Versand',
        'return_refund' => 'Rückgabe oder Erstattung',
        'b2b_partnership' => 'B2B-Partnerschaft',
        'other' => 'Sonstiges',
    ],

    // Optional / conditional fields
    'order_number' => 'Bestellnummer',
    'order_number_placeholder' => 'ORD-2026-00123',
    'oem_number' => 'OEM-Nummer',
    'oem_number_placeholder' => '11127556503',
    'manufacturer' => 'Hersteller / Marke',
    'manufacturer_placeholder' => 'BMW, Audi, Mercedes…',
    'company_name' => 'Firmenname',
    'company_name_placeholder' => 'Acme Automotive GmbH',
    'car_model' => 'Fahrzeugmodell',
    'car_model_placeholder' => '3er, A4, C-Klasse…',
    'vehicle_year' => 'Baujahr',
    'vehicle_year_placeholder' => '2018',
    'vin_number' => 'FIN (optional)',
    'vin_number_placeholder' => '17-stellige Fahrzeug-Identifizierungsnummer',
    'section_order_details' => 'Bestelldetails',
    'section_part_details' => 'Teile- & Fahrzeugdetails',
    'section_b2b_details' => 'Firmendetails',

    // Message
    'message' => 'Ihre Nachricht',
    'message_placeholder' => 'Sagen Sie uns, was Sie brauchen — Teil, Fahrzeug, Menge, Zeitrahmen…',
    'message_min_length' => 'Mindestens 20 Zeichen.',

    // Submit
    'send_message' => 'Nachricht senden',

    // Sidebar info cards
    'email_us' => 'E-Mail an uns',
    'whatsapp_label' => 'WhatsApp',
    'viber_label' => 'Viber',
    'address_label' => 'Adresse',
    'response_time' => 'Antwortzeit',
    'response_time_value' => 'Innerhalb 1 Werktags',
    'secure' => 'Sicherer Kanal',
    'secure_note' => 'Ihre Nachricht wird Ende-zu-Ende mit TLS 1.3 verschlüsselt und Ihre Daten werden DSGVO-konform verarbeitet.',

    // Flash / status
    'sent_success' => 'Nachricht gesendet — wir melden uns in Kürze bei Ihnen.',
    'sent_failed' => 'Beim Senden Ihrer Nachricht ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
    'otp_sent' => 'Verifizierungscode an Ihre E-Mail gesendet.',
    'otp_invalid' => 'Dieser Code ist falsch oder abgelaufen.',
];
