<?php

return [
    // Modal chrome
    'close' => 'Uždaryti',
    'welcome_back' => 'Sveiki sugrįžę',
    'create_account' => 'Sukurti paskyrą',
    'verify_email' => 'Patvirtinti el. paštą',
    'sign_in_subtitle' => 'Prisijunkite, kad tęstumėte · Saugi sesija',
    'register_subtitle' => 'Nemokama paskyra · Patvirtintas el. paštas',
    'otp_subtitle' => 'Vienkartinis kodas · Saugus patvirtinimas',
    'sign_in' => 'Prisijungti',
    'register' => 'Registruotis',

    // Login form
    'email_address' => 'El. pašto adresas',
    'password' => 'Slaptažodis',
    'forgot' => 'Pamiršote?',
    'remember_me' => 'Prisiminti mane',
    'signing_in' => 'Jungiamasi…',
    'new_here' => 'Naujas čia?',
    'create_free_account' => 'Sukurti nemokamą paskyrą',

    // Register form
    'full_name' => 'Vardas, pavardė',
    'password_min_chars' => 'Slaptažodis · min. :min simbolių, didžiosios/mažosios raidės, skaičiai ir simboliai',
    'min_characters' => 'Mažiausiai :min simbolių',
    'show_password' => 'Rodyti slaptažodį',
    'hide_password' => 'Slėpti slaptažodį',
    'confirm_password' => 'Patvirtinkite slaptažodį',
    'agree_terms_prefix' => 'Sutinku su',
    'terms_of_service' => 'Naudojimo sąlygomis',
    'and' => 'ir',
    'privacy_policy' => 'Privatumo politika',
    'creating' => 'Kuriama…',
    'already_a_member' => 'Jau esate narys?',
    'sign_in_instead' => 'Prisijungti',

    // OTP verification
    'enter_code_emailed_to' => 'Įveskite kodą, kurį išsiuntėme adresu',
    'verification_code' => 'Patvirtinimo kodas',
    'verifying' => 'Tikrinama…',
    'verify_and_continue' => 'Patvirtinti ir tęsti',
    'resend_code' => 'Siųsti kodą iš naujo',
    'back_to_sign_in' => 'Grįžti prie prisijungimo',

    // JS-side messages (component's Alpine script)
    'invalid_credentials' => 'Neteisingi prisijungimo duomenys',
    'registration_failed' => 'Registracija nepavyko',
    'registration_disabled' => 'Naujų paskyrų registracija šiuo metu negalima. Bandykite vėliau arba susisiekite su pagalbos tarnyba.',
    'session_expired' => 'Jūsų sesija baigėsi dėl neaktyvumo. Prisijunkite iš naujo.',
    'invalid_or_expired_code' => 'Kodas neteisingas arba nebegalioja.',
    'email_verified_please_sign_in' => 'El. paštas patvirtintas — prisijunkite.',
    'new_code_sent' => 'Naujas kodas išsiųstas jūsų el. paštu.',
    'could_not_resend_code' => 'Nepavyko iš naujo išsiųsti kodo.',

    // Controller JSON responses (App\Http\Controllers\Frontend\AuthController)
    'validation_failed' => 'Patvirtinimas nepavyko',
    'invalid_email_or_password' => 'Neteisingas el. paštas arba slaptažodis.',
    'account_deactivated' => 'Jūsų paskyra buvo deaktyvuota.',
    'email_verification_required' => 'Būtinas el. pašto patvirtinimas.',
    'login_successful' => 'Prisijungta sėkmingai.',
    'registration_successful' => 'Registracija sėkminga. Patvirtinkite savo el. paštą.',
    'logged_out_successfully' => 'Sėkmingai atsijungta.',
    'invalid_input' => 'Neteisinga įvestis',
    'otp_verified_successfully' => 'Kodas sėkmingai patvirtintas.',
    'otp_resent_successfully' => 'Kodas sėkmingai išsiųstas iš naujo.',
    'too_many_login_attempts' => 'Per daug prisijungimo bandymų. Bandykite dar kartą po :minutes min.',

    // Password reset pages (auth/passwords/email.blade.php + reset.blade.php)
    'breadcrumb_home' => 'Pradžia',
    'breadcrumb_reset_password' => 'Slaptažodžio atkūrimas',
    'breadcrumb_new_password' => 'Naujas slaptažodis',
    'reset_password_title' => 'Slaptažodžio atkūrimas',
    'eyebrow_request_link' => '01 · Nuorodos užklausa',
    'eyebrow_set_new_password' => '02 · Naujo slaptažodžio nustatymas',
    'reset_password_heading' => 'Slaptažodžio atkūrimas',
    'new_password_heading' => 'Naujas slaptažodis',
    'request_link_subtitle' => 'Įveskite savo el. paštą · atsiųsime saugią atkūrimo nuorodą',
    'new_password_subtitle' => 'Pasirinkite stiprų slaptažodį · min. :min simbolių',
    'email_verification_eyebrow' => 'El. pašto patvirtinimas',
    'credentials_reset_eyebrow' => 'Prisijungimo duomenų atkūrimas',
    'send_reset_link' => 'Siųsti nuorodą',
    'or_divider' => 'arba',
    'back_to_homepage' => 'Grįžti į pradžią',
    'expires_minutes' => 'GALIOJA · :minutes MIN',
    'token_single_use' => 'Raktas · Vienkartinis',
];
