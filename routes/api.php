<?php

use App\Http\Controllers\Api\CurrentCycleController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\MyFridgeController;
use App\Http\Controllers\Api\MyOrderController;
use App\Http\Controllers\Api\MyOrderItemController;
use App\Http\Controllers\Api\MyProfileController;
use App\Http\Controllers\Api\PasswordLoginController;
use App\Http\Controllers\Api\TelegramAuthController;
use App\Http\Controllers\Api\TelegramLoginController;
use App\Http\Controllers\Api\TelegramLinkController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\VerifyTelegramWebhookSecret;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/telegram', [TelegramAuthController::class, 'store'])
    ->middleware('throttle:30,1');
Route::post('/auth/telegram-login', [TelegramLoginController::class, 'store'])
    ->middleware('throttle:30,1');
Route::post('/auth/login', [PasswordLoginController::class, 'store'])
    ->middleware('throttle:10,1');
Route::get('/auth/telegram-login/config', [TelegramLoginController::class, 'config']);
Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware(VerifyTelegramWebhookSecret::class);

Route::get('/current-cycle', CurrentCycleController::class);
Route::get('/menu/categories', [MenuController::class, 'categories']);
Route::get('/menu/items', [MenuController::class, 'items']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json([
            'data' => $request->user(),
        ]);
    });
    Route::patch('/me/profile', [MyProfileController::class, 'update']);

    Route::post('/auth/logout', function (Request $request) {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken !== null && method_exists($accessToken, 'delete')) {
            $accessToken->delete();
        }

        return response()->json([
            'data' => [
                'ok' => true,
            ],
        ]);
    });

    Route::get('/my-order', [MyOrderController::class, 'show']);
    Route::get('/my-orders/history', [MyOrderController::class, 'history']);
    Route::post('/my-order/submit', [MyOrderController::class, 'submit']);
    Route::post('/my-order/reopen', [MyOrderController::class, 'reopen']);
    Route::post('/my-orders/{order}/repeat', [MyOrderController::class, 'repeat']);

    Route::post('/my-order/items', [MyOrderItemController::class, 'store']);
    Route::patch('/my-order/items/{orderItem}', [MyOrderItemController::class, 'update']);
    Route::delete('/my-order/items/{orderItem}', [MyOrderItemController::class, 'destroy']);

    Route::patch('/my-order/items/{orderItem}/mark-received', [MyOrderItemController::class, 'markReceived']);
    Route::patch('/my-order/items/{orderItem}/mark-eaten', [MyOrderItemController::class, 'markEaten']);

    Route::get('/my-fridge', [MyFridgeController::class, 'index']);
    Route::get('/my-fridge/history', [MyFridgeController::class, 'history']);
    Route::patch('/my-fridge/items/{fridgeItem}/eat-one', [MyFridgeController::class, 'eatOne']);
    Route::patch('/my-fridge/items/{fridgeItem}/eat-all', [MyFridgeController::class, 'eatAll']);
    Route::patch('/my-fridge/items/{fridgeItem}/discard', [MyFridgeController::class, 'discard']);

    Route::get('/telegram/link-status', [TelegramLinkController::class, 'status']);
    Route::post('/telegram/link-token', [TelegramLinkController::class, 'createToken']);
});
