<?php

namespace App\Http\Controllers;

use App\Search\SearchMisses;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Website meldet eine Suche ohne Treffer (X4, #285). Kein Personenbezug; Antwort ohne Inhalt. */
class SearchMissController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:200']]);
        SearchMisses::record((string) $data['q']);

        return response()->noContent();
    }
}
