# Day 1: Project Setup & AI SDK Installation

## Overview

Today you'll install and configure the Laravel AI SDK with multiple providers (Gemini and local Ollama - **OpenAI not required**). You'll also set up PostgreSQL with pgvector support via Laravel Sail.

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
- Installs provider drivers for Gemini, Groq, Ollama, etc. (OpenAI optional)

### Expected Output

Composer will install packages and show a success message. You'll see something like:

```
Installing laravel/ai (v0.x.x)
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

**Step 2:** Add Ollama service to your Docker configuration:

Open `compose.yaml` (or `docker-compose.yml`) and add the `ollama` service after the `redis` service:

```yaml
ollama:
    image: 'ollama/ollama:latest'
    container_name: sail-ollama
    ports:
        - '11434:11434'
    volumes:
        - 'ollama-data:/root/.ollama'
    environment:
        - OLLAMA_KEEP_ALIVE=24h
        - OLLAMA_ORIGINS=*
    networks:
        - sail
```

Also add the volume at the bottom in the `volumes:` section:

```yaml
volumes:
    sail-pgsql:
        driver: local
    sail-redis:
        driver: local
    ollama-data:
        driver: local
```

**Step 3:** Start the containers:

```bash
./vendor/bin/sail up -d
```

The `-d` flag runs containers in detached mode (background).

### What This Does

- Creates `compose.yaml` with PostgreSQL and Ollama services
- Configures pgvector extension in PostgreSQL
- Creates convenient Sail command aliases
- Sets up persistent volumes for database and models storage

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
ollama              Up
```

Test database connection:

```bash
./vendor/bin/sail artisan db:monitor
```

### Common Issues

**Issue:** Port 5432 already in use  
**Fix:** Change the port in `compose.yaml`:

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

## Task 4: Pull Ollama Models

**What we're doing:** Downloading AI models into the Ollama container.

### Instructions

**Pull text generation model:**

```bash
./vendor/bin/sail exec ollama ollama pull llama3.2:3b
```

This is a smaller 3B parameter model (~2GB) that works well with 16GB RAM.

**Pull embedding model:**

```bash
./vendor/bin/sail exec ollama ollama pull nomic-embed-text
```

This is a small model (~300MB) for generating text embeddings.

### Verification

List downloaded models:

```bash
./vendor/bin/sail exec ollama ollama list
```

You should see both models:

```
NAME                    ID              SIZE
llama3.2:3b             ...             2.0 GB
nomic-embed-text:latest ...             274 MB
```

Test Ollama:

```bash
./vendor/bin/sail exec ollama ollama run llama3.2:3b
```

Type a message when you see the `>>>` prompt:

```
>>> Hello, what is Laravel?
```

Type `/bye` to exit.

### Alternative Models (If Memory Issues)

If you get memory errors, use these smaller models:

```bash
# Even smaller text model (1.2GB)
./vendor/bin/sail exec ollama ollama pull llama3.2:1b

# Or tiny but capable (0.5GB)
./vendor/bin/sail exec ollama ollama pull phi3:mini
```

---

## Task 5: Configure Environment Variables

**What we're doing:** Setting up API keys for Gemini and Ollama connection.

### Instructions

Open your `.env` file and add these lines at the bottom:

```env
# ===========================================
# AI Provider API Keys (Free tiers)
# ===========================================

# Gemini - Get key at: https://aistudio.google.com/app/apikey
# Very generous free tier
GEMINI_API_KEY=your_gemini_key_here

# Optional: Groq for fast text inference
# Get key at: https://console.groq.com/keys
# GROQ_API_KEY=your_groq_key_here

# ===========================================
# Ollama (Local Models - running in Sail)
# ===========================================
OLLAMA_URL=http://ollama:11434

# ===========================================
# AI Configuration
# ===========================================
AI_DEFAULT_PROVIDER=gemini
# Optional: Configure specific providers for different AI tasks
AI_IMAGE_PROVIDER=gemini
AI_EMBEDDING_PROVIDER=ollama
```

### Getting Gemini API Key

1. Go to https://aistudio.google.com/app/apikey
2. Sign in with your Google account
3. Click "Create API Key"
4. Copy the key

