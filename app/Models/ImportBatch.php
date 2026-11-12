<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Import-Protokoll (#64): eine Zeile je übernommener Excel-Datei. Ohne Zeileninhalte; Bezeichnung der Person als Text
 * (bei Kontolöschung ersetzt). 12 Monate aufbewahrt.
 *
 * @property int $id
 * @property string|null $user_id
 * @property string|null $user_label
 * @property string $file_name
 * @property int $format_version
 * @property int $rows
 * @property int $created_count
 * @property int $updated_count
 * @property int $error_count
 * @property list<array{zeile: int, meldung: string}>|null $errors
 * @property CarbonImmutable $created_at
 */
class ImportBatch extends Model
{
    public const UPDATED_AT = null;

    /** Aufbewahrung in Monaten. */
    public const RETENTION_MONTHS = 12;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'user_label', 'file_name', 'format_version', 'rows', 'created_count', 'updated_count',
        'error_count', 'errors',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['errors' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
