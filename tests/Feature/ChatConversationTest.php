<?php

use App\Ai\Agents\ChatAgent;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Clean up any existing test conversations
    DB::table('agent_conversations')
        ->where('user_id', 'like', 'test-user-%')
        ->delete();
});

afterEach(function () {
    // Clean up test conversations
    DB::table('agent_conversations')
        ->where('user_id', 'like', 'test-user-%')
        ->delete();
});

it('saves conversation to database', function () {
    $user = (object) ['id' => 'test-user-123'];

    $agent = new ChatAgent;
    $agent->forUser($user);

    $response = $agent->prompt('Hello, my name is John');

    expect($response->text)->not->toBeEmpty();
    expect($agent->currentConversation())->not->toBeNull();

    // Verify conversation saved to database
    $conversation = DB::table('agent_conversations')
        ->where('user_id', 'test-user-123')
        ->first();

    expect($conversation)->not->toBeNull();

    // Verify messages
    $messages = DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversation->id)
        ->get();

    expect($messages)->toHaveCount(2); // User + Assistant
});

it('continues existing conversation', function () {
    $user = (object) ['id' => 'test-user-456'];

    // First message
    $agent = new ChatAgent;
    $agent->forUser($user);
    $response1 = $agent->prompt('My name is Jane');
    $conversationId = $agent->currentConversation();

    expect($conversationId)->not->toBeNull();

    // Second message - should remember context
    $agent2 = new ChatAgent;
    $agent2->continue($conversationId, $user);
    $response2 = $agent2->prompt('What is my name?');

    // The response should contain "Jane" if memory works
    expect(strtolower($response2->text))->toContain('jane');
});

it('stores messages with correct roles', function () {
    $user = (object) ['id' => 'test-user-789'];

    $agent = new ChatAgent;
    $agent->forUser($user);
    $agent->prompt('Hello');

    $conversation = DB::table('agent_conversations')
        ->where('user_id', 'test-user-789')
        ->first();

    $messages = DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversation->id)
        ->orderBy('created_at')
        ->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe('user');
    expect($messages[0]->content)->toBe('Hello');
    expect($messages[1]->role)->toBe('assistant');
    expect($messages[1]->content)->not->toBeEmpty();
});

it('creates new conversation via non-streaming endpoint', function () {
    $response = $this->postJson('/api/chat-with-memory', [
        'message' => 'Hello, my name is Test',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'conversation_id',
        ]);

    $conversationId = $response->json('conversation_id');
    expect($conversationId)->not->toBeNull();

    // Verify conversation exists in database
    $conversation = DB::table('agent_conversations')
        ->where('id', $conversationId)
        ->first();

    expect($conversation)->not->toBeNull();

    $messages = DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->count();

    expect($messages)->toBe(2);
});

it('continues conversation via non-streaming endpoint', function () {
    // First message
    $response1 = $this->postJson('/api/chat-with-memory', [
        'message' => 'My favorite color is blue',
    ]);

    $response1->assertStatus(200);
    $conversationId = $response1->json('conversation_id');

    // Second message in same session
    $response2 = $this->postJson('/api/chat-with-memory', [
        'message' => 'What is my favorite color?',
    ]);

    $response2->assertStatus(200);

    // Should remember the color
    $message = strtolower($response2->json('message'));
    expect($message)->toContain('blue');
});
