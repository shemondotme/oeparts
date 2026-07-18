<?php

return [
    'home' => 'Home',
    'homepage' => 'Homepage',
    'return_back' => 'Return Back',
    'status' => 'Status',
    'what_occurred' => 'What occurred',

    '401' => [
        'breadcrumb' => 'Unauthorized',
        'heading' => 'Authentication Required',
        'intro' => 'Access to this catalog register requires valid authentication tokens. Log in to verify your identity credentials.',
        'glyph_label' => 'Unauthenticated',
        'prerequisite_label' => 'Pre-requisite',
        'prerequisite_value' => 'User Session',
        'identity_key_label' => 'Identity Key',
        'identity_key_value' => 'Guest',
        'explanation' => 'The requested directory is secure. Access is limited to authenticated trade accounts or registered B2C buyers. Trigger the login panel below to establish secure session headers.',
        'open_login' => 'Open Login',
    ],

    '403' => [
        'breadcrumb' => 'Access Limit',
        'heading' => 'Access Forbidden',
        'intro' => 'Your request parameters or authorization headers do not grant read/write access to this restricted directory ledger.',
        'glyph_label' => 'Access Denied',
        'prerequisite_label' => 'Pre-requisite',
        'prerequisite_value' => 'Auth Key',
        'explanation' => 'The directory system detected unauthorized traffic parameters. This path is restricted to operators possessing higher verification keys or valid administrative security guards. Log in with compliant credentials or contact the support desk.',
    ],

    '404' => [
        'breadcrumb' => 'Not Found',
        'heading' => 'Document Not Found',
        'intro' => 'The requested path or genuine OEM part number does not exist in our catalog register. Verify the query inputs and try again.',
        'glyph_label' => 'Resource not found',
        'resolution_label' => 'Resolution',
        'resolution_value' => 'Verify query',
        'explanation' => 'The URL path entered does not map to any active controller endpoint, or the OEM part ID referenced has been removed from our active directory ledger. Return to the search console to submit a fresh query.',
        'search_console' => 'Search Console',
    ],

    '419' => [
        'breadcrumb' => 'Session Expired',
        'heading' => 'Session Handshake Expired',
        'intro' => 'Your cross-site request validation key timed out due to a period of inactive connection. A page reload is required.',
        'glyph_label' => 'Page Expired',
        'handshake_label' => 'Handshake',
        'handshake_value' => 'CSRF Token',
        'action_label' => 'Action',
        'action_value' => 'Reload page',
        'explanation' => 'For security, all forms submit session-based verification tokens (CSRF keys). Since your connection has remained idle, the session key expired. Reload the document to request a new cryptographic token.',
        'reload_page' => 'Reload Page',
    ],

    '429' => [
        'breadcrumb' => 'Rate limit',
        'glyph_label' => 'Too many requests',
        'retry_after' => 'Retry after',
        'what_happened' => 'What happened',
        'explanation' => 'Our systems received too many requests from your address in a short window. This is an automatic safeguard — please slow down and try again in a moment.',
    ],

    '500' => [
        'breadcrumb' => 'System Error',
        'heading' => 'Internal Server Discrepancy',
        'intro' => 'The database compiler or calculation matrix encountered an unhandled exception state while serving your query.',
        'glyph_label' => 'System Discrepancy',
        'reporting_label' => 'Reporting',
        'reporting_value' => 'Automated',
        'explanation' => 'The server was unable to calibrate the output matrix for your request due to an unhandled system exception. This error has been logged automatically. Our catalog team is correcting the indexes.',
        'return_home' => 'Return Home',
        'support_desk' => 'Support Desk',
    ],
];
