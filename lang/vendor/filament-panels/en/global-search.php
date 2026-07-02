<?php

// Overrides only the global-search placeholder so admins can see, at a glance,
// what the topbar multi-search covers. Laravel merges this on top of the
// package's own global-search translations (array_replace_recursive), so the
// other keys keep their defaults.

return [
    'field' => [
        'placeholder' => 'Search orders, products (OEM), customers, manufacturers…',
    ],
];
