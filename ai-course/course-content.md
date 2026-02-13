# Laravel AI SDK Complete Tutorial

A comprehensive 10-day course to learn the Laravel AI SDK by building an "AI Playground" application.

## Course Overview

**Application:** AI Playground - A dashboard showcasing all Laravel AI SDK features  
**Duration:** 10 days, ~1 hour per day  
**Prerequisites:** PHP 8.4+, Composer, Node.js, PostgreSQL (via Sail)  
**Frontend:** Vue.js with Inertia  
**Providers:** OpenAI, Gemini, Groq, Ollama (local)

## What You'll Build

A single-page dashboard with 8 interactive sections demonstrating:

1. **💬 Basic Chat** - Simple AI conversation
2. **🧠 Smart Chat** - Conversation with memory/context
3. **🔍 Code Reviewer** - Structured JSON output from AI
4. **🛠️ Agent with Tools** - Web search and custom tools
5. **🎨 Image Studio** - AI image generation
6. **🔊 Audio Lab** - Text-to-speech and transcription
7. **📚 Knowledge Base Chat** - RAG (Chat with documents)
8. **🔎 Semantic Search** - Vector similarity search

## System Requirements

- **RAM:** 16GB (sufficient for local models)
- **Disk:** 800GB available
- **PHP:** 8.4+
- **Node.js:** Latest LTS
- **Docker:** For Laravel Sail with PostgreSQL

## Provider Configuration

### Free Tier Options

- **OpenAI:** $5-18 free trial credit
- **Gemini:** Generous free tier from Google
- **Groq:** Fast inference with daily limits
- **Ollama:** Completely free, runs locally

### Failover Strategy

Primary: OpenAI → Fallback: Gemini → Fallback: Ollama

---

## Day-by-Day Breakdown

### Day 1: Project Setup & AI SDK Installation

**Goal:** Install and configure the AI SDK with multiple providers

**Topics:**

- Installing `laravel/ai` package
- Publishing configuration and migrations
- Setting up Laravel Sail with PostgreSQL
- Installing Ollama locally
- Configuring environment variables
- Setting up provider failover chain

**Deliverable:** Working AI SDK with test endpoint

---

### Day 2: Basic Agent & Chat UI

**Goal:** Create your first AI agent and build a chat interface

**Topics:**

- Creating an agent with `php artisan make:agent`
- The `Promptable` trait
- Writing agent instructions
- Building a Vue chat component
- Handling synchronous responses
- Displaying AI messages in UI

**Deliverable:** Basic chat interface where you can send messages and get AI responses

---

### Day 3: Streaming Responses

**Goal:** Implement real-time streaming for instant-feeling interactions

**Topics:**

- Streaming vs synchronous responses
- The `stream()` method
- Server-Sent Events (SSE) in Laravel
- Handling streamed responses in Vue
- Vercel AI SDK protocol
- Displaying tokens as they arrive

**Deliverable:** Chat interface with typewriter effect (tokens appearing one by one)

---

### Day 4: Conversation Memory

**Goal:** Build agents that remember context across messages

**Topics:**

- The `Conversational` interface
- `RemembersConversations` trait
- Database conversation storage
- `forUser()` and `continue()` methods
- Loading conversation history
- Starting new conversations

**Deliverable:** Chat that remembers previous messages in the conversation

---

### Day 5: Structured Output

**Goal:** Get JSON/typed data from AI agents

**Topics:**

- `HasStructuredOutput` interface
- JSON Schema definition
- The `schema()` method
- Typed responses as arrays
- Building a code reviewer agent
- Displaying structured results

**Deliverable:** Code reviewer that returns scores, feedback, and suggestions as JSON

---

### Day 6: Tools (Custom & Provider)

**Goal:** Extend agents with capabilities

**Topics:**

- The `HasTools` interface
- Creating custom tools (Calculator, Weather, etc.)
- Provider tools: WebSearch, WebFetch
- Tool invocation and results
- `MaxSteps` attribute
- Multi-step reasoning

