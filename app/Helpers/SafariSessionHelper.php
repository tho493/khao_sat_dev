<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class SafariSessionHelper
{
    /**
     * Kiểm tra và xử lý session cho Safari
     *
     * @param Request $request
     * @return bool
     */
    public static function handleSafariSession(Request $request): bool
    {
        $userAgent = $request->header('User-Agent', '');
        $isSafari = self::isSafari($userAgent);
        $isWebKit = self::isWebKit($userAgent);

        if (!$isSafari && !$isWebKit) {
            return true; // Không phải Safari/WebKit
        }

        // Đảm bảo session được khởi tạo
        if (!$request->session()->isStarted()) {
            $request->session()->start();
        }

        // Kiểm tra session health
        $sessionId = $request->session()->getId();
        $csrfToken = $request->session()->token();

        if (empty($sessionId) || empty($csrfToken)) {
            self::regenerateSafariSession($request);
        }

        // Log session status
        self::logSafariSessionStatus($request, $sessionId, $csrfToken);

        return true;
    }

    /**
     * Kiểm tra nếu là Safari
     */
    public static function isSafari(string $userAgent): bool
    {
        return strpos($userAgent, 'Safari') !== false &&
            strpos($userAgent, 'Chrome') === false &&
            strpos($userAgent, 'Edge') === false;
    }

    /**
     * Kiểm tra nếu là WebKit
     */
    public static function isWebKit(string $userAgent): bool
    {
        return strpos($userAgent, 'AppleWebKit') !== false;
    }

    /**
     * Regenerate session cho Safari
     */
    protected static function regenerateSafariSession(Request $request): void
    {
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        Log::info('Safari: Regenerated session and CSRF token', [
            'user_agent' => $request->header('User-Agent'),
            'new_session_id' => $request->session()->getId(),
            'new_csrf_token' => $request->session()->token(),
        ]);
    }

    /**
     * Log session status cho Safari
     */
    protected static function logSafariSessionStatus(Request $request, string $sessionId, string $csrfToken): void
    {
        if (!Config::get('subdomain.safari.log_attempts', true)) {
            return;
        }

        Log::info('Safari Session Status', [
            'user_agent' => $request->header('User-Agent'),
            'session_id' => $sessionId,
            'csrf_token' => $csrfToken,
            'session_started' => $request->session()->isStarted(),
            'session_data_count' => count($request->session()->all()),
            'cookies_received' => $request->cookies->all(),
            'url' => $request->url(),
            'method' => $request->method(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Tạo meta tags cho Safari
     *
     * @return string
     */
    public static function generateSafariMetaTags(): string
    {
        $csrfToken = csrf_token();
        $sessionId = session()->getId();

        return "
        <meta name=\"csrf-token\" content=\"{$csrfToken}\">
        <meta name=\"session-id\" content=\"{$sessionId}\">
        <meta name=\"safari-compatibility\" content=\"enabled\">";
    }

    /**
     * Tạo JavaScript để xử lý Safari session
     *
     * @return string
     */
    public static function generateSafariSessionScript(): string
    {
        return "
        <script>
        // Safari Session Management
        (function() {
            const isSafari = (navigator.userAgent.indexOf('Safari') !== -1 && navigator.userAgent.indexOf('Chrome') === -1);
            const isWebKit = navigator.userAgent.indexOf('AppleWebKit') !== -1;
            
            if (isSafari || isWebKit) {
                console.log('Safari/WebKit detected - initializing session management');
                
                // Session keep-alive
                let sessionKeepAlive = setInterval(function() {
                    if (document.visibilityState === 'visible') {
                        fetch(window.location.href, {
                            method: 'HEAD',
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(function(response) {
                            if (response.status === 200) {
                                console.log('Safari session keep-alive successful');
                            }
                        }).catch(function(error) {
                            console.log('Safari session keep-alive failed:', error);
                        });
                    }
                }, 30000); // Every 30 seconds
                
                // Clear interval when page unloads
                window.addEventListener('beforeunload', function() {
                    clearInterval(sessionKeepAlive);
                });
                
                // Handle page visibility changes
                document.addEventListener('visibilitychange', function() {
                    if (document.visibilityState === 'visible') {
                        console.log('Safari: Page became visible - checking session');
                        // Trigger a session check
                        fetch(window.location.href, {
                            method: 'HEAD',
                            credentials: 'same-origin'
                        });
                    }
                });
            }
        })();
        </script>";
    }
}
