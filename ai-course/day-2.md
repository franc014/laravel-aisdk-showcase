# Day 2: Creating Your First AI Agent & Chat Interface (TDD Approach)

## Overview

Today you'll create a complete AI chat feature using **Test Driven Development (TDD)**. We'll write tests first, then implement the code to make them pass. You'll also learn about **fakes** - a technique to test AI features without making real API calls.

**Time:** ~90 minutes  
**Prerequisites:** Day 1 completed (AI SDK installed)  
**Deliverable:** Fully tested chat interface with AI agent

---

## Why TDD and Fakes?

### Test Driven Development (TDD)

TDD means writing tests **before** writing the actual code. This helps you:

- Think through requirements clearly
- Catch bugs early
- Ensure your code works as expected
- Have confidence when refactoring

### Why Use Fakes?

When testing AI features, we use **fakes** instead of real API calls because:

1. **Cost** - Real AI API calls cost money (even small amounts add up during testing)
2. **Speed** - Fakes are instant; real API calls take 1-5 seconds each
3. **Reliability** - Tests work offline and aren't affected by API rate limits or downtime
4. **Consistency** - Fakes return predictable responses for assertions
5. **No waiting** - Run tests hundreds of times during development without delays

**Example:** Testing with real API:

```php
// Takes 2-3 seconds, costs money, might fail due to network
$response = $agent->prompt('Hello');
```

**Testing with fakes:**

```php
// Instant, free, works offline, predictable
ChatAgent::fake(['Hello! How can I help?']);
$response = $agent->prompt('Hello');
```

---

## Part 0: Setup Testing Configuration

**Goal:** Configure Laravel to use SQLite in-memory database for fast tests.

### Step 1: Update phpunit.xml

Open `phpunit.xml` and update the `<php>` section:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="APP_MAINTENANCE_DRIVER" value="file"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="BROADCAST_CONNECTION" value="null"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="PULSE_ENABLED" value="false"/>
    <env name="TELESCOPE_ENABLED" value="false"/>
    <env name="NIGHTWATCH_ENABLED" value="false"/>
</php>
```

**What this does:**

- `DB_CONNECTION=sqlite` - Uses SQLite instead of PostgreSQL
- `DB_DATABASE=:memory:` - Database exists only in memory (fast, no files)
- Tests run in ~1 second instead of connecting to real database

---

## Part 1: Write Tests for the ChatAgent (TDD Step 1)

**Goal:** Write tests that define what our agent should do.

### Step 1: Create the Test File

Create `tests/Unit/ChatAgentTest.php`:

```php
<?php

use App\Ai\Agents\ChatAgent;
use Illuminate\Foundation\Testing\TestCase;

// Tell Pest to bootstrap the Laravel application
uses(TestCase::class)->in(__DIR__);

it('can be instantiated', function () {
    $agent = new ChatAgent;

    expect($agent)->toBeInstanceOf(ChatAgent::class);
});

it('returns instructions', function () {
    $agent = new ChatAgent;

    $instructions = $agent->instructions();

    expect($instructions)->toBeString()
                         ->toContain('helpful')
                         ->toContain('friendly');
});

it('generates a response when prompted', function () {
    // Use fake to avoid real API calls
    ChatAgent::fake([
        'This is a test response.',
    ]);

    $agent = new ChatAgent;
    $response = $agent->prompt('Test message');

    expect($response->text)->toBe('This is a test response.');

    // Verify the agent was actually called with our message
    ChatAgent::assertPrompted('Test message');
});
```

**Run the tests (they should fail):**

```bash
./vendor/bin/pest tests/Unit/ChatAgentTest.php
```

Expected: **FAIL** - Class ChatAgent doesn't exist yet!

---

## Part 2: Create the ChatAgent (TDD Step 2)

**Goal:** Write code to make the tests pass.

### Step 1: Generate the Agent Class

```bash
./vendor/bin/sail artisan make:agent ChatAgent
```

### Step 2: Implement the Agent

Open `app/Ai/Agents/ChatAgent.php`:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class ChatAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are a helpful, friendly AI assistant. ' .
               'Answer questions concisely and accurately. ' .
               'If you do not know something, say so honestly.';
    }
}
```

