<?php

use App\Ai\Agents\ChatAgent;
use Laravel\Ai\Streaming\Events\TextDelta;

it('streams response when using stream method', function () {
    ChatAgent::fake(['Hello']);

    $agent = new ChatAgent;
    $stream = $agent->stream('Hi');

    $chunks = [];
    foreach ($stream as $chunk) {
        if ($chunk instanceof TextDelta) {
            $chunks[] = $chunk->delta;
        }
    }

    expect($chunks)->toBe(['Hello']);
});

it('can stream response to client via SSE', function () {
    ChatAgent::fake(['First chunk', ' second chunk']);

    $response = $this->get('/api/stream-chat?message=Hello');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
});

it('validates message in stream endpoint', function () {
    $response = $this->getJson('/api/stream-chat');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});
