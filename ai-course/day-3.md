# Day 3: Streaming Responses with Laravel AI SDK

## Overview

Today you'll implement **streaming responses** using the Laravel AI SDK. This creates a typewriter effect where AI text appears word by word, creating a much more engaging user experience.

**Time:** ~60 minutes  
**Prerequisites:** Day 2 completed (ChatAgent, ChatController, Chat.vue working)  
**Deliverable:** Chat with real-time typewriter effect using Server-Sent Events (SSE)

---

## What is Streaming?

### Normal Response (Non-Streaming)

```
User: "What is Laravel?"
AI: [waits 3 seconds]
AI: "Laravel is a PHP framework..."
```

The AI generates the entire response, then sends it all at once.

### Streaming Response

```
User: "What is Laravel?"
AI: [waits 0.5s] "Laravel"
AI: [0.1s later] " is"
AI: [0.1s later] " a"
AI: [0.1s later] " PHP"
AI: [0.1s later] " framework..."
```

The AI sends tokens as they're generated, creating a typewriter effect.

### Why Streaming Matters

1. **Faster perceived response** - Users see progress immediately
2. **More engaging** - Feels like the AI is "thinking" in real-time
3. **Better UX** - Don't have to wait for complete response to start reading
4. **Cancelable** - Can stop streaming mid-response

---

## Part 1: Update the Backend for Streaming

### Step 1: Update ChatController

Open `app/Http/Controllers/ChatController.php` and update the `streamChat` method:

```php
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
                        $data = json_encode(['text' => $word . ' ']);
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
```

### Key Changes:

1. **Uses `StreamedResponse`** - Laravel's built-in streaming response
2. **Iterates through stream events** - Handles `TextDelta` events with actual text
3. **Splits text into words** - Creates word-by-word typewriter effect
4. **Adds 100ms delay** - Between each word for visible streaming
5. **Sends SSE format** - `data: {"text": "word"}\n\n`
6. **Flushes buffer immediately** - Ensures real-time delivery

### Step 2: Update Routes

Open `routes/web.php` and change the streaming route from POST to GET:

```php
// Streaming route must NOT use session/cookie middleware that buffers responses
Route::get('/api/stream-chat', [ChatController::class, 'streamChat'])
    ->withoutMiddleware([
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        ValidateCsrfToken::class,
    ]);
```

---

## Part 2: Update the Frontend

### Step 1: Update Chat.vue

Replace the entire content of `resources/js/pages/Chat.vue`:

