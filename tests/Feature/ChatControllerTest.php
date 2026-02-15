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
    // Fake the AI agent response
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

    // Verify the agent was called
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
        ->assertJsonPath('error', fn (string $error) => str_contains($error, 'AI service unavailable'));
});
