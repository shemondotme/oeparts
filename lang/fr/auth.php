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
    'remember_me' => 'Se souvenir de moi',
    'signing_in' => 'Connexion en cours…',
    'new_here' => 'Nouveau ici ?',
    'create_free_account' => 'Créer un compte gratuit',

    // Register form
    'full_name' => 'Nom complet',
    'password_min_chars' => 'Mot de passe · min. :min caractères, majuscules/minuscules, chiffres et symboles',
    'min_characters' => 'Min. :min caractères',
    'show_password' => 'Afficher le mot de passe',
    'hide_password' => 'Masquer le mot de passe',
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
    'registration_disabled' => "L'inscription de nouveaux comptes n'est pas disponible actuellement. Veuillez réessayer plus tard ou contacter le support.",
    'session_expired' => 'Votre session a expiré en raison d\'inactivité. Veuillez vous reconnecter.',
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

    // Password reset pages (auth/passwords/email.blade.php + reset.blade.php)
    'breadcrumb_home' => 'Accueil',
    'breadcrumb_reset_password' => 'Réinitialiser le mot de passe',
    'breadcrumb_new_password' => 'Nouveau mot de passe',
    'reset_password_title' => 'Réinitialiser le mot de passe',
    'eyebrow_request_link' => '01 · Demande de lien',
    'eyebrow_set_new_password' => '02 · Définir un nouveau mot de passe',
    'reset_password_heading' => 'Réinitialiser le mot de passe',
    'new_password_heading' => 'Nouveau mot de passe',
    'request_link_subtitle' => 'Saisissez votre e-mail · nous vous enverrons un lien sécurisé',
    'new_password_subtitle' => 'Choisissez un mot de passe fort · min. :min caractères',
    'email_verification_eyebrow' => 'Vérification de l\'e-mail',
    'credentials_reset_eyebrow' => 'Réinitialisation des identifiants',
    'send_reset_link' => 'Envoyer le lien',
    'or_divider' => 'ou',
    'back_to_homepage' => 'Retour à l\'accueil',
    'expires_minutes' => 'EXPIRE · :minutes MIN',
    'token_single_use' => 'Jeton · Usage unique',
];
