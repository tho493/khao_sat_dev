<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class WebKitSessionMiddleware
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
            $this->handleWebKitSession($request);
        }

        return $next($request);
    }

    /**
     * Xử lý session đặc biệt cho Apple WebKit
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function handleWebKitSession($request)
    {
        // Đảm bảo session được khởi tạo
        if (!$request->session()->isStarted()) {
            $request->session()->start();
        }

        // Kiểm tra và tạo session ID mới nếu cần
        $sessionId = $request->session()->getId();
        if (empty($sessionId)) {
            $request->session()->regenerate();
            Log::info('WebKit: Regenerated session ID', [
                'user_agent' => $request->header('User-Agent'),
                'new_session_id' => $request->session()->getId(),
            ]);
        }

        // Đảm bảo CSRF token tồn tại
        $csrfToken = $request->session()->token();
        if (empty($csrfToken)) {
            $request->session()->regenerateToken();
            Log::info('WebKit: Regenerated CSRF token', [
                'user_agent' => $request->header('User-Agent'),
                'new_token' => $request->session()->token(),
            ]);
        }

        // Đặt session data để đảm bảo session hoạt động
        if (!$request->session()->has('_webkit_session_initialized')) {
            $request->session()->put('_webkit_session_initialized', true);
            $request->session()->put('_webkit_last_activity', now()->timestamp);
        } else {
            $request->session()->put('_webkit_last_activity', now()->timestamp);
        }

        // Đặc biệt cho WebKit: Đảm bảo XSRF-TOKEN cookie được set đúng
        // Cookie sẽ được set trong WebKitCookieMiddleware

        // Log session health
        Log::info('WebKit Session Health Check', [
            'user_agent' => $request->header('User-Agent'),
            'session_id' => $request->session()->getId(),
            'csrf_token' => $request->session()->token(),
            'session_data_count' => count($request->session()->all()),
            'last_activity' => $request->session()->get('_webkit_last_activity'),
        ]);
    }
}
