<?php
namespace App\Services;

class MailService {
    private array $cfg;

    public function __construct() {
        $this->cfg = require BASE_PATH . '/app/Config/mail.php';
    }

    public function send(string $to, string $subject, string $htmlBody, string $plainBody = ''): bool {
        if (empty($this->cfg['enabled'])) return false;

        if (!$plainBody) {
            $plainBody = strip_tags($htmlBody);
        }

        if ($this->cfg['driver'] === 'smtp') {
            return $this->sendSmtp($to, $subject, $htmlBody, $plainBody);
        }

        return $this->sendMail($to, $subject, $htmlBody, $plainBody);
    }

    private function sendMail(string $to, string $subject, string $html, string $plain): bool {
        $from    = $this->cfg['from_email'];
        $name    = $this->cfg['from_name'];
        $boundary = md5(uniqid());

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($name) . "?= <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "X-Mailer: FamilyCal/1.0\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$plain}\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n";
        $body .= "--{$boundary}--";

        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }

    private function sendSmtp(string $to, string $subject, string $html, string $plain): bool {
        $c   = $this->cfg['smtp'];
        $err = '';
        $ctx = null;

        if ($c['secure'] === 'ssl') {
            $host = "ssl://{$c['host']}";
            $ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        } else {
            $host = $c['host'];
        }

        $sock = $ctx ? @stream_socket_client("tcp://{$c['host']}:{$c['port']}", $en, $err, 10, STREAM_CLIENT_CONNECT, $ctx)
                     : @fsockopen($host, $c['port'], $en, $err, 10);

        if (!$sock) return false;

        $read = fn() => fgets($sock, 512);
        $send = fn(string $cmd) => fputs($sock, $cmd . "\r\n");

        $read(); // greeting
        $send("EHLO " . gethostname());
        $res = '';
        while ($line = fgets($sock, 512)) {
            $res .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }

        if ($c['secure'] === 'tls') {
            $send("STARTTLS");
            $read();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . gethostname());
            while ($line = fgets($sock, 512)) { if (substr($line, 3, 1) === ' ') break; }
        }

        if ($c['user']) {
            $send("AUTH LOGIN");
            $read();
            $send(base64_encode($c['user']));
            $read();
            $send(base64_encode($c['password']));
            $read();
        }

        $from = $this->cfg['from_email'];
        $send("MAIL FROM:<{$from}>");   $read();
        $send("RCPT TO:<{$to}>");       $read();
        $send("DATA");                  $read();

        $boundary = md5(uniqid());
        $headers  = "From: =?UTF-8?B?" . base64_encode($this->cfg['from_name']) . "?= <{$from}>\r\n"
                  . "To: {$to}\r\n"
                  . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
                  . "MIME-Version: 1.0\r\n"
                  . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $body  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$plain}\r\n";
        $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n";
        $body .= "--{$boundary}--";

        $send($headers . "\r\n" . $body . "\r\n.");
        $response = $read();
        $send("QUIT");
        fclose($sock);

        return str_starts_with($response, '2');
    }

    public function buildEventNotificationEmail(string $recipientName, string $eventTitle, string $eventStart, string $message, string $actionUrl): string {
        $appUrl = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/');
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{margin:0;padding:0;background:#f4f4f8;font-family:'Helvetica Neue',Arial,sans-serif}
  .wrap{max-width:520px;margin:40px auto;background:#1a1a35;border-radius:16px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.3)}
  .top{background:linear-gradient(135deg,#7c3aed,#9d5ff3);padding:32px 32px 24px;text-align:center}
  .logo{color:#fff;font-size:2rem}
  .top h1{color:#fff;margin:8px 0 4px;font-size:1.1rem;font-weight:700}
  .body{padding:28px 32px}
  .hi{color:#9898b8;font-size:.9rem;margin-bottom:16px}
  .event-card{background:#252545;border-radius:10px;padding:16px 20px;margin:16px 0}
  .ev-title{color:#f0f0ff;font-size:1rem;font-weight:700;margin-bottom:4px}
  .ev-date{color:#9898b8;font-size:.85rem}
  .message{color:#c0c0e0;font-size:.9rem;line-height:1.6;margin:16px 0}
  .btn{display:inline-block;background:linear-gradient(135deg,#7c3aed,#9d5ff3);color:#fff!important;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:.9rem;margin-top:8px}
  .footer{padding:16px 32px;border-top:1px solid #252545;color:#606080;font-size:.75rem;text-align:center}
</style></head>
<body>
<div class="wrap">
  <div class="top">
    <div class="logo">🗓</div>
    <h1>FamilyCal</h1>
  </div>
  <div class="body">
    <p class="hi">Hola, {$recipientName}</p>
    <p class="message">{$message}</p>
    <div class="event-card">
      <div class="ev-title">{$eventTitle}</div>
      <div class="ev-date">{$eventStart}</div>
    </div>
    <a href="{$actionUrl}" class="btn">Ver en el calendario</a>
  </div>
  <div class="footer">FamilyCal &middot; <a href="{$appUrl}" style="color:#7c3aed">{$appUrl}</a></div>
</div>
</body>
</html>
HTML;
    }
}
