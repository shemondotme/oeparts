<?php

return [
    'home' => 'Accueil',
    'homepage' => 'Page d\'accueil',
    'return_back' => 'Retour',
    'status' => 'Statut',
    'what_occurred' => 'Ce qui s\'est passé',

    '401' => [
        'breadcrumb' => 'Non autorisé',
        'heading' => 'Authentification requise',
        'intro' => 'L\'accès à ce registre de catalogue nécessite des jetons d\'authentification valides. Connectez-vous pour vérifier votre identité.',
        'glyph_label' => 'Non authentifié',
        'prerequisite_label' => 'Prérequis',
        'prerequisite_value' => 'Session utilisateur',
        'identity_key_label' => 'Clé d\'identité',
        'identity_key_value' => 'Invité',
        'explanation' => 'Le répertoire demandé est sécurisé. L\'accès est réservé aux comptes professionnels authentifiés ou aux acheteurs B2C enregistrés. Ouvrez le panneau de connexion ci-dessous pour établir une session sécurisée.',
        'open_login' => 'Ouvrir la connexion',
    ],

    '403' => [
        'breadcrumb' => 'Accès limité',
        'heading' => 'Accès interdit',
        'intro' => 'Vos paramètres de requête ou vos en-têtes d\'autorisation ne permettent pas l\'accès en lecture/écriture à ce registre restreint.',
        'glyph_label' => 'Accès refusé',
        'prerequisite_label' => 'Prérequis',
        'prerequisite_value' => 'Clé d\'authentification',
        'explanation' => 'Le système de répertoire a détecté un trafic non autorisé. Ce chemin est réservé aux opérateurs disposant de clés de vérification supérieures ou de droits administratifs valides. Connectez-vous avec des identifiants conformes ou contactez le support.',
    ],

    '404' => [
        'breadcrumb' => 'Introuvable',
        'heading' => 'Document introuvable',
        'intro' => 'Le chemin demandé ou la référence OEM n\'existe pas dans notre registre de catalogue. Vérifiez les paramètres et réessayez.',
        'glyph_label' => 'Ressource introuvable',
        'resolution_label' => 'Résolution',
        'resolution_value' => 'Vérifier la requête',
        'explanation' => 'Le chemin d\'URL saisi ne correspond à aucun point de terminaison actif, ou la référence OEM indiquée a été retirée de notre registre actif. Retournez à la console de recherche pour soumettre une nouvelle requête.',
        'search_console' => 'Console de recherche',
    ],

    '419' => [
        'breadcrumb' => 'Session expirée',
        'heading' => 'Session expirée',
        'intro' => 'Votre clé de validation de sécurité a expiré en raison d\'une période d\'inactivité. Un rechargement de la page est nécessaire.',
        'glyph_label' => 'Page expirée',
        'handshake_label' => 'Établissement',
        'handshake_value' => 'Jeton CSRF',
        'action_label' => 'Action',
        'action_value' => 'Recharger la page',
        'explanation' => 'Pour des raisons de sécurité, tous les formulaires envoient des jetons de vérification basés sur la session (clés CSRF). Votre connexion étant restée inactive, la clé de session a expiré. Rechargez le document pour obtenir un nouveau jeton cryptographique.',
        'reload_page' => 'Recharger la page',
    ],

    '429' => [
        'breadcrumb' => 'Limite de requêtes',
        'glyph_label' => 'Trop de requêtes',
        'retry_after' => 'Réessayer après',
        'what_happened' => 'Ce qui s\'est passé',
        'explanation' => 'Nos systèmes ont reçu trop de requêtes depuis votre adresse en peu de temps. Il s\'agit d\'une protection automatique — veuillez ralentir et réessayer dans un instant.',
    ],

    '500' => [
        'breadcrumb' => 'Erreur système',
        'heading' => 'Anomalie serveur interne',
        'intro' => 'Le compilateur de base de données ou la matrice de calcul a rencontré une exception non gérée lors du traitement de votre requête.',
        'glyph_label' => 'Anomalie système',
        'reporting_label' => 'Signalement',
        'reporting_value' => 'Automatique',
        'explanation' => 'Le serveur n\'a pas pu calibrer la matrice de sortie pour votre requête en raison d\'une exception système non gérée. Cette erreur a été enregistrée automatiquement. Notre équipe catalogue corrige les index.',
        'return_home' => 'Retour à l\'accueil',
        'support_desk' => 'Support',
    ],
];
