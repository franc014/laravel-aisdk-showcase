# Day 4: Conversation Memory

## Overview

Today you'll add **conversation memory** to your chat, allowing the AI to remember previous messages and maintain context throughout a conversation. This transforms your chat from isolated Q&A into a true dialogue.

**Time:** ~75 minutes  
**Prerequisites:** Day 3 completed (Streaming chat working)  
**Deliverable:** Chat with persistent conversation history

---

## What is Conversation Memory?

### Without Memory (Current State)

```
User: "My name is John"
AI: "Nice to meet you, John!"

User: "What's my name?"
AI: "I don't know your name."
```

Each message is isolated. The AI has no context.

### With Memory

```
User: "My name is John"
AI: "Nice to meet you, John!"

User: "What's my name?"
AI: "Your name is John, as you just told me!"
```

The AI remembers previous exchanges and can reference them.

### Why Memory Matters

1. **Context awareness** - AI understands references to previous messages
2. **Natural conversation** - Feels like talking to a person who remembers
3. **Follow-up questions** - Can ask "What about X?" referring to earlier topic
4. **Personalization** - Remembers user preferences, facts, history

---

## Part 1: Understanding Laravel AI SDK Memory

### The `RemembersConversations` Trait

The Laravel AI SDK provides a `RemembersConversations` trait that handles:

- **Storing conversations** in the database
- **Retrieving conversation history** automatically
- **Managing conversation context** for prompts
- **Cleaning up old conversations** (optional)

### How It Works

**Backend:**

```php
use Laravel\Ai\Concerns\RemembersConversations;

class ChatAgent extends Agent
{
    use RemembersConversations;

    // That's it! The trait handles everything
}
```

**Database Tables:**

- `agent_conversations` - Stores conversation sessions
- `agent_conversation_messages` - Stores individual messages

### Conversation Flow

1. User sends message
2. System finds or creates conversation
3. User message is saved
4. AI generates response with full context
5. AI response is saved
6. Next message includes all previous context

---

## Part 2: Update the Database

### Step 1: Run the Migration

The Laravel AI SDK includes migrations for conversation tables. Run them:

```bash
php artisan migrate
```

This creates:

- `agent_conversations` table
- `agent_conversation_messages` table

### Step 2: Verify Tables

Check your database:

```bash
php artisan db:show --tables
```

You should see:

- `agent_conversations`
- `agent_conversation_messages`

---

## Part 3: Update ChatAgent

### Step 1: Add the Trait

Open `app/Ai/Agents/ChatAgent.php`:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Agent;
use Laravel\Ai\Concerns\RemembersConversations;

class ChatAgent extends Agent
{
    use RemembersConversations;

    /**
     * Instructions for the AI.
     */
    protected ?string $instructions = "You are a helpful assistant.";
}
```

### Key Points:

- The `RemembersConversations` trait adds all memory functionality
- No additional code needed - it works automatically!
- Conversations are linked by a unique identifier (session ID, user ID, etc.)

---

## Part 4: Update ChatController

### Step 1: Modify streamChat Method

We need to:

1. Accept a conversation ID (or create one)
2. Pass the conversation to the agent

```php
<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        // ... existing non-streaming code ...
    }

    public function streamChat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        // Get or create conversation ID from session
        $conversationId = Session::get('chat_conversation_id');

        return response()->stream(function () use ($validated, $conversationId) {
            $agent = new ChatAgent;

            // If we have an existing conversation, load it
            if ($conversationId) {
                $agent->loadConversation($conversationId);
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
                        $data = json_encode(['text' => $word . ' ']);
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

            // Save conversation ID to session for next message
            Session::put('chat_conversation_id', $agent->conversationId());

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

1. **Get conversation ID from session** - `Session::get('chat_conversation_id')`
2. **Load existing conversation** - `$agent->loadConversation($conversationId)`
3. **Save conversation ID** - `Session::put('chat_conversation_id', ...)`

---

## Part 5: Testing Memory

### Step 1: Start a Conversation

1. Visit `/chat`
2. Type: "My name is Sarah"
3. Wait for response

### Step 2: Test Memory

1. Type: "What's my name?"
2. AI should respond: "Your name is Sarah" (not "I don't know")

### Step 3: Continue Conversation

1. Type: "I love pizza"
2. Type: "What's my favorite food?"
3. AI should respond: "You mentioned that you love pizza"

---

## Part 6: Advanced Features

### Clear Conversation

Add a button to start a new conversation:

**Backend:**

```php
Route::post('/api/chat/clear', function () {
    Session::forget('chat_conversation_id');
    return response()->json(['success' => true]);
})->name('api.chat.clear');
```

**Frontend:**

```vue
<button @click="clearConversation">New Chat</button>

const clearConversation = async () => { await fetch('/api/chat/clear', { method:
'POST', headers: { 'X-CSRF-TOKEN':
document.querySelector('meta[name=csrf-token]').content, }, }); messages.value =
[]; };
```

### Set Conversation Limit

Prevent memory from growing too large:

```php
// In ChatAgent
protected ?int $maxConversationMessages = 20; // Keep last 20 messages
```

### Per-User Conversations

If you have authentication:

```php
$conversationId = auth()->check()
    ? 'user_' . auth()->id()
    : Session::get('chat_conversation_id');
```

---

## Part 7: View Conversation History

### Database Query

You can view saved conversations:

```php
use Laravel\Ai\Models\AgentConversation;

// Get all conversations
$conversations = AgentConversation::all();

// Get messages for a conversation
$messages = AgentConversation::find(1)->messages;
```

### Tinker

```bash
php artisan tinker

>>> \Laravel\Ai\Models\AgentConversation::first()->messages->pluck('content')
```

---

## Day 4 Checklist

**Backend:**

- [ ] Ran migrations for conversation tables
- [ ] Added `RemembersConversations` trait to ChatAgent
- [ ] Updated ChatController to use conversation ID
- [ ] Conversation ID stored in session
- [ ] Memory persists across messages

**Frontend:**

- [ ] Can start new conversation (clear memory)
- [ ] Conversation flows naturally with context

**Testing:**

- [ ] AI remembers user's name
- [ ] AI remembers previous statements
- [ ] Can reference earlier parts of conversation
- [ ] Can clear conversation and start fresh

---

## Key Concepts Learned

### Conversation Memory:

- **`RemembersConversations` trait** - Adds automatic memory
- **`loadConversation($id)`** - Load existing conversation
- **`conversationId()`** - Get current conversation ID
- **Session storage** - Persist conversation ID across requests

### Database Structure:

- **`agent_conversations`** - Conversation sessions
- **`agent_conversation_messages`** - Individual messages with role (user/assistant)

### Context Management:

- **Automatic context injection** - Previous messages included in prompts
- **Token limits** - Large conversations may need trimming
- **Session-based** - Each user/session has separate conversation

---

## Troubleshooting

### Memory not working

- Check migrations ran successfully
- Verify `RemembersConversations` trait is added
- Ensure conversation ID is being saved to session
- Check database tables have data

### AI doesn't remember

- Verify conversation is being loaded: `$agent->loadConversation($id)`
- Check conversation ID is consistent across requests
- Look at database - messages should be saved

### Conversation too long

- Set `maxConversationMessages` property
- Old messages are automatically trimmed
- Or implement manual "New Chat" button

---

## Next Steps (Day 5 Preview)

Tomorrow you'll learn:

- **Tools** - Give your AI the ability to perform actions
- **Function calling** - AI can call PHP functions
- **External APIs** - Weather, calculations, database queries

---

**Congratulations! Your AI now has a memory!** 🧠

Your chat can now maintain context and have natural, flowing conversations!
