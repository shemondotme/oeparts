<?php

/*
|--------------------------------------------------------------------------
| Site migration (.oeparts) - English text
|--------------------------------------------------------------------------
|
| Skeleton: the error catalogue. Every case of App\Services\Migration\MigrationErrorCode has a
| title (what happened), a message (what it means) and a fix (what to do), in plain words for
| someone who is not a developer. :name placeholders are filled from the exception's parameters.
|
| The web installer reads THIS file directly (it runs before the database exists), so it must
| stay a plain array with no function calls. The de/es/fr/lt files and their key-parity test
| arrive with the interface text in Phase 12; Laravel falls back to this file until then.
|
*/

return [

    'error' => [

        // The package file itself
        'package_not_found' => [
            'title' => 'File not found',
            'message' => 'We cannot find that file.',
            'fix' => 'Check the file name, or upload it again.',
        ],
        'package_not_oeparts' => [
            'title' => 'Not an OeParts file',
            'message' => 'This is not an OeParts package.',
            'fix' => 'Use a file that ends in .oeparts.',
        ],
        'package_incomplete' => [
            'title' => 'File incomplete',
            'message' => 'The file looks incomplete.',
            'fix' => 'Upload it again and wait for the upload to finish.',
        ],
        'package_corrupt' => [
            'title' => 'File damaged',
            'message' => 'Part :part of the file is damaged.',
            'fix' => 'Copy the file again and compare its size with the original.',
        ],
        'package_text_mode' => [
            'title' => 'File was uploaded in text mode',
            'message' => 'The file was uploaded in text mode, which changes its bytes.',
            'fix' => 'Upload it again in binary mode (in most FTP programs: "Binary" or "Auto").',
        ],
        'package_hash_mismatch' => [
            'title' => 'File changed on the way',
            'message' => 'The file does not match the checksum it was shared with.',
            'fix' => 'Fetch it again.',
        ],
        'package_changed' => [
            'title' => 'File changed after it was checked',
            'message' => 'The file changed after it was checked.',
            'fix' => 'Do not touch the file during an import. Start again; nothing was changed.',
        ],

        // Passphrase and key
        'passphrase_empty' => [
            'title' => 'Passphrase needed',
            'message' => 'This file is protected, so a passphrase is required.',
            'fix' => 'Enter the passphrase that was shown when the file was exported.',
        ],
        'passphrase_wrong' => [
            'title' => 'Wrong passphrase',
            'message' => 'That passphrase does not open this file.',
            'fix' => 'Check for typing mistakes and capital letters.',
        ],
        'key_escrow_lost' => [
            'title' => 'The key for this run is gone',
            'message' => 'The temporary key kept for this run is missing, expired or no longer readable.',
            'fix' => 'Open the package again with its passphrase.',
        ],
        'passphrase_locked' => [
            'title' => 'Too many wrong passphrases',
            'message' => 'Too many wrong passphrases were entered in a short time.',
            'fix' => 'Wait :seconds seconds and try again.',
        ],
        'passphrase_over_http' => [
            'title' => 'Connection is not secure',
            'message' => 'For safety the installer will not accept a passphrase over a connection that is not secure.',
            'fix' => 'Open this page over https, or import from the command line.',
        ],
        'kdf_memory' => [
            'title' => 'Not enough memory',
            'message' => 'This server has too little memory to open the file.',
            'fix' => 'Raise memory_limit to :mb MB, or run the import from the command line on a bigger machine.',
        ],
        'kdf_unavailable' => [
            'title' => 'Required PHP extension missing',
            'message' => 'This file needs the PHP sodium extension, which is not enabled here.',
            'fix' => 'Enable ext-sodium, or export the file again with the compatible protection.',
        ],
        'kdf_passphrase_weak' => [
            'title' => 'Passphrase too weak',
            'message' => 'This file\'s protection needs a stronger passphrase.',
            'fix' => 'Use the generated passphrase.',
        ],

        // Compatibility
        'version_too_new' => [
            'title' => 'File is from a newer version',
            'message' => 'This file was made by a newer version of OeParts than this site runs.',
            'fix' => 'Update this site to the same version as the site the file came from.',
        ],
        'migration_set_differs' => [
            'title' => 'Different releases',
            'message' => 'This file and this site are not the same release.',
            'fix' => 'Make both sites the same OeParts release, then try again.',
        ],

        // Preflight
        'preflight_php' => [
            'title' => 'PHP version',
            'message' => 'This server runs PHP :found, and PHP :needed or newer is required.',
            'fix' => 'Switch the site to a newer PHP version in your hosting control panel.',
        ],
        'preflight_extension' => [
            'title' => 'PHP extension missing',
            'message' => 'The PHP extension :extension is not enabled.',
            'fix' => 'Enable it in your hosting control panel, or ask your host.',
        ],
        'preflight_db_version' => [
            'title' => 'Database version',
            'message' => 'The database server is too old (:found; :needed or newer is required).',
            'fix' => 'Upgrade the database server, or move the site to a host with a newer one.',
        ],
        'preflight_db_engine' => [
            'title' => 'Database engine',
            'message' => 'This database server works, but product search will use a slower fallback.',
            'fix' => 'No action is needed. MySQL 8 gives the fastest product search on a very large catalogue.',
        ],
        'preflight_db_privileges' => [
            'title' => 'Database permissions',
            'message' => 'The database user is missing a permission the import needs (:privilege).',
            'fix' => 'Grant the permission to the database user, or use a user that has it.',
        ],
        'preflight_disk' => [
            'title' => 'Not enough disk space',
            'message' => 'The import needs about :needed free, and only :free is available.',
            'fix' => 'Free some space or move the site to a bigger disk, then check again.',
        ],
        'preflight_memory' => [
            'title' => 'Not enough memory',
            'message' => 'PHP may use only :found of memory here, and :needed is needed.',
            'fix' => 'Raise memory_limit, or run the import from the command line.',
        ],
        'preflight_not_writable' => [
            'title' => 'Folder not writable',
            'message' => 'The folder :path cannot be written to.',
            'fix' => 'Give the web server user write permission on that folder.',
        ],

        // Target and confirmation
        'target_not_fresh' => [
            'title' => 'This site already has data',
            'message' => 'The installer can only import into a brand-new install, and this site already has data.',
            'fix' => 'Use the admin panel (System, Migration) or the command line to replace an existing site, with the typed confirmation.',
        ],
        'replace_not_confirmed' => [
            'title' => 'Replacing was not confirmed',
            'message' => 'This site already has data and nothing was confirmed.',
            'fix' => 'Type REPLACE in the admin panel, or add --confirm=REPLACE --replace-populated on the command line.',
        ],
        'recovery_point_too_large' => [
            'title' => 'Too much data for the built-in safety copy',
            'message' => 'This site has too many rows for the built-in recovery point, so the import cannot start safely.',
            'fix' => 'Take a normal backup first, then raise migration.import.recovery_max_rows to :rows knowingly (it needs about :disk of disk and about :minutes minutes), or import into a fresh install.',
        ],

        // Locks and runs
        'lock_busy' => [
            'title' => 'Something else is running',
            'message' => 'A :what is running (:age so far).',
            'fix' => 'Wait for it to finish, then try again.',
        ],
        'lock_lost' => [
            'title' => 'Another job took over',
            'message' => 'Another backup or update took over while the export was idle.',
            'fix' => 'Start the export again.',
        ],
        'freeze_active' => [
            'title' => 'The shop is already frozen',
            'message' => 'A frozen export is already in progress or waiting to be released.',
            'fix' => 'Finish or release it first (oeparts:migrate:unfreeze).',
        ],
        'freeze_needs_queue_or_cli' => [
            'title' => 'Cannot freeze from this page',
            'message' => 'A freeze export cannot be started from this page on this server: it needs a queue worker that also processes the "migration" queue.',
            'fix' => 'Run the command line command shown, or add "migration" to your worker command and restart it.',
        ],
        'no_worker' => [
            'title' => 'Nothing picked up the export',
            'message' => 'Nothing picked up the export, so your shop was not frozen.',
            'fix' => 'Check the queue worker command.',
        ],
        'run_stale' => [
            'title' => 'This run was abandoned',
            'message' => 'This run stopped moving for a long time and was closed.',
            'fix' => 'Start it again.',
        ],
        'stage_stalled' => [
            'title' => 'The import stopped',
            'message' => 'The import stopped after repeated errors. Your data is safe.',
            'fix' => 'Resume from the server (oeparts:migrate:import --resume) or roll back.',
        ],
        'import_failed_step' => [
            'title' => 'The import stopped',
            'message' => 'The import stopped while :phase. Your data is safe.',
            'fix' => 'Resume it, or roll back to the copy that was taken before it started.',
        ],
        'rollback_failed' => [
            'title' => 'Rolling back failed',
            'message' => 'The automatic roll back did not complete.',
            'fix' => 'Run: php artisan oeparts:migrate:import --rollback',
        ],
        'token_invalid' => [
            'title' => 'Link not valid',
            'message' => 'This page link is not valid any more.',
            'fix' => 'Open the page again from the start.',
        ],
        'wizard_owned_elsewhere' => [
            'title' => 'Started in another browser',
            'message' => 'Someone else started this install in another browser.',
            'fix' => 'Continue there, or wait until it times out and start again.',
        ],

        // Getting the file onto the server
        'url_not_https' => [
            'title' => 'Link must use https',
            'message' => 'Only secure (https) links can be used.',
            'fix' => 'Use a link that starts with https://.',
        ],
        'url_blocked' => [
            'title' => 'Link not allowed',
            'message' => 'That address points to a place the server is not allowed to fetch from.',
            'fix' => 'Use a public link, or put the file in storage/app/import with FTP or SFTP.',
        ],
        'url_redirect_blocked' => [
            'title' => 'Link redirected somewhere not allowed',
            'message' => 'The link redirected to an address the server is not allowed to fetch from.',
            'fix' => 'Use the final address directly.',
        ],
        'url_too_large' => [
            'title' => 'File too big',
            'message' => 'The file behind the link is bigger than the allowed size.',
            'fix' => 'Put the file in storage/app/import with FTP or SFTP instead.',
        ],
        'url_timeout' => [
            'title' => 'Download timed out',
            'message' => 'The download did not finish in time.',
            'fix' => 'Try again; a download that was interrupted continues where it stopped.',
        ],
        'url_http_error' => [
            'title' => 'Download refused',
            'message' => 'The other server answered with an error (:status).',
            'fix' => 'Check the link, or ask for a new one.',
        ],
        'upload_too_large' => [
            'title' => 'File too big for the browser',
            'message' => 'This file is too big for the browser upload.',
            'fix' => 'Put it in storage/app/import with FTP or SFTP, use "From a link", or use the command line.',
        ],
        'share_expired' => [
            'title' => 'Link expired',
            'message' => 'This link has expired or was already used.',
            'fix' => 'Ask for a new link from the site the file came from.',
        ],

        // The new site's address
        'site_url_invalid' => [
            'title' => 'Address not valid',
            'message' => 'The site address must start with http:// or https:// and contain only the host name.',
            'fix' => 'Enter an address like https://shop.example.com.',
        ],
        'site_url_https_unavailable' => [
            'title' => 'https is not available yet',
            'message' => 'You opened this page over http, so an https address would lock you out.',
            'fix' => 'Open this page over https, or use an http:// address until the certificate is installed.',
        ],

    ],

    // The data groups a person chooses between. Plain words: nobody is asked to think in tables.
    'group' => [
        'core' => [
            'label' => 'Site basics',
            'description' => 'Settings, languages and translations, admin accounts and their roles. Always included.',
        ],
        'catalog' => [
            'label' => 'Products',
            'description' => 'Products, brands, car models, conditions, reviews and stock history.',
        ],
        'catalog_files' => [
            'label' => 'Product pictures',
            'description' => 'The picture files of your products.',
        ],
        'media' => [
            'label' => 'Media library',
            'description' => 'Images and files uploaded to the media library.',
        ],
        'content' => [
            'label' => 'Pages and blog',
            'description' => 'Pages, sections, menus, blog posts, categories, redirects, FAQs and testimonials.',
        ],
        'customers' => [
            'label' => 'Customers',
            'description' => 'Customer accounts and addresses, contact messages and part inquiries.',
        ],
        'sales' => [
            'label' => 'Orders and payments',
            'description' => 'Orders, payments, refund requests and coupons.',
        ],
        'sales_files' => [
            'label' => 'Payment proofs and refund pictures',
            'description' => 'Files customers uploaded with their orders and refund requests.',
        ],
        'invoicing' => [
            'label' => 'Invoices',
            'description' => 'Custom invoices, invoice clients and bank accounts.',
        ],
        'shipping_tax' => [
            'label' => 'Shipping and tax',
            'description' => 'Shipping zones, methods and carriers, and tax rates.',
        ],
        'marketing' => [
            'label' => 'Newsletter',
            'description' => 'Newsletter subscribers and campaigns.',
        ],
        'admin_prefs' => [
            'label' => 'Admin preferences',
            'description' => 'Saved filters and notification topics of the admin panel.',
        ],
        'carts' => [
            'label' => 'Shopping carts',
            'description' => 'Open and abandoned carts. Off by default: they are temporary.',
        ],
        'logs' => [
            'label' => 'Logs and statistics',
            'description' => 'Activity, email, login and search logs and monitoring snapshots. Off by default: they can be very large.',
        ],
        'system' => [
            'label' => 'System data',
            'description' => 'Backups, updates, sessions and queue data. Never moved: it belongs to the server, not to the shop.',
        ],
    ],

    // Warnings about references no foreign key can see. :count is how many records carry the reference.
    'logical' => [
        'site_logo' => 'The site logo is stored in the media library, which is not included. The new site will show no logo until you upload it again.',
        'site_favicon' => 'The site icon (favicon) is stored in the media library, which is not included. The new site will have no icon until you upload it again.',
        'product_pictures' => ':approximate:count product pictures point to files that are not included. They will show as missing on the new site.',
        'refund_pictures' => ':approximate:count refund requests have pictures from the customer that are not included. The requests will arrive without their pictures.',
        'payment_proofs' => ':approximate:count payments have an uploaded proof of payment that is not included. The proofs will be missing on the new site.',
    ],

    'preset' => [
        'full_live' => [
            'label' => 'Full live copy',
            'description' => 'Everything except logs and carts. Use this to move or launch the real site.',
        ],
        'staging' => [
            'label' => 'Staging copy, personal data scrambled',
            'description' => 'Like the full copy, but customer details are replaced and no secrets travel. Invoices and payment proofs are left out unless you add them.',
        ],
        'new_store_same_catalog' => [
            'label' => 'New store, same catalog',
            'description' => 'Catalog, media, pages, shipping and tax and the admin setup, without customers or orders. Numbering starts again.',
        ],
        'catalog_only' => [
            'label' => 'Catalog only',
            'description' => 'Site basics, products, product pictures and the media library. Numbering starts again.',
        ],
    ],

];
