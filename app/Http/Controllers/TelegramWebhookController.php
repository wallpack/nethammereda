<?php

namespace App\Http\Controllers;

use App\Services\Telegram\UpdateHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        UpdateHandler $handler,
    ) {
        $update = $request->all();

        if (! is_array($update)) {
            return response()->json(['ok' => true]);
        }

        try {
            $handler->handle($update);
        } catch (Throwable $e) {
            Log::error('Telegram webhook update failed', [
                'exception' => $e->getMessage(),
                'update_id' => $update['update_id'] ?? null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'update_id' => $update['update_id'] ?? null,
        ]);
    }
}
