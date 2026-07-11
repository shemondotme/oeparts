<?php

return [
    // Hero
    'title' => 'Contactez notre équipe',
    'description' => 'Contactez l\'équipe OeParts pour toute question de sourcing, de commande ou de partenariat. Chaque message est acheminé vers le bon spécialiste et traité sous un jour ouvré.',

    // Form fields
    'name' => 'Nom complet',
    'name_placeholder' => 'Jeanne Dupont',
    'email' => 'E-mail professionnel',
    'email_placeholder' => 'nom@entreprise.eu',

    // Email verification
    'verify_email' => 'Envoyer le code',
    'sending' => 'Envoi en cours',
    'email_verification_note' => 'Nous enverrons un code à 6 chiffres pour vérifier l\'adresse avant l\'envoi de votre message.',
    'verification_code' => 'Code de vérification',
    'verify' => 'Vérifier',
    'verifying' => 'Vérification en cours',
    'email_verified' => 'E-mail vérifié',
    'change_email' => 'Changer d\'e-mail',
    'code_sent_note' => 'Code envoyé. Saisissez le code à 6 chiffres reçu par e-mail pour continuer.',
    'resend_code' => 'Renvoyer le code',

    // Subject
    'subject' => 'Sujet',
    'select_subject' => 'Sélectionnez un sujet…',
    'subjects' => [
        'general_inquiry' => 'Demande générale',
        'part_not_found' => 'Pièce introuvable',
        'order_issue' => 'Problème sur une commande existante',
        'shipping_question' => 'Question sur la livraison',
        'return_refund' => 'Retour ou remboursement',
        'b2b_partnership' => 'Partenariat B2B',
        'other' => 'Autre',
    ],

    // Optional / conditional fields
    'order_number' => 'Numéro de commande',
    'order_number_placeholder' => 'ORD-2026-00123',
    'oem_number' => 'Numéro OEM',
    'oem_number_placeholder' => '11127556503',
    'manufacturer' => 'Fabricant / marque',
    'manufacturer_placeholder' => 'BMW, Audi, Mercedes…',
    'company_name' => 'Nom de l\'entreprise',
    'company_name_placeholder' => 'Acme Automotive SARL',
    'car_model' => 'Modèle du véhicule',
    'car_model_placeholder' => 'Série 3, A4, Classe C…',
    'vehicle_year' => 'Année',
    'vehicle_year_placeholder' => '2018',
    'vin_number' => 'VIN (facultatif)',
    'vin_number_placeholder' => 'Numéro d\'identification du véhicule à 17 caractères',
    'section_order_details' => 'Détails de la commande',
    'section_part_details' => 'Détails de la pièce et du véhicule',
    'section_b2b_details' => 'Détails de l\'entreprise',

    // Message
    'message' => 'Votre message',
    'message_placeholder' => 'Indiquez-nous ce dont vous avez besoin — pièce, véhicule, quantité, délai…',
    'message_min_length' => 'Minimum 20 caractères.',

    // Submit
    'send_message' => 'Envoyer le message',

    // Sidebar info cards
    'email_us' => 'Envoyez-nous un e-mail',
    'response_time' => 'Délai de réponse',
    'response_time_value' => 'Sous 1 jour ouvré',
    'secure' => 'Canal sécurisé',
    'secure_note' => 'Votre message est chiffré de bout en bout avec TLS 1.3 et vos données sont traitées conformément au RGPD.',

    // Flash / status
    'sent_success' => 'Message envoyé — nous reviendrons vers vous rapidement.',
    'sent_failed' => 'Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer.',
    'otp_sent' => 'Code de vérification envoyé à votre e-mail.',
    'otp_invalid' => 'Ce code est incorrect ou a expiré.',
];
