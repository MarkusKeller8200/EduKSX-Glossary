<?php

namespace App\Enums;

/**
 * Status eines Vorschlags (DEC-024, DEC-025).
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum ProposalStatus: string
{
    use EnumValues;

    case OrganizationReview = 'organization_review';
    case Submitted = 'submitted';
    case Query = 'query';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::OrganizationReview => 'Prüfung durch Organisation',
            self::Submitted => 'eingereicht',
            self::Query => 'Rückfrage',
            self::Accepted => 'angenommen',
            self::Rejected => 'abgelehnt',
            self::Withdrawn => 'zurückgezogen',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'org_pruefung' => 'organization_review',
            'eingereicht' => 'submitted',
            'rueckfrage' => 'query',
            'angenommen' => 'accepted',
            'abgelehnt' => 'rejected',
            'zurueckgezogen' => 'withdrawn',
        ];
    }
}
