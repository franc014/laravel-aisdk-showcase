<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Handle a chat message and return AI response.
     */
    public function chat(Request $request): JsonResponse
    {

        // Validate the request
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        try {
            // Create agent and get response
            $agent = new ChatAgent;
            $response = $agent->prompt($validated['message']);

            ray($agent->messages());

            return response()->json([
                'success' => true,
                'message' => $response->text,
                'provider' => config('ai.default'),
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get AI response: '.$e->getMessage(),
            ], 500);
        }
    }
}
