<?php
global $wpdb;
return [
    'driver'    => 'mysql',
    'host'      => wp_config( 'DB_HOST' ),
    'database'  => wp_config( 'DB_NAME' ),
    'username'  => wp_config( 'DB_USER' ),
    'password'  => wp_config( 'DB_PASSWORD' ),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => $wpdb->prefix,
];