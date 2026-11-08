<?php

namespace App\Glossary;

use App\Models\Term;

/**
 * Permalink je Begriff (DEC-026, #154): Code mit 6 Zeichen aus Crockford-Base32 (ohne I, L, O, U), zufällig,
 * eindeutig, unveränderlich; Adresse `/g/{code}` («g» für Glossary). Links baut man nur aus festem Ursprung + Code
 * nach PATTERN – nie aus anderen Eingaben.
 */
final class Permalink
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const LENGTH = 6;

    /** Gültiger Code (Grossbuchstaben). */
    public const PATTERN = '[0-9A-HJKMNP-TV-Z]{6}';

    /** Neuer, noch nicht vergebener Code. */
    public static function generate(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (Term::query()->where('code', $code)->exists());

        return $code;
    }

    /** Eingabe vereinheitlichen (Kleinbuchstaben, verwechselbare Zeichen wie bei Crockford); null, wenn ungültig. */
    public static function normalize(string $input): ?string
    {
        $code = strtr(strtoupper(trim($input)), ['O' => '0', 'I' => '1', 'L' => '1']);

        return preg_match('/^'.self::PATTERN.'$/', $code) === 1 ? $code : null;
    }

    public static function url(string $code): string
    {
        return url('/g/'.$code);
    }
}
