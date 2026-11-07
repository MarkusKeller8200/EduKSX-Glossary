<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Einmalcodes nach RFC 6238 (TOTP, HMAC-SHA1, 30 Sekunden, 6 Stellen) für den zweiten Faktor (#11).
 * Ohne Zusatzpaket; geprüft mit den Testwerten aus RFC 6238 Anhang B (tests/Unit/TotpTest.php).
 */
final class Totp
{
    public const PERIOD = 30;

    public const DIGITS = 6;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Neuer geheimer Schlüssel (160 Bit) in Base32. */
    public static function secret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** Code für einen Zeitpunkt (Standard: jetzt). */
    public static function code(string $secret, ?int $time = null, int $digits = self::DIGITS): string
    {
        return self::codeAt(self::base32Decode($secret), intdiv($time ?? time(), self::PERIOD), $digits);
    }

    /**
     * Code prüfen, eine Periode vor/nach erlaubt (Uhrabweichung). Gibt den verwendeten Zeitschritt zurück, damit
     * derselbe Code nicht zweimal gilt (Schutz vor Wiederverwendung); null = falsch oder schon verwendet.
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{'.self::DIGITS.'}$/', $code) !== 1) {
            return null;
        }
        $key = self::base32Decode($secret);
        $now = intdiv($time ?? time(), self::PERIOD);
        foreach ([0, -1, 1] as $offset) {
            $step = $now + $offset;
            if ($lastStep !== null && $step <= $lastStep) {
                continue;
            }
            if (hash_equals(self::codeAt($key, $step, self::DIGITS), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** Adresse für Authenticator-Apps (otpauth://). */
    public static function uri(string $secret, string $account, string $issuer = 'EduKSX-Glossary'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::PERIOD;
    }

    /** Code aus Schlüssel (binär) und Zeitschritt (RFC 4226, dynamische Kürzung). */
    public static function codeAt(string $key, int $step, int $digits): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=-]/', '', $text) ?? '');
        $bits = '';
        foreach (str_split($text) as $char) {
            $pos = strpos(self::BASE32, $char);
            if ($pos === false) {
                throw new InvalidArgumentException('Ungültiger Schlüssel.');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }

        return $out;
    }
}
