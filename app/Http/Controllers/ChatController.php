<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    /**
     * Handle a chat message and return AI response (non-streaming).
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        try {
            $agent = new ChatAgent;
            $response = $agent->prompt($validated['message']);

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

    /**
     * Stream chat response using Laravel AI SDK basic streaming.
     */
    public function streamChat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        return response()->stream(function () use ($validated) {
            $agent = new ChatAgent;
            $stream = $agent->stream($validated['message']);

            foreach ($stream as $event) {
                // Only send text events (TextDelta), not StreamStart or StreamEnd
                $text = '';
                if (method_exists($event, 'delta') || isset($event->delta)) {
                    $text = $event->delta ?? '';
                } elseif (method_exists($event, 'text') || isset($event->text)) {
                    $text = $event->text ?? '';
                }

                if ($text !== '') {
                    // Split text into words for slower streaming effect
                    $words = explode(' ', $text);
                    foreach ($words as $index => $word) {
                        $data = json_encode(['text' => $word.' ']);
                        echo "data: {$data}\n\n";

                        // Flush output buffer to send immediately
                        if (ob_get_level()) {
                            ob_flush();
                        }
                        flush();

                        // Add delay between words (100ms = 0.1 seconds)
                        usleep(100000);
                    }
                }
            }

            // Send completion marker
            echo "data: [DONE]\n\n";

            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