```vue
<template>
    <div class="min-h-screen bg-gray-50 p-4">
        <div class="mx-auto max-w-3xl">
            <!-- Header -->
            <div class="mb-6 rounded-lg bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-bold">AI Chat</h1>
                <p class="text-gray-600">Ask me anything!</p>
            </div>

            <!-- Messages Area -->
            <div class="mb-4 rounded-lg bg-white shadow-sm">
                <div ref="messagesContainer" class="h-96 overflow-y-auto p-4">
                    <div
                        v-if="messages.length === 0"
                        class="flex h-full items-center justify-center text-gray-400"
                    >
                        Start a conversation by typing below...
                    </div>

                    <div
                        v-for="(msg, index) in messages"
                        :key="index"
                        class="mb-4"
                    >
                        <!-- User messages -->
                        <div
                            v-if="msg.role === 'user'"
                            class="flex justify-end"
                        >
                            <div
                                class="max-w-[80%] rounded-lg bg-blue-600 px-4 py-2 text-white"
                            >
                                {{ msg.content }}
                            </div>
                        </div>

                        <!-- AI messages -->
                        <div v-else class="flex justify-start">
                            <div
                                class="max-w-[80%] rounded-lg bg-gray-200 px-4 py-2"
                            >
                                <!-- Loading animation -->
                                <div
                                    v-if="msg.isLoading"
                                    class="flex space-x-1"
                                >
                                    <div
                                        class="h-2 w-2 animate-bounce rounded-full bg-gray-400"
                                    ></div>
                                    <div
                                        class="h-2 w-2 animate-bounce rounded-full bg-gray-400"
                                        style="animation-delay: 0.1s"
                                    ></div>
                                    <div
                                        class="h-2 w-2 animate-bounce rounded-full bg-gray-400"
                                        style="animation-delay: 0.2s"
                                    ></div>
                                </div>
                                <!-- Message content -->
                                <div v-else>{{ msg.content }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <form @submit.prevent="sendMessage" class="flex space-x-2">
                    <input
                        v-model="newMessage"
                        type="text"
                        placeholder="Type your message..."
                        class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                        :disabled="isLoading"
                    />
                    <button
                        type="submit"
                        :disabled="isLoading || !newMessage.trim()"
                        class="rounded-lg bg-blue-600 px-6 py-2 text-white disabled:bg-gray-400"
                    >
                        Send
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick } from 'vue';

const messages = ref([]);
const newMessage = ref('');
const isLoading = ref(false);
const messagesContainer = ref(null);

const sendMessage = async () => {
    if (!newMessage.value.trim() || isLoading.value) return;

    const userMessage = newMessage.value.trim();

    // Add user message
    messages.value.push({
        role: 'user',
        content: userMessage,
    });

    newMessage.value = '';

    // Add loading placeholder for AI response
    const loadingIndex =
        messages.value.push({
            role: 'assistant',
            content: '',
            isLoading: true,
        }) - 1;

    isLoading.value = true;

    try {
        await nextTick();
        scrollToBottom();

        // Use streaming endpoint with GET request
        const response = await fetch(
            `/api/stream-chat?message=${encodeURIComponent(userMessage)}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'text/event-stream',
                },
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Get the reader from the response body stream
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        // Remove loading state once we start receiving data
        messages.value[loadingIndex].isLoading = false;

        while (true) {
            const { done, value } = await reader.read();

            if (done) break;

            // Decode the chunk and add to buffer
            buffer += decoder.decode(value, { stream: true });

            // Process complete lines from buffer
            const lines = buffer.split('\n');
            buffer = lines.pop(); // Keep incomplete line in buffer

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    const data = line.slice(6);

                    if (data === '[DONE]') {
                        // Stream complete
                        continue;
                    }

                    try {
                        const parsed = JSON.parse(data);

                        if (parsed.text) {
                            // Append new text chunk to message
                            messages.value[loadingIndex].content += parsed.text;
                            await nextTick();
                            scrollToBottom();
                        }
                    } catch (e) {
                        // Not valid JSON, might be plain text
                        messages.value[loadingIndex].content += data;
                    }
                }
            }
        }

        // Process any remaining data in buffer
        if (buffer.startsWith('data: ')) {
            const data = buffer.slice(6);
            if (data && data !== '[DONE]') {
                try {
                    const parsed = JSON.parse(data);
                    if (parsed.text) {
                        messages.value[loadingIndex].content += parsed.text;
                    }
                } catch (e) {
                    messages.value[loadingIndex].content += data;
                }
            }
        }
    } catch (error) {
        console.error('Stream error:', error);
        messages.value[loadingIndex] = {
            role: 'assistant',
            content: 'Error: Failed to get response from AI',
            isLoading: false,
        };
    } finally {
        isLoading.value = false;
        await nextTick();
        scrollToBottom();
    }
};

const scrollToBottom = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop =
            messagesContainer.value.scrollHeight;
    }
};
</script>

<style scoped></style>
```

### Key Changes:

1. **Uses `fetch()` with GET** - Request to streaming endpoint
2. **Reads response as stream** - Uses `response.body.getReader()`
3. **Decodes SSE data** - Parses `data: {"text": "word"}` format
4. **Appends text word by word** - Creates typewriter effect
5. **Auto-scrolls** - As new content arrives

---

## Part 3: Understanding Server-Sent Events (SSE)

### What is SSE?

Server-Sent Events is a standard that allows servers to push data to web clients over HTTP:

```
Client: GET /api/stream-chat?message=hello
Server: HTTP/1.1 200 OK
        Content-Type: text/event-stream

        data: {"text": "Hello"}

        data: {"text": "there"}

        data: {"text": "!"}

        data: [DONE]
