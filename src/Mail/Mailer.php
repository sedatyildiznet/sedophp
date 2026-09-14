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

    /** @param string|list<string> $to @param array<string,string> $headers */
    public static function send(
        string|array $to,
        string $subject,
        string $html,
        ?string $text = null,
        array $headers = [],
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

        self::assertHeaderSafe($subject);
        $message = self::buildMessage($recipients, $subject, $html, $text, $headers);
        $driver = strtolower((string) (self::$config['driver'] ?? 'log'));

        return match ($driver) {
            'smtp' => self::sendSmtp($recipients, $message),
            'mail' => self::sendNative($recipients, $subject, $html, $text, $headers),
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

    /** @param list<string> $recipients @param array<string,string> $headers */
    private static function sendNative(
        array $recipients,
        string $subject,
        string $html,
        ?string $text,
        array $headers,
    ): bool {
        [$body, $headerLines] = self::bodyAndHeaders($html, $text, $headers);
        $headerLines[] = self::fromHeader();

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

    /** @param list<string> $recipients @param array<string,string> $headers */
    private static function buildMessage(
        array $recipients,
        string $subject,
        string $html,
        ?string $text,
        array $headers,
    ): string {
        [$body, $headerLines] = self::bodyAndHeaders($html, $text, $headers);
        $headerLines[] = self::fromHeader();
        $headerLines[] = 'To: ' . implode(', ', $recipients);
        $headerLines[] = 'Subject: ' . $subject;
        $headerLines[] = 'Date: ' . date(DATE_RFC2822);
        $headerLines[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::hostname() . '>';

        return implode("\r\n", $headerLines) . "\r\n\r\n" . $body;
    }

    /** @param array<string,string> $headers @return array{0:string,1:list<string>} */
    private static function bodyAndHeaders(string $html, ?string $text, array $headers): array
    {
        $headerLines = ['MIME-Version: 1.0'];

        foreach ($headers as $name => $value) {
            self::assertHeaderSafe($name);
            self::assertHeaderSafe($value);
            $headerLines[] = $name . ': ' . $value;
        }

        if ($text === null) {
            $headerLines[] = 'Content-Type: text/html; charset=UTF-8';
            $headerLines[] = 'Content-Transfer-Encoding: 8bit';
            return [$html, $headerLines];
        }

        $boundary = 'sedophp_' . bin2hex(random_bytes(12));
        $headerLines[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $text . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $html . "\r\n"
            . '--' . $boundary . "--\r\n";

        return [$body, $headerLines];
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
