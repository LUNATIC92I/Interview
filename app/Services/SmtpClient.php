<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Minimal SMTP client (AUTH LOGIN, STARTTLS or implicit TLS) to avoid an external dependency.
 */
final class SmtpClient
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeout = 15,
    ) {
    }

    public function send(string $from, string $fromName, string $to, string $subject, string $body): bool
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $socket = stream_socket_client($remote, $errno, $errstr, $this->timeout);
        if ($socket === false) {
            throw new \RuntimeException("SMTP connection failed: $errstr");
        }
        $this->socket = $socket;
        stream_set_timeout($socket, $this->timeout);
        $this->expect(220);
        $this->command('EHLO localhost', 250);
        if ($this->encryption === 'tls') {
            $this->command('STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS negotiation failed');
            }
            $this->command('EHLO localhost', 250);
        }
        if ($this->username !== '') {
            $this->command('AUTH LOGIN', 334);
            $this->command(base64_encode($this->username), 334);
            $this->command(base64_encode($this->password), 235);
        }
        $this->command("MAIL FROM:<$from>", 250);
        $this->command("RCPT TO:<$to>", [250, 251]);
        $this->command('DATA', 354);
        $headers = [
            'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <$from>",
            "To: <$to>",
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n.";
        $this->command($data, 250);
        $this->command('QUIT', 221);
        fclose($socket);
        return true;
    }

    private function command(string $line, int|array $expected): void
    {
        fwrite($this->socket, $line . "\r\n");
        $this->expect($expected);
    }

    private function expect(int|array $expected): void
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, (array) $expected, true)) {
            throw new \RuntimeException('Unexpected SMTP response: ' . trim($response));
        }
    }
}
