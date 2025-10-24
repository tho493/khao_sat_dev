<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebKitCookieMiddleware
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
        $isWebKit = strpos($userAgent, 'AppleWebKit') !== false;
        $isSafari = strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false;

        if ($isWebKit || $isSafari) {
            $this->handleWebKitCookies($request);
        }

        $response = $next($request);

        // Đảm bảo XSRF-TOKEN cookie được set đúng cho WebKit
        if ($isWebKit || $isSafari) {
            $this->setWebKitCookies($response, $request);
        }

        return $response;
    }

    /**
     * Xử lý cookies cho WebKit
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function handleWebKitCookies($request)
    {
        // Log cookie issues
        $cookies = $request->cookies->all();
        $sessionId = $request->session()->getId();
        $csrfToken = $request->session()->token();

        Log::info('WebKit Cookie Debug', [
            'user_agent' => $request->header('User-Agent'),
            'session_id' => $sessionId,
            'csrf_token' => $csrfToken,
            'cookies_received' => $cookies,
            'xsrf_cookie' => $request->cookie('XSRF-TOKEN'),
            'session_cookie' => $request->cookie(config('session.cookie')),
        ]);

        // Kiểm tra nếu XSRF-TOKEN cookie không khớp với session token
        $xsrfCookie = $request->cookie('XSRF-TOKEN');
        if ($xsrfCookie && $xsrfCookie !== $csrfToken) {
            Log::warning('WebKit: XSRF-TOKEN cookie mismatch', [
                'cookie_value' => $xsrfCookie,
                'session_token' => $csrfToken,
                'user_agent' => $request->header('User-Agent'),
            ]);
        }
    }

    /**
     * Set cookies cho WebKit
     *
     * @param  \Illuminate\Http\Response  $response
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function setWebKitCookies($response, $request)
    {
        $csrfToken = $request->session()->token();

        // Set XSRF-TOKEN cookie với cấu hình tối ưu cho WebKit
        $response->cookie(
            'XSRF-TOKEN',
            $csrfToken,
            0, // Expire when browser closes
            '/', // Path
            null, // Domain - không set để tránh vấn đề
            false, // Secure - false cho HTTP
            false, // HttpOnly - false để JS có thể đọc
            false, // Raw
            'lax' // SameSite
        );

        Log::info('WebKit: Set XSRF-TOKEN cookie', [
            'token' => $csrfToken,
            'user_agent' => $request->header('User-Agent'),
        ]);
    }
}