```

### SSE Format:

- Each message starts with `data: `
- Ends with two newlines `\n\n`
- Can include event types, IDs, retry timing

### Why We Use GET for Streaming:

- GET requests don't require CSRF tokens
- Easier for EventSource API compatibility
- No request body to buffer

---

## Part 4: Adjusting Streaming Speed

The current implementation streams word-by-word with a 100ms delay. You can adjust this:

### Faster Streaming (50ms):

```php
usleep(50000); // 50ms delay
```

### Slower Streaming (200ms):

```php
usleep(200000); // 200ms delay
```

### Character-by-Character:

```php
// Split into individual characters
$chars = str_split($text);
foreach ($chars as $char) {
    $data = json_encode(['text' => $char]);
    echo "data: {$data}\n\n";
    // ... flush and delay
    usleep(50000); // 50ms per character
}
```

---

## Part 5: Test Your Streaming Chat

**Step 1:** Make sure everything is running:

```bash
# Terminal 1 - Backend
./vendor/bin/sail up -d

# Terminal 2 - Frontend
npm run dev
```

**Step 2:** Visit `http://laravel-aisdk-showcase.test/chat`

**Step 3:** Test the streaming:

- Type a message and send
- Watch the text appear word by word
- Each word has a 100ms delay for visible effect

---

## Part 6: Run All Tests

```bash
./vendor/bin/pest tests/Feature/ChatStreamTest.php
```

Expected output:

```
Tests:    3 passed (7 assertions)
```

Also run all chat tests:

```bash
./vendor/bin/pest tests/Feature/ChatControllerTest.php tests/Unit/ChatAgentTest.php tests/Feature/ChatStreamTest.php
```

---

## Day 3 Checklist

**Backend:**

- [ ] ChatController uses `StreamedResponse`
- [ ] Iterates through stream events correctly
- [ ] Splits text into words with delay
- [ ] Sends proper SSE format
- [ ] Route uses GET method
- [ ] All streaming tests passing

**Frontend:**

- [ ] Uses `fetch()` with GET request
- [ ] Reads response as ReadableStream
- [ ] Parses SSE data format
- [ ] Appends text word by word
- [ ] Auto-scroll works during streaming
- [ ] Loading animation shows initially

**Integration:**

- [ ] Full streaming flow works end-to-end
- [ ] Word-by-word typewriter effect visible
- [ ] Error handling works
- [ ] No console errors

---

## Key Concepts Learned

### Streaming Architecture:

- **`StreamedResponse`** - Laravel's streaming response class
- **`$agent->stream()`** - Returns generator of stream events
- **`TextDelta`** - Event containing text chunk with `delta` property
- **`StreamStart`/`StreamEnd`** - Events marking stream boundaries

### Frontend Streaming:

- **`fetch()` with streaming** - Request with streaming response
- **`response.body.getReader()`** - Get stream reader
- **`TextDecoder`** - Decode binary chunks to text
- **SSE parsing** - Split by `\n\n` and extract `data:` lines

### Typewriter Effect:

- **Split into words** - `explode(' ', $text)`
- **Add delay** - `usleep(100000)` for 100ms
- **Flush output** - `ob_flush()` and `flush()` for real-time delivery

---

## Troubleshooting

### Text doesn't stream word by word

- Check that `explode(' ', $text)` is working
- Verify `usleep()` is being called between words
- Make sure output buffering is disabled

### Stream not starting

- Check route is using GET not POST
- Verify streaming route excludes session middleware
- Check Laravel logs for errors

### All text appears at once

- Some AI providers (like Gemini) send entire response as one chunk
- Word splitting in controller fixes this
- Check that chunks are being sent separately in Network tab

---

## Next Steps (Day 4 Preview)

Tomorrow you'll learn:

- **Conversation Memory** - Chat that remembers previous messages
- **`RemembersConversations` trait** - Persist conversation history
- **Continue conversations** - Add context to ongoing chats

---

**Congratulations! You now have a streaming chat with a typewriter effect!** 🎉

Your AI responses now appear word by word with a realistic typing animation!
