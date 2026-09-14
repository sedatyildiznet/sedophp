<?php

declare(strict_types=1);

namespace SedoPHP\Mail;

use RuntimeException;
use SedoPHP\Core\Logger;

final class Mailer
{
    /** @var array<string, mixed> */
    private static array $config = [];

    /** @param array<string, mixed> $config */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    /** @param string|list<string> $to @param array<string,string> $headers @param array<string,mixed> $options */
    public static function send(
        string|array $to,
        string $subject,
        string $html,
        ?string $text = null,
        array $headers = [],
        array $options = [],
    ): bool {
        $recipients = is_array($to) ? array_values($to) : [$to];
        $recipients = array_values(array_filter(array_map('trim', $recipients)));

        if ($recipients === []) {
            throw new RuntimeException('Mail requires at least one recipient.');
        }

        foreach ($recipients as $recipient) {
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException("Invalid recipient email: {$recipient}");
            }
        }

        $cc = self::recipients($options['cc'] ?? []);
        $bcc = self::recipients($options['bcc'] ?? []);
        $attachments = (array) ($options['attachments'] ?? []);

        self::assertHeaderSafe($subject);
        $message = self::buildMessage($recipients, $subject, $html, $text, $headers, $cc, $attachments);
        $driver = strtolower((string) (self::$config['driver'] ?? 'log'));

