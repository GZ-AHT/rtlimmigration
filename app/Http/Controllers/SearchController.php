<?php

namespace App\Http\Controllers;

use App\Support\Search\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Global search endpoint for the top bar (REQ §1). */
class SearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearch $search): JsonResponse
    {
        $term = (string) $request->validate(['q' => ['required', 'string', 'max:100']])['q'];

        return response()->json(['groups' => $search->search($term, $request->user())]);
    }
}
