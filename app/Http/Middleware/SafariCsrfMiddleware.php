<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class SafariCsrfMiddleware extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Kiểm tra nếu Safari CSRF fix được bật
        if (Config::get('subdomain.safari.enabled', true)) {
            // Kiểm tra nếu là Safari browser hoặc Apple WebKit
            $userAgent = $request->header('User-Agent', '');
            $isSafari = strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false;
            $isWebKit = strpos($userAgent, 'AppleWebKit') !== false;

            if ($isSafari || $isWebKit) {
                // Đối với Safari/WebKit, thêm logic xử lý đặc biệt
                $this->handleSafariCsrf($request);
            }
        }

        return parent::handle($request, $next);
    }

    /**
     * Xử lý CSRF đặc biệt cho Safari/WebKit
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function handleSafariCsrf($request)
    {
        // Đảm bảo session được khởi tạo đúng cách cho Safari/WebKit
        if (!$request->session()->isStarted()) {
            $request->session()->start();
        }

        // Đặt lại CSRF token nếu cần thiết cho Safari/WebKit
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH') || $request->isMethod('DELETE')) {
            $token = $request->session()->token();

            // Kiểm tra token từ header X-CSRF-TOKEN (Safari/WebKit thường gửi qua header)
            $headerToken = $request->header('X-CSRF-TOKEN');
            $inputToken = $request->input('_token');

            if ($headerToken && hash_equals($token, $headerToken)) {
                // Token từ header hợp lệ
                return;
            }

            if ($inputToken && hash_equals($token, $inputToken)) {
                // Token từ input hợp lệ
                return;
            }

            // Đặc biệt cho Apple WebKit: kiểm tra X-XSRF-TOKEN header
            $xsrfToken = $request->header('X-XSRF-TOKEN');
            if ($xsrfToken && hash_equals($token, $xsrfToken)) {
                return;
            }

            // Nếu không có token hợp lệ, tạo token mới và gửi về client
            if (!$headerToken && !$inputToken && !$xsrfToken && Config::get('subdomain.safari.regenerate_token', true)) {
                $request->session()->regenerateToken();

                if (Config::get('subdomain.safari.log_attempts', true)) {
                    $userAgent = $request->header('User-Agent', '');
                    Log::info('Safari/WebKit CSRF: Regenerated token for request', [
                        'url' => $request->url(),
                        'method' => $request->method(),
                        'user_agent' => $userAgent,
                        'headers' => $request->headers->all()
                    ]);
                }
            }
        }
    }

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        $token = $this->getTokenFromRequest($request);

        return is_string($request->session()->token()) &&
            is_string($token) &&
            hash_equals($request->session()->token(), $token);
    }

    /**
     * Get the CSRF token from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function getTokenFromRequest($request)
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (!$token && $request->header('X-XSRF-TOKEN')) {
            $token = $this->encrypter->decrypt($request->header('X-XSRF-TOKEN'), static::serialized());
        }

        return $token;
    }
}
