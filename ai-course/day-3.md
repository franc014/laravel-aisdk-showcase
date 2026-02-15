# Day 3: Streaming Responses & Real-Time Chat

## Overview

Today you'll implement **streaming responses** - showing AI text as it's being generated word by word, instead of waiting for the complete response. This creates a much more engaging user experience.

**Time:** ~60 minutes  
**Prerequisites:** Day 2 completed (ChatAgent, ChatController, Chat.vue working)  
**Deliverable:** Chat with real-time typewriter effect

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

## Part 0: Setup & Prerequisites

**Goal:** Verify your current setup is working before making changes.

Run the tests:

```bash
./vendor/bin/pest tests/Unit/ChatAgentTest.php
./vendor/bin/pest tests/Feature/ChatControllerTest.php
```

Make sure all tests pass before proceeding.

---

## Part 1: Understanding the Stream Method

**Goal:** Learn how the Laravel AI SDK handles streaming.

### How Streaming Works in Laravel AI SDK

The SDK provides a `stream()` method that returns a `StreamableAgentResponse`:

```php
// Non-streaming (what we have now)
$response = $agent->prompt('Hello');
echo $response->text; // Full response

// Streaming (what we'll use)
$stream = $agent->stream('Hello');
foreach ($stream as $chunk) {
    echo $chunk->text; // Incremental chunks
}
```

The stream yields chunks as the AI generates them, allowing you to send each chunk to the frontend in real-time.

---

## Part 2: Write Tests for Streaming (TDD Step 1)

**Goal:** Write tests that define how streaming should work.

### Step 1: Create Streaming Tests

Create `tests/Feature/ChatStreamTest.php`:

```php
<?php

use App\Ai\Agents\ChatAgent;
use Illuminate\Foundation\Testing\TestCase;

uses(TestCase::class)->in(__DIR__);

it('streams response when using stream method', function () {
    ChatAgent::fake([
        'Hello',
        ' there',
        '!',
    ]);

    $agent = new ChatAgent;
    $stream = $agent->stream('Hi');

    $chunks = [];
    foreach ($stream as $chunk) {
        $chunks[] = $chunk->text;
    }

    expect($chunks)->toBe(['Hello', ' there', '!']);
});

it('can stream response to client via SSE', function () {
    ChatAgent::fake(['First chunk', ' second chunk']);

    $response = $this->get('/api/stream-chat?message=Hello');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

    $content = $response->getContent();

    expect($content)->toContain('data: First chunk');
    expect($content)->toContain('data: second chunk');
});

it('validates message in stream endpoint', function () {
    $response = $this->get('/api/stream-chat');

    $response->assertStatus(422);
});
```

**Run the tests (they should fail):**

```bash
./vendor/bin/pest tests/Feature/ChatStreamTest.php
```

Expected: **FAIL** - Routes and methods don't exist yet!

---

## Part 3: Implement Streaming in Controller (TDD Step 2)

**Goal:** Write code to make the tests pass.

### Step 1: Add Streaming Method to ChatController

