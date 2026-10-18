<?php

// Store overrides in configuration so they work with php artisan config:cache.
return [
    'mysqldump_binary' => env('CERTIFY_MYSQLDUMP_BINARY', ''),
    'mysql_binary' => env('CERTIFY_MYSQL_BINARY', ''),
];
