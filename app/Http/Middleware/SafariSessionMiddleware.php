<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;

class SafariSessionMiddleware
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
            $this->handleSafariSession($request);
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
     * Xử lý session cho Safari
     */
    protected function handleSafariSession($request)
    {
        // Đảm bảo session được khởi tạo
        if (!$request->session()->isStarted()) {
            $request->session()->start();
        }

        // Kiểm tra session ID
        $sessionId = $request->session()->getId();
        if (empty($sessionId)) {
            $request->session()->regenerate();
            Log::info('Safari: Regenerated session ID', [
                'user_agent' => $request->header('User-Agent'),
                'new_session_id' => $request->session()->getId(),
            ]);
        }

        // Đảm bảo CSRF token
        $csrfToken = $request->session()->token();
        if (empty($csrfToken)) {
            $request->session()->regenerateToken();
            Log::info('Safari: Regenerated CSRF token', [
                'user_agent' => $request->header('User-Agent'),
                'new_token' => $request->session()->token(),
            ]);
        }

        // Đặt session data để đảm bảo session hoạt động
        if (!$request->session()->has('_safari_session_initialized')) {
            $request->session()->put('_safari_session_initialized', true);
            $request->session()->put('_safari_last_activity', now()->timestamp);
            $request->session()->put('_safari_user_agent', $request->header('User-Agent'));
        } else {
            $request->session()->put('_safari_last_activity', now()->timestamp);
        }

        // Log session health
        Log::info('Safari Session Health Check', [
            'user_agent' => $request->header('User-Agent'),
            'session_id' => $request->session()->getId(),
            'csrf_token' => $request->session()->token(),
            'session_data_count' => count($request->session()->all()),
            'last_activity' => $request->session()->get('_safari_last_activity'),
            'cookies_received' => $request->cookies->all(),
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

        // Set thêm cookie để đảm bảo Safari nhận diện
        $response->cookie(
            'safari_session_check',
            'active',
            0,
            '/',
            null,
            false,
            false,
            false,
            'lax'
        );

        Log::info('Safari: Set cookies', [
            'session_id' => $sessionId,
            'csrf_token' => $csrfToken,
            'user_agent' => $request->header('User-Agent'),
        ]);
    }
}
