#!/usr/bin/env php
<?php
/**
 * VAPID key generator for FamilyCal Web Push.
 * Run from project root:  php tools/generate-vapid-keys.php
 *
 * Requires: PHP 8.1+, OpenSSL with prime256v1 (P-256) support.
 */

if (PHP_MAJOR_VERSION < 8 || (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION < 1)) {
    exit("PHP 8.1+ required.\n");
}

$key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
if (!$key) {
    exit("Failed to generate EC key: " . openssl_error_string() . "\n");
}

$details = openssl_pkey_get_details($key);

// Export private key as PEM PKCS#8
openssl_pkey_export($key, $privatePem);

// Build uncompressed public key point: 0x04 || x || y
$x   = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
$y   = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
$pub = "\x04" . $x . $y;

function b64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$publicB64 = b64url($pub);

echo "=== VAPID Keys Generated ===\n\n";
echo "Copy the values below into app/Config/push.php:\n\n";
echo "'vapid_public_b64'  => '{$publicB64}',\n\n";
echo "'vapid_private_pem' => '" . str_replace(["\n","'"], ['\\n',"\\'"], trim($privatePem)) . "',\n\n";
echo "Then set 'enabled' => true and update 'vapid_subject' with your email.\n";