**Note:** Gemini has a very generous free tier with high rate limits.

### Important Notes

- **Security:** Never commit your `.env` file with real API keys
- **Flexibility:** You only need Gemini + Ollama for this tutorial
- **Defaults:** You can set different default providers for text, images, audio, etc.

---

## Task 6: Configure AI Providers

**What we're doing:** Editing `config/ai.php` to set up providers with failover support.

### Instructions

**Step 1:** Open `config/ai.php` in your editor

**Step 2:** Find the `providers` array (around line 20-50) and update it:

```php
'providers' => [

    // Gemini - Primary cloud provider (generous free tier)
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
        'url' => env('OLLAMA_URL', 'http://ollama:11434'),
        'models' => [
            'text' => 'llama3.2:3b',
            'embedding' => 'nomic-embed-text',
        ],
    ],

    // Optional: Groq - Fast inference (uncomment if you have API key)
    // 'groq' => [
    //     'driver' => 'groq',
    //     'key' => env('GROQ_API_KEY'),
    //     'models' => [
    //         'text' => 'llama-3.2-3b-preview',
    //     ],
    // ],

],
```

**Step 3:** Update the default provider settings (near the top of the file):

Find these lines near the top (around line 16-21):

```php
'default' => 'openai',
'default_for_images' => 'openai',
'default_for_audio' => 'openai',
'default_for_transcription' => 'openai',
'default_for_embeddings' => 'openai',
'default_for_reranking' => 'cohere',
```

Update them to:

```php
'default' => env('AI_DEFAULT_PROVIDER', 'gemini'),
'default_for_images' => env('AI_IMAGE_PROVIDER', 'gemini'),
'default_for_audio' => env('AI_AUDIO_PROVIDER', 'gemini'),
'default_for_transcription' => env('AI_TRANSCRIPTION_PROVIDER', 'gemini'),
'default_for_embeddings' => env('AI_EMBEDDING_PROVIDER', 'ollama'),
'default_for_reranking' => env('AI_RERANKING_PROVIDER', 'cohere'),
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

**Default Providers:**

- `default`: Primary provider for most AI operations
- `default_for_images`: Provider for image generation
- `default_for_audio`: Provider for text-to-speech
- `default_for_transcription`: Provider for speech-to-text
- `default_for_embeddings`: Provider for vector embeddings
- `default_for_reranking`: Provider for result reranking

Each can be set independently to optimize for cost or performance!

### Configuration Options Explained

**url:**

- For Ollama: Uses Docker service name `ollama` for internal networking

**models:**

- `text`: Chat/completion models
- `image`: Image generation models
- `embedding`: Vector embedding models

### Verification

Test your configuration:

```bash
php artisan tinker
```

Then run:

```php
config('ai.providers.gemini.models.text')
```

Should output: `"gemini-pro"`

```php
config('ai.default')
```

Should output: `"gemini"`

Type `exit` to quit tinker.

---

## Task 7: Verify Everything Works

**What we're doing:** Creating a test route to verify the AI SDK is properly configured and can communicate with providers.

### Instructions

**Step 1:** Create a test agent

```bash
./vendor/bin/sail artisan make:agent TestAgent
```

**Step 2:** Open `app/Ai/Agents/TestAgent.php` and update it:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class TestAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are a helpful assistant that confirms systems are working.';
    }
}
```

**Step 3:** Add the test route to `routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Ai\Agents\TestAgent;

// ... existing routes ...

// AI Test Route
Route::get('/test-ai', function () {
    try {
        $agent = new TestAgent;
        $response = $agent->prompt('Say "Hello from Laravel AI SDK!" and confirm you are working.');

        return response()->json([
            'success' => true,
            'message' => 'AI SDK is working!',
            'response' => $response->text,
            'provider' => config('ai.default'),
            'timestamp' => now()->toDateTimeString(),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'AI SDK error',
            'error' => $e->getMessage(),
            'provider' => config('ai.default'),
        ], 500);
    }
});
```

### Testing

**Step 1:** Make sure Sail is running:

```bash
./vendor/bin/sail up -d
```

