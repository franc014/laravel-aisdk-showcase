<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
     * Handle chat with conversation memory (non-streaming, easier to debug).
     */
    public function chatWithMemory(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $sessionUser = (object) ['id' => $sessionId];
        $conversationId = $request->session()->get('chat_conversation_id');

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        Log::info('Chat with memory request', [
            'session_id' => $sessionId,
            'conversation_id' => $conversationId,
            'message' => $validated['message'],
        ]);

        try {
            $agent = new ChatAgent;

            if ($conversationId) {
                Log::info('Continuing existing conversation', ['conversation_id' => $conversationId]);
                $agent->continue($conversationId, $sessionUser);
            } else {
                Log::info('Starting new conversation');
                $agent->forUser($sessionUser);
            }

            $response = $agent->prompt($validated['message']);
            $newConversationId = $agent->currentConversation();

            $request->session()->put('chat_conversation_id', $newConversationId);
            $request->session()->save();

            Log::info('Conversation saved', [
                'conversation_id' => $newConversationId,
                'session_saved' => $request->session()->has('chat_conversation_id'),
            ]);

            return response()->json([
                'success' => true,
                'message' => $response->text,
                'conversation_id' => $newConversationId,
                'provider' => config('ai.default'),
                'timestamp' => now()->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Chat with memory error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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
        $sessionId = $request->session()->getId();
        $sessionUser = (object) ['id' => $sessionId];
        $conversationId = $request->session()->get('chat_conversation_id');
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        Log::info('Stream chat request', [
            'session_id' => $sessionId,
            'conversation_id' => $conversationId,
            'message' => $validated['message'],
        ]);

        return response()->stream(function () use ($conversationId, $sessionUser, $validated, $request) {
            $agent = new ChatAgent;

            if ($conversationId) {
                Log::info('Continuing existing conversation in stream', ['conversation_id' => $conversationId]);
                $agent->continue($conversationId, $sessionUser);
            } else {
                Log::info('Starting new conversation in stream');
                $agent->forUser($sessionUser);
            }

            $stream = $agent->stream($validated['message']);

            $fullResponse = '';

            foreach ($stream as $event) {
                $text = '';
                if (method_exists($event, 'delta') || isset($event->delta)) {
                    $text = $event->delta ?? '';
                } elseif (method_exists($event, 'text') || isset($event->text)) {
                    $text = $event->text ?? '';
                }

                if ($text !== '') {
                    $fullResponse .= $text;

                    $words = explode(' ', $text);
                    foreach ($words as $word) {
                        $data = json_encode(['text' => $word.' ']);
                        echo "data: {$data}\n\n";

                        if (ob_get_level()) {
                            ob_flush();
                        }
                        flush();

                        usleep(100000);
                    }
                }
            }

            echo "data: [DONE]\n\n";

            if (ob_get_level()) {
                ob_flush();
            }
            flush();

            $newConversationId = $agent->currentConversation();
            if ($newConversationId) {
                Log::info('Saving conversation ID to session', [
                    'conversation_id' => $newConversationId,
                    'session_id' => $request->session()->getId(),
                ]);

                $request->session()->put('chat_conversation_id', $newConversationId);
                $request->session()->save();

                Log::info('Conversation ID saved to session', [
                    'saved' => $request->session()->has('chat_conversation_id'),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