**Run the tests again:**

```bash
./vendor/bin/pest tests/Unit/ChatAgentTest.php
```

Expected: **PASS** - All three tests should pass!

---

## Part 3: Write Tests for the ChatController (TDD Step 1)

**Goal:** Write tests that define how our API should behave.

### Step 1: Create the Test File

Create `tests/Feature/ChatControllerTest.php`:

```php
<?php

use App\Ai\Agents\ChatAgent;

it('validates that message is required', function () {
    $response = $this->postJson('/api/chat', []);

    $response->assertStatus(422)
             ->assertJsonValidationErrors(['message']);
});

it('validates that message is not empty', function () {
    $response = $this->postJson('/api/chat', ['message' => '']);

    $response->assertStatus(422)
             ->assertJsonValidationErrors(['message']);
});

it('returns AI response for valid message', function () {
    // Fake the AI agent response (no real API call)
    ChatAgent::fake([
        'Hello! How can I help you today?',
    ]);

    $response = $this->postJson('/api/chat', [
        'message' => 'Hello',
    ]);

    $response->assertStatus(200)
             ->assertJson([
                 'success' => true,
             ])
             ->assertJsonStructure([
                 'success',
                 'message',
                 'provider',
                 'timestamp',
             ]);

    // Verify the agent was called with our message
    ChatAgent::assertPrompted('Hello');
});

it('handles AI errors gracefully', function () {
    // Make the agent throw an error
    ChatAgent::fake(function () {
        throw new \Exception('AI service unavailable');
    });

    $response = $this->postJson('/api/chat', [
        'message' => 'Hello',
    ]);

    $response->assertStatus(500)
             ->assertJson([
                 'success' => false,
             ])
             ->assertJsonPath('error', fn (string $error) =>
                 str_contains($error, 'AI service unavailable')
             );
});
```

**Run the tests (they should fail):**

```bash
./vendor/bin/pest tests/Feature/ChatControllerTest.php
```

Expected: **FAIL** - Route and controller don't exist yet!

---

## Part 4: Create the ChatController (TDD Step 2)

**Goal:** Write code to make the tests pass.

### Step 1: Generate the Controller

```bash
./vendor/bin/sail artisan make:controller ChatController
```

### Step 2: Implement the Controller

Open `app/Http/Controllers/ChatController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
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

            return response()->json([
                'success' => true,
                'message' => $response->text,
                'provider' => config('ai.default'),
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get AI response: ' . $e->getMessage(),
            ], 500);
        }
    }
}
```

### Step 3: Add the Route

Add to `routes/web.php`:

```php
use App\Http\Controllers\ChatController;

Route::post('/api/chat', [ChatController::class, 'chat'])->name('api.chat');
```

**Run the tests again:**

```bash
./vendor/bin/pest tests/Feature/ChatControllerTest.php
```

Expected: **PASS** - All four tests should pass!

### Step 4: Run All Tests

```bash
./vendor/bin/pest
```

You should see all tests passing (50+ tests including the existing ones).

---

## Part 5: Build the Chat UI (Step-by-Step)

**Goal:** Create the Vue component that users interact with, understanding each piece as we build it.

---

### Step 1: Create the Component File

**Create the file:** `resources/js/pages/Chat.vue`

**Start with the basic structure:**

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
                    <!-- Messages will go here -->
                </div>
            </div>

            <!-- Input Area -->
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <!-- Form will go here -->
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick } from 'vue';

