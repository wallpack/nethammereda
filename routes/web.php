<?php

use App\Http\Controllers\Auth\TelegramSiteLoginPageController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/auth/telegram', [TelegramSiteLoginPageController::class, 'show'])
    ->name('auth.telegram.page');
Route::get('/auth/telegram/callback', [TelegramSiteLoginPageController::class, 'callback'])
    ->name('auth.telegram.callback')
    ->middleware('throttle:30,1');
Route::get('/auth/telegram/token', [TelegramSiteLoginPageController::class, 'consumeToken'])
    ->name('auth.telegram.token')
    ->middleware('throttle:30,1');

Route::post('/admin/logout-and-login', function (Request $request): RedirectResponse {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/admin/login');
})->middleware('auth:web')->name('admin.logout-and-login');

Route::get('/', function () {
    return view('app');
});
