<?php
namespace App\Services;

/**
 * Web Push (RFC 8030, RFC 8291, RFC 8188) — pure PHP, no external libraries.
 * Requires PHP 8.1+ (openssl_pkey_derive for ECDH).
 */
class WebPushService {
    private mixed  $vapidPrivateKey;
    private string $vapidPublicB64;
    private string $vapidSubject;

    public function __construct() {
        $cfg = require BASE_PATH . '/app/Config/push.php';

        if (empty($cfg['enabled']) || empty($cfg['vapid_private_pem'])) {
            throw new \RuntimeException('Push notifications not configured.');
        }

        $pem = str_replace('\n', "\n", $cfg['vapid_private_pem']);
        $this->vapidPrivateKey = openssl_pkey_get_private($pem);
        if (!$this->vapidPrivateKey) {
            throw new \RuntimeException('Invalid VAPID private key: ' . openssl_error_string());
        }
        $this->vapidPublicB64 = $cfg['vapid_public_b64'];
        $this->vapidSubject   = $cfg['vapid_subject'];
    }

    public function sendToSubscription(array $sub, string $title, string $body, string $url = ''): bool {
        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'icon'  => '/assets/images/icon-192.png',
        ], JSON_UNESCAPED_UNICODE);

        try {
            return $this->dispatch($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload);
        } catch (\Throwable) {
            return false;
        }
    }

    private function dispatch(string $endpoint, string $p256dhB64, string $authB64, string $payload): bool {
        $p256dh     = $this->b64decode($p256dhB64);
        $authSecret = $this->b64decode($authB64);

        // --- Ephemeral EC P-256 key pair ---
        $ephKey     = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ephDetails = openssl_pkey_get_details($ephKey);
        $senderPub  = "\x04" . str_pad($ephDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                             . str_pad($ephDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        // --- Import recipient public key ---
        $recipientPub = $this->rawP256ToPem($p256dh);
        $recipientKey = openssl_pkey_get_public($recipientPub);

        // --- ECDH shared secret (PHP 8.1+) ---
        $sharedSecret = openssl_pkey_derive($recipientKey, $ephKey);
        if ($sharedSecret === false) {
            throw new \RuntimeException('ECDH failed: ' . openssl_error_string());
        }

        // --- RFC 8291 key derivation ---
        // PRK_key = HKDF-SHA256(salt=authSecret, ikm=sharedSecret,
        //           info="WebPush: info\x00" || ua_pub || as_pub, len=32)
        $info      = "WebPush: info\x00" . $p256dh . $senderPub;
        $prkKey    = $this->hkdf($authSecret, $sharedSecret, $info, 32);

        // --- RFC 8188 aes128gcm header & encryption ---
        $salt = random_bytes(16);

        $cekInfo   = "Content-Encoding: aes128gcm\x00";
        $nonceInfo = "Content-Encoding: nonce\x00";
        $cek       = $this->hkdf($salt, $prkKey, $cekInfo, 16);
        $nonce     = $this->hkdf($salt, $prkKey, $nonceInfo, 12);

        // Pad + encrypt: plaintext || \x02 (delimiter byte)
        $plaintext  = $payload . "\x02";
        $tag        = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        $encrypted  = $ciphertext . $tag;

        // aes128gcm header (RFC 8188 §2.1):
        // salt(16) || rs(4,BE) || idlen(1) || keyid(idlen)
        $rs         = 4096;
        $keyidLen   = strlen($senderPub); // 65 bytes
        $header     = $salt . pack('N', $rs) . chr($keyidLen) . $senderPub;
        $body       = $header . $encrypted;

        // --- VAPID JWT ---
        $parsed   = parse_url($endpoint);
        $audience = $parsed['scheme'] . '://' . $parsed['host'];
        $jwt      = $this->buildJwt($audience);

        // --- HTTP push request ---
        $context = stream_context_create(['http' => [
            'method'         => 'POST',
            'header'         => "Authorization: vapid t={$jwt},k={$this->vapidPublicB64}\r\n"
                              . "Content-Type: application/octet-stream\r\n"
                              . "Content-Encoding: aes128gcm\r\n"
                              . "TTL: 86400\r\n"
                              . "Content-Length: " . strlen($body),
            'content'        => $body,
            'ignore_errors'  => true,
            'timeout'        => 10,
        ]]);

        @file_get_contents($endpoint, false, $context);
        $status = 0;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/[\d.]+\s+(\d+)/', $http_response_header[0], $m);
            $status = (int)($m[1] ?? 0);
        }
        return $status >= 200 && $status < 300;
    }

    private function buildJwt(string $audience): string {
        $header  = $this->b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = $this->b64url(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $this->vapidSubject,
        ]));
        $signing = "$header.$payload";

        openssl_sign($signing, $derSig, $this->vapidPrivateKey, OPENSSL_ALGO_SHA256);
        return $signing . '.' . $this->b64url($this->derToRaw($derSig));
    }

    private function derToRaw(string $der): string {
        // Parse ASN.1 DER SEQUENCE { INTEGER(R), INTEGER(S) }
        $pos  = 2;
        $rLen = ord($der[$pos + 1]);
        $r    = substr($der, $pos + 2, $rLen);
        $pos += 2 + $rLen;
        $sLen = ord($der[$pos + 1]);
        $s    = substr($der, $pos + 2, $sLen);
        // Strip leading 0x00 padding, re-pad to 32 bytes
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
        return $r . $s;
    }

    private function hkdf(string $salt, string $ikm, string $info, int $len): string {
        $prk    = hash_hmac('sha256', $ikm, $salt, true);
        $output = '';
        $t      = '';
        $i      = 0;
        while (strlen($output) < $len) {
            $t       = hash_hmac('sha256', $t . $info . chr(++$i), $prk, true);
            $output .= $t;
        }
        return substr($output, 0, $len);
    }

    private function rawP256ToPem(string $rawPoint): string {
        // SubjectPublicKeyInfo wrapper for P-256 uncompressed point
        $header = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $der    = $header . $rawPoint;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
