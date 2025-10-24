<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class SafariCsrfHelper
{
    /**
     * Kiểm tra và xử lý CSRF token cho Safari
     *
     * @param Request $request
     * @return bool
     */
    public static function handleSafariCsrf(Request $request): bool
    {
        $userAgent = $request->header('User-Agent', '');
        $isSafari = strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false;
        $isWebKit = strpos($userAgent, 'AppleWebKit') !== false;

        if (!$isSafari && !$isWebKit) {
            return true; // Không phải Safari/WebKit, không cần xử lý đặc biệt
        }

        // Kiểm tra các header đặc biệt của Safari/WebKit
        $safariHeaders = [
            'X-Requested-With',
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN',
        ];

        $hasValidHeader = false;
        foreach ($safariHeaders as $header) {
            if ($request->hasHeader($header)) {
                $hasValidHeader = true;
                break;
            }
        }

        // Nếu Safari/WebKit không gửi header CSRF, thêm logic xử lý
        if (!$hasValidHeader) {
            self::logSafariIssue($request, 'Missing CSRF headers');

            // Tạo response với token mới
            if (Config::get('subdomain.safari.regenerate_token', true)) {
                $request->session()->regenerateToken();
                return true;
            }
        }

        return true;
    }

    /**
     * Log các vấn đề CSRF với Safari/WebKit
     *
     * @param Request $request
     * @param string $issue
     * @return void
     */
    public static function logSafariIssue(Request $request, string $issue): void
    {
        if (!Config::get('subdomain.safari.log_attempts', true)) {
            return;
        }

        Log::warning('Safari/WebKit CSRF Issue', [
            'issue' => $issue,
            'url' => $request->url(),
            'method' => $request->method(),
            'user_agent' => $request->header('User-Agent'),
            'headers' => $request->headers->all(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Tạo meta tag CSRF cho Safari
     *
     * @return string
     */
    public static function generateCsrfMetaTag(): string
    {
        $token = csrf_token();
        return "<meta name=\"csrf-token\" content=\"{$token}\">";
    }

    /**
     * Tạo JavaScript để xử lý CSRF cho Safari/WebKit
     *
     * @return string
     */
    public static function generateSafariCsrfScript(): string
    {
        return "
        <script>
        // Safari/WebKit CSRF Fix
        if ((navigator.userAgent.indexOf('Safari') !== -1 && navigator.userAgent.indexOf('Chrome') === -1) || 
            navigator.userAgent.indexOf('AppleWebKit') !== -1) {
            // Đảm bảo CSRF token được gửi trong mọi request
            const token = document.querySelector('meta[name=\"csrf-token\"]');
            if (token) {
                // Thêm token vào tất cả form
                document.addEventListener('DOMContentLoaded', function() {
                    const forms = document.querySelectorAll('form');
                    forms.forEach(function(form) {
                        if (!form.querySelector('input[name=\"_token\"]')) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = '_token';
                            input.value = token.getAttribute('content');
                            form.appendChild(input);
                        }
                    });
                });
                
                // Thêm token vào AJAX requests
                const originalFetch = window.fetch;
                window.fetch = function(url, options = {}) {
                    options.headers = options.headers || {};
                    options.headers['X-CSRF-TOKEN'] = token.getAttribute('content');
                    options.headers['X-Requested-With'] = 'XMLHttpRequest';
                    return originalFetch(url, options);
                };
                
                // Cải thiện jQuery AJAX cho WebKit
                if (window.jQuery) {
                    $.ajaxSetup({
                        beforeSend: function(xhr, settings) {
                            if (settings.type === 'POST' || settings.type === 'PUT' || settings.type === 'PATCH' || settings.type === 'DELETE') {
                                xhr.setRequestHeader('X-CSRF-TOKEN', token.getAttribute('content'));
                                xhr.setRequestHeader('X-XSRF-TOKEN', token.getAttribute('content'));
                                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                            }
                        }
                    });
                }
                
                // Đặc biệt cho WebKit: Override XMLHttpRequest
                const originalXHR = window.XMLHttpRequest;
                window.XMLHttpRequest = function() {
                    const xhr = new originalXHR();
                    const originalOpen = xhr.open;
                    const originalSend = xhr.send;
                    
                    xhr.open = function(method, url, async, user, password) {
                        originalOpen.call(this, method, url, async, user, password);
                        if (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE') {
                            this.setRequestHeader('X-CSRF-TOKEN', token.getAttribute('content'));
                            this.setRequestHeader('X-XSRF-TOKEN', token.getAttribute('content'));
                            this.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        }
                    };
                    
                    return xhr;
                };
            }
        }
        </script>";
    }
}
