<?php

namespace App\Http\Controllers;

use App\Services\Assistant\ConversationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function message(Request $request, ConversationEngine $assistant): JsonResponse
    {
        abort_unless(config('assistant.enabled'), 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json($assistant->respond($data['message'], $request->user())->toArray());
    }

    /**
     * Start a fresh chat: forget what the assistant remembered.
     */
    public function reset(ConversationEngine $assistant): JsonResponse
    {
        abort_unless(config('assistant.enabled'), 404);

        $assistant->reset();

        return response()->json(['ok' => true]);
    }
}
