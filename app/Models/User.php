<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Lokales Konto der Administration bzw. Redaktion (DEC-014). Angelegt nur per `php artisan glossary:admin`,
 * keine Registrierung über die Website.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property bool $is_admin
 */
class User extends Authenticatable
{
    use HasUuids, Notifiable;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password', 'is_admin'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
