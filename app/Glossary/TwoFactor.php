<?php

namespace App\Glossary;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Zweiter Faktor für lokale Konten (#11): einrichten, bestätigen, prüfen, Wiederherstellungscodes, zurücksetzen.
 * Schlüssel verschlüsselt (Model-Cast), Wiederherstellungscodes nur als Hash, jeder Code nur einmal gültig.
 */
final class TwoFactor
{
    public const RECOVERY_CODES = 8;

    /** Neuen (noch unbestätigten) Schlüssel erzeugen; ersetzt einen früheren unbestätigten. */
    public function start(User $user): string
    {
        $secret = Totp::secret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        return $secret;
    }

    /**
     * Ersten Code prüfen und bestätigen; gibt die Wiederherstellungscodes im Klartext zurück (nur dieses eine Mal).
     *
     * @return list<string>|null null = Code falsch
     */
    public function confirm(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || $this->verifyTotp($user, $code) === false) {
            return null;
        }
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->newRecoveryCodes($user);
    }

    /** Code aus der App oder Wiederherstellungscode prüfen (Wiederherstellungscode wird verbraucht). */
    public function check(User $user, string $code): bool
    {
        if (! $user->hasTwoFactor()) {
            return false;
        }
        if ($this->verifyTotp($user, $code)) {
            return true;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        foreach ($codes as $i => $hash) {
            if ($normalized !== '' && Hash::check($normalized, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Neue Wiederherstellungscodes (alte werden ungültig).
     *
     * @return list<string>
     */
    public function newRecoveryCodes(User $user): array
    {
        $plain = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $plain[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }
        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $c): string => Hash::make(str_replace('-', '', $c)), $plain),
        ])->save();

        return $plain;
    }

    /** Zweiten Faktor entfernen (z. B. Handy verloren); beim nächsten Login wird er neu eingerichtet. */
    public function reset(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    private function verifyTotp(User $user, string $code): bool
    {
        $step = Totp::verify((string) $user->two_factor_secret, $code, $user->two_factor_last_step);
        if ($step === null) {
            return false;
        }
        $user->forceFill(['two_factor_last_step' => $step])->save();

        return true;
    }
}
