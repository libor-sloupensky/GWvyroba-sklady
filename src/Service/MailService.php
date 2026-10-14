<?php
declare(strict_types=1);

namespace App\Service;

use App\Support\DB;
use PDO;

/**
 * Systémový e-mail (upozornění na chyby importu apod.) přes SMTP – bez Composeru a knihoven.
 *
 * Údaje jsou v nastaveni_global (smtp_*), heslo šifrované přes CryptoService.
 * Port 465 = SSL od začátku, 587 = STARTTLS. Ověřeno na smtp.cesky-hosting.cz (AUTH LOGIN).
 */
final class MailService
{
    public const DEFAULTS = [
        'smtp_host' => 'smtp.cesky-hosting.cz',
        'smtp_port' => 465,
        'smtp_user' => 'doklady@tuptudu.cz',
    ];

    private const TIMEOUT = 15;

    /** Doplní sloupce smtp_* do nastaveni_global (lazy migrace jako u StockService). */
    public static function ensureColumns(PDO $pdo): void
    {
        $cols = [
            'smtp_host'      => "VARCHAR(255) COLLATE utf8mb4_czech_ci NULL",
            'smtp_port'      => "INT NULL",
            'smtp_user'      => "VARCHAR(255) COLLATE utf8mb4_czech_ci NULL",
            'smtp_heslo_enc' => "TEXT COLLATE utf8mb4_czech_ci NULL",
        ];
        $st = $pdo->prepare('SHOW COLUMNS FROM `nastaveni_global` LIKE ?');
        foreach ($cols as $col => $def) {
            $st->execute([$col]);
            if (!$st->fetch(PDO::FETCH_ASSOC)) {
                $pdo->exec("ALTER TABLE `nastaveni_global` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    /**
     * Aktuální SMTP nastavení (bez dešifrovaného hesla).
     * @return array{smtp_host:string,smtp_port:int,smtp_user:string,has_password:bool}
     */
    public static function settings(): array
    {
        $pdo = DB::pdo();
        self::ensureColumns($pdo);
        $row = $pdo->query('SELECT smtp_host, smtp_port, smtp_user, smtp_heslo_enc FROM nastaveni_global WHERE id=1')->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'smtp_host' => (string)(($row['smtp_host'] ?? '') !== '' ? $row['smtp_host'] : self::DEFAULTS['smtp_host']),
            'smtp_port' => (int)(($row['smtp_port'] ?? 0) ?: self::DEFAULTS['smtp_port']),
            'smtp_user' => (string)(($row['smtp_user'] ?? '') !== '' ? $row['smtp_user'] : self::DEFAULTS['smtp_user']),
            'has_password' => !empty($row['smtp_heslo_enc']),
        ];
    }

    /** Uloží SMTP nastavení. Prázdné heslo = ponechat stávající. */
    public static function saveSettings(string $host, int $port, string $user, string $password): void
    {
        $pdo = DB::pdo();
        self::ensureColumns($pdo);
        $pdo->prepare('UPDATE nastaveni_global SET smtp_host=?, smtp_port=?, smtp_user=? WHERE id=1')->execute([$host, $port, $user]);
        if ($password !== '') {
            $pdo->prepare('UPDATE nastaveni_global SET smtp_heslo_enc=? WHERE id=1')->execute([CryptoService::encrypt($password)]);
        }
    }

    /** @return string[] e-maily aktivních superadminů */
    public static function superadminEmails(): array
    {
        $rows = DB::pdo()->query("SELECT email FROM users WHERE role='superadmin' AND active=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map('strval', $rows), static fn(string $e): bool => filter_var($e, FILTER_VALIDATE_EMAIL) !== false));
    }

    /** Pošle e-mail všem superadminům. Vyhazuje výjimku při chybě (volající rozhodne, co s ní). */
    public static function sendToSuperadmins(string $subject, string $body): void
    {
        $to = self::superadminEmails();
        if (!$to) {
            throw new \RuntimeException('Není žádný aktivní superadmin s platným e-mailem.');
        }
        self::send($to, $subject, $body);
    }

    /**
     * Odešle prostý textový e-mail (UTF-8).
     * @param string[] $to
     */
    public static function send(array $to, string $subject, string $body): void
    {
        $pdo = DB::pdo();
        self::ensureColumns($pdo);
        $row = $pdo->query('SELECT smtp_heslo_enc FROM nastaveni_global WHERE id=1')->fetch(PDO::FETCH_ASSOC) ?: [];
        if (empty($row['smtp_heslo_enc'])) {
            throw new \RuntimeException('Není nastavené heslo k systémovému e-mailu (Nastavení → Systémový e-mail).');
        }
        $cfg = self::settings();
        $password = CryptoService::decrypt((string)$row['smtp_heslo_enc']);
        $from = $cfg['smtp_user'];

        $port = $cfg['smtp_port'];
        $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $cfg['smtp_host'] . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, self::TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException("SMTP připojení k {$remote} selhalo: {$errstr}");
        }
        stream_set_timeout($fp, self::TIMEOUT);
        try {
            self::expect($fp, 220);
            self::cmd($fp, 'EHLO gworm.wormup.com', 250);
            if ($port !== 465) {
                self::cmd($fp, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('SMTP: STARTTLS se nepodařilo.');
                }
                self::cmd($fp, 'EHLO gworm.wormup.com', 250);
            }
            self::cmd($fp, 'AUTH LOGIN', 334);
            self::cmd($fp, base64_encode($cfg['smtp_user']), 334);
            self::cmd($fp, base64_encode($password), 235, 'přihlášení odmítnuto – zkontrolujte jméno a heslo');
            self::cmd($fp, 'MAIL FROM:<' . $from . '>', 250);
            foreach ($to as $rcpt) {
                self::cmd($fp, 'RCPT TO:<' . $rcpt . '>', [250, 251]);
            }
            self::cmd($fp, 'DATA', 354);

            $headers = [
                'Date: ' . date('r'),
                'From: ' . self::encodeHeader('Gworm') . ' <' . $from . '>',
                'To: ' . implode(', ', $to),
                'Subject: ' . self::encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@gworm.wormup.com>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            $data = implode("\r\n", $headers) . "\r\n\r\n"
                . rtrim(chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n"))
                . "\r\n.";
            self::cmd($fp, $data, 250);
            fwrite($fp, "QUIT\r\n");
        } finally {
            fclose($fp);
        }
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    /** @param int|int[] $expected */
    private static function cmd($fp, string $line, $expected, string $hint = ''): string
    {
        fwrite($fp, $line . "\r\n");
        return self::expect($fp, $expected, $hint);
    }

    /** @param int|int[] $expected */
    private static function expect($fp, $expected, string $hint = ''): string
    {
        $resp = '';
        while (($l = fgets($fp, 1024)) !== false) {
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, (array)$expected, true)) {
            $msg = $resp === '' ? 'server neodpověděl (timeout)' : trim($resp);
            throw new \RuntimeException('SMTP chyba: ' . $msg . ($hint !== '' ? " ({$hint})" : ''));
        }
        return $resp;
    }
}
