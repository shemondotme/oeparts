<?php

return [
    // Modal chrome
    'close' => 'Fermer',
    'welcome_back' => 'Bon retour',
    'create_account' => 'Créer un compte',
    'verify_email' => "Vérifier l'e-mail",
    'sign_in_subtitle' => 'Connectez-vous pour continuer · Session sécurisée',
    'register_subtitle' => 'Compte gratuit · E-mail vérifié',
    'otp_subtitle' => 'Code à usage unique · Vérification sécurisée',
    'sign_in' => 'Se connecter',
    'register' => "S'inscrire",

    // Login form
    'email_address' => 'Adresse e-mail',
    'password' => 'Mot de passe',
    'forgot' => 'Oublié ?',
    'signing_in' => 'Connexion en cours…',
    'new_here' => 'Nouveau ici ?',
    'create_free_account' => 'Créer un compte gratuit',

    // Register form
    'full_name' => 'Nom complet',
    'password_min_chars' => 'Mot de passe · min. :min caractères',
    'min_characters' => 'Min. :min caractères',
    'confirm_password' => 'Confirmer le mot de passe',
    'agree_terms_prefix' => "J'accepte les",
    'terms_of_service' => "Conditions d'utilisation",
    'and' => 'et la',
    'privacy_policy' => 'Politique de confidentialité',
    'creating' => 'Création en cours…',
    'already_a_member' => 'Déjà membre ?',
    'sign_in_instead' => 'Se connecter à la place',

    // OTP verification
    'enter_code_emailed_to' => 'Saisissez le code envoyé à',
    'verification_code' => 'Code de vérification',
    'verifying' => 'Vérification en cours…',
    'verify_and_continue' => 'Vérifier et continuer',
    'resend_code' => 'Renvoyer le code',
    'back_to_sign_in' => 'Retour à la connexion',

    // JS-side messages (component's Alpine script)
    'invalid_credentials' => 'Identifiants invalides',
    'registration_failed' => "Échec de l'inscription",
    'invalid_or_expired_code' => 'Code invalide ou expiré.',
    'email_verified_please_sign_in' => 'E-mail vérifié — veuillez vous connecter.',
    'new_code_sent' => 'Un nouveau code a été envoyé à votre e-mail.',
    'could_not_resend_code' => "Impossible de renvoyer le code.",

    // Controller JSON responses (App\Http\Controllers\Frontend\AuthController)
    'validation_failed' => 'Échec de la validation',
    'invalid_email_or_password' => 'E-mail ou mot de passe invalide.',
    'account_deactivated' => 'Votre compte a été désactivé.',
    'email_verification_required' => "Vérification de l'e-mail requise.",
    'login_successful' => 'Connexion réussie.',
    'registration_successful' => 'Inscription réussie. Veuillez vérifier votre e-mail.',
    'logged_out_successfully' => 'Déconnexion réussie.',
    'invalid_input' => 'Saisie invalide',
    'otp_verified_successfully' => 'Code vérifié avec succès.',
    'otp_resent_successfully' => 'Code renvoyé avec succès.',
    'too_many_login_attempts' => 'Trop de tentatives de connexion. Veuillez réessayer dans :minutes minutes.',
];