// State and functions will go here
</script>
```

**What this sets up:**

- A full-height gray background (`min-h-screen bg-gray-50`)
- A centered container with max width (`mx-auto max-w-3xl`)
- Three main sections: header, messages area, input area
- The `ref="messagesContainer"` allows us to programmatically scroll the chat

---

### Step 2: Set Up Reactive State

**Inside `<script setup>`, add the state variables:**

```javascript
// Stores all messages in the conversation
// Each message has: { role: 'user'|'assistant', content: string, isLoading?: boolean }
const messages = ref([]);

// Tracks what the user is currently typing
// Bound to the input field with v-model
const newMessage = ref('');

// Prevents sending multiple messages while waiting for AI
// Controls button disabled state and loading animation
const isLoading = ref(false);

// Reference to the scrollable message container
// Used to auto-scroll to new messages
const messagesContainer = ref(null);
```

**What each does:**

- `messages` - Array that holds the conversation history. Starts empty.
- `newMessage` - String bound to the input field. Updates as user types.
- `isLoading` - Boolean that locks the UI while waiting for AI response.
- `messagesContainer` - DOM reference to the scrollable area for auto-scrolling.

---

### Step 3: Build the Messages Display

**Inside the messages container div (`<div ref="messagesContainer">`), add:**

```vue
<!-- Empty state when no messages yet -->
<div v-if="messages.length === 0" class="flex h-full items-center justify-center text-gray-400">
  Start a conversation by typing below...
</div>

<!-- Loop through all messages -->
<div v-for="(msg, index) in messages" :key="index" class="mb-4">

  <!-- User messages - right side, blue bubble -->
  <div v-if="msg.role === 'user'" class="flex justify-end">
    <div class="max-w-[80%] rounded-lg bg-blue-600 px-4 py-2 text-white">
      {{ msg.content }}
    </div>
  </div>

  <!-- AI messages - left side, gray bubble -->
  <div v-else class="flex justify-start">
    <div class="max-w-[80%] rounded-lg bg-gray-200 px-4 py-2">

      <!-- Loading animation while AI is thinking -->
      <div v-if="msg.isLoading" class="flex space-x-1">
        <div class="h-2 w-2 animate-bounce rounded-full bg-gray-400"></div>
        <div class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0.1s"></div>
        <div class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0.2s"></div>
      </div>

      <!-- Actual message text -->
      <div v-else>{{ msg.content }}</div>
    </div>
  </div>

</div>
```

**How it works:**

- `v-if="messages.length === 0"` - Shows hint text when conversation is empty
- `v-for="(msg, index) in messages"` - Loops through each message object in the array
- `:key="index"` - Helps Vue track items efficiently for reactivity
- `v-if="msg.role === 'user'"` - User messages appear on the right with blue background
- `v-else` - AI messages appear on the left with gray background
- `v-if="msg.isLoading"` - Shows bouncing dots animation while waiting for response
- `max-w-[80%]` - Prevents message bubbles from being too wide
- `mb-4` - Adds spacing between messages

---

### Step 4: Build the Input Form

**Inside the input area div, add:**

```vue
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
```

**How it works:**

- `@submit.prevent="sendMessage"` - Calls sendMessage when form submits (Enter key or button click). The `.prevent` stops page reload.
- `v-model="newMessage"` - Two-way binding connects input to the reactive variable
- `:disabled="isLoading"` - Disables input while waiting for AI (prevents confusion)
- `:disabled="isLoading || !newMessage.trim()"` - Button disabled when:
    - Waiting for AI response, OR
    - Input is empty/whitespace only
- `flex-1` - Input takes all available space, pushing button to the right
- `type="submit"` - Button triggers form submission
- `disabled:bg-gray-400` - Visual feedback when button is disabled

---

### Step 5: Add the Chat Logic

**Inside `<script setup>`, add the functions:**

```javascript
// Main function that handles sending a message
const sendMessage = async () => {
    // Guard clause: exit if input empty or already loading
    if (!newMessage.value.trim() || isLoading.value) return;

    // Capture the message before clearing input
    const userMessage = newMessage.value.trim();

    // Add user message to conversation (appears immediately on right)
    messages.value.push({
        role: 'user',
        content: userMessage,
    });

    // Clear the input field for next message
    newMessage.value = '';

    // Add placeholder for AI response with loading state
    // Store the index so we can update it later with the actual response
    const loadingIndex =
        messages.value.push({
            role: 'assistant',
            content: '',
            isLoading: true,
        }) - 1;

    // Lock the UI to prevent double-sends
    isLoading.value = true;

    try {
        // Wait for Vue to update DOM, then scroll to show new messages
        await nextTick();
        scrollToBottom();

        // Call the backend API
        const response = await fetch('/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')
                    .content,
            },
            body: JSON.stringify({ message: userMessage }),
        });

        const data = await response.json();

        // Replace loading placeholder with actual AI response
        if (data.success) {
            messages.value[loadingIndex] = {
                role: 'assistant',
                content: data.message,
                isLoading: false,
            };
        } else {
            // Handle API error response
            messages.value[loadingIndex] = {
                role: 'assistant',
                content: 'Error: ' + data.error,
                isLoading: false,
            };
        }
    } catch (error) {
        // Handle network or other errors
        messages.value[loadingIndex] = {
            role: 'assistant',
            content: 'Error: Failed to send message',
            isLoading: false,
        };
    } finally {
        // Always unlock the UI when done (success or error)
        isLoading.value = false;
        await nextTick();
        scrollToBottom();
    }
};

