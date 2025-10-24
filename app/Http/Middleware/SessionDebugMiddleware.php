<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class SessionDebugMiddleware
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
            $sessionId = $request->session()->getId();
            $csrfToken = $request->session()->token();
            $sessionStarted = $request->session()->isStarted();
            
            // Log session info
            Log::info('Session Debug - WebKit/Safari', [
                'user_agent' => $userAgent,
                'session_id' => $sessionId,
                'csrf_token' => $csrfToken,
                'session_started' => $sessionStarted,
                'session_data' => $request->session()->all(),
                'cookies' => $request->cookies->all(),
                'headers' => [
                    'X-CSRF-TOKEN' => $request->header('X-CSRF-TOKEN'),
                    'X-XSRF-TOKEN' => $request->header('X-XSRF-TOKEN'),
                    'X-Requested-With' => $request->header('X-Requested-With'),
                ],
                'url' => $request->url(),
                'method' => $request->method(),
                'timestamp' => now()->toISOString(),
            ]);

            // Kiểm tra session health
            if (!$sessionStarted) {
                Log::warning('Session not started for WebKit/Safari', [
                    'user_agent' => $userAgent,
                    'url' => $request->url(),
                ]);
            }

            if (!$csrfToken) {
                Log::warning('No CSRF token for WebKit/Safari', [
                    'user_agent' => $userAgent,
                    'session_id' => $sessionId,
                    'url' => $request->url(),
                ]);
            }
        }

        return $next($request);
    }
}
