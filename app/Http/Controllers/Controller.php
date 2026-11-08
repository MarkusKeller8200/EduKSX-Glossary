<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /** Angemeldetes Konto; ohne Anmeldung 403 (#138, statt eigener Hilfsfunktion je Controller). */
    protected function currentUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** Angemeldetes Konto oder null. */
    protected function currentUserOrNull(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
