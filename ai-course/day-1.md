# Day 1: Project Setup & AI SDK Installation

## Overview

Today you'll install and configure the Laravel AI SDK with multiple providers (OpenAI, Gemini, Groq, and local Ollama). You'll also set up PostgreSQL with pgvector support via Laravel Sail.

**Time:** ~60 minutes  
**Deliverable:** Working AI SDK with a test endpoint that returns AI responses

---

## Prerequisites

Before starting, ensure you have:

- PHP 8.4+ installed
- Composer installed
- Docker installed (for Laravel Sail)
- ~5GB free disk space for Ollama models

---

## Task 1: Install the Laravel AI SDK

**What we're doing:** Adding the AI SDK package to your project.

### Instruction

Run this command in your project directory:

```bash
composer require laravel/ai
```

### What This Does

- Downloads the `laravel/ai` package and its dependencies
- Registers the AI service provider automatically
- Makes AI facades and classes available throughout your application
- Installs provider drivers for OpenAI, Anthropic, Gemini, Groq, Ollama, etc.

### Expected Output

Composer will install packages and show a success message. You'll see something like:

```
Installing laravel/ai (v0.x.x)
Installing openai-php/client (v0.x.x)
...
Package manifest generated successfully.
```

### Verification

Check that the package is installed:

```bash
composer show laravel/ai
```

---

## Task 2: Publish AI SDK Configuration

**What we're doing:** Creating the configuration file and database tables needed for conversation storage.

### Instructions

Run these commands one by one:

```bash
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
```

You should see output like:

```
Copied File [/vendor/laravel/ai/config/ai.php] To [/config/ai.php]
Copied Directory [/vendor/laravel/ai/database/migrations] To [/database/migrations]
Publishing complete.
```

Then run the migrations:

```bash
php artisan migrate
```

### What This Does

**Configuration File (`config/ai.php`):**

- Provider API keys and settings
- Default models for text, images, audio, embeddings
- Failover configuration
- Embedding cache settings

**Database Tables:**

- `agent_conversations` - Stores conversation metadata
- `agent_conversation_messages` - Stores individual messages

### Verification

Check that the files exist:

```bash
ls config/ai.php
ls database/migrations/*agent_conversation*
```

---

## Task 3: Set Up Laravel Sail with PostgreSQL

**What we're doing:** Adding Docker support with PostgreSQL, which includes the pgvector extension for vector embeddings (needed for Day 8-9).

### Instructions

**Step 1:** Install Sail with PostgreSQL support:

```bash
php artisan sail:install --with=pgsql
```

**Step 2:** Start the containers:

```bash
./vendor/bin/sail up -d
```

The `-d` flag runs containers in detached mode (background).

### What This Does

- Creates `docker-compose.yml` with PostgreSQL service
- Configures pgvector extension in PostgreSQL
- Creates convenient Sail command aliases
- Sets up persistent volumes for database storage

### Verification

Check that containers are running:

```bash
./vendor/bin/sail ps
```

You should see:

```
NAME                STATUS
laravel.test        Up
pgsql               Up
```

Test database connection:

```bash
./vendor/bin/sail artisan db:monitor
```

### Common Issues

**Issue:** Port 5432 already in use  
**Fix:** Change the port in `docker-compose.yml`:

```yaml
ports:
    - '5433:5432' # Use 5433 instead of 5432
```

**Issue:** Permission denied  
**Fix:** On Linux, you may need to run:

```bash
sudo chmod +x vendor/bin/sail
```

---

## Task 4: Install Ollama Locally

**What we're doing:** Setting up local AI model hosting with Ollama. This runs models directly on your machine - no API keys needed, completely free.

### Instructions

**Step 1:** Install Ollama

**macOS (with Homebrew):**

```bash
brew install ollama
```

**Or download directly:**

- Visit https://ollama.com/download
- Download for your OS
- Follow installation prompts

**Step 2:** Start the Ollama service

```bash
ollama serve
```

Leave this running in a terminal window. You'll see output like:

```
time=2025-01-XX... level=INFO msg="Listening on 127.0.0.1:11434"
```

**Step 3:** Pull your first model (in a new terminal window)

```bash
ollama pull llama3.1:8b
```

This downloads a 4.7GB model. It will take 5-10 minutes depending on your connection speed.

**Step 4:** Pull the embedding model

```bash
ollama pull nomic-embed-text
```

This is a smaller model (~300MB) for generating text embeddings.

### What This Does

- Installs Ollama CLI and service
- Downloads Llama 3.1 (8B parameters) for text generation
- Downloads nomic-embed-text for embeddings
- Runs an HTTP server on port 11434

### Verification

Test Ollama interactively:

```bash
ollama run llama3.1:8b
```

Type a message when you see the `>>>` prompt:

```
>>> Hello, what is Laravel?
```

The model should respond with an explanation. Type `/bye` to exit.

### System Requirements Check

Your specs (16GB RAM, 800GB disk) are excellent for:

- **Llama 3.1 (8B):** Requires ~6GB RAM, runs smoothly
- **Multiple models:** You have plenty of space for 10+ models
- **Future models:** Can run larger models (13B, 70B) if desired