Open `app/Http/Controllers/ChatController.php`:

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
     * Handle a chat message and return AI response.
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
     * Stream chat response to client via Server-Sent Events (SSE).
     */
    public function streamChat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $agent = new ChatAgent;
        $stream = $agent->stream($validated['message']);

        return response()->stream(function () use ($stream) {
            foreach ($stream as $chunk) {
                // Send each chunk as SSE data
                echo "data: " . json_encode(['text' => $chunk->text]) . "\n\n";

                // Flush output buffer to send immediately
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }

            // Send done signal
            echo "data: " . json_encode(['done' => true]) . "\n\n";

            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
```

### Step 2: Add the Streaming Route

Add to `routes/web.php`:

```php
Route::get('/api/stream-chat', [ChatController::class, 'streamChat']);
```

**Run the tests again:**

```bash
./vendor/bin/pest tests/Feature/ChatStreamTest.php
```

Expected: **PASS** - All tests should pass!

---

## Part 4: Update Frontend for Streaming (Step-by-Step)

**Goal:** Update the Vue component to handle streamed responses.

### Step 1: Understand the New Flow

**Before:** Send POST → Wait → Get full response → Display  
**After:** Send GET → Get chunks in real-time → Display each chunk → Show completion

### Step 2: Update the sendMessage Function

Replace the `sendMessage` function in `Chat.vue`:

```javascript
const sendMessage = async () => {
    // Guard clause: exit if input empty or already loading
    if (!newMessage.value.trim() || isLoading.value) return;

    const userMessage = newMessage.value.trim();

    // Add user message
    messages.value.push({
        role: 'user',
        content: userMessage,
    });

    newMessage.value = '';

    // Add loading placeholder
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

        // Use streaming endpoint
        const response = await fetch(
            `/api/stream-chat?message=${encodeURIComponent(userMessage)}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'text/event-stream',
                    'Cache-Control': 'no-cache',
                },
            },
        );

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();

            if (done) break;

            buffer += decoder.decode(value, { stream: true });

            // Process complete SSE messages
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    const data = line.slice(6);

                    try {
                        const parsed = JSON.parse(data);

                        if (parsed.done) {
                            // Stream complete
                            messages.value[loadingIndex].isLoading = false;
                        } else if (parsed.text) {
                            // Append new text chunk
                            messages.value[loadingIndex].content += parsed.text;
                            await nextTick();
                            scrollToBottom();
                        }
                    } catch (e) {
                        // Skip invalid JSON
                    }
                }
            }
        }
    } catch (error) {
        console.error('Stream error:', error);
        messages.value[loadingIndex] = {
            role: 'assistant',
            content: 'Error: Failed to connect to stream',
            isLoading: false,
        };
    } finally {
        isLoading.value = false;
        await nextTick();
        scrollToBottom();
    }
};
```

### Step 3: What This Does (Conceptual Explanation)

**The fetch call:**

- Uses GET request instead of POST
- Sets `Accept: text/event-stream` header
- Gets a ReadableStream from the response

**The reader loop:**

- Reads chunks from the stream as they arrive
- Decodes the binary data to text
- Buffers incomplete lines

**Processing SSE messages:**

- SSE format: `data: {"text": "hello"}\n\n`
- Splits by newlines
- Parses JSON from `data: ` prefix
- Appends text to message content
- Scrolls to show new text

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
- Watch the text appear word by word (or chunk by chunk)
- The effect depends on how fast the AI generates tokens

---

## Part 6: Run All Tests

```bash
./vendor/bin/pest
```

You should see all tests passing including the new streaming tests!

---

## Day 3 Checklist

**Backend:**

- [ ] Stream method implemented in ChatController
- [ ] SSE endpoint `/api/stream-chat` working
- [ ] Streaming tests passing

**Frontend:**

- [ ] Chat.vue updated to use streaming
- [ ] Text appears in real-time
- [ ] Auto-scroll works during streaming
- [ ] No console errors

**Integration:**

- [ ] Full streaming flow works end-to-end
- [ ] Loading state shows correctly
- [ ] Error handling works

---

## Key Concepts Learned

### Server-Sent Events (SSE):

- **What:** A way to stream data from server to client over HTTP
- **Format:** `data: {"text": "hello"}\n\n`
- **Headers:** `Content-Type: text/event-stream`
- **Connection:** Keep-alive for continuous streaming

### ReadableStream:

- **What:** Web API for reading streaming data
- **Methods:** `read()` returns `{done, value}`
- **Use:** Process chunks as they arrive

### Typewriter Effect:

- **How:** Append each chunk to existing text
- **UI:** User sees text appearing progressively
- **UX:** Feels more responsive and engaging

---

## Troubleshooting

### Text appears all at once

- Check that SSE endpoint is working
- Verify the reader loop is processing chunks
- Add console.log to debug chunk arrival

### Scroll not working during stream

- Make sure `nextTick()` is called after each chunk
- Check that `scrollToBottom()` runs after content update

### Connection closes early

- Check server timeout settings
- Verify no errors in Laravel logs
- Ensure `flush()` is being called

---

## Next Steps (Day 4 Preview)

Tomorrow you'll learn:

- **Conversation Memory** - Chat that remembers previous messages
- **RemembersConversations trait** - Persist conversation history
- **Continue conversations** - Add context to ongoing chats

---

**Congratulations! You now have a real-time streaming chat!** 🎉

Your AI responses now appear as they're being generated, creating a much more engaging experience!
