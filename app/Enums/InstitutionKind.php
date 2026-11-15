<?php

namespace App\Enums;

/**
 * Art der Einrichtung eines Kontos (#415, ersetzt InstitutionType). Werte englisch (KONZEPT-KONVENTIONEN K2), Anzeige
 * über label(). Bei den drei Schularten gibt es zusätzlich das Merkmal «Einheitsgemeinde».
 */
enum InstitutionKind: string
{
    use EnumValues;

    case Personal = 'personal';
    case PrimarySchool = 'primary_school';
    case SecondarySchool = 'secondary_school';
    case ComprehensiveSchool = 'comprehensive_school';
    case Company = 'company';
    case SchoolOffice = 'school_office';
    case MunicipalOffice = 'municipal_office';
    case Canton = 'canton';
    case DataProtection = 'data_protection';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Persönlich',
            self::PrimarySchool => 'Primarschule',
            self::SecondarySchool => 'Sekundarschule',
            self::ComprehensiveSchool => 'Gesamtschule',
            self::Company => 'Firma – Dienstleister',
            self::SchoolOffice => 'Volksschulamt',
            self::MunicipalOffice => 'Gemeindeamt',
            self::Canton => 'Kanton',
            self::DataProtection => 'Datenschutz',
            self::Other => 'andere',
        };
    }

    /** Schularten mit dem Merkmal «Einheitsgemeinde» */
    public function isSchool(): bool
    {
        return in_array($this, [self::PrimarySchool, self::SecondarySchool, self::ComprehensiveSchool], true);
    }

    /** @return array<string, string> keine Werte vor 0.17.0 */
    public static function legacyMap(): array
    {
        return [];
    }
}
