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
    'signing_in' => 'Jungiamasi…',
    'new_here' => 'Naujas čia?',
    'create_free_account' => 'Sukurti nemokamą paskyrą',

    // Register form
    'full_name' => 'Vardas, pavardė',
    'password_min_chars' => 'Slaptažodis · mažiausiai :min simbolių',
    'min_characters' => 'Mažiausiai :min simbolių',
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
];
