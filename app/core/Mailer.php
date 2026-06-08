<?php

namespace App\Core;

use App\Models\SettingModel;

class Mailer
{
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private bool   $tls;

    public function __construct()
    {
        $cfg = (new SettingModel())->all();
        $this->host      = $cfg['smtp_host']      ?? '';
        $this->port      = (int) ($cfg['smtp_port']      ?? 465);
        $this->username  = $cfg['smtp_username']  ?? '';
        $this->password  = $cfg['smtp_password']  ?? '';
        $this->fromEmail = $cfg['smtp_from']      ?? $this->username;
        $this->fromName  = $cfg['smtp_from_name'] ?? 'ClayBBS';
        $this->tls       = (($cfg['smtp_encrypt'] ?? 'ssl') === 'tls');
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        if ($this->host === '' || $this->username === '') {
            return false;
        }

        $boundary = md5(uniqid((string)time(), true));
        $headers  = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . $this->encodeName($this->fromName) . ' <' . $this->fromEmail . '>',
            'To: ' . $this->encodeName($toName) . ' <' . $toEmail . '>',
            'Subject: ' . $this->encodeSubject($subject),
        ]);

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . strip_tags($htmlBody) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $htmlBody . "\r\n"
            . "--{$boundary}--";

        return $this->smtpSend($toEmail, $subject, $headers, $body);
    }

    private function smtpSend(string $to, string $subject, string $headers, string $body): bool
    {
        $encryption = $this->tls ? '' : 'ssl://';
        $sock = @fsockopen($encryption . $this->host, $this->port, $errno, $errstr, 10);
        if (!$sock) return false;

        try {
            $this->expect($sock, 220);
            $this->cmd($sock, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            if ($this->tls) {
                $this->cmd($sock, 'STARTTLS');
                stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->cmd($sock, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            }
            $this->cmd($sock, 'AUTH LOGIN');
            $this->cmd($sock, base64_encode($this->username));
            $this->cmd($sock, base64_encode($this->password));
            $this->cmd($sock, 'MAIL FROM:<' . $this->fromEmail . '>');
            $this->cmd($sock, 'RCPT TO:<' . $to . '>');
            $this->cmd($sock, 'DATA');
            fwrite($sock, $headers . "\r\n\r\n" . $body . "\r\n.");
            fwrite($sock, "\r\n");
            $this->expect($sock, 250);
            $this->cmd($sock, 'QUIT');
        } catch (\Throwable) {
            fclose($sock);
            return false;
        }

        fclose($sock);
        return true;
    }

    private function readResponse($sock): string
    {
        $out = '';
        while ($line = fgets($sock, 512)) {
            $out .= $line;
            
            if (strlen($line) >= 4 && $line[3] === ' ') break;
            if (strlen($line) < 4) break;
        }
        return $out;
    }

    private function cmd($sock, string $cmd): string
    {
        fwrite($sock, $cmd . "\r\n");
        return $this->readResponse($sock);
    }

    private function expect($sock, int $code): void
    {
        $line = $this->readResponse($sock);
        if ((int) $line !== $code) {
            throw new \RuntimeException('SMTP error: ' . $line);
        }
    }

    private function encodeName(string $name): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?=';
    }

    private function encodeSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }
}
