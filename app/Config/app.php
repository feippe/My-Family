<?php
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
          ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

return [
    'name'         => 'Familia',
    'url'          => "{$scheme}://{$host}",
    'timezone'     => 'America/Montevideo',
    'debug'        => false,
    'session_name' => 'familycal_sess',
    'locale'       => 'es_AR',
];
