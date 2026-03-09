<?php

namespace Core;

class Mailer {
    private string $driver;
    private string $fromAddress;
    private string $fromName;
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $logPath;
    private int $timeout = 15;

    public function __construct() {
        $this->driver = strtolower(trim((string) (getenv('MAIL_DRIVER') ?: 'mail')));
        if (!in_array($this->driver, ['mail', 'smtp', 'log'], true)) {
            $this->driver = 'mail';
        }

        $this->fromAddress = trim((string) (getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@localhost'));
        if (!filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL)) {
            $this->fromAddress = 'no-reply@localhost';
        }

        $this->fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: (getenv('APP_NAME') ?: 'RECALDE')));
        $this->host = trim((string) (getenv('MAIL_HOST') ?: 'localhost'));
        $this->port = (int) (getenv('MAIL_PORT') !== false ? getenv('MAIL_PORT') : 587);
        if ($this->port <= 0) {
            $this->port = 587;
        }

        $this->username = trim((string) (getenv('MAIL_USERNAME') ?: ''));
        $this->password = (string) (getenv('MAIL_PASSWORD') !== false ? getenv('MAIL_PASSWORD') : '');
        $this->encryption = strtolower(trim((string) (getenv('MAIL_ENCRYPTION') ?: 'tls')));
        if (!in_array($this->encryption, ['tls', 'ssl', 'none'], true)) {
            $this->encryption = 'tls';
        }