// Auto-scroll to show the newest messages
const scrollToBottom = () => {
    if (messagesContainer.value) {
        // Set scroll position to the total height of content (bottom)
        messagesContainer.value.scrollTop =
            messagesContainer.value.scrollHeight;
    }
};
```

**Flow explanation:**

1. **Validate** - Exit early if we shouldn't proceed
2. **Capture** - Save message before clearing input
3. **Display user message** - Push to array, appears immediately on right
4. **Clear input** - Ready for next message
5. **Show loading** - Add AI placeholder with bouncing dots animation
6. **Lock UI** - Prevent sending another message while waiting
7. **Scroll** - Show the new messages we just added
8. **Call API** - POST to `/api/chat` with user's message
9. **Handle response** - Replace loading with actual AI text
10. **Handle errors** - Show error message if something went wrong
11. **Cleanup** - Always unlock UI and scroll to final position

---

### Step 6: Complete Component

**Your final `Chat.vue` should look like:**

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

                        <div v-else class="flex justify-start">
                            <div
                                class="max-w-[80%] rounded-lg bg-gray-200 px-4 py-2"
                            >
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

    messages.value.push({
        role: 'user',
        content: userMessage,
    });

    newMessage.value = '';

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

        const response = await fetch('/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')
                    .content,
            },
            body: JSON.stringify({ message: userMessage }),
        });

        const data = await response.json();

        if (data.success) {
            messages.value[loadingIndex] = {
                role: 'assistant',
                content: data.message,
                isLoading: false,
            };
        } else {
            messages.value[loadingIndex] = {
                role: 'assistant',
                content: 'Error: ' + data.error,
                isLoading: false,
            };
        }
    } catch (error) {
        messages.value[loadingIndex] = {
            role: 'assistant',
            content: 'Error: Failed to send message',
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
```

---

### Step 7: Add the Route and Navigation

**Add to `routes/web.php`:**

```php
Route::get('/chat', function () {
    return inertia('Chat');
})->name('chat');
```

**Add navigation link to your layout:**

```vue
<Link href="/chat">AI Chat</Link>
```

---

## Testing Your Complete Chat

**Step 1:** Start everything:

```bash
# Terminal 1 - Backend
./vendor/bin/sail up -d

# Terminal 2 - Frontend
npm run dev
```

