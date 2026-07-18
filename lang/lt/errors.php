<?php

return [
    'home' => 'Pradžia',
    'homepage' => 'Pagrindinis puslapis',
    'return_back' => 'Grįžti atgal',
    'status' => 'Būsena',
    'what_occurred' => 'Kas įvyko',

    '401' => [
        'breadcrumb' => 'Neautorizuota',
        'heading' => 'Reikalinga autentifikacija',
        'intro' => 'Norint pasiekti šį katalogo registrą, reikalingi galiojantys autentifikacijos raktai. Prisijunkite, kad patvirtintumėte savo tapatybę.',
        'glyph_label' => 'Neautentifikuota',
        'prerequisite_label' => 'Sąlyga',
        'prerequisite_value' => 'Vartotojo seansas',
        'identity_key_label' => 'Tapatybės raktas',
        'identity_key_value' => 'Svečias',
        'explanation' => 'Prašomas katalogas yra apsaugotas. Prieiga suteikiama tik autentifikuotoms verslo paskyroms arba registruotiems B2C pirkėjams. Paspauskite žemiau esantį prisijungimo langą, kad užmegztumėte saugų seansą.',
        'open_login' => 'Atidaryti prisijungimą',
    ],

    '403' => [
        'breadcrumb' => 'Prieiga ribojama',
        'heading' => 'Prieiga uždrausta',
        'intro' => 'Jūsų užklausos parametrai ar autorizacijos antraštės nesuteikia skaitymo / rašymo teisių į šį ribojamą katalogo registrą.',
        'glyph_label' => 'Prieiga atmesta',
        'prerequisite_label' => 'Sąlyga',
        'prerequisite_value' => 'Autorizacijos raktas',
        'explanation' => 'Katalogo sistema aptiko neautorizuotą srautą. Šis kelias skirtas tik operatoriams, turintiems aukštesnio lygio patvirtinimo raktus ar galiojančias administracines teises. Prisijunkite su tinkamais duomenimis arba susisiekite su pagalbos skyriumi.',
    ],

    '404' => [
        'breadcrumb' => 'Nerasta',
        'heading' => 'Dokumentas nerastas',
        'intro' => 'Prašomas kelias arba OEM dalies numeris neegzistuoja mūsų katalogo registre. Patikrinkite užklausos duomenis ir bandykite dar kartą.',
        'glyph_label' => 'Ištekius nerastas',
        'resolution_label' => 'Sprendimas',
        'resolution_value' => 'Patikrinti užklausą',
        'explanation' => 'Įvestas URL kelias neatitinka jokio aktyvaus valdiklio adreso, arba nurodytas OEM dalies ID buvo pašalintas iš mūsų aktyvaus katalogo. Grįžkite į paieškos konsolę ir pateikite naują užklausą.',
        'search_console' => 'Paieškos konsolė',
    ],

    '419' => [
        'breadcrumb' => 'Seansas baigėsi',
        'heading' => 'Seanso patvirtinimas baigėsi',
        'intro' => 'Jūsų svetainių apsaugos raktas baigė galioti dėl neaktyvaus ryšio laikotarpio. Reikia iš naujo įkelti puslapį.',
        'glyph_label' => 'Puslapio galiojimas baigėsi',
        'handshake_label' => 'Patvirtinimas',
        'handshake_value' => 'CSRF raktas',
        'action_label' => 'Veiksmas',
        'action_value' => 'Iš naujo įkelti puslapį',
        'explanation' => 'Saugumo sumetimais visos formos siunčia seansu pagrįstus patvirtinimo raktus (CSRF raktus). Kadangi jūsų ryšys buvo neaktyvus, seanso raktas baigė galioti. Iš naujo įkelkite dokumentą, kad gautumėte naują kriptografinį raktą.',
        'reload_page' => 'Įkelti iš naujo',
    ],

    '429' => [
        'breadcrumb' => 'Užklausų limitas',
        'glyph_label' => 'Per daug užklausų',
        'retry_after' => 'Bandyti vėl po',
        'what_happened' => 'Kas įvyko',
        'explanation' => 'Mūsų sistemos per trumpą laiką iš jūsų adreso gavo per daug užklausų. Tai automatinė apsaugos priemonė — sulėtinkite ir bandykite dar kartą po akimirkos.',
    ],

    '500' => [
        'breadcrumb' => 'Sistemos klaida',
        'heading' => 'Vidinė serverio klaida',
        'intro' => 'Duomenų bazės kompiliatorius arba skaičiavimo matrica susidūrė su nesutvarkyta klaidos būsena apdorojant jūsų užklausą.',
        'glyph_label' => 'Sistemos neatitikimas',
        'reporting_label' => 'Pranešimas',
        'reporting_value' => 'Automatinis',
        'explanation' => 'Serveris negalėjo suderinti išvesties matricos jūsų užklausai dėl nesutvarkytos sistemos klaidos. Ši klaida buvo automatiškai užregistruota. Mūsų katalogo komanda taiso indeksus.',
        'return_home' => 'Grįžti į pradžią',
        'support_desk' => 'Pagalbos skyrius',
    ],
];
