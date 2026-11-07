<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceAccountToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Selbstauskunft eines Servicekontos (#47): zum Prüfen eines Tokens bei der Einrichtung einer Integration. */
class ServiceController extends Controller
{
    public function whoami(Request $request): JsonResponse
    {
        $token = $request->attributes->get('service_token');
        abort_unless($token instanceof ServiceAccountToken, 401);

        return response()->json([
            'service_account' => ['id' => $token->account->id, 'name' => $token->account->name, 'scopes' => $token->account->scopes],
            'token' => ['name' => $token->name, 'expires_at' => $token->expires_at->toIso8601ZuluString()],
        ])->header('Cache-Control', 'no-store');
    }
}
