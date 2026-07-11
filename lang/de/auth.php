<?php

return [
    // Modal chrome
    'close' => 'Schließen',
    'welcome_back' => 'Willkommen zurück',
    'create_account' => 'Konto erstellen',
    'verify_email' => 'E-Mail verifizieren',
    'sign_in_subtitle' => 'Melden Sie sich an, um fortzufahren · Sichere Sitzung',
    'register_subtitle' => 'Kostenloses Konto · Verifizierte E-Mail',
    'otp_subtitle' => 'Einmalcode · Sichere Verifizierung',
    'sign_in' => 'Anmelden',
    'register' => 'Registrieren',

    // Login form
    'email_address' => 'E-Mail-Adresse',
    'password' => 'Passwort',
    'forgot' => 'Vergessen?',
    'signing_in' => 'Anmeldung läuft…',
    'new_here' => 'Neu hier?',
    'create_free_account' => 'Kostenloses Konto erstellen',

    // Register form
    'full_name' => 'Vollständiger Name',
    'password_min_chars' => 'Passwort · mind. :min Zeichen',
    'min_characters' => 'Mind. :min Zeichen',
    'confirm_password' => 'Passwort bestätigen',
    'agree_terms_prefix' => 'Ich stimme den',
    'terms_of_service' => 'Nutzungsbedingungen',
    'and' => 'und der',
    'privacy_policy' => 'Datenschutzerklärung zu',
    'creating' => 'Wird erstellt…',
    'already_a_member' => 'Bereits Mitglied?',
    'sign_in_instead' => 'Stattdessen anmelden',

    // OTP verification
    'enter_code_emailed_to' => 'Geben Sie den Code ein, den wir gesendet haben an',
    'verification_code' => 'Verifizierungscode',
    'verifying' => 'Wird geprüft…',
    'verify_and_continue' => 'Verifizieren & fortfahren',
    'resend_code' => 'Code erneut senden',
    'back_to_sign_in' => 'Zurück zur Anmeldung',

    // JS-side messages (component's Alpine script)
    'invalid_credentials' => 'Ungültige Anmeldedaten',
    'registration_failed' => 'Registrierung fehlgeschlagen',
    'invalid_or_expired_code' => 'Ungültiger oder abgelaufener Code.',
    'email_verified_please_sign_in' => 'E-Mail verifiziert — bitte melden Sie sich an.',
    'new_code_sent' => 'Ein neuer Code wurde an Ihre E-Mail gesendet.',
    'could_not_resend_code' => 'Der Code konnte nicht erneut gesendet werden.',

    // Controller JSON responses (App\Http\Controllers\Frontend\AuthController)
    'validation_failed' => 'Validierung fehlgeschlagen',
    'invalid_email_or_password' => 'Ungültige E-Mail-Adresse oder ungültiges Passwort.',
    'account_deactivated' => 'Ihr Konto wurde deaktiviert.',
    'email_verification_required' => 'E-Mail-Verifizierung erforderlich.',
    'login_successful' => 'Anmeldung erfolgreich.',
    'registration_successful' => 'Registrierung erfolgreich. Bitte verifizieren Sie Ihre E-Mail.',
    'logged_out_successfully' => 'Erfolgreich abgemeldet.',
    'invalid_input' => 'Ungültige Eingabe',
    'otp_verified_successfully' => 'Code erfolgreich verifiziert.',
    'otp_resent_successfully' => 'Code erneut gesendet.',
    'too_many_login_attempts' => 'Zu viele Anmeldeversuche. Bitte versuchen Sie es in :minutes Minuten erneut.',
];
