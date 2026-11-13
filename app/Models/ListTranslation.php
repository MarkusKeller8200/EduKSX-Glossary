<?php

namespace App\Models;

use App\Data\RecordsDataChanges;
use Illuminate\Database\Eloquent\Model;

/**
 * Übersetzung einer Kategorie oder Herkunft (X1, #282): fr, it oder en. Nicht versioniert (wie die Listen).
 *
 * @property int $id
 * @property string $locale
 * @property string|null $name
 * @property string|null $label
 * @property string|null $description
 */
abstract class ListTranslation extends Model
{
    use RecordsDataChanges;

    /** Übersetzbare Felder. */
    public const FIELDS = ['name', 'label', 'description'];

    /** @var list<string> */
    protected $fillable = ['locale', 'name', 'label', 'description'];
}