### Troubleshooting

**Issue:** "Error: could not connect to ollama"  
**Fix:** Make sure `ollama serve` is running in another terminal

**Issue:** Download is very slow  
**Fix:** This is normal for large models. You can interrupt and resume:

```bash
Ctrl+C  # Interrupt
ollama pull llama3.1:8b  # Resume from where it left off
```

---

## Task 5: Configure Environment Variables

**What we're doing:** Setting up API keys for cloud providers and configuring Ollama connection.

### Instructions

Open your `.env` file and add these lines at the bottom:

```env
# ===========================================
# AI Provider API Keys (Free tiers)
# ===========================================

# OpenAI - Get key at: https://platform.openai.com/api-keys
# Free tier: $5-18 in credits
OPENAI_API_KEY=your_openai_key_here

# Gemini - Get key at: https://aistudio.google.com/app/apikey
# Very generous free tier
GEMINI_API_KEY=your_gemini_key_here

# Groq - Get key at: https://console.groq.com/keys
# Fast inference, free tier with daily limits
GROQ_API_KEY=your_groq_key_here

# ===========================================
# Ollama (Local Models)
# ===========================================
# For macOS/Linux with Docker:
OLLAMA_URL=http://host.docker.internal:11434
# For Windows or direct connection:
# OLLAMA_URL=http://localhost:11434

# ===========================================
# AI Configuration
# ===========================================
AI_DEFAULT_PROVIDER=openai
AI_FALLBACK_PROVIDERS=gemini,ollama
```

### Getting Free API Keys

#### OpenAI

1. Go to https://platform.openai.com/api-keys
2. Sign up for an account
3. Verify your phone number
4. Create a new API key
5. Copy the key (starts with `sk-`)

**Note:** New accounts get $5-18 in free credits valid for 3 months.

#### Gemini

1. Go to https://aistudio.google.com/app/apikey
2. Sign in with your Google account
3. Click "Create API Key"
4. Copy the key

**Note:** Gemini has a very generous free tier with high rate limits.

#### Groq

1. Go to https://console.groq.com/keys
2. Create a free account
3. Generate an API key
4. Copy the key (starts with `gsk_`)

**Note:** Groq offers fast inference with free daily limits.

### Important Notes

- **Security:** Never commit your `.env` file with real API keys
- **Flexibility:** You can add providers later - just Ollama + one cloud provider is enough to start
- **Fallbacks:** The `AI_FALLBACK_PROVIDERS` defines the order if primary fails

---

## Task 6: Configure AI Providers

**What we're doing:** Editing `config/ai.php` to set up multiple providers with failover support.

### Instructions

**Step 1:** Open `config/ai.php` in your editor

**Step 2:** Find the `providers` array (around line 20-50) and update it:

```php
'providers' => [

    // OpenAI - Good for text, images, embeddings
    'openai' => [
        'driver' => 'openai',
        'key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_BASE_URL'), // Optional: for proxies
        'models' => [
            'text' => 'gpt-3.5-turbo',
            'image' => 'dall-e-3',
            'embedding' => 'text-embedding-3-small',
            'audio' => 'tts-1',
            'transcription' => 'whisper-1',
        ],
    ],

    // Gemini - Good for text and vision
    'gemini' => [
        'driver' => 'gemini',
        'key' => env('GEMINI_API_KEY'),
        'models' => [
            'text' => 'gemini-pro',
            'image' => 'gemini-pro-vision',
            'embedding' => 'embedding-001',
        ],
    ],

    // Ollama - Local models, completely free
    'ollama' => [
        'driver' => 'ollama',
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'models' => [
            'text' => 'llama3.1:8b',
            'embedding' => 'nomic-embed-text',
        ],
    ],

    // Groq - Fast inference, good for text
    'groq' => [
        'driver' => 'groq',
        'key' => env('GROQ_API_KEY'),
        'models' => [
            'text' => 'llama-3.1-8b-instant',
        ],
    ],

],
```

**Step 3:** Find the `defaults` array and update it:

```php
'defaults' => [
    // Primary provider
    'provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    // Fallback chain (comma-separated in env, parsed here)
    'fallbacks' => explode(',', env('AI_FALLBACK_PROVIDERS', 'gemini,ollama')),

    // Default models (can be overridden per-request)
    'models' => [
        'text' => 'gpt-3.5-turbo',
        'embedding' => 'text-embedding-3-small',
    ],
],
```

### What This Configures

**Provider Drivers:**

- Each provider has a driver that handles API communication
- Drivers are included in the AI SDK
- Custom drivers can be added

**Model Mapping:**

- Maps feature types (text, image, etc.) to specific model names
- Different providers use different naming conventions
- You can change models per-feature

**Failover Chain:**

- If OpenAI fails, automatically tries Gemini
- If Gemini fails, automatically tries Ollama
- No code changes needed - it's automatic!

### Configuration Options Explained

**url (optional):**

- For OpenAI/Anthropic: Use custom base URL (proxies, Azure, etc.)
- For Ollama: Connect to different host/port