**Step 2:** Visit the test URL:

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
    "response": "Hello from Laravel AI SDK! I am working correctly. The AI SDK has been successfully installed and configured.",
    "provider": "gemini",
    "timestamp": "2025-01-13 10:30:45"
}
```

**Error Response (HTTP 500):**

```json
{
    "success": false,
    "message": "AI SDK error",
    "error": "Invalid API key provided",
    "provider": "gemini"
}
```

### Testing with Ollama (Without Gemini Key)

If you don't have a Gemini API key yet, you can test with Ollama only:

Update `.env` temporarily:

```env
AI_DEFAULT_PROVIDER=ollama
```

Then clear config cache:

```bash
./vendor/bin/sail artisan config:clear
```

Test again - it should use Ollama instead.

### Troubleshooting

**Error: "Invalid API key"**

- Check your `.env` file has correct `GEMINI_API_KEY`
- Run: `./vendor/bin/sail artisan config:clear`
- Verify: `./vendor/bin/sail artisan tinker` → `env('GEMINI_API_KEY')`

**Error: "Could not resolve host"**

- Check your internet connection
- Verify provider status (Gemini API status)

**Error: "Connection refused" (Ollama)**

- Make sure Sail is running: `./vendor/bin/sail up -d`
- Check Ollama container: `./vendor/bin/sail ps`
- Verify OLLAMA_URL in `.env` is `http://ollama:11434`
- Test: `./vendor/bin/sail exec ollama ollama list`

**Error: "model requires more system memory"**

- Use smaller model: `./vendor/bin/sail exec ollama ollama pull llama3.2:1b`
- Update config: `'text' => 'llama3.2:1b'`
- Or increase Docker memory in Docker Desktop settings

**Error: "Connection refused" (PostgreSQL)**

- Make sure Sail is running: `./vendor/bin/sail up -d`
- Check: `./vendor/bin/sail ps`

---

## Day 1 Checklist

Before moving to Day 2, verify:

- [ ] AI SDK installed (`composer show laravel/ai` shows the package)
- [ ] Configuration published (`config/ai.php` exists)
- [ ] Migrations run (database tables created)
- [ ] Sail installed with PostgreSQL (`compose.yaml` exists)
- [ ] Ollama service added to `compose.yaml`
- [ ] PostgreSQL container running (`./vendor/bin/sail ps` shows pgsql)
- [ ] Ollama container running (`./vendor/bin/sail ps` shows ollama)
- [ ] Models pulled (`./vendor/bin/sail exec ollama ollama list` shows models)
- [ ] Environment variables configured (`.env` has `GEMINI_API_KEY` and `OLLAMA_URL`)
- [ ] Config file updated with providers (using `env()` helpers)
- [ ] Test route returns AI response (visit `/test-ai`)

---

## Next Steps

Congratulations! You've completed Day 1. Your AI SDK is:

- ✅ Installed and configured
- ✅ Connected to Gemini and Ollama
- ✅ Tested and working

**For Day 2, you'll:**

- Create your first AI agent
- Build a basic chat interface
- Learn about the `Promptable` trait

**Keep this running for Day 2:**

- Sail: `./vendor/bin/sail up -d`

---

## Additional Resources

- [Laravel AI SDK Docs](https://laravel.com/docs/12.x/ai-sdk)
- [Ollama Documentation](https://github.com/ollama/ollama/blob/main/docs/README.md)
- [Gemini API Docs](https://ai.google.dev/docs)

---

## Common Questions

**Q: Do I need an OpenAI API key?**  
A: No! This tutorial uses Gemini (free tier) and Ollama (local, free).

**Q: Can I use different models?**  
A: Yes! Edit `config/ai.php` to change model names. Just ensure the model exists for that provider.

**Q: How do I switch providers?**  
A: Change `AI_DEFAULT_PROVIDER` in `.env`, or override per-request (we'll learn this in Day 2).

**Q: Is Ollama slower than cloud providers?**  
A: Yes, especially on CPU. But it's free and private. The 3B model is quite fast even on CPU.

**Q: Why use `http://ollama:11434` instead of `localhost`?**  
A: Inside Docker containers, you use service names (`ollama`) as hostnames. From your Mac, you'd use `localhost`.

---

**Once you've completed all tasks and the test route works, you're ready for Day 2!**
