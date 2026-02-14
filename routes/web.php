<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Ai\Ai;
use Laravel\Fortify\Features;

use function Laravel\Ai\agent;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/test-ai', function () {
    try {
        // Try to generate a simple text response

        $response = agent(
            instructions: 'You are a helpful assistant.',
        )->prompt('Say "Hello from Laravel AI SDK!"');

        return response()->json([
            'success' => true,
            'message' => 'AI SDK is working!',
            'response' => $response,
            // 'provider' => config('ai.defaults.provider'),
            'timestamp' => now()->toDateTimeString(),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'AI SDK error',
            'error' => $e->getMessage(),
            // 'provider' => config('ai.defaults.provider'),
        ], 500);
    }
});

require __DIR__.'/settings.php';
