<?php

namespace App\Enums;

/**
 * Ereignis im Verlauf eines Vorschlags.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum ProposalEventType: string
{
    use EnumValues;

    case Submitted = 'submitted';
    case ConfirmedByOrganization = 'confirmed_by_organization';
    case RejectedByOrganization = 'rejected_by_organization';
    case ForwardedByOrganization = 'forwarded_by_organization';
    case Query = 'query';
    case Reply = 'reply';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'eingereicht',
            self::ConfirmedByOrganization => 'von der Organisation bestätigt',
            self::RejectedByOrganization => 'von der Organisation abgelehnt',
            self::ForwardedByOrganization => 'von der Organisation weitergeleitet',
            self::Query => 'Rückfrage',
            self::Reply => 'Antwort',
            self::Accepted => 'angenommen',
            self::Rejected => 'abgelehnt',
            self::Withdrawn => 'zurückgezogen',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'eingereicht' => 'submitted',
            'org_bestaetigt' => 'confirmed_by_organization',
            'org_abgelehnt' => 'rejected_by_organization',
            'org_weitergeleitet' => 'forwarded_by_organization',
            'rueckfrage' => 'query',
            'antwort' => 'reply',
            'angenommen' => 'accepted',
            'abgelehnt' => 'rejected',
            'zurueckgezogen' => 'withdrawn',
        ];
    }
}
