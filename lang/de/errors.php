<?php

return [
    'home' => 'Startseite',
    'homepage' => 'Startseite',
    'return_back' => 'Zurück',
    'status' => 'Status',
    'what_occurred' => 'Was ist passiert',

    '401' => [
        'breadcrumb' => 'Nicht autorisiert',
        'heading' => 'Authentifizierung erforderlich',
        'intro' => 'Der Zugriff auf dieses Katalogregister erfordert gültige Authentifizierungs-Token. Melden Sie sich an, um Ihre Identität zu bestätigen.',
        'glyph_label' => 'Nicht authentifiziert',
        'prerequisite_label' => 'Voraussetzung',
        'prerequisite_value' => 'Benutzersitzung',
        'identity_key_label' => 'Identitätsschlüssel',
        'identity_key_value' => 'Gast',
        'explanation' => 'Das angeforderte Verzeichnis ist gesichert. Der Zugriff ist auf authentifizierte Geschäftskonten oder registrierte B2C-Käufer beschränkt. Öffnen Sie das Login-Fenster unten, um eine sichere Sitzung herzustellen.',
        'open_login' => 'Anmeldung öffnen',
    ],

    '403' => [
        'breadcrumb' => 'Zugriff beschränkt',
        'heading' => 'Zugriff verweigert',
        'intro' => 'Ihre Anfrageparameter oder Autorisierungsheader gewähren keinen Lese-/Schreibzugriff auf dieses eingeschränkte Verzeichnisregister.',
        'glyph_label' => 'Zugriff verweigert',
        'prerequisite_label' => 'Voraussetzung',
        'prerequisite_value' => 'Auth-Schlüssel',
        'explanation' => 'Das Verzeichnissystem hat nicht autorisierten Datenverkehr erkannt. Dieser Pfad ist Betreibern mit höheren Berechtigungsschlüsseln oder gültigen administrativen Sicherheitsrechten vorbehalten. Melden Sie sich mit gültigen Zugangsdaten an oder kontaktieren Sie den Support.',
    ],

    '404' => [
        'breadcrumb' => 'Nicht gefunden',
        'heading' => 'Dokument nicht gefunden',
        'intro' => 'Der angeforderte Pfad oder die OEM-Teilenummer existiert nicht in unserem Katalogregister. Überprüfen Sie die Eingaben und versuchen Sie es erneut.',
        'glyph_label' => 'Ressource nicht gefunden',
        'resolution_label' => 'Lösung',
        'resolution_value' => 'Anfrage prüfen',
        'explanation' => 'Der eingegebene URL-Pfad entspricht keinem aktiven Controller-Endpunkt, oder die referenzierte OEM-Teile-ID wurde aus unserem aktiven Verzeichnis entfernt. Kehren Sie zur Suchkonsole zurück, um eine neue Anfrage zu stellen.',
        'search_console' => 'Suchkonsole',
    ],

    '419' => [
        'breadcrumb' => 'Sitzung abgelaufen',
        'heading' => 'Sitzungs-Handshake abgelaufen',
        'intro' => 'Ihr Sicherheitsschlüssel für die Formularüberprüfung ist aufgrund einer Phase der Inaktivität abgelaufen. Ein Neuladen der Seite ist erforderlich.',
        'glyph_label' => 'Seite abgelaufen',
        'handshake_label' => 'Handshake',
        'handshake_value' => 'CSRF-Token',
        'action_label' => 'Aktion',
        'action_value' => 'Seite neu laden',
        'explanation' => 'Aus Sicherheitsgründen senden alle Formulare sitzungsbasierte Verifizierungs-Token (CSRF-Schlüssel). Da Ihre Verbindung inaktiv war, ist der Sitzungsschlüssel abgelaufen. Laden Sie das Dokument neu, um ein neues kryptografisches Token anzufordern.',
        'reload_page' => 'Seite neu laden',
    ],

    '429' => [
        'breadcrumb' => 'Ratenlimit',
        'glyph_label' => 'Zu viele Anfragen',
        'retry_after' => 'Erneut versuchen nach',
        'what_happened' => 'Was ist passiert',
        'explanation' => 'Unsere Systeme haben in kurzer Zeit zu viele Anfragen von Ihrer Adresse erhalten. Dies ist eine automatische Schutzmaßnahme — bitte verlangsamen Sie und versuchen Sie es gleich erneut.',
    ],

    '500' => [
        'breadcrumb' => 'Systemfehler',
        'heading' => 'Interne Serverabweichung',
        'intro' => 'Der Datenbank-Compiler oder die Berechnungsmatrix hat bei der Bearbeitung Ihrer Anfrage einen unbehandelten Ausnahmezustand festgestellt.',
        'glyph_label' => 'Systemabweichung',
        'reporting_label' => 'Meldung',
        'reporting_value' => 'Automatisch',
        'explanation' => 'Der Server konnte die Ausgabematrix für Ihre Anfrage aufgrund einer unbehandelten Systemausnahme nicht kalibrieren. Dieser Fehler wurde automatisch protokolliert. Unser Katalogteam korrigiert die Indizes.',
        'return_home' => 'Zur Startseite',
        'support_desk' => 'Support-Kontakt',
    ],
];