        $defaultLogPath = defined('BASE_PATH')
            ? BASE_PATH . '/storage/mail.log'
            : dirname(__DIR__, 2) . '/storage/mail.log';
        $this->logPath = trim((string) (getenv('MAIL_LOG_PATH') ?: $defaultLogPath));
        if (!$this->isAbsolutePath($this->logPath) && defined('BASE_PATH')) {
            $this->logPath = rtrim((string) BASE_PATH, '/') . '/' . ltrim($this->logPath, '/');
        }
    }

    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = $this->sanitizeHeaderValue($subject);
        $textBody = trim((string) ($textBody ?? ''));
        if ($textBody === '') {
            $textBody = $this->buildTextBody($htmlBody);
        }

        $mime = $this->buildMimePayload($subject, $htmlBody, $textBody);

        if ($this->driver === 'log') {
            return $this->logEmail($to, $subject, $htmlBody, $textBody, 'log_driver');
        }

        if ($this->driver === 'smtp') {
            if ($this->sendViaSmtp($to, $subject, $mime['headers'], $mime['body'])) {
                return true;
            }

            return $this->logEmail($to, $subject, $htmlBody, $textBody, 'smtp_failed');
        }

        if ($this->sendViaMail($to, $subject, $mime['headers'], $mime['body'])) {
            return true;
        }

        return $this->logEmail($to, $subject, $htmlBody, $textBody, 'mail_failed');
    }

    private function buildMimePayload(string $subject, string $htmlBody, string $textBody): array {
        $boundary = 'recalde-' . $this->generateBoundarySuffix();
        $fromHeader = $this->formatAddress($this->fromAddress, $this->fromName);
        $subject = $this->sanitizeHeaderValue($subject);

        $headers = [
            "From: {$fromHeader}",
            "Reply-To: {$fromHeader}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "Date: " . date(DATE_RFC2822),
            "X-Mailer: RECALDE Mailer",
        ];

        $bodyParts = [
            "--{$boundary}",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "",
            $textBody,
            "",
            "--{$boundary}",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "",
            $htmlBody,
            "",
            "--{$boundary}--",
            ""
        ];

        return [
            'headers' => $headers,
            'body' => implode("\r\n", $bodyParts),
        ];
    }

    private function sendViaMail(string $to, string $subject, array $headers, string $body): bool {
        if (!$this->isMailTransportAvailable()) {
            error_log('Mailer::sendViaMail => transporte mail()/sendmail no disponible.');
            return false;
        }

        $headersRaw = implode("\r\n", $headers);
        return @mail($to, $this->encodeHeader($subject), $body, $headersRaw);
    }

    private function sendViaSmtp(string $to, string $subject, array $headers, string $body): bool {
        $host = $this->encryption === 'ssl' ? "ssl://{$this->host}" : $this->host;
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
        if (!$socket) {
            error_log("Mailer SMTP connect error ({$errno}): {$errstr}");
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        if (!$this->expectCode($this->readSmtpResponse($socket), [220])) {
            fclose($socket);
            return false;
        }

        $clientHost = $this->sanitizeSmtpHostname((string) ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!$this->smtpCommand($socket, "EHLO {$clientHost}", [250])) {
            fclose($socket);
            return false;
        }

        if ($this->encryption === 'tls') {
            if (!$this->smtpCommand($socket, 'STARTTLS', [220])) {
                fclose($socket);
                return false;
            }

            $cryptoEnabled = @stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($cryptoEnabled !== true) {
                fclose($socket);
                return false;
            }

            if (!$this->smtpCommand($socket, "EHLO {$clientHost}", [250])) {
                fclose($socket);
                return false;
            }
        }

        if ($this->username !== '') {
            if ($this->password === '') {
                error_log('Mailer::sendViaSmtp => MAIL_PASSWORD vacío con autenticación SMTP habilitada.');
                fclose($socket);
                return false;
            }

            if (!$this->smtpCommand($socket, 'AUTH LOGIN', [334])) {
                fclose($socket);
                return false;
            }

            if (!$this->smtpCommand($socket, base64_encode($this->username), [334])) {
                fclose($socket);
                return false;
            }

            if (!$this->smtpCommand($socket, base64_encode($this->password), [235])) {
                fclose($socket);
                return false;
            }
        }

        $mailFrom = $this->extractEmailAddress($this->fromAddress);
        if (!$this->smtpCommand($socket, "MAIL FROM:<{$mailFrom}>", [250])) {
            fclose($socket);
            return false;
        }

        $rcptTo = $this->extractEmailAddress($to);
        if (!$this->smtpCommand($socket, "RCPT TO:<{$rcptTo}>", [250, 251])) {
            fclose($socket);
            return false;
        }

        if (!$this->smtpCommand($socket, 'DATA', [354])) {
            fclose($socket);
            return false;
        }

        $smtpHeaders = array_merge(
            [
                "To: {$to}",
                "Subject: " . $this->encodeHeader($subject),
            ],
            $headers
        );
        $rawMessage = implode("\r\n", $smtpHeaders) . "\r\n\r\n" . $body;
        $safeMessage = $this->dotStuffing($rawMessage);

        fwrite($socket, $safeMessage . "\r\n.\r\n");
        if (!$this->expectCode($this->readSmtpResponse($socket), [250])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'QUIT', [221, 250]);
        fclose($socket);
        return true;
    }

    private function smtpCommand($socket, string $command, array $expectedCodes): bool {
        fwrite($socket, $command . "\r\n");
        $response = $this->readSmtpResponse($socket);
        return $this->expectCode($response, $expectedCodes);
    }

    private function readSmtpResponse($socket): string {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }
        return $response;
    }

    private function expectCode(string $response, array $expectedCodes): bool {
        $code = (int) substr(trim($response), 0, 3);
        return in_array($code, $expectedCodes, true);
    }

    private function dotStuffing(string $message): string {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);
        $normalized = preg_replace('/^\./m', '..', $normalized);
        return str_replace("\n", "\r\n", (string) $normalized);
    }

    private function buildTextBody(string $htmlBody): string {
        $text = html_entity_decode(strip_tags($htmlBody), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', (string) $text);
        $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);
        return trim((string) $text);
    }

    private function formatAddress(string $email, string $name): string {
        $email = $this->extractEmailAddress($email);
        $name = $this->sanitizeHeaderValue($name);
        if ($name === '') {
            return $email;
        }
        return $this->encodeHeader($name) . " <{$email}>";
    }

    private function extractEmailAddress(string $email): string {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'no-reply@localhost';
        }
        return $email;
    }

    private function encodeHeader(string $value): string {
        $clean = $this->sanitizeHeaderValue($value);
        if ($clean === '') {
            return '';
        }

        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($clean, 'UTF-8', 'B', "\r\n");
        }

        return $clean;
    }

    private function sanitizeHeaderValue(string $value): string {
        $value = str_replace(["\r", "\n"], '', $value);
        return trim($value);
    }

    private function sanitizeSmtpHostname(string $hostname): string {
        $hostname = strtolower(trim($hostname));
        $hostname = preg_replace('/[^a-z0-9\.\-]/', '', $hostname);
        return $hostname !== '' ? $hostname : 'localhost';
    }

    private function isMailTransportAvailable(): bool {
        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            return true;
        }

        $sendmailPathRaw = trim((string) ini_get('sendmail_path'));
        if ($sendmailPathRaw === '') {
            return false;
        }

        $binary = $this->extractBinaryFromCommand($sendmailPathRaw);
        if ($binary === '') {
            return false;
        }

        if ($this->isAbsolutePath($binary)) {
            return file_exists($binary) && is_executable($binary);
        }

        $resolved = (string) shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null');
        return trim($resolved) !== '';
    }

    private function extractBinaryFromCommand(string $command): string {
        $command = trim($command);
        if ($command === '') {
            return '';
        }

        if (str_starts_with($command, '"')) {
            $end = strpos($command, '"', 1);
            if ($end !== false) {
                return substr($command, 1, $end - 1);
            }
        }

        if (str_starts_with($command, "'")) {
            $end = strpos($command, "'", 1);
            if ($end !== false) {
                return substr($command, 1, $end - 1);
            }
        }

        $parts = preg_split('/\s+/', $command);
        return trim((string) ($parts[0] ?? ''));
    }

    private function isAbsolutePath(string $path): bool {
        return str_starts_with($path, '/')
            || (bool) preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path);
    }

    private function generateBoundarySuffix(): string {
        try {
            return bin2hex(random_bytes(10));
        } catch (\Throwable $e) {
            return hash('sha256', uniqid('boundary', true) . microtime(true));
        }
    }

    private function logEmail(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $reason = 'fallback'
    ): bool {
        $entry = [];
        $entry[] = "==== " . date('Y-m-d H:i:s') . " ====";
        $entry[] = "Reason: {$reason}";
        $entry[] = "To: {$to}";
        $entry[] = "Subject: {$subject}";
        $entry[] = "Text:";
        $entry[] = $textBody;
        $entry[] = "HTML:";
        $entry[] = $htmlBody;
        $entry[] = "";

        $content = implode(PHP_EOL, $entry);
        $candidatePaths = [$this->logPath];
        $tmpFallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'recalde-mail.log';
        if (!in_array($tmpFallback, $candidatePaths, true)) {
            $candidatePaths[] = $tmpFallback;
        }

        foreach ($candidatePaths as $path) {
            if ($this->appendToLogPath($path, $content)) {
                return true;
            }
        }

        error_log('Mailer::logEmail => no se pudo escribir en ninguna ruta de log.');
        return false;
    }

    private function appendToLogPath(string $path, string $content): bool {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        if (!is_writable($directory) && !(file_exists($path) && is_writable($path))) {
            return false;
        }

        return @file_put_contents($path, $content, FILE_APPEND | LOCK_EX) !== false;
    }
}
