<?php

use App\Ai\Agents\ChatAgent;
use Illuminate\Foundation\Testing\TestCase;

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
    ChatAgent::fake([
        'This is a test response.',
    ]);

    $agent = new ChatAgent;
    $response = $agent->prompt('Test message');

    expect($response->text)->toBe('This is a test response.');

    ChatAgent::assertPrompted('Test message');
});

it('can use fake responses dynamically', function () {
    ChatAgent::fake(function ($prompt) {
        return "Response to: {$prompt}";
    });

    $agent = new ChatAgent;
    $response = $agent->prompt('Hello there');

    expect($response->text)->toBe('Response to: Hello there');
});

it('tracks all prompts when faked', function () {
    ChatAgent::fake();

    $agent = new ChatAgent;
    $agent->prompt('First message');
    $agent->prompt('Second message');

    ChatAgent::assertPrompted('First message');
    ChatAgent::assertPrompted('Second message');
});
