<?php
/**
 * Copy to config.php and adjust for your environment.
 */
return [
    'db' => [
        'dsn'  => 'mysql:host=127.0.0.1;dbname=qcarta_maps;charset=utf8mb4',
        'user' => 'root',
        'pass' => '',
    ],
    /** Base URL of the Go qcarta-tiles service (no trailing slash) */
    'tile_service_base' => 'http://127.0.0.1:8011',
    /** Same token as QCARTA_CACHE_PURGE_TOKEN on the Go service */
    'cache_purge_token' => '',
];
