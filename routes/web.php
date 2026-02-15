<?php

use App\Ai\Agents\ChatAgent;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Ai\Ai;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/chat', function () {
    return Inertia::render('Chat');
});

Route::get('/test-agent', function () {
    try {
        $agent = new ChatAgent;

        $response = $agent->prompt('When the men arrived the first time to the moon?');

        return response()->json([
            'success' => true,
            'message' => 'AI SDK is working!',
            'response' => $response->text,
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

Route::post('/api/chat', [ChatController::class, 'chat'])->name('api.chat');

require __DIR__.'/settings.php';