        return match ($driver) {
            'smtp' => self::sendSmtp(array_values(array_unique(array_merge($recipients, $cc, $bcc))), $message),
            'mail' => self::sendNative($recipients, $subject, $html, $text, $headers, $cc, $bcc, $attachments),
            'log' => self::sendLog($recipients, $subject),
            default => throw new RuntimeException("Unsupported mail driver: {$driver}"),
        };
    }

    /** @param list<string> $recipients */
    private static function sendLog(array $recipients, string $subject): bool
    {
        Logger::info('Mail captured by log driver.', [
            'to' => $recipients,
            'subject' => $subject,
        ]);
        return true;
    }

    /** @param list<string> $recipients @param array<string,string> $headers @param list<string> $cc @param list<string> $bcc @param array<int,mixed> $attachments */
    private static function sendNative(
        array $recipients,
        string $subject,
        string $html,
        ?string $text,
        array $headers,
        array $cc,
        array $bcc,
        array $attachments,
    ): bool {
        [$body, $headerLines] = self::bodyAndHeaders($html, $text, $headers, $attachments);
        $headerLines[] = self::fromHeader();
        if ($cc !== []) { $headerLines[] = 'Cc: ' . implode(', ', $cc); }
        if ($bcc !== []) { $headerLines[] = 'Bcc: ' . implode(', ', $bcc); }

        return mail(
            implode(', ', $recipients),
            $subject,
            $body,
            implode("\r\n", $headerLines)
        );
    }

    /** @param list<string> $recipients */
    private static function sendSmtp(array $recipients, string $message): bool
    {
        $host = (string) (self::$config['host'] ?? '');
        $port = (int) (self::$config['port'] ?? 587);
        $encryption = strtolower((string) (self::$config['encryption'] ?? 'tls'));
        $username = (string) (self::$config['username'] ?? '');
        $password = (string) (self::$config['password'] ?? '');
        $timeout = (float) (self::$config['timeout'] ?? 10);

        if ($host === '') {
            throw new RuntimeException('MAIL_HOST is required for SMTP.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errorNumber, $errorMessage, $timeout);

        if (!is_resource($socket)) {
            throw new RuntimeException("SMTP connection failed: {$errorMessage} ({$errorNumber})");
        }

        stream_set_timeout($socket, (int) ceil($timeout));

        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO ' . self::hostname(), [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Unable to enable SMTP TLS.');
                }
                self::command($socket, 'EHLO ' . self::hostname(), [250]);
            }

            if ($username !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($username), [334]);
                self::command($socket, base64_encode($password), [235]);
            }

            $from = self::fromAddress();
            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            foreach ($recipients as $recipient) {
                self::command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            }

            self::command($socket, 'DATA', [354]);
            $message = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message)) ?? $message;
            fwrite($socket, str_replace("\n", "\r\n", $message) . "\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);

            return true;
        } finally {
            fclose($socket);
        }
    }

    /** @param list<string> $recipients @param array<string,string> $headers @param list<string> $cc @param array<int,mixed> $attachments */
    private static function buildMessage(
        array $recipients,
        string $subject,
        string $html,
        ?string $text,
        array $headers,
        array $cc,
        array $attachments,
    ): string {
        [$body, $headerLines] = self::bodyAndHeaders($html, $text, $headers, $attachments);
        $headerLines[] = self::fromHeader();
        $headerLines[] = 'To: ' . implode(', ', $recipients);
        if ($cc !== []) { $headerLines[] = 'Cc: ' . implode(', ', $cc); }
        $headerLines[] = 'Subject: ' . $subject;
        $headerLines[] = 'Date: ' . date(DATE_RFC2822);
        $headerLines[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::hostname() . '>';

        return implode("\r\n", $headerLines) . "\r\n\r\n" . $body;
    }

    /** @param array<string,string> $headers @param array<int,mixed> $attachments @return array{0:string,1:list<string>} */
    private static function bodyAndHeaders(string $html, ?string $text, array $headers, array $attachments = []): array
    {
        $headerLines = ['MIME-Version: 1.0'];

        foreach ($headers as $name => $value) {
            self::assertHeaderSafe($name);
            self::assertHeaderSafe($value);
            $headerLines[] = $name . ': ' . $value;
        }

        if ($attachments === [] && $text === null) {
            $headerLines[] = 'Content-Type: text/html; charset=UTF-8';
            $headerLines[] = 'Content-Transfer-Encoding: 8bit';
            return [$html, $headerLines];
        }

        $alternativeBoundary = 'sedophp_alt_' . bin2hex(random_bytes(12));
        $alternative = '--' . $alternativeBoundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . ($text ?? strip_tags($html)) . "\r\n"
            . '--' . $alternativeBoundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $html . "\r\n"
            . '--' . $alternativeBoundary . "--\r\n";

        if ($attachments === []) {
            $headerLines[] = 'Content-Type: multipart/alternative; boundary="' . $alternativeBoundary . '"';
            return [$alternative, $headerLines];
        }

        $mixedBoundary = 'sedophp_mix_' . bin2hex(random_bytes(12));
        $headerLines[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';
        $body = '--' . $mixedBoundary . "\r\n"
            . 'Content-Type: multipart/alternative; boundary="' . $alternativeBoundary . "\"\r\n\r\n"
            . $alternative;

        foreach ($attachments as $attachment) {
            $path = is_array($attachment) ? (string) ($attachment['path'] ?? '') : (string) $attachment;
            if (!is_file($path) || !is_readable($path)) {
                throw new RuntimeException('Mail attachment is not readable: ' . $path);
            }
            $name = is_array($attachment) ? (string) ($attachment['name'] ?? basename($path)) : basename($path);
            self::assertHeaderSafe($name);
            $mime = is_array($attachment) ? (string) ($attachment['mime'] ?? '') : '';
            $mime = $mime !== '' ? $mime : ((new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream');
            $body .= "\r\n--{$mixedBoundary}\r\nContent-Type: {$mime}; name=\"" . addcslashes($name, '"\\') . "\"\r\n"
                . "Content-Disposition: attachment; filename=\"" . addcslashes($name, '"\\') . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode((string) file_get_contents($path)), 76, "\r\n");
        }
        $body .= '--' . $mixedBoundary . "--\r\n";

        return [$body, $headerLines];
    }

    /** @return list<string> */
    private static function recipients(mixed $value): array
    {
        $items = is_array($value) ? $value : ($value === '' ? [] : [$value]);
        $items = array_values(array_filter(array_map('trim', array_map('strval', $items))));
        foreach ($items as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('Invalid recipient email: ' . $email);
            }
        }
        return $items;
    }

    private static function fromHeader(): string
    {
        $address = self::fromAddress();
        $name = trim((string) (self::$config['from_name'] ?? ''));

        if ($name === '') {
            return 'From: ' . $address;
        }

        self::assertHeaderSafe($name);
        return 'From: "' . addcslashes($name, '"\\') . '" <' . $address . '>';
    }

    private static function fromAddress(): string
    {
        $address = trim((string) (self::$config['from_address'] ?? 'noreply@localhost'));
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('MAIL_FROM_ADDRESS must be a valid email address.');
        }
        return $address;
    }

    private static function hostname(): string
    {
        return preg_replace('/[^A-Za-z0-9.-]/', '', gethostname() ?: 'localhost') ?: 'localhost';
    }

    private static function assertHeaderSafe(string $value): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException('Mail headers may not contain line breaks.');
        }
    }

    /** @param resource $socket @param list<int> $expected */
    private static function command($socket, string $command, array $expected): void
    {
        fwrite($socket, $command . "\r\n");
        self::expect($socket, $expected);
    }

    /** @param resource $socket @param list<int> $expected */
    private static function expect($socket, array $expected): void
    {
        $response = '';
        $code = 0;

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^(\d{3})([ -])/', $line, $match) === 1) {
                $code = (int) $match[1];
                if ($match[2] === ' ') {
                    break;
                }
            }
        }

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
    }
}
