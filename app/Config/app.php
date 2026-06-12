<?php
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
          ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

return [
    'name'         => 'FamilyCal',
    'url'          => "{$scheme}://{$host}",
    'timezone'     => 'America/Argentina/Buenos_Aires',
    'debug'        => false,
    'session_name' => 'familycal_sess',
    'locale'       => 'es_AR',
];
