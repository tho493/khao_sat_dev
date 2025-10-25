<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;

class SafariSessionFixMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $userAgent = $request->header('User-Agent', '');
        $isSafari = $this->isSafari($userAgent);
        $isWebKit = $this->isWebKit($userAgent);

        if ($isSafari || $isWebKit) {
            $this->forceSafariSession($request);
        }

        $response = $next($request);

        // Set cookies đặc biệt cho Safari
        if ($isSafari || $isWebKit) {
            $this->setSafariCookies($response, $request);
        }

        return $response;
    }

    /**
     * Kiểm tra nếu là Safari
     */
    protected function isSafari($userAgent)
    {
        return strpos($userAgent, 'Safari') !== false &&
            strpos($userAgent, 'Chrome') === false &&
            strpos($userAgent, 'Edge') === false;
    }

    /**
     * Kiểm tra nếu là WebKit
     */
    protected function isWebKit($userAgent)
    {
        return strpos($userAgent, 'AppleWebKit') !== false;
    }

    /**
     * Force Safari session - Xử lý mạnh mẽ cho Safari
     */
    protected function forceSafariSession($request)
    {
        // Force start session
        if (!$request->session()->isStarted()) {
            $request->session()->start();
        }

        // Force regenerate session ID nếu cần
        $sessionId = $request->session()->getId();
        if (empty($sessionId)) {
            $request->session()->regenerate();
            Log::info('Safari: Force regenerated session ID', [
                'user_agent' => $request->header('User-Agent'),
                'new_session_id' => $request->session()->getId(),
            ]);
        }

        // Force regenerate CSRF token
        $csrfToken = $request->session()->token();
        if (empty($csrfToken)) {
            $request->session()->regenerateToken();
            Log::info('Safari: Force regenerated CSRF token', [
                'user_agent' => $request->header('User-Agent'),
                'new_token' => $request->session()->token(),
            ]);
        }

        // Force set session data
        $request->session()->put('_safari_force_session', true);
        $request->session()->put('_safari_last_activity', now()->timestamp);
        $request->session()->put('_safari_user_agent', $request->header('User-Agent'));
        $request->session()->put('_safari_ip', $request->ip());

        // Force save session
        $request->session()->save();

        Log::info('Safari: Force session setup completed', [
            'user_agent' => $request->header('User-Agent'),
            'session_id' => $request->session()->getId(),
            'csrf_token' => $request->session()->token(),
            'session_data_count' => count($request->session()->all()),
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Set cookies đặc biệt cho Safari
     */
    protected function setSafariCookies($response, $request)
    {
        $csrfToken = $request->session()->token();
        $sessionId = $request->session()->getId();

        // Set XSRF-TOKEN cookie với cấu hình tối ưu cho Safari
        $response->cookie(
            'XSRF-TOKEN',
            $csrfToken,
            0, // Expire when browser closes
            '/', // Path
            null, // Domain - không set để tránh vấn đề Safari
            false, // Secure - false cho HTTP
            false, // HttpOnly - false để JS có thể đọc
            false, // Raw
            'lax' // SameSite - Safari yêu cầu 'lax'
        );

        // Set session cookie với cấu hình tối ưu cho Safari
        $sessionCookieName = config('session.cookie');
        $response->cookie(
            $sessionCookieName,
            $sessionId,
            0, // Expire when browser closes
            '/', // Path
            null, // Domain - không set để tránh vấn đề Safari
            false, // Secure - false cho HTTP
            true, // HttpOnly - true cho bảo mật
            false, // Raw
            'lax' // SameSite - Safari yêu cầu 'lax'
        );

        // Set thêm cookies để đảm bảo Safari nhận diện
        $response->cookie(
            'safari_session_active',
            'true',
            0,
            '/',
            null,
            false,
            false,
            false,
            'lax'
        );

        $response->cookie(
            'safari_csrf_token',
            $csrfToken,
            0,
            '/',
            null,
            false,
            false,
            false,
            'lax'
        );

        // Set cookie với tên khác để test
        $response->cookie(
            'laravel_session_test',
            'safari_working',
            0,
            '/',
            null,
            false,
            false,
            false,
            'lax'
        );

        Log::info('Safari: Set multiple cookies', [
            'session_id' => $sessionId,
            'csrf_token' => $csrfToken,
            'user_agent' => $request->header('User-Agent'),
            'cookies_set' => [
                'XSRF-TOKEN',
                $sessionCookieName,
                'safari_session_active',
                'safari_csrf_token',
                'laravel_session_test'
            ]
        ]);
    }
}