**models:**

- `text`: Chat/completion models
- `image`: Image generation models
- `embedding`: Vector embedding models
- `audio`: Text-to-speech models
- `transcription`: Speech-to-text models

### Verification

Test your configuration:

```bash
php artisan tinker
```

Then run:

```php
config('ai.providers.openai.models.text')
```

Should output: `"gpt-3.5-turbo"`

Type `exit` to quit tinker.

---

## Task 7: Verify Everything Works

**What we're doing:** Creating a test route to verify the AI SDK is properly configured and can communicate with providers.

### Instructions

**Step 1:** Open `routes/web.php` in your editor

**Step 2:** Add this test route at the bottom:

```php
<?php

use Illuminate\Support\Facades\Route;
use Laravel\Ai\Facades\Ai;

// ... existing routes ...

// AI Test Route
Route::get('/test-ai', function () {
    try {
        // Try to generate a simple text response
        $response = Ai::text('Say "Hello from Laravel AI SDK!" and confirm you are working.');

        return response()->json([
            'success' => true,
            'message' => 'AI SDK is working!',
            'response' => $response,
            'provider' => config('ai.defaults.provider'),
            'timestamp' => now()->toDateTimeString(),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'AI SDK error',
            'error' => $e->getMessage(),
            'provider' => config('ai.defaults.provider'),
        ], 500);
    }
});
```

### Testing

**Step 1:** Make sure Sail is running:

```bash
./vendor/bin/sail up -d
```

**Step 2:** Make sure Ollama is running (in another terminal):

```bash
ollama serve
```

**Step 3:** Visit the test URL:

```
http://localhost/test-ai
```

Or using curl:

```bash
curl http://localhost/test-ai
```

### Expected Results

**Success Response (HTTP 200):**

```json
{
    "success": true,
    "message": "AI SDK is working!",
    "response": "Hello from Laravel AI SDK! I am working correctly.",
    "provider": "openai",
    "timestamp": "2025-01-13 10:30:45"
}
```

**Error Response (HTTP 500):**

```json
{
    "success": false,
    "message": "AI SDK error",
    "error": "Invalid API key provided",
    "provider": "openai"
}
```

### Troubleshooting

**Error: "Invalid API key"**

- Check your `.env` file has correct API keys
- Run: `php artisan config:clear`
- Verify: `php artisan tinker` → `env('OPENAI_API_KEY')`

**Error: "Could not resolve host"**

- Check your internet connection
- Verify provider status (OpenAI, Gemini, etc.)

**Error: "Connection refused" (Ollama)**

- Make sure `ollama serve` is running
- Check OLLAMA_URL in `.env` matches your setup
- Try: `curl http://localhost:11434/api/tags`

**Error: "Connection refused" (PostgreSQL)**

- Make sure Sail is running: `./vendor/bin/sail up -d`
- Check: `./vendor/bin/sail ps`

---

## Day 1 Checklist

Before moving to Day 2, verify:

- [ ] AI SDK installed (`composer show laravel/ai` shows the package)
- [ ] Configuration published (`config/ai.php` exists)
- [ ] Migrations run (database tables created)
- [ ] Sail installed with PostgreSQL (`docker-compose.yml` exists)
- [ ] PostgreSQL container running (`./vendor/bin/sail ps` shows pgsql)
- [ ] Ollama installed (`ollama --version` works)
- [ ] Models pulled (`ollama list` shows llama3.1:8b and nomic-embed-text)
- [ ] Ollama service running (`ollama serve` in a terminal)
- [ ] Environment variables configured (`.env` has API keys)
- [ ] Config file updated with providers
- [ ] Test route returns AI response (visit `/test-ai`)

---

## Next Steps

Congratulations! You've completed Day 1. Your AI SDK is:

- ✅ Installed and configured
- ✅ Connected to multiple providers
- ✅ Tested and working

**For Day 2, you'll:**

- Create your first AI agent
- Build a basic chat interface
- Learn about the `Promptable` trait

**Keep these running for Day 2:**

- Sail: `./vendor/bin/sail up -d`
- Ollama: `ollama serve`

---

## Additional Resources

- [Laravel AI SDK Docs](https://laravel.com/docs/12.x/ai-sdk)
- [Ollama Documentation](https://github.com/ollama/ollama/blob/main/docs/README.md)
- [OpenAI API Docs](https://platform.openai.com/docs)
- [Gemini API Docs](https://ai.google.dev/docs)

---

## Common Questions

**Q: Do I need all API keys?**  
A: No! Start with just Ollama (free) and one cloud provider (OpenAI or Gemini).

**Q: Can I use different models?**  
A: Yes! Edit `config/ai.php` to change model names. Just ensure the model exists for that provider.

**Q: How do I switch providers?**  
A: Change `AI_DEFAULT_PROVIDER` in `.env`, or override per-request (we'll learn this in Day 2).

**Q: Is Ollama slower than cloud providers?**  
A: Yes, especially on CPU. But it's free and private. For faster local inference, use a GPU.

---

**Once you've completed all tasks and the test route works, you're ready for Day 2!**
