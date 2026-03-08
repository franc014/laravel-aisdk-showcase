# 🧪 Manual Browser Testing Guide - Day 4

## ✅ **Streaming with Memory - Browser Test**

### **Step 1: Open Chat**

Visit: `http://laravel-aisdk-showcase.test/chat`

### **Step 2: First Message (Creates Conversation)**

Type: **"My name is Francisco"**

**Expected Response:**

```
AI: "Nice to meet you, Francisco! How can I help you today?"
```

**What happens behind the scenes:**

- ✅ New conversation created in database
- ✅ Conversation ID saved to your browser session
- ✅ User message stored: "My name is Francisco"
- ✅ Assistant message stored: "Nice to meet you..."

### **Step 3: Second Message (Tests Memory)**

Type: **"What's my name?"**

**Expected Response:**

```
AI: "Your name is Francisco."
```

**What happens behind the scenes:**

- ✅ Conversation ID loaded from session
- ✅ Previous messages loaded (AI sees "My name is Francisco")
- ✅ New message added to conversation
- ✅ AI remembers context and answers correctly

### **Step 4: Third Message (Confirms Memory)**

Type: **"Can you spell it for me?"**

**Expected Response:**

```
AI: "Your name is Francisco: F-R-A-N-C-I-S-C-O."
```

**What this proves:**

- ✅ AI remembers the name from 2 messages ago
- ✅ Context is maintained throughout conversation
- ✅ All messages are being stored and retrieved

---

## 🔍 **How to Verify in Database**

### **Check Conversations:**

```bash
php artisan tinker
```

```php
// Get latest conversation
$conv = DB::table('agent_conversations')->latest()->first();

// Check it has your session ID
$conv->user_id; // Should be a 40-character string

// View all messages
DB::table('agent_conversation_messages')
    ->where('conversation_id', $conv->id)
    ->get(['role', 'content']);
```

**Expected output:**

```
1. user:      "My name is Francisco"
2. assistant: "Nice to meet you, Francisco!..."
3. user:      "What's my name?"
4. assistant: "Your name is Francisco."
5. user:      "Can you spell it for me?"
6. assistant: "Your name is Francisco: F-R-A-N-C-I-S-C-O."
```

---

## 📊 **Monitor Logs in Real-Time**

Open a terminal and run:

```bash
tail -f storage/logs/laravel.log | grep -E "(Stream chat|conversation)"
```

**You should see:**

```
[timestamp] local.INFO: Stream chat request {"session_id":"...","conversation_id":null,...}
[timestamp] local.INFO: Starting new conversation in stream
[timestamp] local.INFO: Saving conversation ID to session {"conversation_id":"...","session_id":"..."}

[timestamp] local.INFO: Stream chat request {"session_id":"...","conversation_id":"019ccab0-..."}
[timestamp] local.INFO: Continuing existing conversation in stream {"conversation_id":"019ccab0-..."}
[timestamp] local.INFO: Saving conversation ID to session
```

**Key indicators:**

- First message: `"conversation_id":null` → `"Starting new conversation"`
- Second message: `"conversation_id":"..."` → `"Continuing existing conversation"`

---

## ❌ **If Memory Doesn't Work**

### **Symptom:** AI says "I don't know your name"

**Check 1: Session Persistence**

```bash
# Clear browser cookies
# Then visit /chat again
# Session should be reset
```

**Check 2: Database Type**

```bash
php artisan db:table agent_conversations
```

**Verify:**

```
user_id | character varying(255) | nullable
```

If it shows `bigint`, run:

```bash
php artisan migrate
```

**Check 3: Logs**

```bash
tail -100 storage/logs/laravel.log | grep -i "error"
```

**Check 4: Session Driver**

```bash
# Check .env
grep SESSION_DRIVER .env
```

Should be: `SESSION_DRIVER=database` or `file`

---

## 🎯 **Success Criteria**

### ✅ **Memory Working:**

1. Send message: "My name is X"
2. Send message: "What's my name?"
3. AI responds: "Your name is X"

### ✅ **Database Working:**

```sql
SELECT COUNT(*) FROM agent_conversations;
-- Should be > 0 after sending messages

SELECT COUNT(*) FROM agent_conversation_messages;
-- Should be 2x number of messages (user + assistant)
```

### ✅ **Logs Working:**

```
✓ "Starting new conversation" on first message
✓ "Continuing existing conversation" on subsequent messages
✓ No errors in logs
```

---

## 🐛 **Common Issues**

### **Issue 1: "I don't know your name"**

**Cause:** Conversation ID not being saved to session

**Fix:**

```php
// Check ChatController line ~100-106
$request->session()->put('chat_conversation_id', $agent->currentConversation());
$request->session()->save(); // ← This line is critical!
```

### **Issue 2: Rate Limiting**

**Symptom:** "Application rate limited by AI provider [gemini]"

**Solution:**

- Wait 60 seconds between test runs
- Or switch to Ollama: `AI_DEFAULT_PROVIDER=ollama` in `.env`

### **Issue 3: Database Type Mismatch**

**Error:** `invalid input syntax for type bigint`

**Fix:** Already fixed with migration! If still seeing this:

```bash
php artisan migrate:fresh
```

---

## 📝 **Test Script**

Copy/paste this conversation to test:

```
You: My name is Francisco
AI: [Should greet you by name]

You: What's my name?
AI: [Should say "Your name is Francisco"]

You: I like pizza
AI: [Should acknowledge]

You: What do I like?
AI: [Should say "You mentioned you like pizza"]

You: What's my name again?
AI: [Should still remember "Francisco"]
```

---

## ✅ **Final Verification Checklist**

After testing, verify:

- [ ] AI remembered your name
- [ ] AI remembered previous statements
- [ ] Database has conversations
- [ ] Database has messages
- [ ] Logs show "Continuing existing conversation"
- [ ] No errors in logs
- [ ] Works across multiple messages
- [ ] Works after page refresh (same session)

---

## 🎉 **If All Checks Pass**

**Congratulations! Conversation memory is working!**

Your AI chat now:

- ✅ Remembers user information
- ✅ Maintains context across messages
- ✅ Stores conversation history in database
- ✅ Persists sessions correctly

**Ready for Day 5: Structured Output!**