**Deliverable:** Agent that can search the web and use custom tools

---

### Day 7: Multimodal - Images, Audio, Transcription

**Goal:** Work with non-text AI capabilities

**Topics:**

- `Image::of()` for image generation
- Aspect ratios: `landscape()`, `portrait()`, `square()`
- `Audio::of()` for text-to-speech
- Voice selection: `male()`, `female()`, `voice()`
- `Transcription::fromStorage()` for speech-to-text
- File storage and retrieval
- Queuing heavy workloads

**Deliverable:** Interfaces for generating images, audio, and transcribing speech

---

### Day 8: Embeddings & Semantic Search

**Goal:** Implement semantic search with vector embeddings

**Topics:**

- What are embeddings?
- `toEmbeddings()` Stringable method
- `Embeddings::for()` for batch generation
- PostgreSQL pgvector extension
- Vector columns in migrations
- `whereVectorSimilarTo()` queries
- `Reranking::of()` for result ordering

**Deliverable:** Document upload system with semantic search (search by meaning)

---

### Day 9: Files & Vector Stores (RAG)

**Goal:** Build retrieval-augmented generation

**Topics:**

- `Stores::create()` for vector stores
- `Document::fromStorage()` for file handling
- `FileSearch` provider tool
- RAG (Retrieval-Augmented Generation)
- Uploading PDFs and documents
- Metadata and filtering
- Chat with your documents

**Deliverable:** "Chat with your documents" feature using uploaded files

---

### Day 10: Testing & Production Readiness

**Goal:** Write tests and polish the application

**Topics:**

- `Agent::fake()` for testing agents
- `Image::fake()`, `Audio::fake()`, etc.
- Assertions for AI interactions
- Testing streaming responses
- Testing structured output
- Failover configuration
- AI events and logging
- Error handling

**Deliverable:** Fully tested application with proper error handling

---

## Final Project Structure

```
ai-playground/
├── app/
│   ├── Ai/
│   │   ├── Agents/          # AI agent classes
│   │   └── Tools/           # Custom tools
│   └── Http/
│       └── Controllers/
│           └── Ai/          # AI feature controllers
├── config/
│   └── ai.php               # AI provider configuration
├── resources/
│   └── js/
│       └── pages/
│           └── Ai/          # Vue components for each feature
├── routes/
│   └── web.php              # AI routes
└── tests/
    └── Feature/
        └── Ai/              # AI feature tests
```

## Key Concepts Summary

### Agent Building Blocks

- **Instructions:** System prompt defining agent behavior
- **Context:** Previous messages (Conversational interface)
- **Tools:** Capabilities the agent can use
- **Output:** Text or structured (JSON schema)

### Multimodal APIs

- **Text:** Chat and completion
- **Image:** Generation from prompts
- **Audio:** Text-to-speech and speech-to-text
- **Embeddings:** Vector representations of text

### Advanced Features

- **Streaming:** Real-time response delivery
- **Failover:** Automatic provider switching
- **RAG:** Document-aware responses
- **Vector Search:** Semantic similarity queries

## Additional Resources

- [Laravel AI SDK Documentation](https://laravel.com/docs/12.x/ai-sdk)
- [Laravel AI GitHub](https://github.com/laravel/ai)
- [Reference App: Larrykonn](https://github.com/joshcirre/larrykonn)
- [Ollama Documentation](https://ollama.com/)

---

## Course Completion Checklist

- [ ] Day 1: AI SDK installed and configured
- [ ] Day 2: Basic chat working
- [ ] Day 3: Streaming responses implemented
- [ ] Day 4: Conversation memory working
- [ ] Day 5: Structured output demonstrated
- [ ] Day 6: Tools integrated
- [ ] Day 7: Images, audio, transcription working
- [ ] Day 8: Semantic search implemented
- [ ] Day 9: RAG with documents working
- [ ] Day 10: Tests written and passing

**Congratulations! You've mastered the Laravel AI SDK!**
