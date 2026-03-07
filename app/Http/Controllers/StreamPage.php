<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class StreamPage extends Controller
{
    public function show()
    {
        return Inertia::render('StreamPage');
    }

    public function stream()
    {
        $message = request('message', 'Hello, this is a streamed response!');

        return response()->stream(function (): void {
            foreach (['developer', 'admin'] as $string) {
                echo $string;
                ob_flush();
                flush();
                sleep(1); // Simulate delay between chunks...
            }
        }, 200, ['X-Accel-Buffering' => 'no']);
    }
}
