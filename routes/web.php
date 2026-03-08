<?php

use App\Ai\Agents\ChatAgent;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\StreamPage;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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

// Streaming route must NOT use session/cookie middleware that buffers responses
Route::get('/api/stream-chat', [ChatController::class, 'streamChat']);
/* ->withoutMiddleware([
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
]); */

Route::post('/api/chat', [ChatController::class, 'chat'])->name('api.chat');

Route::post('/api/chat-with-memory', [ChatController::class, 'chatWithMemory'])
    ->name('api.chat-with-memory')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

Route::get('/stream-page', [StreamPage::class, 'show'])->name('stream-page.show');

Route::post('/stream', [StreamPage::class, 'stream'])->name('stream-page.post')->withoutMiddleware(ValidateCsrfToken::class);

require __DIR__.'/settings.php';
