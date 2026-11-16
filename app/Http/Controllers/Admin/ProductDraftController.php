<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DraftProductRequest;
use App\Models\Category;
use App\Services\Ai\LlmException;
use App\Services\Ai\ProductDrafter;

class ProductDraftController extends Controller
{
    /**
     * Suggest listing text for the new-product form. Returns JSON only and
     * saves nothing: the admin reviews and submits the form as usual.
     */
    public function __invoke(DraftProductRequest $request, ProductDrafter $drafter)
    {
        abort_unless(config('ai.enabled'), 404);

        try {
            $draft = $drafter->draft(
                $request->validated('keywords'),
                $request->file('image'),
                Category::all(['id', 'name']),
            );
        } catch (LlmException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($draft);
    }
}
