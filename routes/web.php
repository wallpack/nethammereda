<?php

use App\Http\Controllers\Auth\TelegramSiteLoginPageController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/telegram', [TelegramSiteLoginPageController::class, 'show'])
    ->name('auth.telegram.page');
Route::get('/auth/telegram/callback', [TelegramSiteLoginPageController::class, 'callback'])
    ->name('auth.telegram.callback')
    ->middleware('throttle:30,1');
Route::get('/auth/telegram/token', [TelegramSiteLoginPageController::class, 'consumeToken'])
    ->name('auth.telegram.token')
    ->middleware('throttle:30,1');

Route::get('/', function () {
    return view('app');
});