**Step 2:** Visit `http://localhost/chat`

**Step 3:** Test manually:

- Type a message and send
- Verify the full flow works
- Check that tests still pass: `./vendor/bin/pest`

---

## Day 2 Checklist

**Testing Setup:**

- [ ] `phpunit.xml` configured for SQLite in-memory
- [ ] Tests run in under 3 seconds

**TDD Process:**

- [ ] Wrote agent tests first (they failed)
- [ ] Implemented ChatAgent (tests passed)
- [ ] Wrote controller tests first (they failed)
- [ ] Implemented ChatController (tests passed)

**Code Quality:**

- [ ] All 9 new tests passing
- [ ] All existing tests still passing
- [ ] No real API calls during testing (using fakes)

**Frontend:**

- [ ] Chat.vue component created
- [ ] Can send messages and receive responses
- [ ] Loading states work correctly
- [ ] UI displays correctly

---

## Understanding Fakes in Detail

### What Are Fakes?

Fakes are **test doubles** that replace real implementations. In the Laravel AI SDK, when you call `ChatAgent::fake()`, it replaces the real AI provider with a mock that returns predefined responses.

### How Fakes Work

**Normal flow (production):**

```
Your Code -> ChatAgent -> Real AI API -> $$ + 2-3 seconds -> Response
```

**Fake flow (testing):**

```
Your Code -> ChatAgent -> Fake (returns predefined text) -> Instant
```

### Types of Fakes

**1. Static Fake - Returns fixed responses:**

```php
ChatAgent::fake([
    'Hello!',
    'How can I help?',
]);
```

- First prompt returns "Hello!"
- Second prompt returns "How can I help?"
- Third prompt returns "Hello!" again (cycles)

**2. Dynamic Fake - Returns based on input:**

```php
ChatAgent::fake(function ($prompt) {
    return "You said: {$prompt}";
});
```

- Responds based on what was sent
- Good for testing logic that depends on input

**3. Error Fake - Simulates failures:**

```php
ChatAgent::fake(function () {
    throw new \Exception('API down');
});
```

- Tests error handling without actually breaking anything

### Why This Matters

Imagine running your test suite 100 times a day during development:

**Without fakes:**

- 100 API calls × $0.002 = $0.20/day
- 100 calls × 2 seconds = 200 seconds waiting
- Total: **3.3 minutes wasted per day**

**With fakes:**

- 0 API calls = $0
- 100 tests × 0.01 seconds = 1 second
- Total: **1 second**

**Over a month:** You save ~1.5 hours and ~$6 in API costs!

---

## Key Concepts Learned

### TDD Process:

1. **Red** - Write a test that fails
2. **Green** - Write minimal code to make it pass
3. **Refactor** - Clean up the code

### Testing Benefits:

- Catch bugs before they reach production
- Confidence to refactor
- Living documentation
- Fast feedback loop

### Fakes Benefits:

- No API costs during testing
- Tests run instantly
- Work offline
- Predictable responses
- Test error scenarios easily

---

## Run Your Tests

```bash
# Run all tests
./vendor/bin/pest

# Run just the chat tests
./vendor/bin/pest tests/Feature/ChatControllerTest.php
./vendor/bin/pest tests/Unit/ChatAgentTest.php

# Run with coverage report
./vendor/bin/pest --coverage
```

---

## Next Steps (Day 3 Preview)

Tomorrow you'll learn:

- **Streaming responses** - Show AI text as it arrives word-by-word
- **Server-Sent Events (SSE)** - How to receive streamed data
- **Updating tests** - How to test streaming functionality

---

**Congratulations! You've built a tested AI chat system using TDD!** 🎉

You now have:

- ✅ 9 passing tests (5 unit + 4 feature)
- ✅ Test coverage for validation, success, and error cases
- ✅ Zero API costs during development
- ✅ Confidence your code works correctly
- ✅ Full chat interface working end-to-end
