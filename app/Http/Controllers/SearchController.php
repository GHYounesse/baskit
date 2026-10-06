<?php

namespace App\Http\Controllers;

use App\Services\Search\StoreSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Fewer characters than this and the matches are too broad to be useful.
     */
    private const MIN_LENGTH = 2;

    /**
     * Suggestions for the search box as someone types.
     */
    public function suggest(Request $request, StoreSearch $search): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        // Anything longer than a product name is not a lookup.
        $query = mb_substr($query, 0, 100);

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return response()->json([
                'query' => $query,
                'total' => 0,
                'products' => [],
                'categories' => [],
                'correction' => null,
            ]);
        }

        return response()->json($search->suggest($query));
    }
}
