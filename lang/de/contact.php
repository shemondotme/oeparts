<?php

return [
    // Breadcrumb / page chrome
    'breadcrumb_home' => 'Start',
    'breadcrumb_contact_us' => 'Kontakt',
    'eyebrow_contact_desk' => 'Kontakt · Team',
    'eyebrow_direct_channel' => 'Direkt · Kanal',
    'channel_email_label' => 'E-Mail',
    'channel_phone_label' => 'Telefon',
    'hours_label' => 'Öffnungszeiten',
    'eyebrow_enquiry_form' => 'Anfrage · Formular',
    'spam_protected_note' => 'Spam-geschützt · DSGVO-konform',
    'eyebrow_info_rail' => 'Info · Leiste',
    'looking_for_part_heading' => 'Suchen Sie ein Teil?',
    'looking_for_part_body' => 'Nutzen Sie für Teilesuchen direkt die Suchkonsole — Sie erhalten Ergebnisse in Sekunden.',
    'open_search_btn' => 'Suche öffnen',
    'network_error' => 'Netzwerkfehler. Bitte versuchen Sie es erneut.',

    // Validation messages (ContactFormRequest)
    'validation_email_required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
    'validation_email_invalid' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
    'validation_name_required' => 'Bitte geben Sie Ihren Namen ein.',
    'validation_subject_required' => 'Bitte wählen Sie einen Betreff.',
    'validation_message_required' => 'Bitte geben Sie Ihre Nachricht ein.',
    'validation_message_min' => 'Ihre Nachricht muss mindestens 10 Zeichen lang sein.',
    'validation_message_max' => 'Ihre Nachricht darf 5000 Zeichen nicht überschreiten.',

    // Hero
    'title' => 'Kontaktieren Sie unser Team',
    'description' => 'Erreichen Sie das OeParts-Team bei Beschaffungs-, Bestell- oder Partnerschaftsanfragen. Jede Nachricht wird an den richtigen Spezialisten weitergeleitet und innerhalb eines Werktags beantwortet.',

    // Form fields
    'name' => 'Vollständiger Name',
    'name_placeholder' => 'Erika Mustermann',
    'email' => 'E-Mail-Adresse',
    'email_placeholder' => 'name@beispiel.de',

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
    'message_min_length' => 'Mindestens 10 Zeichen.',

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
    'secure_note' => 'Ihre Nachricht wird über eine verschlüsselte Verbindung (HTTPS/TLS) gesendet und Ihre Daten werden DSGVO-konform verarbeitet.',

    // Flash / status
    'sent_success' => 'Nachricht gesendet — wir melden uns in Kürze bei Ihnen.',
    'sent_failed' => 'Beim Senden Ihrer Nachricht ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
    'otp_invalid' => 'Dieser Code ist falsch oder abgelaufen.',
];
