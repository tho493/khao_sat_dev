<?php

use App\Http\Controllers\Api\ChatbotController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\KhaoSatController;

// Nạp các route dành cho admin:
require __DIR__ . '/admin.php';

// Authentication
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

Route::prefix('api')->name('api.')->group(function () {
    Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])->name('ask');
});

// Public routes
Route::prefix('')->name('khao-sat.')->group(function () {
    Route::get('/', [KhaoSatController::class, 'index'])->name('index');
    Route::post('/{dotKhaoSat}', [KhaoSatController::class, 'store'])->name('store');
    Route::get('/review', [KhaoSatController::class, 'review'])->name('review');
    Route::get('/thank-you', [KhaoSatController::class, 'thanks'])->name('thanks');
    Route::get('/{dotKhaoSat}', [KhaoSatController::class, 'show'])->name('show');
});

// Debug route for WebKit session testing
Route::get('/debug/session', function () {
    $userAgent = request()->header('User-Agent', '');
    $isWebKit = strpos($userAgent, 'AppleWebKit') !== false;
    $isSafari = strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false;

    return response()->json([
        'user_agent' => $userAgent,
        'is_webkit' => $isWebKit,
        'is_safari' => $isSafari,
        'session_id' => session()->getId(),
        'csrf_token' => session()->token(),
        'session_started' => session()->isStarted(),
        'session_data' => session()->all(),
        'cookies' => request()->cookies->all(),
        'session_config' => [
            'driver' => config('session.driver'),
            'lifetime' => config('session.lifetime'),
            'same_site' => config('session.same_site'),
            'secure' => config('session.secure'),
            'domain' => config('session.domain'),
        ],
        'timestamp' => now()->toISOString(),
    ]);
});